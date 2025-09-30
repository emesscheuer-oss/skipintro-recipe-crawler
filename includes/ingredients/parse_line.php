<?php
// parse_line.php (v2f) â€” targeted fixes:
// - Merge split fractions from modular path (e.g., qty=1 & item starts with "/2 â€¦" â†’ qty=0.5, trim "/2")
// - If modular misses unit but item starts with unit word, extract it (e.g., "1.5 Liter Wasser")
// - Textual half: "eine/einer halbe/halben ..." â†’ qty=0.5
// - Normalize fraction slash U+2044 (â„) to '/'
// - Robust prenorm: spaces, dashes, decimal comma, vulgar fractions, digit-letter spacing
// UTF-8 (no BOM), no declare(), no closing tag.

require_once __DIR__ . '/i18n.php';
$__sitc_qty = __DIR__ . '/qty.php';
if (is_file($__sitc_qty)) { require_once $__sitc_qty; }

// Optional: pre-QTY/Unit text normalizer
$__sitc_norm = realpath(__DIR__ . '/../parser/filters/ingredient-text-normalizer.php');
if ($__sitc_norm && is_file($__sitc_norm)) { @require_once $__sitc_norm; }

/**
 * Conservative pre-normalization for parser.
 */
function _sitc_ing_prenorm(string $s): string {
    // spaces: NBSP, NNBSP, thin spaces
    $s = preg_replace('/\x{00A0}|\x{202F}|\x{2009}/u', ' ', $s) ?? $s;
    // unicode vulgar fractions â†’ ascii
    $map = [
        'Â¼'=>'1/4','Â½'=>'1/2','Â¾'=>'3/4',
        'â…'=>'1/7','â…‘'=>'1/9','â…’'=>'1/10','â…“'=>'1/3','â…”'=>'2/3','â…•'=>'1/5','â…–'=>'2/5','â…—'=>'3/5','â…˜'=>'4/5','â…™'=>'1/6','â…š'=>'5/6','â…›'=>'1/8','â…œ'=>'3/8','â…'=>'5/8','â…ž'=>'7/8',
    ];
    $s = strtr($s, $map);
    // normalize fraction slash (U+2044) to regular slash
    $s = str_replace('â„', '/', $s);
    // Decimal comma â†’ dot (but keep thousand separators away)
    $s = preg_replace('/(?<=\d),(?=\d)/', '.', $s) ?? $s;
    // normalize dashes with single spaces
    $s = preg_replace('/\s*[-â€“â€”]\s*/u', ' - ', $s) ?? $s;
    // insert space between digit and following letters/%/Âµ (helps "750g" -> "750 g")
    $s = preg_replace('/(?<=\d)(?=[A-Za-zÃ„Ã–ÃœÃ¤Ã¶Ã¼Âµ%])/', ' ', $s) ?? $s;
    // collapse spaces
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}

/**
 * Convert a numeric token to float.
 */
function _sitc_num_to_float(string $t): ?float {
    $t = trim($t);
    if ($t === '') return null;
    // mixed number: "1 1/2" (optional spaces around slash)
    if (preg_match('/^(\d+)\s+(\d+)\s*\/\s*(\d+)$/', $t, $m)) {
        return (float)$m[1] + ((float)$m[2] / (float)$m[3]);
    }
    // simple fraction: "1/2" or "1 /2"
    if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $t, $m)) {
        return ((float)$m[1] / (float)$m[2]);
    }
    // decimal
    if (preg_match('/^\d+\.\d+$/', $t)) return (float)$t;
    // integer
    if (preg_match('/^\d+$/', $t)) return (float)$t;
    return null;
}

/**
 * Extract a unit at the start of $text if present. Returns [unit|null, remainder].
 */
function _sitc_pull_leading_unit(string $text, array $units): array {
    $unitsQuoted = array_map(function($u){ return preg_quote($u, '/'); }, $units);
    if (empty($unitsQuoted)) return [null, $text];
    $pat = sprintf('/^(%s)\b\s*(.*)$/ui', implode('|', $unitsQuoted));
    if (preg_match($pat, $text, $m)) {
        return [$m[1], trim($m[2])];
    }
    return [null, $text];
}

