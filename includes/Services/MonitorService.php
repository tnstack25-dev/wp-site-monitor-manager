<?php
namespace WPSMM\Services;

use WPSMM\Plugin;
use WPSMM\Repositories\SiteRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class MonitorService
{
    public const MAX_BATCH_SIZE = 200;
    private const DEFAULT_SSL_REFRESH = 21600;
    private const DEFAULT_UPTIME_REFRESH = 1800;
    private const DEFAULT_HOMEPAGE_BODY_LIMIT = 131072;
    private const HOMEPAGE_PROBE = 'homepage';
    private static array $batchOptions = [];
    private static array $probeCache = [];
    private static array $sslHostCache = [];
    private static array $uptimeCache = [];
    private static array $activeIncidents = [];
    private static int $sslChecksRun = 0;
    private static bool $contentChecksNeeded = false;

    public static function checkAll(bool $manual = false): void
    {
        update_option('wpsmm_last_cron_run', time(), false);
        self::prepareBatchRuntime();
        $limit = self::batchSize();
        $total = SiteRepository::enabledCount();
        $offset = $total ? ((int) get_option('wpsmm_batch_offset', 0) % $total) : 0;
        $sites = SiteRepository::enabledBatch($limit, $offset);
        if (!$sites && $offset > 0) {
            $offset = 0;
            $sites = SiteRepository::enabledBatch($limit, 0);
        }
        self::checkSitesBatch($sites, $manual);
        update_option('wpsmm_batch_offset', $total ? (($offset + count($sites)) % $total) : 0, false);
    }

    public static function checkBatch(array $siteIds, bool $manual = true): array
    {
        self::prepareBatchRuntime();
        $sites = [];
        foreach (array_values(array_unique(array_map('absint', $siteIds))) as $siteId) {
            $site = SiteRepository::find($siteId);
            if ($site) {
                $sites[] = $site;
            }
        }
        return self::checkSitesBatch($sites, $manual);
    }

    public static function check(int $siteId, bool $manual = false): array
    {
        $site = SiteRepository::find($siteId);
        if (!$site) {
            return ['ok' => false, 'message' => 'Website không tồn tại'];
        }
        if (!$manual && empty($site->monitor_enabled)) {
            return ['ok' => false, 'status' => 'paused', 'message' => 'Giám sát đang tạm dừng'];
        }
        if (!SecurityService::publicHttpUrl((string) $site->url)) {
            return ['ok' => false, 'message' => 'URL không hợp lệ hoặc không phải HTTP/HTTPS công khai'];
        }
        $responses = self::fetchHttpResponses([$site], $manual);
        return self::processSite($site, $manual, $responses[(int) $site->id] ?? []);
    }

    public static function cleanupOldLogs(): void
    {
        global $wpdb;
        $days = max(1, (int) get_option('wpsmm_log_retention_days', 7));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . DatabaseService::table('logs') . ' WHERE checked_at < DATE_SUB(%s, INTERVAL %d DAY)', current_time('mysql'), $days));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . DatabaseService::table('incidents') . ' WHERE resolved_at IS NOT NULL AND resolved_at < DATE_SUB(%s, INTERVAL %d DAY)', current_time('mysql'), $days));
    }

    public static function stats(): array
    {
        $cached = get_transient('wpsmm_stats_cache');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;
        $table = DatabaseService::table('sites');
        $summary = SiteRepository::statusSummary();
        $avgResponse = (float) $wpdb->get_var("SELECT AVG(response_time) FROM $table WHERE monitor_enabled=1 AND response_time > 0");
        $avgUptime = (float) $wpdb->get_var("SELECT AVG(uptime_percent) FROM $table WHERE monitor_enabled=1");
        $avgHealth = $summary['monitored'] ? (int) $wpdb->get_var("SELECT AVG(health_score) FROM $table WHERE monitor_enabled=1") : 0;
        $lastCronRun = (int) get_option('wpsmm_last_cron_run', 0);
        $cronHealthy = $lastCronRun > 0 && (time() - $lastCronRun) <= max(300, Plugin::checkInterval() * 3);
        $stats = array_merge($summary, compact('avgResponse', 'avgUptime', 'avgHealth', 'lastCronRun', 'cronHealthy'), [
            'sla' => SlaService::aggregate(),
        ]);
        set_transient('wpsmm_stats_cache', $stats, 20);
        return $stats;
    }

    public static function chartData(int $hours = 24): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE_FORMAT(checked_at, '%%H:00') as label, AVG(response_time) as response, SUM(status IN ('online','redirect')) as online_count, COUNT(*) as total_count FROM " . DatabaseService::table('logs') . " WHERE checked_at >= DATE_SUB(%s, INTERVAL %d HOUR) GROUP BY DATE_FORMAT(checked_at, '%%Y-%%m-%%d %%H') ORDER BY MIN(checked_at) ASC", current_time('mysql'), $hours), ARRAY_A) ?: [];
        return array_map(static function ($r) {
            return ['label' => $r['label'], 'response' => round((float) $r['response'], 3), 'uptime' => $r['total_count'] ? round(((int) $r['online_count'] / (int) $r['total_count']) * 100, 1) : 0];
        }, $rows);
    }

    public static function siteUptime(int $siteId, int $days = 30): array
    {
        global $wpdb;
        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . DatabaseService::table('logs') . ' WHERE site_id=%d AND checked_at >= DATE_SUB(%s, INTERVAL %d DAY)', $siteId, current_time('mysql'), $days));
        if (!$total) {
            return ['uptime' => 0, 'downtime_minutes' => 0];
        }
        $up = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . DatabaseService::table('logs') . " WHERE site_id=%d AND checked_at >= DATE_SUB(%s, INTERVAL %d DAY) AND status IN ('online','redirect')", $siteId, current_time('mysql'), $days));
        return ['uptime' => round(($up / $total) * 100, 2), 'downtime_minutes' => ($total - $up) * Plugin::checkIntervalMinutes()];
    }

    public static function batchSize(): int
    {
        return max(1, min(self::MAX_BATCH_SIZE, (int) get_option('wpsmm_batch_size', 10)));
    }

    private static function batchTimeBudget(): int
    {
        return max(30, min(600, (int) get_option('wpsmm_batch_time_budget', 240)));
    }

    private static function batchConcurrency(): int
    {
        return max(10, min(80, (int) get_option('wpsmm_batch_concurrency', 30)));
    }

    private static function maxSslChecksPerBatch(): int
    {
        return max(1, min(100, (int) get_option('wpsmm_batch_max_ssl_checks', 25)));
    }

    private static function homepageBodyLimit(): int
    {
        return max(16384, min(524288, (int) get_option('wpsmm_homepage_body_limit', self::DEFAULT_HOMEPAGE_BODY_LIMIT)));
    }

    private static function probeBodyLimit(string $probe): int
    {
        return $probe === self::HOMEPAGE_PROBE ? self::homepageBodyLimit() : 65536;
    }

    private static function prepareBatchRuntime(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
    }

    private static function checkSitesBatch(array $sites, bool $manual): array
    {
        if (!$sites) {
            return [];
        }

        self::resetBatchState();
        self::loadBatchOptions();
        self::prepareBatchContext($sites);

        $deadline = time() + self::batchTimeBudget();
        $responses = self::fetchHttpResponses($sites, $manual);
        $pending = [];
        foreach ($sites as $site) {
            if (time() >= $deadline) {
                break;
            }
            $prepared = self::buildSiteCheckResult($site, $manual, $responses[(int) $site->id] ?? []);
            if ($prepared) {
                $pending[] = $prepared;
            }
        }

        self::persistBatchResults($pending);

        $results = [];
        foreach ($pending as $row) {
            $results[$row['site_id']] = $row['result'];
        }
        return $results;
    }

    private static function resetBatchState(): void
    {
        self::$batchOptions = [];
        self::$probeCache = [];
        self::$sslHostCache = [];
        self::$uptimeCache = [];
        self::$activeIncidents = [];
        self::$sslChecksRun = 0;
        self::$contentChecksNeeded = false;
    }

    private static function loadBatchOptions(): void
    {
        if (self::$batchOptions) {
            return;
        }
        self::$batchOptions = [
            'timeout' => max(5, (int) get_option('wpsmm_timeout', 15)),
            'multi_probe' => (bool) get_option('wpsmm_multi_probe_enabled', 1),
            'min_probes' => max(1, min(3, (int) get_option('wpsmm_min_probes_required', 2))),
            'ssl_warning_days' => max(1, (int) get_option('wpsmm_ssl_warning_days', 14)),
            'ssl_refresh_interval' => max(HOUR_IN_SECONDS, (int) get_option('wpsmm_ssl_refresh_interval', self::DEFAULT_SSL_REFRESH)),
            'uptime_refresh_interval' => max(5 * MINUTE_IN_SECONDS, (int) get_option('wpsmm_uptime_refresh_interval', self::DEFAULT_UPTIME_REFRESH)),
            'suspicious_keywords' => array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) get_option('wpsmm_suspicious_keywords', 'casino,betting,viagra,porn')) ?: [])),
        ];
        self::$contentChecksNeeded = !empty(self::$batchOptions['suspicious_keywords']);
    }

    private static function prepareBatchContext(array $sites): void
    {
        $siteIds = [];
        foreach ($sites as $site) {
            $siteId = (int) $site->id;
            $siteIds[] = $siteId;
            if (trim((string) $site->expected_title) !== '') {
                self::$contentChecksNeeded = true;
            }
            self::$probeCache[$siteId] = self::buildSiteProbes($site);
        }
        if ($siteIds) {
            self::$uptimeCache = SiteRepository::uptimeForSites($siteIds, 30);
            self::$activeIncidents = SiteRepository::activeIncidentsFor($siteIds);
        }
    }

    private static function processSite(object $site, bool $manual, array $probes): array
    {
        self::resetBatchState();
        self::loadBatchOptions();
        self::prepareBatchContext([$site]);
        $prepared = self::buildSiteCheckResult($site, $manual, $probes);
        if (!$prepared) {
            return ['ok' => false, 'message' => 'Không thể xử lý website'];
        }
        self::persistBatchResults([$prepared]);
        return $prepared['result'];
    }

    private static function buildSiteCheckResult(object $site, bool $manual, array $probes): ?array
    {
        $siteId = (int) $site->id;
        if (!$manual && empty($site->monitor_enabled)) {
            return null;
        }
        if (!SecurityService::publicHttpUrl((string) $site->url)) {
            return null;
        }

        $oldStatus = (string) $site->status;
        $evaluation = self::evaluateProbeResults($site, $probes);
        $endpoint = (string) $evaluation['endpoint'];
        $httpCode = (int) $evaluation['http_code'];
        $status = (string) $evaluation['status'];
        $message = (string) $evaluation['message'];
        $responseTime = (float) $evaluation['response_time'];
        $technical = (array) $evaluation['technical'];

        $refreshSsl = self::shouldRefreshSsl($site);
        $ssl = self::resolveSslForSite($site, $refreshSsl);
        if ($ssl['status'] === 'ssl_error') {
            $status = 'ssl_error';
            $message = $ssl['message'];
        } elseif ($ssl['status'] === 'ssl_expiring' && in_array($status, ['online', 'redirect'], true)) {
            $status = 'ssl_expiring';
            $message = $ssl['message'];
        }

        $bad = !in_array($status, ['online', 'redirect'], true);
        $consecutive = $bad ? ((int) $site->consecutive_errors + 1) : 0;
        $health = self::healthScore($status, $responseTime, $ssl['days_left']);
        $agent = (string) ($evaluation['agent_status'] ?? 'unknown');
        $checkedAt = current_time('mysql');
        $period = self::shouldRefreshUptime($site, $status)
            ? (self::canUsePrefetchedUptime($site, $status)
                ? (self::$uptimeCache[$siteId] ?? self::siteUptime($siteId, 30))
                : self::siteUptime($siteId, 30))
            : ['uptime' => (float) $site->uptime_percent, 'downtime_minutes' => (int) $site->downtime_minutes];

        $update = [
            'status' => $status,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'uptime_percent' => $period['uptime'],
            'downtime_minutes' => $period['downtime_minutes'],
            'health_score' => $health,
            'consecutive_errors' => $consecutive,
            'last_error' => $bad ? $message : '',
            'last_checked' => $checkedAt,
            'updated_at' => $checkedAt,
        ];
        if ($refreshSsl) {
            $update['ssl_expiry'] = $ssl['expiry'];
            $update['ssl_days_left'] = $ssl['days_left'];
        }
        if (array_key_exists('agent', $probes)) {
            $update['agent_status'] = $agent;
            $update['agent_checked_at'] = $checkedAt;
        }

        return [
            'site_id' => $siteId,
            'site' => $site,
            'old_status' => $oldStatus,
            'status' => $status,
            'message' => $message,
            'consecutive' => $consecutive,
            'result' => [
                'ok' => true,
                'status' => $status,
                'http_code' => $httpCode,
                'response_time' => $responseTime,
                'message' => $message,
            ],
            'log' => [
                'site_id' => $siteId,
                'status' => $status,
                'http_code' => $httpCode,
                'response_time' => $responseTime,
                'message' => $message,
                'endpoint_url' => $endpoint,
                'technical_details' => wp_json_encode($technical),
                'checked_at' => $checkedAt,
            ],
            'update' => $update,
            'incident' => self::buildIncidentChange($siteId, $status, $message),
        ];
    }

    private static function persistBatchResults(array $rows): void
    {
        if (!$rows) {
            return;
        }

        global $wpdb;
        self::bulkInsertLogs(array_column($rows, 'log'));

        $sitesTable = DatabaseService::table('sites');
        $incidentsTable = DatabaseService::table('incidents');
        $now = current_time('mysql');

        foreach ($rows as $row) {
            $wpdb->update($sitesTable, $row['update'], ['id' => $row['site_id']]);
            $incident = $row['incident'];
            if ($incident['action'] === 'resolve' && $incident['id']) {
                $wpdb->update($incidentsTable, ['resolved_at' => $now, 'last_seen_at' => $now], ['id' => $incident['id']]);
            } elseif ($incident['action'] === 'update' && $incident['id']) {
                $wpdb->update($incidentsTable, [
                    'status' => $row['status'],
                    'message' => $row['message'],
                    'last_seen_at' => $now,
                    'check_count' => $incident['check_count'],
                ], ['id' => $incident['id']]);
            } elseif ($incident['action'] === 'create') {
                $wpdb->insert($incidentsTable, [
                    'site_id' => $row['site_id'],
                    'status' => $row['status'],
                    'message' => $row['message'],
                    'started_at' => $now,
                    'last_seen_at' => $now,
                    'check_count' => 1,
                ]);
            }
            NotificationService::notifyStatusChange($row['site'], $row['old_status'], $row['status'], $row['message'], $row['consecutive']);
        }

        delete_transient('wpsmm_stats_cache');
    }

    private static function bulkInsertLogs(array $logs): void
    {
        global $wpdb;
        $table = DatabaseService::table('logs');
        foreach (array_chunk($logs, 40) as $chunk) {
            $placeholders = [];
            $values = [];
            foreach ($chunk as $log) {
                $placeholders[] = '(%d,%s,%d,%f,%s,%s,%s,%s)';
                array_push(
                    $values,
                    (int) $log['site_id'],
                    (string) $log['status'],
                    (int) $log['http_code'],
                    (float) $log['response_time'],
                    (string) $log['message'],
                    (string) $log['endpoint_url'],
                    (string) $log['technical_details'],
                    (string) $log['checked_at']
                );
            }
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $table (site_id,status,http_code,response_time,message,endpoint_url,technical_details,checked_at) VALUES " . implode(',', $placeholders),
                ...$values
            ));
        }
    }

    private static function buildIncidentChange(int $siteId, string $status, string $message): array
    {
        $healthy = in_array($status, ['online', 'redirect'], true);
        $active = self::$activeIncidents[$siteId] ?? null;
        if ($healthy) {
            return [
                'action' => $active ? 'resolve' : 'none',
                'id' => $active ? (int) $active->id : 0,
                'check_count' => 0,
            ];
        }
        if ($active) {
            return [
                'action' => 'update',
                'id' => (int) $active->id,
                'check_count' => (int) $active->check_count + 1,
            ];
        }
        return ['action' => 'create', 'id' => 0, 'check_count' => 1];
    }

    private static function resolveSslForSite(object $site, bool $refresh): array
    {
        if (!$refresh) {
            return self::cachedSsl($site);
        }
        if (self::$sslChecksRun >= self::maxSslChecksPerBatch()) {
            return self::cachedSsl($site);
        }

        $host = strtolower((string) parse_url((string) $site->url, PHP_URL_HOST));
        if ($host && isset(self::$sslHostCache[$host])) {
            return self::$sslHostCache[$host];
        }

        self::$sslChecksRun++;
        $ssl = self::sslInfo((string) $site->url);
        if ($host) {
            self::$sslHostCache[$host] = $ssl;
        }
        return $ssl;
    }

    private static function multiProbeEnabled(): bool
    {
        self::loadBatchOptions();
        return (bool) (self::$batchOptions['multi_probe'] ?? get_option('wpsmm_multi_probe_enabled', 1));
    }

    private static function minProbesRequired(): int
    {
        self::loadBatchOptions();
        return (int) (self::$batchOptions['min_probes'] ?? max(1, min(3, (int) get_option('wpsmm_min_probes_required', 2))));
    }

    private static function cachedSiteProbes(object $site): array
    {
        $siteId = (int) $site->id;
        if (isset(self::$probeCache[$siteId])) {
            return self::$probeCache[$siteId];
        }
        return self::buildSiteProbes($site);
    }

    private static function buildSiteProbes(object $site): array
    {
        $base = untrailingslashit((string) $site->url);
        $probes = [
            'homepage' => self::monitorEndpoint($site),
        ];
        if (!self::multiProbeEnabled()) {
            return $probes;
        }
        $probes['rest'] = $base . '/wp-json/';
        $probes['agent'] = $base . '/wp-json/wpma/v1/status';
        return $probes;
    }

    private static function fetchHttpResponses(array $sites, bool $manual): array
    {
        self::loadBatchOptions();
        $timeout = (int) (self::$batchOptions['timeout'] ?? max(5, (int) get_option('wpsmm_timeout', 15)));
        $eligible = [];
        foreach ($sites as $site) {
            if (!$manual && empty($site->monitor_enabled)) {
                continue;
            }
            if (!SecurityService::publicHttpUrl((string) $site->url)) {
                continue;
            }
            $eligible[] = $site;
        }
        if (!$eligible) {
            return [];
        }

        $requestMap = [];
        foreach ($eligible as $site) {
            $siteId = (int) $site->id;
            foreach (self::cachedSiteProbes($site) as $probe => $endpoint) {
                if (!SecurityService::publicHttpUrl($endpoint)) {
                    continue;
                }
                $key = $siteId . ':' . $probe;
                $requestMap[$key] = [
                    'endpoint' => $endpoint,
                    'started' => microtime(true),
                    'site_id' => $siteId,
                    'probe' => $probe,
                ];
            }
        }

        $responses = [];
        foreach (array_chunk($requestMap, self::batchConcurrency(), true) as $chunk) {
            $requests = [];
            foreach ($chunk as $key => $meta) {
                $requests[$key] = [
                    'endpoint' => $meta['endpoint'],
                    'started' => $meta['started'],
                ];
            }
            $parallel = self::requestMultiple($requests, $timeout);
            foreach ($chunk as $key => $meta) {
                $siteId = (int) $meta['site_id'];
                $probe = (string) $meta['probe'];
                if (!isset($responses[$siteId])) {
                    $responses[$siteId] = [];
                }
                $responses[$siteId][$probe] = $parallel[$key] ?? [
                    'http_code' => 0,
                    'body' => '',
                    'response_time' => 0,
                    'headers' => [],
                    'error' => 'Không thể tạo yêu cầu kiểm tra.',
                    'error_code' => 'request_skipped',
                ];
            }
        }
        return $responses;
    }

    private static function evaluateProbeResults(object $site, array $probes): array
    {
        $expected = (int) $site->expected_status;
        $probeMeta = self::cachedSiteProbes($site);
        $results = [];
        foreach ($probeMeta as $probe => $endpoint) {
            $http = $probes[$probe] ?? null;
            $results[$probe] = self::evaluateProbe($probe, $endpoint, $http, $expected);
        }

        $passed = array_values(array_filter($results, static fn(array $result): bool => !empty($result['ok'])));
        $passCount = count($passed);
        $totalProbes = count($results);
        $minRequired = self::multiProbeEnabled() ? min(self::minProbesRequired(), $totalProbes) : 1;
        $homepage = $results['homepage'] ?? ['ok' => false, 'http_code' => 0, 'response_time' => 0, 'body' => '', 'message' => 'Thiếu dữ liệu trang chủ'];
        $responseTime = self::maxProbeResponseTime($results);
        $probeLog = $results;
        foreach ($probeLog as &$probeEntry) {
            unset($probeEntry['body']);
        }
        unset($probeEntry);

        $technical = [
            'endpoint' => (string) ($homepage['endpoint'] ?? self::monitorEndpoint($site)),
            'multi_probe' => self::multiProbeEnabled(),
            'min_probes_required' => $minRequired,
            'probes_passed' => $passCount,
            'probes_total' => $totalProbes,
            'probes' => $probeLog,
            'redirects_allowed' => 5,
            'transport_error' => '',
            'response_headers' => (array) ($homepage['headers'] ?? []),
        ];

        $agentStatus = !empty($results['agent']['ok']) ? 'online' : (!isset($results['agent']) ? 'unknown' : 'offline');

        if ($passCount < $minRequired) {
            return [
                'status' => 'offline',
                'message' => sprintf('Chỉ %d/%d endpoint phản hồi (cần tối thiểu %d).', $passCount, $totalProbes, $minRequired),
                'http_code' => (int) ($homepage['http_code'] ?? 0),
                'response_time' => $responseTime,
                'endpoint' => (string) ($homepage['endpoint'] ?? self::monitorEndpoint($site)),
                'agent_status' => $agentStatus,
                'technical' => $technical,
            ];
        }

        if (empty($homepage['ok'])) {
            return [
                'status' => 'partial',
                'message' => sprintf('Trang chủ không phản hồi đúng nhưng %d/%d endpoint khác vẫn hoạt động.', $passCount, $totalProbes),
                'http_code' => (int) ($homepage['http_code'] ?? 0),
                'response_time' => $responseTime,
                'endpoint' => (string) ($homepage['endpoint'] ?? self::monitorEndpoint($site)),
                'agent_status' => $agentStatus,
                'technical' => $technical,
            ];
        }

        $httpCode = (int) ($homepage['http_code'] ?? 0);
        $status = self::statusFromCode($httpCode, $expected);
        $message = $status === 'online' ? 'OK' : 'HTTP ' . $httpCode;
        $body = (string) ($homepage['body'] ?? '');

        if ($status === 'online' && self::$contentChecksNeeded) {
            $titleStatus = self::checkTitle($body, (string) $site->expected_title);
            if ($titleStatus) {
                $status = 'title_changed';
                $message = $titleStatus;
            } else {
                $keywordStatus = self::checkSuspiciousKeywords($body);
                if ($keywordStatus) {
                    $status = 'suspicious';
                    $message = $keywordStatus;
                }
            }
        }

        if (self::multiProbeEnabled() && $passCount < $totalProbes && in_array($status, ['online', 'redirect'], true)) {
            $message .= sprintf(' (%d/%d endpoint OK)', $passCount, $totalProbes);
        }

        return [
            'status' => $status,
            'message' => $message,
            'http_code' => $httpCode,
            'response_time' => $responseTime,
            'endpoint' => (string) ($homepage['endpoint'] ?? self::monitorEndpoint($site)),
            'agent_status' => $agentStatus,
            'technical' => $technical,
        ];
    }

    private static function evaluateProbe(string $probe, string $endpoint, ?array $http, int $expected): array
    {
        $base = [
            'probe' => $probe,
            'endpoint' => $endpoint,
            'ok' => false,
            'http_code' => 0,
            'response_time' => (float) ($http['response_time'] ?? 0),
            'message' => '',
            'body' => '',
            'headers' => [],
        ];

        if ($http === null) {
            $base['message'] = 'Không gửi được yêu cầu kiểm tra.';
            return $base;
        }
        if (!empty($http['error'])) {
            $base['message'] = (string) $http['error'];
            return $base;
        }

        $code = (int) ($http['http_code'] ?? 0);
        $body = (string) ($http['body'] ?? '');
        $base['http_code'] = $code;
        $base['body'] = $probe === self::HOMEPAGE_PROBE ? $body : '';
        $base['headers'] = (array) ($http['headers'] ?? []);

        if ($probe === 'agent') {
            if ($code !== 200) {
                $base['message'] = 'HTTP ' . $code;
                return $base;
            }
            $payload = json_decode($body, true);
            if (!is_array($payload) || empty($payload['success']) || ($payload['agent'] ?? '') !== 'wp-site-monitor-agent') {
                $base['message'] = 'Phản hồi health Agent không hợp lệ.';
                return $base;
            }
            $health = is_array($payload['health'] ?? null) ? $payload['health'] : [];
            if ($health) {
                if (empty($health['ok']) && (empty($health['wp_ok']) || empty($health['db_ok']))) {
                    $base['message'] = 'Health Agent báo WordPress hoặc database lỗi.';
                    return $base;
                }
            }
            $base['ok'] = true;
            $base['message'] = 'OK';
            return $base;
        }

        if ($probe === 'rest') {
            if ($code !== 200) {
                $base['message'] = 'HTTP ' . $code;
                return $base;
            }
            $payload = json_decode($body, true);
            if (!is_array($payload) || (empty($payload['namespaces']) && empty($payload['name']))) {
                $base['message'] = 'REST API WordPress không hợp lệ.';
                return $base;
            }
            $base['ok'] = true;
            $base['message'] = 'OK';
            return $base;
        }

        $status = self::statusFromCode($code, $expected);
        if (in_array($status, ['online', 'redirect'], true)) {
            $base['ok'] = true;
            $base['message'] = $status === 'redirect' ? 'Chuyển hướng ' . $code : 'OK';
            return $base;
        }

        $base['message'] = 'HTTP ' . $code;
        return $base;
    }

    private static function maxProbeResponseTime(array $results): float
    {
        $max = 0.0;
        foreach ($results as $result) {
            $max = max($max, (float) ($result['response_time'] ?? 0));
        }
        return round($max, 3);
    }

    private static function requestMultiple(array $requests, int $timeout): array
    {
        $class = self::requestsClass();
        if ($class && method_exists($class, 'request_multiple')) {
            return self::requestMultipleViaLibrary($class, $requests, $timeout);
        }
        return self::requestMultipleSequential($requests, $timeout);
    }

    private static function requestMultipleViaLibrary(string $class, array $requests, int $timeout): array
    {
        $payload = [];
        foreach ($requests as $siteId => $request) {
            $payload[$siteId] = [
                'url' => $request['endpoint'],
                'type' => 'GET',
                'headers' => ['User-Agent' => 'WPSMM/' . WPSMM_VERSION],
                'timeout' => $timeout,
                'redirects' => 5,
            ];
        }
        $responses = $class::request_multiple($payload, ['timeout' => $timeout]);
        $normalized = [];
        foreach ($requests as $siteId => $request) {
            $response = $responses[$siteId] ?? null;
            $probe = strpos((string) $siteId, ':') !== false ? substr((string) $siteId, strpos((string) $siteId, ':') + 1) : self::HOMEPAGE_PROBE;
            if ($response instanceof \WpOrg\Requests\Response) {
                $normalized[$siteId] = [
                    'http_code' => (int) $response->status_code,
                    'body' => self::truncateResponseBody((string) $response->body, $probe),
                    'response_time' => round(microtime(true) - (float) $request['started'], 3),
                    'headers' => self::safeResponseHeaders((array) $response->headers->getAll()),
                    'error' => null,
                    'error_code' => '',
                ];
                continue;
            }
            if ($response instanceof \Requests_Response) {
                $normalized[$siteId] = [
                    'http_code' => (int) $response->status_code,
                    'body' => self::truncateResponseBody((string) $response->body, $probe),
                    'response_time' => round(microtime(true) - (float) $request['started'], 3),
                    'headers' => self::safeResponseHeaders(is_object($response->headers) && method_exists($response->headers, 'getAll') ? (array) $response->headers->getAll() : []),
                    'error' => null,
                    'error_code' => '',
                ];
                continue;
            }
            $message = is_object($response) && method_exists($response, 'getMessage') ? $response->getMessage() : 'Không thể kết nối tới website.';
            $normalized[$siteId] = [
                'http_code' => 0,
                'body' => '',
                'response_time' => round(microtime(true) - (float) $request['started'], 3),
                'headers' => [],
                'error' => $message,
                'error_code' => is_object($response) && method_exists($response, 'getType') ? (string) $response->getType() : 'transport_error',
            ];
        }
        return $normalized;
    }

    private static function requestMultipleSequential(array $requests, int $timeout): array
    {
        $normalized = [];
        $args = [
            'timeout' => $timeout,
            'redirection' => 5,
            'reject_unsafe_urls' => true,
            'sslverify' => true,
            'headers' => ['User-Agent' => 'WPSMM/' . WPSMM_VERSION],
        ];
        $remote = (defined('WPSMM_ALLOW_PRIVATE_HOSTS') && WPSMM_ALLOW_PRIVATE_HOSTS) ? 'wp_remote_get' : 'wp_safe_remote_get';
        foreach ($requests as $siteId => $request) {
            $started = microtime(true);
            $res = $remote($request['endpoint'], $args);
            if (is_wp_error($res)) {
                $normalized[$siteId] = [
                    'http_code' => 0,
                    'body' => '',
                    'response_time' => round(microtime(true) - $started, 3),
                    'headers' => [],
                    'error' => $res->get_error_message(),
                    'error_code' => $res->get_error_code(),
                ];
                continue;
            }
            $headers = wp_remote_retrieve_headers($res);
            $probe = strpos((string) $siteId, ':') !== false ? substr((string) $siteId, strpos((string) $siteId, ':') + 1) : self::HOMEPAGE_PROBE;
            $normalized[$siteId] = [
                'http_code' => (int) wp_remote_retrieve_response_code($res),
                'body' => self::truncateResponseBody((string) wp_remote_retrieve_body($res), $probe),
                'response_time' => round(microtime(true) - $started, 3),
                'headers' => self::safeResponseHeaders(is_object($headers) && method_exists($headers, 'getAll') ? $headers->getAll() : (array) $headers),
                'error' => null,
                'error_code' => '',
            ];
        }
        return $normalized;
    }

    private static function requestsClass(): ?string
    {
        if (class_exists('WpOrg\\Requests\\Requests')) {
            return 'WpOrg\\Requests\\Requests';
        }
        if (class_exists('Requests')) {
            return 'Requests';
        }
        return null;
    }

    private static function truncateResponseBody(string $body, string $probe): string
    {
        $limit = self::probeBodyLimit($probe);
        if (strlen($body) <= $limit) {
            return $body;
        }
        return substr($body, 0, $limit);
    }

    private static function shouldRefreshSsl(object $site): bool
    {
        if (strtolower((string) parse_url((string) $site->url, PHP_URL_SCHEME)) !== 'https') {
            return false;
        }
        if ($site->ssl_days_left === null && empty($site->ssl_expiry)) {
            return true;
        }
        $last = strtotime((string) ($site->last_checked ?? ''));
        self::loadBatchOptions();
        $interval = (int) (self::$batchOptions['ssl_refresh_interval'] ?? max(HOUR_IN_SECONDS, (int) get_option('wpsmm_ssl_refresh_interval', self::DEFAULT_SSL_REFRESH)));
        return !$last || (time() - $last) >= $interval;
    }

    private static function cachedSsl(object $site): array
    {
        $days = $site->ssl_days_left;
        self::loadBatchOptions();
        $warning = (int) (self::$batchOptions['ssl_warning_days'] ?? max(1, (int) get_option('wpsmm_ssl_warning_days', 14)));
        if ($days === null || $days === '') {
            return ['status' => 'ok', 'expiry' => $site->ssl_expiry, 'days_left' => null, 'message' => ''];
        }
        $days = (int) $days;
        if ($days < 0) {
            return ['status' => 'ssl_error', 'expiry' => $site->ssl_expiry, 'days_left' => $days, 'message' => 'SSL đã hết hạn'];
        }
        if ($days <= $warning) {
            return ['status' => 'ssl_expiring', 'expiry' => $site->ssl_expiry, 'days_left' => $days, 'message' => 'SSL sắp hết hạn còn ' . $days . ' ngày'];
        }
        return ['status' => 'ok', 'expiry' => $site->ssl_expiry, 'days_left' => $days, 'message' => ''];
    }

    private static function shouldRefreshAgent(object $site): bool
    {
        $last = strtotime((string) ($site->agent_checked_at ?? ''));
        $interval = max(Plugin::checkInterval(), (int) get_option('wpsmm_agent_refresh_interval', Plugin::checkInterval() * 2));
        return !$last || (time() - $last) >= $interval;
    }

    private static function canUsePrefetchedUptime(object $site, string $newStatus): bool
    {
        $oldHealthy = in_array((string) $site->status, ['online', 'redirect'], true);
        $newHealthy = in_array($newStatus, ['online', 'redirect'], true);
        return $oldHealthy === $newHealthy;
    }

    private static function shouldRefreshUptime(object $site, string $newStatus): bool
    {
        $last = strtotime((string) ($site->last_checked ?? ''));
        self::loadBatchOptions();
        $interval = (int) (self::$batchOptions['uptime_refresh_interval'] ?? max(5 * MINUTE_IN_SECONDS, (int) get_option('wpsmm_uptime_refresh_interval', self::DEFAULT_UPTIME_REFRESH)));
        if (!$last || (time() - $last) >= $interval) {
            return true;
        }
        $oldHealthy = in_array((string) $site->status, ['online', 'redirect'], true);
        $newHealthy = in_array($newStatus, ['online', 'redirect'], true);
        return $oldHealthy !== $newHealthy;
    }

    private static function monitorEndpoint(object $site): string
    {
        $path = trim((string) ($site->health_path ?? ''));
        if ($path === '') {
            return (string) $site->url;
        }
        return untrailingslashit((string) $site->url) . '/' . ltrim($path, '/');
    }

    private static function safeResponseHeaders(array $headers): array
    {
        $safe = [];
        $allowed = ['content-type', 'content-length', 'server', 'location', 'cache-control', 'x-powered-by'];
        foreach ($headers as $name => $value) {
            $name = strtolower((string) $name);
            if (in_array($name, $allowed, true)) {
                $safe[$name] = $value;
            }
        }
        return $safe;
    }

    private static function statusFromCode(int $code, int $expected): string
    {
        if ($code === $expected) {
            return 'online';
        }
        if ($code >= 300 && $code < 400) {
            return 'redirect';
        }
        if ($code === 404) {
            return 'not_found';
        }
        if ($code >= 400 && $code < 500) {
            return 'client_error';
        }
        if ($code >= 500) {
            return 'server_error';
        }
        return 'offline';
    }

    private static function checkTitle(string $body, string $expected): string
    {
        if ($expected === '') {
            return '';
        }
        preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m);
        $current = trim(wp_strip_all_tags($m[1] ?? ''));
        return ($current && stripos($current, $expected) === false) ? 'Tiêu đề thay đổi: ' . $current : '';
    }

    private static function checkSuspiciousKeywords(string $body): string
    {
        self::loadBatchOptions();
        $keywords = (array) (self::$batchOptions['suspicious_keywords'] ?? []);
        if (!$keywords) {
            return '';
        }
        $plain = strtolower(wp_strip_all_tags(substr($body, 0, self::homepageBodyLimit())));
        foreach ($keywords as $kw) {
            if ($kw !== '' && strpos($plain, strtolower($kw)) !== false) {
                return 'Phát hiện keyword nghi vấn: ' . $kw;
            }
        }
        return '';
    }

    private static function sslInfo(string $url): array
    {
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            return ['status' => 'ok', 'expiry' => null, 'days_left' => null, 'message' => ''];
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return ['status' => 'ok', 'expiry' => null, 'days_left' => null, 'message' => ''];
        }
        self::loadBatchOptions();
        $warning = (int) (self::$batchOptions['ssl_warning_days'] ?? max(1, (int) get_option('wpsmm_ssl_warning_days', 14)));
        if (!SecurityService::isPublicHost($host)) {
            return ['status' => 'ssl_error', 'expiry' => null, 'days_left' => null, 'message' => 'Tên miền SSL không công khai'];
        }
        $context = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true]]);
        $client = @stream_socket_client('ssl://' . $host . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
        if (!$client) {
            return ['status' => 'ssl_error', 'expiry' => null, 'days_left' => null, 'message' => 'Không kiểm tra được SSL: ' . $errstr];
        }
        $params = stream_context_get_params($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        if (!$cert) {
            return ['status' => 'ssl_error', 'expiry' => null, 'days_left' => null, 'message' => 'Không đọc được chứng chỉ SSL'];
        }
        $parsed = openssl_x509_parse($cert);
        $validTo = (int) ($parsed['validTo_time_t'] ?? 0);
        $days = $validTo ? (int) floor(($validTo - time()) / DAY_IN_SECONDS) : null;
        $expiry = $validTo ? gmdate('Y-m-d H:i:s', $validTo) : null;
        if ($days !== null && $days < 0) {
            return ['status' => 'ssl_error', 'expiry' => $expiry, 'days_left' => $days, 'message' => 'SSL đã hết hạn'];
        }
        if ($days !== null && $days <= $warning) {
            return ['status' => 'ssl_expiring', 'expiry' => $expiry, 'days_left' => $days, 'message' => 'SSL sắp hết hạn còn ' . $days . ' ngày'];
        }
        return ['status' => 'ok', 'expiry' => $expiry, 'days_left' => $days, 'message' => ''];
    }

    private static function healthScore(string $status, float $response, $sslDays): int
    {
        $score = 100;
        if (!in_array($status, ['online', 'redirect'], true)) {
            $score -= 45;
        }
        if ($status === 'partial') {
            $score -= 20;
        }
        if (in_array($status, ['suspicious', 'ssl_error'], true)) {
            $score -= 30;
        }
        if ($response > 3) {
            $score -= 20;
        } elseif ($response > 1.5) {
            $score -= 10;
        }
        if ($sslDays !== null && $sslDays <= 14) {
            $score -= 15;
        }
        return max(0, min(100, $score));
    }
}