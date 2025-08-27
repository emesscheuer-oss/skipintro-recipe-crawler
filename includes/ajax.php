<?php
if (!defined('ABSPATH')) exit;

/**
 * Frontend-Post-Löschung per Token (öffentlich zugänglich).
 * Der Token wird pro Beitrag in _sitc_trash_token gespeichert.
 */
function sitc_handle_trash_post() {
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $token   = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

    if (!$post_id || empty($token)) {
        wp_send_json_error(['message' => 'Bad request.']);
    }

    $saved = get_post_meta($post_id, '_sitc_trash_token', true);
    if (!$saved || !hash_equals($saved, $token)) {
        wp_send_json_error(['message' => 'Invalid token.']);
    }

    // Beitrag in den Papierkorb verschieben
    $trashed = wp_trash_post($post_id);
    if ($trashed instanceof WP_Post || $trashed === true) {
        wp_send_json_success(['message' => 'Post trashed.']);
    }
    wp_send_json_error(['message' => 'Trash failed.']);
}
add_action('wp_ajax_sitc_trash_post', 'sitc_handle_trash_post');
add_action('wp_ajax_nopriv_sitc_trash_post', 'sitc_handle_trash_post');
