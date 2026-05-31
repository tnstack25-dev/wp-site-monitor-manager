<?php
namespace WPSMM\Repositories;

use WPSMM\Services\DatabaseService;

if (!defined('ABSPATH')) {
    exit;
}

final class SiteRepository
{
    public static function all(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . DatabaseService::table('sites') . ' ORDER BY id DESC') ?: [];
    }
    public static function enabledBatch(int $limit, int $offset = 0): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('sites') . ' WHERE monitor_enabled=1 ORDER BY id ASC LIMIT %d OFFSET %d', max(1, $limit), max(0, $offset))) ?: [];
    }
    public static function enabledCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DatabaseService::table('sites') . ' WHERE monitor_enabled=1');
    }
    public static function find(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('sites') . ' WHERE id=%d', $id)) ?: null;
    }
    public static function findByUrl(string $url, int $excludeId = 0): ?object
    {
        $identity = self::urlIdentity($url);
        if ($identity === '') {
            return null;
        }
        foreach (self::all() as $site) {
            if ((int) $site->id !== $excludeId && self::urlIdentity((string) $site->url) === $identity) {
                return $site;
            }
        }
        return null;
    }
    public static function recentLogs(int $siteId, int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('logs') . ' WHERE site_id=%d ORDER BY checked_at DESC LIMIT %d', $siteId, max(1, min(50, $limit)))) ?: [];
    }
    public static function recentIncidents(int $siteId, int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('incidents') . ' WHERE site_id=%d ORDER BY started_at DESC LIMIT %d', $siteId, max(1, min(50, $limit)))) ?: [];
    }
    public static function save(array $data, int $id = 0): int
    {
        global $wpdb;
        $table = DatabaseService::table('sites');
        $data['updated_at'] = current_time('mysql');
        if ($id > 0) {
            $wpdb->update($table, $data, ['id' => $id]);
            return $id;
        }
        $data['created_at'] = current_time('mysql');
        $wpdb->insert($table, $data);
        return (int) $wpdb->insert_id;
    }
    public static function delete(int $id): void
    {
        global $wpdb;
        $wpdb->delete(DatabaseService::table('sites'), ['id' => $id]);
        $wpdb->delete(DatabaseService::table('logs'), ['site_id' => $id]);
        $wpdb->delete(DatabaseService::table('incidents'), ['site_id' => $id]);
    }

    private static function urlIdentity(string $url): string
    {
        $parts = wp_parse_url(trim($url));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return '';
        }
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = untrailingslashit('/' . ltrim((string) ($parts['path'] ?? ''), '/'));
        return $host . $port . $path;
    }
}
