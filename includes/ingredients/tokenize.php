<?php
declare(strict_types=1);

/**
 * Zerlegt vorerst NICHT; gibt nur raw zurück (Stub).
 * TODO: später: Vor-Normalisierung (Fractions, Dashes, ca.), Tokenliste (qty/unit/item/notes)
 */
if (!function_exists('sitc_ing_tokenize')) {
function sitc_ing_tokenize(string $line, string $locale = 'de'): array {
    $raw = (string)$line;
    $norm = $raw;
    // 1) Remove qty-context stopwords (ca., etwa, approx.)
    if (function_exists('sitc_norm_strip_stopwords')) $norm = sitc_norm_strip_stopwords($norm);
    // 2) Normalize unicode dashes to '-'
    if (function_exists('sitc_norm_unify_dashes')) $norm = sitc_norm_unify_dashes($norm);
    else $norm = preg_replace('/[\x{2012}-\x{2015}]/u', '-', $norm) ?? $norm;
    // 3) Insert plus for mixed unicode vulgar fractions like "1½" -> "1+½"
    $norm = preg_replace('/(\d)\s*([\x{00BD}\x{00BC}\x{00BE}\x{2153}\x{2154}\x{215B}\x{215C}\x{215D}\x{215E}])/u', '$1+$2', $norm) ?? $norm;
    // 4) Map unicode vulgar fractions to ASCII fractions for easier handling
    $mapUniToAscii = [
        "\x{00BC}"=>'1/4', "\x{00BD}"=>'1/2', "\x{00BE}"=>'3/4',
        "\x{2153}"=>'1/3', "\x{2154}"=>'2/3', "\x{215B}"=>'1/8',
        "\x{215C}"=>'3/8', "\x{215D}"=>'5/8', "\x{215E}"=>'7/8',
    ];
    $norm = strtr($norm, $mapUniToAscii);
    // 5) Normalize decimal comma to dot inside numbers
    $norm = preg_replace('/(\d),(?=\d)/u', '.', $norm) ?? $norm;
    // 6) Convert ASCII fractions to decimals where safe
    //    mixed: "a b/c" -> a + b/c
    $norm = preg_replace_callback('/(?<!\d)(\d+)\s+(\d+)\/(\d+)(?!\d)/u', function($m){
        $a=(float)$m[1]; $b=(float)$m[2]; $c=max(1.0,(float)$m[3]); return (string)($a + ($b/$c));
    }, $norm) ?? $norm;
    //    simple: "b/c"
    $norm = preg_replace_callback('/(?<!\d)(\d+)\/(\d+)(?!\d)/u', function($m){
        $b=(float)$m[1]; $c=max(1.0,(float)$m[2]); return (string)($b/$c);
    }, $norm) ?? $norm;
    // 7) Collapse whitespace
    $norm = preg_replace('/\s+/u', ' ', $norm) ?? $norm;
    $norm = trim($norm);

    // Extract range if present at line start: a - b
    $range = null;
    if (preg_match('/^(\d+(?:\.\d+)?)\s*[-]\s*(\d+(?:\.\d+)?)/u', $norm, $rm)) {
        $range = ['low'=>(float)$rm[1], 'high'=>(float)$rm[2]];
    }
    return ['raw' => $raw, 'norm' => $norm, 'range' => $range];
}
}
