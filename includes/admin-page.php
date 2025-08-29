<?php
if (!defined('ABSPATH')) exit;

function sitc_render_importer_page() {
    ?>
    <div class="wrap">
        <h1>Rezept importieren</h1>
        <form method="post">
            <input type="url" name="sitc_recipe_url" placeholder="Rezept-URL eingeben" size="60" required><br><br>

            <h3>Kategorien auswählen</h3>
            <?php
            $cats = get_categories(['hide_empty'=>0]);
            foreach ($cats as $cat) {
                echo '<label style="display:block;margin:2px 0;"><input type="checkbox" name="sitc_cats[]" value="' . esc_attr($cat->term_id) . '"> ' . esc_html($cat->name) . '</label>';
            }
            ?>
            <br>
            <label>Neue Kategorien (kommagetrennt):<br>
                <input type="text" name="sitc_newcats" size="60" placeholder="z. B. Indisch, Abendessen">
            </label><br><br>

            <label>
                <input type="checkbox" name="sitc_debug" value="1"> Debug-Ausgabe anzeigen
            </label><br><br>

            <?php submit_button('Import starten'); ?>
        </form>
        <?php
        if (!empty($_POST['sitc_recipe_url'])) {
            $url = esc_url_raw($_POST['sitc_recipe_url']);
            // Use v2 parser that returns legacy + rich meta
            $recipe = sitc_parse_recipe_from_url_v2($url);

            if ($recipe) {
                // Debug-Ausgabe
                if (!empty($_POST['sitc_debug'])) {
                    echo '<div class="notice notice-info"><p><strong>Debug-Ausgabe der Parser-Daten:</strong></p><pre style="max-height:300px;overflow:auto;background:#f9f9f9;border:1px solid #ccc;padding:10px;">';
                    print_r($recipe);
                    echo '</pre></div>';
                }

                // Beitrag anlegen
                $post_id = wp_insert_post([
                    'post_title'   => !empty($recipe['title']) ? $recipe['title'] : 'Unbenanntes Rezept',
                    'post_content' => !empty($recipe['description']) ? $recipe['description'] : '',
                    'post_status'  => 'publish',
                    'post_type'    => 'post'
                ]);

                if ($post_id && !is_wp_error($post_id)) {
                    // Kategorien sammeln
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

                    // Metadaten schreiben
                    if (!empty($recipe['ingredients_struct'])) update_post_meta($post_id, '_sitc_ingredients_struct', $recipe['ingredients_struct']);
                    if (!empty($recipe['instructions']))       update_post_meta($post_id, '_sitc_instructions',       $recipe['instructions']);
                    if (isset($recipe['yield_raw']))           update_post_meta($post_id, '_sitc_yield_raw',          $recipe['yield_raw']);
                    if (isset($recipe['yield_num']))           update_post_meta($post_id, '_sitc_yield_num',          $recipe['yield_num']);
                    update_post_meta($post_id, '_sitc_source_url', !empty($recipe['source_url']) ? $recipe['source_url'] : $url);
                    // Rich parse meta
                    if (!empty($recipe['meta'])) {
                        $meta = $recipe['meta'];
                        if (!empty($meta['schema_recipe'])) update_post_meta($post_id, '_sitc_schema_recipe_json', wp_json_encode($meta['schema_recipe']));
                        if (array_key_exists('confidence', $meta)) update_post_meta($post_id, '_sitc_confidence', (float)$meta['confidence']);
                        if (!empty($meta['sources']))        update_post_meta($post_id, '_sitc_sources_json', wp_json_encode($meta['sources']));
                        if (!empty($meta['flags']))          update_post_meta($post_id, '_sitc_flags_json', wp_json_encode($meta['flags']));
                    }

                    // Featured Image
                    $image_url = !empty($recipe['image']) ? $recipe['image'] : '';
                    sitc_set_featured_image($post_id, $image_url, $all_cats);

                    echo '<div class="notice notice-success"><p>Rezept erfolgreich erstellt! ID: ' . (int)$post_id . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Fehler beim Erstellen des Beitrags.</p></div>';
                }
            } else {
                echo '<div class="notice notice-error"><p>Keine Rezeptdaten gefunden!</p></div>';
            }
        }
        ?>
    </div>
    <?php
}
