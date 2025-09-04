<?php
if (!defined('ABSPATH')) exit;

/**
 * Admin Meta Box + Refresh Handler for updating parser-managed fields
 */

// Add tools meta box on post edit screen
add_action('add_meta_boxes', function() {
    add_meta_box(
        'sitc_recipe_tools',
        __('Rezept-Tools', 'sitc'),
        'sitc_render_recipe_tools_metabox',
        'post',
        'side',
        'high'
    );
});

function sitc_render_recipe_tools_metabox(WP_Post $post) {
    if (!current_user_can('edit_post', $post->ID)) {
        echo '<p>' . esc_html__('Keine Berechtigung.', 'sitc') . '</p>';
        return;
    }
    $source_url = get_post_meta($post->ID, '_sitc_source_url', true);
    $schema_json = get_post_meta($post->ID, '_sitc_schema_recipe_json', true);
    $has_recipe_meta = !empty($source_url) || !empty($schema_json);
    $last_refreshed = get_post_meta($post->ID, '_sitc_last_refreshed', true);
    if (!empty($last_refreshed)) {
        // Private Use: display UTC + 2 hours (static CEST-like offset)
        $tsUtc = strtotime($last_refreshed . ' UTC');
        if ($tsUtc) {
            $last_refreshed = gmdate('Y-m-d H:i:s', $tsUtc + 2 * 3600);
        }
    }
    $parser_ver = get_post_meta($post->ID, '_sitc_parser_version', true);

    echo '<div class="sitc-recipe-tools">';
    if ($has_recipe_meta) {
        echo '<p style="margin:0 0 8px;">' . esc_html__('Quelle erkannt. Du kannst das Rezept aktualisieren (Parser v2).', 'sitc') . '</p>';
        $url = wp_nonce_url(
            add_query_arg([
                'action'  => 'sitc_refresh_recipe',
                'post_id' => (int)$post->ID,
            ], admin_url('admin-post.php')),
            'sitc_refresh_recipe_' . $post->ID,
            'sitc_refresh_nonce'
        );
        echo '<p><a href="' . esc_url($url) . '" class="button button-primary">' . esc_html__('Rezept aktualisieren', 'sitc') . '</a></p>';
        if (function_exists('sitc_is_dev_mode') && sitc_is_dev_mode()) {
            $dry = add_query_arg('dry_run', '1', $url);
            echo '<p style="margin-top:6px;"><a href="' . esc_url($dry) . '" class="button">' . esc_html__('Trockenlauf (Diff, kein Schreiben)', 'sitc') . '</a></p>';
        }
    } else {
        echo '<p style="margin:0;">' . esc_html__('Keine bekannte Quelle gespeichert.', 'sitc') . '</p>';
    }

    if (!empty($last_refreshed)) {
        echo '<hr style="margin:10px 0">';
        echo '<p style="margin:0 0 4px;"><strong>' . esc_html__('Zuletzt aktualisiert:', 'sitc') . '</strong><br>' . esc_html($last_refreshed) . '</p>';
    }
    if (!empty($parser_ver)) {
        echo '<p style="margin:4px 0 0;"><strong>' . esc_html__('Parser-Version:', 'sitc') . '</strong> ' . esc_html($parser_ver) . '</p>';
    }
    echo '</div>';
}

