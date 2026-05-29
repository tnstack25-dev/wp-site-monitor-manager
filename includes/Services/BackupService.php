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
            $wpdb->insert(DatabaseService::table('backups'), ['site_id' => (int) $siteId, 'job_id' => $job, 'file_name' => $fileName, 'status' => 'queued', 'progress' => 0, 'message' => 'Đang chờ backup nền', 'created_at' => current_time('mysql')]);
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
            $wpdb->update($table, ['status' => 'failed', 'message' => 'Website không tồn tại', 'finished_at' => current_time('mysql')], ['id' => $backupId]);
            return;
        }
        $wpdb->update($table, ['status' => 'running', 'progress' => 10, 'message' => 'Đang chuẩn bị file backup'], ['id' => $backupId]);
        $dir = SecurityService::prepareBackupDir();
        $fileName = self::safeFileName($site->url, current_time('Ymd-His') . '-' . wp_generate_password(16, false, false)) . '.zip';
        $filePath = $dir . $fileName;
        $wpdb->update($table, ['file_name' => $fileName, 'file_path' => $filePath, 'progress' => 25, 'message' => 'Đang nén source code'], ['id' => $backupId]);
        $ok = self::zipCurrentSite($filePath);
        if (!$ok) {
            $wpdb->update($table, ['status' => 'failed', 'progress' => 100, 'message' => 'Không tạo được file zip. Kiểm tra quyền ghi uploads/wpsmm-backups.', 'finished_at' => current_time('mysql')], ['id' => $backupId]);
            return;
        }
        $wpdb->update($table, ['progress' => 75, 'message' => 'Đã tạo backup local, đang xử lý retention'], ['id' => $backupId]);
        $size = file_exists($filePath) ? filesize($filePath) : 0;
        $wpdb->update($table, ['status' => 'success', 'progress' => 100, 'file_size' => $size, 'message' => 'Backup hoàn tất', 'finished_at' => current_time('mysql')], ['id' => $backupId]);
        self::cleanupLocalRetention();
    }
    private static function zipCurrentSite(string $filePath): bool
    {
        if (!class_exists('ZipArchive')) {
            return false;
        }
        $root = ABSPATH;
        $zip = new \ZipArchive();
        if ($zip->open($filePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        $exclude = ['wp-content/uploads/wpsmm-backups', 'wp-config.php', '.env', '.user.ini'];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        $count = 0;
        foreach ($files as $file) {
            if ($count++ > 8000) {
                break;
            }
            $path = $file->getPathname();
            $rel = ltrim(str_replace($root, '', $path), '/\\');
            foreach ($exclude as $ex) {
                $normalized = str_replace('\\', '/', $rel);
                if ($normalized === $ex || strpos($normalized, trailingslashit($ex)) === 0) {
                    continue 2;
                }
            }
            if ($file->isFile() && $file->getSize() < 50 * 1024 * 1024) {
                $zip->addFile($path, $rel);
            }
        }
        $zip->addFromString('wpsmm-backup-info.json', wp_json_encode(['created_at' => current_time('mysql'), 'site' => home_url()], JSON_PRETTY_PRINT));
        return $zip->close();
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
