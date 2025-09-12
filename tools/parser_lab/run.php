<?php
declare(strict_types=1);

/**
 * Parser-Lab (Browser, WP-bootstrapped) – zeigt Fixtures und jagt sie durch den Parser.
 * Aufruf:
 *   .../wp-content/plugins/skipintro-recipe-crawler/tools/parser_lab/run.php
 *   .../wp-content/plugins/skipintro-recipe-crawler/tools/parser_lab/run.php?f=fixtures/butter_chicken.jsonld
 *   + debug: &debug=1
 */

$debug = isset($_GET['debug']) && $_GET['debug'] !== '0';
if ($debug) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

header('Content-Type: text/html; charset=utf-8');

const FIXTURE_DIR = __DIR__ . '/fixtures';

// Plugin-Root: .../wp-content/plugins/skipintro-recipe-crawler
$pluginRoot = realpath(__DIR__ . '/../../');
$wpContent  = dirname($pluginRoot, 1);                // .../wp-content
$siteRoot   = dirname($wpContent, 1);                 // WP Root (vermutlich)
$wpLoad     = null;

// Kandidaten für wp-load.php testen (verschiedene Hosting-Layouts abdecken)
$candidates = [
    $siteRoot . '/wp-load.php',
    dirname($pluginRoot, 2) . '/wp-load.php',         // falls pluginRoot abweicht
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
];
foreach ($candidates as $cand) {
    if ($cand && is_file($cand)) { $wpLoad = $cand; break; }
}

safe_html_head('Parser-Lab');

$diag = [
    'php'         => PHP_VERSION,
    'debug'       => $debug ? 'on' : 'off',
    'pluginRoot'  => $pluginRoot ?: '(not found)',
    'wpContent'   => $wpContent ?: '(not found)',
    'siteRoot'    => $siteRoot ?: '(not found)',
    'wpLoad'      => $wpLoad ?: '(not found)',
    'script'      => __FILE__,
];

// WordPress bootstrappen (für Parser & Helfer)
try {
    if (!$wpLoad || !is_file($wpLoad)) {
        throw new RuntimeException('wp-load.php nicht gefunden – bitte Pfade prüfen.');
    }
    require_once $wpLoad;
} catch (Throwable $e) {
    echo err_box('WP-Bootstrap-Fehler: ' . safe($e->getMessage()));
    echo diag_box($diag);
    safe_html_end();
    exit;
}

// Parser laden
$parserFile = $pluginRoot . '/includes/parser.php';
if (is_file($parserFile)) {
    require_once $parserFile;
}

// Prüfen, ob parseRecipe existiert (oder der Wrapper)
$haveParse = function_exists('parseRecipe');
$diag['have_parseRecipe'] = $haveParse ? 'yes' : 'no';

// Übersicht oder Detail?
$fixtureRel = isset($_GET['f']) ? (string)$_GET['f'] : '';

