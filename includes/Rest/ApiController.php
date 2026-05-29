<?php
namespace WPSMM\Rest;

use WP_REST_Request;
use WPSMM\Services\BackupService;
use WPSMM\Services\DatabaseService;
use WPSMM\Services\MalwareScannerService;
use WPSMM\Services\MonitorService;
use WPSMM\Services\SecurityService;
use WPSMM\Services\ServerService;
use WPSMM\Repositories\SiteRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ApiController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }
    public function routes(): void
    {
        $perm = [$this, 'permission'];
        register_rest_route('wpsmm/v1', '/stats', ['methods' => 'GET', 'callback' => [$this, 'stats'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/sites', ['methods' => 'GET', 'callback' => [$this, 'sites'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/logs', ['methods' => 'GET', 'callback' => [$this, 'logs'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/chart', ['methods' => 'GET', 'callback' => [$this, 'chart'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/check/(?P<id>\d+)', ['methods' => 'POST', 'callback' => [$this, 'check'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/backup', ['methods' => 'POST', 'callback' => [$this, 'backup'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/backup/jobs', ['methods' => 'GET', 'callback' => [$this, 'backupJobs'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/malware/scan', ['methods' => 'POST', 'callback' => [$this, 'malwareScan'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/malware/jobs', ['methods' => 'GET', 'callback' => [$this, 'malwareJobs'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/server/(?P<id>\d+)/test', ['methods' => 'POST', 'callback' => [$this, 'serverTest'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/settings/dark-mode', ['methods' => 'POST', 'callback' => [$this, 'darkMode'], 'permission_callback' => $perm]);
    }
    public function permission(): bool
    {
        return current_user_can('manage_options');
    }
    public function stats(): array
    {
        return MonitorService::stats();
    }
    public function sites(): array
    {
        return array_map([SecurityService::class, 'redactObject'], SiteRepository::all());
    }
    public function logs(WP_REST_Request $r): array
    {
        global $wpdb;
        $page = max(1, (int) $r->get_param('page'));
        $per = min(100, max(10, (int) ($r->get_param('per_page') ?: 20)));
        $offset = ($page - 1) * $per;
        return $wpdb->get_results($wpdb->prepare('SELECT l.*, s.name site_name, s.url site_url FROM ' . DatabaseService::table('logs') . ' l LEFT JOIN ' . DatabaseService::table('sites') . ' s ON s.id=l.site_id ORDER BY l.checked_at DESC LIMIT %d OFFSET %d', $per, $offset)) ?: [];
    }
    public function chart(WP_REST_Request $r): array
    {
        return MonitorService::chartData(max(1, (int) ($r->get_param('hours') ?: 24)));
    }
    public function check(WP_REST_Request $r): array
    {
        return MonitorService::check((int) $r['id'], true);
    }
    public function backup(WP_REST_Request $r): array
    {
        $ids = array_map('intval', (array) $r->get_param('site_ids'));
        return ['job_id' => BackupService::queue($ids), 'message' => 'Đã đưa backup vào hàng đợi nền'];
    }
    public function backupJobs(): array
    {
        global $wpdb;
        return array_map([SecurityService::class, 'redactObject'], $wpdb->get_results('SELECT * FROM ' . DatabaseService::table('backups') . ' ORDER BY created_at DESC LIMIT 50') ?: []);
    }
    public function malwareScan(WP_REST_Request $r): array
    {
        return ['job_id' => MalwareScannerService::queue((int) $r->get_param('site_id')), 'message' => 'Đã đưa malware scan vào hàng đợi nền'];
    }
    public function malwareJobs(): array
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . DatabaseService::table('malware_scans') . ' ORDER BY created_at DESC LIMIT 30') ?: [];
    }
    public function serverTest(WP_REST_Request $r): array
    {
        return ServerService::test((int) $r['id']);
    }
    public function darkMode(WP_REST_Request $r): array
    {
        $enabled = (bool) $r->get_param('enabled');
        update_option('wpsmm_dark_mode', $enabled ? 1 : 0);
        return ['success' => true, 'enabled' => $enabled];
    }
}
