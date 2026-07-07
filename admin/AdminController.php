<?php
namespace WPSMM\Admin;

use WPSMM\Repositories\GroupRepository;
use WPSMM\Repositories\SiteRepository;
use WPSMM\Services\DatabaseService;
use WPSMM\Services\GitHubUpdateService;
use WPSMM\Services\MonitorService;
use WPSMM\Services\PublicSiteService;
use WPSMM\Services\SecurityService;

if (!defined('ABSPATH')) {
    exit;
}

final class AdminController
{
    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_filter('admin_body_class', [$this, 'bodyClass']);
        add_action('admin_post_wpsmm_save_site', [$this, 'saveSite']);
        add_action('admin_post_wpsmm_delete_site', [$this, 'deleteSite']);
        add_action('admin_post_wpsmm_bulk_sites', [$this, 'bulkSites']);
        add_action('admin_post_wpsmm_save_group', [$this, 'saveGroup']);
        add_action('admin_post_wpsmm_delete_group', [$this, 'deleteGroup']);
        add_action('admin_post_wpsmm_quick_login', [$this, 'quickLogin']);
        add_action('admin_post_wpsmm_reveal_credentials', [$this, 'revealCredentials']);
        add_action('admin_post_wpsmm_update_site_credentials', [$this, 'updateSiteCredentials']);
        add_action('admin_post_wpsmm_save_settings', [$this, 'saveSettings']);
        add_action('admin_head', [$this, 'hideTgmpaNotice']);
        add_action('admin_notices', [$this, 'notices']);
    }

    public function menu(): void
    {
        add_menu_page('WP Site Monitor', 'WP Site Monitor', 'manage_options', 'wpsmm', [$this, 'dashboard'], 'dashicons-chart-area', 58);
        add_submenu_page('wpsmm', 'Tổng quan', 'Tổng quan', 'manage_options', 'wpsmm', [$this, 'dashboard']);
        add_submenu_page('wpsmm', 'Website', 'Website', 'manage_options', 'wpsmm-sites', [$this, 'sites']);
        add_submenu_page('wpsmm', 'Nhóm website', 'Nhóm website', 'manage_options', 'wpsmm-groups', [$this, 'groups']);
        add_submenu_page('wpsmm', 'Nhật ký', 'Nhật ký', 'manage_options', 'wpsmm-logs', [$this, 'logs']);
        add_submenu_page('wpsmm', 'Cài đặt', 'Cài đặt', 'manage_options', 'wpsmm-settings', [$this, 'settings']);
    }

    public function assets(string $hook): void
    {
        wp_enqueue_style('wpsmm-admin-dark', WPSMM_PLUGIN_URL . 'assets/css/admin-dark.css', [], WPSMM_VERSION);
        if (strpos($hook, 'wpsmm') === false) {
            return;
        }
        wp_enqueue_style('dashicons');
        wp_enqueue_style('wpsmm-admin', WPSMM_PLUGIN_URL . 'assets/css/admin.css', [], WPSMM_VERSION);
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.1', true);
        wp_enqueue_script('wpsmm-admin', WPSMM_PLUGIN_URL . 'assets/js/admin.js', ['chart-js'], WPSMM_VERSION, true);
        wp_localize_script('wpsmm-admin', 'WPSMM', [
            'rest' => esc_url_raw(rest_url('wpsmm/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'websocketUrl' => (string) get_option('wpsmm_websocket_url', ''),
            'darkMode' => (bool) get_option('wpsmm_dark_mode', 0),
            'pollInterval' => 10000,
            'adminSites' => admin_url('admin.php?page=wpsmm-sites'),
            'adminNewSite' => admin_url('admin.php?page=wpsmm-sites&action=new'),
            'adminLogs' => admin_url('admin.php?page=wpsmm-logs'),
        ]);
    }

    public function bodyClass(string $classes): string
    {
        return get_option('wpsmm_dark_mode', 0) ? $classes . ' wpsmm-dark' : $classes;
    }

    public function hideTgmpaNotice(): void
    {
        if (!get_option('wpsmm_hide_tgmpa_notice', 0)) {
            return;
        }
        echo '<style id="wpsmm-hide-tgmpa-notice">#setting-error-tgmpa,.settings-error-tgmpa{display:none!important}</style>';
    }

    public function dashboard(): void
    {
        $this->render('dashboard');
    }

    public function sites(): void
    {
        $edit = isset($_GET['id']) ? SiteRepository::find((int) $_GET['id']) : null;
        $action = sanitize_key(wp_unslash($_GET['action'] ?? ''));
        if ($edit && $action === 'view') {
            $credentials = $this->consumeCredentialsReveal($edit);
            $refreshInventory = !empty($_GET['refresh_inventory']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'wpsmm_refresh_inventory_' . $edit->id);
            $inventory = $this->fetchSiteInventory($edit, $refreshInventory);
            if ($credentials) {
                nocache_headers();
            }
            $this->render('site-detail', ['site' => $edit, 'logs' => SiteRepository::recentLogs((int) $edit->id), 'incidents' => SiteRepository::recentIncidents((int) $edit->id), 'credentials' => $credentials, 'inventory' => $inventory]);
            return;
        }
        if ($edit || $action === 'new') {
            $this->render('site-form', ['edit' => $edit, 'groups' => GroupRepository::all(false)]);
            return;
        }
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $per = min(100, max(5, (int) ($_GET['per_page'] ?? 20)));
        $filter = sanitize_key(wp_unslash($_GET['filter'] ?? 'all'));
        $search = sanitize_text_field(wp_unslash($_GET['search'] ?? ''));
        $groupId = isset($_GET['group_id']) ? (int) $_GET['group_id'] : 0;
        $listing = SiteRepository::paginate([
            'page' => $page,
            'per_page' => $per,
            'filter' => $filter,
            'search' => $search,
            'group_id' => $groupId,
        ]);
        $this->render('sites', [
            'sites' => $listing['items'],
            'summary' => SiteRepository::summaryCounts(),
            'counts' => SiteRepository::filterCounts(),
            'groups' => GroupRepository::all(),
            'group_id' => $groupId,
            'page' => $listing['page'],
            'per' => $listing['per_page'],
            'pages' => $listing['pages'],
            'total' => $listing['total'],
            'filter' => $filter,
            'search' => $search,
        ]);
    }

    public function groups(): void
    {
        $editId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $edit = $editId > 0 ? GroupRepository::find($editId) : null;
        $this->render('groups', [
            'groups' => GroupRepository::all(),
            'edit' => $edit,
            'ungrouped_count' => GroupRepository::ungroupedCount(),
        ]);
    }

    public function logs(): void
    {
        global $wpdb;
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $per = min(100, max(10, (int) ($_GET['per_page'] ?? 20)));
        $filters = [
            'site_id' => absint($_GET['site_id'] ?? 0),
            'status' => sanitize_key(wp_unslash($_GET['status'] ?? '')),
            'date_from' => sanitize_text_field(wp_unslash($_GET['date_from'] ?? '')),
            'date_to' => sanitize_text_field(wp_unslash($_GET['date_to'] ?? '')),
            'search' => sanitize_text_field(wp_unslash($_GET['search'] ?? '')),
        ];
        $where = [];
        $values = [];
        if ($filters['site_id']) {
            $where[] = 'l.site_id=%d';
            $values[] = $filters['site_id'];
        }
        if ($filters['status']) {
            $where[] = 'l.status=%s';
            $values[] = $filters['status'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_from'])) {
            $where[] = 'l.checked_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['date_to'])) {
            $where[] = 'l.checked_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }
        if ($filters['search'] !== '') {
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = '(l.message LIKE %s OR s.name LIKE %s OR s.url LIKE %s)';
            array_push($values, $like, $like, $like);
        }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $join = ' FROM ' . DatabaseService::table('logs') . ' l LEFT JOIN ' . DatabaseService::table('sites') . ' s ON s.id=l.site_id';
        $countSql = 'SELECT COUNT(*)' . $join . $clause;
        $total = (int) $wpdb->get_var($values ? $wpdb->prepare($countSql, $values) : $countSql);
        $pages = max(1, (int) ceil($total / $per));
        $page = min($page, $pages);
        $query = 'SELECT l.*,s.name site_name,s.url site_url' . $join . $clause . ' ORDER BY l.checked_at DESC LIMIT %d OFFSET %d';
        $logs = $wpdb->get_results($wpdb->prepare($query, array_merge($values, [$per, ($page - 1) * $per]))) ?: [];
        $stats = ['online' => 0, 'warning' => 0, 'error' => 0];
        $statsSql = 'SELECT l.status, COUNT(*) total' . $join . $clause . ' GROUP BY l.status';
        foreach (($wpdb->get_results($values ? $wpdb->prepare($statsSql, $values) : $statsSql) ?: []) as $row) {
            if (in_array($row->status, ['online', 'redirect'], true)) {
                $stats['online'] += (int) $row->total;
            } elseif (in_array($row->status, ['offline', 'server_error', 'ssl_error'], true)) {
                $stats['error'] += (int) $row->total;
            } else {
                $stats['warning'] += (int) $row->total;
            }
        }
        $this->render('logs', ['logs' => $logs, 'sites' => SiteRepository::all(), 'page' => $page, 'per' => $per, 'total' => $total, 'filters' => $filters, 'stats' => $stats]);
    }

    public function settings(): void
    {
        $this->render('settings');
    }

    public function saveSite(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('wpsmm_save_site');

        $id = (int) ($_POST['id'] ?? 0);
        $url = trim(wp_unslash($_POST['url'] ?? ''));
        if ($url && !preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $data = [
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'url' => SecurityService::publicHttpUrl($url),
            'group_id' => absint($_POST['group_id'] ?? 0),
            'monitor_enabled' => !empty($_POST['monitor_enabled']) ? 1 : 0,
            'health_path' => sanitize_text_field(wp_unslash($_POST['health_path'] ?? '')),
            'expected_status' => absint($_POST['expected_status'] ?? 200) ?: 200,
            'expected_title' => sanitize_text_field(wp_unslash($_POST['expected_title'] ?? '')),
        ];
        $loginUrl = trim(wp_unslash($_POST['login_url'] ?? ''));
        if ($loginUrl !== '' && !preg_match('~^https?://~i', $loginUrl)) {
            $loginUrl = 'https://' . $loginUrl;
        }
        $data['login_url'] = $loginUrl === '' ? '' : SecurityService::publicLoginUrl($loginUrl);
        $postedAgentSecret = strtolower(trim((string) wp_unslash($_POST['agent_secret'] ?? '')));
        if ($postedAgentSecret !== '' && !preg_match('/^[a-f0-9]{64}$/', $postedAgentSecret)) {
            $this->notice('error', 'Khóa kết nối Agent phải gồm đúng 64 ký tự hex.');
            $this->redirect('wpsmm-sites');
        }
        if ((!empty($_POST['login_username']) || !empty($_POST['login_password']) || $postedAgentSecret !== '') && !SecurityService::supportsEncryption()) {
            $this->notice('error', 'Máy chủ chưa hỗ trợ OpenSSL nên không thể lưu thông tin đăng nhập an toàn.');
            $this->redirect('wpsmm-sites');
        }
        foreach (['login_username', 'login_password', 'agent_secret'] as $secret) {
            $value = trim(wp_unslash($_POST[$secret] ?? ''));
            if ($value !== '') {
                $data[$secret] = SecurityService::encryptSecret($value);
            }
        }
        if (!$data['name'] || !$data['url']) {
            $this->notice('error', 'Tên website hoặc URL không hợp lệ.');
            $this->redirect('wpsmm-sites');
        }
        $data['url'] = untrailingslashit($data['url']);
        if (SiteRepository::findByUrl($data['url'], $id)) {
            $this->notice('error', 'Website này đã tồn tại trong danh sách giám sát.');
            $this->redirect('wpsmm-sites');
        }
        if ($loginUrl !== '' && !$data['login_url']) {
            $this->notice('error', 'URL đăng nhập không hợp lệ.');
            $this->redirect('wpsmm-sites');
        }
        $data = SiteRepository::applyGroupSelection($data);
        $savedId = SiteRepository::save($data, $id);
        delete_transient('wpsmm_inventory_' . $savedId);
        if ($id <= 0 && !empty($data['monitor_enabled'])) {
            MonitorService::check($savedId, true);
        }
        $this->notice('success', 'Đã lưu website.');
        $this->redirect('wpsmm-sites&action=view&id=' . $savedId);
    }

    public function deleteSite(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpsmm_delete_site_' . $id);
        SiteRepository::delete($id);
        $this->notice('success', 'Đã xóa website.');
        $this->redirect('wpsmm-sites');
    }

    public function bulkSites(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('wpsmm_bulk_sites');
        $ids = array_values(array_filter(array_map('absint', (array) ($_POST['site_ids'] ?? []))));
        $action = sanitize_key(wp_unslash($_POST['bulk_action'] ?? ''));
        if ($action === '' || !$ids) {
            $this->notice('error', 'Vui lòng chọn ít nhất một website và thao tác hàng loạt.');
            $this->redirect('wpsmm-sites');
        }
        if ($action === 'check') {
            MonitorService::checkBatch($ids, true);
        } elseif ($action === 'assign_group') {
            $groupId = absint($_POST['bulk_group_id'] ?? 0);
            if ($groupId > 0 && !GroupRepository::find($groupId)) {
                $this->notice('error', 'Nhóm website không tồn tại.');
                $this->redirect('wpsmm-sites');
            }
            GroupRepository::assignSites($groupId, $ids);
        } else {
            foreach ($ids as $id) {
                if ($action === 'pause') {
                    SiteRepository::save(['monitor_enabled' => 0], $id);
                } elseif ($action === 'resume') {
                    SiteRepository::save(['monitor_enabled' => 1], $id);
                } elseif ($action === 'delete') {
                    SiteRepository::delete($id);
                }
            }
        }
        $this->notice('success', 'Đã xử lý thao tác hàng loạt cho ' . count($ids) . ' website.');
        $this->redirect('wpsmm-sites');
    }

    public function saveGroup(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('wpsmm_save_group');
        $id = (int) ($_POST['id'] ?? 0);
        $savedId = GroupRepository::save([
            'name' => wp_unslash($_POST['name'] ?? ''),
            'description' => wp_unslash($_POST['description'] ?? ''),
            'color' => wp_unslash($_POST['color'] ?? '#5b7cfa'),
            'sort_order' => (int) ($_POST['sort_order'] ?? 0),
        ], $id);
        if ($savedId <= 0) {
            $this->notice('error', 'Tên nhóm không hợp lệ.');
            $this->redirect('wpsmm-groups');
        }
        $this->notice('success', $id > 0 ? 'Đã cập nhật nhóm website.' : 'Đã tạo nhóm website mới.');
        $this->redirect('wpsmm-groups');
    }

    public function deleteGroup(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpsmm_delete_group_' . $id);
        if (!GroupRepository::delete($id)) {
            $this->notice('error', 'Không thể xóa nhóm website.');
            $this->redirect('wpsmm-groups');
        }
        $this->notice('success', 'Đã xóa nhóm website. Các website trong nhóm đã được chuyển về chưa phân nhóm.');
        $this->redirect('wpsmm-groups');
    }

    public function quickLogin(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('wpsmm_quick_login_' . $id);
        if (!$this->verifyCurrentUserPassword((string) wp_unslash($_POST['confirmation_password'] ?? ''), 'quick_login')) {
            wp_die('Mật khẩu xác nhận không chính xác hoặc bạn đã thử sai quá nhiều lần.');
        }
        $site = SiteRepository::find($id);
        $loginUrl = $site ? SecurityService::publicLoginUrl((string) $site->login_url) : '';
        $username = $site ? SecurityService::decryptSecret((string) $site->login_username) : '';
        if (!$site || !$loginUrl || $username === '' || empty($site->agent_secret)) {
            wp_die('Thông tin đăng nhập nhanh chưa được cấu hình đầy đủ.');
        }
        $endpoint = untrailingslashit((string) $site->url) . '/wp-json/wpma/v1/sso-ticket';
        $response = $this->signedAgentPost($site, $endpoint, '/wpma/v1/sso-ticket', ['username' => $username]);
        if (is_wp_error($response)) {
            wp_die('Không thể kết nối an toàn với WP Site Monitor Agent trên website con.');
        }
        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        $ticketUrl = SecurityService::publicLoginUrl((string) ($payload['login_url'] ?? ''));
        $ticket = strtolower((string) ($payload['ticket'] ?? ''));
        $siteHost = wp_parse_url((string) $site->url, PHP_URL_HOST);
        $ticketHost = wp_parse_url($ticketUrl, PHP_URL_HOST);
        if (wp_remote_retrieve_response_code($response) !== 200 || !$ticketUrl || !preg_match('/^[a-f0-9]{64}$/', $ticket) || !$siteHost || strcasecmp((string) $siteHost, (string) $ticketHost) !== 0) {
            wp_die(esc_html((string) ($payload['message'] ?? 'Website con từ chối yêu cầu đăng nhập nhanh.')));
        }
        nocache_headers();
        ?>
        <!doctype html><html><head><meta charset="utf-8"><meta name="referrer" content="no-referrer"><title>Đang đăng nhập...</title></head>
        <body><form id="wpsmm-sso" method="post" action="<?php echo esc_url($ticketUrl); ?>"><input type="hidden" name="ticket" value="<?php echo esc_attr($ticket); ?>"></form><script>document.getElementById("wpsmm-sso").submit();</script></body></html>
        <?php
        exit;
    }

    public function revealCredentials(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('wpsmm_reveal_credentials_' . $id);
        $rateKey = 'wpsmm_reveal_rate_' . get_current_user_id();
        $attempts = (int) get_transient($rateKey);
        if ($attempts >= 5) {
            $this->notice('error', 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau 5 phút.');
            $this->redirect('wpsmm-sites&action=view&id=' . $id);
        }

        $user = wp_get_current_user();
        $password = (string) wp_unslash($_POST['confirmation_password'] ?? '');
        if ($password === '' || !wp_check_password($password, (string) $user->user_pass, (int) $user->ID)) {
            set_transient($rateKey, $attempts + 1, 5 * MINUTE_IN_SECONDS);
            $this->notice('error', 'Mật khẩu xác nhận không chính xác.');
            $this->redirect('wpsmm-sites&action=view&id=' . $id);
        }

        $site = SiteRepository::find($id);
        if (!$site || empty($site->login_username) || empty($site->login_password)) {
            $this->notice('error', 'Website chưa có thông tin đăng nhập để hiển thị.');
            $this->redirect('wpsmm-sites&action=view&id=' . $id);
        }

        delete_transient($rateKey);
        $token = bin2hex(random_bytes(32));
        set_transient('wpsmm_reveal_' . hash('sha256', $token), ['user_id' => (int) $user->ID, 'site_id' => $id], MINUTE_IN_SECONDS);
        $this->redirect('wpsmm-sites&action=view&id=' . $id . '&reveal=' . rawurlencode($token));
    }

    public function updateSiteCredentials(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = absint($_POST['id'] ?? 0);
        check_admin_referer('wpsmm_update_site_credentials_' . $id);
        $site = SiteRepository::find($id);
        if (!$site) {
            $this->notice('error', 'Website không tồn tại.');
            $this->redirect('wpsmm-sites');
        }

        if (!$this->verifyCurrentUserPassword((string) wp_unslash($_POST['confirmation_password'] ?? ''), 'update_credentials')) {
            $this->notice('error', 'Mật khẩu xác nhận không chính xác.');
            $this->redirect('wpsmm-sites&action=view&id=' . $id);
        }
        if (!SecurityService::supportsEncryption()) {
            $this->notice('error', 'Máy chủ chưa hỗ trợ OpenSSL nên không thể lưu thông tin đăng nhập an toàn.');
            $this->redirect('wpsmm-sites&action=view&id=' . $id);
        }

        $loginUrl = trim((string) wp_unslash($_POST['login_url'] ?? ''));
        if ($loginUrl !== '' && !preg_match('~^https?://~i', $loginUrl)) {
            $loginUrl = 'https://' . $loginUrl;
        }
        $loginUrl = $loginUrl === '' ? '' : SecurityService::publicLoginUrl($loginUrl);
        $username = trim((string) wp_unslash($_POST['login_username'] ?? ''));
        $password = (string) wp_unslash($_POST['login_password'] ?? '');
        if ($loginUrl === '' || $username === '' || $password === '') {
            $this->notice('error', 'Vui lòng nhập đầy đủ URL đăng nhập, tài khoản và mật khẩu mới.');
            $this->redirect('wpsmm-sites&action=view&id=' . $id);
        }

        $data = [
            'login_url' => $loginUrl,
            'login_username' => SecurityService::encryptSecret($username),
            'login_password' => SecurityService::encryptSecret($password),
        ];
        $agentSecret = strtolower(trim((string) wp_unslash($_POST['agent_secret'] ?? '')));
        if ($agentSecret !== '') {
            if (!preg_match('/^[a-f0-9]{64}$/', $agentSecret)) {
                $this->notice('error', 'Khóa kết nối Agent phải gồm đúng 64 ký tự hex.');
                $this->redirect('wpsmm-sites&action=view&id=' . $id);
            }
            $data['agent_secret'] = SecurityService::encryptSecret($agentSecret);
        }
        SiteRepository::save($data, $id);
        delete_transient('wpsmm_inventory_' . $id);
        $this->notice('success', 'Đã cập nhật thông tin đăng nhập website con.');
        $this->redirect('wpsmm-sites&action=view&id=' . $id);
    }

    public function saveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('wpsmm_save_settings');
        foreach (['wpsmm_telegram_chat_id', 'wpsmm_alert_email'] as $key) {
            update_option($key, sanitize_text_field(wp_unslash($_POST[$key] ?? '')));
        }
        update_option('wpsmm_suspicious_keywords', sanitize_textarea_field(wp_unslash($_POST['wpsmm_suspicious_keywords'] ?? '')));
        foreach (['wpsmm_telegram_bot_token', 'wpsmm_zalo_webhook_url'] as $key) {
            $value = sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
            if ($key === 'wpsmm_zalo_webhook_url' && $value !== '') {
                $value = SecurityService::publicHttpUrl($value, true);
            }
            if ($value !== '') {
                update_option($key, SecurityService::encryptSecret($value));
            }
        }
        $ws = SecurityService::publicUrl((string) wp_unslash($_POST['wpsmm_websocket_url'] ?? ''), ['wss']);
        update_option('wpsmm_websocket_url', $ws);
        foreach (['wpsmm_dark_mode', 'wpsmm_enable_email_alert', 'wpsmm_enable_zalo_alert', 'wpsmm_enable_telegram_alert', 'wpsmm_hide_tgmpa_notice', 'wpsmm_dns_monitor_enabled', 'wpsmm_dns_alert_enabled', 'wpsmm_enable_recovery_alert'] as $key) {
            update_option($key, !empty($_POST[$key]) ? 1 : 0);
        }
        update_option('wpsmm_alert_cooldown_p1', max(300, absint($_POST['wpsmm_alert_cooldown_p1'] ?? 900)));
        update_option('wpsmm_alert_cooldown_p2', max(600, absint($_POST['wpsmm_alert_cooldown_p2'] ?? 1800)));
        update_option('wpsmm_alert_cooldown_p3', max(900, absint($_POST['wpsmm_alert_cooldown_p3'] ?? 3600)));
        update_option('wpsmm_alert_cooldown_recovery', max(300, absint($_POST['wpsmm_alert_cooldown_recovery'] ?? 1800)));
        update_option('wpsmm_log_retention_days', max(1, absint($_POST['wpsmm_log_retention_days'] ?? 7)));
        update_option('wpsmm_batch_size', max(1, min(MonitorService::MAX_BATCH_SIZE, absint($_POST['wpsmm_batch_size'] ?? 10))));
        update_option('wpsmm_batch_time_budget', max(30, min(600, absint($_POST['wpsmm_batch_time_budget'] ?? 240))));
        update_option('wpsmm_batch_concurrency', max(10, min(80, absint($_POST['wpsmm_batch_concurrency'] ?? 30))));
        update_option('wpsmm_batch_max_ssl_checks', max(1, min(100, absint($_POST['wpsmm_batch_max_ssl_checks'] ?? 25))));
        update_option('wpsmm_homepage_body_limit', max(16384, min(524288, absint($_POST['wpsmm_homepage_body_limit'] ?? 131072))));
        update_option('wpsmm_multi_probe_enabled', !empty($_POST['wpsmm_multi_probe_enabled']) ? 1 : 0);
        update_option('wpsmm_min_probes_required', max(1, min(3, absint($_POST['wpsmm_min_probes_required'] ?? 2))));
        $checkInterval = min(DAY_IN_SECONDS, max(MINUTE_IN_SECONDS, absint($_POST['wpsmm_check_interval_minutes'] ?? 2) * MINUTE_IN_SECONDS));
        if ($checkInterval !== (int) get_option('wpsmm_check_interval', 120)) {
            update_option('wpsmm_check_interval', $checkInterval);
            wpsmm_app()->rescheduleCheckCron();
        }
        update_option('wpsmm_error_threshold', max(1, absint($_POST['wpsmm_error_threshold'] ?? 2)));
        update_option('wpsmm_timeout', max(5, absint($_POST['wpsmm_timeout'] ?? 15)));
        update_option('wpsmm_ssl_warning_days', max(1, absint($_POST['wpsmm_ssl_warning_days'] ?? 14)));
        GitHubUpdateService::clearCache();
        $this->notice('success', 'Đã lưu cài đặt.');
        $this->redirect('wpsmm-settings');
    }

    public function notices(): void
    {
        $notice = get_transient('wpsmm_notice_' . get_current_user_id());
        if (!$notice) {
            return;
        }
        delete_transient('wpsmm_notice_' . get_current_user_id());
        echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    private function render(string $template, array $data = []): void
    {
        extract($data);
        include WPSMM_PLUGIN_DIR . 'templates/admin/' . $template . '.php';
    }

    private function notice(string $type, string $message): void
    {
        set_transient('wpsmm_notice_' . get_current_user_id(), compact('type', 'message'), 60);
    }

    private function consumeCredentialsReveal(object $site): ?array
    {
        $token = sanitize_text_field(wp_unslash($_GET['reveal'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $key = 'wpsmm_reveal_' . hash('sha256', $token);
        $grant = get_transient($key);
        delete_transient($key);
        if (!is_array($grant) || (int) ($grant['user_id'] ?? 0) !== get_current_user_id() || (int) ($grant['site_id'] ?? 0) !== (int) $site->id) {
            return null;
        }
        return [
            'username' => SecurityService::decryptSecret((string) $site->login_username),
            'password' => SecurityService::decryptSecret((string) $site->login_password),
        ];
    }

    private function verifyCurrentUserPassword(string $password, string $context): bool
    {
        $user = wp_get_current_user();
        $rateKey = 'wpsmm_password_rate_' . get_current_user_id() . '_' . sanitize_key($context);
        $attempts = (int) get_transient($rateKey);
        if ($attempts >= 5 || $password === '' || !wp_check_password($password, (string) $user->user_pass, (int) $user->ID)) {
            set_transient($rateKey, $attempts + 1, 5 * MINUTE_IN_SECONDS);
            return false;
        }
        delete_transient($rateKey);
        return true;
    }

    private function fetchSiteInventory(object $site, bool $refresh = false): array
    {
        $cacheKey = 'wpsmm_inventory_' . (int) $site->id;
        if (!$refresh && ($cached = get_transient($cacheKey)) && is_array($cached)) {
            return $cached;
        }

        $result = null;
        if (!empty($site->agent_secret)) {
            $endpoint = SecurityService::publicLoginUrl(untrailingslashit((string) $site->url) . '/wp-json/wpma/v1/inventory');
            if ($endpoint) {
                $response = $this->signedAgentPost($site, $endpoint, '/wpma/v1/inventory', ['refresh' => $refresh]);
                if (!is_wp_error($response)) {
                    $payload = json_decode((string) wp_remote_retrieve_body($response), true);
                    if (wp_remote_retrieve_response_code($response) === 200 && !empty($payload['success']) && is_array($payload['inventory'] ?? null)) {
                        $result = [
                            'success' => true,
                            'data' => PublicSiteService::enrichInventory((string) $site->url, $payload['inventory']),
                            'source' => 'agent',
                        ];
                    }
                }
            }
        }

        if (!$result) {
            $public = $this->buildPublicInventory((string) $site->url);
            if (!empty($public['success'])) {
                $public['message'] = empty($site->agent_secret)
                    ? 'Đang hiển thị thông tin public qua REST API WordPress. Cấu hình khóa Agent để xem plugin và giao diện.'
                    : 'Không kết nối được Agent. Đang hiển thị thông tin public qua REST API WordPress.';
                $result = $public;
            } else {
                return [
                    'success' => false,
                    'message' => empty($site->agent_secret)
                        ? 'Cần cấu hình khóa Agent hoặc bật REST API public trên website con.'
                        : 'Không thể kết nối Agent và không tải được REST API public của website con.',
                ];
            }
        }

        set_transient($cacheKey, $result, 5 * MINUTE_IN_SECONDS);
        return $result;
    }

    private function buildPublicInventory(string $url): array
    {
        $profile = PublicSiteService::fetchPublicProfile($url);
        if (empty($profile['available'])) {
            return ['success' => false];
        }

        $site = is_array($profile['site'] ?? null) ? $profile['site'] : [];
        $content = is_array($profile['content'] ?? null) ? $profile['content'] : [];
        $network = is_array($profile['network'] ?? null) ? $profile['network'] : [];

        return [
            'success' => true,
            'source' => 'wp_rest',
            'data' => [
                'generated_at' => current_time('mysql'),
                'wordpress' => '',
                'php' => '',
                'database' => '',
                'server' => '',
                'public_site' => [
                    'name' => (string) ($site['name'] ?? ''),
                    'description' => (string) ($site['description'] ?? ''),
                    'url' => (string) ($site['home'] ?? $site['url'] ?? ''),
                    'timezone' => (string) ($site['timezone_string'] ?? ''),
                    'language' => '',
                    'users' => null,
                ],
                'content' => $content,
                'network' => array_merge($network, [
                    'dns_ips' => PublicSiteService::resolveHostIps($url),
                    'public_ips' => PublicSiteService::resolveHostIps($url),
                ]),
                'plugins' => [],
                'themes' => [],
            ],
        ];
    }

    private function signedAgentPost(object $site, string $endpoint, string $route, array $payload)
    {
        $secret = SecurityService::decryptSecret((string) ($site->agent_secret ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
            return new \WP_Error('wpsmm_agent_secret_missing', 'Khóa kết nối Agent chưa được cấu hình.');
        }
        $endpoint = SecurityService::publicLoginUrl($endpoint);
        $siteHost = wp_parse_url((string) $site->url, PHP_URL_HOST);
        $endpointHost = wp_parse_url($endpoint, PHP_URL_HOST);
        if (!$endpoint || !$siteHost || !$endpointHost || strcasecmp((string) $siteHost, (string) $endpointHost) !== 0) {
            return new \WP_Error('wpsmm_agent_endpoint_invalid', 'Endpoint Agent không hợp lệ.');
        }
        $body = wp_json_encode($payload);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $canonical = $timestamp . "\n" . $nonce . "\n" . $route . "\n" . hash('sha256', $body);
        $args = [
            'timeout' => 20,
            'redirection' => 0,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WPMA-Timestamp' => $timestamp,
                'X-WPMA-Nonce' => $nonce,
                'X-WPMA-Signature' => hash_hmac('sha256', $canonical, $secret),
                'X-WPMA-Manager-URL' => untrailingslashit(home_url()),
            ],
            'body' => $body,
        ];
        if (defined('WPSMM_ALLOW_PRIVATE_HOSTS') && WPSMM_ALLOW_PRIVATE_HOSTS) {
            return wp_remote_post($endpoint, $args);
        }
        return wp_safe_remote_post($endpoint, $args);
    }

    private function redirect(string $page): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . $page));
        exit;
    }
}
