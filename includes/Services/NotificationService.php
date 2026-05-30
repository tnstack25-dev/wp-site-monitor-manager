<?php
namespace WPSMM\Services;

if (!defined('ABSPATH')) {
    exit;
}

final class NotificationService
{
    public static function alert(string $title, string $message): void
    {
        $text = '[' . $title . '] ' . $message;
        if (get_option('wpsmm_enable_email_alert') && ($email = get_option('wpsmm_alert_email'))) {
            wp_mail($email, $title, $message);
        }
        $token = SecurityService::decryptSecret((string) get_option('wpsmm_telegram_bot_token', ''));
        $chat = (string) get_option('wpsmm_telegram_chat_id', '');
        if (get_option('wpsmm_enable_telegram_alert', ($token && $chat) ? 1 : 0) && $token && $chat) {
            wp_remote_post('https://api.telegram.org/bot' . rawurlencode($token) . '/sendMessage', [
                'timeout' => 10,
                'body' => ['chat_id' => $chat, 'text' => $text]
            ]);
        }
        if (get_option('wpsmm_enable_zalo_alert') && ($url = SecurityService::decryptSecret((string) get_option('wpsmm_zalo_webhook_url')))) {
            wp_safe_remote_post($url, ['timeout' => 10, 'redirection' => 0, 'reject_unsafe_urls' => true, 'headers' => ['Content-Type' => 'application/json'], 'body' => wp_json_encode(['text' => $text])]);
        }
    }
}