// Handle refresh action (admin-post)
add_action('admin_post_sitc_refresh_recipe', 'sitc_handle_refresh_recipe');
function sitc_handle_refresh_recipe() {
    $post_id = isset($_REQUEST['post_id']) ? (int)$_REQUEST['post_id'] : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        sitc_store_admin_notice('error', __('Keine Berechtigung oder ungültige Anfrage.', 'sitc'));
        wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post='.$post_id.'&action=edit'));
        exit;
    }
    if (empty($_REQUEST['sitc_refresh_nonce']) || !wp_verify_nonce($_REQUEST['sitc_refresh_nonce'], 'sitc_refresh_recipe_' . $post_id)) {
        sitc_store_admin_notice('error', __('Sicherheitsprüfung fehlgeschlagen.', 'sitc'));
        wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post='.$post_id.'&action=edit'));
        exit;
    }

    $dry_run = !empty($_REQUEST['dry_run']);

    // Determine source URL: schema.url -> canonical/og:url -> _sitc_source_url
    $source_url = sitc_determine_source_url($post_id);
    if (empty($source_url)) {
        sitc_store_admin_notice('error', __('Keine Quelle ermittelbar.', 'sitc'));
        wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post='.$post_id.'&action=edit'));
        exit;
    }

    do_action('sitc_before_refresh', $post_id, $source_url, ['dry_run'=>$dry_run]);

    // Parse with v2 (guarded)
    try {
        $result = sitc_parse_recipe_from_url_v2($source_url);
    } catch (Throwable $e) {
        error_log('SITC Refresh fatal: ' . $e->getMessage());
        sitc_store_admin_notice('error', __('Parser-Fehler beim Aktualisieren (abgefangen).', 'sitc'));
        wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post='.$post_id.'&action=edit'));
        exit;
    }
    if (!$result || !is_array($result)) {
        sitc_store_admin_notice('error', __('Parser lieferte keine Daten.', 'sitc'));
        wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post='.$post_id.'&action=edit'));
        exit;
    }

    // Build update plan respecting locks
    $locks = get_post_meta($post_id, '_sitc_lock', true);
    if (!is_array($locks)) $locks = [];
    $locks = array_map('strval', $locks);

    $changes = [];
    $meta_updates = [];
    $warnings = [];

    // 1) Title / Description (excerpt via description)
    if (!in_array('title', $locks, true)) {
        $new_title = (string)($result['title'] ?? '');
        if ($new_title !== '') {
            $old_title = get_post_field('post_title', $post_id);
            if ($old_title !== $new_title) {
                $changes['title'] = [$old_title, $new_title];
                if (!$dry_run) wp_update_post(['ID'=>$post_id, 'post_title'=>$new_title]);
            }
        }
    }
    if (!in_array('description', $locks, true)) {
        $new_desc = (string)($result['description'] ?? '');
        $old_desc = get_post_field('post_content', $post_id);
        if ($new_desc !== '' && $new_desc !== $old_desc) {
            $changes['description'] = [
                mb_substr(wp_strip_all_tags($old_desc), 0, 120),
                mb_substr(wp_strip_all_tags($new_desc), 0, 120)
            ];
            if (!$dry_run) wp_update_post(['ID'=>$post_id, 'post_content'=>$new_desc]);
        }
    }

    // 2) Image (featured)
    if (!in_array('image', $locks, true)) {
        $new_img = (string)($result['image'] ?? '');
        if ($new_img !== '') {
            $old_thumb = get_post_thumbnail_id($post_id);
            if (!$dry_run) {
                sitc_set_featured_image($post_id, $new_img, []);
            }
            $new_thumb = $dry_run ? $old_thumb : get_post_thumbnail_id($post_id);
            if ($new_thumb !== $old_thumb) {
                $changes['image'] = [$old_thumb, $new_thumb];
            }
        }
    }

    // 3) Ingredients (ingredients_struct) (+ ingredientGroups/ingredientsParsed via schema)
    if (!in_array('ingredients', $locks, true)) {
        $old_ing = get_post_meta($post_id, '_sitc_ingredients_struct', true);
        $new_ing = $result['ingredients_struct'] ?? [];
        if (!is_array($old_ing)) $old_ing = [];
        if (wp_json_encode($old_ing) !== wp_json_encode($new_ing)) {
            $changes['ingredients'] = ['count_old'=>count((array)$old_ing), 'count_new'=>count((array)$new_ing)];
            if (!$dry_run) update_post_meta($post_id, '_sitc_ingredients_struct', $new_ing);
        }
    }

    // 4) Instructions
    if (!in_array('instructions', $locks, true)) {
        $old_ins = get_post_meta($post_id, '_sitc_instructions', true);
        $new_ins = $result['instructions'] ?? [];
        if (!is_array($old_ins)) $old_ins = [];
        if (wp_json_encode($old_ins) !== wp_json_encode($new_ins)) {
            $changes['instructions'] = ['count_old'=>count((array)$old_ins), 'count_new'=>count((array)$new_ins)];
            if (!$dry_run) update_post_meta($post_id, '_sitc_instructions', $new_ins);
        }
    }

    // 5) Times (prep/cook/total) and yield/yieldNormalized
    $schema = [];
    if (!empty($result['meta']['schema_recipe'])) {
        $schema = (array)$result['meta']['schema_recipe'];
    }
    if (!in_array('times', $locks, true)) {
        $times = [
            '_sitc_prep_time'  => $schema['prepTime']  ?? null,
            '_sitc_cook_time'  => $schema['cookTime']  ?? null,
            '_sitc_total_time' => $schema['totalTime'] ?? null,
        ];
        foreach ($times as $k=>$v) {
            $old = get_post_meta($post_id, $k, true);
            $v = $v ?: '';
            if ((string)$old !== (string)$v) {
                $changes[$k] = [$old, $v];
                if (!$dry_run) update_post_meta($post_id, $k, $v);
            }
        }
    }
    if (!in_array('yield', $locks, true)) {
        $old_y_raw = get_post_meta($post_id, '_sitc_yield_raw', true);
        $old_y_num = get_post_meta($post_id, '_sitc_yield_num', true);
        $new_y_raw = $result['yield_raw'] ?? '';
        $new_y_num = $result['yield_num'] ?? '';
        if ((string)$old_y_raw !== (string)$new_y_raw) {
            $changes['_sitc_yield_raw'] = [$old_y_raw, $new_y_raw];
            if (!$dry_run) update_post_meta($post_id, '_sitc_yield_raw', $new_y_raw);
        }
        if ((string)$old_y_num !== (string)$new_y_num) {
            $changes['_sitc_yield_num'] = [$old_y_num, $new_y_num];
            if (!$dry_run) update_post_meta($post_id, '_sitc_yield_num', $new_y_num);
        }
        if (!empty($schema['yieldNormalized'])) {
            $old_y_norm = get_post_meta($post_id, '_sitc_yield_normalized_json', true);
            $new_y_norm = wp_json_encode($schema['yieldNormalized']);
            if ((string)$old_y_norm !== (string)$new_y_norm) {
                $changes['_sitc_yield_normalized_json'] = ['old'=>!!$old_y_norm, 'new'=>!!$new_y_norm];
                if (!$dry_run) update_post_meta($post_id, '_sitc_yield_normalized_json', $new_y_norm);
            }
        }
    }

    // 6) nutrition / aggregateRating
    if (!in_array('nutrition', $locks, true) && !empty($schema['nutrition'])) {
        $old = get_post_meta($post_id, '_sitc_nutrition_json', true);
        $new = wp_json_encode($schema['nutrition']);
        if ((string)$old !== (string)$new) {
            $changes['_sitc_nutrition_json'] = ['old'=>!!$old, 'new'=>!!$new];
            if (!$dry_run) update_post_meta($post_id, '_sitc_nutrition_json', $new);
        }
    }
    if (!in_array('rating', $locks, true) && !empty($schema['aggregateRating'])) {
        $old = get_post_meta($post_id, '_sitc_aggregate_rating_json', true);
        $new = wp_json_encode($schema['aggregateRating']);
        if ((string)$old !== (string)$new) {
            $changes['_sitc_aggregate_rating_json'] = ['old'=>!!$old, 'new'=>!!$new];
            if (!$dry_run) update_post_meta($post_id, '_sitc_aggregate_rating_json', $new);
        }
    }

    // Always persist rich parser meta bundle
    if (!empty($result['meta'])) {
        $m = $result['meta'];
        if (!empty($m['schema_recipe'])) {
            $old = get_post_meta($post_id, '_sitc_schema_recipe_json', true);
            $new = wp_json_encode($m['schema_recipe']);
            if ((string)$old !== (string)$new) {
                $changes['_sitc_schema_recipe_json'] = ['old'=>!!$old, 'new'=>!!$new];
                if (!$dry_run) update_post_meta($post_id, '_sitc_schema_recipe_json', $new);
            }
        }
        if (array_key_exists('confidence', $m)) {
            $old = get_post_meta($post_id, '_sitc_confidence', true);
            $new = (float)$m['confidence'];
            if ((string)$old !== (string)$new) {
                $changes['_sitc_confidence'] = [$old, $new];
                if (!$dry_run) update_post_meta($post_id, '_sitc_confidence', $new);
            }
            if ($new < 0.7) $warnings[] = __('Geringer Confidence-Score beim Parser-Ergebnis.', 'sitc');
        }
        if (!empty($m['flags'])) {
            $flags = (array)$m['flags'];
            if (!empty($flags['isPartial'])) $warnings[] = __('Parser meldet unvollständige Daten (isPartial).', 'sitc');
            $old = get_post_meta($post_id, '_sitc_flags_json', true);
            $new = wp_json_encode($flags);
            if ((string)$old !== (string)$new) {
                $changes['_sitc_flags_json'] = ['old'=>!!$old, 'new'=>!!$new];
                if (!$dry_run) update_post_meta($post_id, '_sitc_flags_json', $new);
            }
        }
        if (!empty($m['sources'])) {
            $old = get_post_meta($post_id, '_sitc_sources_json', true);
            $new = wp_json_encode($m['sources']);
            if ((string)$old !== (string)$new) {
                $changes['_sitc_sources_json'] = ['old'=>!!$old, 'new'=>!!$new];
                if (!$dry_run) update_post_meta($post_id, '_sitc_sources_json', $new);
            }
        }
    }

    // Metadata: last refreshed + parser version + short log
    $now_utc = gmdate('Y-m-d H:i:s');
    $parser_version = sitc_get_parser_version();
    if (!$dry_run) {
        update_post_meta($post_id, '_sitc_last_refreshed', $now_utc);
        update_post_meta($post_id, '_sitc_parser_version', $parser_version);
    }
    sitc_append_refresh_log($post_id, [
        'ts' => $now_utc,
        'user' => get_current_user_id(),
        'source' => $source_url,
        'dry_run' => $dry_run,
        'changes' => array_keys($changes),
        'confidence' => isset($result['meta']['confidence']) ? (float)$result['meta']['confidence'] : null,
        'flags' => $result['meta']['flags'] ?? [],
    ], $dry_run);

    // Build notice
    $summary = $dry_run
        ? __('Trockenlauf: Es würden folgende Felder aktualisiert werden:', 'sitc')
        : __('Rezept aktualisiert. Geänderte Felder:', 'sitc');
    $detail = '<ul style="margin:6px 0 0 16px;">';
    foreach ($changes as $k=>$v) { $detail .= '<li><code>' . esc_html($k) . '</code></li>'; }
    $detail .= '</ul>';
    if ($warnings) {
        $detail .= '<p style="margin:6px 0 0;color:#a00;">' . esc_html(implode(' ', $warnings)) . '</p>';
    }
    sitc_store_admin_notice('success', $summary, $detail);

    do_action('sitc_after_refresh', $post_id, $result, $changes, ['dry_run'=>$dry_run]);

    wp_safe_redirect(wp_get_referer() ?: admin_url('post.php?post='.$post_id.'&action=edit'));
    exit;
}

