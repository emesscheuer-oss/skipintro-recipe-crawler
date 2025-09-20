<?php

namespace SITC\Admin;

if (!defined('ABSPATH')) exit;

class Validation_Page
{
    const SLUG = 'sitc-parser-validation';
    const CAP  = 'edit_posts';
    const NONCE_ACTION = 'sitc_parser_validation_action';
    const NONCE_NAME   = 'sitc_parser_validation_nonce';

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_init', [self::class, 'maybe_send_json']);
        add_action('admin_post_sitc_clear_debug_log', [self::class, 'handle_clear_debug_log']);
    }

    public static function register_menu(): void
    {
        if (!is_admin()) return;
        add_submenu_page(
            'edit.php',
            'Recipe Crawler – Parser-Validierung',
            'Validierung',
            self::CAP,
            self::SLUG,
            [self::class, 'render_page']
        );
    }

    public static function handle_clear_debug_log(): void
    {
        if (!current_user_can('manage_options')) {
            wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
            exit;
        }
        if (empty($_GET['_wpnonce']) || !wp_verify_nonce((string)$_GET['_wpnonce'], 'sitc_clear_debug_log')) {
            wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php'));
            exit;
        }
        try {
            if (function_exists('sitc_parser_debug_logging_enabled') && sitc_parser_debug_logging_enabled()) {
                $u = wp_upload_dir();
                $file = rtrim($u['basedir'] ?? '', '/\\') . '/skipintro-recipe-crawler/debug.log';
                if (is_file($file)) {
                    @file_put_contents($file, '');
                }
            }
        } catch (\Throwable $e) {
            error_log('SITC clear debug log error: ' . $e->getMessage());
        }
        wp_safe_redirect(admin_url('edit.php?page=' . self::SLUG . '&view=debug'));
        exit;
    }

    public static function render_page(): void
    {
        if (!current_user_can(self::CAP)) {
            wp_die(__('Insufficient permissions', 'skipintro'));
        }
        $view = isset($_GET['view']) ? (string)$_GET['view'] : 'tests';
        $engine = isset($_REQUEST['engine']) ? (string)$_REQUEST['engine'] : 'auto';
        if (!in_array($engine, ['legacy','mod','auto'], true)) $engine = 'auto';
        $didRun = isset($_POST['sitc_pv_run']) && check_admin_referer(self::NONCE_ACTION, self::NONCE_NAME);

        // Scoped styles (no global enqueues)
        echo '<div class="wrap">';
        echo '<h1>Recipe Crawler – Parser-Validierung</h1>';
        echo '<p>Führt Parser-Tests über Fixtures im Parser-Lab aus. Keine Änderungen am Frontend.</p>';

        // Tabs (only show Debug-Log when logging active)
        $tabs = [ 'tests' => 'Tests', 'debug' => 'Debug-Log' ];
        $loggingActive = function_exists('sitc_parser_debug_logging_enabled') && sitc_parser_debug_logging_enabled();
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            if ($key === 'debug' && !$loggingActive) continue;
            $url = add_query_arg(['page'=>self::SLUG, 'view'=>$key], admin_url('edit.php'));
            $cls = ($view === $key) ? ' nav-tab-active' : '';
            echo '<a class="nav-tab' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        if ($view === 'debug') {
            self::render_debug_log_view();
            echo '</div>';
            return;
        }

        // Form
        echo '<form method="post" action="">';
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        echo '<table class="form-table" role="presentation"><tbody>';
        echo '<tr><th scope="row"><label for="sitc_pv_engine">Engine</label></th><td>';
        echo '<select id="sitc_pv_engine" name="engine">';
        foreach ([ 'auto' => 'auto', 'legacy' => 'legacy', 'mod' => 'mod' ] as $val => $label) {
            $sel = selected($engine, $val, false);
            echo '<option value="' . esc_attr($val) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td></tr>';
        echo '</tbody></table>';
        submit_button('Parser-Tests starten', 'primary', 'sitc_pv_run');
        echo '</form>';

        // JSON link with nonce
        $json_url = add_query_arg([
            'page' => self::SLUG,
            'format' => 'json',
            'engine' => $engine,
            '_wpnonce' => wp_create_nonce(self::NONCE_ACTION),
        ], admin_url('edit.php'));
        echo '<p><a href="' . esc_url($json_url) . '">JSON-Output anzeigen</a></p>';

        // Results
        if ($didRun) {
            $report = ValidationRunner::run($engine);
            self::render_report_html($report);
        }

        // Inline styles limited to this page
        echo '<style>
            .sitc-val-summary{margin:10px 0;padding:10px;border-radius:6px;border:1px solid #ddd;background:#fafafa}
            .sitc-val-table{border-collapse:collapse;width:100%;max-width:1100px;margin-top:10px}
            .sitc-val-table th,.sitc-val-table td{border:1px solid #ddd;padding:6px 8px;vertical-align:top}
            .sitc-val-table thead th{background:#f6f6f6}
            .ok{color:#2e7d32}
            .warn{color:#e69500}
            .fail{color:#c62828}
            .sitc-val-msg{color:#555}
        </style>';

        echo '</div>';
    }

    private static function render_report_html(array $report): void
    {
        $tot = $report['totals'] ?? [];
        $sum = sprintf('Total: %d | Failures: %d | Warnings: %d | Duration: %d ms',
            (int)($tot['total'] ?? 0), (int)($tot['failures'] ?? 0), (int)($tot['warnings'] ?? 0), (int)($tot['duration_ms'] ?? 0)
        );
        echo '<div class="sitc-val-summary"><strong>Engine:</strong> ' . esc_html((string)($report['engine'] ?? 'auto')) . ' &nbsp; ' . esc_html($sum) . '</div>';

        if ((int)($tot['total'] ?? 0) === 0) {
            echo '<p class="description">Keine Fixtures gefunden unter <code>tools/parser_lab/fixtures/</code>.</p>';
            return;
        }

        echo '<table class="sitc-val-table"><thead><tr>'
           . '<th>Fixture</th><th>Status</th><th>Messages</th>'
           . '</tr></thead><tbody>';
        foreach ((array)($report['fixtures'] ?? []) as $fx) {
            $name = (string)($fx['name'] ?? '');
            $st   = (string)($fx['status'] ?? 'ok');
            $cls  = in_array($st, ['ok','warn','fail'], true) ? $st : 'ok';
            $msgs = (array)($fx['messages'] ?? []);
            echo '<tr>'
               . '<td><code>' . esc_html($name) . '</code></td>'
               . '<td class="' . esc_attr($cls) . '"><strong>' . esc_html($st) . '</strong></td>'
               . '<td class="sitc-val-msg">' . esc_html(implode("; ", array_map('strval', $msgs))) . '</td>'
               . '</tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_debug_log_view(): void
    {
        if (!(function_exists('sitc_parser_debug_logging_enabled') && sitc_parser_debug_logging_enabled())) {
            echo '<p>' . esc_html__('Debug-Logging ist nicht aktiv.', 'skipintro') . '</p>';
            return;
        }
        $u = wp_upload_dir();
        $file = rtrim($u['basedir'] ?? '', '/\\') . '/skipintro-recipe-crawler/debug.log';
        $size = (is_file($file) ? (int)filesize($file) : 0);
        $linesOpt = isset($_GET['lines']) ? max(100, min(500, (int)$_GET['lines'])) : 250;
        $tail = function_exists('sitc_debug_tail') ? sitc_debug_tail($linesOpt, 200*1024) : [];

        echo '<div class="box" style="margin-top:12px">';
        echo '<form method="get" action="">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::SLUG) . '">';
        echo '<input type="hidden" name="view" value="debug">';
        echo '<strong>Log:</strong> ' . esc_html($file) . ' &nbsp; (' . esc_html(number_format_i18n($size)) . ' bytes, Rotation > 1MB)';
        echo '<div style="margin-top:8px">';
        echo '<label>Letzte Zeilen: <select name="lines">';
        foreach ([100,250,500] as $n) {
            $sel = selected($linesOpt, $n, false);
            echo '<option value="' . $n . '" ' . $sel . '>' . $n . '</option>';
        }
        echo '</select></label> ';
        echo '<button class="button">Neu laden</button> ';
        echo '</div>';
        echo '</form>';
        echo '</div>';

        echo '<div style="margin:10px 0">';
        if ($tail) {
            $esc = array_map('esc_html', $tail);
            echo '<pre class="sitc-log-preview">' . implode("\n", $esc) . '</pre>';
        } else {
            echo '<p><em>' . esc_html__('Kein Debug-Log verfügbar (Logging inaktiv oder Datei leer).', 'skipintro') . '</em></p>';
        }
        echo '</div>';

        // Clear log (manage_options)
        if (current_user_can('manage_options')) {
            $clrUrl = wp_nonce_url(add_query_arg(['action'=>'sitc_clear_debug_log'], admin_url('admin-post.php')), 'sitc_clear_debug_log');
            echo '<p><a href="' . esc_url($clrUrl) . '" class="button button-secondary">Log löschen</a></p>';
        }

        echo '<style>.sitc-log-preview{max-height:600px;overflow:auto;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;background:#111;color:#eee;padding:8px;border-radius:6px;border:1px solid #ddd}</style>';
    }

    public static function maybe_send_json(): void
    {
        if (!is_admin()) return;
        if (!isset($_GET['page']) || (string)$_GET['page'] !== self::SLUG) return;
        $format = isset($_GET['format']) ? (string)$_GET['format'] : '';
        if ($format !== 'json') return;

        if (!current_user_can(self::CAP)) {
            status_header(403);
            wp_die(__('Forbidden', 'skipintro'));
        }
        // Nonce check for GET-JSON link (security requirement)
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce((string)$_GET['_wpnonce'], self::NONCE_ACTION)) {
            status_header(403);
            wp_die(__('Invalid nonce', 'skipintro'));
        }

        $engine = isset($_GET['engine']) ? (string)$_GET['engine'] : 'auto';
        if (!in_array($engine, ['legacy','mod','auto'], true)) $engine = 'auto';

        $report = ValidationRunner::run($engine);

        // Emit JSON
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode([
            'status' => (string)($report['status'] ?? 'ok'),
            'totals' => (array)($report['totals'] ?? []),
            'engine' => (string)($report['engine'] ?? 'auto'),
            'fixtures' => (array)($report['fixtures'] ?? []),
            'env' => (array)($report['env'] ?? []),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
