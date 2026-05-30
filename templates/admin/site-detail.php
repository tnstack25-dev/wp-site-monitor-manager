<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/partials.php';
$online = in_array($site->status, ['online', 'redirect'], true);
$login_ready = !empty($site->login_url) && !empty($site->login_username) && !empty($site->login_password);
$credentials = $credentials ?? null;
$inventory = $inventory ?? ['success' => false, 'message' => 'Chưa tải được thông tin kỹ thuật.'];
$inventory_data = !empty($inventory['success']) ? $inventory['data'] : [];
$plugins = is_array($inventory_data['plugins'] ?? null) ? $inventory_data['plugins'] : [];
$themes = is_array($inventory_data['themes'] ?? null) ? $inventory_data['themes'] : [];
$response_ms = $site->response_time ? round((float) $site->response_time * 1000) : 0;
?>
<div class="wrap wpsmm-wrap wpsmm-site-detail-page">
    <header class="wpsmm-detail-header">
        <div class="wpsmm-detail-identity"><span class="dashicons dashicons-admin-site-alt3"></span><div><h1><?php echo esc_html($site->name); ?> <?php wpsmm_status_badge($site->status); ?></h1><a href="<?php echo esc_url($site->url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($site->url); ?></a><small>Nhóm: <?php echo esc_html($site->group_name ?: 'Tất cả website'); ?> · Lần kiểm tra cuối: <?php echo esc_html($site->last_checked ?: 'Chưa kiểm tra'); ?></small></div></div>
        <div class="wpsmm-detail-actions">
            <button class="button wpsmm-check-site" data-id="<?php echo esc_attr($site->id); ?>"><span class="dashicons dashicons-update"></span>Kiểm tra ngay</button>
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&id=' . $site->id)); ?>"><span class="dashicons dashicons-edit"></span>Chỉnh sửa</a>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites')); ?>"><span class="dashicons dashicons-list-view"></span>Danh sách</a>
        </div>
    </header>
    <nav class="wpsmm-detail-tabs"><a href="#wpsmm-detail-overview">Tổng quan</a><a href="#wpsmm-detail-technical">Kỹ thuật</a><a href="#wpsmm-detail-login">Đăng nhập</a><a href="#wpsmm-detail-extensions">Plugin & Theme</a><a href="#wpsmm-detail-logs">Lịch sử sự cố</a></nav>

    <section class="wpsmm-detail-stats" id="wpsmm-detail-overview">
        <article><small>Trạng thái hiện tại</small><strong class="<?php echo $online ? 'wpsmm-text-success' : 'wpsmm-text-danger'; ?>"><?php echo $online ? 'Đang hoạt động' : 'Cần kiểm tra'; ?></strong><em><?php echo esc_html($site->last_error ?: 'Website hoạt động bình thường.'); ?></em></article>
        <article><small>Uptime (30 ngày)</small><strong><?php echo esc_html(number_format((float) $site->uptime_percent, 2)); ?>%</strong><em>Thời gian website hoạt động ổn định.</em></article>
        <article><small>Thời gian phản hồi</small><strong><?php echo $response_ms ? esc_html($response_ms . ' ms') : '-'; ?></strong><em>Phản hồi của lần kiểm tra gần nhất.</em></article>
        <article><small>SSL</small><strong><?php echo $site->ssl_days_left === null ? 'Chưa có dữ liệu' : esc_html(max(0, (int) $site->ssl_days_left) . ' ngày'); ?></strong><em><?php echo $site->ssl_days_left !== null && (int) $site->ssl_days_left < 0 ? 'Chứng chỉ đã hết hạn.' : 'Thời gian còn lại của chứng chỉ.'; ?></em></article>
    </section>

    <div class="wpsmm-detail-grid">
        <section class="wpsmm-panel wpsmm-detail-panel">
            <h2>Thông tin website</h2>
            <dl><div><dt>URL</dt><dd><a href="<?php echo esc_url($site->url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($site->url); ?></a></dd></div><div><dt>Nhóm</dt><dd><?php echo esc_html($site->group_name ?: 'Tất cả website'); ?></dd></div><div><dt>Mã HTTP</dt><dd><?php echo esc_html($site->http_code ?: '-'); ?></dd></div><div><dt>Mã HTTP mong đợi</dt><dd><?php echo esc_html($site->expected_status); ?></dd></div><div><dt>Tiêu đề mong đợi</dt><dd><?php echo esc_html($site->expected_title ?: 'Không kiểm tra'); ?></dd></div><div><dt>Ngày thêm</dt><dd><?php echo esc_html($site->created_at); ?></dd></div></dl>
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
        <section class="wpsmm-panel wpsmm-detail-panel">
            <div class="wpsmm-detail-panel-heading"><h2>Thông tin kỹ thuật</h2><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=wpsmm-sites&action=view&id=' . $site->id . '&refresh_inventory=1'), 'wpsmm_refresh_inventory_' . $site->id)); ?>"><span class="dashicons dashicons-update"></span>Làm mới</a></div>
            <?php if (empty($inventory['success'])): ?>
                <div class="wpsmm-inventory-error"><?php echo esc_html($inventory['message'] ?? 'Không thể tải thông tin kỹ thuật.'); ?></div>
            <?php else: ?>
                <dl><div><dt>WordPress</dt><dd><?php echo esc_html($inventory_data['wordpress'] ?? '-'); ?></dd></div><div><dt>PHP</dt><dd><?php echo esc_html($inventory_data['php'] ?? '-'); ?></dd></div><div><dt>Database</dt><dd><?php echo esc_html($inventory_data['database'] ?? '-'); ?></dd></div><div><dt>Web server</dt><dd><?php echo esc_html($inventory_data['server'] ?? '-'); ?></dd></div><div><dt>Cập nhật lúc</dt><dd><?php echo esc_html($inventory_data['generated_at'] ?? '-'); ?></dd></div></dl>
            <?php endif; ?>
        </section>
        <section class="wpsmm-panel wpsmm-detail-panel">
            <h2>Tổng quan thành phần</h2>
            <div class="wpsmm-extension-summary">
                <article><span class="dashicons dashicons-admin-plugins"></span><div><strong><?php echo esc_html(count($plugins)); ?></strong><small>Plugin đã cài đặt</small></div></article>
                <article><span class="dashicons dashicons-admin-appearance"></span><div><strong><?php echo esc_html(count($themes)); ?></strong><small>Theme đã cài đặt</small></div></article>
                <article><span class="dashicons dashicons-update"></span><div><strong><?php echo esc_html(count(array_filter(array_merge($plugins, $themes), static fn($item) => !empty($item['update_available'])))); ?></strong><small>Bản cập nhật khả dụng</small></div></article>
            </div>
        </section>
    </div>

    <section class="wpsmm-panel wpsmm-detail-panel wpsmm-extensions-panel" id="wpsmm-detail-extensions">
        <div class="wpsmm-detail-panel-heading"><h2>Plugin & Theme</h2><small>Dữ liệu được lấy từ WP Site Monitor Agent trên website con.</small></div>
        <?php if (empty($inventory['success'])): ?>
            <div class="wpsmm-inventory-error"><?php echo esc_html($inventory['message'] ?? 'Không thể tải danh sách plugin và theme.'); ?></div>
        <?php else: ?>
            <div class="wpsmm-extension-tabs"><button type="button" class="is-active" data-extension-tab="plugins">Plugin <b><?php echo esc_html(count($plugins)); ?></b></button><button type="button" data-extension-tab="themes">Theme <b><?php echo esc_html(count($themes)); ?></b></button></div>
            <div class="wpsmm-table-scroll" data-extension-panel="plugins"><table class="widefat wpsmm-extension-table"><thead><tr><th>Plugin</th><th>Phiên bản</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead><tbody><?php if (!$plugins): ?><tr><td colspan="4"><div class="wpsmm-empty-state">Không có plugin.</div></td></tr><?php endif; ?><?php foreach ($plugins as $plugin): ?><tr><td><strong><?php echo esc_html($plugin['name'] ?? $plugin['file'] ?? '-'); ?></strong><small><?php echo esc_html($plugin['file'] ?? ''); ?></small></td><td><?php echo esc_html($plugin['version'] ?? '-'); ?></td><td><span class="wpsmm-status-pill <?php echo !empty($plugin['active']) ? 'success' : 'muted'; ?>"><i></i><?php echo !empty($plugin['active']) ? 'Đang bật' : 'Đang tắt'; ?></span></td><td><?php echo !empty($plugin['update_available']) ? '<span class="wpsmm-update-available">Có bản ' . esc_html($plugin['new_version'] ?? '') . '</span>' : '<span class="wpsmm-text-success">Mới nhất</span>'; ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="wpsmm-table-scroll" data-extension-panel="themes" hidden><table class="widefat wpsmm-extension-table"><thead><tr><th>Theme</th><th>Phiên bản</th><th>Trạng thái</th><th>Cập nhật</th></tr></thead><tbody><?php if (!$themes): ?><tr><td colspan="4"><div class="wpsmm-empty-state">Không có theme.</div></td></tr><?php endif; ?><?php foreach ($themes as $theme): ?><tr><td><strong><?php echo esc_html($theme['name'] ?? $theme['stylesheet'] ?? '-'); ?></strong><small><?php echo esc_html($theme['stylesheet'] ?? ''); ?></small></td><td><?php echo esc_html($theme['version'] ?? '-'); ?></td><td><span class="wpsmm-status-pill <?php echo !empty($theme['active']) ? 'success' : 'muted'; ?>"><i></i><?php echo !empty($theme['active']) ? 'Đang dùng' : 'Không dùng'; ?></span></td><td><?php echo !empty($theme['update_available']) ? '<span class="wpsmm-update-available">Có bản ' . esc_html($theme['new_version'] ?? '') . '</span>' : '<span class="wpsmm-text-success">Mới nhất</span>'; ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
    </section>

    <section class="wpsmm-panel wpsmm-detail-panel" id="wpsmm-detail-logs">
        <div class="wpsmm-detail-panel-heading"><h2>Nhật ký gần đây</h2><a href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-logs&site_id=' . $site->id)); ?>">Xem tất cả</a></div>
        <div class="wpsmm-table-scroll"><table class="widefat wpsmm-log-table"><thead><tr><th>Thời gian</th><th>Trạng thái</th><th>Mã HTTP</th><th>Phản hồi</th><th>Thông điệp</th></tr></thead><tbody><?php if (!$logs): ?><tr><td colspan="5"><div class="wpsmm-empty-state">Chưa có nhật ký giám sát.</div></td></tr><?php endif; ?><?php foreach ($logs as $log): ?><tr><td><?php echo esc_html($log->checked_at); ?></td><td><?php wpsmm_status_badge($log->status); ?></td><td><?php echo esc_html($log->http_code); ?></td><td><?php echo esc_html(round((float) $log->response_time * 1000)); ?> ms</td><td><?php echo esc_html($log->message); ?></td></tr><?php endforeach; ?></tbody></table></div>
    </section>
</div>
