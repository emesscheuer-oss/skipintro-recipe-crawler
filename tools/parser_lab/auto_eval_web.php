<?php

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

require_once __DIR__ . '/_ui_helpers.php';

safe_html_head('Auto-Eval (Web)');

try {
    if (!$wpLoad || !is_file($wpLoad)) { throw new RuntimeException('wp-load.php nicht gefunden.'); }
    require_once $wpLoad;

    require_once dirname(__DIR__, 2) . '/includes/ingredients/i18n.php';
    $sitc_locale = function_exists('get_locale') ? (string)get_locale() : 'en_US';
    if ($sitc_locale === '') { $sitc_locale = 'en_US'; }
    $lex = function_exists('sitc_ing_load_locale') ? (array)sitc_ing_load_locale($sitc_locale) : [];

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
    echo '<p><a href="run.php">&larr; ZurÃ¯Â¿Â½ck zum Parser-Lab</a></p>';

    $esc_fn = function_exists('esc_html') ? 'esc_html' : 'safe';
    $bannerLocale = call_user_func($esc_fn, $sitc_locale);
    $bannerCore = call_user_func($esc_fn, isset($lex['_source_core']) ? $lex['_source_core'] : 'n/a');
    echo '<div style="margin:10px 0;padding:8px;background:#f6f7f7;border:1px solid #ddd">';
    echo 'Locale: <strong>' . $bannerLocale . '</strong> | ';
    echo 'Lexikon-Quellen: Core (' . $bannerCore . ')';
    if (!empty($lex['_source_override'])) {
        echo ' + Override (' . call_user_func($esc_fn, $lex['_source_override']) . ')';
    }
    echo '</div>';

    if (!$fixtures) { sitc_warn('Keine Fixtures gefunden.'); safe_html_end(); exit; }

    $outDir = $pluginRoot . '/tools/parser_lab/out';
    if (!is_dir($outDir)) { @mkdir($outDir, 0777, true); }
    $writable = is_dir($outDir) && is_writable($outDir);

    echo '<table class="grid"><thead><tr><th>Fixture</th><th>Legacy (count)</th><th>Modular (count)</th><th>Status</th></tr></thead><tbody>';

    foreach ($fixtures as $rel) {
        $abs = $pluginRoot . '/' . $rel;
        $rowStatus = 'Ã¢ÂÅ’';
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
                $rowStatus = 'Ã¢ÂÅ’';
            } else {
                $eq = json_encode($L, JSON_UNESCAPED_UNICODE) === json_encode($M, JSON_UNESCAPED_UNICODE);
                if ($eq) $rowStatus = 'Ã¢Å“â€¦ identisch';
                elseif ($countL === 0 && $countM > 0) $rowStatus = 'Ã¢Å“â€¦ verbessert';
                else $rowStatus = 'Ã¢Å¡Â Ã¯Â¸Â unterschiedlich';
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

    render_golden_qty_section($pluginRoot, $sitc_locale, $lex);

    // Simple compare report
    if ($writable) {
        @file_put_contents($outDir . '/compare_report.txt', "Web Auto-Eval executed at " . date('c') . "\n");
    }

} catch (Throwable $e) {
    sitc_error('Fehler: ' . $e->getMessage());
}

safe_html_end();

function render_golden_qty_section(string $pluginRoot, string $sitc_locale, array $lex): void {
    $goldenRoot = $pluginRoot . '/tools/parser_lab/fixtures/golden';
    if (!is_dir($goldenRoot)) {
        echo warn_box('Golden-Fixtures-Ordner nicht gefunden.');
        return;
    }

    $collections = [];
    $entries = @scandir($goldenRoot);
    if (is_array($entries)) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $abs = $goldenRoot . '/' . $entry;
            if (is_dir($abs)) {
                $collections[] = $entry;
            }
        }
    }

    if (!$collections) {
        echo warn_box('Keine Golden-Fixtures vorhanden.');
        return;
    }

    sort($collections, SORT_STRING);

    echo '<h2>Golden (Qty)</h2>';

    $metricKeys = ['qty_parse_rate','range_detect_rate','decimal_comma_clean','unit_attach_clean','note_separation_ok'];
    foreach ($collections as $collection) {
        $absCollection = $goldenRoot . '/' . $collection;
        $files = glob($absCollection . '/*.expected.json');
        if (!$files) {
            continue;
        }
        sort($files, SORT_STRING);

        echo '<h3>' . safe($collection) . '</h3>';
        echo '<table class="grid golden"><thead><tr><th>Fixture</th><th>Cases</th>';
        foreach ($metricKeys as $mk) {
            echo '<th>' . safe($mk) . '</th>';
        }
        echo '<th>Status</th></tr></thead><tbody>';

        foreach ($files as $expectedPath) {
            $result = eval_evaluate_golden_fixture($expectedPath, $sitc_locale);
            if (!$result) {
                continue;
            }
            $status = $result['mismatches'] ? 'CHECK' : 'PASS';

            echo '<tr>';
            echo '<td><strong>' . safe($result['name']) . '</strong></td>';
            echo '<td>' . safe((string)$result['total']) . '</td>';
            foreach ($metricKeys as $mk) {
                $metricVal = $result['metrics'][$mk] ?? [0, 0];
                echo '<td>' . safe(eval_format_metric($metricVal)) . '</td>';
            }
            echo '<td>' . safe($status) . '</td>';
            echo '</tr>';

            if (!empty($result['mismatches'])) {
                $colspan = count($metricKeys) + 3;
                echo '<tr><td colspan="' . $colspan . '"><details><summary>Details</summary><ul>';
                foreach ($result['mismatches'] as $line) {
                    echo '<li>' . safe($line) . '</li>';
                }
                echo '</ul></details></td></tr>';
            }
        }

        echo '</tbody></table>';
    }
}

