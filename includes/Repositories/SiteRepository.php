<?php
namespace WPSMM\Repositories;

use WPSMM\Services\DatabaseService;

if (!defined('ABSPATH')) {
    exit;
}

final class SiteRepository
{
    private const STATUS_GROUPS = [
        'online' => ['online', 'redirect'],
        'warning' => ['client_error', 'not_found', 'title_changed', 'suspicious', 'ssl_expiring', 'unknown', 'partial'],
        'offline' => ['offline', 'server_error', 'ssl_error'],
    ];

    public static function statusSummary(): array
    {
        global $wpdb;
        $table = DatabaseService::table('sites');
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $paused = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE monitor_enabled=0");
        $monitored = max(0, $total - $paused);
        $counts = ['online' => 0, 'warning' => 0, 'offline' => 0];
        foreach (self::STATUS_GROUPS as $group => $statuses) {
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $counts[$group] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE monitor_enabled=1 AND status IN ($placeholders)",
                ...$statuses
            ));
        }
        return [
            'total' => $total,
            'monitored' => $monitored,
            'paused' => $paused,
            'online' => $counts['online'],
            'warning' => $counts['warning'],
            'offline' => $counts['offline'],
        ];
    }

    public static function all(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . DatabaseService::table('sites') . ' ORDER BY id DESC') ?: [];
    }

    public static function paginate(array $args = []): array
    {
        global $wpdb;
        $page = max(1, (int) ($args['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($args['per_page'] ?? 20)));
        $filter = sanitize_key((string) ($args['filter'] ?? 'all'));
        $search = trim((string) ($args['search'] ?? ''));
        $groupId = isset($args['group_id']) ? (int) $args['group_id'] : 0;
        $where = self::buildWhere($filter, $search, $groupId);
        $table = DatabaseService::table('sites');
        $countSql = 'SELECT COUNT(*) FROM ' . $table . $where['sql'];
        $total = (int) ($where['values'] ? $wpdb->get_var($wpdb->prepare($countSql, $where['values'])) : $wpdb->get_var($countSql));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $query = 'SELECT * FROM ' . $table . $where['sql'] . ' ORDER BY id DESC LIMIT %d OFFSET %d';
        $values = array_merge($where['values'], [$perPage, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($query, $values)) ?: [];
        $groups = GroupRepository::indexed();
        foreach ($items as $item) {
            $item->group_label = GroupRepository::labelForSite($item, $groups);
            $item->group = GroupRepository::resolveForSite($item, $groups);
        }
        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
            'filter' => $filter,
            'search' => $search,
            'group_id' => $groupId,
        ];
    }

    public static function filterCounts(): array
    {
        $summary = self::statusSummary();
        return [
            'all' => $summary['total'],
            'online' => $summary['online'],
            'warning' => $summary['warning'],
            'offline' => $summary['offline'],
            'paused' => $summary['paused'],
        ];
    }

    public static function summaryCounts(): array
    {
        $summary = self::statusSummary();
        return [
            'total' => $summary['total'],
            'online' => $summary['online'],
            'warning' => $summary['warning'],
            'offline' => $summary['offline'],
        ];
    }

    private static function buildWhere(string $filter, string $search, int $groupId = 0): array
    {
        global $wpdb;
        $clauses = [];
        $values = [];
        if ($filter === 'paused') {
            $clauses[] = 'monitor_enabled=0';
        } elseif ($filter !== 'all' && isset(self::STATUS_GROUPS[$filter])) {
            $clauses[] = 'monitor_enabled=1';
            $statuses = self::STATUS_GROUPS[$filter];
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $clauses[] = "status IN ($placeholders)";
            array_push($values, ...$statuses);
        }
        if ($groupId === -1) {
            $clauses[] = '(group_id IS NULL OR group_id=0)';
        } elseif ($groupId > 0) {
            $clauses[] = 'group_id=%d';
            $values[] = $groupId;
        }
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $clauses[] = '(name LIKE %s OR url LIKE %s OR group_name LIKE %s)';
            array_push($values, $like, $like, $like);
        }
        return [
            'sql' => $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '',
            'values' => $values,
        ];
    }

    public static function applyGroupSelection(array $data): array
    {
        $groupId = isset($data['group_id']) ? (int) $data['group_id'] : null;
        if ($groupId === null) {
            return $data;
        }
        if ($groupId <= 0) {
            $data['group_id'] = 0;
            $data['group_name'] = '';
            return $data;
        }
        $group = GroupRepository::find($groupId);
        if (!$group) {
            $data['group_id'] = 0;
            $data['group_name'] = '';
            return $data;
        }
        $data['group_id'] = (int) $group->id;
        $data['group_name'] = (string) $group->name;
        return $data;
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
        $site = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('sites') . ' WHERE id=%d', $id)) ?: null;
        if (!$site) {
            return null;
        }
        $groups = GroupRepository::indexed();
        $site->group_label = GroupRepository::labelForSite($site, $groups);
        $site->group = GroupRepository::resolveForSite($site, $groups);
        return $site;
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

    public static function activeIncidentsFor(array $siteIds): array
    {
        global $wpdb;
        $siteIds = array_values(array_unique(array_filter(array_map('absint', $siteIds))));
        if (!$siteIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($siteIds), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . DatabaseService::table('incidents') . " WHERE resolved_at IS NULL AND site_id IN ($placeholders) ORDER BY id DESC",
            ...$siteIds
        )) ?: [];
        $indexed = [];
        foreach ($rows as $row) {
            $siteId = (int) $row->site_id;
            if (!isset($indexed[$siteId])) {
                $indexed[$siteId] = $row;
            }
        }
        return $indexed;
    }

    public static function uptimeForSites(array $siteIds, int $days = 30): array
    {
        global $wpdb;
        $siteIds = array_values(array_unique(array_filter(array_map('absint', $siteIds))));
        if (!$siteIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($siteIds), '%d'));
        $logs = DatabaseService::table('logs');
        $since = current_time('mysql');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT site_id, COUNT(*) AS total_count, SUM(status IN ('online','redirect')) AS online_count
            FROM $logs
            WHERE site_id IN ($placeholders) AND checked_at >= DATE_SUB(%s, INTERVAL %d DAY)
            GROUP BY site_id",
            ...array_merge($siteIds, [$since, $days])
        ), ARRAY_A) ?: [];
        $uptime = [];
        foreach ($rows as $row) {
            $total = (int) ($row['total_count'] ?? 0);
            $online = (int) ($row['online_count'] ?? 0);
            $uptime[(int) $row['site_id']] = [
                'uptime' => $total ? round(($online / $total) * 100, 2) : 0.0,
                'downtime_minutes' => ($total - $online) * \WPSMM\Plugin::checkIntervalMinutes(),
            ];
        }
        return $uptime;
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
