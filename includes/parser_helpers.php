<?php
if (!defined('ABSPATH')) exit;

// PCRE2-safe split helper: never returns false; returns [$s] on regex failure
if (!function_exists('sitc_pcre2_split')) {
    function sitc_pcre2_split(string $pattern, string $s): array {
        $out = @preg_split($pattern, $s, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($out) ? $out : [$s];
    }
}

// E4 helpers: minimal normalizers (guards)
if (!function_exists('sitc_norm_strip_stopwords')) {
    function sitc_norm_strip_stopwords(string $s): string {
        // Delegate to existing stopword stripper if present
        if (function_exists('sitc_strip_stopwords_qty_context')) {
            return sitc_strip_stopwords_qty_context($s);
        }
        // Basic leading patterns
        return preg_replace('/^\s*(?:ca\.?|circa|etwa|ungef\.?|ungef(?:ae|ä)hr\.?|about|approx\.?|approximately|\(ca\.?\))\s+/iu', ' ', $s) ?? $s;
    }
}

if (!function_exists('sitc_norm_unify_dashes')) {
    function sitc_norm_unify_dashes(string $s): string {
        return preg_replace('/[\x{2012}-\x{2015}]/u', '-', $s) ?? $s;
    }
}

if (!function_exists('sitc_norm_fractions_to_decimal')) {
    function sitc_norm_fractions_to_decimal(string $s): string {
        // Insert plus for mixed forms with unicode vulgar fractions
        $s = preg_replace('/(\d)\s*([\x{00BD}\x{00BC}\x{00BE}\x{2153}\x{2154}\x{215B}])/u', '$1+$2', $s) ?? $s;
        // Map unicode to ASCII fraction tokens
        $map = [
            "\x{00BD}"=>'1/2', "\x{00BC}"=>'1/4', "\x{00BE}"=>'3/4',
            "\x{2153}"=>'1/3', "\x{2154}"=>'2/3', "\x{215B}"=>'1/8',
        ];
        $s = strtr($s, $map);
        // Mixed ASCII form: "a b/c" -> "a+b/c"
        $s = preg_replace('/(?<!\d)(\d+)\s+(\d+)\/(\d+)(?!\d)/u', '$1+$2/$3', $s) ?? $s;
        return $s;
    }
}

if (!function_exists('sitc_norm_commas_to_dot')) {
    function sitc_norm_commas_to_dot(string $s): string {
        return preg_replace('/(\d),(?=\d)/u', '.', $s) ?? $s;
    }
}

if (!function_exists('sitc_norm_trim_ws')) {
    function sitc_norm_trim_ws(string $s): string {
        $t = preg_replace('/\s+/u', ' ', $s);
        return trim($t ?? $s);
    }
}

// FIX-QTY-CORE: normalize unicode fractions (incl. mixed forms like 1½ -> 1 1/2)
if (!function_exists('sitc_normalize_unicode_fractions')) {
    function sitc_normalize_unicode_fractions(string $s): string {
        // Insert space between integer and unicode fraction
        $s = preg_replace('/(\d)\s*([\x{00BD}\x{00BC}\x{00BE}\x{2153}\x{2154}\x{215B}])/u', '$1 $2', $s);
        // Replace unicode fractions with ASCII equivalents
        $map = [
            "\x{00BD}" => '1/2', // ½
            "\x{00BC}" => '1/4', // ¼
            "\x{00BE}" => '3/4', // ¾
            "\x{2153}" => '1/3', // ⅓
            "\x{2154}" => '2/3', // ⅔
            "\x{215B}" => '1/8', // ⅛
        ];
        return strtr($s, $map);
    }
}

// FIX-QTY-CORE: normalize leading decimals and decimal comma
if (!function_exists('sitc_normalize_leading_decimal')) {
    function sitc_normalize_leading_decimal(string $s): string {
        $s = preg_replace('/(\d),(?=\d)/u', '.', $s);
        $s = preg_replace('/(?<!\d)\.([0-9]+)/u', '0.$1', $s);
        return $s;
    }
}

// FIX-QTY-CORE: convert ASCII fractions to decimals for tokens
if (!function_exists('sitc_fraction_to_decimal')) {
    function sitc_fraction_to_decimal(string $s): string {
        $s = preg_replace_callback('/(?<!\d)(\d+)\s+(\d+)\/(\d+)(?!\d)/u', function($m){
            $a=(float)$m[1]; $b=(float)$m[2]; $c=max(1.0,(float)$m[3]); return (string)($a + ($b/$c));
        }, $s);
        $s = preg_replace_callback('/(?<!\d)(\d+)\/(\d+)(?!\d)/u', function($m){
            $b=(float)$m[1]; $c=max(1.0,(float)$m[2]); return (string)($b/$c);
        }, $s);
        return $s;
    }
}

// FIX-QTY-CORE: Strip leading qty and unit tokens from item string
if (!function_exists('sitc_strip_leading_qty_unit')) {
    function sitc_strip_leading_qty_unit(string $s, array $knownUnits): string {
        $t = trim($s);
        if ($t === '') return $t;
        // remove number at start
        $t = preg_replace('/^\s*(?:\d+(?:[\.,]\d+)?)(?:\s+)?/u', '', $t);
        // remove optional unit token
        if ($knownUnits) {
            $pattern = '/^(?:'.implode('|', array_map(fn($u)=>preg_quote($u,'/'), $knownUnits)).')(?:\.?s)?\b\s*/iu';
            $t = preg_replace($pattern, '', $t);
        }
        return trim($t);
    }
}
// D-FIX: normalize qty tokens (leading dot, decimal comma, unicode fractions to ascii, dashes, "bis")
if (!function_exists('sitc_normalize_qty_tokens')) {
    function sitc_normalize_qty_tokens(string $s): string {
        $t = $s;
        // NBSP variants to space
        $t = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $t);
        // leading ".50" â†’ " 0.50" (space before to aid tokenization), but keep prefix
        $t = preg_replace('/(^|[^0-9])\.([0-9]+)/u', '$1 0.$2', $t);
        // decimal comma inside numbers
        $t = preg_replace('/(\d),(?=\d)/u', '.', $t);
        // unicode vulgar fraction chars â†’ ascii fraction strings
        $map = [ 'Â½'=>'1/2', 'Â¼'=>'1/4', 'Â¾'=>'3/4', 'â…“'=>'1/3', 'â…”'=>'2/3', 'â…›'=>'1/8' ];
        $t = strtr($t, $map);
        // unify dashes and "bis" to hyphen
        $t = preg_replace('/[\x{2012}-\x{2015}]/u', '-', $t);
        $t = preg_replace('/\s+bis\s+/iu', '-', $t);
        return trim(preg_replace('/\s{2,}/', ' ', $t));
    }
}

