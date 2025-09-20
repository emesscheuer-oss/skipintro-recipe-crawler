<?php

/**
 * Mini-Labor für Mengen-Prä-Normalisierung & Parsing (standalone, ohne WP).
 * Nicht im Live-Renderer verwenden, bevor die Tests grün sind.
 */

function sitc_qty_prenorm(string $s): string {
    // Trim & unify whitespace
    $s = str_replace(["\u{00A0}", "\u{202F}"], ' ', $s); // NBSP, NNBSP -> Space
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    $s = trim($s);

    // Decode literal u00XX sequences -> echtes UTF-8-Zeichen (z.B. "u00bd" -> ½)
    $s = preg_replace_callback('/u00([0-9a-fA-F]{2})/', function ($m) {
        $code = hexdec($m[1]);
        return mb_convert_encoding(chr($code), 'UTF-8', 'ISO-8859-1');
    }, $s) ?? $s;

    // "1 /2" -> "1/2" (Spaces um Bruchstrich)
    $s = preg_replace('/(\d+)\s*\/\s*(\d+)/', '$1/$2', $s) ?? $s;

    // Unicode-Fraktionen -> ASCII-Brüche
    // Mindestens: ½, ⅓, ⅔, ¼, ¾, ⅛
    $map = [
        "¼" => "1/4", "½" => "1/2", "¾" => "3/4",
        "⅓" => "1/3", "⅔" => "2/3",
        "⅛" => "1/8",
    ];
    $s = strtr($s, $map);

    // Gemischte Zahl: "1½" / "1 ½" / "1 1/2" -> "1+1/2"
    // (erst Unicode-Mix, danach ASCII-Fälle)
    $s = preg_replace('/\b(\d+)\s*(1\/2|1\/3|2\/3|1\/4|3\/4|1\/8)\b/u', '$1+$2', $s) ?? $s;

    // Stopwörter am Anfang (ca., circa, about, approx., ungefähr, (ca.)) entfernen
    $s = preg_replace('/^(?:\(?\s*(?:ca\.?|circa|about|approx\.?|ungef(?:\x{00E4}|ae)?hr?)\s*\)?\s*)/ui', '', $s) ?? $s;

    // Doppelte Kommas/Spaces aufräumen
    $s = preg_replace('/\s+/', ' ', $s) ?? $s;
    return trim($s);
}

/**
 * @return array{qty: null|float, unit: null|string, item: string, raw: string}
 */
function sitc_qty_parse(string $s): array {
    $raw = $s;

    // 1) Menge extrahieren (Float; unterstützt auch "1+1/2" oder "1/2")
    $qty = null;

    // a) "1+1/2" -> Float
    if (preg_match('/^\s*(\d+)\+(\d+)\/(\d+)\b/u', $s, $m)) {
        $qty = (float)$m[1] + ((int)$m[2] / (int)$m[3]);
        $s   = substr($s, strlen($m[0]));
    }
    // b) "1/2" -> Float
    elseif (preg_match('/^\s*(\d+)\/(\d+)\b/u', $s, $m)) {
        $qty = (int)$m[1] / (int)$m[2];
        $s   = substr($s, strlen($m[0]));
    }
    // c) Dezimal/Ganzzahl ("1,5" | "1.5" | "2")
    elseif (preg_match('/^\s*(\d+(?:[.,]\d+)?)(?![^\s])/u', $s, $m)) {
        $num = str_replace(',', '.', $m[1]);
        $qty = (float)$num;
        $s   = substr($s, strlen($m[0]));
    }

    $s = ltrim($s);

    // 2) Einheit rudimentär erkennen (nur gängigste + "TK" tolerieren)
    $unit = null;
    if ($qty !== null) {
        if (preg_match('/^(TL|EL|ml|g|kg|l|St(?:ue|ü)ck|Stk|TK)\b/ui', $s, $m)) {
            $unit = normalisiere_einheit($m[1]);
            $s    = ltrim(substr($s, strlen($m[0])));
        }
    }

    // 3) Rest ist Item
    $item = trim($s);

    return [
        'qty'  => $qty,
        'unit' => $unit,
        'item' => $item,
        'raw'  => $raw,
    ];
}

function normalisiere_einheit(string $u): string {
    $u = mb_strtolower($u, 'UTF-8');
    // DE-Konventionen: ml/g/kg/l klein; TL/EL groß; Stück mit Umlaut
    $map = [
        'tl' => 'TL',
        'el' => 'EL',
        'stk' => 'Stück',
        'stueck' => 'Stück',
        'stück' => 'Stück',
        'ml' => 'ml',
        'g'  => 'g',
        'kg' => 'kg',
        'l'  => 'l',
        'tk' => 'TK',  // geduldet (Formfaktor)
    ];
    return $map[$u] ?? $u;
}

function de_format(float $f): string {
    // Ganzzahl ohne Nachkomma, sonst max. 2 (deutsches Komma)
    if (abs($f - round($f)) < 1e-9) {
        return (string)round($f);
    }
    return str_replace('.', ',', rtrim(rtrim(number_format($f, 2, '.', ''), '0'), '.'));
}
