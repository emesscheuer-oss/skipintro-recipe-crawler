<?php
if (!defined('ABSPATH')) exit;

/**
 * Set featured image from remote URL. Never throws; returns bool.
 */
function sitc_set_featured_image_v2(int $post_id, string $image_url): bool {
    $image_url = trim((string)$image_url);
    if ($post_id <= 0 || $image_url === '' || !filter_var($image_url, FILTER_VALIDATE_URL)) {
        return false;
    }
    // Load required WP media includes
    if (!function_exists('media_sideload_image')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if (!function_exists('wp_read_image_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    // Try direct sideload with ID return (WP 5.3+)
    try {
        $attach_id = null;
        $res = @media_sideload_image($image_url, $post_id, null, 'id');
        if (!is_wp_error($res) && is_numeric($res)) {
            $attach_id = (int)$res;
        } else {
            // Fallback: download + media_handle_sideload
            $tmp = download_url($image_url);
            if (!is_wp_error($tmp)) {
                $filename = basename(parse_url($image_url, PHP_URL_PATH));
                if ($filename === '' || strpos($filename, '.') === false) { $filename = 'image.jpg'; }
                $file = [ 'name' => $filename, 'tmp_name' => $tmp ];
                $id2 = media_handle_sideload($file, $post_id);
                if (!is_wp_error($id2)) {
                    $attach_id = (int)$id2;
                } else {
                    @unlink($tmp);
                }
            }
        }
        if ($attach_id && $attach_id > 0) {
            @set_post_thumbnail($post_id, $attach_id);
            return has_post_thumbnail($post_id);
        }
    } catch (Throwable $e) {
        if (function_exists('sitc_parser_debug_log')) {
            sitc_parser_debug_log([
                'engine' => function_exists('sitc_get_ing_engine') ? sitc_get_ing_engine() : ($GLOBALS['SITC_ING_ENGINE'] ?? 'auto'),
                'post_id' => $post_id,
                'exception' => get_class($e) . ': ' . $e->getMessage(),
                'phase' => 'refresh_media',
                'fallback_triggered' => false,
            ]);
        }
    }
    return false;
}

/**
 * Guarded setter for featured image. Only sets when sensible; dev-logs on failure.
 */
function sitc_maybe_set_featured_image(int $post_id, ?string $image_url): bool {
    $image_url = is_string($image_url) ? trim($image_url) : '';
    $dev = function_exists('sitc_parser_debug_logging_enabled') && sitc_parser_debug_logging_enabled();
    $log = function(array $f) use ($dev) { if ($dev && function_exists('sitc_parser_debug_log')) sitc_parser_debug_log($f); };

    $post = get_post($post_id);
    if (!$post) {
        $log(['engine'=>($GLOBALS['SITC_ING_ENGINE'] ?? 'auto'),'post_id'=>$post_id,'message'=>'no_post','phase'=>'refresh_media','fallback_triggered'=>false]);
        return false;
    }
    if ($image_url === '' || !filter_var($image_url, FILTER_VALIDATE_URL)) {
        $log(['engine'=>($GLOBALS['SITC_ING_ENGINE'] ?? 'auto'),'post_id'=>$post_id,'message'=>'empty_or_invalid_image_url','phase'=>'refresh_media','fallback_triggered'=>false]);
        return false;
    }
    if (!current_theme_supports('post-thumbnails')) {
        $log(['engine'=>($GLOBALS['SITC_ING_ENGINE'] ?? 'auto'),'post_id'=>$post_id,'message'=>'no_thumb_support','phase'=>'refresh_media','fallback_triggered'=>false]);
        return false;
    }
    if (has_post_thumbnail($post_id)) {
        // Do not replace existing thumbnail by default
        return true;
    }
    $ok = sitc_set_featured_image_v2($post_id, $image_url);
    if (!$ok) {
        $log(['engine'=>($GLOBALS['SITC_ING_ENGINE'] ?? 'auto'),'post_id'=>$post_id,'message'=>'featured image not set','phase'=>'refresh_media','fallback_triggered'=>false]);
    }
    return $ok;
}
