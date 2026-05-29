<?php
namespace WPSMM\Services;

use WPSMM\Repositories\ServerRepository;

if (!defined('ABSPATH')) {
    exit;
}

final class ServerService
{
    public static function test(int $serverId): array
    {
        $server = ServerRepository::find($serverId);
        if (!$server) {
            return ['ok' => false, 'message' => 'Server không tồn tại'];
        }
        $host = $server->host;
        if (!SecurityService::isPublicHost((string) $host)) {
            return ['ok' => false, 'message' => 'Host không hợp lệ hoặc không phải public'];
        }
        $port = (int) $server->port ?: 22;
        $start = microtime(true);
        $fp = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$fp) {
            return ['ok' => false, 'message' => 'Không kết nối được: ' . $errstr];
        }
        fclose($fp);
        return ['ok' => true, 'message' => 'Kết nối TCP OK', 'response_time' => round(microtime(true) - $start, 3)];
    }
    public static function sshAvailable(): bool
    {
        return extension_loaded('ssh2');
    }
}