// Add a small action in the Publish box for better visibility
add_action('post_submitbox_misc_actions', function() {
    global $post;
    if (!$post || $post->post_type !== 'post') return;
    if (!current_user_can('edit_post', $post->ID)) return;
    $source_url = get_post_meta($post->ID, '_sitc_source_url', true);
    $schema_json = get_post_meta($post->ID, '_sitc_schema_recipe_json', true);
    $has_recipe_meta = !empty($source_url) || !empty($schema_json);
    echo '<div class="misc-pub-section sitc-refresh-action">';
    if ($has_recipe_meta) {
        $url = wp_nonce_url(
            add_query_arg([
                'action'  => 'sitc_refresh_recipe',
                'post_id' => (int)$post->ID,
            ], admin_url('admin-post.php')),
            'sitc_refresh_recipe_' . $post->ID,
            'sitc_refresh_nonce'
        );
        echo '<a href="' . esc_url($url) . '" class="button button-primary" style="margin-top:6px;">' . esc_html__('Rezept aktualisieren', 'sitc') . '</a>';
        if (function_exists('sitc_is_dev_mode') && sitc_is_dev_mode()) {
            $dry = add_query_arg('dry_run', '1', $url);
            echo ' <a href="' . esc_url($dry) . '" class="button" style="margin-top:6px;">' . esc_html__('Trockenlauf', 'sitc') . '</a>';
        }
    } else {
        echo '<span style="display:block;color:#666;">' . esc_html__('Keine Quelle gespeichert', 'sitc') . '</span>';
    }
    echo '</div>';
});

