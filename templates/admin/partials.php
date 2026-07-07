<?php
if (!defined('ABSPATH')) {
    exit;
}
function wpsmm_status_label(string $status): string
{
    $labels = ['online' => 'Đang hoạt động', 'redirect' => 'Chuyển hướng', 'partial' => 'Hoạt động một phần', 'offline' => 'Ngừng hoạt động', 'server_error' => 'Lỗi máy chủ', 'client_error' => 'Lỗi truy cập', 'not_found' => 'Không tìm thấy', 'title_changed' => 'Tiêu đề thay đổi', 'suspicious' => 'Nội dung đáng ngờ', 'ssl_expiring' => 'SSL sắp hết hạn', 'ssl_error' => 'Lỗi SSL', 'unknown' => 'Chưa kiểm tra', 'paused' => 'Tạm dừng', 'queued' => 'Đang chờ', 'running' => 'Đang xử lý', 'success' => 'Thành công', 'failed' => 'Thất bại'];
    return $labels[$status] ?? $status;
}

function wpsmm_status_tone(string $status): string
{
    if (in_array($status, ['online', 'redirect'], true)) {
        return 'success';
    }
    if ($status === 'partial') {
        return 'partial';
    }
    if (in_array($status, ['offline', 'server_error', 'ssl_error'], true)) {
        return 'danger';
    }
    if (in_array($status, ['unknown', 'paused'], true)) {
        return 'muted';
    }
    return 'warning';
}

function wpsmm_status_summary_text(string $status): string
{
    if (in_array($status, ['online', 'redirect'], true)) {
        return 'Đang hoạt động';
    }
    if ($status === 'partial') {
        return 'Hoạt động một phần';
    }
    return 'Cần kiểm tra';
}

function wpsmm_status_summary_class(string $status): string
{
    if (in_array($status, ['online', 'redirect'], true)) {
        return 'wpsmm-text-success';
    }
    if ($status === 'partial') {
        return 'wpsmm-text-warning';
    }
    return 'wpsmm-text-danger';
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

function wpsmm_pagination_pages(int $page, int $pages): array
{
    if ($pages <= 7) {
        return range(1, $pages);
    }
    $items = array_values(array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages], static function (int $item) use ($pages): bool {
        return $item >= 1 && $item <= $pages;
    })));
    sort($items);
    return $items;
}

function wpsmm_resolve_site_ips(string $url): array
{
    return \WPSMM\Services\PublicSiteService::resolveHostIps($url);
}

function wpsmm_format_ips(array $ips): string
{
    $ips = array_values(array_unique(array_filter(array_map('strval', $ips))));
    return $ips ? implode(', ', $ips) : '-';
}

function wpsmm_format_count($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    return number_format_i18n((int) $value);
}

function wpsmm_group_badge(object $site): void
{
    $group = $site->group ?? null;
    $label = (string) ($site->group_label ?? 'Chưa phân nhóm');
    $color = $group && !empty($group->color) ? (string) $group->color : '#94a3b8';
    $style = '--wpsmm-group-color:' . esc_attr($color) . ';';
    echo '<span class="wpsmm-group-badge" style="' . esc_attr($style) . '"><i></i>' . esc_html($label) . '</span>';
}

function wpsmm_render_pagination(int $page, int $pages, callable $pageUrl, string $label = 'Phân trang'): void
{
    if ($pages <= 1) {
        return;
    }
    $paginationPages = wpsmm_pagination_pages($page, $pages);
    echo '<nav class="wpsmm-pagination" aria-label="' . esc_attr($label) . '">';
    if ($page > 1) {
        echo '<a href="' . esc_url($pageUrl($page - 1)) . '" aria-label="Trang trước" title="Trang trước">&lsaquo;</a>';
    } else {
        echo '<span class="is-disabled" aria-hidden="true">&lsaquo;</span>';
    }
    $previousPage = 0;
    foreach ($paginationPages as $paginationPage) {
        if ($previousPage && $paginationPage > $previousPage + 1) {
            echo '<span class="wpsmm-pagination-gap" aria-hidden="true">...</span>';
        }
        if ($paginationPage === $page) {
            echo '<span class="is-active" aria-current="page">' . esc_html((string) $paginationPage) . '</span>';
        } else {
            echo '<a href="' . esc_url($pageUrl($paginationPage)) . '">' . esc_html((string) $paginationPage) . '</a>';
        }
        $previousPage = $paginationPage;
    }
    if ($page < $pages) {
        echo '<a href="' . esc_url($pageUrl($page + 1)) . '" aria-label="Trang sau" title="Trang sau">&rsaquo;</a>';
    } else {
        echo '<span class="is-disabled" aria-hidden="true">&rsaquo;</span>';
    }
    echo '</nav>';
}
