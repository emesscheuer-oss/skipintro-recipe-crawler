<?php
/*
Plugin Name: Skip Intro Recipe Crawler
Description: Importiert Rezepte von externen URLs (extrahiert JSON-LD Recipe-Daten) und speichert sie als Beiträge mit Kategorien & skalierbaren Zutaten.
Version: 0.5.9
Author: SKIP INTRO OHG
*/

if (!defined('ABSPATH')) exit;

// Admin-Menü: Unter "Beiträge" → "Importieren"
add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php',
        'Rezepte importieren',
        'Importieren',
        'manage_options',
        'skipintro-recipe-importer',
        'sitc_render_importer_page'
    );
});

// Module laden
require_once plugin_dir_path(__FILE__) . 'includes/settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-page.php';
require_once plugin_dir_path(__FILE__) . 'includes/parser.php';
require_once plugin_dir_path(__FILE__) . 'includes/renderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/featured-image.php';
require_once plugin_dir_path(__FILE__) . 'includes/assets.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/frontend-import.php';
require_once plugin_dir_path(__FILE__) . 'includes/refresh.php';
