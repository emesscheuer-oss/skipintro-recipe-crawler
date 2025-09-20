<?php
declare(strict_types=1);
/**
 * Normalizes ingredient lines for modular parsing (qty/unit/note detection).
 */
if (!function_exists('sitc_ing_tokenize')) {
function sitc_ing_tokenize(string $line, string $locale = 'de'): array {
    $raw = (string)$line;
    $norm = $raw;

    // 0) Normalize NBSP variants to regular spaces
    $norm = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $norm) ?? $norm;

    // 1) Remove qty-context stopwords (ca., etwa, approx.)
    if (function_exists('sitc_norm_strip_stopwords')) {
        $norm = sitc_norm_strip_stopwords($norm);
    }

    // 2) Normalize dash characters
    if (function_exists('sitc_norm_unify_dashes')) {
        $norm = sitc_norm_unify_dashes($norm);
    } else {
        $norm = preg_replace('/[\x{2012}-\x{2015}\x{2212}]/u', '-', $norm) ?? $norm;
    }

    // Ensure numeric ranges use en dash consistently
    $norm = preg_replace('/(?<=\d)\s*[-\\u{2013}\x{2014}]\s*(?=\d)/u', "\\u{2013}", $norm) ?? $norm;

    // 3) Mixed unicode vulgar fractions attached to integers (e.g. 1 1/2)
    $unicodeDecimal = [
        "\x{00BC}" => '0.25',
        "\x{00BD}" => '0.5',
        "\x{00BE}" => '0.75',
    ];
    $norm = preg_replace_callback('/(\d)\s*([\x{00BC}\x{00BD}\x{00BE}])/u', function(array $m) use ($unicodeDecimal) {
        $base = (string)$m[1];
        $frac = $unicodeDecimal[$m[2]] ?? '0';
        return $base . ' + ' . $frac;
    }, $norm) ?? $norm;

    // 4) Replace remaining unicode fractions with decimals / ASCII helpers
    $norm = strtr($norm, $unicodeDecimal);
    $norm = strtr($norm, [
        "\x{2153}"=>'1/3', "\x{2154}"=>'2/3', "\x{215B}"=>'1/8',
        "\x{215C}"=>'3/8', "\x{215D}"=>'5/8', "\x{215E}"=>'7/8',
    ]);

    // 5) Normalize decimal comma to dot inside numbers
    $norm = preg_replace('/(\d),(?=\d)/u', '$1.', $norm) ?? $norm;

    // 6) Collapse mixed numbers (e.g. "1 + 1/2", "1 1/2") to decimals
    $norm = preg_replace_callback('/(?<!\d)(\d+)(?:\s*\+\s*|\s+)(\d+)\/(\d+)/u', function(array $m){
        $a = (float)$m[1];
        $b = (float)$m[2];
        $c = max(1.0, (float)$m[3]);
        return (string)($a + ($b / $c));
    }, $norm) ?? $norm;

    // 7) Convert standalone ASCII fractions to decimals
    $norm = preg_replace_callback('/(?<!\d)(\d+)\/(\d+)(?!\d)/u', function(array $m){
        $num = (float)$m[1];
        $den = max(1.0, (float)$m[2]);
        return (string)($num / $den);
    }, $norm) ?? $norm;

    // 8) Reduce explicit plus notation like "1 + 0.5" to decimals
    $norm = preg_replace_callback('/(?<!\d)(\d+(?:\.\d+)?)\s*\+\s*(\d+(?:\.\d+)?)/u', function(array $m){
        return (string)((float)$m[1] + (float)$m[2]);
    }, $norm) ?? $norm;

    // 9) Ensure space between number and following unit letters (e.g., 2EL -> 2 EL, 0.5l -> 0.5 l)
    $norm = preg_replace('/(\d)(\p{L})/u', '$1 $2', $norm) ?? $norm;

    // 10) Collapse whitespace
    $norm = preg_replace('/\s+/u', ' ', $norm) ?? $norm;
    $norm = trim($norm);

    // Extract range if present at line start: a – b
    $range = null;
    if (preg_match('/^(\d+(?:\.\d+)?)\s*[\\u{2013}-]\s*(\d+(?:\.\d+)?)/u', $norm, $rm)) {
        $low = (float)$rm[1];
        $high = (float)$rm[2];
        if ($high >= $low) {
            $range = ['low' => $low, 'high' => $high];
        } else {
            $range = ['low' => $high, 'high' => $low];
        }
    }

    return ['raw' => $raw, 'norm' => $norm, 'range' => $range];
}
}