function eval_evaluate_golden_fixture(string $expectedPath, string $sitc_locale): ?array {
    $cases = eval_load_expected_cases($expectedPath);
    $txtPath = preg_replace('/\.expected\.json$/', '.txt', $expectedPath);
    $lines = eval_load_fixture_lines($txtPath);
    $total = max(count($cases), count($lines));

    if ($total === 0) {
        return [
            'name' => basename($expectedPath, '.expected.json'),
            'total' => 0,
            'metrics' => [
                'qty_parse_rate' => [0, 0],
                'range_detect_rate' => [0, 0],
                'decimal_comma_clean' => [0, 0],
                'unit_attach_clean' => [0, 0],
                'note_separation_ok' => [0, 0],
            ],
            'mismatches' => [],
        ];
    }

    $localeCode = eval_locale_primary_tag($sitc_locale);

    $qtyTotal = $qtyOk = 0;
    $rangeTotal = $rangeOk = 0;
    $decimalTotal = $decimalOk = 0;
    $unitTotal = $unitOk = 0;
    $noteTotal = $noteOk = 0;
    $mismatches = [];

    for ($i = 0; $i < $total; $i++) {
        $expected = $cases[$i] ?? [];
        if (!is_array($expected)) {
            $expected = [];
        }
        if (!array_key_exists('line', $expected) && isset($lines[$i])) {
            $expected['line'] = $lines[$i];
        }
        $rawLine = isset($expected['line']) ? (string)$expected['line'] : (isset($lines[$i]) ? (string)$lines[$i] : '');

        $actual = eval_parse_line_for_eval($rawLine, $localeCode);
        $comparison = eval_compare_case($expected, $actual, $rawLine);

        $caseFailed = false;

        if ($comparison['qty_expected']) {
            $qtyTotal++;
            if ($comparison['range_expected']) {
                $rangeTotal++;
                if ($comparison['range_equal']) {
                    $qtyOk++;
                    $rangeOk++;
                } else {
                    $caseFailed = true;
                }
            } else {
                if ($comparison['qty_equal']) {
                    $qtyOk++;
                } else {
                    $caseFailed = true;
                }
            }
        }

        if ($comparison['decimal_case'] && $comparison['qty_expected']) {
            $decimalTotal++;
            if (($comparison['range_expected'] && $comparison['range_equal']) || (!$comparison['range_expected'] && $comparison['qty_equal'])) {
                $decimalOk++;
            }
        }

        if ($comparison['unit_expected']) {
            $unitTotal++;
            if ($comparison['unit_equal']) {
                $unitOk++;
            } else {
                $caseFailed = true;
            }
        }

        if ($comparison['note_expected']) {
            $noteTotal++;
            if ($comparison['note_equal']) {
                $noteOk++;
            } else {
                $caseFailed = true;
            }
        }

        if (!$comparison['item_equal']) {
            $caseFailed = true;
        }

        if ($caseFailed) {
            $mismatches[] = eval_build_mismatch_message($i, $comparison);
        }
    }

    $metrics = [
        'qty_parse_rate' => [$qtyOk, $qtyTotal],
        'range_detect_rate' => [$rangeOk, $rangeTotal],
        'decimal_comma_clean' => [$decimalOk, $decimalTotal],
        'unit_attach_clean' => [$unitOk, $unitTotal],
        'note_separation_ok' => [$noteOk, $noteTotal],
    ];

    return [
        'name' => basename($expectedPath, '.expected.json'),
        'total' => $total,
        'metrics' => $metrics,
        'mismatches' => $mismatches,
    ];
}

