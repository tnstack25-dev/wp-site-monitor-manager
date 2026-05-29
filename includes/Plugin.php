<?php
namespace WPSMM;

use WPSMM\Admin\AdminController;
use WPSMM\Rest\ApiController;
use WPSMM\Services\BackupService;
use WPSMM\Services\DatabaseService;
use WPSMM\Services\GitHubUpdateService;
use WPSMM\Services\MalwareScannerService;
use WPSMM\Services\MonitorService;
use WPSMM\Services\SecurityService;

if (!defined('ABSPATH')) {
    exit;
}

final class Plugin
{
    public const CHECK_CRON = 'wpsmm_monitor_check_event';
    public const LOG_CLEAN_CRON = 'wpsmm_log_clean_event';
    public const BACKUP_CRON = 'wpsmm_backup_event';
    private static ?self $instance = null;
    private ?AdminController $admin = null;
    private ?ApiController $api = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function boot(): void
    {
        add_filter('cron_schedules', [$this, 'cronSchedules']);
        add_action('init', [$this, 'loadTextdomain']);
        add_action('admin_init', [$this, 'maybeUpgrade']);
        add_action(self::CHECK_CRON, [MonitorService::class, 'checkAll']);
        add_action(self::LOG_CLEAN_CRON, [MonitorService::class, 'cleanupOldLogs']);
        add_action(self::BACKUP_CRON, [BackupService::class, 'runScheduled']);
        add_action('wpsmm_async_backup', [BackupService::class, 'runQueued'], 10, 1);
        add_action('wpsmm_async_malware_scan', [MalwareScannerService::class, 'runQueued'], 10, 1);
        GitHubUpdateService::register();
        $this->admin = new AdminController();
        $this->admin->register();
        $this->api = new ApiController();
        $this->api->register();
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain('wp-site-monitor-manager', false, dirname(plugin_basename(WPSMM_PLUGIN_FILE)) . '/languages');
    }

    public function cronSchedules(array $schedules): array
    {
        $schedules['wpsmm_2_minutes'] = ['interval' => 120, 'display' => 'WPSMM mỗi 2 phút'];
        $schedules['wpsmm_5_minutes'] = ['interval' => 300, 'display' => 'WPSMM mỗi 5 phút'];
        $schedules['wpsmm_weekly'] = ['interval' => WEEK_IN_SECONDS, 'display' => 'WPSMM hàng tuần'];
        return $schedules;
    }

    public function activate(): void
    {
        DatabaseService::install();
        SecurityService::prepareBackupDir();
        $this->scheduleCrons();
        update_option('wpsmm_version', WPSMM_VERSION);
    }

    public function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CHECK_CRON);
        wp_clear_scheduled_hook(self::LOG_CLEAN_CRON);
        wp_clear_scheduled_hook(self::BACKUP_CRON);
    }

    public function maybeUpgrade(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        if (get_option('wpsmm_version') !== WPSMM_VERSION) {
            DatabaseService::install();
            SecurityService::prepareBackupDir();
            $this->scheduleCrons();
            update_option('wpsmm_version', WPSMM_VERSION);
        }
    }

    private function scheduleCrons(): void
    {
        if (!wp_next_scheduled(self::CHECK_CRON)) {
            wp_schedule_event(time() + 30, 'wpsmm_2_minutes', self::CHECK_CRON);
        }
        if (!wp_next_scheduled(self::LOG_CLEAN_CRON)) {
            wp_schedule_event(time() + 3600, 'daily', self::LOG_CLEAN_CRON);
        }
        BackupService::reschedule();
    }
}