/**
 * Detect textual "half" at the start. Returns [qty|null, remainder] (qty=0.5 if matched).
 * Handles: "eine halbe Zitrone", "einer halben Zitrone", "halbe Zitrone", ...
 */
function _sitc_pull_textual_half(string $text): array {
    if (preg_match('/^(?:ein(?:e|er|em|en)?\s+)?halb(?:e|en|er|es|em)?\s+(.*)$/iu', $text, $m)) {
        return [0.5, trim($m[1])];
    }
    return [null, $text];
}

/**
 * Fallback simple parse for "qty [unit] item (note)" (+ ranges).
 */
function _sitc_ing_parse_simple(string $line, array $units): array {
    $raw = $line;
    $line = _sitc_ing_prenorm($line);

    $qty = null; $unit = null; $item = $line; $note = null;

    // textual half at start
    if ($qty === null) {
        [$qHalf, $afterHalf] = _sitc_pull_textual_half($line);
        if ($qHalf !== null) {
            $qty = $qHalf;
            [$u, $rest] = _sitc_pull_leading_unit($afterHalf, $units);
            if ($u !== null) $unit = $u;
            $item = $rest;
        }
    }

    // Range at start: a-b ... (each side can be decimal or fraction with optional spaces)
    if ($qty === null) {
        $num = '(?:\d+\s+\d+\s*\/\s*\d+|\d+\s*\/\s*\d+|\d+(?:\.\d+)?)';
        if (preg_match('/^\s*(' . $num . ')\s*-\s*(' . $num . ')(?=\s|$)(.*)$/u', $line, $m)) {
            $low = _sitc_num_to_float(trim($m[1]));
            $high = _sitc_num_to_float(trim($m[2]));
            if ($low !== null && $high !== null) {
                $qty = ['low'=>$low,'high'=>$high];
                $rest = ltrim($m[3]);
                [$u, $rest2] = _sitc_pull_leading_unit($rest, $units);
                if ($u !== null) $unit = $u;
                $item = $rest2;
            }
        }
    }

    // Simple start qty (fraction variants are supported)
    if ($qty === null && preg_match('/^\s*((?:\d+\s+\d+\s*\/\s*\d+|\d+\s*\/\s*\d+|\d+(?:\.\d+)?))(?=\s|$)(.*)$/u', $line, $m)) {
        $q = _sitc_num_to_float(trim($m[1]));
        if ($q !== null) {
            $qty = $q;
            $rest = ltrim($m[2]);
            [$u, $rest2] = _sitc_pull_leading_unit($rest, $units);
            if ($u !== null) $unit = $u;
            $item = $rest2;
        }
    }

    // (note) at end
    if ($item !== '' && preg_match('/^(.*?)\s*\(([^()]+)\)\s*$/u', $item, $nm)) {
        $item = trim($nm[1]);
        $note = trim($nm[2]);
    }

    return [
        'raw'  => $raw,
        'qty'  => $qty,
        'unit' => $unit,
        'item' => $item,
        'note' => $note,
    ];
}

/**
 * Public v2 parser: modular pipeline if available, otherwise simple fallback.
 */