// D-FIX-2: remove consumed prefix from start of line (qty or range fragment)
if (!function_exists('sitc_trim_consumed_prefix')) {
    function sitc_trim_consumed_prefix(string $line, string $consumed): string {
        $l = $line;
        $c = trim($consumed);
        if ($c !== '') {
            $pattern = '/^\s*' . preg_quote($c, '/') . '\s*/u';
            $l = preg_replace($pattern, '', $l, 1);
            if ($l === null) $l = $line;
        }
        return trim(preg_replace('/\s{2,}/u', ' ', $l));
    }
}

// D-FIX-2: extract unit, item, note from remainder after qty/range
if (!function_exists('sitc_extract_unit_item_note')) {
    function sitc_extract_unit_item_note(string $rest): array {
        $unit = null; $item = ''; $note = null;
        $s = trim($rest);
        if ($s === '') return ['unit'=>null,'item'=>'','note'=>null];

        // Leading TK flag becomes part of note
        if (preg_match('/^TK\b/u', $s)) { $note = 'TK'; $s = trim(preg_replace('/^TK\b\s*/u','', $s)); }

        // Unit at start (letter token)
        if (preg_match('/^(\p{L}+)/u', $s, $m)) {
            $uTok = $m[1];
            $uCanon = sitc_unit_alias_canonical($uTok);
            if ($uCanon) {
                $unit = $uCanon;
                $s = trim(substr($s, strlen($uTok)));
            } else {
                $uLower = mb_strtolower($uTok, 'UTF-8');
                if (in_array($uLower, ['stück','stǬck','stueck','stk'], true)) {
                    $unit = 'piece';
                    $s = trim(substr($s, strlen($uTok)));
                }
            }
        }

        // Trailing notes: parentheses and comma tail
        $tn = sitc_extract_trailing_notes($s);
        $item = trim($tn['clean']);
        if ($tn['note']) { $note = trim(($note ? $note . '; ' : '') . $tn['note']); }

        // Hyphen note like Ingwer-StÃ¼ck when weight unit present
        if ($unit !== null) {
            $hn = sitc_hyphen_note_split($item, $unit);
            if ($hn['note']) { $item = $hn['item']; $note = trim(($note ? $note . '; ' : '') . $hn['note']); }
        }

        $note = $note !== null ? preg_replace('/\s*,\s*/', ', ', trim($note)) : null;
        return ['unit'=>$unit,'item'=>$item,'note'=>($note!==''?$note:null)];
    }
}

