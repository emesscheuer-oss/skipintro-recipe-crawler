<?php
// tools/parser_lab/run.php — rebuilt clean (UTF-8, no closing tag), no raw HTML outside PHP
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

$root = realpath(__DIR__ . '/../../');
if ($root === false) { $root = getcwd(); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Try to load parsers
$ing_parse_mod = null;
$ing_parse_legacy = null;

$inc_parse_line = $root . '/includes/ingredients/parse_line.php';
$inc_qty        = $root . '/includes/ingredients/qty.php';

if (is_file($inc_parse_line)) { require_once $inc_parse_line; }
if (is_file($inc_qty))        { require_once $inc_qty; }

if (function_exists('sitc_ing_v2_parse_line'))            { $ing_parse_mod    = 'sitc_ing_v2_parse_line'; }
if (function_exists('sitc_ing_parse_line_mod'))           { $ing_parse_mod    = $ing_parse_mod ?: 'sitc_ing_parse_line_mod'; }
if (function_exists('sitc_ing_parse_line'))               { $ing_parse_mod    = $ing_parse_mod ?: 'sitc_ing_parse_line'; } // fallback
if (function_exists('sitc_parse_ingredient_line'))        { $ing_parse_legacy = 'sitc_parse_ingredient_line'; }
if (function_exists('sitc_parse_ingredient_line_legacy')) { $ing_parse_legacy = $ing_parse_legacy ?: 'sitc_parse_ingredient_line_legacy'; }

$engine = isset($_GET['engine']) ? (string)$_GET['engine'] : 'mod'; // mod | legacy | auto
$f_rel  = isset($_GET['f']) ? (string)$_GET['f'] : '';

$abs = '';
if ($f_rel !== '') {
    $abs = realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $f_rel)) ?: '';
}

$ext = $abs ? strtolower(pathinfo($abs, PATHINFO_EXTENSION)) : '';
$manual_lines = [];

// Load manual lines if a .txt file is selected
if ($abs && is_file($abs) && $ext === 'txt') {
    $raw = file_get_contents($abs);
    if ($raw !== false) {
        foreach (preg_split('/\r?\n/u', $raw) as $ln) {
            $ln = trim($ln);
            if ($ln !== '') { $manual_lines[] = $ln; }
        }
    }
}

// Simple fixture listing (under tools/parser_lab/fixtures)
$fixtures_root = $root . '/tools/parser_lab/fixtures';
$fixture_options = [];
if (is_dir($fixtures_root)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixtures_root, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) continue;
        $rel = ltrim(str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname()), DIRECTORY_SEPARATOR);
        $fixture_options[] = $rel;
    }
    sort($fixture_options, SORT_NATURAL | SORT_FLAG_CASE);
}

// Choose parser callable based on engine
$parse_fn = null;
if ($engine === 'legacy') {
    $parse_fn = $ing_parse_legacy ?: $ing_parse_mod;
} elseif ($engine === 'mod') {
    $parse_fn = $ing_parse_mod ?: $ing_parse_legacy;
} else { // auto
    $parse_fn = $ing_parse_mod ?: $ing_parse_legacy;
}

$tz = new DateTimeZone('Europe/Berlin');
$now = new DateTime('now', $tz);

echo <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Parser-Lab</title>
<style>
body{font:14px/1.45 system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px}
.box{border:1px solid #ddd;background:#fafafa;padding:10px 12px;border-radius:8px;margin:10px 0}
.badge{display:inline-block;padding:2px 6px;border-radius:6px;background:#eef;border:1px solid #ccd}
a{color:#06c;text-decoration:none}
a:hover{text-decoration:underline}
ul{padding-left:18px;margin:6px 0 0 0}
code{background:#f0f0f0;padding:1px 4px;border-radius:4px}
select{font:inherit}
</style>
</head>
<body>
<h1>Parser-Lab</h1>
<div class="box">
<strong>Engine:</strong>
<span class="badge">
HTML;

echo h($engine);

echo <<<HTML
</span>
&nbsp;
HTML;

$qs_base = '?f=' . urlencode($f_rel);
echo '<a href="' . h($qs_base . '&engine=legacy') . '">Legacy</a> | ';
echo '<a href="' . h($qs_base . '&engine=mod')    . '">Modular</a> | ';
echo '<a href="' . h($qs_base . '&engine=auto')   . '">Auto</a>';

echo '</div>';

echo '<div class="box"><form method="get">';
echo '<label>Fixture:&nbsp;</label>';
echo '<select name="f">';
echo '<option value="">(— bitte wählen —)</option>';
foreach ($fixture_options as $opt) {
    $sel = ($opt === $f_rel) ? ' selected' : '';
    echo '<option value="' . h($opt) . '"' . $sel . '>' . h($opt) . '</option>';
}
echo '</select> &nbsp;';
echo '<input type="hidden" name="engine" value="' . h($engine) . '">';
echo '<button type="submit">Öffnen</button>';
echo '</form></div>';

echo '<p><small>Zeit: ' . h($now->format('Y-m-d H:i:s')) . ' (Europe/Berlin)</small></p>';

if ($f_rel === '') {
    echo '<div class="box">Wähle links eine Fixture aus der Liste und klicke „Öffnen“.</div>';
} elseif (!$abs || !is_file($abs)) {
    echo '<div class="box">Datei nicht gefunden: <code>' . h($f_rel) . '</code></div>';
} elseif ($ext !== 'txt') {
    echo '<div class="box">Diese Ansicht erwartet eine <code>.txt</code>-Fixture mit einer Zutat je Zeile.<br>Gewählte Datei: <code>' . h($f_rel) . '</code></div>';
} else {
    echo '<div class="box"><strong>Datei:</strong> <code>' . h($f_rel) . '</code><br>';
    echo '<strong>Zeilen:</strong> ' . count($manual_lines) . '</div>';

    if (!$parse_fn) {
        echo '<div class="box">Kein Parser verfügbar (weder legacy noch mod gefunden).</div>';
    } else {
        echo '<div class="box"><strong>Ergebnis (' . h($engine) . '):</strong><ul>';
        foreach ($manual_lines as $disp) {
            $norm = $disp;
            $parsed_str = '(n/a)';
            $qty = $unit = $item = $note = null;

            try {
                $res = call_user_func($parse_fn, $norm, 'de');
                if (is_array($res)) {
                    $qty  = $res['qty']  ?? ($res['quantity'] ?? null);
                    $unit = $res['unit'] ?? null;
                    $item = $res['item'] ?? ($res['name'] ?? null);
                    $note = $res['note'] ?? null;

                    $parts = [];
                    if ($qty !== null && $qty !== '')   $parts[] = (string)$qty;
                    if ($unit !== null && $unit !== '') $parts[] = (string)$unit;
                    if ($item !== null && $item !== '') $parts[] = (string)$item;
                    if ($note !== null && $note !== '') $parts[] = '(' . (string)$note . ')';
                    $parsed_str = trim(implode(' ', $parts));
                    if ($parsed_str === '') $parsed_str = $norm;
                } elseif (is_string($res)) {
                    $parsed_str = $res;
                }
            } catch (Throwable $e) {
                $parsed_str = '[Parser-Fehler] ' . $e->getMessage();
            }

            echo '<li><code>' . h($disp) . '</code> &nbsp;→ <strong>' . h($parsed_str) . '</strong></li>';
        }
        echo '</ul></div>';
    }
}

echo "</body></html>";
