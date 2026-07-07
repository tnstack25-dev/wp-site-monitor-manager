<?php
if (!defined('ABSPATH')) {
    exit;
}
require_once __DIR__ . '/partials.php';
$edit = $edit ?? null;
$groups = $groups ?? [];
$ungrouped_count = (int) ($ungrouped_count ?? 0);
?>
<div class="wrap wpsmm-wrap wpsmm-groups-page">
    <header class="wpsmm-list-header">
        <div>
            <h1>Quản lý nhóm website</h1>
            <p>Tạo nhóm để phân loại website và lọc nhanh trên trang quản lý.</p>
        </div>
        <div class="wpsmm-toolbar-actions">
            <a class="button wpsmm-guide-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-sites')); ?>"><span class="dashicons dashicons-list-view"></span>Danh sách website</a>
        </div>
    </header>

    <div class="wpsmm-groups-layout">
        <section class="wpsmm-panel wpsmm-groups-form-panel">
            <header class="">
                <h2><?php echo $edit ? 'Cập nhật nhóm' : 'Thêm nhóm mới'; ?></h2>
                <p><?php echo $edit ? 'Chỉnh sửa tên, mô tả và màu hiển thị của nhóm.' : 'Tạo nhóm để gán website theo dự án, khách hàng hoặc mục đích sử dụng.'; ?></p>
            </header>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="wpsmm-group-form">
                <?php wp_nonce_field('wpsmm_save_group'); ?>
                <input type="hidden" name="action" value="wpsmm_save_group">
                <input type="hidden" name="id" value="<?php echo esc_attr($edit->id ?? 0); ?>">
                <label>Tên nhóm <b>*</b><small>Tên hiển thị trên danh sách website.</small><input name="name" maxlength="100" value="<?php echo esc_attr($edit->name ?? ''); ?>" placeholder="Ví dụ: Khách hàng A, Website nội bộ" required></label>
                <label>Mô tả<small>Tùy chọn — ghi chú ngắn về nhóm này.</small><input name="description" maxlength="255" value="<?php echo esc_attr($edit->description ?? ''); ?>" placeholder="Mô tả ngắn"></label>
                <div class="wpsmm-group-meta-row">
                    <label>Màu nhãn<small>Dùng để nhận biết nhanh trên bảng website.</small><input type="color" name="color" value="<?php echo esc_attr($edit->color ?? '#5b7cfa'); ?>"></label>
                    <label>Thứ tự<small>Số nhỏ hiển thị trước trong danh sách lọc.</small><input type="number" name="sort_order" min="0" max="9999" value="<?php echo esc_attr($edit->sort_order ?? 0); ?>"></label>
                </div>
                <footer class="wpsmm-group-form-footer">
                    <?php if ($edit): ?>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-groups')); ?>">Hủy</a>
                    <?php endif; ?>
                    <button class="button button-primary"><span class="dashicons dashicons-saved"></span><?php echo $edit ? 'Lưu nhóm' : 'Thêm nhóm'; ?></button>
                </footer>
            </form>
        </section>

        <section class="wpsmm-panel wpsmm-groups-list-panel">
            <header class="wpsmm-sites-toolbar">
                <div>
                    <h2>Danh sách nhóm</h2>
                    <p><?php echo esc_html(count($groups)); ?> nhóm · <?php echo esc_html($ungrouped_count); ?> website chưa phân nhóm</p>
                </div>
            </header>
            <div class="wpsmm-table-scroll">
                <table class="widefat wpsmm-dashboard-table">
                    <thead>
                        <tr>
                            <th>Nhóm</th>
                            <th>Website</th>
                            <th>Thứ tự</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$groups): ?>
                        <tr><td colspan="4"><div class="wpsmm-empty-state">Chưa có nhóm nào. Tạo nhóm đầu tiên ở bên trái.</div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td>
                                <div class="wpsmm-group-cell">
                                    <?php wpsmm_group_badge((object) ['group' => $group, 'group_label' => $group->name]); ?>
                                    <?php if (!empty($group->description)): ?>
                                        <small><?php echo esc_html($group->description); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><strong><?php echo esc_html((int) ($group->site_count ?? 0)); ?></strong></td>
                            <td><?php echo esc_html((int) $group->sort_order); ?></td>
                            <td>
                                <div class="wpsmm-row-actions">
                                    <a class="wpsmm-icon-button" href="<?php echo esc_url(add_query_arg(['page' => 'wpsmm-sites', 'group_id' => (int) $group->id], admin_url('admin.php'))); ?>" title="Xem website trong nhóm"><span class="dashicons dashicons-visibility"></span></a>
                                    <a class="wpsmm-icon-button" href="<?php echo esc_url(admin_url('admin.php?page=wpsmm-groups&id=' . (int) $group->id)); ?>" title="Sửa nhóm"><span class="dashicons dashicons-edit"></span></a>
                                    <a class="wpsmm-icon-button wpsmm-delete-action" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wpsmm_delete_group&id=' . (int) $group->id), 'wpsmm_delete_group_' . (int) $group->id)); ?>" title="Xóa nhóm"><span class="dashicons dashicons-trash"></span></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>