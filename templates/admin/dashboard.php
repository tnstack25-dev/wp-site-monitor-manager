<?php if (!defined('ABSPATH'))
    exit;
require_once __DIR__ . '/partials.php'; ?>
<div class="wrap wpsmm-wrap" data-wpsmm-page="dashboard">
    <section class="wpsmm-hero wpsmm-saas-card">
        <div><span class="wpsmm-eyebrow">SaaS Monitor Dashboard</span>
            <h1>TRUNG TÂM QUẢN LÝ WEBSITE</h1>
            <p>Dashboard thời gian thực cho uptime, downtime, HTTP status, SSL, response time, malware scan, backup và VPS/server.</p>
        </div>
        <div class="wpsmm-hero-actions">
            <a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites')); ?>">+ Thêm website</a>
            <a class="button" style="color: #fff; border-color: #fff;" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-backup')); ?>">Backup</a></div>
    </section>
    <div class="wpsmm-kpi-grid" id="wpsmm-kpis">
        <div class="wpsmm-skeleton"></div>
        <div class="wpsmm-skeleton"></div>
        <div class="wpsmm-skeleton"></div>
        <div class="wpsmm-skeleton"></div>
    </div>
    <div class="wpsmm-grid-2">
        <section class="wpsmm-saas-card">
            <div class="wpsmm-card-head">
                <h2>RESPONSE TIME REALTIME</h2><span id="wpsmm-live-state">Connecting...</span>
            </div><canvas id="wpsmm-response-chart" height="120"></canvas>
        </section>
        <section class="wpsmm-saas-card">
            <div class="wpsmm-card-head">
                <h2>UPTIME REALTIME</h2><span>24h</span>
            </div><canvas id="wpsmm-uptime-chart" height="120"></canvas>
        </section>
    </div>
    <section class="wpsmm-saas-card">
        <div class="wpsmm-card-head">
            <h2>WEBSITE HIỆN TẠI</h2><a href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-logs')); ?>">Xem
                logs</a>
        </div>
        <div class="wpsmm-table-scroll">
            <table class="widefat striped wpsmm-table" id="wpsmm-sites-table">
                <thead>
                    <tr>
                        <th>Website</th>
                        <th>Status</th>
                        <th>HTTP</th>
                        <th>Response</th>
                        <th>Uptime</th>
                        <th>Health</th>
                        <th>Last check</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </section>
</div>