<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/partials.php';

$groups = $groups ?? [];
$group_id = (int) ($group_id ?? 0);
$queryBase = array_filter([
    'page' => 'wpsmm-sites',
    'filter' => $filter !== 'all' ? $filter : '',
    'group_id' => $group_id !== 0 ? $group_id : '',
    'search' => $search,
    'per_page' => $per,
]);
$pageUrl = static function (int $target) use ($queryBase): string {
    return add_query_arg(array_merge($queryBase, ['paged' => $target]), admin_url('admin.php'));
};
$filterUrl = static function (string $target) use ($queryBase): string {
    $args = $queryBase;
    unset($args['paged']);
    if ($target === 'all') {
        unset($args['filter']);
    } else {
        $args['filter'] = $target;
    }
    return add_query_arg($args, admin_url('admin.php'));
};
?>
<div class="wrap wpsmm-wrap wpsmm-sites-page">
    <header class="wpsmm-list-header">
        <div><h1>Quản lý website</h1><p>Theo dõi trạng thái và quản lý các website trong một nơi.</p></div>
        <div class="wpsmm-toolbar-actions">
            <a class="button wpsmm-guide-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-groups')); ?>"><span class="dashicons dashicons-category"></span>Quản lý nhóm</a>
            <a class="button button-primary wpsmm-primary-action" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=new')); ?>"><span class="dashicons dashicons-plus-alt2"></span>Thêm website</a>
        </div>
    </header>

    <section class="wpsmm-sites-summary">
        <article><span class="dashicons dashicons-admin-site-alt3 blue"></span><div><small>Tổng website</small><strong><?php echo esc_html($summary['total']); ?></strong></div></article>
        <article><span class="dashicons dashicons-yes-alt green"></span><div><small>Đang hoạt động</small><strong><?php echo esc_html($summary['online']); ?></strong></div></article>
        <article><span class="dashicons dashicons-warning orange"></span><div><small>Gặp sự cố</small><strong><?php echo esc_html($summary['warning']); ?></strong></div></article>
        <article><span class="dashicons dashicons-dismiss red"></span><div><small>Ngừng hoạt động</small><strong><?php echo esc_html($summary['offline']); ?></strong></div></article>
    </section>

    <section class="wpsmm-panel wpsmm-sites-list-panel">
        <header class="wpsmm-sites-toolbar">
            <div><h2>Danh sách website</h2><p>Tìm kiếm, lọc và thao tác nhanh trên từng website.</p></div>
            <div class="wpsmm-sites-filters">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="wpsmm-group-filter">
                    <input type="hidden" name="page" value="wpsmm-sites">
                    <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>"><?php endif; ?>
                    <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo esc_attr($search); ?>"><?php endif; ?>
                    <input type="hidden" name="per_page" value="<?php echo esc_attr($per); ?>">
                    <label class="screen-reader-text" for="wpsmm-filter-group">Lọc theo nhóm</label>
                    <select name="group_id" id="wpsmm-filter-group" onchange="this.form.submit()">
                        <option value="0" <?php selected($group_id, 0); ?>>Tất cả nhóm</option>
                        <option value="-1" <?php selected($group_id, -1); ?>>Chưa phân nhóm</option>
                        <?php foreach ($groups as $group): ?>
                            <option value="<?php echo esc_attr($group->id); ?>" <?php selected($group_id, (int) $group->id); ?>><?php echo esc_html($group->name); ?> (<?php echo esc_html((int) ($group->site_count ?? 0)); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="wpsmm-search">
                    <input type="hidden" name="page" value="wpsmm-sites">
                    <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>"><?php endif; ?>
                    <?php if ($group_id !== 0): ?><input type="hidden" name="group_id" value="<?php echo esc_attr($group_id); ?>"><?php endif; ?>
                    <input type="hidden" name="per_page" value="<?php echo esc_attr($per); ?>">
                    <span class="dashicons dashicons-search"></span>
                    <input type="search" name="search" value="<?php echo esc_attr($search); ?>" placeholder="Tìm kiếm website...">
                </form>
            </div>
        </header>
        <nav class="wpsmm-site-tabs" id="wpsmm-manage-tabs" aria-label="Lọc website">
            <a class="<?php echo $filter === 'all' ? 'is-active' : ''; ?>" href="<?php echo esc_url($filterUrl('all')); ?>">Tất cả <b><?php echo esc_html($counts['all']); ?></b></a>
            <a class="<?php echo $filter === 'online' ? 'is-active' : ''; ?>" href="<?php echo esc_url($filterUrl('online')); ?>">Đang hoạt động <b><?php echo esc_html($counts['online']); ?></b></a>
            <a class="<?php echo $filter === 'warning' ? 'is-active' : ''; ?>" href="<?php echo esc_url($filterUrl('warning')); ?>">Gặp sự cố <b><?php echo esc_html($counts['warning']); ?></b></a>
            <a class="<?php echo $filter === 'offline' ? 'is-active' : ''; ?>" href="<?php echo esc_url($filterUrl('offline')); ?>">Ngừng hoạt động <b><?php echo esc_html($counts['offline']); ?></b></a>
        </nav>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="wpsmm_bulk_sites"><?php wp_nonce_field('wpsmm_bulk_sites'); ?>
            <p class="wpsmm-bulk-actions">
                <select name="bulk_action" id="wpsmm-bulk-action" required>
                    <option value="">Thao tác hàng loạt</option>
                    <option value="check">Kiểm tra ngay</option>
                    <option value="pause">Tạm dừng giám sát</option>
                    <option value="resume">Bật giám sát</option>
                    <option value="assign_group">Gán vào nhóm</option>
                    <option value="delete">Xóa website</option>
                </select>
                <select name="bulk_group_id" id="wpsmm-bulk-group" class="wpsmm-bulk-group-select" hidden>
                    <option value="0">Bỏ nhóm (chưa phân nhóm)</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?php echo esc_attr($group->id); ?>"><?php echo esc_html($group->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="button">Áp dụng</button>
            </p>
        <div class="wpsmm-table-scroll">
            <table class="widefat wpsmm-dashboard-table" id="wpsmm-manage-table">
                <thead><tr><th><input type="checkbox" onclick="document.querySelectorAll('input[name=&quot;site_ids[]&quot;]').forEach((item) => item.checked = this.checked)"></th><th>Website</th><th>IP</th><th>Nhóm</th><th>Trạng thái</th><th>Agent</th><th>Uptime</th><th>Phản hồi</th><th>SSL</th><th>Lần kiểm tra cuối</th><th>Thao tác</th></tr></thead>
                <tbody>
                <?php if (!$sites): ?>
                    <tr><td colspan="11"><div class="wpsmm-empty-state">Không tìm thấy website phù hợp.</div></td></tr>
                <?php endif; ?>
                <?php foreach ($sites as $site): ?>
                    <?php
                    $tone = empty($site->monitor_enabled) ? 'muted' : wpsmm_status_tone((string) $site->status);
                    $site_ips = wpsmm_resolve_site_ips((string) $site->url);
                    ?>
                    <tr>
                        <td><input type="checkbox" name="site_ids[]" value="<?php echo esc_attr($site->id); ?>"></td>
                        <td><div class="wpsmm-site-cell"><span class="dashicons dashicons-admin-site-alt3"></span><div><strong><a href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=view&id=' . $site->id)); ?>"><?php echo esc_html($site->name); ?></a></strong><small><?php echo esc_html($site->url); ?></small></div></div></td>
                        <td><code class="wpsmm-ip-cell"><?php echo esc_html(wpsmm_format_ips($site_ips)); ?></code></td>
                        <td><?php wpsmm_group_badge($site); ?></td>
                        <td><span class="wpsmm-status-pill <?php echo empty($site->monitor_enabled) ? 'muted' : esc_attr($tone); ?>"><i></i><?php echo empty($site->monitor_enabled) ? 'Tạm dừng' : esc_html(wpsmm_status_label($site->status)); ?></span></td>
                        <td><span class="wpsmm-status-pill <?php echo ($site->agent_status ?? '') === 'online' ? 'success' : 'muted'; ?>"><i></i><?php echo esc_html(($site->agent_status ?? '') === 'online' ? 'Trực tuyến' : 'Ngoại tuyến'); ?></span></td>
                        <td><strong><?php echo esc_html(number_format((float) $site->uptime_percent, 2)); ?>%</strong></td>
                        <td><strong class="<?php echo (float) $site->response_time > 2 ? 'wpsmm-text-danger' : 'wpsmm-text-success'; ?>"><?php echo $site->response_time ? esc_html(round((float) $site->response_time * 1000) . ' ms') : '-'; ?></strong></td>
                        <td><?php if ($site->ssl_days_left === null): ?><span class="wpsmm-ssl muted">Chưa có dữ liệu</span><?php else: ?><span class="wpsmm-ssl <?php echo (int) $site->ssl_days_left <= 14 ? 'danger' : 'success'; ?>"><span class="dashicons dashicons-lock"></span><?php echo (int) $site->ssl_days_left < 0 ? 'Hết hạn' : 'Còn ' . esc_html($site->ssl_days_left) . ' ngày'; ?></span><?php endif; ?></td>
                        <td><small><?php echo esc_html($site->last_checked ?: 'Chưa kiểm tra'); ?></small></td>
                        <td><div class="wpsmm-row-actions"><a class="wpsmm-icon-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=view&id=' . $site->id)); ?>" title="Xem chi tiết"><span class="dashicons dashicons-visibility"></span></a><button type="button" class="wpsmm-icon-button wpsmm-check-site" data-id="<?php echo esc_attr($site->id); ?>" title="Kiểm tra ngay"><span class="dashicons dashicons-update"></span></button><a class="wpsmm-icon-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&id=' . $site->id)); ?>" title="Sửa website"><span class="dashicons dashicons-edit"></span></a><a class="wpsmm-icon-button wpsmm-delete-action" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsmm_delete_site&id=' . $site->id), 'wpsmm_delete_site_' . $site->id)); ?>" title="Xóa website"><span class="dashicons dashicons-trash"></span></a></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </form>
        <footer class="wpsmm-table-footer">
            <span id="wpsmm-manage-count">Hiển thị <?php echo esc_html(count($sites)); ?> / <?php echo esc_html(number_format_i18n($total)); ?> website · Trang <?php echo esc_html($page); ?>/<?php echo esc_html($pages); ?></span>
            <?php wpsmm_render_pagination($page, $pages, $pageUrl, 'Phân trang danh sách website'); ?>
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="wpsmm-sites">
                <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>"><?php endif; ?>
                <?php if ($group_id !== 0): ?><input type="hidden" name="group_id" value="<?php echo esc_attr($group_id); ?>"><?php endif; ?>
                <?php if ($search !== ''): ?><input type="hidden" name="search" value="<?php echo esc_attr($search); ?>"><?php endif; ?>
                <select name="per_page" onchange="this.form.submit()">
                    <option value="10" <?php selected($per, 10); ?>>10 / trang</option>
                    <option value="20" <?php selected($per, 20); ?>>20 / trang</option>
                    <option value="50" <?php selected($per, 50); ?>>50 / trang</option>
                    <option value="100" <?php selected($per, 100); ?>>100 / trang</option>
                </select>
            </form>
        </footer>
    </section>
</div>