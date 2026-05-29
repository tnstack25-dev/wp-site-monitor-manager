<?php
namespace WPSMM\Admin;

use WPSMM\Repositories\ServerRepository;
use WPSMM\Repositories\SiteRepository;
use WPSMM\Services\BackupService;
use WPSMM\Services\DatabaseService;
use WPSMM\Services\GitHubUpdateService;
use WPSMM\Services\MalwareScannerService;
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
        add_action('admin_post_wpsmm_save_site', [$this, 'saveSite']);
        add_action('admin_post_wpsmm_delete_site', [$this, 'deleteSite']);
        add_action('admin_post_wpsmm_save_settings', [$this, 'saveSettings']);
        add_action('admin_post_wpsmm_save_server', [$this, 'saveServer']);
        add_action('admin_post_wpsmm_delete_server', [$this, 'deleteServer']);
        add_action('admin_post_wpsmm_backup_now', [$this, 'backupNow']);
        add_action('admin_post_wpsmm_malware_scan', [$this, 'malwareScan']);
        add_action('admin_post_wpsmm_download_backup', [$this, 'downloadBackup']);
        add_action('admin_post_wpsmm_delete_backup', [$this, 'deleteBackup']);
        add_action('admin_notices', [$this, 'notices']);
    }

    public function menu(): void
    {
        add_menu_page('WP Site Monitor', 'WP Site Monitor', 'manage_options', 'wpsmm', [$this, 'dashboard'], 'dashicons-chart-area', 58);
        add_submenu_page('wpsmm', 'Dashboard', 'Dashboard', 'manage_options', 'wpsmm', [$this, 'dashboard']);
        add_submenu_page('wpsmm', 'Websites', 'Websites', 'manage_options', 'wpsmm-sites', [$this, 'sites']);
        add_submenu_page('wpsmm', 'Logs', 'Logs', 'manage_options', 'wpsmm-logs', [$this, 'logs']);
        add_submenu_page('wpsmm', 'Backup', 'Backup', 'manage_options', 'wpsmm-backup', [$this, 'backup']);
        add_submenu_page('wpsmm', 'Malware Scan', 'Malware Scan', 'manage_options', 'wpsmm-malware', [$this, 'malware']);
        add_submenu_page('wpsmm', 'Servers/VPS', 'Servers/VPS', 'manage_options', 'wpsmm-servers', [$this, 'servers']);
        add_submenu_page('wpsmm', 'Settings', 'Settings', 'manage_options', 'wpsmm-settings', [$this, 'settings']);
    }

    public function assets(string $hook): void
    {
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
        ]);
    }

    public function dashboard(): void
    {
        $this->render('dashboard');
    }

    public function sites(): void
    {
        $this->render('sites', [
            'sites' => SiteRepository::all(),
            'servers' => ServerRepository::all(),
            'edit' => isset($_GET['id']) ? SiteRepository::find((int) $_GET['id']) : null,
        ]);
    }

    public function logs(): void
    {
        global $wpdb;
        $page = max(1, (int) ($_GET['paged'] ?? 1));
        $per = 30;
        $total = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . DatabaseService::table('logs'));
        $logs = $wpdb->get_results($wpdb->prepare('SELECT l.*,s.name site_name,s.url site_url FROM ' . DatabaseService::table('logs') . ' l LEFT JOIN ' . DatabaseService::table('sites') . ' s ON s.id=l.site_id ORDER BY l.checked_at DESC LIMIT %d OFFSET %d', $per, ($page - 1) * $per)) ?: [];
        $this->render('logs', compact('logs', 'page', 'per', 'total'));
    }

    public function backup(): void
    {
        global $wpdb;
        $this->render('backup', [
            'sites' => SiteRepository::all(),
            'backups' => $wpdb->get_results('SELECT b.*, s.name site_name FROM ' . DatabaseService::table('backups') . ' b LEFT JOIN ' . DatabaseService::table('sites') . ' s ON s.id=b.site_id ORDER BY b.created_at DESC LIMIT 80') ?: [],
        ]);
    }

    public function malware(): void
    {
        global $wpdb;
        $this->render('malware', [
            'sites' => SiteRepository::all(),
            'scans' => $wpdb->get_results('SELECT m.*, s.name site_name, s.url site_url FROM ' . DatabaseService::table('malware_scans') . ' m LEFT JOIN ' . DatabaseService::table('sites') . ' s ON s.id=m.site_id ORDER BY m.created_at DESC LIMIT 40') ?: [],
        ]);
    }

    public function servers(): void
    {
        $this->render('servers', ['servers' => ServerRepository::all()]);
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
        $existing = $id ? SiteRepository::find($id) : null;
        $url = trim(wp_unslash($_POST['url'] ?? ''));
        if ($url && !preg_match('~^https?://~i', $url)) {
            $url = 'https://' . $url;
        }
        $url = SecurityService::publicHttpUrl($url);

        $data = [
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'url' => $url,
            'group_name' => sanitize_text_field(wp_unslash($_POST['group_name'] ?? '')),
            'expected_status' => absint($_POST['expected_status'] ?? 200) ?: 200,
            'expected_title' => sanitize_text_field(wp_unslash($_POST['expected_title'] ?? '')),
            'server_id' => absint($_POST['server_id'] ?? 0),
            'remote_path' => sanitize_text_field(wp_unslash($_POST['remote_path'] ?? '')),
            'db_name' => sanitize_text_field(wp_unslash($_POST['db_name'] ?? '')),
            'db_user' => sanitize_text_field(wp_unslash($_POST['db_user'] ?? '')),
            'db_pass' => $this->postedSecret('db_pass', $existing->db_pass ?? ''),
            'backup_secret' => $this->postedSecret('backup_secret', $existing->backup_secret ?? ''),
        ];

        if (!$data['name'] || !$data['url']) {
            $this->notice('error', 'Ten website hoac URL khong hop le/public.');
            $this->redirect('wpsmm-sites');
        }

        SiteRepository::save($data, $id);
        $this->notice('success', 'Da luu website.');
        $this->redirect('wpsmm-sites');
    }

    public function deleteSite(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpsmm_delete_site_' . $id);
        SiteRepository::delete($id);
        $this->notice('success', 'Da xoa website.');
        $this->redirect('wpsmm-sites');
    }

    public function saveServer(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('wpsmm_save_server');

        $host = sanitize_text_field(wp_unslash($_POST['host'] ?? ''));
        if (!SecurityService::isPublicHost($host)) {
            $this->notice('error', 'Host server phai la public host/IP hop le.');
            $this->redirect('wpsmm-servers');
        }

        ServerRepository::save([
            'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
            'host' => $host,
            'port' => absint($_POST['port'] ?? 22) ?: 22,
            'username' => sanitize_text_field(wp_unslash($_POST['username'] ?? '')),
            'auth_type' => sanitize_key(wp_unslash($_POST['auth_type'] ?? 'key_path')),
            'key_path' => $this->postedSecret('key_path', ''),
            'password' => $this->postedSecret('password', ''),
            'backup_path' => sanitize_text_field(wp_unslash($_POST['backup_path'] ?? '/tmp/wpsmm-backups')),
        ]);
        $this->notice('success', 'Da luu server/VPS.');
        $this->redirect('wpsmm-servers');
    }

    public function deleteServer(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpsmm_delete_server_' . $id);
        ServerRepository::delete($id);
        $this->notice('success', 'Da xoa server.');
        $this->redirect('wpsmm-servers');
    }

    public function saveSettings(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('wpsmm_save_settings');

        foreach (['wpsmm_telegram_chat_id', 'wpsmm_alert_email', 'wpsmm_suspicious_keywords'] as $key) {
            update_option($key, sanitize_text_field(wp_unslash($_POST[$key] ?? '')));
        }

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
        foreach (['wpsmm_dark_mode', 'wpsmm_enable_email_alert', 'wpsmm_enable_zalo_alert'] as $key) {
            update_option($key, !empty($_POST[$key]) ? 1 : 0);
        }
        update_option('wpsmm_log_retention_days', max(1, absint($_POST['wpsmm_log_retention_days'] ?? 7)));
        update_option('wpsmm_error_threshold', max(1, absint($_POST['wpsmm_error_threshold'] ?? 2)));
        update_option('wpsmm_timeout', max(5, absint($_POST['wpsmm_timeout'] ?? 15)));
        update_option('wpsmm_ssl_warning_days', max(1, absint($_POST['wpsmm_ssl_warning_days'] ?? 14)));
        update_option('wpsmm_backup_schedule', sanitize_key(wp_unslash($_POST['wpsmm_backup_schedule'] ?? 'daily')));
        update_option('wpsmm_backup_hour', sanitize_text_field(wp_unslash($_POST['wpsmm_backup_hour'] ?? '02:00')));
        update_option('wpsmm_backup_retention', max(1, absint($_POST['wpsmm_backup_retention'] ?? 3)));
        BackupService::reschedule();
        GitHubUpdateService::clearCache();
        $this->notice('success', 'Da luu cai dat.');
        $this->redirect('wpsmm-settings');
    }

    public function backupNow(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('wpsmm_backup_now');
        $ids = array_map('intval', (array) ($_POST['site_ids'] ?? []));
        if (!$ids) {
            $this->notice('error', 'Vui lòng chọn website cần backup.');
        } else {
            BackupService::queue($ids);
            $this->notice('success', 'Đã thêm backup vào hàng đợi.');
        }
        $this->redirect('wpsmm-backup');
    }

    public function malwareScan(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        check_admin_referer('wpsmm_malware_scan');
        MalwareScannerService::queue(absint($_POST['site_id'] ?? 0));
        $this->notice('success', 'Đã thêm malware scan vào hàng đợi.');
        $this->redirect('wpsmm-malware');
    }

    public function downloadBackup(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpsmm_download_backup_' . $id);
        global $wpdb;
        $backup = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('backups') . ' WHERE id=%d', $id));
        if (!$backup || !is_file($backup->file_path) || !SecurityService::backupPathAllowed((string) $backup->file_path)) {
            wp_die('File backup không tồn tại hoặc không hợp lệ.');
        }
        header('Content-Type: application/zip');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . basename($backup->file_name) . '"');
        header('Content-Length: ' . filesize($backup->file_path));
        readfile($backup->file_path);
        exit;
    }

    public function deleteBackup(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die();
        }
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer('wpsmm_delete_backup_' . $id);
        global $wpdb;
        $backup = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . DatabaseService::table('backups') . ' WHERE id=%d', $id));
        if ($backup && is_file($backup->file_path) && SecurityService::backupPathAllowed((string) $backup->file_path)) {
            @unlink($backup->file_path);
        }
        if ($backup) {
            $wpdb->update(DatabaseService::table('backups'), ['file_path' => '', 'message' => 'Đã xóa file local'], ['id' => $id]);
        }
        $this->notice('success', 'Đã xóa file backup local.');
        $this->redirect('wpsmm-backup');
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

    private function postedSecret(string $key, string $existing): string
    {
        $value = sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
        return $value === '' ? $existing : SecurityService::encryptSecret($value);
    }

    private function notice(string $type, string $message): void
    {
        set_transient('wpsmm_notice_' . get_current_user_id(), compact('type', 'message'), 60);
    }

    private function redirect(string $page): void
    {
        wp_safe_redirect(admin_url('admin.php?page=' . $page));
        exit;
    }
}