/**
 * Determine best source URL: schema.url -> canonical/og:url -> _sitc_source_url
 */
function sitc_determine_source_url(int $post_id): string {
    $schema_json = get_post_meta($post_id, '_sitc_schema_recipe_json', true);
    if (!empty($schema_json)) {
        $schema = json_decode($schema_json, true);
        if (is_array($schema) && !empty($schema['url']) && filter_var($schema['url'], FILTER_VALIDATE_URL)) {
            return (string)$schema['url'];
        }
    }
    $stored = get_post_meta($post_id, '_sitc_source_url', true);
    $stored = is_string($stored) ? trim($stored) : '';
    if ($stored !== '') {
        // Try to resolve canonical/og:url once
        $resolved = sitc_resolve_canonical_url($stored);
        return $resolved ?: $stored;
    }
    return '';
}

function sitc_resolve_canonical_url(string $url): string {
    $resp = wp_remote_get($url, ['timeout'=>12]);
    if (is_wp_error($resp)) return '';
    $body = wp_remote_retrieve_body($resp);
    if (!$body) return '';
    $canon = '';
    if (preg_match('/<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']/i', $body, $m)) {
        $canon = trim($m[1]);
    } elseif (preg_match('/<meta[^>]+property=["\']og:url["\'][^>]+content=["\']([^"\']+)["\']/i', $body, $m)) {
        $canon = trim($m[1]);
    }
    if ($canon && filter_var($canon, FILTER_VALIDATE_URL)) return $canon;
    return '';
}

