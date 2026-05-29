<?php if (!defined('ABSPATH'))
  exit;
require_once __DIR__ . '/partials.php'; ?>
<div class="wrap wpsmm-wrap">
  <section class="wpsmm-page-title">
    <h1>LOGS</h1>
    <p>Logs <?php echo esc_html(get_option('wpsmm_log_retention_days', 7)); ?> ngày gần nhất</p>
  </section>
  <section class="wpsmm-saas-card">
    <div class="wpsmm-table-scroll">
      <table class="widefat striped wpsmm-table">
        <thead>
          <tr>
            <th>Time</th>
            <th>Website</th>
            <th>Status</th>
            <th>HTTP</th>
            <th>Response</th>
            <th>Message</th>
          </tr>
        </thead>
        <tbody><?php foreach ($logs as $l): ?>
            <tr>
              <td><?php echo esc_html($l->checked_at); ?></td>
              <td>
                <strong><?php echo esc_html($l->site_name ?: '#' . $l->site_id); ?></strong><br><small><?php echo esc_html($l->site_url); ?></small>
              </td>
              <td><?php wpsmm_status_badge($l->status); ?></td>
              <td><?php echo esc_html($l->http_code); ?></td>
              <td><?php echo esc_html($l->response_time); ?>s</td>
              <td><?php echo esc_html($l->message); ?></td>
            </tr><?php endforeach; ?>
        </tbody>
      </table>
    </div><?php $pages = max(1, ceil($total / $per));
    if ($pages > 1): ?>
      <div class="wpsmm-pagination"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?php echo $i === $page ? 'is-active' : ''; ?>"
            href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-logs&paged=' . $i)); ?>"><?php echo esc_html($i); ?></a><?php endfor; ?>
      </div><?php endif; ?>
  </section>
</div>