function eval_load_expected_cases(string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return [];
    }
    $cases = isset($data['cases']) && is_array($data['cases']) ? $data['cases'] : $data;
    $out = [];
    foreach ($cases as $case) {
        if (is_array($case)) {
            $out[] = $case;
        }
    }
    return $out;
}

function eval_load_fixture_lines(string $path): array {
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }
    return array_map(function ($line) {
        return rtrim((string)$line, "\r\n");
    }, $lines);
}

function eval_parse_line_for_eval(string $line, string $locale): array {
    $parsed = null;
    try {
        if (function_exists('sitc_ing_parse_line_mod')) {
            $parsed = sitc_ing_parse_line_mod($line, $locale);
        } elseif (function_exists('sitc_ing_parse_line_mux')) {
            $parsed = sitc_ing_parse_line_mux($line, $locale);
        } elseif (function_exists('sitc_parse_ingredient_line')) {
            $parsed = sitc_parse_ingredient_line($line);
        }
    } catch (Throwable $e) {
        $parsed = null;
    }

    if (!is_array($parsed)) {
        $parsed = [];
    }

    return [
        'raw' => $line,
        'qty' => $parsed['qty'] ?? null,
        'unit' => $parsed['unit'] ?? null,
        'item' => isset($parsed['item']) ? (string)$parsed['item'] : '',
        'note' => $parsed['note'] ?? null,
    ];
}