// D-FIX-2: new stable struct builder (no multi-split, range/single-first, robust unit/item/note)
if (!function_exists('sitc_struct_from_line_df2')) {
    function sitc_struct_from_line_df2(string $rawLine): array {
        $out = [];
        $line = sitc_strip_stopwords_qty_context($rawLine);
        // FIX-QTY-CORE pre-normalization: unicode fractions, leading decimals, ASCII fractions
        $line = sitc_normalize_unicode_fractions($line);
        $line = sitc_normalize_leading_decimal($line);
        $line = sitc_fraction_to_decimal($line);
        // Do not destroy slashes; only normalize qty tokens
        $line = sitc_normalize_qty_tokens($line);

        // Split by bullets/semicolon/2+ spaces
        $parts = sitc_pcre2_split('/(?:\s*[;•·]\s+|\h{2,})/u', $line);
        $parts = array_values(array_filter(array_map('trim', $parts), fn($s)=>$s!=='' && !sitc_is_bare_number($s)));
        if (!$parts) $parts = [$line];

        foreach ($parts as $part) {
            $seg = trim($part);
            if ($seg==='') continue;
            if (sitc_is_bare_number($seg)) continue;

            $qty = null; $unit = null; $item = ''; $note = null;

            // Range first
            if (preg_match('/^\s*(\S+\s*\-\s*\S+)\b(.*)$/u', $seg, $m)) {
                $consumed = trim($m[1]);
                $r = sitc_extract_range($consumed);
                    if ($r) {
                        $qty = $r;
                        $rest = sitc_trim_consumed_prefix($seg, $consumed);
                        $e = sitc_extract_unit_item_note($rest);
                        $unit = $e['unit']; $item = $e['item']; $note = $e['note'];
                        if (function_exists('sitc_unit_alias_map')) { $item = sitc_strip_leading_qty_unit($item, array_keys(sitc_unit_alias_map())); }
                    }
            }

            // Single qty
            if ($qty === null) {
                $tokens = sitc_tokenize_numbers_range_aware($seg);
                if (!empty($tokens)) {
                    $tok = $tokens[0];
                    $qv = sitc_parse_mixed_or_fraction($tok);
                        if ($qv !== null) {
                            $qty = (float)$qv;
                            $rest = sitc_trim_consumed_prefix($seg, $tok);
                            $e = sitc_extract_unit_item_note($rest);
                            $unit = $e['unit']; $item = $e['item']; $note = $e['note'];
                            if (function_exists('sitc_unit_alias_map')) { $item = sitc_strip_leading_qty_unit($item, array_keys(sitc_unit_alias_map())); }
                        }
                }
            }

            if ($qty === null && $item === '') { $item = $seg; }
            $item = trim($item);
            if ($item === '' && $unit === null) continue;
            if ($qty !== null && $item === '' && $unit === null) continue;
            if (sitc_is_bare_number($item)) continue;
            $out[] = [
                'raw'  => $seg,
                'qty'  => $qty,
                'unit' => $unit,
                'item' => $item,
                'note' => $note !== null ? preg_replace('/\s*,\s*/', ', ', trim((string)$note)) : null,
            ];
        }
        return $out;
    }
}

// G1 wrappers (compat): qty normalize, range parse, stopwords, notes
if (!function_exists('sitc_qty_normalize')) {
    function sitc_qty_normalize(string $s): ?float {
        if (function_exists('sitc_coerce_qty_float')) {
            return sitc_coerce_qty_float($s);
        }
        $t = trim($s);
        if ($t === '') return null;
        if (function_exists('sitc_parse_mixed_or_fraction')) {
            $v = sitc_parse_mixed_or_fraction($t);
            return is_float($v) ? $v : null;
        }
        $t = str_replace(',', '.', $t);
        return is_numeric($t) ? (float)$t : null;
    }
}

if (!function_exists('sitc_qty_parse_range')) {
    function sitc_qty_parse_range(string $s): ?array {
        if (function_exists('sitc_extract_range')) {
            return sitc_extract_range($s);
        }
        $t = preg_replace('/[\x{2012}-\x{2015}]/u', '-', $s) ?? $s;
        if (preg_match('/^\s*(\S+)\s*-\s*(\S+)/u', trim($t), $m)) {
            $a = sitc_qty_normalize($m[1]);
            $b = sitc_qty_normalize($m[2]);
            if ($a !== null && $b !== null) return ['low'=>(float)$a,'high'=>(float)$b];
        }
        return null;
    }
}

if (!function_exists('sitc_strip_stopwords')) {
    function sitc_strip_stopwords(string $s): string {
        if (function_exists('sitc_strip_stopwords_qty_context')) {
            return sitc_strip_stopwords_qty_context($s);
        }
        return preg_replace('/^\s*(?:ca\.?|circa|etwa|ungef\.?|about|approx\.?|\(ca\.?\))\s+/iu', ' ', $s) ?? $s;
    }
}

if (!function_exists('sitc_extract_note_from_item')) {
    function sitc_extract_note_from_item(string $s): array {
        if (function_exists('sitc_extract_trailing_notes')) {
            $tn = sitc_extract_trailing_notes($s);
            return ['item'=>$tn['clean'], 'note'=>$tn['note']];
        }
        $item = trim($s);
        $note = null;
        if (preg_match('/^(.*)\(([^\)]*)\)\s*$/u', $item, $m)) { $item = trim($m[1]); $note = trim($m[2]); }
        if (strpos($item, ',') !== false) {
            [$l,$r] = array_map('trim', explode(',', $item, 2));
            $item = $l; $note = ($note ? $note.', ' : '').$r;
        }
        $note = $note !== '' ? $note : null;
        return ['item'=>$item, 'note'=>$note];
    }
}

