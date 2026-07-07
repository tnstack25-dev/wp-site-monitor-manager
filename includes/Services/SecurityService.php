<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class SecurityService
{
    private const SECRET_PREFIX = 'wpsmm:v1:';

    public static function supportsEncryption(): bool
    {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
    }

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

    public static function publicLoginUrl(string $url): string
    {
        $allowHttp = defined('WPSMM_ALLOW_INSECURE_LOGIN') && WPSMM_ALLOW_INSECURE_LOGIN;
        return self::publicHttpUrl($url, !$allowHttp);
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

    public static function redactObject($row): array
    {
        $data = (array) $row;
        foreach (['db_pass', 'backup_secret', 'password', 'login_username', 'login_password', 'agent_secret', 'key_path', 'file_path', 'drive_file_id'] as $key) {
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

    public static function verifyAgentSignature(\WP_REST_Request $request, string $secret)
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $secret)) {
            return new \WP_Error('wpsmm_signature_invalid', 'Khóa Agent không hợp lệ.', ['status' => 403]);
        }
        $rateKey = 'wpsmm_signature_rate_' . md5(self::requestIp());
        $attempts = (int) get_transient($rateKey);
        if ($attempts >= 10) {
            return new \WP_Error('wpsmm_signature_rate_limit', 'Có quá nhiều yêu cầu không hợp lệ.', ['status' => 429]);
        }
        if (strlen($request->get_body()) > 8192) {
            return new \WP_Error('wpsmm_request_too_large', 'Dữ liệu yêu cầu vượt quá giới hạn.', ['status' => 413]);
        }

        $timestamp = (string) $request->get_header('x-wpma-timestamp');
        $nonce = strtolower((string) $request->get_header('x-wpma-nonce'));
        $signature = strtolower((string) $request->get_header('x-wpma-signature'));
        if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 120 || !preg_match('/^[a-f0-9]{32}$/', $nonce) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
            set_transient($rateKey, $attempts + 1, 5 * MINUTE_IN_SECONDS);
            return new \WP_Error('wpsmm_signature_invalid', 'Chữ ký yêu cầu không hợp lệ.', ['status' => 403]);
        }

        $nonceKey = 'wpsmm_nonce_' . hash('sha256', $nonce);
        if (get_transient($nonceKey)) {
            set_transient($rateKey, $attempts + 1, 5 * MINUTE_IN_SECONDS);
            return new \WP_Error('wpsmm_signature_replay', 'Yêu cầu đã được sử dụng.', ['status' => 403]);
        }

        $canonical = $timestamp . "\n" . $nonce . "\n" . $request->get_route() . "\n" . hash('sha256', $request->get_body());
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (!hash_equals($expected, $signature)) {
            set_transient($rateKey, $attempts + 1, 5 * MINUTE_IN_SECONDS);
            return new \WP_Error('wpsmm_signature_invalid', 'Chữ ký yêu cầu không hợp lệ.', ['status' => 403]);
        }

        set_transient($nonceKey, 1, 5 * MINUTE_IN_SECONDS);
        delete_transient($rateKey);
        return true;
    }

    private static function requestIp(): string
    {
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : 'unknown';
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