function eval_compare_case(array $expected, array $actual, string $rawLine): array {
    $expectedQty = eval_expected_qty_struct($expected);
    $actualQty = eval_actual_qty_struct($actual);

    $expectedUnitRaw = array_key_exists('unit', $expected) ? $expected['unit'] : null;
    $expectedUnitNorm = eval_normalize_unit($expectedUnitRaw);
    $actualUnitNorm = eval_normalize_unit($actual['unit'] ?? null);
    $unitExpected = array_key_exists('unit', $expected) && $expectedUnitRaw !== null;
    $unitEqual = !$unitExpected || ($expectedUnitNorm === $actualUnitNorm);

    $expectedItem = $expected['item'] ?? '';
    $actualItem = $actual['item'] ?? '';
    $itemEqual = eval_normalize_text($expectedItem) === eval_normalize_text($actualItem);

    $noteExpected = array_key_exists('note', $expected);
    $expectNoNote = $noteExpected && $expected['note'] === null;
    $expNoteNorm = eval_normalize_text($expected['note'] ?? null);
    $gotNoteNorm = eval_normalize_text($actual['note'] ?? null);
    $noteEqual = !$noteExpected || ($expectNoNote ? ($gotNoteNorm === '') : ($expNoteNorm === $gotNoteNorm));

    $qtyExpected = $expectedQty['type'] !== 'none';
    $rangeExpected = $expectedQty['type'] === 'range';
    $qtyEqual = true;
    $rangeEqual = true;

    if ($rangeExpected) {
        $rangeEqual = eval_qty_matches_range($expectedQty, $actualQty);
        $qtyEqual = $rangeEqual;
    } elseif ($qtyExpected) {
        $qtyEqual = eval_qty_matches_single($expectedQty['value'] ?? null, $actualQty);
    }

    $decimalCase = (bool)preg_match('/\d,\d/', $rawLine);

    return [
        'line' => $rawLine,
        'expected' => $expected,
        'actual' => $actual,
        'qty_expected' => $qtyExpected,
        'qty_equal' => !$qtyExpected || $qtyEqual,
        'range_expected' => $rangeExpected,
        'range_equal' => !$rangeExpected || $rangeEqual,
        'unit_expected' => $unitExpected,
        'unit_equal' => $unitEqual,
        'note_expected' => $noteExpected,
        'note_equal' => $noteEqual,
        'expect_no_note' => $expectNoNote,
        'item_equal' => $itemEqual,
        'expected_qty_struct' => $expectedQty,
        'actual_qty_struct' => $actualQty,
        'unit_norm' => ['expected' => $expectedUnitNorm, 'actual' => $actualUnitNorm],
        'note_norm' => ['expected' => $expNoteNorm, 'actual' => $gotNoteNorm],
        'item_norm' => ['expected' => eval_normalize_text($expectedItem), 'actual' => eval_normalize_text($actualItem)],
        'decimal_case' => $decimalCase,
    ];
}

function eval_expected_qty_struct(array $expected): array {
    $qty = $expected['qty'] ?? null;
    $min = $expected['min_qty'] ?? null;
    $max = $expected['max_qty'] ?? null;

    $minFloat = eval_to_float($min);
    $maxFloat = eval_to_float($max);
    if ($minFloat !== null || $maxFloat !== null) {
        $low = $minFloat ?? $maxFloat;
        $high = $maxFloat ?? $minFloat;
        if ($low !== null && $high !== null) {
            if (eval_float_equals($low, $high)) {
                return ['type' => 'single', 'value' => $low];
            }
            return ['type' => 'range', 'low' => $low, 'high' => $high];
        }
        if ($low !== null) {
            return ['type' => 'single', 'value' => $low];
        }
        if ($high !== null) {
            return ['type' => 'single', 'value' => $high];
        }
    }

    if (is_string($qty) && function_exists('sitc_parse_qty_or_range_parser')) {
        $parsed = sitc_parse_qty_or_range_parser($qty);
        if (is_array($parsed) && isset($parsed['low'], $parsed['high'])) {
            $low = (float)$parsed['low'];
            $high = (float)$parsed['high'];
            if (eval_float_equals($low, $high)) {
                return ['type' => 'single', 'value' => $low];
            }
            return ['type' => 'range', 'low' => $low, 'high' => $high];
        }
        if (is_numeric($parsed)) {
            return ['type' => 'single', 'value' => (float)$parsed];
        }
    }

    $qtyFloat = eval_to_float($qty);
    if ($qtyFloat !== null) {
        return ['type' => 'single', 'value' => $qtyFloat];
    }

    if (is_string($qty) && preg_match('/^\s*([0-9.,]+)\s*-\s*([0-9.,]+)\s*$/', $qty, $m)) {
        $low = eval_to_float($m[1]);
        $high = eval_to_float($m[2]);
        if ($low !== null && $high !== null) {
            if (eval_float_equals($low, $high)) {
                return ['type' => 'single', 'value' => $low];
            }
            return ['type' => 'range', 'low' => $low, 'high' => $high];
        }
    }

    return ['type' => 'none'];
}

