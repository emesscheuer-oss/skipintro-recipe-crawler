<?php
if (!defined('ABSPATH')) exit;

/**
 * Set featured image. Tries direct image, then first category image meta (_sitc_cat_image), else plugin default.
 */
function sitc_set_featured_image($post_id, $image_url = '', $cat_ids = []) {
    $attachment_id = 0;

    if (!empty($image_url)) {
        $attachment_id = sitc_download_attach_image($image_url, $post_id);
    }

    if (!$attachment_id && !empty($cat_ids) && is_array($cat_ids)) {
        foreach ($cat_ids as $cid) {
            $cat_img = get_term_meta($cid, '_sitc_cat_image', true);
            if ($cat_img) {
                $attachment_id = sitc_download_attach_image($cat_img, $post_id);
                if ($attachment_id) break;
            }
        }
    }

    if (!$attachment_id) {
        // fallback default
        $default_path = plugin_dir_path(dirname(__FILE__)) . 'assets/img/default-recipe.png';
        if (file_exists($default_path)) {
            $attachment_id = sitc_import_local_image($default_path, $post_id, 'default-recipe.png');
        }
    }

    if ($attachment_id) {
        set_post_thumbnail($post_id, $attachment_id);
    }
}

function sitc_download_attach_image($url, $post_id) {
    if (empty($url)) return 0;
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = download_url($url);
    if (is_wp_error($tmp)) return 0;
    $file = [
        'name'     => basename(parse_url($url, PHP_URL_PATH)),
        'type'     => mime_content_type($tmp),
        'tmp_name' => $tmp,
        'error'    => 0,
        'size'     => filesize($tmp),
    ];
    $id = media_handle_sideload($file, $post_id);
    if (is_wp_error($id)) {
        @unlink($tmp);
        return 0;
    }
    return $id;
}

function sitc_import_local_image($path, $post_id, $filename='image.png') {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $data = file_get_contents($path);
    if ($data === false) return 0;
    $upload = wp_upload_bits($filename, null, $data);
    if ($upload['error']) return 0;
    $file = [
        'name'     => basename($upload['file']),
        'type'     => mime_content_type($upload['file']),
        'tmp_name' => $upload['file'],
        'error'    => 0,
        'size'     => filesize($upload['file']),
    ];
    $id = media_handle_sideload($file, $post_id);
    if (is_wp_error($id)) return 0;
    return $id;
}
