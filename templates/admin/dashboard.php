<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wpsmm-wrap wpsmm-dashboard" data-wpsmm-page="dashboard">
    <section class="wpsmm-summary-grid" id="wpsmm-kpis"><?php for ($i = 0; $i < 6; $i++): ?><div class="wpsmm-skeleton"></div><?php endfor; ?></section>
    <section class="wpsmm-overview-grid">
        <article class="wpsmm-panel wpsmm-chart-panel">
            <header class="wpsmm-panel-head"><div><h2>Tình trạng hoạt động</h2><p>Uptime tổng hợp của các website</p></div><select id="wpsmm-chart-range" aria-label="Khoảng thời gian"><option value="24">24 giờ gần đây</option><option value="72">3 ngày gần đây</option><option value="168" selected>7 ngày gần đây</option></select></header>
            <div class="wpsmm-chart-area"><canvas id="wpsmm-uptime-chart"></canvas></div>
        </article>
        <article class="wpsmm-panel wpsmm-status-panel">
            <header class="wpsmm-panel-head"><div><h2>Phân bổ trạng thái</h2><p>Trạng thái hiện tại</p></div></header>
            <div class="wpsmm-status-layout"><div class="wpsmm-donut" id="wpsmm-status-donut"><div><strong>0</strong><span>Tổng</span></div></div><div class="wpsmm-status-legend" id="wpsmm-status-legend"></div></div>
        </article>
        <article class="wpsmm-panel wpsmm-incidents-panel">
            <header class="wpsmm-panel-head"><div><h2>Sự cố gần đây</h2><p>Cần ưu tiên xử lý</p></div><a href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-logs')); ?>">Xem tất cả</a></header>
            <div class="wpsmm-incidents" id="wpsmm-incidents"><div class="wpsmm-empty-state">Đang tải dữ liệu...</div></div>
        </article>
    </section>
    <section class="wpsmm-panel wpsmm-sites-panel">
        <header class="wpsmm-sites-toolbar"><div><h2>Danh sách website</h2><p>Quản lý và theo dõi trạng thái theo thời gian thực</p></div><div class="wpsmm-toolbar-actions"><label class="wpsmm-search"><span class="dashicons dashicons-search"></span><input id="wpsmm-site-search" type="search" placeholder="Tìm kiếm website..."></label><a class="button button-primary wpsmm-add-site" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&action=new')); ?>"><span class="dashicons dashicons-plus-alt2"></span>Thêm website</a></div></header>
        <nav class="wpsmm-site-tabs" id="wpsmm-site-tabs" aria-label="Lọc website"></nav>
        <div class="wpsmm-table-scroll"><table class="widefat wpsmm-dashboard-table" id="wpsmm-sites-table"><thead><tr><th>Website</th><th>Trạng thái</th><th>Uptime</th><th>Thời gian phản hồi</th><th>SSL</th><th>Lần kiểm tra cuối</th><th>Thao tác</th></tr></thead><tbody><tr><td colspan="7"><div class="wpsmm-empty-state">Đang tải danh sách...</div></td></tr></tbody></table></div>
        <footer class="wpsmm-table-footer"><span id="wpsmm-table-count">0 website</span><div class="wpsmm-pagination" id="wpsmm-site-pagination"></div></footer>
    </section>
</div>
