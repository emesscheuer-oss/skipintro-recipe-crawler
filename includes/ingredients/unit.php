<?php
declare(strict_types=1);

/**
 * Unit-Erkennung (Stub): delegiert an vorhandene Logik oder liefert null.
 * TODO: Alias-Map (el↔tbsp, tl↔tsp, bund↔bunch, stück↔piece, zehe↔clove).
 */
if (!function_exists('sitc_ing_detect_unit')) {
function sitc_ing_detect_unit(array $tok, $qty, string $locale = 'de'): ?string {
    $s = mb_strtolower((string)($tok['norm'] ?? ($tok['raw'] ?? '')), 'UTF-8');
    // Canonical map
    $map = [
        'el'=>'tbsp','esslöffel'=>'tbsp','essloeffel'=>'tbsp','eßlöffel'=>'tbsp','eßloeffel'=>'tbsp',
        'tl'=>'tsp','teelöffel'=>'tsp','teeloeffel'=>'tsp',
        'bund'=>'bunch','bund.'=>'bunch','bunde'=>'bunch','bund(e)'=>'bunch','bunch'=>'bunch',
        'stück'=>'piece','stueck'=>'piece','stk'=>'piece','piece'=>'piece',
        'zehe'=>'clove','zehen'=>'clove','clove'=>'clove','cloves'=>'clove',
        'g'=>'g','gramm'=>'g','gram'=>'g', 'kg'=>'kg',
    ];
    // Look for a unit token immediately after qty at start when present
    $patternStart = '/^\s*(?:\d+(?:\.\d+)?(?:\s*[-]\s*\d+(?:\.\d+)?)?)\s*(\p{L}+[\.\)]?)/u';
    if (preg_match($patternStart, $s, $m)) {
        $u = rtrim($m[1], '.)');
        if (isset($map[$u])) return $map[$u];
    }
    // Fused word special-case: Knoblauchzehen → clove (when qty present or looks like ingredient)
    if ($qty !== null && preg_match('/knoblauchzeh(?:e|en)\b/u', $s)) {
        return 'clove';
    }
    return null;
}
}
