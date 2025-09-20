<?php
// Dev-only exporter: dumps current raw ingredient lines from a post into Golden fixtures
// Usage:
//   tools/parser_lab/export_golden.php?post_id=123
//   tools/parser_lab/export_golden.php?ids=1,2,3

header('Content-Type: text/plain; charset=utf-8');

// Resolve plugin root and wp-load.php
$pluginRoot = realpath(__DIR__ . '/../../');
$wpContent  = dirname($pluginRoot ?: __DIR__, 1);
$siteRoot   = dirname($wpContent, 1);
$wpLoad     = null;
$candidates = [ $siteRoot . '/wp-load.php', dirname($pluginRoot ?: __DIR__, 2) . '/wp-load.php', $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' ];
foreach ($candidates as $cand) { if ($cand && is_file($cand)) { $wpLoad = $cand; break; } }
if (!$wpLoad || !is_file($wpLoad)) { http_response_code(500); echo "wp-load.php nicht gefunden.\n"; exit; }
require_once $wpLoad;

// Dev guard
$dev = (defined('WP_DEBUG') && WP_DEBUG) || (defined('SITC_DEV_MODE') && SITC_DEV_MODE) || (function_exists('sitc_is_dev_mode') && sitc_is_dev_mode());
if (!$dev) { http_response_code(403); echo "Forbidden (dev-only).\n"; exit; }

// Helpers
function sitc_golden_slug_from_post(int $post_id): string {
    $post = get_post($post_id);
    if ($post && !is_wp_error($post)) {
        $slug = $post->post_name ?: sanitize_title((string)$post->post_title);
        return $slug !== '' ? $slug : ('post-' . $post_id);
    }
    return 'post-' . $post_id;
}
function sitc_collect_raw_lines_for_post(int $post_id): array {
    $lines = [];
    // Prefer schema recipeIngredient strings if present
    $schema_json = get_post_meta($post_id, '_sitc_schema_recipe_json', true);
    if (is_string($schema_json) && $schema_json !== '') {
        $schema = json_decode($schema_json, true);
        if (is_array($schema) && !empty($schema['recipeIngredient'])) {
            $src = $schema['recipeIngredient'];
            if (!is_array($src)) $src = [$src];
            foreach ($src as $s) { if (is_string($s)) { $t = trim($s); if ($t !== '') $lines[] = $t; } }
        }
    }
    // Fallback to stored structured ingredients joined as a single line
    if (!$lines) {
        $ings = get_post_meta($post_id, '_sitc_ingredients_struct', true);
        if (is_array($ings)) {
            foreach ($ings as $ing) {
                if (!is_array($ing)) continue;
                $qty  = trim((string)($ing['qty'] ?? ''));
                $unit = trim((string)($ing['unit'] ?? ''));
                $name = trim((string)($ing['name'] ?? ''));
                $line = trim(($qty !== '' ? $qty.' ' : '') . ($unit !== '' ? $unit.' ' : '') . $name);
                if ($line !== '') $lines[] = $line;
            }
        }
    }
    return array_values(array_filter($lines, function($s){ return is_string($s) && trim($s) !== ''; }));
}

// Determine target dir YYYYMMDD
$dateDir = gmdate('Ymd');
$targetDir = $pluginRoot . '/tools/parser_lab/fixtures/golden/' . $dateDir;
if (!is_dir($targetDir)) { @mkdir($targetDir, 0777, true); }
if (!is_dir($targetDir) || !is_writable($targetDir)) { http_response_code(500); echo "Zielordner nicht beschreibbar: $targetDir\n"; exit; }

// Parse input: single post_id or batch ids
$idsParam = isset($_GET['ids']) ? (string)$_GET['ids'] : '';
$postIdParam = isset($_GET['post_id']) ? (string)$_GET['post_id'] : '';
$ids = [];
if ($idsParam !== '') {
    foreach (explode(',', $idsParam) as $x) { $n = (int)trim($x); if ($n > 0) $ids[] = $n; }
}
if (!$ids && $postIdParam !== '') { $n = (int)$postIdParam; if ($n > 0) $ids[] = $n; }
if (!$ids) { echo "Parameter fehlt: post_id oder ids=1,2,3\n"; exit; }

$exported = 0; $skipped = 0;
foreach (array_unique($ids) as $pid) {
    $lines = sitc_collect_raw_lines_for_post($pid);
    if (!$lines) { $skipped++; echo "Post $pid: keine Zutatenzeilen gefunden.\n"; continue; }
    $slug = sitc_golden_slug_from_post($pid);
    $base = $targetDir . '/' . $slug;
    $txtFile = $base . '.txt';
    $expFile = $base . '.expected.json';

    // Write .txt
    $ok1 = @file_put_contents($txtFile, implode("\n", $lines) . "\n");
    // Prepare expected skeleton
    $cases = [];
    foreach ($lines as $ln) {
        $cases[] = [ 'line' => (string)$ln, 'qty' => null, 'min_qty' => null, 'max_qty' => null, 'unit' => null, 'item' => null, 'note' => null ];
    }
    $json = json_encode([ 'cases' => $cases ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $ok2 = @file_put_contents($expFile, $json . "\n");

    if ($ok1 !== false && $ok2 !== false) {
        $exported++;
        echo "Exportiert: $slug (" . count($lines) . ") -> golden/$dateDir\n";
    } else {
        echo "Fehler beim Schreiben fÃ¼r Post $pid.\n";
    }
}
echo "Fertig. Exportiert: $exported, ohne Zeilen: $skipped\n";



