<?php
namespace WPSMM\Repositories;

use WPSMM\Services\DatabaseService;

if (!defined('ABSPATH')) {
    exit;
}

final class GroupRepository
{
    public static function all(bool $withCounts = true): array
    {
        global $wpdb;
        $groups = $wpdb->get_results(
            'SELECT * FROM ' . DatabaseService::table('groups') . ' ORDER BY sort_order ASC, name ASC'
        ) ?: [];
        if (!$withCounts || !$groups) {
            return $groups;
        }
        $counts = self::siteCounts();
        foreach ($groups as $group) {
            $group->site_count = (int) ($counts[(int) $group->id] ?? 0);
        }
        return $groups;
    }

    public static function indexed(): array
    {
        $indexed = [];
        foreach (self::all(false) as $group) {
            $indexed[(int) $group->id] = $group;
        }
        return $indexed;
    }

    public static function find(int $id): ?object
    {
        global $wpdb;
        if ($id <= 0) {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . DatabaseService::table('groups') . ' WHERE id=%d',
            $id
        )) ?: null;
    }

    public static function findBySlug(string $slug): ?object
    {
        global $wpdb;
        $slug = self::slugify($slug);
        if ($slug === '') {
            return null;
        }
        return $wpdb->get_row($wpdb->prepare(
            'SELECT * FROM ' . DatabaseService::table('groups') . ' WHERE slug=%s',
            $slug
        )) ?: null;
    }

    public static function save(array $data, int $id = 0): int
    {
        global $wpdb;
        $table = DatabaseService::table('groups');
        $name = sanitize_text_field((string) ($data['name'] ?? ''));
        if ($name === '') {
            return 0;
        }
        $row = [
            'name' => $name,
            'description' => sanitize_text_field((string) ($data['description'] ?? '')),
            'color' => self::sanitizeColor((string) ($data['color'] ?? '#5b7cfa')),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'updated_at' => current_time('mysql'),
        ];
        if ($id > 0) {
            $existing = self::find($id);
            if (!$existing) {
                return 0;
            }
            if (!empty($data['slug'])) {
                $row['slug'] = self::uniqueSlug((string) $data['slug'], $id);
            }
            $wpdb->update($table, $row, ['id' => $id]);
            self::syncSitesGroupName($id, $name);
            return $id;
        }
        $row['slug'] = self::uniqueSlug((string) ($data['slug'] ?? $name));
        $row['created_at'] = current_time('mysql');
        $wpdb->insert($table, $row);
        return (int) $wpdb->insert_id;
    }

    public static function delete(int $id): bool
    {
        global $wpdb;
        if ($id <= 0 || !self::find($id)) {
            return false;
        }
        $sites = DatabaseService::table('sites');
        $wpdb->query($wpdb->prepare(
            "UPDATE $sites SET group_id=0, group_name='', updated_at=%s WHERE group_id=%d",
            current_time('mysql'),
            $id
        ));
        return (bool) $wpdb->delete(DatabaseService::table('groups'), ['id' => $id]);
    }

    public static function assignSites(int $groupId, array $siteIds): int
    {
        global $wpdb;
        $siteIds = array_values(array_unique(array_filter(array_map('absint', $siteIds))));
        if (!$siteIds) {
            return 0;
        }
        $groupName = '';
        if ($groupId > 0) {
            $group = self::find($groupId);
            if (!$group) {
                return 0;
            }
            $groupName = (string) $group->name;
        }
        $placeholders = implode(',', array_fill(0, count($siteIds), '%d'));
        $sites = DatabaseService::table('sites');
        return (int) $wpdb->query($wpdb->prepare(
            "UPDATE $sites SET group_id=%d, group_name=%s, updated_at=%s WHERE id IN ($placeholders)",
            array_merge([$groupId, $groupName, current_time('mysql')], $siteIds)
        ));
    }

    public static function resolveForSite(object $site, array $indexed = []): ?object
    {
        $groupId = (int) ($site->group_id ?? 0);
        if ($groupId <= 0) {
            return null;
        }
        if (isset($indexed[$groupId])) {
            return $indexed[$groupId];
        }
        return self::find($groupId);
    }

    public static function labelForSite(object $site, array $indexed = []): string
    {
        $group = self::resolveForSite($site, $indexed);
        if ($group) {
            return (string) $group->name;
        }
        $legacy = trim((string) ($site->group_name ?? ''));
        return $legacy !== '' ? $legacy : 'Chưa phân nhóm';
    }

    public static function migrateLegacyGroupNames(): void
    {
        if (get_option('wpsmm_groups_migrated')) {
            return;
        }
        global $wpdb;
        $sites = DatabaseService::table('sites');
        $rows = $wpdb->get_results(
            "SELECT DISTINCT TRIM(group_name) AS group_name FROM $sites WHERE TRIM(group_name) <> '' AND (group_id IS NULL OR group_id=0)"
        ) ?: [];
        foreach ($rows as $row) {
            $name = sanitize_text_field((string) $row->group_name);
            if ($name === '') {
                continue;
            }
            $group = self::findBySlug(self::slugify($name));
            if (!$group) {
                $groupId = self::save(['name' => $name]);
                $group = $groupId ? self::find($groupId) : null;
            }
            if (!$group) {
                continue;
            }
            $wpdb->query($wpdb->prepare(
                "UPDATE $sites SET group_id=%d, group_name=%s WHERE TRIM(group_name)=%s AND (group_id IS NULL OR group_id=0)",
                (int) $group->id,
                (string) $group->name,
                $name
            ));
        }
        update_option('wpsmm_groups_migrated', 1, false);
    }

    public static function ungroupedCount(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var(
            'SELECT COUNT(*) FROM ' . DatabaseService::table('sites') . ' WHERE group_id=0 OR group_id IS NULL'
        );
    }

    private static function siteCounts(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT group_id, COUNT(*) AS total FROM ' . DatabaseService::table('sites') . ' WHERE group_id > 0 GROUP BY group_id',
            ARRAY_A
        ) ?: [];
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['group_id']] = (int) $row['total'];
        }
        return $counts;
    }

    private static function syncSitesGroupName(int $groupId, string $name): void
    {
        global $wpdb;
        $wpdb->update(
            DatabaseService::table('sites'),
            ['group_name' => $name, 'updated_at' => current_time('mysql')],
            ['group_id' => $groupId]
        );
    }

    private static function uniqueSlug(string $source, int $excludeId = 0): string
    {
        $base = self::slugify($source);
        if ($base === '') {
            $base = 'group';
        }
        $slug = $base;
        $suffix = 2;
        while (self::slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }
        return $slug;
    }

    private static function slugExists(string $slug, int $excludeId = 0): bool
    {
        global $wpdb;
        if ($excludeId > 0) {
            return (bool) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . DatabaseService::table('groups') . ' WHERE slug=%s AND id<>%d LIMIT 1',
                $slug,
                $excludeId
            ));
        }
        return (bool) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . DatabaseService::table('groups') . ' WHERE slug=%s LIMIT 1',
            $slug
        ));
    }

    public static function slugify(string $value): string
    {
        $value = sanitize_title($value);
        return substr($value, 0, 100);
    }

    private static function sanitizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }
        return '#5b7cfa';
    }
}