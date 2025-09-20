<?php
if (!defined('ABSPATH')) exit;

// Feature flag: gate modular ingredient pipeline (default OFF)
if (!defined('SITC_ING_V2')) {
    // Global activation: modular ingredient engine ON (with safe fallback in auto mode)
    define('SITC_ING_V2', true);
}

// Option accessor
function sitc_is_dev_mode(): bool {
    return get_option('sitc_dev_mode', '0') === '1';
}

// Feature flag: Safe Mode (disables risky features; forces fallback renderer)
function sitc_is_safe_mode(): bool {
    return get_option('_sitc_safe_mode', '0') === '1';
}

// Handle toggle via admin-post (admin only)
if (is_admin()) add_action('admin_post_sitc_toggle_dev_mode', function() {
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

// Handle safe mode toggle via admin-post (admin only)
if (is_admin()) add_action('admin_post_sitc_toggle_safe_mode', function() {
    if (!current_user_can('manage_options')) {
        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
        exit;
    }
    if (empty($_POST['sitc_safe_mode_nonce']) || !wp_verify_nonce($_POST['sitc_safe_mode_nonce'], 'sitc_safe_mode_toggle')) {
        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
        exit;
    }
    $enable = !empty($_POST['enable']) ? '1' : '0';
    update_option('_sitc_safe_mode', $enable);

    if (is_user_logged_in()) {
        $key = 'sitc_refresh_notice_' . get_current_user_id();
        set_transient($key, [
            'type' => 'warning',
            'message' => __('Safe Mode aktualisiert.', 'sitc'),
            'details' => '',
        ], 60);
    }

    wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
    exit;
});

// Admin notice: show when Safe Mode is active (admin only)
if (is_admin()) add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    if (!function_exists('sitc_is_safe_mode') || !sitc_is_safe_mode()) return;
    echo '<div class="notice notice-warning"><p><strong>'
        . esc_html__('Skip Intro Recipe Crawler: Safe Mode aktiv.', 'sitc')
        . '</strong> '
        . esc_html__('Fallback-Darstellung ist aktiv; Gruppierung und Skalierung sind deaktiviert.', 'sitc')
        . '</p></div>';
});