// D-FIX: tokenize numbers without splitting ranges (returns array of tokens found in order)
if (!function_exists('sitc_tokenize_numbers_range_aware')) {
    function sitc_tokenize_numbers_range_aware(string $s): array {
        $t = sitc_normalize_qty_tokens($s);
        $tokens = [];
        // find candidates: mixed, fraction, decimal, integer
        $pattern = '/(?:(\d+\s+\d+\/\d+)|(\d+\/\d+)|(\d+\.\d+)|(\d+))/u';
        if (preg_match_all($pattern, $t, $m, PREG_OFFSET_CAPTURE)) {
            $matches = [];
            foreach ($m[0] as $i => $mm) { $matches[] = ['text'=>$mm[0], 'pos'=>$mm[1]]; }
            // range join: if a '-' appears between consecutive matches with only spaces around
            for ($i=0; $i < count($matches); $i++) {
                $cur = $matches[$i];
                if ($i+1 < count($matches)) {
                    $next = $matches[$i+1];
                    $between = substr($t, $cur['pos'] + strlen($cur['text']), $next['pos'] - ($cur['pos'] + strlen($cur['text'])));
                    if (preg_match('/^\s*\-\s*$/u', $between)) {
                        $tokens[] = trim($cur['text'] . '-' . $next['text']);
                        $i++; // skip the next, consumed by range
                        continue;
                    }
                }
                $tokens[] = $cur['text'];
            }
        }
        return $tokens;
    }
}

// D-FIX: parse mixed or fraction to float
if (!function_exists('sitc_parse_mixed_or_fraction')) {
    function sitc_parse_mixed_or_fraction(string $num): ?float {
        $s = trim(sitc_normalize_qty_tokens($num));
        // mixed a b/c
        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/u', $s, $m)) {
            $a=(float)$m[1]; $b=(float)$m[2]; $c=max(1.0,(float)$m[3]);
            return $a + ($b/$c);
        }
        // fraction b/c
        if (preg_match('/^(\d+)\/(\d+)$/u', $s, $m)) {
            return (float)$m[1]/max(1.0,(float)$m[2]);
        }
        // decimal
        if (preg_match('/^\d+\.\d+$/u', $s)) { return (float)$s; }
        // integer
        if (preg_match('/^\d+$/u', $s)) { return (float)$s; }
        return null;
    }
}

// D-FIX: extract range a-b (returns ['low'=>float,'high'=>float] or null). Does not treat slash as range.
if (!function_exists('sitc_extract_range')) {
    function sitc_extract_range(string $s): ?array {
        $t = sitc_normalize_qty_tokens($s);
        if (preg_match('/^\s*(\S+)\s*\-\s*(\S+)/u', $t, $m)) {
            $a = sitc_parse_mixed_or_fraction($m[1]);
            $b = sitc_parse_mixed_or_fraction($m[2]);
            if ($a !== null && $b !== null) return ['low'=>(float)$a, 'high'=>(float)$b];
        }
        return null;
    }
}

// E1: New robust ingredient splitter implementing normalization + notes + guards
if (!function_exists('sitc_struct_from_line_e1')) {
    function sitc_struct_from_line_e1(string $rawLine): array {
        $out = [];
        $pre = sitc_strip_stopwords_qty_context($rawLine);
        $pre = sitc_unicode_fractions_to_decimal_fixed($pre);
        $pre = sitc_decimalize_commas_in_numbers($pre);
        $pn  = sitc_qty_pre_normalize_parser($pre);

        // Split by bullets, middot, semicolon, or 2+ spaces
        $parts = sitc_pcre2_split('/(?:\s*[;â€¢Â·]\s+|\h{2,})/u', $pn);
        $parts = array_values(array_filter(array_map('trim', $parts), fn($s)=>$s!=='' && !sitc_is_bare_number($s)));
        if (!$parts) $parts = [$pn];

        foreach ($parts as $part) {
            // D-FIX: Do not split when multiple qty tokens appear; keep one segment per part
            $segments = [$part];
            foreach ($segments as $seg) {
                if ($seg==='') continue;
                if (sitc_is_bare_number($seg)) continue;
                $qtyField = null; $unitField = null; $itemField = ''; $noteField = null;
                if (preg_match('/^\s*([^\s,()]+)\s*(\p{L}+)?\s*(.*)$/u', $seg, $m)) {
                    $qtyParsed = sitc_parse_qty_or_range_parser($m[1]);
                    if (is_array($qtyParsed) || is_float($qtyParsed)) { $qtyField = $qtyParsed; }
                    $u = trim((string)($m[2] ?? ''));
                    $rest = trim((string)($m[3] ?? ''));
                    if ($u !== '') {
                        $uLower = mb_strtolower($u, 'UTF-8');
                        $uCanon = sitc_unit_alias_canonical($u);
                        if ($uCanon) {
                            $unitField = $uCanon;
                        } else {
                            // Extra: explicit StÃ¼ck umlaut handling
                            if ($uLower === 'stÃ¼ck') { $unitField = 'piece'; }
                            else {
                                $rest = trim($u . ' ' . $rest);
                                if ($uLower === 'tk') { $noteField = trim(($noteField ? $noteField.'; ' : '') . 'TK'); }
                            }
                        }
                    }
                    if (preg_match('/^TK\b/u', $rest)) { $noteField = 'TK'; $rest = trim(preg_replace('/^TK\b\s*/u','',$rest)); }
                    $tn = sitc_extract_trailing_notes($rest);
                    $itemField = $tn['clean'];
                    if (!empty($tn['note'])) { $noteField = trim(($noteField ? $noteField.', ' : '') . $tn['note']); }

                    if ($unitField===null && preg_match('/^knoblauchzehen?\b/iu', $itemField)) { $unitField='clove'; $itemField='Knoblauch'; }
                    if ($unitField===null && preg_match('/^(zehe|zehen)\b/iu', $itemField)) { $unitField='clove'; $itemField=preg_replace('/^(zehe|zehen)\b\s*/iu','',$itemField); }

                    if ($unitField !== null) {
                        $hn = sitc_hyphen_note_split($itemField, $unitField);
                        if (!empty($hn['note'])) {
                            $itemField = $hn['item'];
                            $noteField = trim(($noteField ? $noteField.', ' : '') . $hn['note']);
                        }
                    }
                } else {
                    $itemField = $seg;
                }
                $itemField = trim($itemField);
                if ($itemField === '' && $unitField === null) continue;
                if ($qtyField !== null && $itemField === '' && $unitField === null) continue;
                if (sitc_is_bare_number($itemField)) continue;
                $out[] = [
                    'raw'  => $seg,
                    'qty'  => $qtyField,
                    'unit' => $unitField,
                    'item' => $itemField,
                    'note' => $noteField !== null ? preg_replace('/\s*,\s*/', ', ', trim($noteField)) : null,
                ];
            }
        }
        return $out;
    }
}

