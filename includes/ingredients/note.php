<?php
declare(strict_types=1);

/**
 * Item/Notes extraction: comma/parentheses/"-Stück"/TK; removes qty/unit prefix.
 */
if (!function_exists('sitc_ing_extract_item_note')) {
function sitc_ing_extract_item_note(array $tok, $qty, ?string $unit, string $locale = 'de'): array {
    $raw  = (string)($tok['raw'] ?? '');
    $norm = (string)($tok['norm'] ?? $raw);

    // 1) Remove leading qty (range or single)
    $rest = $norm;
    if (is_array($qty) && isset($qty['low'],$qty['high'])) {
        if (preg_match('/^\s*(\d+(?:\.\d+)?)\s*[-]\s*(\d+(?:\.\d+)?)/u', $rest, $m)) {
            $rest = (string)substr($rest, strlen($m[0]));
        }
    } elseif (is_numeric($qty)) {
        if (preg_match('/^\s*(\d+(?:\.\d+)?)/u', $rest, $m)) {
            $rest = (string)substr($rest, strlen($m[0]));
        }
    }

    // 2) Optional unit token directly after (strip only if unit detected)
    if ($unit !== null && preg_match('/^\s*([\p{L}\.]+)/u', $rest, $um)) {
        $tokUnit = rtrim($um[1], '.');
        $canon = function_exists('sitc_unit_alias_canonical') ? sitc_unit_alias_canonical($tokUnit) : null;
        if ($canon === $unit) {
            $rest = (string)substr($rest, strlen($um[0]));
        }
    }
    $rest = trim($rest);

    // 3) Extract parentheses notes at end
    $note = null;
    if (preg_match('/^(.*)\(([^\)]*)\)\s*$/u', $rest, $pm)) {
        $rest = trim($pm[1]);
        $note = trim($pm[2]);
    }
    // 4) Comma tail note
    if (strpos($rest, ',') !== false) {
        $parts = array_map('trim', explode(',', $rest, 2));
        if (count($parts) === 2) {
            $rest = $parts[0];
            $note = trim($note ? ($note.'; '.$parts[1]) : $parts[1]);
        }
    }
    // 5) TK at start
    if (preg_match('/^TK\b/u', $rest)) {
        $rest = trim(preg_replace('/^TK\b\s*/u', '', $rest));
        $note = trim($note ? ('TK; '.$note) : 'TK');
    }
    // 6) Hyphen suffix "-Stück" becomes note
    if (preg_match('/-\s*St(?:u|ü|ue)ck\b/u', $rest)) {
        $rest = trim(preg_replace('/-\s*St(?:u|ü|ue)ck\b/u', '', $rest));
        $note = trim($note ? ($note.'; Stück') : 'Stück');
    }
    // 7) If unit is clove, remove zehe/zehen tokens from item
    if ($unit === 'clove') {
        $rest = preg_replace('/\bzehe(n)?\b/iu', '', $rest) ?? $rest;
    }

    $item = trim(preg_replace('/\s{2,}/u', ' ', (string)$rest) ?? '');
    $item = trim($item, " \t.,;:-");
    return ['item' => $item, 'note' => ($note !== null && $note !== '') ? $note : null];
}
}
