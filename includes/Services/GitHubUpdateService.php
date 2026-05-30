<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class GitHubUpdateService
{
    private const CACHE_KEY = 'wpsmm_github_update_release';
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    public static function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [self::class, 'checkForUpdates']);
        add_filter('plugins_api', [self::class, 'pluginInfo'], 20, 3);
        add_filter('upgrader_source_selection', [self::class, 'renameSourceDirectory'], 10, 4);
    }

    public static function checkForUpdates($transient)
    {
        if (!is_object($transient)) {
            return $transient;
        }

        $release = self::latestRelease();
        if (!$release || empty($release['version']) || version_compare($release['version'], WPSMM_VERSION, '<=')) {
            return $transient;
        }

        $plugin = plugin_basename(WPSMM_PLUGIN_FILE);
        $transient->response[$plugin] = (object) [
            'id' => $plugin,
            'slug' => dirname($plugin),
            'plugin' => $plugin,
            'new_version' => $release['version'],
            'url' => $release['html_url'] ?? self::repoUrl(),
            'package' => $release['package'] ?? '',
            'tested' => get_bloginfo('version'),
            'requires_php' => WPSMM_MIN_PHP,
        ];

        return $transient;
    }

    public static function pluginInfo($result, string $action, $args)
    {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== dirname(plugin_basename(WPSMM_PLUGIN_FILE))) {
            return $result;
        }

        $release = self::latestRelease(true);
        if (!$release) {
            return $result;
        }

        return (object) [
            'name' => 'WP Site Monitor Manager',
            'slug' => dirname(plugin_basename(WPSMM_PLUGIN_FILE)),
            'version' => $release['version'] ?? WPSMM_VERSION,
            'author' => '<a href="' . esc_url(self::repoUrl()) . '">GitHub</a>',
            'homepage' => self::repoUrl(),
            'requires' => '5.8',
            'requires_php' => WPSMM_MIN_PHP,
            'tested' => get_bloginfo('version'),
            'download_link' => $release['package'] ?? '',
            'last_updated' => $release['published_at'] ?? '',
            'sections' => [
                'description' => 'WordPress website uptime, HTTP status, SSL, realtime dashboard, and log monitoring.',
                'changelog' => wp_kses_post(nl2br((string) ($release['body'] ?? 'No changelog provided.'))),
            ],
        ];
    }

    public static function renameSourceDirectory($source, $remoteSource, $upgrader, $hookExtra)
    {
        if (empty($hookExtra['plugin']) || $hookExtra['plugin'] !== plugin_basename(WPSMM_PLUGIN_FILE)) {
            return $source;
        }

        global $wp_filesystem;
        if (!$wp_filesystem) {
            return $source;
        }

        $target = trailingslashit($remoteSource) . dirname(plugin_basename(WPSMM_PLUGIN_FILE));
        if (trailingslashit($source) === trailingslashit($target)) {
            return $source;
        }
        if ($wp_filesystem->exists($target)) {
            $wp_filesystem->delete($target, true);
        }
        if ($wp_filesystem->move($source, $target)) {
            return $target;
        }
        return $source;
    }

    public static function clearCache(): void
    {
        delete_site_transient(self::CACHE_KEY);
    }

    private static function latestRelease(bool $force = false): array
    {
        $repo = self::repo();
        if (!$repo) {
            return [];
        }

        if (!$force) {
            $cached = get_site_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $response = wp_remote_get('https://api.github.com/repos/' . rawurlencode($repo['owner']) . '/' . rawurlencode($repo['repo']) . '/releases/latest', [
            'timeout' => 12,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'WPSMM/' . WPSMM_VERSION . '; ' . home_url(),
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (!is_array($body) || !empty($body['draft']) || !empty($body['prerelease'])) {
            return [];
        }

        $version = self::normalizeVersion((string) ($body['tag_name'] ?? $body['name'] ?? ''));
        if (!$version) {
            return [];
        }

        $release = [
            'version' => $version,
            'html_url' => esc_url_raw((string) ($body['html_url'] ?? self::repoUrl())),
            'package' => self::packageUrl($body),
            'published_at' => sanitize_text_field((string) ($body['published_at'] ?? '')),
            'body' => wp_strip_all_tags((string) ($body['body'] ?? '')),
        ];

        set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);
        return $release;
    }

    private static function packageUrl(array $release): string
    {
        $assetName = defined('WPSMM_GITHUB_ASSET_NAME') ? (string) WPSMM_GITHUB_ASSET_NAME : trim((string) get_option('wpsmm_github_asset_name', ''));
        foreach ((array) ($release['assets'] ?? []) as $asset) {
            $name = (string) ($asset['name'] ?? '');
            if ($assetName !== '' && $name !== $assetName) {
                continue;
            }
            if ($assetName === '' && !preg_match('/\.zip$/i', $name)) {
                continue;
            }
            return esc_url_raw((string) ($asset['browser_download_url'] ?? ''));
        }
        return esc_url_raw((string) ($release['zipball_url'] ?? ''));
    }

    private static function normalizeVersion(string $tag): string
    {
        $tag = trim($tag);
        if (preg_match('/v?(\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?)/', $tag, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private static function repo(): array
    {
        $value = defined('WPSMM_GITHUB_REPO') ? (string) WPSMM_GITHUB_REPO : trim((string) get_option('wpsmm_github_repo', ''));
        if (!preg_match('~^([A-Za-z0-9_.-]+)/([A-Za-z0-9_.-]+)$~', $value, $matches)) {
            return [];
        }
        return ['owner' => $matches[1], 'repo' => $matches[2]];
    }

    private static function repoUrl(): string
    {
        $repo = self::repo();
        return $repo ? 'https://github.com/' . rawurlencode($repo['owner']) . '/' . rawurlencode($repo['repo']) : '';
    }
}
