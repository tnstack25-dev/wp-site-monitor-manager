<?php
namespace WPSMM\Services;

use WPSMM\Repositories\SiteRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class DnsMonitorService
{
    public static function checkAll(): void
    {
        if (!get_option('wpsmm_dns_monitor_enabled', 1)) {
            return;
        }
        $offset = 0;
        $limit = 100;
        do {
            $sites = SiteRepository::enabledBatch($limit, $offset);
            foreach ($sites as $site) {
                self::checkSite((int) $site->id, (string) $site->url, (string) ($site->dns_records ?? ''));
            }
            $offset += $limit;
        } while (count($sites) === $limit);
    }

    public static function checkSite(int $siteId, string $url, string $previousJson = ''): array
    {
        global $wpdb;
        $host = (string) parse_url($url, PHP_URL_HOST);
        if ($host === '') {
            return ['ok' => false, 'message' => 'Không xác định được hostname'];
        }

        $records = self::resolveRecords($host);
        $payload = wp_json_encode($records);
        $previous = json_decode($previousJson, true);
        $changed = is_array($previous) && self::recordsChanged($previous, $records);
        $now = current_time('mysql');

        $update = [
            'dns_records' => $payload,
            'dns_checked_at' => $now,
            'updated_at' => $now,
        ];
        if ($changed) {
            $update['dns_changed_at'] = $now;
            NotificationService::notifyDnsChange($siteId, $host, $previous, $records);
        }
        $wpdb->update(DatabaseService::table('sites'), $update, ['id' => $siteId]);

        return [
            'ok' => true,
            'host' => $host,
            'records' => $records,
            'changed' => $changed,
            'checked_at' => $now,
        ];
    }

    public static function formatRecords(?string $json): string
    {
        $data = json_decode((string) $json, true);
        if (!is_array($data)) {
            return '-';
        }
        $parts = [];
        foreach (['a' => 'A', 'aaaa' => 'AAAA'] as $key => $label) {
            $values = array_values(array_unique(array_filter(array_map('strval', (array) ($data[$key] ?? [])))));
            if ($values) {
                $parts[] = $label . ': ' . implode(', ', $values);
            }
        }
        return $parts ? implode(' · ', $parts) : '-';
    }

    private static function resolveRecords(string $host): array
    {
        $records = ['a' => [], 'aaaa' => []];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $records['aaaa'][] = $host;
            } else {
                $records['a'][] = $host;
            }
            return self::normalizeRecords($records);
        }

        if (function_exists('dns_get_record')) {
            $rows = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!empty($row['ip'])) {
                        $records['a'][] = (string) $row['ip'];
                    }
                    if (!empty($row['ipv6'])) {
                        $records['aaaa'][] = (string) $row['ipv6'];
                    }
                }
            }
        }

        $resolved = gethostbynamel($host);
        if (is_array($resolved)) {
            $records['a'] = array_merge($records['a'], $resolved);
        }

        return self::normalizeRecords($records);
    }

    private static function normalizeRecords(array $records): array
    {
        foreach (['a', 'aaaa'] as $type) {
            $records[$type] = array_values(array_unique(array_filter(array_map('trim', (array) ($records[$type] ?? [])))));
            sort($records[$type]);
        }
        return $records;
    }

    private static function recordsChanged(array $previous, array $current): bool
    {
        foreach (['a', 'aaaa'] as $type) {
            $old = array_values(array_unique(array_filter(array_map('strval', (array) ($previous[$type] ?? [])))));
            $new = array_values(array_unique(array_filter(array_map('strval', (array) ($current[$type] ?? [])))));
            sort($old);
            sort($new);
            if ($old !== $new) {
                return true;
            }
        }
        return false;
    }
}