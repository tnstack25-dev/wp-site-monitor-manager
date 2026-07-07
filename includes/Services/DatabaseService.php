<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class DatabaseService
{
    public const SCHEMA_VERSION = '1.0.7';

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
        $incidents = self::table('incidents');
        $groups = self::table('groups');

        dbDelta("CREATE TABLE $groups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL DEFAULT '',
            description VARCHAR(255) DEFAULT '',
            color VARCHAR(7) DEFAULT '#5b7cfa',
            sort_order INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY sort_order (sort_order)
        ) $charset;");

        dbDelta("CREATE TABLE $sites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            url TEXT NOT NULL,
            group_id BIGINT UNSIGNED DEFAULT 0,
            group_name VARCHAR(100) DEFAULT '',
            login_url TEXT NULL,
            login_username TEXT NULL,
            login_password TEXT NULL,
            agent_secret TEXT NULL,
            monitor_enabled TINYINT(1) DEFAULT 1,
            health_path VARCHAR(255) DEFAULT '',
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
            agent_status VARCHAR(30) DEFAULT 'unknown',
            agent_checked_at DATETIME NULL,
            agent_heartbeat_at DATETIME NULL,
            agent_health TEXT NULL,
            dns_records TEXT NULL,
            dns_checked_at DATETIME NULL,
            dns_changed_at DATETIME NULL,
            last_checked DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY group_id (group_id)
        ) $charset;");

        dbDelta("CREATE TABLE $logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL,
            http_code INT DEFAULT 0,
            response_time FLOAT DEFAULT 0,
            message TEXT NULL,
            endpoint_url TEXT NULL,
            technical_details TEXT NULL,
            checked_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY checked_at (checked_at),
            KEY status (status)
        ) $charset;");

        dbDelta("CREATE TABLE $incidents (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL,
            message TEXT NULL,
            started_at DATETIME NOT NULL,
            resolved_at DATETIME NULL,
            last_seen_at DATETIME NOT NULL,
            check_count INT DEFAULT 1,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY resolved_at (resolved_at),
            KEY started_at (started_at)
        ) $charset;");

        add_option('wpsmm_log_retention_days', 7);
        add_option('wpsmm_check_interval', 120);
        add_option('wpsmm_batch_size', 10);
        add_option('wpsmm_batch_concurrency', 30);
        add_option('wpsmm_batch_max_ssl_checks', 25);
        add_option('wpsmm_homepage_body_limit', 131072);
        add_option('wpsmm_last_cron_run', 0);
        add_option('wpsmm_dark_mode', 0);
        add_option('wpsmm_realtime_mode', 'websocket_fallback');
        add_option('wpsmm_websocket_url', '');
        add_option('wpsmm_enable_telegram_alert', 0);
        add_option('wpsmm_hide_tgmpa_notice', 0);
        add_option('wpsmm_dns_monitor_enabled', 1);
        add_option('wpsmm_dns_alert_enabled', 1);
        add_option('wpsmm_enable_recovery_alert', 1);
        add_option('wpsmm_alert_cooldown_p1', 900);
        add_option('wpsmm_alert_cooldown_p2', 1800);
        add_option('wpsmm_alert_cooldown_p3', 3600);
        add_option('wpsmm_alert_cooldown_recovery', 1800);
        update_option('wpsmm_schema_version', self::SCHEMA_VERSION);
        \WPSMM\Repositories\GroupRepository::migrateLegacyGroupNames();
    }
}
