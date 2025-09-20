<?php

if (!defined('ABSPATH')) exit;

// Extra helper set to robustly handle Unicode fractions, mixed numbers, and stopwords like "ca.".

if (!function_exists('sitc_unicode_fraction_map2')) {
function sitc_unicode_fraction_map2(): array {
    return [
        'Ã‚Â¼' => '1/4', 'Ã‚Â½' => '1/2', 'Ã‚Â¾' => '3/4',
        'Ã¢â€¦â€œ' => '1/3', 'Ã¢â€¦â€' => '2/3', 'Ã¢â€¦â€º' => '1/8',
        'Ã¢â€¦â€¢' => '1/5', 'Ã¢â€¦â€“' => '2/5', 'Ã¢â€¦â€”' => '3/5', 'Ã¢â€¦Ëœ' => '4/5',
        'Ã¢â€¦â„¢' => '1/6', 'Ã¢â€¦Å¡' => '5/6',
        'Ã¢â€¦Å“' => '3/8', 'Ã¢â€¦Â' => '5/8', 'Ã¢â€¦Å¾' => '7/8',
    ];
}}

if (!function_exists('sitc_decode_u00_sequences')) {
function sitc_decode_u00_sequences(string $s): string {
    return preg_replace_callback('/u00([0-9a-fA-F]{2})/', function($m) {
        $hex = strtolower($m[1]);
        $u = '\\u00' . $hex;
        $decoded = json_decode('"' . $u . '"');
        return is_string($decoded) ? $decoded : $m[0];
    }, $s);
}}

if (!function_exists('sitc_qty_pre_normalize_v2')) {
function sitc_qty_pre_normalize_v2(string $s): string {
    $t = trim($s);
    if ($t === '') return '';
    $t = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $t); // NBSP/NNBSP -> space
    $t = sitc_decode_u00_sequences($t);
    // Remove leading stopwords + optional trailing parens e.g. (ca.)
    $t = preg_replace('/^(ca\.?|circa|etwa|ungef(?:ae|ÃƒÂ¤)hr\.?|about|approx\.?|approximately)[\s\(\)]*/iu', '', $t);
    $t = preg_replace('/\((?:ca\.?|circa|about|approx\.?)\)/iu', '', $t);
    // Tighten separators
    $t = preg_replace('/\s*,\s*/u', ',', $t);
    $t = preg_replace('/\s*\.\s*/u', '.', $t);
    $t = preg_replace('/\s*\/\s*/u', '/', $t);
    // Unicode fractions
    $map = sitc_unicode_fraction_map2();
    $chars = preg_quote(implode('', array_keys($map)), '/');
    $t = preg_replace_callback('/(\d)\s*(['.$chars.'])/u', function($m) use ($map) {
        $frac = $map[$m[2]] ?? $m[2];
        return $m[1].' '.$frac;
    }, $t);
    $t = strtr($t, $map);
    return $t;
}}

if (!function_exists('sitc_extract_leading_qty_token_v2')) {
function sitc_extract_leading_qty_token_v2(string $line): string {
    $t = sitc_qty_pre_normalize_v2($line);
    // capture token(s) like: 1, 1/2, 1 1/2, 1-2, 1,5, Ã‚Â½, 1Ã‚Â½, etc. at the start
    if (preg_match('/^\s*((?:\d+(?:[\.,]\d+)?)(?:\s+\d+\/\d+)?)\b/u', $t, $m)) {
        return trim($m[1]);
    }
    // fallback: standalone fraction at start
    if (preg_match('/^\s*(\d+\/\d+)\b/u', $t, $m)) return trim($m[1]);
    return '';
}}

if (!function_exists('sitc_coerce_qty_float_v2')) {
function sitc_coerce_qty_float_v2($qty) {
    $s = trim((string)$qty);
    if ($s === '') return null;
    $s = sitc_qty_pre_normalize_v2($s);
    if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $s, $m)) return (float)$m[1] + ((float)$m[2]/max(1,(float)$m[3]));
    if (preg_match('/^(\d+)\/(\d+)$/', $s, $m)) return ((float)$m[1])/max(1,(float)$m[2]);
    if (preg_match('/^\d+(?:[\.,]\d+)?$/', $s)) return (float)str_replace(',', '.', $s);
    // If range like a-b or aÃ¢â‚¬â€œb, use low bound
    if (preg_match('/^(\S+)\s*[\-\x{2012}-\x{2015}]\s*\S+$/u', $s, $m)) {
        $l = sitc_coerce_qty_float_v2($m[1]);
        if ($l !== null) return $l;
    }
    return null;
}}

if (!function_exists('sitc_parse_qty_or_range_v2')) {
function sitc_parse_qty_or_range_v2($qty): array {
    $s = trim((string)$qty);
    if ($s === '') return ['isRange'=>false,'low'=>null,'high'=>null];
    $s = sitc_qty_pre_normalize_v2($s);
    if (preg_match('/^(\S+)\s*[\-\x{2012}-\x{2015}]\s*(\S+)$/u', $s, $m)) {
        $a = sitc_coerce_qty_float_v2($m[1]);
        $b = sitc_coerce_qty_float_v2($m[2]);
        if ($a !== null && $b !== null) return ['isRange'=>true,'low'=>(float)$a,'high'=>(float)$b];
    }
    $v = sitc_coerce_qty_float_v2($s);
    if ($v !== null) return ['isRange'=>false,'low'=>(float)$v,'high'=>null];
    return ['isRange'=>false,'low'=>null,'high'=>null];
}}

if (!function_exists('sitc_merge_ingredients_for_display')) {
function sitc_merge_ingredients_for_display(array $items): array {
    $seen = [];
    $out = [];
    foreach ($items as $ing) {
        $qty  = isset($ing['qty']) ? (string)$ing['qty'] : '';
        $unit = isset($ing['unit']) ? (string)$ing['unit'] : '';
        $name = isset($ing['name']) ? (string)$ing['name'] : '';

        $v = sitc_coerce_qty_float_v2($qty);
        $qtyKey = ($v === null) ? '' : sprintf('%.3f', (float)$v);
        $unitKey = mb_strtolower(trim($unit), 'UTF-8');
        $nameKey = $name;
        // Canonicalize name: lower-case, remove diacritics, collapse spaces
        $nameKey = html_entity_decode(strip_tags($nameKey), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $nameKey = preg_replace('/\s+/u', ' ', $nameKey);
        $nameKey = trim($nameKey);
        $nameKey = mb_strtolower($nameKey, 'UTF-8');
        if (function_exists('normalizer_normalize')) {
            $nfd = normalizer_normalize($nameKey, Normalizer::FORM_D);
            if ($nfd !== false) $nameKey = preg_replace('/\p{Mn}+/u', '', $nfd);
        }

        $key = $qtyKey.'|'.$unitKey.'|'.$nameKey;
        if (!isset($seen[$key])) { $seen[$key] = true; $out[] = $ing; }
    }
    return $out;
}}