function eval_actual_qty_struct(array $actual): array {
    $value = $actual['qty'] ?? null;
    $min = $actual['min_qty'] ?? null;
    $max = $actual['max_qty'] ?? null;

    $minFloat = eval_to_float($min);
    $maxFloat = eval_to_float($max);
    if ($minFloat !== null || $maxFloat !== null) {
        $low = $minFloat ?? $maxFloat;
        $high = $maxFloat ?? $minFloat;
        if ($low !== null && $high !== null) {
            if (eval_float_equals($low, $high)) {
                return ['type' => 'single', 'value' => $low];
            }
            return ['type' => 'range', 'low' => $low, 'high' => $high];
        }
        if ($low !== null) {
            return ['type' => 'single', 'value' => $low];
        }
        if ($high !== null) {
            return ['type' => 'single', 'value' => $high];
        }
    }

    if (is_array($value)) {
        $low = eval_to_float($value['low'] ?? null);
        $high = eval_to_float($value['high'] ?? null);
        if ($low !== null && $high !== null) {
            if (eval_float_equals($low, $high)) {
                return ['type' => 'single', 'value' => $low];
            }
            return ['type' => 'range', 'low' => $low, 'high' => $high];
        }
        if ($low !== null) {
            return ['type' => 'single', 'value' => $low];
        }
        if ($high !== null) {
            return ['type' => 'single', 'value' => $high];
        }
    }

    if (is_string($value) && function_exists('sitc_parse_qty_or_range_parser')) {
        $parsed = sitc_parse_qty_or_range_parser($value);
        if (is_array($parsed) && isset($parsed['low'], $parsed['high'])) {
            $low = (float)$parsed['low'];
            $high = (float)$parsed['high'];
            if (eval_float_equals($low, $high)) {
                return ['type' => 'single', 'value' => $low];
            }
            return ['type' => 'range', 'low' => $low, 'high' => $high];
        }
        if (is_numeric($parsed)) {
            return ['type' => 'single', 'value' => (float)$parsed];
        }
    }

    $float = eval_to_float($value);
    if ($float !== null) {
        return ['type' => 'single', 'value' => $float];
    }

    return ['type' => 'none'];
}

function eval_qty_matches_single($expectedValue, array $actual): bool {
    if ($expectedValue === null) {
        return false;
    }
    $type = $actual['type'] ?? 'none';
    if ($type === 'single') {
        return eval_float_equals($expectedValue, $actual['value'] ?? null);
    }
    if ($type === 'range') {
        return eval_float_equals($expectedValue, $actual['low'] ?? null) && eval_float_equals($expectedValue, $actual['high'] ?? null);
    }
    return false;
}

function eval_qty_matches_range(array $expected, array $actual): bool {
    if (($actual['type'] ?? 'none') !== 'range') {
        return false;
    }
    return eval_float_equals($expected['low'] ?? null, $actual['low'] ?? null)
        && eval_float_equals($expected['high'] ?? null, $actual['high'] ?? null);
}

function eval_to_float($value): ?float {
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || is_float($value)) {
        return (float)$value;
    }
    if (is_string($value)) {
        $trim = trim($value);
        if ($trim === '') {
            return null;
        }
        if (function_exists('sitc_qty_to_float')) {
            $parsed = sitc_qty_to_float($trim);
            if ($parsed !== null) {
                return (float)$parsed;
            }
        }
        $normalized = str_replace(',', '.', $trim);
        if (is_numeric($normalized)) {
            return (float)$normalized;
        }
    }
    return null;
}

function eval_float_equals($a, $b): bool {
    if ($a === null || $b === null) {
        return false;
    }
    return abs((float)$a - (float)$b) <= 0.0005;
}

function eval_format_metric(array $metric): string {
    $ok = (int)($metric[0] ?? 0);
    $total = (int)($metric[1] ?? 0);
    if ($total === 0) {
        return 'n/a';
    }
    $pct = (int)round(($ok / max(1, $total)) * 100);
    return $ok . '/' . $total . ' (' . $pct . '%)';
}

function eval_format_qty_struct(array $struct): string {
    $type = $struct['type'] ?? 'none';
    if ($type === 'single') {
        return eval_format_float($struct['value'] ?? null);
    }
    if ($type === 'range') {
        $low = eval_format_float($struct['low'] ?? null);
        $high = eval_format_float($struct['high'] ?? null);
        return $low . ' - ' . $high;
    }
    return 'n/a';
}

