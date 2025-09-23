<?php
// qty.sitcnorm.v4.php — robust, conservative QTY normalizer (no unit synonym rewrites)
// - UTF-8 (no BOM), no declare(), no closing PHP tag
// - Safe to call early in tokenization before regex patterns

if (!function_exists('sitc_qty_normalize')) {
function sitc_qty_normalize(string $s): string {
    // 1) NBSP / NNBSP → normal space
    $s = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $s) ?? $s;

    // 2) Normalize en/em dash (– —) → hyphen-minus (-)
    $s = str_replace(["\xE2\x80\x93", "\xE2\x80\x94"], "-", $s);

    // 3) Unicode vulgar fractions → ASCII fractions (¼→1/4, ½→1/2, …)
    //    SAFEGUARD: do NOT replace if glyph is embedded inside a word (letters on either side)
    $map = [
        "¼" => "1/4", "½" => "1/2", "¾" => "3/4",
        "⅐" => "1/7", "⅑" => "1/9", "⅒" => "1/10",
        "⅓" => "1/3", "⅔" => "2/3",
        "⅕" => "1/5", "⅖" => "2/5", "⅗" => "3/5", "⅘" => "4/5",
        "⅙" => "1/6", "⅚" => "5/6",
        "⅛" => "1/8", "⅜" => "3/8", "⅝" => "5/8", "⅞" => "7/8",
    ];
    $chars = implode('', array_map('preg_quote', array_keys($map)));
    $s = preg_replace_callback('/(?<![A-Za-zÀ-ÖØ-öø-ÿ])(['.$chars.'])(?![A-Za-zÀ-ÖØ-öø-ÿ])/u', function($m) use ($map) {
        $g = $m[1];
        return $map[$g] ?? $g;
    }, $s) ?? $s;

    // 3b) Ensure a space between an integer and an immediately following ASCII fraction (e.g., "11/2" -> "1 1/2")
    $s = preg_replace('/(?<=\d)(?=\d\/\d)/', ' ', $s) ?? $s;

    // 4) Decimal comma only when between digits: "0,75" -> "0.75"
    $s = preg_replace('/(?<=\d),(?=\d)/', '.', $s) ?? $s;

    // 5) Multiplier: × -> x and normalize spacing: "2x200" -> "2 x 200"
    $s = str_replace("×", "x", $s);
    $s = preg_replace('/(\d)\s*[xX]\s*(\d)/', '$1 x $2', $s) ?? $s;

    // 6) Tolerance markers (remove as words, including optional dot): "ca.", "circa", "etwa", "ungefähr/ungefaehr", "approx", "~"
    $s = preg_replace('/(?<!\S)(?:ca|circa|etwa|ungef(?:ae|ä)hr|approx|~)\.?/iu', ' ', $s) ?? $s;

    // 6b) If tolerance removal left a stray leading dot before a number, strip it: ". 0.75" -> "0.75"
    $s = preg_replace('/^\s*\.\s*(?=\d)/', '', $s) ?? $s;

    // 7) Whitespace normalization
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    $s = trim($s);

    return $s;
}
}
