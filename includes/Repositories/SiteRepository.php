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
    public static function find(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('sites') . ' WHERE id=%d', $id)) ?: null;
    }
    public static function recentLogs(int $siteId, int $limit = 10): array
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('logs') . ' WHERE site_id=%d ORDER BY checked_at DESC LIMIT %d', $siteId, max(1, min(50, $limit)))) ?: [];
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
    }
}
