<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class DatabaseService
{
    public const SCHEMA_VERSION = '1.0.4';

    public static function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpsmm_' . $name;
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $sites = self::table('sites');
        $logs = self::table('logs');

        dbDelta("CREATE TABLE $sites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            url TEXT NOT NULL,
            group_name VARCHAR(100) DEFAULT '',
            login_url TEXT NULL,
            login_username TEXT NULL,
            login_password TEXT NULL,
            agent_secret TEXT NULL,
            expected_status INT DEFAULT 200,
            expected_title VARCHAR(255) DEFAULT '',
            status VARCHAR(30) DEFAULT 'unknown',
            http_code INT DEFAULT 0,
            response_time FLOAT DEFAULT 0,
            uptime_percent FLOAT DEFAULT 0,
            downtime_minutes BIGINT DEFAULT 0,
            ssl_expiry DATETIME NULL,
            ssl_days_left INT DEFAULT NULL,
            health_score INT DEFAULT 0,
            consecutive_errors INT DEFAULT 0,
            last_error TEXT NULL,
            last_checked DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) $charset;");

        dbDelta("CREATE TABLE $logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL,
            http_code INT DEFAULT 0,
            response_time FLOAT DEFAULT 0,
            message TEXT NULL,
            checked_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY checked_at (checked_at),
            KEY status (status)
        ) $charset;");

        add_option('wpsmm_log_retention_days', 7);
        add_option('wpsmm_check_interval', 120);
        add_option('wpsmm_dark_mode', 0);
        add_option('wpsmm_realtime_mode', 'websocket_fallback');
        add_option('wpsmm_websocket_url', '');
        add_option('wpsmm_enable_telegram_alert', 0);
        add_option('wpsmm_hide_tgmpa_notice', 0);
        update_option('wpsmm_schema_version', self::SCHEMA_VERSION);
    }
}