// Decimal-comma normalize helper
if (!function_exists('sitc_num_norm_dec_comma')) {
    function sitc_num_norm_dec_comma(string $num): string {
        return str_replace(',', '.', $num);
    }
}

// Fixed Unicode fraction map (Â¼ Â½ Â¾ â…“ â…” â…›)
if (!function_exists('sitc_unicode_fraction_map_fixed')) {
    function sitc_unicode_fraction_map_fixed(): array {
        return [
            'Â½' => 0.5,
            'Â¼' => 0.25,
            'Â¾' => 0.75,
            'â…“' => 0.3333,
            'â…”' => 0.6667,
            'â…›' => 0.125,
        ];
    }
}

if (!function_exists('sitc_unicode_fractions_to_decimal_fixed')) {
    function sitc_unicode_fractions_to_decimal_fixed(string $s): string {
        $map = sitc_unicode_fraction_map_fixed();
        $class = preg_quote(implode('', array_keys($map)), '/');
        $s = preg_replace_callback('/(\d)\s*(['.$class.'])/u', function($m) use($map){
            return (string)((float)$m[1] + (float)$map[$m[2]]);
        }, $s);
        $s = preg_replace_callback('/(['.$class.'])/u', function($m) use($map){ return (string)$map[$m[1]]; }, $s);
        return $s;
    }
}

// E1: stopwords around quantity tokens (e.g., ca., circa) removal
if (!function_exists('sitc_strip_stopwords_qty_context')) {
    function sitc_strip_stopwords_qty_context(string $s): string {
        $t = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $s);
        if ($t === null) $t = $s;
        $t = preg_replace('/^\s*(?:ca\.?|circa|etwa|ungef(?:\.|aehr|Ã¤hr)?|about|approx(?:\.|imately)?|\(ca\.?\))\s+/iu', ' ', $t);
        if ($t === null) $t = $s;
        $t = preg_replace('/(?<=\d|\p{L})\s*\((?:ca\.?|circa|about|approx\.?)\)\s*/iu', ' ', $t);
        if ($t === null) $t = $s;
        return trim(preg_replace('/\s{2,}/u', ' ', $t));
    }
}

// E1: decimalize commas inside numeric tokens only
if (!function_exists('sitc_decimalize_commas_in_numbers')) {
    function sitc_decimalize_commas_in_numbers(string $s): string {
        return preg_replace('/(\d),(?=\d)/u', '.', $s);
    }
}

// E1: convert Unicode fractions and mixed numbers to decimal strings
if (!function_exists('sitc_unicode_fractions_to_decimal')) {
    function sitc_unicode_fractions_to_decimal(string $s): string {
        $map = [ 'Â½'=>0.5, 'Â¼'=>0.25, 'Â¾'=>0.75, 'â…“'=>0.3333, 'â…”'=>0.6667, 'â…›'=>0.125 ];
        $class = preg_quote(implode('', array_keys($map)), '/');
        $s = preg_replace_callback('/(\d)\s*(['.$class.'])/u', function($m) use($map){
            return (string)((float)$m[1] + (float)$map[$m[2]]);
        }, $s);
        $s = preg_replace_callback('/(['.$class.'])/u', function($m) use($map){ return (string)$map[$m[1]]; }, $s);
        return $s;
    }
}

