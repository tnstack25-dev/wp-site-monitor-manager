<?php
if (!defined('ABSPATH')) {
    exit;
}
$pages = max(1, (int) ceil($total / $per));
$queryBase = array_filter(['page' => 'wpsmm-logs', 'site_id' => $filters['site_id'], 'status' => $filters['status'], 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'search' => $filters['search'], 'per_page' => $per]);
$pageUrl = static function (int $target) use ($queryBase): string {
    return add_query_arg(array_merge($queryBase, ['paged' => $target]), admin_url('admin.php'));
};
$paginationPages = $pages <= 7 ? range(1, $pages) : array_values(array_unique(array_filter([1, $page - 2, $page - 1, $page, $page + 1, $page + 2, $pages], static function (int $item) use ($pages): bool {
    return $item >= 1 && $item <= $pages;
})));
sort($paginationPages);
$statusLabels = ['online' => 'Thành công', 'redirect' => 'Chuyển hướng', 'offline' => 'Mất kết nối', 'server_error' => 'HTTP 5xx', 'client_error' => 'HTTP 4xx', 'not_found' => 'HTTP 404', 'title_changed' => 'Tiêu đề thay đổi', 'suspicious' => 'Cảnh báo nội dung', 'ssl_expiring' => 'SSL sắp hết hạn', 'ssl_error' => 'SSL lỗi', 'unknown' => 'Chưa xác định'];
?>
<div class="wrap wpsmm-wrap wpsmm-logs-page">
    <header class="wpsmm-list-header">
        <div><h1>Nhật ký</h1><p>Xem nhật ký hoạt động và sự kiện giám sát website.</p></div>
        <a class="button wpsmm-guide-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm')); ?>"><span class="dashicons dashicons-arrow-left-alt2"></span>Quay lại</a>
    </header>

    <section class="wpsmm-panel wpsmm-log-panel">
        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="wpsmm-log-filters">
            <input type="hidden" name="page" value="wpsmm-logs">
            <label><span class="dashicons dashicons-admin-site-alt3"></span><select name="site_id"><option value="0">Tất cả website</option><?php foreach ($sites as $site): ?><option value="<?php echo esc_attr($site->id); ?>" <?php selected($filters['site_id'], (int) $site->id); ?>><?php echo esc_html($site->name); ?></option><?php endforeach; ?></select></label>
            <label><span class="dashicons dashicons-calendar-alt"></span><input type="date" name="date_from" value="<?php echo esc_attr($filters['date_from']); ?>" aria-label="Từ ngày"></label>
            <label><span class="dashicons dashicons-calendar-alt"></span><input type="date" name="date_to" value="<?php echo esc_attr($filters['date_to']); ?>" aria-label="Đến ngày"></label>
            <label><span class="dashicons dashicons-filter"></span><select name="status"><option value="">Tất cả trạng thái</option><?php foreach ($statusLabels as $value => $text): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($filters['status'], $value); ?>><?php echo esc_html($text); ?></option><?php endforeach; ?></select></label>
            <label class="wpsmm-log-search"><span class="dashicons dashicons-search"></span><input type="search" name="search" value="<?php echo esc_attr($filters['search']); ?>" placeholder="Tìm kiếm nhật ký..."></label>
            <button class="button wpsmm-filter-button"><span class="dashicons dashicons-filter"></span>Bộ lọc</button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-logs')); ?>">Xóa bộ lọc</a>
        </form>

        <div class="wpsmm-log-summary">
            <span>Tổng <b><?php echo esc_html(number_format_i18n($total)); ?></b> nhật ký</span>
            <span class="info"><i></i><?php echo esc_html(number_format_i18n($stats['online'])); ?> Thành công</span>
            <span class="warning"><i></i><?php echo esc_html(number_format_i18n($stats['warning'])); ?> Cảnh báo</span>
            <span class="danger"><i></i><?php echo esc_html(number_format_i18n($stats['error'])); ?> Lỗi</span>
        </div>

        <div class="wpsmm-table-scroll">
            <table class="widefat wpsmm-log-table">
                <thead><tr><th>Thời gian</th><th>Mức độ</th><th>Loại nhật ký</th><th>Thông điệp</th><th>Website</th><th>Phản hồi</th><th>Chi tiết</th></tr></thead>
                <tbody>
                <?php if (!$logs): ?><tr><td colspan="7"><div class="wpsmm-empty-state">Không có nhật ký phù hợp với bộ lọc.</div></td></tr><?php endif; ?>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $level = in_array($log->status, ['online', 'redirect'], true) ? 'info' : (in_array($log->status, ['offline', 'server_error', 'ssl_error'], true) ? 'danger' : 'warning');
                    $type = in_array($log->status, ['ssl_error', 'ssl_expiring'], true) ? 'Kiểm tra SSL' : (in_array($log->status, ['title_changed', 'suspicious'], true) ? 'Kiểm tra nội dung' : 'Kiểm tra HTTP');
                    ?>
                    <tr>
                        <td><?php echo esc_html($log->checked_at); ?></td>
                        <td><span class="wpsmm-log-level <?php echo esc_attr($level); ?>"><i></i><?php echo esc_html($level === 'info' ? 'Thông tin' : ($level === 'warning' ? 'Cảnh báo' : 'Lỗi')); ?></span></td>
                        <td><?php echo esc_html($type); ?></td>
                        <td><strong><?php echo esc_html($log->message ?: ($statusLabels[$log->status] ?? $log->status)); ?></strong><small><?php echo esc_html($statusLabels[$log->status] ?? $log->status); ?> · HTTP <?php echo esc_html($log->http_code); ?></small></td>
                        <td><strong><?php echo esc_html($log->site_name ?: '#' . $log->site_id); ?></strong><small><?php echo esc_html($log->site_url); ?></small></td>
                        <td><?php echo esc_html(round((float) $log->response_time * 1000)); ?> ms</td>
                        <td><button type="button" class="button button-small wpsmm-open-log" data-log="<?php echo esc_attr(wp_json_encode(['time' => $log->checked_at, 'site' => $log->site_name ?: '#' . $log->site_id, 'url' => $log->site_url, 'endpoint' => $log->endpoint_url ?? '', 'status' => $statusLabels[$log->status] ?? $log->status, 'http' => $log->http_code, 'response' => round((float) $log->response_time * 1000) . ' ms', 'message' => $log->message, 'technical' => $log->technical_details ?? ''])); ?>">Xem</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <footer class="wpsmm-table-footer">
            <span>Hiển thị <?php echo esc_html(count($logs)); ?> trong tổng số <?php echo esc_html(number_format_i18n($total)); ?> nhật ký · Trang <?php echo esc_html($page); ?>/<?php echo esc_html($pages); ?></span>
            <nav class="wpsmm-pagination" aria-label="Phân trang nhật ký">
                <?php if ($page > 1): ?><a href="<?php echo esc_url($pageUrl($page - 1)); ?>" aria-label="Trang trước" title="Trang trước">&lsaquo;</a><?php else: ?><span class="is-disabled" aria-hidden="true">&lsaquo;</span><?php endif; ?>
                <?php $previousPage = 0; foreach ($paginationPages as $paginationPage): ?>
                    <?php if ($previousPage && $paginationPage > $previousPage + 1): ?><span class="wpsmm-pagination-gap" aria-hidden="true">...</span><?php endif; ?>
                    <?php if ($paginationPage === $page): ?><span class="is-active" aria-current="page"><?php echo esc_html($paginationPage); ?></span><?php else: ?><a href="<?php echo esc_url($pageUrl($paginationPage)); ?>"><?php echo esc_html($paginationPage); ?></a><?php endif; ?>
                    <?php $previousPage = $paginationPage; ?>
                <?php endforeach; ?>
                <?php if ($page < $pages): ?><a href="<?php echo esc_url($pageUrl($page + 1)); ?>" aria-label="Trang sau" title="Trang sau">&rsaquo;</a><?php else: ?><span class="is-disabled" aria-hidden="true">&rsaquo;</span><?php endif; ?>
            </nav>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>"><input type="hidden" name="page" value="wpsmm-logs"><?php foreach ($queryBase as $key => $value): if ($key !== 'page' && $key !== 'per_page'): ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>"><?php endif; endforeach; ?><select name="per_page" onchange="this.form.submit()"><option value="10" <?php selected($per, 10); ?>>10 / trang</option><option value="20" <?php selected($per, 20); ?>>20 / trang</option><option value="50" <?php selected($per, 50); ?>>50 / trang</option></select></form>
        </footer>
    </section>

    <div class="wpsmm-log-modal" id="wpsmm-log-modal" aria-hidden="true">
        <div class="wpsmm-log-modal-backdrop" data-close-log></div>
        <article class="wpsmm-log-modal-panel"><button type="button" class="wpsmm-log-modal-close" data-close-log aria-label="Đóng">&times;</button><h2>Chi tiết nhật ký</h2><dl id="wpsmm-log-detail"></dl></article>
    </div>
</div>
