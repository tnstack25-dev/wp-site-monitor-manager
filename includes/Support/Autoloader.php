<?php
namespace WPSMM\Support;

if (!defined('ABSPATH')) {
    exit;
}

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function ($class): void {
            if (strpos($class, 'WPSMM\\') !== 0) {
                return;
            }

            $relative = substr($class, strlen('WPSMM\\'));
            $relative_path = str_replace('\\', '/', $relative) . '.php';

            $paths = [
                WPSMM_PLUGIN_DIR . 'includes/' . $relative_path,
                WPSMM_PLUGIN_DIR . 'admin/' . preg_replace('#^Admin/#', '', $relative_path),
            ];

            foreach ($paths as $file) {
                if (is_readable($file)) {
                    require_once $file;
                    return;
                }
            }
        });
    }
}
