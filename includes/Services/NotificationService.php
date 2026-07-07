<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class NotificationService
{
    private const PRIORITY_MAP = [
        'P1' => ['offline', 'server_error', 'ssl_error'],
        'P2' => ['partial', 'not_found'],
        'P3' => ['client_error', 'title_changed', 'suspicious', 'ssl_expiring'],
    ];

    public static function alert(string $title, string $message): void
    {
        $text = '[' . $title . '] ' . $message;
        if (get_option('wpsmm_enable_email_alert') && ($email = get_option('wpsmm_alert_email'))) {
            wp_mail($email, $title, $message);
        }
        $token = SecurityService::decryptSecret((string) get_option('wpsmm_telegram_bot_token', ''));
        $chat = (string) get_option('wpsmm_telegram_chat_id', '');
        if (get_option('wpsmm_enable_telegram_alert', ($token && $chat) ? 1 : 0) && $token && $chat) {
            wp_remote_post('https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage', [
                'timeout' => 10,
                'body' => ['chat_id' => $chat, 'text' => $text],
            ]);
        }
        if (get_option('wpsmm_enable_zalo_alert') && ($url = SecurityService::decryptSecret((string) get_option('wpsmm_zalo_webhook_url')))) {
            wp_safe_remote_post($url, ['timeout' => 10, 'redirection' => 0, 'reject_unsafe_urls' => true, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode(['text' => $text])]);
        }
    }

    public static function notifyStatusChange(object $site, string $oldStatus, string $newStatus, string $message, int $consecutive): void
    {
        $threshold = max(1, (int) get_option('wpsmm_error_threshold', 2));
        $oldHealthy = self::isHealthy($oldStatus);
        $newHealthy = self::isHealthy($newStatus);

        if ($newHealthy && !$oldHealthy) {
            self::notifyRecovery($site, $oldStatus, $newStatus, $message);
            return;
        }
        if ($newHealthy || $consecutive < $threshold) {
            return;
        }

        $priority = self::priorityFor($newStatus);
        if (!$priority || !self::cooldownReady((int) $site->id, $priority)) {
            return;
        }

        self::markCooldown((int) $site->id, $priority);
        $label = self::priorityLabel($priority);
        self::alert(
            sprintf('[%s] Website cảnh báo', $label),
            sprintf("%s\n%s\nTrạng thái: %s\n%s", $site->name, $site->url, $newStatus, $message)
        );
    }

    public static function notifyRecovery(object $site, string $oldStatus, string $newStatus, string $message): void
    {
        if (!get_option('wpsmm_enable_recovery_alert', 1)) {
            return;
        }
        if (!self::cooldownReady((int) $site->id, 'recovery')) {
            return;
        }
        self::markCooldown((int) $site->id, 'recovery');
        self::alert(
            '[Hồi phục] Website đã hoạt động lại',
            sprintf("%s\n%s\nTừ %s → %s\n%s", $site->name, $site->url, $oldStatus, $newStatus, $message)
        );
    }

    public static function notifyDnsChange(int $siteId, string $host, array $previous, array $current): void
    {
        if (!get_option('wpsmm_dns_alert_enabled', 1)) {
            return;
        }
        if (!self::cooldownReady($siteId, 'dns')) {
            return;
        }
        $site = \WPSMM\Repositories\SiteRepository::find($siteId);
        if (!$site) {
            return;
        }
        self::markCooldown($siteId, 'dns');
        self::alert(
            '[P2] Thay đổi DNS',
            sprintf(
                "%s\n%s\nHostname: %s\nTrước: %s\nSau: %s",
                $site->name,
                $site->url,
                $host,
                DnsMonitorService::formatRecords(wp_json_encode($previous)),
                DnsMonitorService::formatRecords(wp_json_encode($current))
            )
        );
    }

    private static function isHealthy(string $status): bool
    {
        return in_array($status, ['online', 'redirect'], true);
    }

    private static function priorityFor(string $status): ?string
    {
        foreach (self::PRIORITY_MAP as $priority => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $priority;
            }
        }
        return null;
    }

    private static function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'P1' => 'P1 - Khẩn cấp',
            'P2' => 'P2 - Cảnh báo',
            'P3' => 'P3 - Thông tin',
            default => $priority,
        };
    }

    private static function cooldownReady(int $siteId, string $bucket): bool
    {
        return !get_transient(self::cooldownKey($siteId, $bucket));
    }

    private static function markCooldown(int $siteId, string $bucket): void
    {
        $seconds = match ($bucket) {
            'P1' => max(300, (int) get_option('wpsmm_alert_cooldown_p1', 900)),
            'P2', 'dns' => max(600, (int) get_option('wpsmm_alert_cooldown_p2', 1800)),
            'P3' => max(900, (int) get_option('wpsmm_alert_cooldown_p3', 3600)),
            'recovery' => max(300, (int) get_option('wpsmm_alert_cooldown_recovery', 1800)),
            default => 1800,
        };
        set_transient(self::cooldownKey($siteId, $bucket), 1, $seconds);
    }

    private static function cooldownKey(int $siteId, string $bucket): string
    {
        return 'wpsmm_alert_cd_' . $siteId . '_' . sanitize_key($bucket);
    }
}