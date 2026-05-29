<?php if (!defined('ABSPATH'))
    exit;
require_once __DIR__ . '/partials.php'; ?>
<div class="wrap wpsmm-wrap" data-wpsmm-page="servers">
    <section class="wpsmm-page-title wpsmm-section-header">
        <div>
            <span class="wpsmm-eyebrow-light">Infrastructure</span>
            <h1>QUẢN LÝ SERVER</h1>
            <p>Khai báo nhiều VPS/server để gán website, phục vụ backup qua SSH, kiểm tra kết nối và mở rộng giám sát
                tài nguyên sau này.</p>
        </div>
    </section>

    <section class="wpsmm-saas-card wpsmm-form-card">
        <div class="wpsmm-card-head">
            <div>
                <h2>Thêm server/VPS</h2>
                <p>Ưu tiên dùng SSH key path thay vì password để an toàn hơn khi backup nhiều website.</p>
            </div>
        </div>
        <form class="wpsmm-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wpsmm_save_server'); ?>
            <input type="hidden" name="action" value="wpsmm_save_server">
            <div class="wpsmm-row"><label>Tên VPS<input name="name" required
                        placeholder="VPS Production 01"></label><label>Host/IP<input name="host" required
                        placeholder="192.168.1.10 hoặc server.example.com"></label></div>
            <div class="wpsmm-row"><label>Port<input type="number" name="port" value="22"></label><label>Username<input
                        name="username" required placeholder="root / deploy / user"></label></div>
            <div class="wpsmm-row"><label>Auth type<select name="auth_type">
                        <option value="key_path">SSH key path</option>
                        <option value="password">Password</option>
                    </select></label><label>Backup path trên VPS<input name="backup_path"
                        value="/tmp/wpsmm-backups"></label></div>
            <label>SSH key path<input name="key_path" placeholder="/home/user/.ssh/id_rsa"></label>
            <label>Password SSH<?php wpsmm_secret_input('password'); ?></label>
            <button class="button button-primary button-hero">Lưu VPS</button>
        </form>
    </section>

    <section class="wpsmm-saas-card">
        <div class="wpsmm-card-head">
            <div>
                <h2>Danh sách server/VPS</h2>
                <p>Danh sách được đặt bên dưới để phần nhập liệu thoáng hơn và dễ thao tác trên màn hình nhỏ.</p>
            </div>
        </div>
        <div class="wpsmm-table-scroll">
            <table class="widefat striped wpsmm-table">
                <thead>
                    <tr>
                        <th>Tên server</th>
                        <th>Host</th>
                        <th>User</th>
                        <th>Auth</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servers)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="wpsmm-empty">Chưa có server/VPS nào được cấu hình.</div>
                            </td>
                        </tr><?php endif; ?>
                    <?php foreach ($servers as $s): ?>
                        <tr>
                            <td><strong><?php echo esc_html($s->name); ?></strong></td>
                            <td><?php echo esc_html($s->host . ':' . $s->port); ?></td>
                            <td><?php echo esc_html($s->username); ?></td>
                            <td><?php echo esc_html($s->auth_type); ?></td>
                            <td><button type="button" class="button wpsmm-test-server"
                                    data-id="<?php echo esc_attr($s->id); ?>">Test</button> <a
                                    class="button button-link-delete"
                                    href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsmm_delete_server&id=' . $s->id), 'wpsmm_delete_server_' . $s->id)); ?>">Xóa</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="description">SSH command thực thi thật cần PHP extension <code>ssh2</code>. Nếu chưa có, plugin vẫn
            lưu cấu hình và kiểm tra TCP port.</p>
    </section>
</div>