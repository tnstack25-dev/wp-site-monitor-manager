<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class SecurityService
{
    private const SECRET_PREFIX = 'wpsmm:v1:';

    public static function encryptSecret(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strpos($value, self::SECRET_PREFIX) === 0) {
            return $value;
        }
        if (!function_exists('openssl_encrypt')) {
            return $value;
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'aes-256-cbc', self::secretKey(), OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return $value;
        }
        $mac = hash_hmac('sha256', $iv . $ciphertext, self::secretKey(), true);
        return self::SECRET_PREFIX . base64_encode($iv . $mac . $ciphertext);
    }

    public static function decryptSecret(?string $value): string
    {
        $value = (string) $value;
        if ($value === '' || strpos($value, self::SECRET_PREFIX) !== 0 || !function_exists('openssl_decrypt')) {
            return $value;
        }

        $raw = base64_decode(substr($value, strlen(self::SECRET_PREFIX)), true);
        if ($raw === false || strlen($raw) <= 48) {
            return '';
        }

        $iv = substr($raw, 0, 16);
        $mac = substr($raw, 16, 32);
        $ciphertext = substr($raw, 48);
        $calc = hash_hmac('sha256', $iv . $ciphertext, self::secretKey(), true);
        if (!hash_equals($mac, $calc)) {
            return '';
        }

        $plain = openssl_decrypt($ciphertext, 'aes-256-cbc', self::secretKey(), OPENSSL_RAW_DATA, $iv);
        return $plain === false ? '' : $plain;
    }

    public static function publicHttpUrl(string $url, bool $httpsOnly = false): string
    {
        return self::publicUrl($url, $httpsOnly ? ['https'] : ['http', 'https']);
    }

    public static function publicUrl(string $url, array $allowedSchemes): string
    {
        $url = esc_url_raw(trim($url));
        $parts = wp_parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');

        if (!$host || !in_array($scheme, $allowedSchemes, true)) {
            return '';
        }
        if (!self::isPublicHost($host)) {
            return '';
        }
        return $url;
    }

    public static function isPublicHost(string $host): bool
    {
        $host = trim($host, " \t\n\r\0\x0B[]");
        if (defined('WPSMM_ALLOW_PRIVATE_HOSTS') && WPSMM_ALLOW_PRIVATE_HOSTS) {
            return $host !== '';
        }
        if ($host === '' || in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);
        if (!$ips) {
            return false;
        }

        foreach ($ips as $ip) {
            if (!self::isPublicIp($ip)) {
                return false;
            }
        }
        return true;
    }

    public static function backupDir(): string
    {
        $upload = wp_upload_dir();
        return trailingslashit($upload['basedir']) . 'wpsmm-backups/';
    }

    public static function prepareBackupDir(): string
    {
        $dir = self::backupDir();
        wp_mkdir_p($dir);
        if (is_dir($dir)) {
            $htaccess = $dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Require all denied\nDeny from all\n");
            }
            $webConfig = $dir . 'web.config';
            if (!file_exists($webConfig)) {
                file_put_contents($webConfig, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n");
            }
            $index = $dir . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }
        return $dir;
    }

    public static function backupPathAllowed(string $path): bool
    {
        $base = realpath(self::backupDir());
        $real = realpath($path);
        if (!$base || !$real) {
            return false;
        }
        return strpos($real, trailingslashit($base)) === 0;
    }

    public static function redactObject($row): array
    {
        $data = (array) $row;
        foreach (['db_pass', 'backup_secret', 'password', 'key_path', 'file_path', 'drive_file_id'] as $key) {
            if (array_key_exists($key, $data)) {
                unset($data[$key]);
            }
        }
        return $data;
    }

    private static function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return false;
    }

    private static function secretKey(): string
    {
        $material = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '') . (defined('LOGGED_IN_KEY') ? LOGGED_IN_KEY : '');
        if ($material === '') {
            $material = wp_salt('auth');
        }
        return hash('sha256', $material, true);
    }
}
