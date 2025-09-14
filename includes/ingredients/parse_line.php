<?php
declare(strict_types=1);

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
    return [
        'raw'  => $raw,
        'qty'  => $qty,
        'unit' => $unit,
        'item' => (string)($en['item'] ?? trim($raw)),
        'note' => isset($en['note']) && $en['note'] !== '' ? (string)$en['note'] : null,
    ];
}