try {
    if ($fixtureRel === '') {
        echo h1('Parser-Lab – Fixtures');
        echo diag_box($diag);
        echo fixtures_list($pluginRoot);
        safe_html_end();
        exit;
    }

    // Fixture auflösen & lesen
    $fixtureAbs = realpath($pluginRoot . '/' . ltrim($fixtureRel, '/'));
    if (!$fixtureAbs || strpos($fixtureAbs, realpath(FIXTURE_DIR)) !== 0 || !is_file($fixtureAbs)) {
        throw new RuntimeException('Ungültige Fixture: ' . safe($fixtureRel));
    }

    $ext = strtolower(pathinfo($fixtureAbs, PATHINFO_EXTENSION));
    $raw = file_get_contents($fixtureAbs);
    if ($raw === false) {
        throw new RuntimeException('Konnte Fixture nicht lesen: ' . safe($fixtureRel));
    }

    // JSON/JSON-LD in HTML-Hülle verpacken, damit <script type=application/ld+json> gefunden wird
    if (in_array($ext, ['json', 'jsonld'], true)) {
        $html = "<!doctype html><meta charset='utf-8'>\n<script type=\"application/ld+json\">" . $raw . "</script>";
    } else {
        $html = $raw;
    }

    echo h1('Parser-Lab – ' . safe($fixtureRel));
    echo '<p><a href="' . safe(self_url(['f' => null])) . '">← Zur Übersicht</a></p>';
    echo diag_box($diag + [
        'fixture' => $fixtureRel,
        'ext'     => $ext,
    ]);

    if (!$haveParse) {
        throw new RuntimeException('Funktion parseRecipe(...) nicht gefunden. Bitte includes/parser.php prüfen.');
    }

    // Parser aufrufen
    $fauxUrl = 'https://fixtures.local/' . basename($fixtureAbs);
    $result  = parseRecipe($html, $fauxUrl);

    // Meta
    echo '<details open><summary><strong>Meta (Result)</strong></summary><pre>';
    echo safe(json_encode([
        'type'       => gettype($result),
        'keys'       => is_array($result) ? array_keys($result) : [],
        'has_recipe' => is_array($result) && isset($result['recipe']),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo '</pre></details>';

    // Zutaten
    $ingredients = is_array($result) ? ($result['recipe']['recipeIngredient'] ?? null) : null;
    if (!$ingredients) {
        echo warn_box('Keine Zutaten gefunden (recipe.recipeIngredient leer).');
        safe_html_end();
        exit;
    }

    echo '<table class="grid"><thead><tr>'
       . '<th>#</th><th>raw / struct</th><th>qty</th><th>unit</th><th>item</th><th>display (einfach)</th>'
       . '</tr></thead><tbody>';

    $i = 0;
    foreach ($ingredients as $row) {
        $i++;
        if (is_array($row)) {
            $raw  = $row['raw']  ?? '';
            $qty  = $row['qty']  ?? '';
            $unit = $row['unit'] ?? '';
            $item = $row['item'] ?? '';
            $disp = trim(implode(' ', array_filter([(string)$qty, (string)$unit, (string)$item], 'strlen')));
            echo '<tr><td>' . $i . '</td>'
               . '<td><code>struct</code><br><small>' . safe($raw ?: json_encode($row, JSON_UNESCAPED_UNICODE)) . '</small></td>'
               . '<td>' . safe((string)$qty) . '</td>'
               . '<td>' . safe((string)$unit) . '</td>'
               . '<td>' . safe((string)$item) . '</td>'
               . '<td><strong>' . safe($disp) . '</strong></td>'
               . '</tr>';
        } else {
            $raw = (string)$row;
            echo '<tr><td>' . $i . '</td>'
               . '<td><code>raw</code><br><small>' . safe($raw) . '</small></td>'
               . '<td>—</td><td>—</td><td>—</td>'
               . '<td><strong>' . safe($raw) . '</strong></td>'
               . '</tr>';
        }
    }
    echo '</tbody></table>';

} catch (Throwable $e) {
    echo err_box($e->getMessage());
    echo diag_box($diag);
}

safe_html_end();

/* ===== Helpers ===== */
function safe(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function h1(string $s): string { return '<h1>' . safe($s) . '</h1>'; }
function styles(): string {
    return <<<CSS
<style>
body{font:14px/1.45 system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px}
.grid{border-collapse:collapse;width:100%;max-width:1100px}
.grid th,.grid td{border:1px solid #ccc;padding:6px 8px;vertical-align:top}
.grid thead th{background:#f6f6f6}
.box{border:1px solid #ddd;background:#fafafa;padding:8px 10px;border-radius:6px;margin:10px 0}
.box.err{border-color:#d33;background:#fff0f0}
.box.warn{border-color:#e6a700;background:#fff8e1}
pre{background:#f9f9f9;border:1px solid #eee;padding:8px;overflow:auto}
a{color:#06c;text-decoration:none} a:hover{text-decoration:underline}
</style>
CSS;
}
function safe_html_head(string $title): void {
    echo "<!doctype html><meta charset='utf-8'><title>" . safe($title) . "</title>" . styles();
}
function safe_html_end(): void { /* noop */ }
function err_box(string $msg): string { return '<div class="box err"><strong>Fehler:</strong> ' . safe($msg) . '</div>'; }
function warn_box(string $msg): string { return '<div class="box warn"><strong>Hinweis:</strong> ' . safe($msg) . '</div>'; }
function diag_box(array $kv): string {
    $h = '<div class="box"><strong>Diagnose</strong><ul style="margin:6px 0 0 18px">';
    foreach ($kv as $k => $v) { $h .= '<li><code>' . safe((string)$k) . '</code>: ' . safe((string)$v) . '</li>'; }
    return $h . '</ul></div>';
}
function fixtures_list(string $pluginRoot): string {
    $dir = realpath(FIXTURE_DIR);
    if (!$dir) return warn_box('Fixture-Ordner nicht gefunden: ' . safe(FIXTURE_DIR));
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    $rows = [];
    foreach ($rii as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) continue;
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['html','json','jsonld'], true)) continue;
        $abs = $file->getRealPath();
        $rel = ltrim(str_replace('\\', '/', substr($abs, strlen($pluginRoot))), '/'); // relativ zum Plugin-Root
        $rows[] = $rel;
    }
    sort($rows, SORT_NATURAL);
    if (!$rows) return warn_box('Keine Fixtures (.html/.json/.jsonld) gefunden.');
    $h = '<ul>';
    foreach ($rows as $rel) {
        $h .= '<li><a href="' . safe(self_url(['f' => $rel])) . '">' . safe($rel) . '</a></li>';
    }
    return $h . '</ul>';
}
function self_url(array $override = []): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = $_SERVER['REQUEST_URI'] ?? '/';
    $parts  = parse_url($path);
    $q      = [];
    if (!empty($parts['query'])) parse_str($parts['query'], $q);
    foreach ($override as $k => $v) {
        if ($v === null) unset($q[$k]); else $q[$k] = $v;
    }
    $query = $q ? ('?' . http_build_query($q)) : '';
    return $scheme . '://' . $host . ($parts['path'] ?? '/') . $query;
}
