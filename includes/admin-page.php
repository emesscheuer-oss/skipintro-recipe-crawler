<?php
if (!defined('ABSPATH')) exit;

// Ensure media helpers are available (sitc_maybe_set_featured_image, etc.)
if (!function_exists('sitc_maybe_set_featured_image')) {
    $__sitc_media = __DIR__ . '/../media.php';
    if (is_file($__sitc_media)) {
        require_once $__sitc_media;
    }
}


function sitc_render_importer_page() {
    $html = '';

    $html .= '<div class="wrap">';
    $html .= '<h1>Rezept importieren</h1>';

    $can_manage = current_user_can('manage_options');
    $dev_active = function_exists('sitc_parser_debug_logging_enabled') && sitc_parser_debug_logging_enabled();
    if ($can_manage && $dev_active) {
        $valUrl = admin_url('edit.php?page=sitc-parser-validation');
        $logUrl = add_query_arg(['page' => 'sitc-parser-validation', 'view' => 'debug'], admin_url('edit.php'));
        $html .= '<div class="notice" style="padding:10px;background:#f8f8f8;border:1px solid #ccd0d4;margin:10px 0 0 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;">';
        $html .= '<strong>QA/Debug:</strong> ';
        $html .= '<a class="button button-secondary" href="' . esc_url($valUrl) . '">Validierung &ouml;ffnen</a>';
        $html .= '<a class="button button-secondary" href="' . esc_url($logUrl) . '">Debug-Log &ouml;ffnen</a>';
        $html .= '<span style="color:#666;margin-left:6px;">(Nur im Dev-Modus sichtbar)</span>';
        $html .= '</div>';
    }

    $html .= '<div class="notice" style="padding:10px;background:#fff;border:1px solid #ccd0d4;margin:10px 0;display:flex;gap:16px;flex-wrap:wrap;align-items:center;">';

    $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;align-items:center;gap:10px;">';
    $html .= wp_nonce_field('sitc_dev_mode_toggle', 'sitc_dev_mode_nonce', true, false);
    $html .= '<input type="hidden" name="action" value="sitc_toggle_dev_mode">';
    $is_dev_on = function_exists('sitc_is_dev_mode') && sitc_is_dev_mode();
    $html .= '<label style="margin:0;display:flex;align-items:center;gap:6px;">';
    $html .= '<input type="checkbox" name="enable" value="1" ' . checked($is_dev_on, true, false) . '>';
    $html .= '<strong>Dev-Mode</strong> (steuert Dry-Run im Refresh)';
    $html .= '</label>';
    $html .= get_submit_button('Speichern', 'secondary', 'submit', false);
    $html .= '</form>';

    $html .= '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;align-items:center;gap:10px;">';
    $html .= wp_nonce_field('sitc_safe_mode_toggle', 'sitc_safe_mode_nonce', true, false);
    $html .= '<input type="hidden" name="action" value="sitc_toggle_safe_mode">';
    $is_safe_on = function_exists('sitc_is_safe_mode') && sitc_is_safe_mode();
    $html .= '<label style="margin:0;display:flex;align-items:center;gap:6px;">';
    $html .= '<input type="checkbox" name="enable" value="1" ' . checked($is_safe_on, true, false) . '>';
    $html .= '<strong>Safe Mode</strong> (erzwingt Fallback-Renderer)';
    $html .= '</label>';
    $html .= get_submit_button('Speichern', 'secondary', 'submit', false);
    $html .= '</form>';

    $html .= '</div>';

    $html .= '<form method="post">';
    $html .= '<input type="url" name="sitc_recipe_url" placeholder="Rezept-URL eingeben" size="60" required><br><br>';
    $html .= '<h3>Kategorien ausw&auml;hlen</h3>';

    $cats = get_categories(['hide_empty' => 0]);
    foreach ($cats as $cat) {
        $html .= '<label style="display:block;margin:2px 0;"><input type="checkbox" name="sitc_cats[]" value="' . esc_attr((string)$cat->term_id) . '"> ' . esc_html((string)$cat->name) . '</label>';
    }

    $html .= '<br>';
    $html .= '<label>Neue Kategorien (kommagetrennt):<br>';
    $html .= '<input type="text" name="sitc_newcats" size="60" placeholder="z. B. Indisch, Abendessen">';
    $html .= '</label><br><br>';

    $html .= '<label>';
    $html .= '<input type="checkbox" name="sitc_debug" value="1"> Debug-Ausgabe anzeigen';
    $html .= '</label><br><br>';

    $html .= get_submit_button('Import starten', 'primary', 'submit', false);
    $html .= '</form>';

    if (!empty($_POST['sitc_recipe_url'])) {
        $url = esc_url_raw((string)$_POST['sitc_recipe_url']);
        try {
            $recipe = sitc_parse_recipe_from_url_v2($url);
        } catch (Throwable $e) {
            error_log('SITC Admin Import fatal: ' . $e->getMessage());
            $recipe = null;
        }

        if ($recipe) {
            if (!empty($_POST['sitc_debug'])) {
                $html .= '<div class="notice notice-info"><p><strong>Debug-Ausgabe der Parser-Daten:</strong></p><pre style="max-height:300px;overflow:auto;background:#f9f9f9;border:1px solid #ccc;padding:10px;">';
                $html .= print_r($recipe, true);
                $html .= '</pre></div>';
            }

            $post_id = wp_insert_post([
                'post_title'   => !empty($recipe['title']) ? $recipe['title'] : 'Unbenanntes Rezept',
                'post_content' => !empty($recipe['description']) ? $recipe['description'] : '',
                'post_status'  => 'publish',
                'post_type'    => 'post',
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                $all_cats = [];
                if (!empty($_POST['sitc_cats'])) {
                    $all_cats = array_map('intval', (array)$_POST['sitc_cats']);
                }
                if (!empty($_POST['sitc_newcats'])) {
                    $newcats = array_filter(array_map('trim', explode(',', (string)$_POST['sitc_newcats'])));
                    foreach ($newcats as $new) {
                        $term = term_exists($new, 'category');
                        if (!$term) {
                            $term = wp_insert_term($new, 'category');
                        }
                        if (!is_wp_error($term)) {
                            $all_cats[] = is_array($term) ? $term['term_id'] : $term;
                        }
                    }
                }
                if ($all_cats) {
                    wp_set_post_categories($post_id, array_unique($all_cats));
                }

                if (!empty($recipe['ingredients_struct'])) {
                    update_post_meta($post_id, '_sitc_ingredients_struct', $recipe['ingredients_struct']);
                }
                if (!empty($recipe['instructions'])) {
                    update_post_meta($post_id, '_sitc_instructions', $recipe['instructions']);
                }
                if (isset($recipe['yield_raw'])) {
                    update_post_meta($post_id, '_sitc_yield_raw', $recipe['yield_raw']);
                }
                if (isset($recipe['yield_num'])) {
                    update_post_meta($post_id, '_sitc_yield_num', $recipe['yield_num']);
                }
                update_post_meta($post_id, '_sitc_source_url', !empty($recipe['source_url']) ? $recipe['source_url'] : $url);

                if (!empty($recipe['meta'])) {
                    $meta = $recipe['meta'];
                    if (!empty($meta['schema_recipe'])) {
                        update_post_meta($post_id, '_sitc_schema_recipe_json', wp_json_encode($meta['schema_recipe']));
                    }
                    if (array_key_exists('confidence', $meta)) {
                        update_post_meta($post_id, '_sitc_confidence', (float)$meta['confidence']);
                    }
                    if (!empty($meta['sources'])) {
                        update_post_meta($post_id, '_sitc_sources_json', wp_json_encode($meta['sources']));
                    }
                    if (!empty($meta['flags'])) {
                        update_post_meta($post_id, '_sitc_flags_json', wp_json_encode($meta['flags']));
                    }
                }

                $image_url = !empty($recipe['image']) ? $recipe['image'] : '';
                if (function_exists('sitc_maybe_set_featured_image')) { sitc_maybe_set_featured_image($post_id, $image_url); }

                $html .= '<div class="notice notice-success"><p>Rezept erfolgreich erstellt! ID: ' . (int)$post_id . '</p></div>';

                error_log('SITC import pre-after_ingest for post ' . (int)$post_id);
                sitc_after_ingest((int)$post_id);
                error_log('SITC import post-after_ingest for post ' . (int)$post_id);
            } else {
                $html .= '<div class="notice notice-error"><p>Fehler beim Erstellen des Beitrags.</p></div>';
            }
        } else {
            $html .= '<div class="notice notice-error"><p>Keine Rezeptdaten gefunden!</p></div>';
        }
    }

    $html .= '</div>';

    echo $html;
}

