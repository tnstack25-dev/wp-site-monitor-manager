<?php
/**
 * Plugin Name: WP Site Monitor Manager
 * Description: Bảng điều khiển giám sát nhiều website WordPress: uptime, trạng thái HTTP, SSL, REST API, thời gian thực và biểu đồ.
 * Version: 2.0.0
 * Author: TNStack
 * Author URI: https://tnstack.com
 * Text Domain: wp-site-monitor-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WPSMM_VERSION', '2.0.0');
define('WPSMM_PLUGIN_FILE', __FILE__);
define('WPSMM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPSMM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPSMM_MIN_PHP', '7.4');
define('WPSMM_GITHUB_REPO', 'tnstack25-dev/wp-site-monitor-manager');

require_once WPSMM_PLUGIN_DIR . 'includes/Support/Autoloader.php';
WPSMM\Support\Autoloader::register();

function wpsmm_app(): WPSMM\Plugin
{
    return WPSMM\Plugin::instance();
}

add_action('plugins_loaded', static function () {
    if (version_compare(PHP_VERSION, WPSMM_MIN_PHP, '<')) {
        add_action('admin_notices', static function () {
            echo '<div class="notice notice-error"><p>WP Site Monitor Manager yeu cau PHP ' . esc_html(WPSMM_MIN_PHP) . ' tro len.</p></div>';
        });
        return;
    }
    wpsmm_app()->boot();
});

register_activation_hook(__FILE__, static function () {
    WPSMM\Plugin::instance()->activate();
});

register_deactivation_hook(__FILE__, static function () {
    WPSMM\Plugin::instance()->deactivate();
});
