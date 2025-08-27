<?php
if (!defined('ABSPATH')) exit;

/**
 * Shortcode [sitc_import_form]
 * Formular im Frontend, analog zur Admin-Version.
 */
function sitc_render_import_form() {
    ob_start(); ?>

    <form method="post" class="sitc-import-form">
        <label>Rezept-URL:<br>
            <input type="url" name="sitc_recipe_url" size="60" required>
        </label>
        <br><br>

        <h3>Kategorien auswählen</h3>
        <?php
        $cats = get_categories(['hide_empty'=>0]);
        foreach ($cats as $cat) {
            echo '<label style="display:block;margin:2px 0;">
                <input type="checkbox" name="sitc_cats[]" value="' . esc_attr($cat->term_id) . '">
                ' . esc_html($cat->name) . '
            </label>';
        }
        ?>
        <br>
        <label>Neue Kategorien (kommagetrennt):<br>
            <input type="text" name="sitc_newcats" size="60" placeholder="z. B. Indisch, Abendessen">
        </label>
        <br><br>

        <button type="submit" name="sitc_frontend_import" value="1">Import starten</button>
    </form>

    <?php
    // Formular-Verarbeitung
    if (!empty($_POST['sitc_frontend_import']) && !empty($_POST['sitc_recipe_url'])) {
        $url = esc_url_raw($_POST['sitc_recipe_url']);
        $recipe = sitc_parse_recipe_from_url($url);
        if ($recipe) {
            $post_id = wp_insert_post([
                'post_title'   => !empty($recipe['title']) ? $recipe['title'] : 'Unbenanntes Rezept',
                'post_content' => !empty($recipe['description']) ? $recipe['description'] : '',
                'post_status'  => 'publish',
                'post_type'    => 'post'
            ]);

            if ($post_id && !is_wp_error($post_id)) {
                // Kategorien sammeln: vorhandene Checkboxen + neue
                $all_cats = [];
                if (!empty($_POST['sitc_cats'])) {
                    $all_cats = array_map('intval', (array)$_POST['sitc_cats']);
                }
                if (!empty($_POST['sitc_newcats'])) {
                    $newcats = array_filter(array_map('trim', explode(',', $_POST['sitc_newcats'])));
                    foreach ($newcats as $new) {
                        $term = term_exists($new, 'category');
                        if (!$term) $term = wp_insert_term($new, 'category');
                        if (!is_wp_error($term)) {
                            $all_cats[] = is_array($term) ? $term['term_id'] : $term;
                        }
                    }
                }
                if ($all_cats) {
                    wp_set_post_categories($post_id, array_unique($all_cats));
                }

                // Metadaten
                if (!empty($recipe['ingredients_struct'])) update_post_meta($post_id, '_sitc_ingredients_struct', $recipe['ingredients_struct']);
                if (!empty($recipe['instructions']))       update_post_meta($post_id, '_sitc_instructions', $recipe['instructions']);
                if (isset($recipe['yield_raw']))           update_post_meta($post_id, '_sitc_yield_raw', $recipe['yield_raw']);
                if (isset($recipe['yield_num']))           update_post_meta($post_id, '_sitc_yield_num', $recipe['yield_num']);
                update_post_meta($post_id, '_sitc_source_url', $url);

                // Featured Image mit Fallbacks
                $image_url = !empty($recipe['image']) ? $recipe['image'] : '';
                sitc_set_featured_image($post_id, $image_url, $all_cats);

                echo '<div class="sitc-success">Rezept importiert! <a href="'.get_permalink($post_id).'">ansehen</a></div>';
            } else {
                echo '<div class="sitc-error">Fehler beim Erstellen des Beitrags.</div>';
            }
        } else {
            echo '<div class="sitc-error">Keine Rezeptdaten gefunden.</div>';
        }
    }

    return ob_get_clean();
}
add_shortcode('sitc_import_form', 'sitc_render_import_form');
