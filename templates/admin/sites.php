<?php if (!defined('ABSPATH'))
  exit;
require_once __DIR__ . '/partials.php';
$edit = $edit ?? null; ?>
<div class="wrap wpsmm-wrap">
  <section class="wpsmm-page-title">
    <h1>Websites</h1>
    <p>Thêm website, gán server/VPS, database và thông tin backup.</p>
  </section>
  <div class="wpsmm-grid-2">
    <section class="wpsmm-saas-card">
      <h2><?php echo $edit ? 'Cập nhật website' : 'Thêm website mới'; ?></h2>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wpsmm-form">
        <?php wp_nonce_field('wpsmm_save_site'); ?><input type="hidden" name="action" value="wpsmm_save_site"><input
          type="hidden" name="id" value="<?php echo esc_attr($edit->id ?? 0); ?>">
        <label>Tên website<input name="name" value="<?php echo esc_attr($edit->name ?? ''); ?>"
            required></label><label>URL<input name="url" value="<?php echo esc_attr($edit->url ?? ''); ?>"
            placeholder="https://domain.com" required></label><label>Nhóm<input name="group_name"
            value="<?php echo esc_attr($edit->group_name ?? ''); ?>" placeholder="Money site, khách hàng A..."></label>
        <div class="wpsmm-row"><label>Expected HTTP<input type="number" name="expected_status"
              value="<?php echo esc_attr($edit->expected_status ?? 200); ?>"></label><label>Expected title<input
              name="expected_title" value="<?php echo esc_attr($edit->expected_title ?? ''); ?>"></label></div>
        <label>Server/VPS<select name="server_id">
            <option value="0">Không gán</option><?php foreach ($servers as $s): ?>
              <option value="<?php echo esc_attr($s->id); ?>" <?php selected((int) ($edit->server_id ?? 0), (int) $s->id); ?>>
                <?php echo esc_html($s->name . ' - ' . $s->host); ?></option><?php endforeach; ?>
          </select></label><label>Remote path<input name="remote_path"
            value="<?php echo esc_attr($edit->remote_path ?? ''); ?>"
            placeholder="/home/user/domains/domain.com/public_html"></label>
        <div class="wpsmm-row"><label>DB name<input name="db_name"
              value="<?php echo esc_attr($edit->db_name ?? ''); ?>"></label><label>DB user<input name="db_user"
              value="<?php echo esc_attr($edit->db_user ?? ''); ?>"></label></div><label>DB
          password<?php wpsmm_secret_input('db_pass', $edit->db_pass ?? ''); ?></label><label>Backup Secret<div
            class="wpsmm-secret-row"><?php wpsmm_secret_input('backup_secret', $edit->backup_secret ?? ''); ?><button
              type="button" class="button wpsmm-generate-secret">Tạo secret</button></div></label><button
          class="button button-primary button-hero">Lưu website</button>
      </form>
    </section>
    <section class="wpsmm-saas-card">
      <h2>Danh sách website</h2>
      <div class="wpsmm-table-scroll">
        <table class="widefat striped wpsmm-table">
          <thead>
            <tr>
              <th>Website</th>
              <th>Status</th>
              <th>Server</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody><?php foreach ($sites as $site): ?>
              <tr>
                <td>
                  <strong><?php echo esc_html($site->name); ?></strong><br><small><?php echo esc_html($site->url); ?></small>
                </td>
                <td><?php wpsmm_status_badge($site->status); ?></td>
                <td><?php echo esc_html($site->server_id ? '#' . $site->server_id : '—'); ?></td>
                <td><a class="button"
                    href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites&id=' . $site->id)); ?>">Sửa</a> <a
                    class="button button-link-delete"
                    href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsmm_delete_site&id=' . $site->id), 'wpsmm_delete_site_' . $site->id)); ?>">Xóa</a>
                </td>
              </tr><?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>