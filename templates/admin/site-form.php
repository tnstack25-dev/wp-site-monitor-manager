<?php
if (!defined('ABSPATH')) {
    exit;
}
$edit = $edit ?? null;
$check_interval_minutes = \WPSMM\Plugin::checkIntervalMinutes();
?>
<div class="wrap wpsmm-wrap wpsmm-site-form-page">
    <header class="wpsmm-list-header">
        <div><h1><?php echo $edit ? 'Cập nhật website' : 'Thêm website mới'; ?></h1><p><?php echo $edit ? 'Điều chỉnh thông tin, điều kiện giám sát và tài khoản quản trị.' : 'Thêm website để bắt đầu giám sát và theo dõi trạng thái hoạt động.'; ?></p></div>
        <div class="wpsmm-toolbar-actions">
            <?php if ($edit): ?>
                <a class="button button-primary wpsmm-primary-action" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=new')); ?>"><span class="dashicons dashicons-plus-alt2"></span>Thêm website</a>
            <?php else: ?>
                <button type="submit" form="wpsmm-site-editor" class="button button-primary wpsmm-primary-action"><span class="dashicons dashicons-saved"></span>Thêm website</button>
            <?php endif; ?>
            <a class="button wpsmm-guide-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites')); ?>"><span class="dashicons dashicons-list-view"></span>Danh sách website</a>
        </div>
    </header>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wpsmm-site-editor" id="wpsmm-site-editor">
        <?php wp_nonce_field('wpsmm_save_site'); ?>
        <input type="hidden" name="action" value="wpsmm_save_site">
        <input type="hidden" name="id" value="<?php echo esc_attr($edit->id ?? 0); ?>">
        <main class="wpsmm-editor-main">
            <section class="wpsmm-form-panel">
                <h2>Thông tin website</h2>
                <label>Tên website <b>*</b><small>Tên hiển thị để dễ dàng nhận biết website này.</small><input name="name" id="wpsmm-site-name" maxlength="190" value="<?php echo esc_attr($edit->name ?? ''); ?>" placeholder="Ví dụ: Website công ty, Blog cá nhân..." required></label>
                <label>URL website <b>*</b><small>Nhập đầy đủ URL bao gồm http:// hoặc https://. URL đã tồn tại trong danh sách sẽ không được thêm lại.</small><div class="wpsmm-input-icon"><span class="dashicons dashicons-admin-site-alt3"></span><input name="url" id="wpsmm-site-url" value="<?php echo esc_attr($edit->url ?? ''); ?>" placeholder="https://example.com" required></div></label>
                <label>Nhóm<small>Phân loại website để dễ quản lý.</small><input name="group_name" id="wpsmm-site-group" value="<?php echo esc_attr($edit->group_name ?? ''); ?>" placeholder="Ví dụ: Khách hàng A, Website nội bộ"></label>
            </section>

            <section class="wpsmm-form-panel">
                <h2>Cài đặt giám sát</h2>
                <div class="wpsmm-monitor-modes">
                    <button type="button" class="is-active" data-monitor-mode="basic"><span class="dashicons dashicons-chart-line"></span><strong>Giám sát cơ bản</strong><small>Kiểm tra trạng thái, phản hồi và SSL.</small></button>
                    <button type="button" data-monitor-mode="advanced"><span class="dashicons dashicons-admin-settings"></span><strong>Tùy chỉnh</strong><small>Đặt mã HTTP và tiêu đề mong đợi.</small></button>
                </div>
                <div class="wpsmm-advanced-fields" id="wpsmm-advanced-fields">
                    <label>Đường dẫn kiểm tra trạng thái<small>Để trống để kiểm tra trang chủ. Ví dụ: <code>/health</code> hoặc <code>/wp-json/</code>.</small><input name="health_path" value="<?php echo esc_attr($edit->health_path ?? ''); ?>" placeholder="/health"></label>
                    <label>Mã HTTP mong đợi<small>Mã HTTP được xem là website hoạt động bình thường.</small><input type="number" name="expected_status" min="100" max="599" value="<?php echo esc_attr($edit->expected_status ?? 200); ?>"></label>
                    <label>Tiêu đề mong đợi<small>Cảnh báo nếu tiêu đề website không còn chứa nội dung này.</small><input name="expected_title" value="<?php echo esc_attr($edit->expected_title ?? ''); ?>" placeholder="Để trống nếu không cần kiểm tra"></label>
                </div>
                <div class="wpsmm-static-frequency"><span class="dashicons dashicons-clock"></span><div><strong>Tần suất kiểm tra</strong><small>Hệ thống đang kiểm tra website mỗi <?php echo esc_html($check_interval_minutes); ?> phút.</small></div></div>
                <label class="wpsmm-monitor-option"><input type="checkbox" name="monitor_enabled" value="1" <?php checked(!isset($edit->monitor_enabled) || !empty($edit->monitor_enabled)); ?>><span><strong>Bật tự động giám sát website này</strong><small>Tắt tùy chọn này để tạm dừng tác vụ kiểm tra định kỳ nhưng vẫn giữ lại website và lịch sử giám sát.</small></span></label>
            </section>

            <section class="wpsmm-form-panel">
                <h2>Đăng nhập nhanh website con</h2>
                <p class="wpsmm-form-description">Thông tin được mã hóa trước khi lưu. Website con cần kích hoạt WP Site Monitor Agent để sử dụng đăng nhập nhanh.</p>
                <label>URL đăng nhập<small>URL trang đăng nhập WordPress của website con.</small><input name="login_url" value="<?php echo esc_attr($edit->login_url ?? ''); ?>" placeholder="https://example.com/wp-login.php"></label>
                <label>Khóa kết nối Agent<small><?php echo !empty($edit->agent_secret) ? 'Để trống nếu không muốn thay đổi khóa đã lưu.' : 'Sao chép khóa 64 ký tự từ trang WP Site Monitor Agent trên website con.'; ?></small><input name="agent_secret" autocomplete="off" maxlength="64" placeholder="64 ký tự hex"></label>
                <div class="wpsmm-advanced-fields is-open">
                    <label>Tài khoản<small><?php echo !empty($edit->login_username) ? 'Để trống nếu không muốn thay đổi tài khoản đã lưu.' : 'Tài khoản quản trị website con.'; ?></small><input name="login_username" autocomplete="off" placeholder="admin@example.com"></label>
                    <label>Mật khẩu<small><?php echo !empty($edit->login_password) ? 'Để trống nếu không muốn thay đổi mật khẩu đã lưu.' : 'Mật khẩu quản trị website con.'; ?></small><input type="password" name="login_password" autocomplete="new-password" placeholder="••••••••••••"></label>
                </div>
            </section>

            <footer class="wpsmm-editor-footer"><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites')); ?>">Hủy</a><button class="button button-primary"><span class="dashicons dashicons-saved"></span><?php echo $edit ? 'Lưu thay đổi' : 'Thêm website'; ?></button></footer>
        </main>

        <aside class="wpsmm-editor-aside">
            <section class="wpsmm-summary-panel">
                <h2>Tóm tắt cấu hình</h2>
                <p><span class="dashicons dashicons-format-aside"></span><b>Tên website</b><small id="wpsmm-summary-name"><?php echo esc_html($edit->name ?? 'Chưa nhập'); ?></small></p>
                <p><span class="dashicons dashicons-admin-site-alt3"></span><b>URL website</b><small id="wpsmm-summary-url"><?php echo esc_html($edit->url ?? 'Chưa nhập'); ?></small></p>
                <p><span class="dashicons dashicons-groups"></span><b>Nhóm</b><small id="wpsmm-summary-group"><?php echo esc_html($edit->group_name ?? 'Tất cả website'); ?></small></p>
                <p><span class="dashicons dashicons-chart-line"></span><b>Giám sát</b><small id="wpsmm-summary-mode">Giám sát cơ bản</small></p>
                <p><span class="dashicons dashicons-clock"></span><b>Tần suất kiểm tra</b><small><?php echo esc_html($check_interval_minutes); ?> phút/lần</small></p>
                <div class="wpsmm-editor-note"><span class="dashicons dashicons-info-outline"></span><div><strong>Lưu ý</strong><small>Hệ thống bắt đầu giám sát theo lịch của plugin ngay sau khi lưu website.</small></div></div>
            </section>
        </aside>
    </form>
</div>
