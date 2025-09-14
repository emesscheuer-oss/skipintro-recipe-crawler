<?php
declare(strict_types=1);

// Web Auto-Eval (no CLI): Compare Legacy vs Modular engines.

$debug = isset($_GET['debug']) && $_GET['debug'] !== '0';
if ($debug) {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

header('Content-Type: text/html; charset=utf-8');

const FIXTURE_DIR = __DIR__ . '/fixtures';

// Plugin root and WordPress bootstrap
$pluginRoot = realpath(__DIR__ . '/../../');
$wpContent  = dirname($pluginRoot ?: __DIR__, 1);
$siteRoot   = dirname($wpContent, 1);
$wpLoad     = null;
$candidates = [ $siteRoot . '/wp-load.php', dirname($pluginRoot ?: __DIR__, 2) . '/wp-load.php', $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' ];
foreach ($candidates as $cand) { if ($cand && is_file($cand)) { $wpLoad = $cand; break; } }

safe_html_head('Auto-Eval (Web)');

try {
    if (!$wpLoad || !is_file($wpLoad)) { throw new RuntimeException('wp-load.php nicht gefunden.'); }
    require_once $wpLoad;

    // Load parser
    $parserFile = $pluginRoot . '/includes/parser.php';
    if (is_file($parserFile)) require_once $parserFile;

    // Fixtures
    $fixtureRel = isset($_GET['f']) ? (string)$_GET['f'] : '';
    $fixtures = [];
    if ($fixtureRel !== '') {
        $fixtures[] = $fixtureRel;
    } else {
        $dir = realpath(FIXTURE_DIR);
        if (!$dir) throw new RuntimeException('Fixture-Ordner nicht gefunden: ' . FIXTURE_DIR);
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['html','json','jsonld'], true)) continue;
            $abs = $file->getRealPath();
            $rel = ltrim(str_replace('\\', '/', substr((string)$abs, strlen((string)$pluginRoot))), '/');
            $fixtures[] = $rel;
        }
        sort($fixtures, SORT_NATURAL);
    }

    echo '<h1>Auto-Eval (Web)</h1>';
    echo '<p><a href="run.php">&larr; Zurück zum Parser-Lab</a></p>';

    if (!$fixtures) { echo warn_box('Keine Fixtures gefunden.'); safe_html_end(); exit; }

    $outDir = $pluginRoot . '/tools/parser_lab/out';
    if (!is_dir($outDir)) { @mkdir($outDir, 0777, true); }
    $writable = is_dir($outDir) && is_writable($outDir);

    echo '<table class="grid"><thead><tr><th>Fixture</th><th>Legacy (count)</th><th>Modular (count)</th><th>Status</th></tr></thead><tbody>';

    foreach ($fixtures as $rel) {
        $abs = $pluginRoot . '/' . $rel;
        $rowStatus = '❌';
        $countL = 0; $countM = 0; $L = null; $M = null; $err = '';
        try {
            if (!is_file($abs)) throw new RuntimeException('Fixture nicht gefunden: ' . $rel);
            $raw = file_get_contents($abs);
            if ($raw === false) throw new RuntimeException('Lesefehler: ' . $rel);
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            $html = in_array($ext, ['json','jsonld'], true)
                ? "<!doctype html><meta charset='utf-8'>\n<script type=\"application/ld+json\">" . $raw . "</script>"
                : $raw;

            // Legacy
            if (function_exists('sitc_set_ing_engine')) sitc_set_ing_engine('legacy');
            $resL = parseRecipe($html, 'https://fixtures.local/' . basename($abs));
            $L = $resL['recipe']['recipeIngredient'] ?? [];
            $countL = is_array($L) ? count($L) : 0;

            // Modular
            if (function_exists('sitc_set_ing_engine')) sitc_set_ing_engine('mod');
            $resM = parseRecipe($html, 'https://fixtures.local/' . basename($abs));
            $M = $resM['recipe']['recipeIngredient'] ?? [];
            $countM = is_array($M) ? count($M) : 0;

            // Classify
            if ($countL === 0 && $countM === 0) {
                $rowStatus = '❌';
            } else {
                $eq = json_encode($L, JSON_UNESCAPED_UNICODE) === json_encode($M, JSON_UNESCAPED_UNICODE);
                if ($eq) $rowStatus = '✅ identisch';
                elseif ($countL === 0 && $countM > 0) $rowStatus = '✅ verbessert';
                else $rowStatus = '⚠️ unterschiedlich';
            }

            // Persist JSON if possible
            if ($writable) {
                $slug = preg_replace('~[^a-z0-9_.-]+~i', '_', basename($abs));
                @file_put_contents($outDir . '/legacy_' . $slug . '.json', json_encode($L, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
                @file_put_contents($outDir . '/mod_' . $slug . '.json', json_encode($M, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            }
        } catch (Throwable $e) { $err = $e->getMessage(); }

        echo '<tr>'
           . '<td><strong>' . safe($rel) . '</strong></td>'
           . '<td>' . safe((string)$countL) . '</td>'
           . '<td>' . safe((string)$countM) . '</td>'
           . '<td>' . safe($rowStatus) . ($err?('<br><small>'.safe($err).'</small>'):'') . '</td>'
           . '</tr>';

        // Details JSON
        echo '<tr><td colspan="4">'
           . '<details><summary>Details (Legacy)</summary><pre>' . safe(json_encode($L, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . '</pre></details>'
           . '<details><summary>Details (Modular)</summary><pre>' . safe(json_encode($M, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) . '</pre></details>'
           . '</td></tr>';
    }

    echo '</tbody></table>';

    // Simple compare report
    if ($writable) {
        @file_put_contents($outDir . '/compare_report.txt', "Web Auto-Eval executed at " . date('c') . "\n");
    }

} catch (Throwable $e) {
    echo err_box('Fehler: ' . safe($e->getMessage()));
}

safe_html_end();

/* Helpers */
function safe(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function styles(): string {
    return <<<CSS
<style>
body{font:14px/1.45 system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px}
.grid{border-collapse:collapse;width:100%;max-width:1100px}
.grid th,.grid td{border:1px solid #ccc;padding:6px 8px;vertical-align:top}
.grid thead th{background:#f6f6f6}
.box{border:1px solid #ddd;background:#fafafa;padding:8px 10px;border-radius:6px;margin:10px 0}
pre{background:#f9f9f9;border:1px solid #eee;padding:8px;overflow:auto}
a{color:#06c;text-decoration:none} a:hover{text-decoration:underline}
</style>
CSS;
}
function safe_html_head(string $title): void {
    echo "<!doctype html><meta charset='utf-8'><title>" . safe($title) . "</title>" . styles();
}
function safe_html_end(): void { /* noop */ }
function warn_box(string $msg): string { return '<div class="box"><strong>Hinweis:</strong> ' . safe($msg) . '</div>'; }
function self_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path   = $_SERVER['REQUEST_URI'] ?? '/';
    return $scheme . '://' . $host . $path;
}
