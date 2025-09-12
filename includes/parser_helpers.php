<?php
if (!defined('ABSPATH')) exit;

// PCRE2-safe split helper: never returns false; returns [$s] on regex failure
if (!function_exists('sitc_pcre2_split')) {
    function sitc_pcre2_split(string $pattern, string $s): array {
        $out = @preg_split($pattern, $s, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($out) ? $out : [$s];
    }
}

// Decimal-comma normalize helper
if (!function_exists('sitc_num_norm_dec_comma')) {
    function sitc_num_norm_dec_comma(string $num): string {
        return str_replace(',', '.', $num);
    }
}

// Map of common Unicode fractions to decimal strings
if (!function_exists('sitc_unicode_fraction_map')) {
    function sitc_unicode_fraction_map(): array {
        return [
            '½' => 0.5,
            '¼' => 0.25,
            '¾' => 0.75,
            '⅓' => 0.3333,
            '⅔' => 0.6667,
            '⅛' => 0.125,
        ];
    }
}

// Quantity token to float, handling:
// - Unicode fractions (½ ¼ ¾ ⅓ ⅔ ⅛)
// - Mixed numbers (1½, 1 1/2)
// - Fractions a/b
// - Decimal comma
if (!function_exists('sitc_qty_from_token')) {
    function sitc_qty_from_token(string $t): ?float {
        $s = trim($t);
        if ($s === '') return null;
        $s = sitc_num_norm_dec_comma($s);
        $s = preg_replace('/\s*\/\s*/u', '/', $s);
        if ($s === null) $s = trim($t);

        $map = sitc_unicode_fraction_map();

        // 1½ style
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
        $t = preg_replace('/^(ca\.?|circa|etwa|ungef\.?|ungef(?:ae|ä)hr\.?|about|approx\.?|approximately)\s+/iu', '', $t);
        $t = preg_replace('/\((?:ca\.?|circa|about|approx\.?)\)\s*$/iu', '', $t);
        // unify range separators to hyphen
        $t = preg_replace('/[\x{2012}-\x{2015}]/u', '-', $t);
        // tighten slash
        $t = preg_replace('/\s*\/\s*/u', '/', $t);
        // Mixed unicode fractions to decimal within text ➜ keep comma inside text neutralization for human strings
        $map = sitc_unicode_fraction_map();
        $class = preg_quote(implode('', array_keys($map)), '/');
        // mixed number: 1½ -> 1 + 0.5 (but keep as decimal string; the float parse happens later)
        $t = preg_replace_callback('/(\d)\s*([' . $class . '])/u', function($m) use ($map){
            $sum = (float)$m[1] + (float)($map[$m[2]] ?? 0.0);
            return (string)$sum;
        }, $t);
        // standalone unicode fraction -> decimal string
        $t = preg_replace_callback('/([' . $class . '])/u', function($m) use ($map){
            return (string)($map[$m[1]] ?? $m[1]);
        }, $t);
        return $t;
    }
}

// Parse quantity or range (a-b)
if (!function_exists('sitc_parse_qty_or_range_parser')) {
    function sitc_parse_qty_or_range_parser(string $s) {
        $t = sitc_qty_pre_normalize_parser($s);
        // a–b: allow hyphen and unicode dashes
        if (preg_match('/^(\S+)\h*[\-\x{2012}-\x{2015}]\h*(\S+)$/u', $t, $m)) {
            $a = sitc_qty_from_token($m[1]);
            $b = sitc_qty_from_token($m[2]);
            if ($a !== null && $b !== null) return ['low' => (float)$a, 'high' => (float)$b];
        }
        $v = sitc_qty_from_token($t);
        if ($v !== null) return (float)$v;
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

// Canonical unit aliases (German -> short EN) – parser-internal only
if (!function_exists('sitc_unit_alias_canonical')) {
    function sitc_unit_alias_canonical(string $u): ?string {
        $m = [
            'g'=>'g','gram'=>'g','grams'=>'g','gramm'=>'g','gr'=>'g',
            'kg'=>'kg',
            'ml'=>'ml','milliliter'=>'ml','millilitre'=>'ml',
            'l'=>'l','liter'=>'l','litre'=>'l',
            'tl'=>'tsp','teeloeffel'=>'tsp','teelöffel'=>'tsp','tsp'=>'tsp','teaspoon'=>'tsp','teaspoons'=>'tsp',
            'el'=>'tbsp','essloeffel'=>'tbsp','esslöffel'=>'tbsp','tbsp'=>'tbsp','tablespoon'=>'tbsp','tablespoons'=>'tbsp',
            'tasse'=>'cup','tassen'=>'cup','cup'=>'cup','cups'=>'cup',
            'prise'=>'pinch','prisen'=>'pinch','pinch'=>'pinch',
            'stk'=>'piece','stück'=>'piece','stueck'=>'piece','piece'=>'piece','pieces'=>'piece',
            'dose'=>'can','dosen'=>'can','can'=>'can',
            'bund'=>'bunch','bündel'=>'bunch','bunch'=>'bunch',
            'zehe'=>'clove','zehen'=>'clove','clove'=>'clove','cloves'=>'clove',
        ];
        $k = mb_strtolower(trim($u), 'UTF-8');
        return $m[$k] ?? null;
    }
}

// Structured ingredient splitter from a single line → one or more entries
// Returns list of: { raw, qty:?float|{low,high}, unit:?string, item:string, note:?string }
if (!function_exists('sitc_struct_from_line')) {
    function sitc_struct_from_line(string $rawLine): array {
        $out = [];
        $pn = sitc_qty_pre_normalize_parser($rawLine);

        // Split by common delimiters: bullets, middot, semicolon, or 2+ horizontal spaces
        $parts = sitc_pcre2_split('/(?:\s*[;•·]\s+|\h{2,})/u', $pn);
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
                    // Heuristics: Knoblauchzehen → unit clove + item Knoblauch
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
