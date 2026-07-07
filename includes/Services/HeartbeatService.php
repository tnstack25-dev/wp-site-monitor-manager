<?php
namespace WPSMM\Services;

use WPSMM\Repositories\SiteRepository;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

final class HeartbeatService
{
    public static function receive(WP_REST_Request $request)
    {
        $body = json_decode($request->get_body(), true);
        if (!is_array($body)) {
            return new \WP_Error('wpsmm_heartbeat_invalid', 'Payload heartbeat không hợp lệ.', ['status' => 400]);
        }

        $siteUrl = untrailingslashit((string) ($body['site_url'] ?? ''));
        $site = $siteUrl ? SiteRepository::findByUrl($siteUrl) : null;
        if (!$site) {
            $siteId = absint($body['site_id'] ?? 0);
            $site = $siteId ? SiteRepository::find($siteId) : null;
        }
        if (!$site || empty($site->agent_secret)) {
            return new \WP_Error('wpsmm_heartbeat_unknown_site', 'Không tìm thấy website hoặc chưa cấu hình khóa Agent.', ['status' => 404]);
        }

        $verified = SecurityService::verifyAgentSignature($request, SecurityService::decryptSecret((string) $site->agent_secret));
        if (is_wp_error($verified)) {
            return $verified;
        }

        $health = is_array($body['health'] ?? null) ? $body['health'] : [];
        $agentStatus = self::agentStatusFromHealth($health);
        $now = current_time('mysql');

        global $wpdb;
        $wpdb->update(DatabaseService::table('sites'), [
            'agent_heartbeat_at' => $now,
            'agent_health' => wp_json_encode($health),
            'agent_status' => $agentStatus,
            'agent_checked_at' => $now,
            'updated_at' => $now,
        ], ['id' => (int) $site->id]);

        return [
            'success' => true,
            'site_id' => (int) $site->id,
            'received_at' => $now,
            'agent_status' => $agentStatus,
        ];
    }

    public static function agentStatusFromHealth(array $health): string
    {
        if (!empty($health['ok'])) {
            return 'online';
        }
        if (isset($health['wp_ok'], $health['db_ok']) && ($health['wp_ok'] || $health['db_ok'])) {
            return 'partial';
        }
        return 'offline';
    }

    public static function decodeHealth(?string $json): array
    {
        $data = json_decode((string) $json, true);
        return is_array($data) ? $data : [];
    }
}