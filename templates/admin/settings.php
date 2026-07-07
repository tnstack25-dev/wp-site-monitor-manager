<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/partials.php';

$dark_mode = (bool) get_option('wpsmm_dark_mode');
$telegram_configured = (bool) get_option('wpsmm_telegram_bot_token') && (bool) get_option('wpsmm_telegram_chat_id');
$telegram_enabled = (bool) get_option('wpsmm_enable_telegram_alert', $telegram_configured ? 1 : 0);
?>
<div class="wrap wpsmm-wrap wpsmm-settings-page" data-wpsmm-page="settings">
    <header class="wpsmm-settings-heading">
        <h1>Cài đặt</h1>
        <p>Quản lý các thiết lập và tùy chọn của hệ thống.</p>
    </header>

    <nav class="wpsmm-settings-tabs" aria-label="Điều hướng cài đặt">
        <button type="button" class="wpsmm-settings-tab is-active" data-settings-target="wpsmm-settings-general">Chung</button>
        <button type="button" class="wpsmm-settings-tab" data-settings-target="wpsmm-settings-notifications">Thông báo</button>
        <button type="button" class="wpsmm-settings-tab" data-settings-target="wpsmm-settings-monitor">Giám sát</button>
        <button type="button" class="wpsmm-settings-tab" data-settings-target="wpsmm-settings-integration">Tích hợp</button>
    </nav>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wpsmm-settings-form">
        <?php wp_nonce_field('wpsmm_save_settings'); ?>
        <input type="hidden" name="action" value="wpsmm_save_settings">
        <input type="hidden" name="wpsmm_dark_mode" id="wpsmm-dark-mode-field" value="<?php echo esc_attr($dark_mode ? 1 : 0); ?>">

        <div class="wpsmm-settings-layout">
            <div class="wpsmm-settings-column">
                <section class="wpsmm-settings-card" id="wpsmm-settings-general">
                    <div class="wpsmm-settings-card-heading">
                        <h2>Giao diện</h2>
                        <p>Chọn giao diện hiển thị của hệ thống.</p>
                    </div>
                    <div class="wpsmm-theme-grid">
                        <button type="button" class="wpsmm-theme-choice <?php echo !$dark_mode ? 'is-active' : ''; ?>" data-theme="light">
                            <span class="wpsmm-theme-radio"></span><span class="dashicons dashicons-lightbulb"></span><strong>Giao diện sáng</strong>
                        </button>
                        <button type="button" class="wpsmm-theme-choice <?php echo $dark_mode ? 'is-active' : ''; ?>" data-theme="dark">
                            <span class="wpsmm-theme-radio"></span><span class="dashicons dashicons-star-filled"></span><strong>Giao diện tối</strong>
                        </button>
                    </div>
                </section>

                <section class="wpsmm-settings-card" id="wpsmm-settings-monitor">
                    <div class="wpsmm-settings-card-heading"><h2>Cấu hình giám sát</h2><p>Điều chỉnh thời gian lưu trữ và ngưỡng cảnh báo.</p></div>
                    <label class="wpsmm-setting-field"><span>Tần suất kiểm tra</span><small>Khoảng thời gian giữa hai lần tự động kiểm tra toàn bộ website.</small><span class="wpsmm-input-unit"><input type="number" min="1" max="1440" name="wpsmm_check_interval_minutes" value="<?php echo esc_attr(\WPSMM\Plugin::checkIntervalMinutes()); ?>"><em>phút</em></span></label>
                    <label class="wpsmm-setting-field"><span>Số website mỗi nhóm</span><small>Giới hạn số website được gọi trong mỗi lần chạy định kỳ. Có thể đặt tới 200 website khi máy chủ đủ tài nguyên.</small><span class="wpsmm-input-unit"><input type="number" min="1" max="200" name="wpsmm_batch_size" value="<?php echo esc_attr(get_option('wpsmm_batch_size', 10)); ?>"><em>website</em></span></label>
                    <div class="wpsmm-settings-inline">
                        <label class="wpsmm-setting-field"><span>Thời gian tối đa mỗi nhóm</span><small>Dừng kiểm tra khi vượt ngưỡng để tránh quá tải PHP.</small><span class="wpsmm-input-unit"><input type="number" min="30" max="600" name="wpsmm_batch_time_budget" value="<?php echo esc_attr(get_option('wpsmm_batch_time_budget', 240)); ?>"><em>giây</em></span></label>
                        <label class="wpsmm-setting-field"><span>Số request song song</span><small>Số HTTP request chạy đồng thời mỗi vòng (homepage + REST + Agent). Tăng lên 40–60 khi máy chủ đủ RAM/băng thông.</small><span class="wpsmm-input-unit"><input type="number" min="10" max="80" name="wpsmm_batch_concurrency" value="<?php echo esc_attr(get_option('wpsmm_batch_concurrency', 30)); ?>"><em>request</em></span></label>
                    </div>
                    <div class="wpsmm-settings-inline">
                        <label class="wpsmm-setting-field"><span>Giới hạn kiểm tra SSL mỗi nhóm</span><small>Tránh nghẽn khi kiểm tra 200 site HTTPS. SSL được cache theo hostname.</small><span class="wpsmm-input-unit"><input type="number" min="1" max="100" name="wpsmm_batch_max_ssl_checks" value="<?php echo esc_attr(get_option('wpsmm_batch_max_ssl_checks', 25)); ?>"><em>lần</em></span></label>
                        <label class="wpsmm-setting-field"><span>Giới hạn HTML trang chủ</span><small>Chỉ tải tối đa dung lượng HTML để phân tích tiêu đề/từ khóa.</small><span class="wpsmm-input-unit"><input type="number" min="16384" max="524288" step="1024" name="wpsmm_homepage_body_limit" value="<?php echo esc_attr(get_option('wpsmm_homepage_body_limit', 131072)); ?>"><em>bytes</em></span></label>
                    </div>
                    <label class="wpsmm-admin-option">
                        <input type="checkbox" name="wpsmm_multi_probe_enabled" value="1" <?php checked(get_option('wpsmm_multi_probe_enabled', 1)); ?>>
                        <span><strong>Kiểm tra song song nhiều endpoint</strong><small>Đồng thời kiểm tra trang chủ, <code>/wp-json/</code> và <code>/wp-json/wpma/v1/status</code>. Website được coi là online khi đạt ngưỡng endpoint phản hồi.</small></span>
                    </label>
                    <label class="wpsmm-setting-field"><span>Số endpoint tối thiểu</span><small>Số endpoint phải phản hồi thành công để coi website online. Mặc định 2 trong 3.</small><span class="wpsmm-input-unit"><input type="number" min="1" max="3" name="wpsmm_min_probes_required" value="<?php echo esc_attr(get_option('wpsmm_min_probes_required', 2)); ?>"><em>endpoint</em></span></label>
                    <label class="wpsmm-setting-field"><span>Số ngày lưu nhật ký</span><small>Số ngày hệ thống lưu trữ nhật ký giám sát.</small><span class="wpsmm-input-unit"><input type="number" min="1" name="wpsmm_log_retention_days" value="<?php echo esc_attr(get_option('wpsmm_log_retention_days', 7)); ?>"><em>ngày</em></span></label>
                    <label class="wpsmm-setting-field"><span>Thời gian chờ</span><small>Thời gian tối đa chờ phản hồi của website.</small><span class="wpsmm-input-unit"><input type="number" min="5" name="wpsmm_timeout" value="<?php echo esc_attr(get_option('wpsmm_timeout', 15)); ?>"><em>giây</em></span></label>
                    <div class="wpsmm-settings-inline">
                        <label class="wpsmm-setting-field"><span>Ngưỡng lỗi liên tiếp</span><small>Số lần lỗi trước khi cảnh báo.</small><span class="wpsmm-input-unit"><input type="number" min="1" name="wpsmm_error_threshold" value="<?php echo esc_attr(get_option('wpsmm_error_threshold', 2)); ?>"><em>lần</em></span></label>
                        <label class="wpsmm-setting-field"><span>Ngày cảnh báo SSL</span><small>Cảnh báo trước khi SSL hết hạn.</small><span class="wpsmm-input-unit"><input type="number" min="1" name="wpsmm_ssl_warning_days" value="<?php echo esc_attr(get_option('wpsmm_ssl_warning_days', 14)); ?>"><em>ngày</em></span></label>
                    </div>
                    <label class="wpsmm-setting-field"><span>Từ khóa đáng ngờ</span><small>Nhập mỗi từ khóa trên một dòng hoặc phân cách bằng dấu phẩy.</small><textarea name="wpsmm_suspicious_keywords" rows="6"><?php echo esc_textarea(get_option('wpsmm_suspicious_keywords', "phishing\nmalware\nhack\ndeface\nsuspicious\nspam")); ?></textarea></label>
                    <label class="wpsmm-admin-option">
                        <input type="checkbox" name="wpsmm_dns_monitor_enabled" value="1" <?php checked(get_option('wpsmm_dns_monitor_enabled', 1)); ?>>
                        <span><strong>Giám sát DNS hàng ngày</strong><small>Theo dõi bản ghi A/AAAA và cảnh báo khi DNS thay đổi.</small></span>
                    </label>
                    <label class="wpsmm-admin-option">
                        <input type="checkbox" name="wpsmm_dns_alert_enabled" value="1" <?php checked(get_option('wpsmm_dns_alert_enabled', 1)); ?>>
                        <span><strong>Cảnh báo thay đổi DNS</strong><small>Gửi thông báo P2 khi phát hiện thay đổi bản ghi DNS.</small></span>
                    </label>
                </section>
            </div>

            <div class="wpsmm-settings-column">
                <section class="wpsmm-settings-card" id="wpsmm-settings-notifications">
                    <div class="wpsmm-settings-card-heading"><h2>Thông báo</h2><p>Cấu hình các kênh nhận thông báo khi website có lỗi hoặc sự cố.</p></div>
                    <div class="wpsmm-channel">
                        <div class="wpsmm-channel-heading"><span class="wpsmm-channel-icon"><span class="dashicons dashicons-email-alt"></span></span><div><strong>Email</strong><small>Nhận thông báo qua email.</small></div><label class="wpsmm-channel-switch"><input type="checkbox" name="wpsmm_enable_email_alert" value="1" <?php checked(get_option('wpsmm_enable_email_alert')); ?>><i></i></label></div>
                        <label class="wpsmm-setting-field"><span>Email nhận thông báo</span><input type="email" name="wpsmm_alert_email" value="<?php echo esc_attr(get_option('wpsmm_alert_email')); ?>" placeholder="admin@example.com"></label>
                    </div>
                    <div class="wpsmm-channel">
                        <div class="wpsmm-channel-heading"><span class="wpsmm-channel-icon is-zalo">Zalo</span><div><strong>Zalo</strong><small>Nhận thông báo qua webhook Zalo.</small></div><label class="wpsmm-channel-switch"><input type="checkbox" name="wpsmm_enable_zalo_alert" value="1" <?php checked(get_option('wpsmm_enable_zalo_alert')); ?>><i></i></label></div>
                        <label class="wpsmm-setting-field"><span>URL webhook</span><?php wpsmm_secret_input('wpsmm_zalo_webhook_url', get_option('wpsmm_zalo_webhook_url'), 'https://example.zalo.me/webhook/...'); ?></label>
                    </div>
                    <label class="wpsmm-admin-option">
                        <input type="checkbox" name="wpsmm_enable_recovery_alert" value="1" <?php checked(get_option('wpsmm_enable_recovery_alert', 1)); ?>>
                        <span><strong>Thông báo hồi phục</strong><small>Gửi cảnh báo khi website chuyển từ lỗi sang hoạt động bình thường.</small></span>
                    </label>
                    <div class="wpsmm-settings-inline">
                        <label class="wpsmm-setting-field"><span>Cooldown P1</span><small>Khẩn cấp: offline, lỗi server, SSL.</small><span class="wpsmm-input-unit"><input type="number" min="300" name="wpsmm_alert_cooldown_p1" value="<?php echo esc_attr(get_option('wpsmm_alert_cooldown_p1', 900)); ?>"><em>giây</em></span></label>
                        <label class="wpsmm-setting-field"><span>Cooldown P2</span><small>Cảnh báo: partial, 404, DNS.</small><span class="wpsmm-input-unit"><input type="number" min="600" name="wpsmm_alert_cooldown_p2" value="<?php echo esc_attr(get_option('wpsmm_alert_cooldown_p2', 1800)); ?>"><em>giây</em></span></label>
                    </div>
                    <div class="wpsmm-settings-inline">
                        <label class="wpsmm-setting-field"><span>Cooldown P3</span><small>Thông tin: SSL sắp hết hạn, nội dung.</small><span class="wpsmm-input-unit"><input type="number" min="900" name="wpsmm_alert_cooldown_p3" value="<?php echo esc_attr(get_option('wpsmm_alert_cooldown_p3', 3600)); ?>"><em>giây</em></span></label>
                        <label class="wpsmm-setting-field"><span>Cooldown hồi phục</span><small>Tránh spam thông báo phục hồi.</small><span class="wpsmm-input-unit"><input type="number" min="300" name="wpsmm_alert_cooldown_recovery" value="<?php echo esc_attr(get_option('wpsmm_alert_cooldown_recovery', 1800)); ?>"><em>giây</em></span></label>
                    </div>
                    <div class="wpsmm-channel">
                        <div class="wpsmm-channel-heading"><span class="wpsmm-channel-icon is-telegram"><span class="dashicons dashicons-location-alt"></span></span><div><strong>Telegram</strong><small>Nhận thông báo qua bot Telegram.</small></div><label class="wpsmm-channel-switch"><input type="checkbox" name="wpsmm_enable_telegram_alert" value="1" <?php checked($telegram_enabled); ?>><i></i></label></div>
                        <div class="wpsmm-settings-inline wpsmm-telegram-fields">
                            <label class="wpsmm-setting-field"><span>Mã bot</span><?php wpsmm_secret_input('wpsmm_telegram_bot_token', get_option('wpsmm_telegram_bot_token'), '123456789:AAE...'); ?></label>
                            <label class="wpsmm-setting-field"><span>ID cuộc trò chuyện</span><input name="wpsmm_telegram_chat_id" value="<?php echo esc_attr(get_option('wpsmm_telegram_chat_id')); ?>" placeholder="-1001234567890"></label>
                        </div>
                    </div>
                </section>
                <section class="wpsmm-settings-card" id="wpsmm-settings-integration">
                    <div class="wpsmm-settings-card-heading"><h2>Tích hợp thời gian thực</h2><p>Bảng điều khiển dùng WebSocket nếu được cấu hình và tự động chuyển sang truy vấn REST định kỳ khi cần.</p></div>
                    <label class="wpsmm-setting-field"><span>URL WebSocket</span><input name="wpsmm_websocket_url" value="<?php echo esc_attr(get_option('wpsmm_websocket_url')); ?>" placeholder="wss://monitor.example.com/ws"></label>
                </section>
                <section class="wpsmm-settings-card">
                    <div class="wpsmm-settings-card-heading"><h2>Tùy chọn quản trị</h2><p>Điều chỉnh các thành phần hiển thị trong khu vực quản trị WordPress.</p></div>
                    <label class="wpsmm-admin-option">
                        <input type="checkbox" name="wpsmm_hide_tgmpa_notice" value="1" <?php checked(get_option('wpsmm_hide_tgmpa_notice')); ?>>
                        <span><strong>Ẩn thông báo TGMPA</strong><small>Ẩn khối thông báo <code>setting-error-tgmpa</code> trên các trang quản trị WordPress.</small></span>
                    </label>
                </section>
            </div>
        </div>
        <div class="wpsmm-settings-savebar"><button class="button button-primary"><span class="dashicons dashicons-saved"></span>Lưu thay đổi</button></div>
    </form>
</div>
