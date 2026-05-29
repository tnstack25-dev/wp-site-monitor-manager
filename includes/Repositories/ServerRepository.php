<?php
namespace WPSMM\Repositories;

use WPSMM\Services\DatabaseService;

if (!defined('ABSPATH')) {
    exit;
}

final class ServerRepository
{
    public static function all(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . DatabaseService::table('servers') . ' ORDER BY id DESC') ?: [];
    }
    public static function find(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('servers') . ' WHERE id=%d', $id)) ?: null;
    }
    public static function save(array $data, int $id = 0): int
    {
        global $wpdb;
        $table = DatabaseService::table('servers');
        if ($id) {
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
        $wpdb->delete(DatabaseService::table('servers'), ['id' => $id]);
    }
}
