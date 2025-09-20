<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode [sitc_import_form]
 * Formular im Frontend, analog zur Admin-Version.
 */
function sitc_render_import_form() {
    $html = '';

    $html .= '<form method="post" class="sitc-import-form">';
    $html .= '<label>Rezept-URL:<br>';
    $html .= '<input type="url" name="sitc_recipe_url" size="60" required>';
    $html .= '</label>';
    $html .= '<br><br>';

    $html .= '<h3>Kategorien ausw&auml;hlen</h3>';

    $cats = get_categories(['hide_empty' => 0]);
    foreach ($cats as $cat) {
        $html .= '<label style="display:block;margin:2px 0;">';
        $html .= '<input type="checkbox" name="sitc_cats[]" value="' . esc_attr((string)$cat->term_id) . '">';
        $html .= ' ' . esc_html((string)$cat->name);
        $html .= '</label>';
    }

    $html .= '<br>';
    $html .= '<label>Neue Kategorien (kommagetrennt):<br>';
    $html .= '<input type="text" name="sitc_newcats" size="60" placeholder="z. B. Indisch, Abendessen">';
    $html .= '</label>';
    $html .= '<br><br>';

    $html .= '<button type="submit" name="sitc_frontend_import" value="1">Import starten</button>';
    $html .= '</form>';

    if (!empty($_POST['sitc_frontend_import']) && !empty($_POST['sitc_recipe_url'])) {
        $url = esc_url_raw((string)$_POST['sitc_recipe_url']);
        try {
            $recipe = sitc_parse_recipe_from_url_v2($url);
        } catch (Throwable $e) {
            error_log('SITC Frontend Import fatal: ' . $e->getMessage());
            $recipe = null;
        }
        if ($recipe) {
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
                if (function_exists('sitc_get_ing_parser_version')) {
                    update_post_meta($post_id, '_sitc_ing_parser_version', sitc_get_ing_parser_version());
                }
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
                sitc_set_featured_image($post_id, $image_url, $all_cats);

                $html .= '<div class="sitc-success">Rezept importiert! <a href="' . esc_url(get_permalink($post_id)) . '">ansehen</a></div>';
            } else {
                $html .= '<div class="sitc-error">Fehler beim Erstellen des Beitrags.</div>';
            }
        } else {
            $html .= '<div class="sitc-error">Keine Rezeptdaten gefunden.</div>';
        }
    }

    return $html;
}
add_shortcode('sitc_import_form', 'sitc_render_import_form');
