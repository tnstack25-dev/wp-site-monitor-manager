<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class SlaService
{
    public static function aggregate(): array
    {
        return [
            'uptime_7d' => self::uptimePercent(7),
            'uptime_30d' => self::uptimePercent(30),
            'uptime_90d' => self::uptimePercent(90),
            'mttr_minutes' => self::mttrMinutes(30),
        ];
    }

    public static function siteSla(int $siteId): array
    {
        return [
            'uptime_7d' => self::uptimePercent(7, $siteId),
            'uptime_30d' => self::uptimePercent(30, $siteId),
            'uptime_90d' => self::uptimePercent(90, $siteId),
            'mttr_minutes' => self::mttrMinutes(30, $siteId),
        ];
    }

    public static function uptimePercent(int $days, int $siteId = 0): float
    {
        global $wpdb;
        $logs = DatabaseService::table('logs');
        $where = 'checked_at >= DATE_SUB(%s, INTERVAL %d DAY)';
        $values = [current_time('mysql'), $days];
        if ($siteId > 0) {
            $where .= ' AND site_id=%d';
            $values[] = $siteId;
        }
        $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $logs WHERE $where", ...$values));
        if (!$total) {
            return 0.0;
        }
        $up = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $logs WHERE $where AND status IN ('online','redirect','partial')",
            ...$values
        ));
        return round(($up / $total) * 100, 2);
    }

    public static function mttrMinutes(int $days, int $siteId = 0): float
    {
        global $wpdb;
        $table = DatabaseService::table('incidents');
        $where = 'resolved_at IS NOT NULL AND started_at >= DATE_SUB(%s, INTERVAL %d DAY)';
        $values = [current_time('mysql'), $days];
        if ($siteId > 0) {
            $where .= ' AND site_id=%d';
            $values[] = $siteId;
        }
        $avg = $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, started_at, resolved_at)) FROM $table WHERE $where",
            ...$values
        ));
        return round((float) $avg, 1);
    }
}