// Map of common Unicode fractions to decimal strings
if (!function_exists('sitc_unicode_fraction_map')) {
    function sitc_unicode_fraction_map(): array {
        return [
            'Â½' => 0.5,
            'Â¼' => 0.25,
            'Â¾' => 0.75,
            'â…“' => 0.3333,
            'â…”' => 0.6667,
            'â…›' => 0.125,
        ];
    }
}

// Quantity token to float, handling:
// - Unicode fractions (Â½ Â¼ Â¾ â…“ â…” â…›)
// - Mixed numbers (1Â½, 1 1/2)
// - Fractions a/b
// - Decimal comma
if (!function_exists('sitc_qty_from_token')) {
    function sitc_qty_from_token(string $t): ?float {
        $s = trim($t);
        if ($s === '') return null;
        $s = sitc_num_norm_dec_comma($s);
        $s = preg_replace('/\s*\/\s*/u', '/', $s);
        if ($s === null) $s = trim($t);

        $map = sitc_unicode_fraction_map_fixed();

        // 1Â½ style
        if (preg_match('/^([0-9]+)\s*([' . preg_quote(implode('', array_keys($map)), '/') . '])$/u', $s, $m)) {
            $base = (float)$m[1];
            $frac = (float)($map[$m[2]] ?? 0.0);
            return $base + $frac;
        }
        // 1 1/2 style
        if (preg_match('/^([0-9]+)\s+([0-9]+)\/([0-9]+)$/u', $s, $m)) {
            $a = (float)$m[1];
            $b = (float)$m[2];
            $c = max(1.0, (float)$m[3]);
            return $a + ($b / $c);
        }
        // Pure unicode fraction
        if (preg_match('/^([' . preg_quote(implode('', array_keys($map)), '/') . '])$/u', $s, $m)) {
            return (float)($map[$m[1]] ?? 0.0);
        }
        // Fraction a/b
        if (preg_match('/^([0-9]+)\/([0-9]+)$/u', $s, $m)) {
            return (float)$m[1] / max(1.0, (float)$m[2]);
        }
        // Decimal
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/u', $s)) {
            return (float)$s;
        }
        return null;
    }
}

// Pre-normalize text around quantities (spaces, dashes, stop-words)
if (!function_exists('sitc_qty_pre_normalize_parser')) {
    function sitc_qty_pre_normalize_parser(string $s): string {
        $t = trim($s);
        if ($t === '') return '';
        // NBSP / NNBSP to space
        $t = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $t);
        // drop leading stopwords and (ca.) suffix
        $t = preg_replace('/^(ca\.?|circa|etwa|ungef\.?|ungef(?:ae|Ã¤)hr\.?|about|approx\.?|approximately)\s+/iu', '', $t);
        $t = preg_replace('/\((?:ca\.?|circa|about|approx\.?)\)\s*$/iu', '', $t);
        // decimal comma in numbers and leading dot â†’ 0.x
        $t = sitc_decimalize_commas_in_numbers($t);
        $t = preg_replace('/(^|[^0-9])\.([0-9]+)/u', '$10.$2', $t);
        // decimal comma in numbers and leading dot â†’ 0.x
        $t = sitc_decimalize_commas_in_numbers($t);
        $t = preg_replace('/(^|[^0-9])\.([0-9]+)/u', '$10.$2', $t);
        // unify range separators to hyphen
        $t = preg_replace('/[\x{2012}-\x{2015}]/u', '-', $t);
        // tighten slash
        $t = preg_replace('/\s*\/\s*/u', '/', $t);
        // Mixed unicode fractions to decimal within text âžœ keep comma inside text neutralization for human strings
        $map = sitc_unicode_fraction_map_fixed();
        $class = preg_quote(implode('', array_keys($map)), '/');
        // mixed number: 1Â½ -> 1 + 0.5 (but keep as decimal string; the float parse happens later)
        $t = preg_replace_callback('/(\d)\s*([' . $class . '])/u', function($m) use ($map){
            $sum = (float)$m[1] + (float)($map[$m[2]] ?? 0.0);
            return (string)$sum;
        }, $t);
        // standalone unicode fraction -> decimal string
        $t = preg_replace_callback('/([' . $class . '])/u', function($m) use ($map){
            return (string)($map[$m[1]] ?? $m[1]);
        }, $t);
        // D-FIX: ASCII mixed numbers and fractions to decimal strings
        $t = preg_replace_callback('/(\d+)\s+(\d+)\/(\d+)/u', function($m){
            $a=(float)$m[1]; $b=(float)$m[2]; $c=max(1.0,(float)$m[3]);
            return (string)($a + ($b/$c));
        }, $t);
        $t = preg_replace_callback('/\b(\d+)\/(\d+)\b/u', function($m){
            $b=(float)$m[1]; $c=max(1.0,(float)$m[2]); return (string)($b/$c);
        }, $t);
        return $t;
    }
}

