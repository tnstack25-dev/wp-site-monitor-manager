<?php
if (!defined('ABSPATH')) {
    exit;
}
function wpsmm_status_label(string $status): string
{
    $labels = ['online' => 'Đang hoạt động', 'redirect' => 'Chuyển hướng', 'offline' => 'Ngừng hoạt động', 'server_error' => 'Lỗi máy chủ', 'client_error' => 'Lỗi truy cập', 'not_found' => 'Không tìm thấy', 'title_changed' => 'Tiêu đề thay đổi', 'suspicious' => 'Nội dung đáng ngờ', 'ssl_expiring' => 'SSL sắp hết hạn', 'ssl_error' => 'Lỗi SSL', 'unknown' => 'Chưa kiểm tra', 'queued' => 'Đang chờ', 'running' => 'Đang xử lý', 'success' => 'Thành công', 'failed' => 'Thất bại'];
    return $labels[$status] ?? $status;
}
function wpsmm_status_text(string $status): void
{
    echo esc_html(wpsmm_status_label($status));
}
function wpsmm_status_badge(string $status): void
{
    echo '<span class="wpsmm-badge wpsmm-badge-' . esc_attr($status) . '">' . esc_html(wpsmm_status_label($status)) . '</span>';
}
function wpsmm_secret_input(string $name, string $value = '', string $placeholder = ''): void
{
    $placeholder = $placeholder ?: ($value ? 'Để trống nếu không muốn thay đổi' : '');
    echo '<div class="wpsmm-secret"><input type="password" name="' . esc_attr($name) . '" value="" placeholder="' . esc_attr($placeholder) . '" autocomplete="new-password"><button type="button" class="wpsmm-eye" title="Hiện hoặc ẩn nội dung"><span class="dashicons dashicons-visibility"></span></button></div>';
}
