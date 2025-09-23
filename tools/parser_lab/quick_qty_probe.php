<?php
// tools/parser_lab/quick_qty_probe.php
// Minimal smoke-probe for parse_line to debug fractions & unit extraction.
// UTF-8 (no BOM), no declare(), no closing tag.

error_reporting(E_ALL);
ini_set('display_errors', '1');

$root = dirname(__DIR__, 2); // project root: wp-content/plugins/skipintro-recipe-crawler
require_once $root . '/includes/ingredients/parse_line.php';

$tests = [
    '1 /2 TL Kurkuma',
    '½ Bund Koriander, gehackt',
    'einer halben Zitrone',
    '1,5 Liter Wasser',
    '1/2 Bund Minze, gehackt',
];

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Quick QTY Probe</h1><ul>";
foreach ($tests as $line) {
    $res = function_exists('sitc_ing_v2_parse_line')
        ? sitc_ing_v2_parse_line($line, 'de')
        : (function_exists('sitc_ing_parse_line_mod') ? sitc_ing_parse_line_mod($line, 'de') : ['raw'=>$line,'qty'=>null,'unit'=>null,'item'=>$line,'note'=>null]);

    $qty = $res['qty'];
    if (is_array($qty)) {
        $qtyDisp = (isset($qty['low'], $qty['high'])) ? ($qty['low'].'–'.$qty['high']) : json_encode($qty);
    } else {
        $qtyDisp = ($qty === null ? '' : (string)$qty);
    }
    $unit = $res['unit'] ?? '';
    $item = $res['item'] ?? '';

    echo "<li><code>".htmlspecialchars($line, ENT_QUOTES, 'UTF-8')."</code> → ";
    echo "<strong>qty=".htmlspecialchars($qtyDisp, ENT_QUOTES, 'UTF-8')."</strong>, ";
    echo "unit=<strong>".htmlspecialchars((string)$unit, ENT_QUOTES, 'UTF-8')."</strong>, ";
    echo "item=<strong>".htmlspecialchars((string)$item, ENT_QUOTES, 'UTF-8')."</strong></li>";
}
echo "</ul><p>File: includes/ingredients/parse_line.php</p>";
