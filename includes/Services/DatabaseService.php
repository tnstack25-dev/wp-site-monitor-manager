<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class DatabaseService
{
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
        $backups = self::table('backups');
        $servers = self::table('servers');
        $scans = self::table('malware_scans');

        dbDelta("CREATE TABLE $sites (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            url TEXT NOT NULL,
            group_name VARCHAR(100) DEFAULT '',
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
            server_id BIGINT UNSIGNED DEFAULT 0,
            remote_path TEXT NULL,
            db_name VARCHAR(190) DEFAULT '',
            db_user VARCHAR(190) DEFAULT '',
            db_pass TEXT NULL,
            backup_secret VARCHAR(190) DEFAULT '',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY server_id (server_id)
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

        dbDelta("CREATE TABLE $backups (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id BIGINT UNSIGNED DEFAULT 0,
            job_id VARCHAR(64) DEFAULT '',
            file_name VARCHAR(255) NOT NULL,
            file_path TEXT NULL,
            file_size BIGINT DEFAULT 0,
            storage VARCHAR(30) DEFAULT 'local',
            drive_file_id VARCHAR(190) DEFAULT '',
            drive_link TEXT NULL,
            status VARCHAR(30) DEFAULT 'queued',
            progress INT DEFAULT 0,
            message TEXT NULL,
            created_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY job_id (job_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset;");

        dbDelta("CREATE TABLE $servers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            host VARCHAR(190) NOT NULL,
            port INT DEFAULT 22,
            username VARCHAR(190) NOT NULL,
            auth_type VARCHAR(30) DEFAULT 'key_path',
            key_path TEXT NULL,
            password TEXT NULL,
            backup_path TEXT NULL,
            status VARCHAR(30) DEFAULT 'unknown',
            last_checked DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY host (host)
        ) $charset;");

        dbDelta("CREATE TABLE $scans (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id BIGINT UNSIGNED DEFAULT 0,
            job_id VARCHAR(64) DEFAULT '',
            status VARCHAR(30) DEFAULT 'queued',
            progress INT DEFAULT 0,
            suspicious_count INT DEFAULT 0,
            scanned_files INT DEFAULT 0,
            findings LONGTEXT NULL,
            message TEXT NULL,
            created_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            PRIMARY KEY (id),
            KEY site_id (site_id),
            KEY job_id (job_id),
            KEY status (status)
        ) $charset;");

        add_option('wpsmm_log_retention_days', 7);
        add_option('wpsmm_check_interval', 120);
        add_option('wpsmm_dark_mode', 0);
        add_option('wpsmm_realtime_mode', 'websocket_fallback');
        add_option('wpsmm_websocket_url', '');
        add_option('wpsmm_backup_schedule', 'daily');
        add_option('wpsmm_backup_hour', '02:00');
        add_option('wpsmm_backup_retention', 3);
    }
}
