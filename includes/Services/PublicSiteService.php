<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class PublicSiteService
{
    public static function resolveHostIps(string $url): array
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return [];
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ip'])) {
                        $ips[] = (string) $record['ip'];
                    }
                    if (!empty($record['ipv6'])) {
                        $ips[] = (string) $record['ipv6'];
                    }
                }
            }
        }

        $resolved = gethostbynamel($host);
        if (is_array($resolved)) {
            $ips = array_merge($ips, $resolved);
        }

        $fallback = gethostbyname($host);
        if ($fallback && $fallback !== $host) {
            $ips[] = $fallback;
        }

        return array_values(array_unique(array_filter(array_map('trim', $ips))));
    }

    public static function fetchPublicProfile(string $url): array
    {
        $base = self::publicApiBase($url);
        if ($base === '') {
            return ['available' => false, 'message' => 'URL không hợp lệ hoặc không phải HTTP/HTTPS công khai.'];
        }

        $root = self::requestJson($base);
        $site = is_array($root) ? [
            'name' => (string) ($root['name'] ?? ''),
            'description' => (string) ($root['description'] ?? ''),
            'url' => (string) ($root['url'] ?? ''),
            'home' => (string) ($root['home'] ?? ''),
            'gmt_offset' => $root['gmt_offset'] ?? null,
            'timezone_string' => (string) ($root['timezone_string'] ?? ''),
            'namespaces' => array_values((array) ($root['namespaces'] ?? [])),
        ] : [];

        $posts = self::fetchRestTotal($base . 'wp/v2/posts');
        $pages = self::fetchRestTotal($base . 'wp/v2/pages');
        $products = self::fetchProductTotal($base, $root);
        $types = self::fetchPublicTypes($base);

        return [
            'available' => $posts !== null || $pages !== null || $site !== [],
            'source' => 'wp_rest',
            'site' => $site,
            'content' => [
                'posts' => $posts,
                'pages' => $pages,
                'products' => $products['count'],
                'woocommerce_active' => $products['active'],
                'public_post_types' => $types,
            ],
            'network' => [
                'hostname' => (string) parse_url($url, PHP_URL_HOST),
                'public_ips' => self::resolveHostIps($url),
            ],
        ];
    }

    public static function enrichInventory(string $url, array $inventory): array
    {
        $dnsIps = self::resolveHostIps($url);
        if (!isset($inventory['network']) || !is_array($inventory['network'])) {
            $inventory['network'] = [];
        }
        $inventory['network']['dns_ips'] = $dnsIps;
        $inventory['network']['public_ips'] = array_values(array_unique(array_filter(array_merge(
            (array) ($inventory['network']['public_ips'] ?? []),
            $dnsIps
        ))));

        $needsContent = empty($inventory['content']) || !is_array($inventory['content']);
        $needsPublic = empty($inventory['public_site']) || !is_array($inventory['public_site']);
        if (!$needsContent && !$needsPublic) {
            return $inventory;
        }

        $profile = self::fetchPublicProfile($url);
        if (empty($profile['available'])) {
            return $inventory;
        }

        if ($needsPublic && !empty($profile['site'])) {
            $inventory['public_site'] = array_merge([
                'name' => (string) ($profile['site']['name'] ?? ''),
                'description' => (string) ($profile['site']['description'] ?? ''),
                'url' => (string) ($profile['site']['home'] ?? $profile['site']['url'] ?? ''),
                'timezone' => (string) ($profile['site']['timezone_string'] ?? ''),
            ], (array) ($inventory['public_site'] ?? []));
        }

        if ($needsContent && !empty($profile['content'])) {
            $inventory['content'] = $profile['content'];
            $inventory['content']['source'] = 'wp_rest_fallback';
        }

        return $inventory;
    }

    private static function publicApiBase(string $url): string
    {
        $url = SecurityService::publicHttpUrl(untrailingslashit(trim($url)));
        if ($url === '') {
            return '';
        }
        return trailingslashit($url) . 'wp-json/';
    }

    private static function requestJson(string $endpoint): ?array
    {
        $args = [
            'timeout' => 10,
            'redirection' => 3,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'headers' => ['Accept' => 'application/json', 'User-Agent' => 'WPSMM/' . WPSMM_VERSION],
        ];
        $response = (defined('WPSMM_ALLOW_PRIVATE_HOSTS') && WPSMM_ALLOW_PRIVATE_HOSTS)
            ? wp_remote_get($endpoint, $args)
            : wp_safe_remote_get($endpoint, $args);
        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
            return null;
        }
        $payload = json_decode((string) wp_remote_retrieve_body($response), true);
        return is_array($payload) ? $payload : null;
    }

    private static function fetchRestTotal(string $endpoint): ?int
    {
        $args = [
            'timeout' => 10,
            'redirection' => 3,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'headers' => ['User-Agent' => 'WPSMM/' . WPSMM_VERSION],
        ];
        $response = (defined('WPSMM_ALLOW_PRIVATE_HOSTS') && WPSMM_ALLOW_PRIVATE_HOSTS)
            ? wp_remote_head(add_query_arg(['per_page' => 1], $endpoint), $args)
            : wp_safe_remote_head(add_query_arg(['per_page' => 1], $endpoint), $args);
        if (is_wp_error($response)) {
            $response = (defined('WPSMM_ALLOW_PRIVATE_HOSTS') && WPSMM_ALLOW_PRIVATE_HOSTS)
                ? wp_remote_get(add_query_arg(['per_page' => 1, '_fields' => 'id'], $endpoint), $args)
                : wp_safe_remote_get(add_query_arg(['per_page' => 1, '_fields' => 'id'], $endpoint), $args);
        }
        if (is_wp_error($response)) {
            return null;
        }
        $total = wp_remote_retrieve_header($response, 'x-wp-total');
        return $total !== '' ? (int) $total : null;
    }

    private static function fetchProductTotal(string $base, ?array $root): array
    {
        $active = is_array($root) && in_array('wc/v3', (array) ($root['namespaces'] ?? []), true)
            || is_array($root) && in_array('wc/store', (array) ($root['namespaces'] ?? []), true);

        if (!$active) {
            return ['active' => false, 'count' => null];
        }

        foreach (['wc/store/v1/products', 'wc/store/products'] as $route) {
            $count = self::fetchRestTotal($base . $route);
            if ($count !== null) {
                return ['active' => true, 'count' => $count];
            }
        }

        return ['active' => true, 'count' => null];
    }

    private static function fetchPublicTypes(string $base): array
    {
        $payload = self::requestJson($base . 'wp/v2/types');
        if (!is_array($payload)) {
            return [];
        }

        $types = [];
        foreach ($payload as $slug => $type) {
            if (!is_array($type) || $slug === 'attachment' || in_array($slug, ['post', 'page', 'product'], true)) {
                continue;
            }
            $restBase = (string) ($type['rest_base'] ?? $slug);
            $count = self::fetchRestTotal($base . 'wp/v2/' . rawurlencode($restBase));
            if ($count === null || $count <= 0) {
                continue;
            }
            $types[] = [
                'slug' => (string) $slug,
                'label' => (string) ($type['name'] ?? $slug),
                'count' => $count,
            ];
        }
        return $types;
    }
}