function sitc_ing_v2_parse_line(string $line, string $locale = 'de'): array {
    // Pre-normalizer ensures the normalizer runs across all v2 paths (frontend, refresh, lab)
    if (class_exists('Skipintro\\RecipeCrawler\\Parser\\Filters\\Ingredient_Text_Normalizer')) {
        $line = \Skipintro\RecipeCrawler\Parser\Filters\Ingredient_Text_Normalizer::normalize($line);
    }
    $raw = (string)$line;
    $norm = _sitc_ing_prenorm($raw);
    if (function_exists('sitc_qty_normalize')) {
        $norm = sitc_qty_normalize($norm);
    }

    // Units (for post-fixes and fallback)
    $units = ['g','kg','mg','l','ml','Liter','EL','TL','Prise','Stk','StÃ¼ck','Stueck','Bund','Dose','Tasse','Becher','Pck','Pck.','Paket','Scheibe','Zehe','Knolle','Paar'];
    if (function_exists('sitc_ing_units_for_locale')) {
        $lex = sitc_ing_units_for_locale($locale);
        if (!empty($lex['units']) && is_array($lex['units'])) {
            $units = array_unique(array_merge($units, array_keys($lex['units'])));
        }
    }

    $have = function_exists('sitc_ing_tokenize')
         && function_exists('sitc_ing_detect_qty')
         && function_exists('sitc_ing_detect_unit')
         && function_exists('sitc_ing_extract_item_note');

    if ($have) {
        try {
            $toks = sitc_ing_tokenize($norm, $locale);
            $qtyRaw  = sitc_ing_detect_qty($toks, $locale);
            $unit    = sitc_ing_detect_unit($toks, $locale);
            $rest    = sitc_ing_extract_item_note($toks, $qtyRaw, $unit, $locale);

            // Map qty to float/range
            $outQty = null;
            if (is_array($qtyRaw)) {
                if (isset($qtyRaw['low'], $qtyRaw['high'])) {
                    $low  = is_string($qtyRaw['low'])  ? _sitc_num_to_float($qtyRaw['low'])  : (float)$qtyRaw['low'];
                    $high = is_string($qtyRaw['high']) ? _sitc_num_to_float($qtyRaw['high']) : (float)$qtyRaw['high'];
                    if ($low !== null && $high !== null) {
                        $outQty = ['low'=>$low,'high'=>$high];
                    }
                } else {
                    foreach (['val','value','amount','num','q'] as $k) {
                        if (isset($qtyRaw[$k])) {
                            $v = $qtyRaw[$k];
                            if (is_string($v)) { $v = _sitc_num_to_float($v); }
                            if (is_numeric($v)) { $outQty = (float)$v; break; }
                        }
                    }
                }
            } elseif (is_string($qtyRaw)) {
                $v = _sitc_num_to_float($qtyRaw);
                if ($v !== null) $outQty = $v;
            } elseif (is_numeric($qtyRaw)) {
                $outQty = (float)$qtyRaw;
            }

            // Pull fields
            $itemText = (string)($rest['item'] ?? $rest['name'] ?? $rest ?? $norm);
            $noteText = $rest['note'] ?? null;

            // Post-fix A: textual half at start (when qty missing)
            if ($outQty === null) {
                [$qHalf, $afterHalf] = _sitc_pull_textual_half($itemText);
                if ($qHalf !== null) {
                    $outQty = $qHalf;
                    [$u, $afterUnit] = _sitc_pull_leading_unit($afterHalf, $units);
                    if ($u !== null) $unit = $u;
                    $itemText = $afterUnit;
                }
            }

            // Post-fix B: merge split fraction like "qty=1" and item starts "/2 ..."
            if (is_numeric($outQty) && preg_match('/^\s*\/\s*(\d+)\b(.*)$/u', $itemText, $fm)) {
                $den = (int)$fm[1];
                if ($den > 0) {
                    $outQty = ((float)$outQty) / $den;
                    $itemText = ltrim($fm[2]);
                }
            }

            // Post-fix C: if unit was missed but item starts with a unit word, pull it out
            if (($unit === null || $unit === '') && $itemText !== '') {
                [$u, $afterUnit] = _sitc_pull_leading_unit($itemText, $units);
                if ($u !== null) {
                    $unit = $u;
                    $itemText = $afterUnit;
                }
            }

            return [
                'raw'  => $raw,
                'qty'  => $outQty,
                'unit' => $unit !== null ? (string)$unit : null,
                'item' => (string)$itemText,
                'note' => $noteText,
            ];
        } catch (\Throwable $e) {
            // fall back
        }
    }

    // Fallback
    return _sitc_ing_parse_simple($norm, $units);
}

if (!function_exists('sitc_ing_parse_line_mod')) {
    function sitc_ing_parse_line_mod(string $line, string $locale = 'de'): array {
        // << NEU: Nur im mod-Pfad voranstellen â€“ QTY/Unit-Logik bleibt unberÃ¼hrt >>
        if (class_exists('Skipintro\\RecipeCrawler\\Parser\\Filters\\Ingredient_Text_Normalizer')) {
            $line = \Skipintro\RecipeCrawler\Parser\Filters\Ingredient_Text_Normalizer::normalize($line);
        }
        return sitc_ing_v2_parse_line($line, $locale);
    }
}
