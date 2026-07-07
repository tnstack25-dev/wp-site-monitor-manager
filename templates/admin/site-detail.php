<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/partials.php';
$online = in_array($site->status, ['online', 'redirect'], true);
$partial = $site->status === 'partial';
$login_ready = !empty($site->login_url) && !empty($site->login_username) && !empty($site->login_password);
$credentials = $credentials ?? null;
$inventory = $inventory ?? ['success' => false, 'message' => 'Chưa tải được thông tin kỹ thuật.'];
$inventory_data = !empty($inventory['success']) ? $inventory['data'] : [];
$plugins = is_array($inventory_data['plugins'] ?? null) ? $inventory_data['plugins'] : [];
$themes = is_array($inventory_data['themes'] ?? null) ? $inventory_data['themes'] : [];
$public_site = is_array($inventory_data['public_site'] ?? null) ? $inventory_data['public_site'] : [];
$content = is_array($inventory_data['content'] ?? null) ? $inventory_data['content'] : [];
$network = is_array($inventory_data['network'] ?? null) ? $inventory_data['network'] : [];
$public_ips = array_values(array_unique(array_filter(array_merge(
    (array) ($network['public_ips'] ?? []),
    (array) ($network['dns_ips'] ?? []),
    wpsmm_resolve_site_ips((string) $site->url)
))));
$response_ms = $site->response_time ? round((float) $site->response_time * 1000) : 0;
$incidents = $incidents ?? [];
$sla = \WPSMM\Services\SlaService::siteSla((int) $site->id);
$agent_health = \WPSMM\Services\HeartbeatService::decodeHealth((string) ($site->agent_health ?? ''));
$cron_health = is_array($agent_health['cron'] ?? null) ? $agent_health['cron'] : [];
$synthetic_health = is_array($agent_health['synthetic'] ?? null) ? $agent_health['synthetic'] : [];
$latest_log = !empty($logs[0]) ? $logs[0] : null;
$probe_details = [];
if ($latest_log && !empty($latest_log->technical_details)) {
    $technical = json_decode((string) $latest_log->technical_details, true);
    if (is_array($technical['probes'] ?? null)) {
        $probe_details = $technical['probes'];
    }
}
$probe_labels = [
    'homepage' => 'Trang chủ / health path',
    'rest' => 'REST API WordPress',
    'agent' => 'WP Site Monitor Agent',
];
$public_types = is_array($content['public_post_types'] ?? null) ? $content['public_post_types'] : [];
?>
<div class="wrap wpsmm-wrap wpsmm-site-detail-page">
    <header class="wpsmm-detail-header">
        <div class="wpsmm-detail-identity"><span class="dashicons dashicons-admin-site-alt3"></span><div><h1><?php echo esc_html($site->name); ?> <?php wpsmm_status_badge($site->status); ?></h1><a href="<?php echo esc_url($site->url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($site->url); ?></a><small>Nhóm: <?php wpsmm_group_badge($site); ?> · Lần kiểm tra cuối: <?php echo esc_html($site->last_checked ?: 'Chưa kiểm tra'); ?></small></div></div>
        <div class="wpsmm-detail-actions">
            <button class="button wpsmm-check-site" data-id="<?php echo esc_attr($site->id); ?>"><span class="dashicons dashicons-update"></span>Kiểm tra ngay</button>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&id=' . $site->id)); ?>"><span class="dashicons dashicons-edit"></span>Chỉnh sửa</a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites')); ?>"><span class="dashicons dashicons-list-view"></span>Danh sách</a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=new')); ?>"><span class="dashicons dashicons-plus-alt2"></span>Thêm website</a>
        </div>
    </header>
    <nav class="wpsmm-detail-tabs"><a href="#wpsmm-detail-overview">Tổng quan</a><a href="#wpsmm-detail-public">Thông tin công khai</a><a href="#wpsmm-detail-technical">Kỹ thuật</a><a href="#wpsmm-detail-login">Đăng nhập</a><a href="#wpsmm-detail-extensions">Plugin và giao diện</a><a href="#wpsmm-detail-logs">Lịch sử sự cố</a></nav>

    <section class="wpsmm-detail-stats" id="wpsmm-detail-overview">
        <article><small>Trạng thái hiện tại</small><strong class="<?php echo esc_attr(wpsmm_status_summary_class((string) $site->status)); ?>"><?php echo esc_html(wpsmm_status_summary_text((string) $site->status)); ?></strong><em><?php echo esc_html($site->last_error ?: ($partial ? 'Một số endpoint vẫn phản hồi nhưng trang chủ có vấn đề.' : 'Website hoạt động bình thường.')); ?></em></article>
        <article><small>Uptime (30 ngày)</small><strong><?php echo esc_html(number_format((float) $site->uptime_percent, 2)); ?>%</strong><em>SLA log: <?php echo esc_html(number_format((float) $sla['uptime_30d'], 2)); ?>% · MTTR <?php echo esc_html(number_format((float) $sla['mttr_minutes'], 1)); ?> phút.</em></article>
        <article><small>Thời gian phản hồi</small><strong><?php echo $response_ms ? esc_html($response_ms . ' ms') : '-'; ?></strong><em>Phản hồi của lần kiểm tra gần nhất.</em></article>
        <article><small>SSL</small><strong><?php echo $site->ssl_days_left === null ? 'Chưa có dữ liệu' : esc_html(max(0, (int) $site->ssl_days_left) . ' ngày'); ?></strong><em><?php echo $site->ssl_days_left !== null && (int) $site->ssl_days_left < 0 ? 'Chứng chỉ đã hết hạn.' : 'Thời gian còn lại của chứng chỉ.'; ?></em></article>
        <article><small>WP Site Monitor Agent</small><strong class="<?php echo esc_attr(($site->agent_status ?? '') === 'online' ? 'wpsmm-text-success' : (($site->agent_status ?? '') === 'partial' ? 'wpsmm-text-warning' : 'wpsmm-text-danger')); ?>"><?php echo esc_html(($site->agent_status ?? '') === 'online' ? 'Trực tuyến' : (($site->agent_status ?? '') === 'partial' ? 'Một phần' : 'Ngoại tuyến')); ?></strong><em>Heartbeat: <?php echo esc_html($site->agent_heartbeat_at ?? $site->agent_checked_at ?? 'chưa có dữ liệu'); ?></em></article>
    </section>

    <section class="wpsmm-panel wpsmm-detail-panel wpsmm-public-panel" id="wpsmm-detail-public">
        <div class="wpsmm-detail-panel-heading">
            <h2>Thông tin công khai từ website con</h2>
            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpsmm-sites&action=view&id=' . $site->id . '&refresh_inventory=1'), 'wpsmm_refresh_inventory_' . $site->id)); ?>"><span class="dashicons dashicons-update"></span>Làm mới</a>
        </div>
        <?php if (!empty($inventory['message'])): ?>
            <div class="wpsmm-inventory-note"><?php echo esc_html($inventory['message']); ?></div>
        <?php endif; ?>
        <?php if (empty($inventory['success'])): ?>
            <div class="wpsmm-inventory-error"><?php echo esc_html($inventory['message'] ?? 'Không thể tải thông tin công khai.'); ?></div>
        <?php else: ?>
            <div class="wpsmm-public-summary">
                <article><span class="dashicons dashicons-admin-post"></span><div><strong><?php echo esc_html(wpsmm_format_count($content['posts'] ?? null)); ?></strong><small>Bài viết đã xuất bản</small></div></article>
                <article><span class="dashicons dashicons-admin-page"></span><div><strong><?php echo esc_html(wpsmm_format_count($content['pages'] ?? null)); ?></strong><small>Trang đã xuất bản</small></div></article>
                <article><span class="dashicons dashicons-cart"></span><div><strong><?php echo !empty($content['woocommerce_active']) ? esc_html(wpsmm_format_count($content['products'] ?? null)) : '-'; ?></strong><small><?php echo !empty($content['woocommerce_active']) ? 'Sản phẩm WooCommerce' : 'Chưa cài WooCommerce'; ?></small></div></article>
                <article><span class="dashicons dashicons-admin-site-alt3"></span><div><strong><?php echo esc_html(wpsmm_format_ips($public_ips)); ?></strong><small>IP công khai / DNS</small></div></article>
            </div>
            <div class="wpsmm-detail-grid">
                <section class="wpsmm-detail-subpanel">
                    <h3>Thông tin website</h3>
                    <dl>
                        <div><dt>Tên website</dt><dd><?php echo esc_html($public_site['name'] ?? '-'); ?></dd></div>
                        <div><dt>Mô tả</dt><dd><?php echo esc_html($public_site['description'] ?? '-'); ?></dd></div>
                        <div><dt>URL công khai</dt><dd><?php if (!empty($public_site['url'])): ?><a href="<?php echo esc_url($public_site['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($public_site['url']); ?></a><?php else: ?>-<?php endif; ?></dd></div>
                        <div><dt>Ngôn ngữ</dt><dd><?php echo esc_html($public_site['language'] ?? '-'); ?></dd></div>
                        <div><dt>Múi giờ</dt><dd><?php echo esc_html($public_site['timezone'] ?? '-'); ?></dd></div>
                        <div><dt>Tổng tài khoản</dt><dd><?php echo isset($public_site['users']) && $public_site['users'] !== null ? esc_html(wpsmm_format_count($public_site['users'])) : '-'; ?></dd></div>
                        <div><dt>REST API</dt><dd><?php echo !empty($public_site['rest_url']) ? '<code>' . esc_html($public_site['rest_url']) . '</code>' : '-'; ?></dd></div>
                    </dl>
                </section>
                <section class="wpsmm-detail-subpanel">
                    <h3>Mạng và máy chủ</h3>
                    <dl>
                        <div><dt>Hostname</dt><dd><?php echo esc_html($network['hostname'] ?? parse_url((string) $site->url, PHP_URL_HOST) ?: '-'); ?></dd></div>
                        <div><dt>IP công khai / DNS</dt><dd><code><?php echo esc_html(wpsmm_format_ips($public_ips)); ?></code></dd></div>
                        <div><dt>IP máy chủ web</dt><dd><code><?php echo esc_html($network['server_ip'] ?? '-'); ?></code></dd></div>
                        <div><dt>IP request gần nhất</dt><dd><code><?php echo esc_html($network['request_ip'] ?? '-'); ?></code></dd></div>
                        <div><dt>Nguồn dữ liệu</dt><dd><?php echo esc_html(($inventory['source'] ?? 'agent') === 'wp_rest' ? 'REST API WordPress' : 'WP Site Monitor Agent'); ?></dd></div>
                    </dl>
                </section>
            </div>
            <?php if ($public_types): ?>
                <section class="wpsmm-detail-subpanel wpsmm-public-types">
                    <h3>Loại nội dung public khác</h3>
                    <div class="wpsmm-table-scroll">
                        <table class="widefat wpsmm-extension-table">
                            <thead><tr><th>Loại nội dung</th><th>Slug</th><th>Số bài đã xuất bản</th></tr></thead>
                            <tbody>
                            <?php foreach ($public_types as $type): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($type['label'] ?? $type['slug'] ?? '-'); ?></strong></td>
                                    <td><code><?php echo esc_html($type['slug'] ?? '-'); ?></code></td>
                                    <td><?php echo esc_html(wpsmm_format_count($type['count'] ?? null)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <div class="wpsmm-detail-grid">
        <section class="wpsmm-panel wpsmm-detail-panel">
            <h2>Thông tin website</h2>
            <dl><div><dt>URL</dt><dd><a href="<?php echo esc_url($site->url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($site->url); ?></a></dd></div><div><dt>Đường dẫn kiểm tra trạng thái</dt><dd><?php echo esc_html($site->health_path ?: 'Trang chủ'); ?></dd></div><div><dt>Tự động giám sát</dt><dd><?php echo empty($site->monitor_enabled) ? 'Tạm dừng' : 'Đang bật'; ?></dd></div><div><dt>Nhóm</dt><dd><?php wpsmm_group_badge($site); ?></dd></div><div><dt>Mã HTTP</dt><dd><?php echo esc_html($site->http_code ?: '-'); ?></dd></div><div><dt>Mã HTTP mong đợi</dt><dd><?php echo esc_html($site->expected_status); ?></dd></div><div><dt>Tiêu đề mong đợi</dt><dd><?php echo esc_html($site->expected_title ?: 'Không kiểm tra'); ?></dd></div><div><dt>Ngày thêm</dt><dd><?php echo esc_html($site->created_at); ?></dd></div></dl>
        </section>
        <section class="wpsmm-panel wpsmm-detail-panel" id="wpsmm-detail-login">
            <h2>Đăng nhập quản trị</h2>
            <p class="wpsmm-detail-note">Thông tin đăng nhập được mã hóa. Bạn cần nhập lại mật khẩu WordPress hiện tại trước khi xem nội dung đã lưu.</p>
            <dl><div><dt>URL đăng nhập</dt><dd><?php echo esc_html($site->login_url ?: 'Chưa cấu hình'); ?></dd></div><div><dt>Tài khoản</dt><dd><?php echo $login_ready ? 'Đã lưu an toàn' : 'Chưa cấu hình'; ?></dd></div><div><dt>Mật khẩu</dt><dd><?php echo $login_ready ? '••••••••••••' : 'Chưa cấu hình'; ?></dd></div></dl>
            <?php if ($credentials): ?>
                <div class="wpsmm-credentials-reveal">
                    <p><span>Tài khoản</span><code><?php echo esc_html($credentials['username']); ?></code></p>
                    <p><span>Mật khẩu</span><code><?php echo esc_html($credentials['password']); ?></code></p>
                    <small>Thông tin chỉ hiển thị trong lần tải trang này. Tải lại trang để ẩn nội dung.</small>
                </div>
            <?php elseif ($login_ready): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wpsmm-reveal-form">
                    <input type="hidden" name="action" value="wpsmm_reveal_credentials"><input type="hidden" name="id" value="<?php echo esc_attr($site->id); ?>"><?php wp_nonce_field('wpsmm_reveal_credentials_' . $site->id); ?>
                    <label>Mật khẩu WordPress hiện tại<input type="password" name="confirmation_password" autocomplete="current-password" required></label>
                    <button class="button"><span class="dashicons dashicons-visibility"></span>Xem tài khoản và mật khẩu</button>
                </form>
            <?php endif; ?>
            <div class="wpsmm-detail-login-actions">
                <?php if ($login_ready): ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" target="_blank" class="wpsmm-quick-login-form"><input type="hidden" name="action" value="wpsmm_quick_login"><input type="hidden" name="id" value="<?php echo esc_attr($site->id); ?>"><?php wp_nonce_field('wpsmm_quick_login_' . $site->id); ?><input type="password" name="confirmation_password" autocomplete="current-password" placeholder="Mật khẩu WordPress hiện tại" required><button class="button button-primary"><span class="dashicons dashicons-admin-network"></span>Đăng nhập nhanh</button></form><?php else: ?><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&id=' . $site->id)); ?>">Cấu hình đăng nhập</a><?php endif; ?>
            </div>
            <details class="wpsmm-credentials-editor">
                <summary><span class="dashicons dashicons-edit"></span>Cập nhật tài khoản và mật khẩu</summary>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wpsmm_update_site_credentials"><input type="hidden" name="id" value="<?php echo esc_attr($site->id); ?>"><?php wp_nonce_field('wpsmm_update_site_credentials_' . $site->id); ?>
                    <label>URL đăng nhập<input name="login_url" value="<?php echo esc_attr($site->login_url); ?>" placeholder="https://example.com/wp-login.php" required></label>
                    <label>Tài khoản mới<input name="login_username" autocomplete="off" required></label>
                    <label>Mật khẩu mới<input type="password" name="login_password" autocomplete="new-password" required></label>
                    <label>Khóa kết nối Agent<input name="agent_secret" autocomplete="off" maxlength="64" placeholder="Để trống nếu không đổi khóa"></label>
                    <label>Mật khẩu WordPress hiện tại của bạn<input type="password" name="confirmation_password" autocomplete="current-password" required></label>
                    <button class="button button-primary"><span class="dashicons dashicons-saved"></span>Lưu thông tin đăng nhập</button>
                </form>
            </details>
        </section>
    </div>

    <div class="wpsmm-detail-grid" id="wpsmm-detail-technical">
        <section class="wpsmm-panel wpsmm-detail-panel wpsmm-probe-panel">
            <div class="wpsmm-detail-panel-heading"><h2>Kiểm tra endpoint song song</h2><small>Kết quả từ lần giám sát gần nhất</small></div>
            <?php if (!$probe_details): ?>
                <div class="wpsmm-empty-state">Chưa có dữ liệu kiểm tra endpoint. Hãy bấm "Kiểm tra ngay".</div>
            <?php else: ?>
                <div class="wpsmm-table-scroll">
                    <table class="widefat wpsmm-extension-table">
                        <thead><tr><th>Endpoint</th><th>URL</th><th>Trạng thái</th><th>HTTP</th><th>Thời gian</th><th>Thông điệp</th></tr></thead>
                        <tbody>
                        <?php foreach ($probe_details as $probe => $detail): ?>
                            <tr>
                                <td><strong><?php echo esc_html($probe_labels[$probe] ?? $probe); ?></strong></td>
                                <td><code><?php echo esc_html($detail['endpoint'] ?? '-'); ?></code></td>
                                <td><span class="wpsmm-status-pill <?php echo !empty($detail['ok']) ? 'success' : 'danger'; ?>"><i></i><?php echo !empty($detail['ok']) ? 'OK' : 'Lỗi'; ?></span></td>
                                <td><?php echo esc_html($detail['http_code'] ?? '-'); ?></td>
                                <td><?php echo isset($detail['response_time']) ? esc_html(round((float) $detail['response_time'] * 1000) . ' ms') : '-'; ?></td>
                                <td><?php echo esc_html($detail['message'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <section class="wpsmm-panel wpsmm-detail-panel">
            <div class="wpsmm-detail-panel-heading"><h2>DNS và SLA</h2><small>Theo dõi bản ghi DNS và uptime theo nhật ký</small></div>
            <dl>
                <div><dt>Bản ghi DNS (A/AAAA)</dt><dd><code><?php echo esc_html(\WPSMM\Services\DnsMonitorService::formatRecords((string) ($site->dns_records ?? ''))); ?></code></dd></div>
                <div><dt>Kiểm tra DNS lần cuối</dt><dd><?php echo esc_html($site->dns_checked_at ?: 'Chưa kiểm tra'); ?></dd></div>
                <div><dt>Thay đổi DNS gần nhất</dt><dd><?php echo esc_html($site->dns_changed_at ?: 'Chưa phát hiện'); ?></dd></div>
                <div><dt>SLA 7 / 30 / 90 ngày</dt><dd><?php echo esc_html(number_format((float) $sla['uptime_7d'], 2) . '% / ' . number_format((float) $sla['uptime_30d'], 2) . '% / ' . number_format((float) $sla['uptime_90d'], 2) . '%'); ?></dd></div>
            </dl>
        </section>
        <section class="wpsmm-panel wpsmm-detail-panel">
            <div class="wpsmm-detail-panel-heading"><h2>WP-Cron và kiểm tra nội bộ</h2><small>Dữ liệu từ heartbeat Agent</small></div>
            <?php if (!$agent_health): ?>
                <div class="wpsmm-empty-state">Chưa nhận heartbeat từ Agent. Bật heartbeat trên website con.</div>
            <?php else: ?>
                <dl>
                    <div><dt>WP-Cron</dt><dd><span class="wpsmm-status-pill <?php echo !empty($cron_health['ok']) ? 'success' : 'danger'; ?>"><i></i><?php echo !empty($cron_health['ok']) ? 'Ổn định' : 'Cần kiểm tra'; ?></span></dd></div>
                    <div><dt>DISABLE_WP_CRON</dt><dd><?php echo !empty($cron_health['disabled']) ? 'Có' : 'Không'; ?></dd></div>
                    <div><dt>Sự kiện trễ</dt><dd><?php echo esc_html((string) ($cron_health['late_events'] ?? 0)); ?></dd></div>
                    <div><dt>Lịch chạy tiếp theo</dt><dd><?php echo esc_html($cron_health['next_scheduled'] ?? '-'); ?></dd></div>
                    <div><dt>Kiểm tra synthetic (home)</dt><dd><?php echo !empty($synthetic_health['ok']) ? esc_html('HTTP ' . ($synthetic_health['home_http_code'] ?? '-') . ' · ' . ($synthetic_health['home_response_ms'] ?? '-') . ' ms') : esc_html($synthetic_health['error'] ?? 'Lỗi'); ?></dd></div>
                </dl>
            <?php endif; ?>
        </section>
        <section class="wpsmm-panel wpsmm-detail-panel">
            <div class="wpsmm-detail-panel-heading"><h2>Thông tin kỹ thuật</h2><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpsmm-sites&action=view&id=' . $site->id . '&refresh_inventory=1'), 'wpsmm_refresh_inventory_' . $site->id)); ?>"><span class="dashicons dashicons-update"></span>Làm mới</a></div>
            <?php if (empty($inventory['success'])): ?>
                <div class="wpsmm-inventory-error"><?php echo esc_html($inventory['message'] ?? 'Không thể tải thông tin kỹ thuật.'); ?></div>
            <?php else: ?>
                <dl><div><dt>WordPress</dt><dd><?php echo esc_html($inventory_data['wordpress'] ?? '-'); ?></dd></div><div><dt>PHP</dt><dd><?php echo esc_html($inventory_data['php'] ?? '-'); ?></dd></div><div><dt>Cơ sở dữ liệu</dt><dd><?php echo esc_html($inventory_data['database'] ?? '-'); ?></dd></div><div><dt>Máy chủ web</dt><dd><?php echo esc_html($inventory_data['server'] ?? '-'); ?></dd></div><div><dt>Cập nhật lúc</dt><dd><?php echo esc_html($inventory_data['generated_at'] ?? '-'); ?></dd></div></dl>
            <?php endif; ?>
        </section>
        <section class="wpsmm-panel wpsmm-detail-panel">
            <h2>Tổng quan thành phần</h2>
            <div class="wpsmm-extension-summary">
                <article><span class="dashicons dashicons-admin-plugins"></span><div><strong><?php echo esc_html(count($plugins)); ?></strong><small>Plugin đã cài đặt</small></div></article>
                <article><span class="dashicons dashicons-admin-appearance"></span><div><strong><?php echo esc_html(count($themes)); ?></strong><small>Giao diện đã cài đặt</small></div></article>
                <article><span class="dashicons dashicons-update"></span><div><strong><?php echo esc_html(count(array_filter(array_merge($plugins, $themes), static fn($item) => !empty($item['update_available'])))); ?></strong><small>Bản cập nhật khả dụng</small></div></article>
            </div>
        </section>
    </div>

    <section class="wpsmm-panel wpsmm-detail-panel wpsmm-extensions-panel" id="wpsmm-detail-extensions">
        <div class="wpsmm-detail-panel-heading"><h2>Plugin và giao diện</h2><small>Dữ liệu được lấy từ WP Site Monitor Agent trên website con.</small></div>
        <?php if (empty($inventory['success'])): ?>
            <div class="wpsmm-inventory-error"><?php echo esc_html($inventory['message'] ?? 'Không thể tải danh sách plugin và giao diện.'); ?></div>
        <?php else: ?>
            <div class="wpsmm-extension-tabs"><button type="button" class="is-active" data-extension-tab="plugins">Plugin <b><?php echo esc_html(count($plugins)); ?></b></button><button type="button" data-extension-tab="themes">Giao diện <b><?php echo esc_html(count($themes)); ?></b></button></div>
            <div class="wpsmm-table-scroll" data-extension-panel="plugins"><table class="widefat wpsmm-extension-table"><thead><tr><th>Plugin</th><th>Phiên bản</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead><tbody><?php if (!$plugins): ?><tr><td colspan="4"><div class="wpsmm-empty-state">Không có plugin.</div></td></tr><?php endif; ?><?php foreach ($plugins as $plugin): ?><tr><td><strong><?php echo esc_html($plugin['name'] ?? $plugin['file'] ?? '-'); ?></strong><small><?php echo esc_html($plugin['file'] ?? ''); ?></small></td><td><?php echo esc_html($plugin['version'] ?? '-'); ?></td><td><span class="wpsmm-status-pill <?php echo !empty($plugin['active']) ? 'success' : 'muted'; ?>"><i></i><?php echo !empty($plugin['active']) ? 'Đang bật' : 'Đang tắt'; ?></span></td><td><?php echo !empty($plugin['update_available']) ? '<span class="wpsmm-update-available">Có bản ' . esc_html($plugin['new_version'] ?? '') . '</span>' : '<span class="wpsmm-text-success">Mới nhất</span>'; ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="wpsmm-table-scroll" data-extension-panel="themes" hidden><table class="widefat wpsmm-extension-table"><thead><tr><th>Giao diện</th><th>Phiên bản</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead><tbody><?php if (!$themes): ?><tr><td colspan="4"><div class="wpsmm-empty-state">Không có giao diện.</div></td></tr><?php endif; ?><?php foreach ($themes as $theme): ?><tr><td><strong><?php echo esc_html($theme['name'] ?? $theme['stylesheet'] ?? '-'); ?></strong><small><?php echo esc_html($theme['stylesheet'] ?? ''); ?></small></td><td><?php echo esc_html($theme['version'] ?? '-'); ?></td><td><span class="wpsmm-status-pill <?php echo !empty($theme['active']) ? 'success' : 'muted'; ?>"><i></i><?php echo !empty($theme['active']) ? 'Đang dùng' : 'Không dùng'; ?></span></td><td><?php echo !empty($theme['update_available']) ? '<span class="wpsmm-update-available">Có bản ' . esc_html($theme['new_version'] ?? '') . '</span>' : '<span class="wpsmm-text-success">Mới nhất</span>'; ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>

    <section class="wpsmm-panel wpsmm-detail-panel" id="wpsmm-detail-logs">
        <div class="wpsmm-detail-panel-heading"><h2>Nhật ký gần đây</h2><a href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-logs&site_id=' . $site->id)); ?>">Xem tất cả</a></div>
        <div class="wpsmm-table-scroll"><table class="widefat wpsmm-log-table"><thead><tr><th>Thời gian</th><th>Trạng thái</th><th>Mã HTTP</th><th>Phản hồi</th><th>Thông điệp</th></tr></thead><tbody><?php if (!$logs): ?><tr><td colspan="5"><div class="wpsmm-empty-state">Chưa có nhật ký giám sát.</div></td></tr><?php endif; ?><?php foreach ($logs as $log): ?><tr><td><?php echo esc_html($log->checked_at); ?></td><td><?php wpsmm_status_badge($log->status); ?></td><td><?php echo esc_html($log->http_code); ?></td><td><?php echo esc_html(round((float) $log->response_time * 1000)); ?> ms</td><td><?php echo esc_html($log->message); ?></td></tr><?php endforeach; ?></tbody></table></div>
    </section>
    <section class="wpsmm-panel wpsmm-detail-panel">
        <div class="wpsmm-detail-panel-heading"><h2>Lịch sử sự cố</h2></div>
        <div class="wpsmm-table-scroll"><table class="widefat wpsmm-log-table"><thead><tr><th>Bắt đầu</th><th>Kết thúc</th><th>Trạng thái</th><th>Số lần ghi nhận</th><th>Thông điệp cuối</th></tr></thead><tbody><?php if (!$incidents): ?><tr><td colspan="5"><div class="wpsmm-empty-state">Chưa có sự cố.</div></td></tr><?php endif; ?><?php foreach ($incidents as $incident): ?><tr><td><?php echo esc_html($incident->started_at); ?></td><td><?php echo esc_html($incident->resolved_at ?: 'Đang diễn ra'); ?></td><td><?php wpsmm_status_badge($incident->status); ?></td><td><?php echo esc_html($incident->check_count); ?></td><td><?php echo esc_html($incident->message); ?></td></tr><?php endforeach; ?></tbody></table></div>
    </section>
</div>
