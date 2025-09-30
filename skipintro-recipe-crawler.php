<?php
/*
Plugin Name: Skipintro Recipe Crawler
Description: Importiert/aktualisiert Rezepte und rendert sie im Theme. Parser v2 + Normalizer; Refresh schreibt Schema & Struct neu.
Version: 0.6.01
Author: Skipintro
License: GPL-2.0+
Text Domain: skipintro
*/

if (!defined('ABSPATH')) exit;

// FRONTEND Output-Guard: entfernt fuehrende Whitespaces/BOM-Ausgaben vor dem ersten Output.
add_action('init', function () {
    if (is_admin()) return;
    if (!defined('SITC_OB_GUARD')) define('SITC_OB_GUARD', true);
    if (SITC_OB_GUARD && !ob_get_level()) {
        ob_start(function($buf){
            static $seen = false;
            if (!$seen) {
                $seen = true;
                // Nur fuehrende Whitespaces kappen; echten Inhalt unveraendert ausgeben.
                $trim = ltrim($buf);
                return $trim;
            }
            return $buf;
        });
        add_action('shutdown', function(){ if (ob_get_level()) @ob_end_flush(); }, 9999);
    }
}, 1);

// Bootstrap: lade Admin-Only vs. Frontend sauber getrennt.
add_action('plugins_loaded', function () {
    $base = plugin_dir_path(__FILE__);

    // Settings needed in both admin and frontend (feature flags/options)
    require_once $base . 'includes/settings.php';
    // Debug logger (dev-only)
    require_once $base . 'includes/debug.php';
    // Media helpers (featured image)
    require_once $base . 'includes/media.php';
    require_once $base . 'includes/ingest_postprocess.php';
    // Toggle for legacy debug endpoints (enable manually when needed)
    if (!defined('SITC_DEBUG_TOOLS')) {
        define('SITC_DEBUG_TOOLS', false);
    }

    // Global engine query override (no admin gating): ?engine=legacy|mod|auto
    if (isset($_GET['engine'])) {
        $e = (string)$_GET['engine'];
        if (function_exists('sitc_set_ing_engine')) { sitc_set_ing_engine($e); }
    }

    if (is_admin()) {
        // Admin: Admin-Page, Refresh (Admin-only Hooks).
        // Also load parser for admin flows (refresh, validation)
        require_once $base . 'includes/parser.php';
        require_once $base . 'includes/admin-page.php';
        require_once $base . 'includes/refresh.php';
		require_once __DIR__ . '/includes/refresh_v2_helpers.php';
        // Admin: Parser Validation page (Parser-Lab orchestration)
        require_once $base . 'includes/Admin/ValidationRunner.php';
        require_once $base . 'includes/Admin/ValidationPage.php';

        // Admin-Menue nur im Admin registrieren.
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

        // Init validation page (separate hook to keep order close to Importer)
        \SITC\Admin\Validation_Page::init();
    } else {
        // Frontend: Parser/Renderer/Assets/Frontend-Importer/AJAX, kein Admin-Code.
        require_once $base . 'includes/parser.php';
        require_once $base . 'includes/renderer.php';
        require_once $base . 'includes/assets.php';
        require_once $base . 'includes/ajax.php';
        require_once $base . 'includes/frontend-import.php';
        require_once $base . 'includes/featured-image.php';
		require_once $base . '/colibriwp-adjustments-and-features/colibriwp-features.php';
    }
});

