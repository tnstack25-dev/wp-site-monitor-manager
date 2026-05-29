<?php if (!defined('ABSPATH'))
  exit;
function wpsmm_status_badge($status)
{
  $labels = ['online' => 'Online', 'redirect' => 'Redirect', 'offline' => 'Offline', 'server_error' => 'Server error', 'client_error' => 'Client error', 'not_found' => '404', 'title_changed' => 'Title changed', 'suspicious' => 'Nghi hack', 'ssl_expiring' => 'SSL sắp hết hạn', 'ssl_error' => 'SSL lỗi', 'unknown' => 'Unknown', 'queued' => 'Queued', 'running' => 'Running', 'success' => 'Success', 'failed' => 'Failed'];
  echo '<span class="wpsmm-badge wpsmm-badge-' . esc_attr($status) . '">' . esc_html($labels[$status] ?? $status) . '</span>';
}
function wpsmm_secret_input($name, $value = '', $placeholder = '')
{
  $placeholder = $placeholder ?: ($value ? 'Leave blank to keep existing secret' : '');
  echo '<div class="wpsmm-secret"><input type="password" name="' . esc_attr($name) . '" value="" placeholder="' . esc_attr($placeholder) . '" autocomplete="new-password"><button type="button" class="wpsmm-eye"><span class="dashicons dashicons-visibility"></span></button></div>';
}
