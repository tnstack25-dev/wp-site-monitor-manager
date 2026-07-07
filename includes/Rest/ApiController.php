<?php
namespace WPSMM\Rest;

use WP_REST_Request;
use WPSMM\Repositories\SiteRepository;
use WPSMM\Services\DatabaseService;
use WPSMM\Services\HeartbeatService;
use WPSMM\Services\MonitorService;
use WPSMM\Services\SecurityService;

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
        register_rest_route('wpsmm/v1', '/settings/dark-mode', ['methods' => 'POST', 'callback' => [$this, 'darkMode'], 'permission_callback' => $perm]);
        register_rest_route('wpsmm/v1', '/heartbeat', ['methods' => 'POST', 'callback' => [$this, 'heartbeat'], 'permission_callback' => '__return_true']);
    }

    public function permission(): bool
    {
        return current_user_can('manage_options');
    }

    public function stats(): array
    {
        return MonitorService::stats();
    }

    public function sites(WP_REST_Request $request): array
    {
        $result = SiteRepository::paginate([
            'page' => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
            'filter' => (string) ($request->get_param('filter') ?: 'all'),
            'search' => (string) ($request->get_param('search') ?: ''),
            'group_id' => (int) ($request->get_param('group_id') ?: 0),
        ]);
        return [
            'items' => array_map([SecurityService::class, 'redactObject'], $result['items']),
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
            'pages' => $result['pages'],
            'filter' => $result['filter'],
            'search' => $result['search'],
            'group_id' => $result['group_id'],
            'counts' => SiteRepository::filterCounts(),
        ];
    }

    public function logs(WP_REST_Request $request): array
    {
        global $wpdb;
        $page = max(1, (int) $request->get_param('page'));
        $per = min(100, max(10, (int) ($request->get_param('per_page') ?: 20)));
        $offset = ($page - 1) * $per;
        return $wpdb->get_results($wpdb->prepare('SELECT l.*, s.name site_name, s.url site_url FROM ' . DatabaseService::table('logs') . ' l LEFT JOIN ' . DatabaseService::table('sites') . ' s ON s.id=l.site_id ORDER BY l.checked_at DESC LIMIT %d OFFSET %d', $per, $offset)) ?: [];
    }

    public function chart(WP_REST_Request $request): array
    {
        return MonitorService::chartData(max(1, (int) ($request->get_param('hours') ?: 24)));
    }

    public function check(WP_REST_Request $request): array
    {
        return MonitorService::check((int) $request['id'], true);
    }

    public function darkMode(WP_REST_Request $request): array
    {
        $enabled = (bool) $request->get_param('enabled');
        update_option('wpsmm_dark_mode', $enabled ? 1 : 0);
        return ['success' => true, 'enabled' => $enabled];
    }

    public function heartbeat(WP_REST_Request $request)
    {
        return HeartbeatService::receive($request);
    }
}