function eval_format_float($value): string {
    if ($value === null || $value === '') {
        return 'n/a';
    }
    $formatted = rtrim(rtrim(sprintf('%.3f', (float)$value), '0'), '.');
    if ($formatted === '') {
        return '0';
    }
    return $formatted;
}

function eval_short_text($value, int $max = 80): string {
    $text = trim((string)($value ?? ''));
    if ($text === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $max) {
            return mb_substr($text, 0, $max, 'UTF-8') . '...';
        }
        return $text;
    }
    if (strlen($text) > $max) {
        return substr($text, 0, $max) . '...';
    }
    return $text;
}

function eval_normalize_unit($unit): string {
    if ($unit === null) {
        return '';
    }
    $u = trim((string)$unit);
    if ($u === '') {
        return '';
    }
    if (function_exists('sitc_unit_alias_canonical')) {
        $canon = sitc_unit_alias_canonical($u);
        if (is_string($canon) && $canon !== '') {
            return $canon;
        }
    }
    $lower = strtolower($u);
    $map = [
        'el' => 'tbsp',
        'tl' => 'tsp',
        'stk' => 'piece',
    ];
    return $map[$lower] ?? $lower;
}

function eval_locale_primary_tag(string $locale): string {
    $locale = strtolower(str_replace('_', '-', $locale));
    if (preg_match('/^[a-z]{2}/', $locale, $m)) {
        return $m[0];
    }
    return 'en';
}

function eval_build_mismatch_message(int $index, array $comparison): string {
    $parts = [];
    if ($comparison['range_expected'] && !$comparison['range_equal']) {
        $parts[] = 'range expected ' . eval_format_qty_struct($comparison['expected_qty_struct']) . ' got ' . eval_format_qty_struct($comparison['actual_qty_struct']);
    } elseif ($comparison['qty_expected'] && !$comparison['qty_equal']) {
        $parts[] = 'qty expected ' . eval_format_qty_struct($comparison['expected_qty_struct']) . ' got ' . eval_format_qty_struct($comparison['actual_qty_struct']);
    }
    if ($comparison['unit_expected'] && !$comparison['unit_equal']) {
        $expectedUnit = $comparison['unit_norm']['expected'] !== '' ? $comparison['unit_norm']['expected'] : '(none)';
        $actualUnit = $comparison['unit_norm']['actual'] !== '' ? $comparison['unit_norm']['actual'] : '(none)';
        $parts[] = 'unit ' . $expectedUnit . ' vs ' . $actualUnit;
    }
    if ($comparison['note_expected'] && !$comparison['note_equal']) {
        if (!empty($comparison['expect_no_note'])) {
            $parts[] = 'note expected none got "' . eval_short_text($comparison['actual']['note'] ?? '') . '"';
        } else {
            $parts[] = 'note expected "' . eval_short_text($comparison['expected']['note'] ?? '') . '" got "' . eval_short_text($comparison['actual']['note'] ?? '') . '"';
        }
    }
    if (!$comparison['item_equal']) {
        $parts[] = 'item expected "' . eval_short_text($comparison['expected']['item'] ?? '') . '" got "' . eval_short_text($comparison['actual']['item'] ?? '') . '"';
    }
    if (!$parts) {
        $parts[] = 'abweichung ohne details';
    }
    $line = eval_short_text($comparison['line'] ?? '');
    $suffix = $line !== '' ? ' | line "' . $line . '"' : '';
    return 'Case #' . ($index + 1) . ': ' . implode('; ', $parts) . $suffix;
}

if (!function_exists('sitc_slugify_lite')) {
    function sitc_slugify_lite(string $s): string {
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/u', ' ', $s);
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s);
        return $s ?? '';
    }
}

if (!function_exists('eval_normalize_text')) {
    function eval_normalize_text($v): string {
        if ($v === null) {
            return '';
        }
        $v = preg_replace('/\s*,\s*/u', ', ', (string)$v);
        $v = preg_replace('/\s+/u', ' ', trim((string)$v));
        return sitc_slugify_lite($v);
    }
}

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
