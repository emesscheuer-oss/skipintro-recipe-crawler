<?php
declare(strict_types=1);

/**
 * Qty-Erkennung (härtet Unicode/ASCII-Brüche, gemischte Zahlen, Ranges, Dezimalkomma).
 * Gibt float oder {low,high} zurück; kein String.
 */
if (!function_exists('sitc_ing_detect_qty')) {
function sitc_ing_detect_qty(array $tok, string $locale = 'de') {
    $s = (string)($tok['norm'] ?? ($tok['raw'] ?? ''));
    // a) Range (aus Tokenizer oder per Pattern)
    if (!empty($tok['range']) && is_array($tok['range'])) return $tok['range'];
    if (preg_match('/^(\d+(?:\.\d+)?)\s*[-]\s*(\d+(?:\.\d+)?)/u', $s, $m)) {
        return ['low'=>(float)$m[1],'high'=>(float)$m[2]];
    }
    // b) Gemischte Zahl: a + b/c (Tokenizer hat Unicode gemappt)
    if (preg_match('/^\s*(\d+)\s*\+\s*(\d+)\/(\d+)/u', $s, $m)) {
        $a=(float)$m[1]; $b=(float)$m[2]; $c=max(1.0,(float)$m[3]);
        return $a + ($b/$c);
    }
    // c) Dezimal oder Ganzzahl am Anfang
    if (preg_match('/^\s*(\d+(?:\.\d+)?)/u', $s, $m)) {
        return (float)$m[1];
    }
    return null;
}
}

