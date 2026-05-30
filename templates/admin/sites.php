<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/partials.php';
?>
<div class="wrap wpsmm-wrap wpsmm-sites-page">
    <header class="wpsmm-list-header">
        <div><h1>Quản lý website</h1><p>Theo dõi trạng thái và quản lý các website trong một nơi.</p></div>
        <a class="button button-primary wpsmm-primary-action" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=new')); ?>"><span class="dashicons dashicons-plus-alt2"></span>Thêm website</a>
    </header>

    <section class="wpsmm-sites-summary">
        <article><span class="dashicons dashicons-admin-site-alt3 blue"></span><div><small>Tổng website</small><strong><?php echo esc_html(count($sites)); ?></strong></div></article>
        <article><span class="dashicons dashicons-yes-alt green"></span><div><small>Đang hoạt động</small><strong><?php echo esc_html(count(array_filter($sites, static fn($site) => in_array($site->status, ['online', 'redirect'], true)))); ?></strong></div></article>
        <article><span class="dashicons dashicons-warning orange"></span><div><small>Gặp sự cố</small><strong><?php echo esc_html(count(array_filter($sites, static fn($site) => in_array($site->status, ['client_error', 'not_found', 'title_changed', 'suspicious', 'ssl_expiring'], true)))); ?></strong></div></article>
        <article><span class="dashicons dashicons-dismiss red"></span><div><small>Ngừng hoạt động</small><strong><?php echo esc_html(count(array_filter($sites, static fn($site) => in_array($site->status, ['offline', 'server_error', 'ssl_error'], true)))); ?></strong></div></article>
    </section>

    <section class="wpsmm-panel wpsmm-sites-list-panel">
        <header class="wpsmm-sites-toolbar"><div><h2>Danh sách website</h2><p>Tìm kiếm, lọc và thao tác nhanh trên từng website.</p></div><label class="wpsmm-search"><span class="dashicons dashicons-search"></span><input id="wpsmm-manage-search" type="search" placeholder="Tìm kiếm website..."></label></header>
        <nav class="wpsmm-site-tabs" id="wpsmm-manage-tabs"><button type="button" class="is-active" data-manage-filter="all">Tất cả <b><?php echo esc_html(count($sites)); ?></b></button><button type="button" data-manage-filter="online">Đang hoạt động</button><button type="button" data-manage-filter="warning">Gặp sự cố</button><button type="button" data-manage-filter="offline">Ngừng hoạt động</button></nav>
        <div class="wpsmm-table-scroll">
            <table class="widefat wpsmm-dashboard-table" id="wpsmm-manage-table">
                <thead><tr><th>Website</th><th>Nhóm</th><th>Trạng thái</th><th>Uptime</th><th>Phản hồi</th><th>SSL</th><th>Lần kiểm tra cuối</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php foreach ($sites as $site): ?>
                    <?php
                    $tone = in_array($site->status, ['online', 'redirect'], true) ? 'success' : (in_array($site->status, ['offline', 'server_error', 'ssl_error'], true) ? 'danger' : ($site->status === 'unknown' ? 'muted' : 'warning'));
                    $group = in_array($site->status, ['online', 'redirect'], true) ? 'online' : (in_array($site->status, ['offline', 'server_error', 'ssl_error'], true) ? 'offline' : 'warning');
                    ?>
                    <tr data-manage-row data-status-group="<?php echo esc_attr($group); ?>" data-search="<?php echo esc_attr(strtolower($site->name . ' ' . $site->url . ' ' . $site->group_name)); ?>">
                        <td><div class="wpsmm-site-cell"><span class="dashicons dashicons-admin-site-alt3"></span><div><strong><a href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=view&id=' . $site->id)); ?>"><?php echo esc_html($site->name); ?></a></strong><small><?php echo esc_html($site->url); ?></small></div></div></td>
                        <td><?php echo esc_html($site->group_name ?: 'Tất cả website'); ?></td>
                        <td><span class="wpsmm-status-pill <?php echo esc_attr($tone); ?>"><i></i><?php wpsmm_status_text($site->status); ?></span></td>
                        <td><strong><?php echo esc_html(number_format((float) $site->uptime_percent, 2)); ?>%</strong></td>
                        <td><strong class="<?php echo (float) $site->response_time > 2 ? 'wpsmm-text-danger' : 'wpsmm-text-success'; ?>"><?php echo $site->response_time ? esc_html(round((float) $site->response_time * 1000) . ' ms') : '-'; ?></strong></td>
                        <td><?php if ($site->ssl_days_left === null): ?><span class="wpsmm-ssl muted">Chưa có dữ liệu</span><?php else: ?><span class="wpsmm-ssl <?php echo (int) $site->ssl_days_left <= 14 ? 'danger' : 'success'; ?>"><span class="dashicons dashicons-lock"></span><?php echo (int) $site->ssl_days_left < 0 ? 'Hết hạn' : 'Còn ' . esc_html($site->ssl_days_left) . ' ngày'; ?></span><?php endif; ?></td>
                        <td><small><?php echo esc_html($site->last_checked ?: 'Chưa kiểm tra'); ?></small></td>
                        <td><div class="wpsmm-row-actions"><a class="wpsmm-icon-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=view&id=' . $site->id)); ?>" title="Xem chi tiết"><span class="dashicons dashicons-visibility"></span></a><button class="wpsmm-icon-button wpsmm-check-site" data-id="<?php echo esc_attr($site->id); ?>" title="Kiểm tra ngay"><span class="dashicons dashicons-update"></span></button><a class="wpsmm-icon-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&id=' . $site->id)); ?>" title="Sửa website"><span class="dashicons dashicons-edit"></span></a><a class="wpsmm-icon-button wpsmm-delete-action" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsmm_delete_site&id=' . $site->id), 'wpsmm_delete_site_' . $site->id)); ?>" title="Xóa website"><span class="dashicons dashicons-trash"></span></a></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <footer class="wpsmm-table-footer"><span id="wpsmm-manage-count"><?php echo esc_html(count($sites)); ?> website</span></footer>
    </section>
</div>
