<?php
if (!defined('ABSPATH')) exit;

// Option accessor
function sitc_is_dev_mode(): bool {
    return get_option('sitc_dev_mode', '0') === '1';
}

// Handle toggle via admin-post
add_action('admin_post_sitc_toggle_dev_mode', function() {
    if (!current_user_can('manage_options')) {
        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
        exit;
    }
    if (empty($_POST['sitc_dev_mode_nonce']) || !wp_verify_nonce($_POST['sitc_dev_mode_nonce'], 'sitc_dev_mode_toggle')) {
        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
        exit;
    }
    $enable = !empty($_POST['enable']) ? '1' : '0';
    update_option('sitc_dev_mode', $enable);

    // Reuse refresh notice transient pattern so the existing admin_notices hook in refresh.php picks it up.
    if (is_user_logged_in()) {
        $key = 'sitc_refresh_notice_' . get_current_user_id();
        set_transient($key, [
            'type' => 'success',
            'message' => __('Dev-Mode aktualisiert.', 'sitc'),
            'details' => '',
        ], 60);
    }

    wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
    exit;
});

?>

