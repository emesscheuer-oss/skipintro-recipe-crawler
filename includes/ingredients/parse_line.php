<?php
declare(strict_types=1);
require_once __DIR__ . '/i18n.php';

/**
 * Modular parser: tokenize → detect qty → detect unit → extract item/note.
 * @return array{raw:string, qty:float|array{low:float,high:float}|null, unit:?string, item:string, note:?string}
 */
function sitc_ing_parse_line_mod(string $line, string $locale = 'de'): array {
    // Guard: ensure required modular functions are available
    $have = function_exists('sitc_ing_tokenize')
         && function_exists('sitc_ing_detect_qty')
         && function_exists('sitc_ing_detect_unit')
         && function_exists('sitc_ing_extract_item_note');
    if (!$have) {
        $line = (string)$line;
        return ['raw'=>$line,'qty'=>null,'unit'=>null,'item'=>trim($line),'note'=>null,'_diag'=>'mod_not_loaded'];
    }
    $raw = (string)$line;
    // 1) Tokenize (raw/norm/range)
    $tok = sitc_ing_tokenize($raw, $locale);
    // 2) Qty
    $qty = sitc_ing_detect_qty($tok, $locale);
    // 3) Unit
    $unit = sitc_ing_detect_unit($tok, $qty, $locale);
    // 4) Item/Note
    $en = sitc_ing_extract_item_note($tok, $qty, $unit, $locale);
    // Canonicalize unit if available
    if (!empty($unit) && function_exists('sitc_unit_alias_canonical')) {
        $u = sitc_unit_alias_canonical((string)$unit);
        $unit = $u ?: $unit;
    }
    $item = (string)($en['item'] ?? trim($raw));
    $noteVal = isset($en['note']) && $en['note'] !== '' ? (string)$en['note'] : null;
    $lex = function_exists('sitc_ing_load_locale') ? sitc_ing_load_locale($locale) : null;

    if (($unit === null || $unit === '') && sitc_ing_should_default_piece($qty, $item, $lex, $locale)) {
        $unit = 'piece';
    }

    return [
        'raw'  => $raw,
        'qty'  => $qty,
        'unit' => $unit,
        'item' => $item,
        'note' => $noteVal,
    ];
}

if (!function_exists('sitc_ing_should_default_piece')) {
function sitc_ing_should_default_piece($qty, string $item, ?array $lex = null, string $locale = 'de'): bool {
    $hasQty = false;
    if (is_array($qty)) {
        $low = $qty['low'] ?? null;
        $high = $qty['high'] ?? null;
        $hasQty = ($low !== null && $low !== '') || ($high !== null && $high !== '');
    } elseif ($qty !== null && $qty !== '') {
        $hasQty = is_numeric($qty);
    }
    if (!$hasQty) {
        return false;
    }

    if (!function_exists('sitc_slugify_lite')) {
        return false;
    }

    if ($lex === null && function_exists('sitc_ing_load_locale')) {
        $lex = sitc_ing_load_locale($locale);
    }
    $lex = is_array($lex) ? $lex : [];

    $slug = sitc_slugify_lite($item);
    if ($slug === '') {
        return false;
    }

    $uncountable = $lex['uncountable_nouns'] ?? [];
    if ($uncountable && function_exists('sitc_ing_match_patterns') && sitc_ing_match_patterns($slug, $uncountable)) {
        return false;
    }

    $countable = $lex['countable_nouns'] ?? [];
    if ($countable && function_exists('sitc_ing_match_patterns') && sitc_ing_match_patterns($slug, $countable)) {
        return true;
    }

    return false;
}
}




