<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;
function sitc_normalize_array_meta($value) {
    if (is_array($value)) return $value;
    if (is_string($value) && $value !== '') {
        $json = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) return $json;
        $maybe = maybe_unserialize($value);
        if (is_array($maybe)) return $maybe;
    }
    return [];
}

function sitc_normalize_scalar_number($value, $default = 2) {
    if ($value === '' || $value === null) return $default;
    $v = is_numeric($value) ? (float)$value : (float)str_replace(',', '.', (string)$value);
    return $v > 0 ? $v : $default;
}

function sitc_unit_to_de($unit) {
    $u = trim(mb_strtolower((string)$unit, 'UTF-8'));
    if ($u === '') return '';
    $map = [
        'tsp' => 'TL', 'teaspoon' => 'TL', 'teaspoons' => 'TL', 'tl' => 'TL',
        'tbsp' => 'EL', 'tablespoon' => 'EL', 'tablespoons' => 'EL', 'el' => 'EL',
        'cup' => 'Tasse', 'cups' => 'Tasse', 'tasse' => 'Tasse',
        'piece' => 'Stück', 'pieces' => 'Stück', 'stueck' => 'Stück', 'stück' => 'Stück',
        'pinch' => 'Prise', 'prisen' => 'Prise', 'prise' => 'Prise',
        'can' => 'Dose', 'dose' => 'Dose',
        'bunch' => 'Bund', 'bund' => 'Bund',
        'g' => 'g', 'gram' => 'g', 'grams' => 'g', 'gramm' => 'g',
        'kg' => 'kg',
        'ml' => 'ml', 'milliliter' => 'ml', 'millilitre' => 'ml',
        'l' => 'l', 'liter' => 'l', 'litre' => 'l',
        'oz' => 'oz', 'ounce' => 'oz', 'ounces' => 'oz',
        'lb' => 'lb', 'pound' => 'lb', 'pounds' => 'lb',
    ];
    $res = $map[$u] ?? $unit;
    return $res;
}

function sitc_ucfirst_de(string $s): string {
    if ($s === '') return $s;
    $first = mb_substr($s, 0, 1, 'UTF-8');
    $rest  = mb_substr($s, 1, null, 'UTF-8');
    if ($first === mb_strtolower($first, 'UTF-8') && $first !== mb_strtoupper($first, 'UTF-8')) {
        return mb_strtoupper($first, 'UTF-8') . $rest;
    }
    return $s;
}

function sitc_cased_de_ingredient(string $s): string {
    $t = trim($s);
    if ($t === '') return $t;
    $letters = preg_replace('/[^\p{L}\s]/u', '', $t);
    if ($letters !== '' && $letters === mb_strtolower($letters, 'UTF-8')) {
        return sitc_ucfirst_de($t);
    }
    return $t;
}

function sitc_qty_pre_normalize(string $s): string {
    $t = trim($s);
    if ($t === '') return '';
    $t = preg_replace('/^(ca\.|circa|etwa|ungef\.?|about|approx\.?|approximately)\s+/iu', '', $t);
    $t = preg_replace('/\s*,\s*/u', ',', $t);
    $t = preg_replace('/\s*\.\s*/u', '.', $t);
    return sitc_replace_unicode_fractions($t);
}

function sitc_replace_unicode_fractions(string $s): string {
    $map = [
        '½' => '1/2', '¼' => '1/4', '¾' => '3/4',
        '⅓' => '1/3', '⅔' => '2/3', '⅛' => '1/8', '⅜' => '3/8', '⅝' => '5/8', '⅞' => '7/8',
    ];
    return strtr($s, $map);
}

function sitc_parse_qty_or_range($qty): array {
    $s = trim((string)$qty);
    if ($s === '') return ['isRange'=>false,'low'=>null,'high'=>null];
    $s = sitc_qty_pre_normalize($s);
    if (preg_match('/^(\d+(?:[\.,]\d+)?)\s*[–-]\s*(\d+(?:[\.,]\d+)?)$/u', $s, $m)) {
        return [
            'isRange' => true,
            'low'  => (float)str_replace(',', '.', $m[1]),
            'high' => (float)str_replace(',', '.', $m[2]),
        ];
    }
    if (preg_match('/^(\d+(?:[\.,]\d+)?)$/u', $s, $m)) {
        return ['isRange'=>false,'low'=>(float)str_replace(',', '.', $m[1]),'high'=>null];
    }
    return ['isRange'=>false,'low'=>null,'high'=>null];
}

function sitc_format_qty_display($val): string {
    if ($val === null) return '';
    $v = (float)$val;
    if (abs($v - round($v)) < 0.01) $v = round($v);
    $s = number_format($v, 2, ',', '');
    return rtrim(rtrim($s, '0'), ',');
}

function sitc_coerce_qty_float($qty) {
    $s = trim((string)$qty);
    if ($s === '') return null;
    $s = sitc_replace_unicode_fractions($s);
    if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $s, $m)) { return (float)$m[1] + ((float)$m[2]/max(1,(float)$m[3])); }
    if (preg_match('/^(\d+)\/(\d+)$/', $s, $m)) { return ((float)$m[1])/max(1,(float)$m[2]); }
    if (preg_match('/^(\d+(?:[\.,]\d+)?)\s*[–-]\s*\d+(?:[\.,]\d+)?$/u', $s, $m)) { return (float)str_replace(',', '.', $m[1]); }
    if (preg_match('/^\d+(?:[\.,]\d+)?$/', $s)) { return (float)str_replace(',', '.', $s); }
    return null;
}

function sitc_format_qty_de($val) {
    if ($val === null) return '';
    $s = number_format((float)$val, 2, ',', '');
    return rtrim(rtrim($s, '0'), ',');
}