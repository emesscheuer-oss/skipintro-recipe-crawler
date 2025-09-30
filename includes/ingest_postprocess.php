<?php
if (!defined('ABSPATH')) exit;

/**
 * Vereinheitlichte Nachbearbeitung nach Import/Refresh:
 * - Parser v2 + Normalizer (Schema neu) + Struct materialisieren
 * - Fallback-Thumbnail setzen
 */
function sitc_after_ingest(int $post_id): void {
    if ($post_id <= 0) return;

    if (function_exists('sitc_refresh_current_post_v2')) {
        // fuehrt: reparse schema (v2+Normalizer) + struct materialize aus
        sitc_refresh_current_post_v2($post_id, false);
    }

    if (function_exists('sitc_assign_fallback_thumbnail_if_missing')) {
        sitc_assign_fallback_thumbnail_if_missing($post_id);
    }
}