function sitc_get_parser_version(): string {
    // Use plugin version as parser version fallback
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $data = get_plugin_data(WP_PLUGIN_DIR . '/skipintro-recipe-crawler/skipintro-recipe-crawler.php', false, false);
    return is_array($data) && !empty($data['Version']) ? (string)$data['Version'] : '0.0.0';
}

function sitc_append_refresh_log(int $post_id, array $entry, bool $dry_run=false): void {
    $raw = get_post_meta($post_id, '_sitc_refresh_log', true);
    $log = [];
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $log = $decoded;
    }
    array_unshift($log, $entry);
    $log = array_slice($log, 0, 10); // capped history
    update_post_meta($post_id, '_sitc_refresh_log', wp_json_encode($log));
}

// Admin notices helper via transient to avoid long query args
add_action('admin_notices', function() {
    if (!is_user_logged_in()) return;
    // Skip classic admin_notices on Block Editor screens; use Gutenberg notice instead
    if (function_exists('get_current_screen')) {
        $screen = get_current_screen();
        if ($screen && method_exists($screen, 'is_block_editor') && $screen->is_block_editor()) {
            return;
        }
    }
    $key = 'sitc_refresh_notice_' . get_current_user_id();
    $note = get_transient($key);
    if (!$note) return;
    delete_transient($key);
    $type = in_array($note['type'], ['success','error','warning','info'], true) ? $note['type'] : 'info';
    echo '<div class="notice notice-' . esc_attr($type) . '"><p>' . wp_kses_post($note['message']) . '</p>';
    if (!empty($note['details'])) echo '<div>' . wp_kses_post($note['details']) . '</div>';
    echo '</div>';
});

// Show notice inside the Block Editor (Gutenberg) as in-editor notice
add_action('admin_footer-post.php', function() {
    if (!is_user_logged_in()) return;
    if (!function_exists('get_current_screen')) return;
    $screen = get_current_screen();
    // Only run on block editor screens to avoid duplicate messages in Classic Editor
    if (!$screen || (method_exists($screen, 'is_block_editor') && !$screen->is_block_editor())) return;

    $key = 'sitc_refresh_notice_' . get_current_user_id();
    $note = get_transient($key);
    if (!$note) return;
    delete_transient($key);

    $type = in_array($note['type'], ['success','error','warning','info'], true) ? $note['type'] : 'info';
    $message = wp_strip_all_tags((string)($note['message'] ?? ''));
    $details = wp_strip_all_tags((string)($note['details'] ?? ''));
    $content = trim($message . ($details !== '' ? ' ' . $details : ''));

    // Print a small script to push a notice via Gutenberg's data store
    echo '<script>(function(w){
        if(!w || !w.wp || !w.wp.data || !w.wp.data.dispatch){return;}
        try {
            var kind = ' . json_encode($type) . ';
            var text = ' . json_encode($content) . ';
            var status = (kind===' . json_encode('error') . ') ? ' . json_encode('error') . ' : (kind===' . json_encode('warning') . ' ? ' . json_encode('warning') . ' : ' . json_encode('success') . ');
            // Delay a tick to ensure editor is ready
            setTimeout(function(){
                w.wp.data.dispatch("core/notices").createNotice(status, text, {isDismissible:true});
            }, 50);
        } catch(e){}
    })(window);</script>';
});

function sitc_store_admin_notice(string $type, string $message, string $details_html=''): void {
    if (!is_user_logged_in()) return;
    $key = 'sitc_refresh_notice_' . get_current_user_id();
    set_transient($key, [
        'type' => $type,
        'message' => $message,
        'details' => $details_html,
    ], 60);
}

?>
