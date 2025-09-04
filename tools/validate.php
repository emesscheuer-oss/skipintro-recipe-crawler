<?php
define('ABSPATH', __DIR__);
require_once dirname(__DIR__) . '/includes/parser.php';

function show($label, $value) {
    echo $label, ': ', json_encode($value, JSON_UNESCAPED_UNICODE), "\n";
}

function parse_line($s) {
    if (function_exists('sitc_parse_ingredient_line_v3')) {
        return sitc_parse_ingredient_line_v3($s);
    }
    return sitc_parse_ingredient_line_v2($s);
}

$tests = [
    'pre_norm_before' => 'ca. 60 ml Öl zum anbraten (zb. Sonnenblumenöl) (ca.)',
    'pre_norm_after'  => '60 (ca.) ml Öl',
    'frac_space'      => '1 /2 TL Salz',
    'range_dash'      => '2 – 3 Stück Tomaten',
    'approx_words'    => 'approximately 1.5 cup yogurt',
    'de_word'         => 'ca 15 g Ingwer-Stück (ca.)',
];

echo "-- Parse checks --\n";
foreach ($tests as $k => $line) {
    $p = parse_line($line);
    show($k, $p);
}

echo "\n-- Dedupe checks --\n";
$dupes = [
    '1 Lorbeerblatt',
    '1  Lorbeerblatt',
    '1 lorbeerblatt',
];
[$disp, $parsed] = sitc_dedupe_ingredients($dupes);
show('dedupe_disp', $disp);
show('dedupe_parsed_count', count($parsed));

$ok1 = !!(float) (parse_line($tests['pre_norm_before'])['qty'] ?? 0);
$ok2 = !!(float) (parse_line($tests['de_word'])['qty'] ?? 0);
$ok3 = strpos((string)parse_line($tests['range_dash'])['qty'], '-') !== false;

echo "\nSUMMARY: ", json_encode([
    'qty_ok_ca_60ml' => $ok1,
    'qty_ok_ca_15g'  => $ok2,
    'range_ok'       => $ok3,
    'dedupe_one'     => count($parsed) === 1,
], JSON_UNESCAPED_UNICODE), "\n";

