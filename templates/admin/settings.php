<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/partials.php';
?>
<div class="wrap wpsmm-wrap" data-wpsmm-page="settings">
    <section class="wpsmm-page-title wpsmm-section-header">
        <div>
            <span class="wpsmm-eyebrow-light">Configuration</span>
            <h1>SETTINGS</h1>
            <p>Quản lý cài đặt của plugin.</p>
        </div>
    </section>

    <section class="wpsmm-saas-card wpsmm-setting-quickbar">
        <div>
            <h2>Giao Diện</h2>
            <p>Cài đặt dark mode / light mode</p>
        </div>
        <label class="wpsmm-switch-line">
            <span>Dark mode</span>
            <button type="button"
                class="wpsmm-toggle-switch <?php echo get_option('wpsmm_dark_mode') ? 'is-on' : ''; ?>"
                data-setting="dark_mode"
                aria-pressed="<?php echo get_option('wpsmm_dark_mode') ? 'true' : 'false'; ?>"><i></i></button>
        </label>
    </section>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wpsmm-settings-form">
        <?php wp_nonce_field('wpsmm_save_settings'); ?>
        <input type="hidden" name="action" value="wpsmm_save_settings">
        <input type="hidden" name="wpsmm_dark_mode" id="wpsmm-dark-mode-field"
            value="<?php echo esc_attr(get_option('wpsmm_dark_mode') ? 1 : 0); ?>">
        <div class="wpsmm-settings-grid">
            <section class="wpsmm-saas-card wpsmm-settings-section">
                <h2>Realtime</h2>
                <p class="wpsmm-section-desc">Dashboard dùng WebSocket nếu có, fallback REST polling.</p>
                <label>WebSocket URL<input name="wpsmm_websocket_url"
                        value="<?php echo esc_attr(get_option('wpsmm_websocket_url')); ?>"
                        placeholder="wss://monitor.example.com/ws"></label>
            </section>

            <section class="wpsmm-saas-card wpsmm-settings-section">
                <h2>Monitor</h2>
                <p class="wpsmm-section-desc">Thiết lập timeout, log retention và ngưỡng mức cảnh báo.</p>
                <div class="wpsmm-row">
                    <label>Thời gian giữ log (ngày)<input type="number" name="wpsmm_log_retention_days"
                            value="<?php echo esc_attr(get_option('wpsmm_log_retention_days', 7)); ?>"></label>
                    <label>Thời gian timeout (giây)<input type="number" name="wpsmm_timeout"
                            value="<?php echo esc_attr(get_option('wpsmm_timeout', 15)); ?>"></label>
                </div>
                <div class="wpsmm-row">
                    <label>Ngưỡng lỗi<input type="number" name="wpsmm_error_threshold"
                            value="<?php echo esc_attr(get_option('wpsmm_error_threshold', 2)); ?>"></label>
                    <label>Ngày cảnh báo SSL<input type="number" name="wpsmm_ssl_warning_days"
                            value="<?php echo esc_attr(get_option('wpsmm_ssl_warning_days', 14)); ?>"></label>
                </div>
                <label>Từ khóa đáng ngờ<input name="wpsmm_suspicious_keywords"
                        value="<?php echo esc_attr(get_option('wpsmm_suspicious_keywords', 'casino,betting,viagra,porn')); ?>"></label>
            </section>

            <section class="wpsmm-saas-card wpsmm-settings-section">
                <h2>Backup</h2>
                <p class="wpsmm-section-desc">Lịch backup nên và số bản local được giữ lại.</p>
                <div class="wpsmm-row">
                    <label>Lịch backup<select name="wpsmm_backup_schedule">
                            <option value="disabled" <?php selected(get_option('wpsmm_backup_schedule'), 'disabled'); ?>>Tắt</option>
                            <option value="daily" <?php selected(get_option('wpsmm_backup_schedule', 'daily'), 'daily'); ?>>Hàng ngày</option>
                            <option value="weekly" <?php selected(get_option('wpsmm_backup_schedule'), 'weekly'); ?>>Hàng tuần</option>
                        </select></label>
                    <label>Giờ backup<input type="time" name="wpsmm_backup_hour"
                            value="<?php echo esc_attr(get_option('wpsmm_backup_hour', '02:00')); ?>"></label>
                </div>
                <label>Giữ số bản local<input type="number" name="wpsmm_backup_retention"
                        value="<?php echo esc_attr(get_option('wpsmm_backup_retention', 3)); ?>"></label>
            </section>

            <section class="wpsmm-saas-card wpsmm-settings-section">
                <h2>Cảnh báo</h2>
                <p class="wpsmm-section-desc">ửi thông báo khi website lỗi, SSL sắp hết hạn, backup thất bại hoặc có dấu hiệu bất thường.</p>
                <label>Email nhận cảnh báo<input name="wpsmm_alert_email"
                        value="<?php echo esc_attr(get_option('wpsmm_alert_email')); ?>"></label>
                <label class="wpsmm-check"><input type="checkbox" name="wpsmm_enable_email_alert" value="1" <?php checked(get_option('wpsmm_enable_email_alert')); ?>> Bật email alert</label>
                <label>Telegram Bot Token<?php wpsmm_secret_input('wpsmm_telegram_bot_token', get_option('wpsmm_telegram_bot_token')); ?></label>
                <label>Telegram Chat ID<input name="wpsmm_telegram_chat_id"
                        value="<?php echo esc_attr(get_option('wpsmm_telegram_chat_id')); ?>"></label>
                <label class="wpsmm-check"><input type="checkbox" name="wpsmm_enable_zalo_alert" value="1" <?php checked(get_option('wpsmm_enable_zalo_alert')); ?>> Bật Zalo webhook</label>
                <label>Zalo Webhook URL<?php wpsmm_secret_input('wpsmm_zalo_webhook_url', get_option('wpsmm_zalo_webhook_url')); ?></label>
            </section>
        </div>
        <div class="wpsmm-savebar"><button class="button button-primary button-hero">Lưu cài đặt</button></div>
    </form>
</div>