// Parse quantity or range (a-b)
if (!function_exists('sitc_parse_qty_or_range_parser')) {
    function sitc_parse_qty_or_range_parser(string $s) {
        $t = sitc_normalize_qty_tokens($s);
        // single value first
        $v = sitc_parse_mixed_or_fraction($t);
        if ($v !== null) return (float)$v;
        // range with hyphen / normalized "bis"
        $r = sitc_extract_range($t);
        if ($r !== null) return $r;
        return null;
}
}

// Legacy renderer-facing wrappers to avoid duplicate implementations
if (!function_exists('sitc_qty_pre_normalize')) {
    function sitc_qty_pre_normalize(string $s): string { return sitc_qty_pre_normalize_parser($s); }
}

if (!function_exists('sitc_parse_qty_or_range')) {
    function sitc_parse_qty_or_range($qty): array {
        $res = sitc_parse_qty_or_range_parser((string)$qty);
        if (is_array($res) && isset($res['low'],$res['high'])) return ['isRange'=>true,'low'=>(float)$res['low'],'high'=>(float)$res['high']];
        if (is_float($res)) return ['isRange'=>false,'low'=>(float)$res,'high'=>null];
        return ['isRange'=>false,'low'=>null,'high'=>null];
    }
}

if (!function_exists('sitc_coerce_qty_float')) {
    function sitc_coerce_qty_float($qty) {
        $s = trim((string)$qty);
        if ($s === '') return null;
        // Prefer low bound if range
        $parsed = sitc_parse_qty_or_range_parser($s);
        if (is_array($parsed) && isset($parsed['low'])) return (float)$parsed['low'];
        if (is_float($parsed)) return (float)$parsed;
        return null;
    }
}

// Canonical unit aliases (German -> short EN) â€“ parser-internal only
// E1 helpers: trailing notes, hyphen-notes, bare-number detector
if (!function_exists('sitc_extract_trailing_notes')) {
    function sitc_extract_trailing_notes(string $s): array {
        $item = trim($s);
        $notes = [];
        if (preg_match('/^(.*)\(([^\)]*)\)\s*$/u', $item, $m)) {
            $item = trim($m[1]);
            $n = trim($m[2]);
            if ($n !== '') $notes[] = $n;
        }
        if (strpos($item, ',') !== false) {
            $parts = array_map('trim', explode(',', $item, 2));
            if (count($parts) === 2) {
                $item = $parts[0];
                if ($parts[1] !== '') $notes[] = $parts[1];
            }
        }
        $notes = array_values(array_filter(array_unique(array_map(function($n){
            return preg_replace('/\s{2,}/u',' ', trim($n));
        }, $notes)), 'strlen'));
        return ['clean'=>$item, 'note'=>$notes ? implode(', ', $notes) : null];
    }
}
if (!function_exists('sitc_hyphen_note_split')) {
    function sitc_hyphen_note_split(string $item, ?string $contextUnit): array {
        $i = trim($item);
        if ($i === '') return ['item'=>$i, 'note'=>null];
        $weightUnits = ['g','kg'];
        if ($contextUnit && in_array($contextUnit, $weightUnits, true)) {
            $suffixMap = [
                'stÃ¼ck'=>'StÃ¼ck','stk'=>'StÃ¼ck','stueck'=>'StÃ¼ck','stÇ¬ck'=>'StÃ¼ck',
                'scheibe'=>'Scheibe','scheiben'=>'Scheiben',
                'wÃ¼rfel'=>'WÃ¼rfel','wuerfel'=>'WÃ¼rfel',
                'streifen'=>'Streifen',
            ];
            if (preg_match('/^([\p{L}][\p{L}\-]*)\s*-\s*([\p{L}]+)$/u', $i, $m)) {
                $right = mb_strtolower($m[2], 'UTF-8');
                if (isset($suffixMap[$right])) {
                    return ['item'=>trim($m[1]), 'note'=>$suffixMap[$right]];
                }
            }
        }
        return ['item'=>$i, 'note'=>null];
    }
}
if (!function_exists('sitc_is_bare_number')) {
    function sitc_is_bare_number(string $s): bool {
        $t = trim($s);
        if ($t === '') return false;
        return (bool)preg_match('/^\d+(?:[\.,]\d+)?(?:\s*\/\s*\d+)?$/u', $t);
    }
}
if (!function_exists('sitc_unit_alias_canonical')) {
    function sitc_unit_alias_canonical(string $u): ?string {
        $m = [
            'g'=>'g','gram'=>'g','grams'=>'g','gramm'=>'g','gr'=>'g',
            'kg'=>'kg',
            'ml'=>'ml','milliliter'=>'ml','millilitre'=>'ml',
            'l'=>'l','liter'=>'l','litre'=>'l',
            'tl'=>'tsp','teeloeffel'=>'tsp','teelÃ¶ffel'=>'tsp','tsp'=>'tsp','teaspoon'=>'tsp','teaspoons'=>'tsp',
            'el'=>'tbsp','essloeffel'=>'tbsp','esslÃ¶ffel'=>'tbsp','tbsp'=>'tbsp','tablespoon'=>'tbsp','tablespoons'=>'tbsp',
            'tasse'=>'cup','tassen'=>'cup','cup'=>'cup','cups'=>'cup',
            'prise'=>'pinch','prisen'=>'pinch','pinch'=>'pinch',
            'stk'=>'piece','stÃ¼ck'=>'piece','stueck'=>'piece','piece'=>'piece','pieces'=>'piece',
            'dose'=>'can','dosen'=>'can','can'=>'can',
            'bund'=>'bunch','bÃ¼ndel'=>'bunch','bunch'=>'bunch',
            'zehe'=>'clove','zehen'=>'clove','clove'=>'clove','cloves'=>'clove',
        ];
        $k = mb_strtolower(trim($u), 'UTF-8');
        return $m[$k] ?? null;
    }
}

