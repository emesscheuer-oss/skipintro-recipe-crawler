<?php
declare(strict_types=1);
require_once __DIR__ . '/i18n.php';

/**
 * Qty-Erkennung (Unicode/ASCII-Brueche, gemischte Zahlen, Ranges, Dezimalkomma, halbe-Texte).
 * Gibt float oder {low,high} zurueck; kein String.
 */
if (!function_exists('sitc_ing_detect_qty')) {
function sitc_ing_detect_qty(array $tok, string $locale = 'de') {
    $s = (string)($tok['norm'] ?? ($tok['raw'] ?? ''));
    $lex = function_exists('sitc_ing_load_locale') ? sitc_ing_load_locale($locale) : [];
    $rawSource = (string)($tok['raw'] ?? $s);

    // a) Range (aus Tokenizer oder per Pattern)
    if (!empty($tok['range']) && is_array($tok['range'])) {
        return $tok['range'];
    }
    if (preg_match('/^(\d+(?:\.\d+)?)\s*[\x{2013}-]\s*(\d+(?:\.\d+)?)/u', $s, $m)) {
        return ['low' => (float)$m[1], 'high' => (float)$m[2]];
    }

    // b) Gemischte Zahl: a + b/c oder a + 0.5
    if (preg_match('/^\s*(\d+)\s*\+\s*(\d+)\/(\d+)/u', $s, $m)) {
        $a = (float)$m[1];
        $b = (float)$m[2];
        $c = max(1.0, (float)$m[3]);
        return $a + ($b / $c);
    }
    if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*\+\s*(\d+(?:\.\d+)?)/u', $s, $m)) {
        return (float)$m[1] + (float)$m[2];
    }

    // c) Dezimal oder Ganzzahl am Anfang
    if (preg_match('/^\s*(\d+(?:\.\d+)?)/u', $s, $m)) {
        return (float)$m[1];
    }

    // d) Textuelle halbe-Angaben auf Basis des Lexikons
    if ($lex && !empty($lex['fraction_words']) && function_exists('sitc_slugify_lite') && function_exists('sitc_ing_match_patterns')) {
        $slug = sitc_slugify_lite($rawSource);
        if ($slug !== '' && sitc_ing_match_patterns($slug, $lex['fraction_words'])) {
            $countable = $lex['countable_nouns'] ?? [];
            if (!$countable || sitc_ing_match_patterns($slug, $countable)) {
                return 0.5;
            }
        }
    }

    return null;
}
}
