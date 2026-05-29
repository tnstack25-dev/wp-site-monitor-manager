<?php if (!defined('ABSPATH'))
    exit;
require_once __DIR__ . '/partials.php'; ?>
<div class="wrap wpsmm-wrap" data-wpsmm-page="backup">
    <section class="wpsmm-page-title wpsmm-section-header">
        <div>
            <span class="wpsmm-eyebrow-light">Backup Center</span>
            <h1>BACKUP WEBSITE</h1>
            <p>Chọn một hoặc nhiều website để đưa vào hàng đợi backup nền.</p>
        </div>
    </section>

    <section class="wpsmm-action-panel wpsmm-backup-panel">
        <div class="wpsmm-action-copy">
            <h2>Tạo backup mới</h2>
            <p>Backup chạy nền để hạn chế làm chậm trang quản trị. Bạn có thể rời trang này, hệ thống vẫn tiếp tục xử lý
                theo hàng đợi.</p>
        </div>
        <form class="wpsmm-inline-backup-form" method="post"
            action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wpsmm_backup_now'); ?>
            <input type="hidden" name="action" value="wpsmm_backup_now">
            <div class="wpsmm-picker wpsmm-picker-wide">
                <button type="button" class="wpsmm-picker-toggle">Chọn website cần backup <b>⌄</b></button>
                <div class="wpsmm-picker-menu">
                    <input class="wpsmm-picker-search" placeholder="Tìm theo tên hoặc URL website...">
                    <div class="wpsmm-picker-actions">
                        <button type="button" class="button wpsmm-select-all">Chọn tất cả</button>
                        <button type="button" class="button wpsmm-clear-all">Bỏ chọn</button>
                    </div>
                    <div class="wpsmm-picker-options">
                        <?php foreach ($sites as $s): ?>
                            <label class="wpsmm-picker-option">
                                <input type="checkbox" name="site_ids[]" value="<?php echo esc_attr($s->id); ?>">
                                <span><strong><?php echo esc_html($s->name); ?></strong><small><?php echo esc_html($s->url); ?></small></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <button class="button button-primary button-hero" style="line-height: unset;">Backup Now</button>
        </form>
    </section>

    <section class="wpsmm-saas-card">
        <div class="wpsmm-card-head">
            <div>
                <h2>Danh sách bản backup</h2>
                <p>Theo dõi trạng thái, tiến trình, dung lượng và tải/xóa file backup local.</p>
            </div>
        </div>
        <div class="wpsmm-table-scroll">
            <table class="widefat striped wpsmm-table">
                <thead>
                    <tr>
                        <th>Website</th>
                        <th>File backup</th>
                        <th>Trạng thái</th>
                        <th>Tiến trình</th>
                        <th>Dung lượng</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="wpsmm-empty">Chưa có bản backup nào. Hãy chọn website và bấm Backup Now để tạo
                                    bản đầu tiên.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td><strong><?php echo esc_html($b->site_name ?: '#' . $b->site_id); ?></strong></td>
                            <td><strong><?php echo esc_html($b->file_name); ?></strong><br><small><?php echo esc_html($b->message); ?></small>
                            </td>
                            <td><?php wpsmm_status_badge($b->status); ?></td>
                            <td>
                                <div class="wpsmm-progress"><span
                                        style="width:<?php echo esc_attr((int) $b->progress); ?>%"></span></div>
                                <small><?php echo esc_html((int) $b->progress); ?>%</small>
                            </td>
                            <td><?php echo esc_html(size_format((int) $b->file_size)); ?></td>
                            <td>
                                <?php if (!empty($b->file_path) && is_file($b->file_path) && \WPSMM\Services\SecurityService::backupPathAllowed((string) $b->file_path)): ?>
                                    <a class="button"
                                        href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsmm_download_backup&id=' . $b->id), 'wpsmm_download_backup_' . $b->id)); ?>">Download</a>
                                    <a class="button button-link-delete"
                                        href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsmm_delete_backup&id=' . $b->id), 'wpsmm_delete_backup_' . $b->id)); ?>">Xóa
                                        local</a>
                                <?php else: ?>
                                    <span class="wpsmm-muted">Không có file local</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
