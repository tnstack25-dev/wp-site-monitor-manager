<?php
namespace WPSMM\Services;

use WPSMM\Repositories\SiteRepository;
use WPSMM\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

final class BackupService
{
    public static function reschedule(): void
    {
        wp_clear_scheduled_hook(Plugin::BACKUP_CRON);
        $schedule = get_option('wpsmm_backup_schedule', 'daily');
        if ($schedule === 'disabled') {
            return;
        }
        $hour = (string) get_option('wpsmm_backup_hour', '02:00');
        [$h, $m] = array_pad(explode(':', $hour), 2, 0);
        $next = strtotime('today ' . absint($h) . ':' . absint($m));
        if ($next <= time()) {
            $next = strtotime('+1 day', $next);
        }
        $recurrence = $schedule === 'weekly' ? 'wpsmm_weekly' : 'daily';
        wp_schedule_event($next, $recurrence, Plugin::BACKUP_CRON);
    }

    public static function queue(array $siteIds): string
    {
        global $wpdb;
        $job = wp_generate_uuid4();
        foreach ($siteIds as $siteId) {
            $site = SiteRepository::find((int) $siteId);
            if (!$site) {
                continue;
            }
            $fileName = self::safeFileName($site->url, 'queued-' . wp_generate_password(12, false, false)) . '.zip';
            $wpdb->insert(DatabaseService::table('backups'), [
                'site_id' => (int) $siteId,
                'job_id' => $job,
                'file_name' => $fileName,
                'status' => 'queued',
                'progress' => 0,
                'message' => 'Waiting for child site backup',
                'created_at' => current_time('mysql'),
            ]);
        }
        wp_schedule_single_event(time() + 5, 'wpsmm_async_backup', [$job]);
        return $job;
    }

    public static function runScheduled(): void
    {
        $ids = array_map('intval', (array) get_option('wpsmm_backup_site_ids', []));
        if (!$ids) {
            $ids = array_map(static fn($s) => (int) $s->id, SiteRepository::all());
        }
        if ($ids) {
            self::queue($ids);
        }
    }

