<?php

/**
 * Parser-Lab (Browser, WP-bootstrapped) ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“ zeigt Fixtures und jagt sie durch den Parser.
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

// Kandidaten fÃƒÆ’Ã‚Â¼r wp-load.php testen (verschiedene Hosting-Layouts abdecken)
$candidates = [
    $siteRoot . '/wp-load.php',
    dirname($pluginRoot, 2) . '/wp-load.php',         // falls pluginRoot abweicht
    $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
];
foreach ($candidates as $cand) {
    if ($cand && is_file($cand)) { $wpLoad = $cand; break; }
}

require_once __DIR__ . '/_ui_helpers.php';

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

// WordPress bootstrappen (fÃƒÆ’Ã‚Â¼r Parser & Helfer)
try {
    if (!$wpLoad || !is_file($wpLoad)) {
        throw new RuntimeException('wp-load.php nicht gefunden ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“ bitte Pfade prÃƒÆ’Ã‚Â¼fen.');
    }
    require_once $wpLoad;
} catch (Throwable $e) {
    sitc_error('WP-Bootstrap-Fehler: ' . $e->getMessage());
    echo diag_box($diag);
    safe_html_end();
    exit;
}

// Parser laden
$parserFile = $pluginRoot . '/includes/parser.php';
if (is_file($parserFile)) {
    require_once $parserFile;
}

// Engine toggle from URL (?engine=legacy|mod|auto)
$engine = isset($_GET['engine']) ? (string)$_GET['engine'] : '';
if ($engine !== '' && function_exists('sitc_set_ing_engine')) {
    sitc_set_ing_engine($engine);
}
$activeEngine = isset($GLOBALS['SITC_ING_ENGINE']) ? (string)$GLOBALS['SITC_ING_ENGINE'] : 'auto';
$isModActive = ($activeEngine === 'mod');

// PrÃƒÆ’Ã‚Â¼fen, ob parseRecipe existiert (oder der Wrapper)
$haveParse = function_exists('parseRecipe');
$diag['have_parseRecipe'] = $haveParse ? 'yes' : 'no';

// ÃƒÆ’Ã…â€œbersicht oder Detail?
$fixtureRel = isset($_GET['f']) ? (string)$_GET['f'] : '';
$doEval     = isset($_GET['eval']) && $_GET['eval'] === '1';

try {
    // Toolbar: engine toggle
    echo '<div class="box">'
       . '<strong>Engine:</strong> '
       . '<span class="badge">' . safe($activeEngine) . '</span>'
       . ' &nbsp; '
       . '<a href="' . safe(self_url(['engine'=>'legacy'])) . '">Legacy</a> | '
       . '<a href="' . safe(self_url(['engine'=>'mod'])) . '">Modular</a> | '
       . '<a href="' . safe(self_url(['engine'=>'auto'])) . '">Auto</a>'
       . '</div>';

    // DEV Smoke-Test: only when engine=mod selected explicitly
    if ($isModActive) {
        $__smoke = [
            'Ãƒâ€šÃ‚Â½ Bund Koriander, gehackt',
            '1/2 Zwiebel',
            '1 1/2 TL KreuzkÃƒÆ’Ã‚Â¼mmel',
            '2ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“3 Nelken',
            '0,50 StÃƒÆ’Ã‚Â¼ck Zitrone (Saft)'
        ];
        $rows = [];
        $anyDiagNotLoaded = false; $allQtyNull = true;
        foreach ($__smoke as $s) {
            $r = function_exists('sitc_ing_parse_line_mod') ? sitc_ing_parse_line_mod($s, 'de') : null;
            $diag = is_array($r) ? ($r['_diag'] ?? '') : '';
            if ($diag === 'mod_not_loaded') { $anyDiagNotLoaded = true; }
            $qty = is_array($r) ? ($r['qty'] ?? null) : null;
            if ($qty !== null && $qty !== '') { $allQtyNull = false; }
            $qtyStr = '';
            if (is_array($qty) && isset($qty['low'],$qty['high'])) {
                $qtyStr = rtrim(rtrim(sprintf('%.3f', (float)$qty['low']), '0'), '.') . 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“' . rtrim(rtrim(sprintf('%.3f', (float)$qty['high']), '0'), '.');
            } elseif ($qty !== null && $qty !== '') {
                $qtyStr = rtrim(rtrim(sprintf('%.3f', (float)$qty), '0'), '.');
            }
            $rows[] = [
                'qty'  => $qtyStr,
                'unit' => is_array($r) ? (string)($r['unit'] ?? '') : '',
                'item' => is_array($r) ? (string)($r['item'] ?? '') : '',
                'note' => is_array($r) ? ((string)($r['note'] ?? '')) : ''
            ];
        }
        echo '<div class="box"><strong>Smoke-Test (mod):</strong> ';
        echo '<ul style="margin:6px 0 0 18px">';
        foreach ($rows as $i => $rr) {
            $line = $__smoke[$i];
            $disp = trim(implode(' ', array_filter([$rr['qty'], $rr['unit'], $rr['item']], 'strlen')));
            if ($rr['note'] !== '') { $disp .= ', ' . $rr['note']; }
            echo '<li><code>' . safe($line) . '</code> ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ <strong>' . safe($disp) . '</strong></li>';
        }
        echo '</ul></div>';
        if ($anyDiagNotLoaded || $allQtyNull) {
            echo '<div class="box err"><strong>Modular-Pipeline nicht geladen</strong> ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â tokenize/qty/unit/note fehlen. '
               . 'Bitte <code>includes/parser.php</code>-Includes prÃƒÆ’Ã‚Â¼fen.</div>';
        }
    }

    // Auto-Eval mode: summary across 4 fixtures
    if ($doEval && $fixtureRel === '') {
        echo h1('Auto-Eval (4 Fixtures)');
        echo diag_box($diag);

        $norm_str = function(string $s): string {
            $s = mb_strtolower(trim($s), 'UTF-8');
            $s = preg_replace('/\s+/', ' ', $s);
            $from = ['ÃƒÆ’Ã‚Â¤','ÃƒÆ’Ã‚Â¶','ÃƒÆ’Ã‚Â¼','ÃƒÆ’Ã…Â¸','ÃƒÆ’Ã‚Â¡','ÃƒÆ’Ã‚Â ','ÃƒÆ’Ã‚Â¢','ÃƒÆ’Ã‚Â©','ÃƒÆ’Ã‚Â¨','ÃƒÆ’Ã‚Âª','ÃƒÆ’Ã‚Â­','ÃƒÆ’Ã‚Â¬','ÃƒÆ’Ã‚Â®','ÃƒÆ’Ã‚Â³','ÃƒÆ’Ã‚Â²','ÃƒÆ’Ã‚Â´','ÃƒÆ’Ã‚Âº','ÃƒÆ’Ã‚Â¹','ÃƒÆ’Ã‚Â»'];
            $to   = ['a','o','u','ss','a','a','a','e','e','e','i','i','i','o','o','o','u','u','u'];
            return str_replace($from, $to, $s);
        };
        $approx = function($a, $b, float $eps = 0.02): bool { return is_numeric($a) && is_numeric($b) && abs((float)$a-(float)$b) <= $eps; };
        $has_range = function($q): bool { return is_array($q) && isset($q['low'],$q['high']); };
        $unit_ok = function(?string $u, array $accepted) use ($norm_str): bool {
            $u = $norm_str((string)$u);
            foreach ($accepted as $acc) { if ($u === $norm_str($acc)) return true; }
            return false;
        };

        $fixtures = [
            'tools/parser_lab/fixtures/biryani.html' => [
                ['qty'=>0.5, 'unit'=>['bunch','bund'], 'item'=>'koriander'],
                ['range'=>[2,3], 'unit'=>[], 'item'=>'nelken'],
                ['qty'=>200, 'unit'=>['g'], 'item'=>'reis'],
                ['qty'=>0.5, 'unit'=>[], 'item'=>'zwiebel'],
                ['qty'=>1.5, 'unit'=>['tsp','tl'], 'item'=>'kreuz'],
            ],
            'tools/parser_lab/fixtures/haehnchen_biryani.html' => [
                ['qty'=>0.5, 'unit'=>['bunch','bund'], 'item'=>'koriander'],
                ['range'=>[2,3], 'unit'=>[], 'item'=>'nelken'],
                ['qty'=>200, 'unit'=>['g'], 'item'=>'reis'],
                ['qty'=>0.5, 'unit'=>[], 'item'=>'zwiebel'],
                ['qty'=>1.5, 'unit'=>['tsp','tl'], 'item'=>'kreuz'],
            ],
            'tools/parser_lab/fixtures/ente_chop_suey.html' => [
                ['qty'=>0.5, 'unit'=>[], 'item'=>'ente', 'note_has'=>['tk','vorgegart','aufgetaut']],
                ['qty'=>0.5, 'unit'=>[], 'item'=>'paprika', 'note_has'=>['rot']],
                ['qty'=>2,   'unit'=>[], 'item'=>'fruhlingszwiebeln'],
                ['qty'=>30,  'unit'=>['ml'], 'item'=>'wasser'],
            ],
            'tools/parser_lab/fixtures/butter_chicken.jsonld' => [
                ['qty'=>0.5, 'unit'=>['piece','stÃƒÆ’Ã‚Â¼ck'], 'item'=>'zitrone', 'note_has'=>['saft'], 'item_not_has'=>['.50','0,50','0.50']],
                ['qty'=>4,   'unit'=>['clove','zehe','zehen'], 'item'=>'knoblauch'],
                ['qty'=>60,  'unit'=>['ml'], 'item'=>'ÃƒÆ’Ã‚Â¶l'],
                ['qty'=>15,  'unit'=>['g'], 'item'=>'ingwer', 'note_has'=>['stÃƒÆ’Ã‚Â¼ck']],
                ['qty'=>250, 'unit'=>['g'], 'item'=>'hÃƒÆ’Ã‚Â¤hnchenbrust'],
                ['qty'=>1.5, 'unit'=>['tbsp','el'], 'item'=>'joghurt'],
                ['qty'=>1.5, 'unit'=>['tsp','tl'], 'item'=>'garam masala'],
            ],
        ];

        $rows = []; $failDetails = [];
        foreach ($fixtures as $rel => $expList) {
            $abs = realpath($pluginRoot . '/' . $rel);
            $passed = 0; $failed = 0; $short = []; $detail = [];
            if (!$abs || !is_file($abs)) { $rows[] = [$rel, 0, 1, 'not found']; $failDetails[] = safe($rel).' not found'; continue; }
            $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
            $raw = file_get_contents($abs);
            $html = in_array($ext, ['json','jsonld'], true)
                ? "<!doctype html><meta charset='utf-8'>\n<script type=\"application/ld+json\">" . $raw . "</script>"
                : $raw;
            $result = parseRecipe($html, 'https://fixtures.local/' . basename($abs));
            $ings = is_array($result) ? ($result['recipe']['recipeIngredient'] ?? []) : [];

            foreach ($expList as $idx => $exp) {
                $ok = true; $msgs = [];
                $row = $ings[$idx] ?? null;
                if (!is_array($row)) { $ok=false; $msgs[]='missing row #'.($idx+1); }
                if ($ok) {
                    if (isset($exp['range'])) {
                        if (!($has_range($row['qty'] ?? null))) { $ok=false; $msgs[]='qty not range'; }
                        else {
                            if (!$approx(($row['qty']['low'] ?? null), $exp['range'][0])) { $ok=false; $msgs[]='low!='.$exp['range'][0]; }
                            if (!$approx(($row['qty']['high'] ?? null), $exp['range'][1])) { $ok=false; $msgs[]='high!='.$exp['range'][1]; }
                        }
                    } elseif (isset($exp['qty'])) {
                        if (is_array($row['qty'] ?? null)) { $ok=false; $msgs[]='qty is range'; }
                        elseif (!$approx((float)($row['qty'] ?? -999), (float)$exp['qty'])) { $ok=false; $msgs[]='qty!='.$exp['qty']; }
                    }
                    if (isset($exp['unit'])) {
                        if ($exp['unit'] === [] || $exp['unit'] === null) {
                            if (!empty($row['unit'])) { $ok=false; $msgs[]='unit not empty'; }
                        } else {
                            if (!$unit_ok($row['unit'] ?? '', (array)$exp['unit'])) { $ok=false; $msgs[]='unit!='.implode('|',(array)$exp['unit']); }
                        }
                    }
                    if (isset($exp['item'])) {
                        $it = $norm_str((string)($row['item'] ?? ''));
                        if (strpos($it, $norm_str((string)$exp['item'])) === false) { $ok=false; $msgs[]='item!~'.$exp['item']; }
                    }
                    if (isset($exp['note_has'])) {
                        $note = $norm_str((string)($row['note'] ?? ''));
                        foreach ((array)$exp['note_has'] as $frag) { if ($frag !== '' && strpos($note, $norm_str($frag)) === false) { $ok=false; $msgs[]='note!~'.$frag; } }
                    }
                    if (isset($exp['item_not_has'])) {
                        $it2 = (string)($row['item'] ?? '');
                        foreach ((array)$exp['item_not_has'] as $frag2) { if ($frag2!=='' && strpos($it2, $frag2) !== false) { $ok=false; $msgs[]='item has '.$frag2; } }
                    }
                }
                if ($ok) { $passed++; } else { $failed++; $short[] = '#'.($idx+1); $detail[] = [$idx+1, $exp, $row]; }
            }
            $rows[] = [$rel, $passed, $failed, $short ? ('Fail @ '.implode(', ',$short)) : 'OK'];
            if ($detail) { $failDetails[] = '<h4>'.safe($rel).'</h4><pre>'.safe(json_encode($detail, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre>'; }
        }

        echo '<table class="grid"><thead><tr><th>Fixture</th><th>Passed</th><th>Failed</th><th>Details</th></tr></thead><tbody>';
        $totalFail = 0; $totalPass = 0;
        foreach ($rows as [$rel,$p,$f,$d]) {
            $totalFail += $f; $totalPass += $p;
            $link = self_url(['eval'=>null,'f'=>$rel]);
            echo '<tr>'
               . '<td><a href="' . safe($link) . '">' . safe($rel) . '</a></td>'
               . '<td>' . safe((string)$p) . '</td>'
               . '<td>' . safe((string)$f) . '</td>'
               . '<td>' . safe($d) . '</td>'
               . '</tr>';
        }
        echo '</tbody></table>';
        echo '<p><strong>' . ($totalFail === 0 ? 'ALL PASS' : ('FAIL (' . (string)$totalFail . ' Details)')) . '</strong></p>';
        if ($failDetails) { echo '<details><summary><strong>Failing expectations</strong></summary>' . implode("\n", $failDetails) . '</details>'; }

        safe_html_end();
        exit;
    }
    if ($fixtureRel === '') {
        echo h1('Parser-Lab ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“ Fixtures');
        echo diag_box($diag);
        echo fixtures_list($pluginRoot);
        safe_html_end();
        exit;
    }

    // Fixture auflÃƒÆ’Ã‚Â¶sen & lesen
    $fixtureAbs = realpath($pluginRoot . '/' . ltrim($fixtureRel, '/'));
    if (!$fixtureAbs || strpos($fixtureAbs, realpath(FIXTURE_DIR)) !== 0 || !is_file($fixtureAbs)) {
        throw new RuntimeException('UngÃƒÆ’Ã‚Â¼ltige Fixture: ' . safe($fixtureRel));
    }

    $ext = strtolower(pathinfo($fixtureAbs, PATHINFO_EXTENSION));
    $raw = file_get_contents($fixtureAbs);
    if ($raw === false) {
        throw new RuntimeException('Konnte Fixture nicht lesen: ' . safe($fixtureRel));
    }

    // JSON/JSON-LD in HTML-HÃƒÆ’Ã‚Â¼lle verpacken, damit <script type=application/ld+json> gefunden wird
    if (in_array($ext, ['json', 'jsonld'], true)) {
        $html = "<!doctype html><meta charset='utf-8'>\n<script type=\"application/ld+json\">" . $raw . "</script>";
    } else {
        $html = $raw;
    }

    echo h1('Parser-Lab ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“ ' . safe($fixtureRel));
    echo '<p><a href="' . safe(self_url(['f' => null])) . '">ÃƒÂ¢Ã¢â‚¬Â Ã‚Â Zur ÃƒÆ’Ã…â€œbersicht</a></p>';
    echo diag_box($diag + [
        'fixture' => $fixtureRel,
        'ext'     => $ext,
    ]);

    if (!$haveParse) {
        throw new RuntimeException('Funktion parseRecipe(...) nicht gefunden. Bitte includes/parser.php prÃƒÆ’Ã‚Â¼fen.');
    }

    // Parser aufrufen
    $fauxUrl = 'https://fixtures.local/' . basename($fixtureAbs);
    $result  = parseRecipe($html, $fauxUrl);
    // Quelle der Zutaten anzeigen und Roh-Auszug aus der Datei darstellen
    try { $srcs = (array)($result['sources']['recipeIngredient'] ?? []); } catch (Throwable $e) { $srcs = []; }
    $srcLabel = $srcs ? implode(', ', $srcs) : '(unbekannt)';
    echo '<div class="box"><strong>Quelle Zutaten:</strong> ' . safe($srcLabel) . '</div>';
    $snippet = '';
    if (in_array('dom', $srcs, true) || in_array('microdata', $srcs, true)) {
        $snippet = sitc_lab_extract_ingredients_dom_snippet($html);
    } elseif (in_array('jsonld', $srcs, true)) {
        $items = sitc_lab_extract_jsonld_ingredients($html);
        if ($items) {
            $snippet = "<script type=\"application/ld+json\">\n" . safe(json_encode(['recipeIngredient'=>$items], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)) . "\n</script>";
        }
    }
    if ($snippet) { echo '<pre>' . $snippet . '</pre>'; }

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
            $qtyStr = '';
            if (is_array($qty) && isset($qty['low'],$qty['high'])) {
                $qtyStr = rtrim(rtrim(sprintf('%.3f', (float)$qty['low']), '0'), '.') . 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Å“' . rtrim(rtrim(sprintf('%.3f', (float)$qty['high']), '0'), '.');
            } elseif ($qty !== null && $qty !== '') {
                $qtyStr = rtrim(rtrim(sprintf('%.3f', (float)$qty), '0'), '.');
            }
            $unit = $row['unit'] ?? '';
            $item = $row['item'] ?? '';
            $note = isset($row['note']) ? (string)$row['note'] : '';
            $disp = trim(implode(' ', array_filter([$qtyStr, (string)$unit, (string)$item], 'strlen')));
            if ($note !== '') { $disp .= ', ' . $note; }
            echo '<tr><td>' . $i . '</td>'
               . '<td><code>struct</code><br><small>' . safe($raw ?: json_encode($row, JSON_UNESCAPED_UNICODE)) . '</small></td>'
               . '<td>' . safe($qtyStr) . '</td>'
               . '<td>' . safe((string)$unit) . '</td>'
               . '<td>' . safe((string)$item) . '</td>'
               . '<td><strong>' . safe($disp) . '</strong></td>'
               . '</tr>';
            if ($isModActive) {
                $dumpQty = 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â';
                if (is_array($qty) && isset($qty['low'],$qty['high'])) {
                    $dumpQty = '{low:'
                        . rtrim(rtrim(sprintf('%.3f', (float)$qty['low']), '0'), '.')
                        . ', high:'
                        . rtrim(rtrim(sprintf('%.3f', (float)$qty['high']), '0'), '.')
                        . '}';
                } elseif ($qty !== null && $qty !== '') {
                    $dumpQty = rtrim(rtrim(sprintf('%.3f', (float)$qty), '0'), '.');
                }
                $dumpUnit = ($unit !== null && $unit !== '') ? (string)$unit : 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â';
                $dumpItem = ($item !== null && $item !== '') ? (string)$item : 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â';
                $dumpNote = ($note !== null && $note !== '') ? (string)$note : 'ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â';
                echo '<tr><td colspan="6"><div class="dev-dump">dev: qty='
                    . safe($dumpQty) . ', unit=' . safe($dumpUnit)
                    . ', item=' . safe($dumpItem) . ', note=' . safe($dumpNote)
                    . '</div></td></tr>';
            }
        } else {
            $raw = (string)$row;
            echo '<tr><td>' . $i . '</td>'
               . '<td><code>raw</code><br><small>' . safe($raw) . '</small></td>'
               . '<td>ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â</td><td>ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â</td><td>ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â</td>'
               . '<td><strong>' . safe($raw) . '</strong></td>'
               . '</tr>';
        }
    }
    echo '</tbody></table>';

} catch (Throwable $e) {
    sitc_error($e->getMessage());
    echo diag_box($diag);
}

safe_html_end();

/* ===== Helpers ===== */
function safe(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function lab_time_berlin_html(): string {
    try {
        $dt = new DateTime('now', new DateTimeZone('Europe/Berlin'));
        return '<p><small>Aktuelle Uhrzeit (Europe/Berlin): ' . safe($dt->format('Y-m-d H:i:s')) . '</small></p>';
    } catch (Throwable $e) {
        return '';
    }
}
function h1(string $s): string { return '<h1>' . safe($s) . '</h1>' . lab_time_berlin_html(); }
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
.dev-dump{font:12px/1.3 ui-monospace,Consolas,monospace;color:#555;background:#f7f7f7;border-left:3px solid #e0e0e0;padding:3px 6px;margin:0}
</style>
CSS;
}
function safe_html_head(string $title): void {
    echo "<!doctype html><meta charset='utf-8'><title>" . safe($title) . "</title>" . styles();
}
function safe_html_end(): void { /* noop */ }
function warn_box($msg): string {
    if (is_array($msg) || is_object($msg)) {
        $msg = json_encode($msg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        $msg = (string)$msg;
    }
    return '<div class="box warn"><strong>Hinweis:</strong> ' . safe($msg) . '</div>';
}
function diag_box($kv): string {
    if (!is_array($kv)) { $kv = ['message' => (string)$kv]; }
    $isAssoc = array_keys($kv) !== range(0, count($kv) - 1);
    $fmt = function($v): string {
        if (is_array($v) || is_object($v)) {
            return (string)json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return (string)$v;
    };
    $h = '<div class="box"><strong>Diagnose</strong><ul style="margin:6px 0 0 18px">';
    if ($isAssoc) {
        foreach ($kv as $k => $v) {
            $h .= '<li><code>' . safe((string)$k) . '</code>: ' . safe($fmt($v)) . '</li>';
        }
    } else {
        foreach ($kv as $v) {
            $h .= '<li>' . safe($fmt($v)) . '</li>';
        }
    }
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
    $h = '<div class="box"><a href="auto_eval_web.php"><strong>Alle 4 Fixtures vergleichen (Web)</strong></a></div><ul>';
    foreach ($rows as $rel) {
        $cmp = 'auto_eval_web.php?f=' . rawurlencode($rel);
        $h .= '<li><a href="' . safe(self_url(['f' => $rel])) . '">' . safe($rel) . '</a>'
           . ' &nbsp; <small><a href="' . safe($cmp) . '">Vergleiche Legacy vs. Modular (Web)</a></small>'
           . '</li>';
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

// ===== Lab helpers for source snippet rendering =====
function sitc_lab_extract_ingredients_dom_snippet(string $html): string {
    $doc = new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xp = new DOMXPath($doc);
    $candidates = [
        '//ul[contains(@class, "wprm-recipe-ingredients")]','//ol[contains(@class, "wprm-recipe-ingredients")]',
        '//div[contains(@class, "wprm-recipe-container")]//ul[li]','//div[contains(@class, "wprm-recipe-container")]//ol[li]',
        '//ul[li][count(li) >= 2]','//ol[li][count(li) >= 2]'
    ];
    foreach ($candidates as $q) {
        $node = $xp->query($q)->item(0);
        if ($node) {
            return htmlspecialchars((string)$doc->saveHTML($node), ENT_NOQUOTES, 'UTF-8');
        }
    }
    return '';
}

function sitc_lab_extract_jsonld_ingredients(string $html): ?array {
    if (!preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/is', $html, $m)) return null;
    foreach ($m[1] as $block) {
        $json = json_decode(trim($block), true);
        if (!is_array($json)) continue;
        if (isset($json['@graph']) && is_array($json['@graph'])) {
            foreach ($json['@graph'] as $node) {
                if (isset($node['@type']) && (in_array('Recipe', (array)$node['@type']) || $node['@type'] === 'Recipe')) {
                    $json = $node;
                    break;
                }
            }
        }
        if (isset($json['@type']) && (in_array('Recipe', (array)$json['@type']) || $json['@type'] === 'Recipe')) {
            $ings = $json['recipeIngredient'] ?? null;
            if (is_array($ings) && $ings) return $ings;
        }
    }
    return null;
}
