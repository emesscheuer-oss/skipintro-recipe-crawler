<?php
if (!defined('ABSPATH')) exit;

/**
 * Lädt CSS & JS für das Frontend (Rezepte).
 */
function sitc_enqueue_frontend_assets() {
    // Nur auf einzelnen Beitragsseiten mit Rezept-Daten laden
    if (!is_singular('post')) return;
    $post_id = get_queried_object_id();
    if (!$post_id) return;
    $has_ing   = get_post_meta($post_id, '_sitc_ingredients_struct', true);
    $has_instr = get_post_meta($post_id, '_sitc_instructions', true);
    if (empty($has_ing) && empty($has_instr)) return;

    // Basis-Plugin-URL
    $base = plugin_dir_url(__DIR__);

    // CSS
    wp_enqueue_style(
        'sitc-recipe-css',
        $base . 'assets/css/recipe.css',
        [],
        filemtime(plugin_dir_path(__DIR__) . 'assets/css/recipe.css')
    );

    // Google Material Symbols (für Icons)
    wp_enqueue_style(
        'sitc-material-icons',
        'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined',
        [],
        null
    );

    // JS
    wp_enqueue_script(
        'sitc-recipe-js',
        $base . 'assets/js/recipe-frontend.js',
        ['jquery'],
        filemtime(plugin_dir_path(__DIR__) . 'assets/js/recipe-frontend.js'),
        true
    );

    // Übergibt Ajax-URL für evtl. Aktionen (z. B. Trash-Button später)
    wp_localize_script('sitc-recipe-js', 'sitcRecipe', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
}
add_action('wp_enqueue_scripts', 'sitc_enqueue_frontend_assets');

// Minimaler CSS-Fallback gegen unerwünschten Top-Whitespace im Frontend (kein Admin-Bar Offset)
add_action('wp_enqueue_scripts', function(){
    if (is_admin()) return;
    if (function_exists('is_admin_bar_showing') && is_admin_bar_showing()) return;
    $css = 'html{margin-top:0!important} body{margin-top:0!important;padding-top:0!important}';
    wp_register_style('sitc-whitespace-fix', false);
    wp_enqueue_style('sitc-whitespace-fix');
    wp_add_inline_style('sitc-whitespace-fix', $css);
}, 99);