    public static function runQueued(string $jobId): void
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('backups') . ' WHERE job_id=%s AND status=%s', $jobId, 'queued')) ?: [];
        foreach ($rows as $row) {
            self::runOne((int) $row->id);
        }
    }

    public static function runOne(int $backupId): void
    {
        global $wpdb;
        $table = DatabaseService::table('backups');
        $backup = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", $backupId));
        if (!$backup) {
            return;
        }
        $site = SiteRepository::find((int) $backup->site_id);
        if (!$site) {
            $wpdb->update($table, ['status' => 'failed', 'message' => 'Monitored site does not exist.', 'finished_at' => current_time('mysql')], ['id' => $backupId]);
            return;
        }

        $wpdb->update($table, ['status' => 'running', 'progress' => 10, 'message' => 'Requesting backup from child agent'], ['id' => $backupId]);
        $dir = SecurityService::prepareBackupDir();
        $fileName = self::safeFileName($site->url, current_time('Ymd-His') . '-' . wp_generate_password(16, false, false)) . '.zip';
        $filePath = $dir . $fileName;
        $wpdb->update($table, ['file_name' => $fileName, 'file_path' => $filePath, 'progress' => 25, 'message' => 'Child agent is creating source ZIP'], ['id' => $backupId]);
        $result = self::fetchRemoteAgentBackup($site, $filePath);
        if (is_wp_error($result)) {
            @unlink($filePath);
            $wpdb->update($table, ['status' => 'failed', 'progress' => 100, 'file_path' => '', 'message' => $result->get_error_message(), 'finished_at' => current_time('mysql')], ['id' => $backupId]);
            return;
        }

        $wpdb->update($table, ['progress' => 75, 'message' => 'Child site ZIP downloaded. Applying retention policy.'], ['id' => $backupId]);
        $size = file_exists($filePath) ? filesize($filePath) : 0;
        $wpdb->update($table, ['status' => 'success', 'progress' => 100, 'file_size' => $size, 'message' => 'Child site source backup complete.', 'finished_at' => current_time('mysql')], ['id' => $backupId]);
        self::cleanupLocalRetention();
    }

    private static function fetchRemoteAgentBackup(object $site, string $filePath)
    {
        $baseUrl = SecurityService::publicHttpUrl((string) $site->url);
        $secret = SecurityService::decryptSecret((string) ($site->backup_secret ?? ''));
        if ($baseUrl === '') {
            return new \WP_Error('wpsmm_backup_url', 'Child site URL must resolve to a public HTTP or HTTPS address.');
        }
        if ($secret === '') {
            return new \WP_Error('wpsmm_backup_secret', 'Child agent secret is missing. Save the same secret in the manager site record and child agent settings.');
        }

        $body = wp_json_encode(['type' => 'source']);
        $time = time();
        $response = wp_remote_post(trailingslashit($baseUrl) . 'wp-json/wpma/v1/backup', [
            'timeout' => 300,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WPSMM-Time' => $time,
                'X-WPSMM-Signature' => hash_hmac('sha256', $time . '.' . $body, $secret),
            ],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            return new \WP_Error('wpsmm_backup_agent', 'Child agent backup request failed: ' . $response->get_error_message());
        }

        $data = json_decode((string) wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200 || !is_array($data) || empty($data['success']) || empty($data['token']) || empty($data['download_url'])) {
            return new \WP_Error('wpsmm_backup_agent', 'Child agent did not create a backup. HTTP ' . $code . '. ' . self::responseMessage($data, $response));
        }

        $token = (string) $data['token'];
        $downloadUrl = SecurityService::publicHttpUrl((string) $data['download_url']);
        if (!preg_match('/^[A-Za-z0-9_-]{32,80}$/', $token) || $downloadUrl === '') {
            return new \WP_Error('wpsmm_backup_agent', 'Child agent returned an invalid download response.');
        }

        $downloadTime = time();
        $download = wp_remote_get($downloadUrl, [
            'timeout' => 300,
            'stream' => true,
            'filename' => $filePath,
            'headers' => [
                'X-WPSMM-Time' => $downloadTime,
                'X-WPSMM-Signature' => hash_hmac('sha256', $downloadTime . '.' . $token, $secret),
            ],
        ]);
        if (is_wp_error($download)) {
            return new \WP_Error('wpsmm_backup_download', 'Cannot download child backup: ' . $download->get_error_message());
        }
        $downloadCode = wp_remote_retrieve_response_code($download);
        $actualSize = is_file($filePath) ? (int) filesize($filePath) : 0;
        $expectedSize = max(0, (int) ($data['file_size'] ?? 0));
        if ($downloadCode !== 200 || $actualSize <= 0) {
            $detail = $actualSize > 0 ? sanitize_text_field(substr((string) file_get_contents($filePath, false, null, 0, 300), 0, 300)) : 'No response body was written.';
            return new \WP_Error('wpsmm_backup_download', 'Child backup download failed. HTTP ' . $downloadCode . ', expected ' . $expectedSize . ' bytes, received ' . $actualSize . ' bytes. ' . $detail);
        }
        if ($expectedSize > 0 && $actualSize !== $expectedSize) {
            return new \WP_Error('wpsmm_backup_download', 'Child backup download was incomplete. Expected ' . $expectedSize . ' bytes, received ' . $actualSize . ' bytes.');
        }
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($filePath, \ZipArchive::CHECKCONS) !== true) {
                return new \WP_Error('wpsmm_backup_invalid', 'Downloaded child backup is not a valid ZIP file.');
            }
            $zip->close();
        }
        return true;
    }

    private static function responseMessage($data, $response): string
    {
        if (is_array($data) && !empty($data['message'])) {
            return sanitize_text_field((string) $data['message']);
        }
        $body = sanitize_text_field(substr((string) wp_remote_retrieve_body($response), 0, 300));
        return $body !== '' ? $body : 'No error details returned.';
    }

    private static function safeFileName(string $url, string $suffix): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: preg_replace('~https?://~', '', $url);
        $host = preg_replace('/[^a-zA-Z0-9\-_\.]/', '-', $host);
        return 'wpsmm-backup-' . trim($host, '-') . '-' . $suffix;
    }

    private static function cleanupLocalRetention(): void
    {
        global $wpdb;
        $keep = max(1, (int) get_option('wpsmm_backup_retention', 3));
        $rows = $wpdb->get_results('SELECT * FROM ' . DatabaseService::table('backups') . " WHERE status='success' AND file_path <> '' ORDER BY created_at DESC") ?: [];
        $i = 0;
        foreach ($rows as $row) {
            $i++;
            if ($i > $keep && is_file($row->file_path) && SecurityService::backupPathAllowed((string) $row->file_path)) {
                @unlink($row->file_path);
            }
        }
    }
}
