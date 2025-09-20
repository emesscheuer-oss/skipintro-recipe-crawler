<?php

if (!defined('ABSPATH')) exit;

// Single Source of Truth for parsing/tokenizing helpers used by renderer
require_once __DIR__ . '/../parser_helpers.php';

if (!function_exists('sitc_normalize_array_meta')) {
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
}

if (!function_exists('sitc_normalize_scalar_number')) {
function sitc_normalize_scalar_number($value, $default = 2) {
    if ($value === '' || $value === null) return $default;
    $v = is_numeric($value) ? (float)$value : (float)str_replace(',', '.', (string)$value);
    return $v > 0 ? $v : $default;
}
}

if (!function_exists('sitc_unit_to_de')) {
function sitc_unit_to_de($unit) {
    $u = trim(mb_strtolower((string)$unit, 'UTF-8'));
    if ($u === '') return '';
    $map = [
        'tsp' => 'TL', 'teaspoon' => 'TL', 'teaspoons' => 'TL', 'tl' => 'TL',
        'tbsp' => 'EL', 'tablespoon' => 'EL', 'tablespoons' => 'EL', 'el' => 'EL',
        'cup' => 'Tasse', 'cups' => 'Tasse', 'tasse' => 'Tasse',
        'piece' => 'StÃƒÂ¼ck', 'pieces' => 'StÃƒÂ¼ck', 'stueck' => 'StÃƒÂ¼ck', 'stÃƒÂ¼ck' => 'StÃƒÂ¼ck',
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
}

if (!function_exists('sitc_ucfirst_de')) {
function sitc_ucfirst_de(string $s): string {
    if ($s === '') return $s;
    $first = mb_substr($s, 0, 1, 'UTF-8');
    $rest  = mb_substr($s, 1, null, 'UTF-8');
    if ($first === mb_strtolower($first, 'UTF-8') && $first !== mb_strtoupper($first, 'UTF-8')) {
        return mb_strtoupper($first, 'UTF-8') . $rest;
    }
    return $s;
}
}

if (!function_exists('sitc_cased_de_ingredient')) {
function sitc_cased_de_ingredient(string $s): string {
    $t = trim($s);
    if ($t === '') return $t;
    $letters = preg_replace('/[^\p{L}\s]/u', '', $t);
    if ($letters !== '' && $letters === mb_strtolower($letters, 'UTF-8')) {
        return sitc_ucfirst_de($t);
    }
    return $t;
}
}

if (!function_exists('sitc_format_qty_display')) {
function sitc_format_qty_display($val): string {
    if ($val === null) return '';
    $v = (float)$val;
    if (abs($v - round($v)) < 0.01) $v = round($v);
    $s = number_format($v, 2, ',', '');
    return rtrim(rtrim($s, '0'), ',');
}
}

if (!function_exists('sitc_format_qty_de')) {
function sitc_format_qty_de($val) {
    if ($val === null) return '';
    $s = number_format((float)$val, 2, ',', '');
    return rtrim(rtrim($s, '0'), ',');
}
}

