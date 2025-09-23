<?php

/**
 * Unit-Erkennung: mappt Token neben der Menge auf kanonische Einheitencodes.
 */
if (!function_exists('sitc_ing_detect_unit')) {
function sitc_ing_detect_unit(array $tok, $qty, string $locale = 'de'): ?string {
    $line = (string)($tok['norm'] ?? ($tok['raw'] ?? ''));

    static $fallbackMap = null;
    static $normalizeKey = null;

    if ($normalizeKey === null) {
        $normalizeKey = static function(string $val): string {
            $val = mb_strtolower(trim($val), 'UTF-8');
            $val = strtr($val, ['\x{00E4}'=>'ae','\x{00F6}'=>'oe','\x{00FC}'=>'ue','\x{00DF}'=>'ss']);
            $val = preg_replace('/[^a-z]/u', '', $val) ?? $val;
            return $val;
        };
    }
    if ($fallbackMap === null) {
        $fallbackMap = [
            'el' => 'tbsp',
            'essl' => 'tbsp',
            'essloeffel' => 'tbsp',
            'essloffel' => 'tbsp',
            'tl' => 'tsp',
            'teel' => 'tsp',
            'teeloeffel' => 'tsp',
            'teeloffel' => 'tsp',
            'tasse' => 'cup',
            'tassen' => 'cup',
            'cup' => 'cup',
            'cups' => 'cup',
            'bund' => 'bunch',
            'bunde' => 'bunch',
            'buendel' => 'bunch',
            'bundel' => 'bunch',
            'bunch' => 'bunch',
            'stk' => 'piece',
            'stueck' => 'piece',
            'stuck' => 'piece',
            'piece' => 'piece',
            'pieces' => 'piece',
            'zehe' => 'clove',
            'zehen' => 'clove',
            'clove' => 'clove',
            'cloves' => 'clove',
            'prise' => 'pinch',
            'prisen' => 'pinch',
            'pinch' => 'pinch',
            'dose' => 'can',
            'dosen' => 'can',
            'can' => 'can',
            'gr' => 'g',
            'g' => 'g',
            'kg' => 'kg',
            'ml' => 'ml',
            'l' => 'l',
        ];
    }

    $patternStart = '/^\s*(?:\d+(?:\.\d+)?(?:\s*[-\x{2013}\x{2014}\x{2015}]\s*\d+(?:\.\d+)?)?)\s*(\p{L}+[\.\)]?)/u';
    if (preg_match($patternStart, $line, $m)) {
        $candidate = rtrim($m[1], '.)');
        if ($candidate !== '') {
            if (function_exists('sitc_unit_alias_canonical')) {
                $canon = sitc_unit_alias_canonical($candidate);
                if ($canon) {
                    return $canon;
                }
            }
            $key = $normalizeKey($candidate);
            if (isset($fallbackMap[$key])) {
                return $fallbackMap[$key];
            }
            $lower = mb_strtolower($candidate, 'UTF-8');
            if (in_array($lower, ['ml','l','g','kg'], true)) {
                return $lower;
            }
        }
    }

    // Fused word special-case: Knoblauchzehen -> clove (when qty present)
    $lowerLine = mb_strtolower($line, 'UTF-8');
    if ($qty !== null && preg_match('/knoblauchzeh(?:e|en)\b/u', $lowerLine)) {
        return 'clove';
    }

    return null;
}
}