// Structured ingredient splitter from a single line â†’ one or more entries
// Returns list of: { raw, qty:?float|{low,high}, unit:?string, item:string, note:?string }
if (!function_exists('sitc_struct_from_line')) {
    function sitc_struct_from_line(string $rawLine): array {
        $out = [];
        $pn = sitc_qty_pre_normalize_parser($rawLine);

        // Split by common delimiters: bullets, middot, semicolon, or 2+ horizontal spaces
        $parts = sitc_pcre2_split('/(?:\s*[;â€¢Â·]\s+|\h{2,})/u', $pn);
        $parts = array_values(array_filter(array_map('trim', $parts), fn($s)=>$s!==''));
        if (!$parts) $parts = [$pn];

        foreach ($parts as $part) {
            // If a part contains multiple qty tokens, split conservatively at subsequent tokens
            if (preg_match_all('/(?:(?:\d+(?:[\.,]\d+)?)|\d+\/\d+)/u', $part, $mm, PREG_OFFSET_CAPTURE) && count($mm[0])>1) {
                $segments = [];
                $last = 0;
                foreach ($mm[0] as $i=>$m) {
                    if ($i===0) continue;
                    $off = $m[1];
                    $segments[] = trim(substr($part, $last, $off-$last));
                    $last = $off;
                }
                $segments[] = trim(substr($part, $last));
            } else {
                $segments = [$part];
            }

            foreach ($segments as $seg) {
                if ($seg==='') continue;
                $qtyField = null; $unitField = null; $itemField = ''; $noteField = null;
                // qty + optional unit at start
                if (preg_match('/^\s*([^\s,()]+)\s*(\p{L}+)?\s*(.*)$/u', $seg, $m)) {
                    $qtyParsed = sitc_parse_qty_or_range_parser($m[1]);
                    if (is_array($qtyParsed) || is_float($qtyParsed)) { $qtyField = $qtyParsed; }
                    $u = trim((string)($m[2] ?? ''));
                    $rest = trim((string)($m[3] ?? ''));
                    if ($u !== '') {
                        $uLower = mb_strtolower($u, 'UTF-8');
                        $uCanon = sitc_unit_alias_canonical($u);
                        if ($uCanon) {
                            $unitField = $uCanon;
                        } else {
                            // Unknown letter token following qty: treat as part of item (e.g., "Nelken")
                            $rest = trim($u . ' ' . $rest);
                            // Special-case: TK as note
                            if ($uLower === 'tk') {
                                $noteField = trim(($noteField ? $noteField.'; ' : '') . 'TK');
                            }
                        }
                    }
                    // Leading TK flag as note
                    if (preg_match('/^TK\b/u', $rest)) { $noteField = 'TK'; $rest = trim(preg_replace('/^TK\b\s*/u','',$rest)); }
                    // Parentheses note
                    if (preg_match('/^(.*)\(([^\)]+)\)\s*$/u', $rest, $n)) { $itemField = trim($n[1]); $noteField = trim(($noteField? $noteField.'; ':'').$n[2]); }
                    else $itemField = $rest;
                    // Trailing comma note
                    if (strpos($itemField, ',') !== false) {
                        $parts2 = array_map('trim', explode(',', $itemField, 2));
                        if (count($parts2)===2) { $itemField = $parts2[0]; $noteField = trim(($noteField? $noteField.'; ':'').$parts2[1]); }
                    }
                    // Heuristics: Knoblauchzehen â†’ unit clove + item Knoblauch
                    if ($unitField===null && preg_match('/^knoblauchzehen?\b/iu', $itemField)) { $unitField='clove'; $itemField='Knoblauch'; }
                    if ($unitField===null && preg_match('/^(zehe|zehen)\b/iu', $itemField)) { $unitField='clove'; $itemField=preg_replace('/^(zehe|zehen)\b\s*/iu','',$itemField); }
                } else {
                    $itemField = $seg;
                }
                $out[] = [
                    'raw'  => $seg,
                    'qty'  => $qtyField,
                    'unit' => $unitField,
                    'item' => trim($itemField),
                    'note' => $noteField !== null ? $noteField : null,
                ];
            }
        }
        return $out;
    }
}
