<?php
namespace WPSMM\Services;

use WPSMM\Repositories\SiteRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class MonitorService
{
    public static function checkAll(bool $manual = false): void
    {
        foreach (SiteRepository::all() as $site) {
            self::check((int) $site->id, $manual);
        }
        self::cleanupOldLogs();
    }

    public static function check(int $siteId, bool $manual = false): array
    {
        global $wpdb;
        $site = SiteRepository::find($siteId);
        if (!$site) {
            return ['ok' => false, 'message' => 'Website không tồn tại'];
        }
        if (!SecurityService::publicHttpUrl((string) $site->url)) {
            return ['ok' => false, 'message' => 'URL không hợp lệ hoặc không phải HTTP/HTTPS công khai'];
        }
        $started = microtime(true);
        $timeout = max(5, (int) get_option('wpsmm_timeout', 15));
        $res = wp_remote_get($site->url, [
            'timeout' => $timeout,
            'redirection' => 5,
            'sslverify' => true,
            'headers' => ['User-Agent' => 'WPSMM/' . WPSMM_VERSION]
        ]);
        $responseTime = round(microtime(true) - $started, 3);
        $httpCode = 0;
        $status = 'offline';
        $message = '';
        $body = '';
        if (is_wp_error($res)) {
            $message = $res->get_error_message();
        } else {
            $httpCode = (int) wp_remote_retrieve_response_code($res);
            $body = (string) wp_remote_retrieve_body($res);
            $status = self::statusFromCode($httpCode, (int) $site->expected_status);
            $message = $status === 'online' ? 'OK' : 'HTTP ' . $httpCode;
            if ($status === 'online') {
                $titleStatus = self::checkTitle($body, (string) $site->expected_title);
                if ($titleStatus) {
                    $status = 'title_changed';
                    $message = $titleStatus;
                }
                $keywordStatus = self::checkSuspiciousKeywords($body);
                if ($keywordStatus) {
                    $status = 'suspicious';
                    $message = $keywordStatus;
                }
            }
        }
        $ssl = self::sslInfo((string) $site->url);
        if ($ssl['status'] === 'ssl_error') {
            $status = 'ssl_error';
            $message = $ssl['message'];
        } elseif ($ssl['status'] === 'ssl_expiring' && $status === 'online') {
            $status = 'ssl_expiring';
            $message = $ssl['message'];
        }
        $bad = !in_array($status, ['online', 'redirect'], true);
        $consecutive = $bad ? ((int) $site->consecutive_errors + 1) : 0;
        $health = self::healthScore($status, $responseTime, $ssl['days_left']);
        $wpdb->insert(DatabaseService::table('logs'), [
            'site_id' => $siteId,
            'status' => $status,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'message' => $message,
            'checked_at' => current_time('mysql')
        ]);
        $period = self::siteUptime($siteId, 30);
        $wpdb->update(DatabaseService::table('sites'), [
            'status' => $status,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'uptime_percent' => $period['uptime'],
            'downtime_minutes' => $period['downtime_minutes'],
            'ssl_expiry' => $ssl['expiry'],
            'ssl_days_left' => $ssl['days_left'],
            'health_score' => $health,
            'consecutive_errors' => $consecutive,
            'last_error' => $bad ? $message : '',
            'last_checked' => current_time('mysql')
        ], ['id' => $siteId]);
        if ($consecutive >= max(1, (int) get_option('wpsmm_error_threshold', 2))) {
            NotificationService::alert('Website cảnh báo', sprintf('%s - %s - HTTP %s - %s', $site->name, $site->url, $httpCode, $message));
        }
        return ['ok' => true, 'status' => $status, 'http_code' => $httpCode, 'response_time' => $responseTime, 'message' => $message];
    }

    public static function cleanupOldLogs(): void
    {
        global $wpdb;
        $days = max(1, (int) get_option('wpsmm_log_retention_days', 7));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . DatabaseService::table('logs') . ' WHERE checked_at < DATE_SUB(%s, INTERVAL %d DAY)', current_time('mysql'), $days));
    }

    public static function stats(): array
    {
        global $wpdb;
        $table = DatabaseService::table('sites');
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $online = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('online','redirect')");
        $offline = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('offline','server_error','ssl_error')");
        $warning = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status IN ('client_error','not_found','title_changed','suspicious','ssl_expiring')");
        $unknown = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='unknown'");
        $avgResponse = (float) $wpdb->get_var("SELECT AVG(response_time) FROM $table WHERE response_time > 0");
        $avgHealth = $total ? (int) $wpdb->get_var("SELECT AVG(health_score) FROM $table") : 0;
        return compact('total', 'online', 'offline', 'warning', 'unknown', 'avgResponse', 'avgHealth');
    }

    public static function chartData(int $hours = 24): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE_FORMAT(checked_at, '%%H:00') as label, AVG(response_time) as response, SUM(status IN ('online','redirect')) as online_count, COUNT(*) as total_count FROM " . DatabaseService::table('logs') . " WHERE checked_at >= DATE_SUB(%s, INTERVAL %d HOUR) GROUP BY DATE_FORMAT(checked_at, '%%Y-%%m-%%d %%H') ORDER BY MIN(checked_at) ASC", current_time('mysql'), $hours), ARRAY_A) ?: [];
        return array_map(static function ($r) {
            return ['label' => $r['label'], 'response' => round((float) $r['response'], 3), 'uptime' => $r['total_count'] ? round(((int) $r['online_count'] / (int) $r['total_count']) * 100, 1) : 0]; }, $rows);
    }

    public static function siteUptime(int $siteId, int $days = 30): array
    {
        global $wpdb;
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DatabaseService::table('logs') . ' WHERE site_id=%d AND checked_at >= DATE_SUB(%s, INTERVAL %d DAY)', $siteId, current_time('mysql'), $days));
        if (!$total) {
            return ['uptime' => 0, 'downtime_minutes' => 0];
        }
        $up = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . DatabaseService::table('logs') . " WHERE site_id=%d AND checked_at >= DATE_SUB(%s, INTERVAL %d DAY) AND status IN ('online','redirect')", $siteId, current_time('mysql'), $days));
        return ['uptime' => round(($up / $total) * 100, 2), 'downtime_minutes' => ($total - $up) * 2];
    }

    private static function statusFromCode(int $code, int $expected): string
    {
        if ($code === $expected) {
            return 'online';
        }
        if ($code >= 300 && $code < 400) {
            return 'redirect';
        }
        if ($code === 404) {
            return 'not_found';
        }
        if ($code >= 400 && $code < 500) {
            return 'client_error';
        }
        if ($code >= 500) {
            return 'server_error';
        }
        return 'offline';
    }
    private static function checkTitle(string $body, string $expected): string
    {
        if ($expected === '') {
            return '';
        }
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m);
        $current = trim(wp_strip_all_tags($m[1] ?? ''));
        return ($current && stripos($current, $expected) === false) ? 'Title thay đổi: ' . $current : '';
    }
    private static function checkSuspiciousKeywords(string $body): string
    {
        $keywords = array_filter(array_map('trim', explode(',', (string) get_option('wpsmm_suspicious_keywords', 'casino,betting,viagra,porn,บาคาร่า,พนัน'))));
        $plain = strtolower(wp_strip_all_tags(substr($body, 0, 200000)));
        foreach ($keywords as $kw) {
            if ($kw !== '' && strpos($plain, strtolower($kw)) !== false) {
                return 'Phát hiện keyword nghi vấn: ' . $kw;
            }
        }
        return '';
    }
    private static function sslInfo(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return ['status' => 'ok', 'expiry' => null, 'days_left' => null, 'message' => ''];
        }
        $warning = max(1, (int) get_option('wpsmm_ssl_warning_days', 14));
        if (!SecurityService::isPublicHost($host)) {
            return ['status' => 'ssl_error', 'expiry' => null, 'days_left' => null, 'message' => 'SSL host không public'];
        }
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true]]);
        $client = @stream_socket_client('ssl://' . $host . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
        if (!$client) {
            return ['status' => 'ssl_error', 'expiry' => null, 'days_left' => null, 'message' => 'Không kiểm tra được SSL: ' . $errstr];
        }
        $params = stream_context_get_params($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['status' => 'ssl_error', 'expiry' => null, 'days_left' => null, 'message' => 'Không đọc được SSL certificate'];
        }
        $parsed = openssl_x509_parse($cert);
        $validTo = (int) ($parsed['validTo_time_t'] ?? 0);
        $days = $validTo ? (int) floor(($validTo - time()) / DAY_IN_SECONDS) : null;
        $expiry = $validTo ? gmdate('Y-m-d H:i:s', $validTo) : null;
        if ($days !== null && $days < 0) {
            return ['status' => 'ssl_error', 'expiry' => $expiry, 'days_left' => $days, 'message' => 'SSL đã hết hạn'];
        }
        if ($days !== null && $days <= $warning) {
            return ['status' => 'ssl_expiring', 'expiry' => $expiry, 'days_left' => $days, 'message' => 'SSL sắp hết hạn còn ' . $days . ' ngày'];
        }
        return ['status' => 'ok', 'expiry' => $expiry, 'days_left' => $days, 'message' => ''];
    }
    private static function healthScore(string $status, float $response, $sslDays): int
    {
        $score = 100;
        if (!in_array($status, ['online', 'redirect'], true)) {
            $score -= 45;
        }
        if (in_array($status, ['suspicious', 'ssl_error'], true)) {
            $score -= 30;
        }
        if ($response > 3) {
            $score -= 20;
        } elseif ($response > 1.5) {
            $score -= 10;
        }
        if ($sslDays !== null && $sslDays <= 14) {
            $score -= 15;
        }
        return max(0, min(100, $score));
    }
}
