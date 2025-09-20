<?php

// Parser-Lab harness: central parse entry with error capture and normalization

require_once dirname(__DIR__) . '/lib_io.php';

/**
 * pl_harness_parse(string $html, string $pageUrl): array
 * Returns [ 'recipe' => array, 'errors' => string[], 'info' => array ]
 */
function pl_harness_parse(string $html, string $pageUrl): array {
    $errors = [];
    $info   = [ 'entry' => null ];

    try {
        if (function_exists('parseRecipe')) {
            $info['entry'] = 'parseRecipe';
            $res = parseRecipe($html, $pageUrl);
            $recipe = is_array($res['recipe'] ?? null) ? $res['recipe'] : $res;
        } elseif (function_exists('sitc_parse_recipe_from_html')) {
            $info['entry'] = 'sitc_parse_recipe_from_html';
            $recipe = sitc_parse_recipe_from_html($html, $pageUrl);
        } else {
            $errors[] = 'Kein Parser-Einstieg gefunden (parseRecipe / sitc_parse_recipe_from_html).';
            $recipe = [];
        }
    } catch (Throwable $e) {
        $trace = $e->getTrace();
        $top = isset($trace[0]) ? ($trace[0]['file'] ?? '') . ':' . (string)($trace[0]['line'] ?? '') : '';
        $errors[] = sprintf('Exception %s: %s in %s:%d%s',
            get_class($e), (string)$e->getMessage(), (string)$e->getFile(), (int)$e->getLine(),
            $top ? (' | top: ' . $top) : ''
        );
        $recipe = [];
    }

    // Normalize ingredients to structured entries
    $recipe['recipeIngredient'] = pl_normalize_ingredients($recipe, $errors);

    return [ 'recipe' => $recipe, 'errors' => $errors, 'info' => $info ];
}

/**
 * Normalize any available ingredients to a structured list for the table.
 * - Prefer recipeIngredient (array of strings or structs)
 * - Else map ingredientsParsed if present
 * - Else produce structured entries from any string lines found
 */
function pl_normalize_ingredients(array $recipe, array &$errors): array {
    $items = [];
    $addFromArray = function ($arr) use (&$items, &$errors) {
        foreach ($arr as $it) {
            if (is_array($it)) {
                $items[] = [
                    'raw'  => (string)($it['raw'] ?? ''),
                    'qty'  => $it['qty'] ?? null,
                    'unit' => isset($it['unit']) ? (string)$it['unit'] : null,
                    'item' => (string)($it['item'] ?? ''),
                    'note' => isset($it['note']) ? (string)$it['note'] : null,
                ];
            } else {
                $raw = (string)$it;
                $parsed = null;
                if (function_exists('sitc_parse_ingredient_line')) {
                    try { $parsed = sitc_parse_ingredient_line($raw); } catch (Throwable $e) {
                        $t = $e->getTrace();
                        $top = isset($t[0]) ? ($t[0]['file'] ?? '') . ':' . (string)($t[0]['line'] ?? '') : '';
                        $errors[] = sprintf('Line-Parse-Error %s: %s in %s:%d%s',
                            get_class($e), (string)$e->getMessage(), (string)$e->getFile(), (int)$e->getLine(),
                            $top ? (' | top: ' . $top) : ''
                        );
                    }
                }
                if (is_array($parsed)) {
                    $items[] = [
                        'raw'  => (string)($parsed['raw'] ?? $raw),
                        'qty'  => $parsed['qty'] ?? null,
                        'unit' => isset($parsed['unit']) ? (string)$parsed['unit'] : null,
                        'item' => (string)($parsed['item'] ?? ''),
                        'note' => isset($parsed['note']) ? (string)$parsed['note'] : null,
                    ];
                } else {
                    $items[] = [ 'raw' => $raw, 'qty' => null, 'unit' => null, 'item' => $raw, 'note' => null ];
                }
            }
        }
    };

    if (!empty($recipe['recipeIngredient']) && is_array($recipe['recipeIngredient'])) {
        $addFromArray($recipe['recipeIngredient']);
    } elseif (!empty($recipe['ingredientsParsed']) && is_array($recipe['ingredientsParsed'])) {
        $addFromArray($recipe['ingredientsParsed']);
    } elseif (!empty($recipe['ingredients']) && is_array($recipe['ingredients'])) {
        $addFromArray($recipe['ingredients']);
    }

    return $items;
}

/**
 * sitc_run_fixture(string $fixturePath, ?string $pageUrl): array
 * - Resolves fixture path (absolute or relative to plugin root)
 * - Embeds .jsonld as HTML with <script type="application/ld+json">
 * - Tries to normalize encodings to UTF-8
 * - Calls parser with try/catch
 * - Ensures a sensible, structured ingredients table is always possible
 */
function sitc_run_fixture(string $fixturePath, ?string $pageUrl = null): array {
    $errors = [];
    $pluginRoot = realpath(dirname(__DIR__, 2)) ?: (dirname(__DIR__, 2));
    $parserPhp  = $pluginRoot . '/includes/parser.php';

    // Resolve fixture (robust: support absolute Windows/Unix; avoid mixed prefixes like 'tools/C:/...')
    $in = (string)$fixturePath;
    // Strip accidental 'tools/' prefix before a Windows drive or UNC path
    $in = preg_replace('~^tools[\\/]+(?=(?:[a-zA-Z]:[\\/]|\\\\\\\\))~', '', $in);
    // Normalize backslashes for detection (do not alter final filesystem path semantics)
    $detect = str_replace('\\', '/', $in);
    $isAbs = (bool)preg_match('~^(?:[a-zA-Z]:/|/|\\\\\\\\)~', $detect);
    $fixtureRel = $isAbs ? $in : ltrim($in, '/');
    $fixtureAbs = $isAbs ? $fixtureRel : ($pluginRoot . '/' . $fixtureRel);
    $fixtureAbsReal = (is_file($fixtureAbs) ? (realpath($fixtureAbs) ?: $fixtureAbs) : $fixtureAbs);

    $html = '';
    $rawLd = null;
    $jsonldScriptCount = 0;
    if (file_exists($fixtureAbsReal)) {
        if (!is_readable($fixtureAbsReal)) {
            $errors[] = 'Fixture nicht lesbar: ' . $fixtureAbsReal;
        } else {
            $raw = @file_get_contents($fixtureAbsReal);
            if ($raw === false) {
                $errors[] = 'Konnte Fixture nicht lesen: ' . $fixtureAbsReal;
                $raw = '';
            }
            // Normalize to UTF-8 where possible
            $normalized = $raw;
            $encOk = true;
            if (function_exists('mb_check_encoding') && !mb_check_encoding($normalized, 'UTF-8')) {
                $det = function_exists('mb_detect_encoding') ? (mb_detect_encoding($normalized, ['UTF-8','Windows-1252','ISO-8859-1','ISO-8859-15'], true) ?: null) : null;
                $conv = null;
                if ($det && function_exists('iconv')) {
                    $conv = @iconv($det, 'UTF-8//IGNORE', $normalized);
                }
                if ($conv !== false && $conv !== null) {
                    $normalized = $conv;
                } else {
                    $encOk = false;
                    $errors[] = 'Encoding nicht UTF-8 und Konvertierung fehlgeschlagen (iconv).';
                }
            }

            $ext = strtolower(pathinfo($fixtureAbsReal, PATHINFO_EXTENSION));
            if ($ext === 'jsonld') {
                $rawLd = $normalized;
                $html = "<!doctype html><html><head><meta charset=\"utf-8\">"
                      . "<script type=\"application/ld+json\">" . $rawLd . "</script>"
                      . "</head><body></body></html>";
            } elseif (in_array($ext, ['html','htm','txt'], true)) {
                $html = $normalized;
            } else {
                $errors[] = 'Unbekannte Fixture-Endung .' . $ext . ' — nutze HTML-Stub';
                $html = "<!doctype html><html><head><meta charset=\"utf-8\"></head><body></body></html>";
            }

            // Count JSON-LD scripts in HTML
            if (is_string($html) && $html !== '') {
                $jsonldScriptCount = preg_match_all('~<script[^>]+type\s*=\s*([\"\']?)application/ld\+json\1[^>]*>~i', $html) ?: 0;
            }

            if (!$encOk) {
                $errors[] = 'Hinweis: Inhalt möglicherweise nicht UTF-8-korrigiert dargestellt.';
            }
        }
    } else {
        $errors[] = 'Fixture nicht vorhanden, bitte Datei im Verzeichnis fixtures/ prüfen. Pfad: ' . $fixtureAbsReal;
    }

    // Fallback URL when none provided
    $url = $pageUrl ?: ('https://fixtures.local/' . (basename((string)$fixtureRel) ?: 'index.html'));

    // Execute parser if possible
    $recipe = [];
    $res = null;
    $parseMs = null;
    $exceptionSummary = null;
    try {
        if ($html === '' || $html === null) {
            // Do not pass empty input to parser; return clear infra error instead
            $errors[] = 'Kein Inhalt geladen (leerer Input) – bitte Fixture prüfen.';
        } elseif (function_exists('parseRecipe')) {
            $t0 = microtime(true);
            $res = parseRecipe((string)$html, (string)$url);
            $t1 = microtime(true);
            $parseMs = (int)round(($t1 - $t0) * 1000);
            $recipe = is_array($res['recipe'] ?? null) ? $res['recipe'] : (is_array($res) ? $res : []);
        } elseif (function_exists('sitc_parse_recipe_from_html')) {
            $t0 = microtime(true);
            $recipe = sitc_parse_recipe_from_html((string)$html, (string)$url);
            $t1 = microtime(true);
            $parseMs = (int)round(($t1 - $t0) * 1000);
        } else {
            $errors[] = 'Parser-Einstieg fehlt (includes/parser.php oder Funktion nicht vorhanden).';
        }
    } catch (Throwable $e) {
        $trace = $e->getTrace();
        $top = isset($trace[0]) ? ($trace[0]['file'] ?? '') . ':' . (string)($trace[0]['line'] ?? '') : '';
        $shortTrace = [];
        for ($i = 0; $i < min(5, count($trace)); $i++) {
            $f = $trace[$i]['file'] ?? '';
            $ln = $trace[$i]['line'] ?? '';
            $fn = $trace[$i]['function'] ?? '';
            $shortTrace[] = ($f ? ($f . ':' . $ln) : '[internal]') . ($fn ? (' ' . $fn . '()') : '');
        }
        $errors[] = sprintf('Exception %s: %s in %s:%d%s%s%s',
            get_class($e), (string)$e->getMessage(), (string)$e->getFile(), (int)$e->getLine(),
            $top ? (' | top: ' . $top) : '',
            $shortTrace ? ' | trace: ' : '',
            $shortTrace ? implode(' › ', $shortTrace) : ''
        );
        $recipe = [];
        $exceptionSummary = sprintf('%s: %s', get_class($e), (string)$e->getMessage());
    }

    // Determine whether parser returned structured entries before normalization
    $origHasStruct = false;
    $origLen = 0;
    if (!empty($recipe['recipeIngredient']) && is_array($recipe['recipeIngredient'])) {
        $origLen = count($recipe['recipeIngredient']);
        foreach ($recipe['recipeIngredient'] as $it) {
            if (is_array($it)) { $origHasStruct = true; break; }
        }
    } elseif (!empty($recipe['ingredientsParsed']) && is_array($recipe['ingredientsParsed'])) {
        $origLen = max($origLen, count($recipe['ingredientsParsed']));
        $origHasStruct = true;
    }

    // Normalize to structured entries
    $items = pl_normalize_ingredients($recipe, $errors);

    // Fallback: if items still empty and we had JSON-LD, try to extract strings directly
    if (count($items) === 0 && is_string($rawLd) && $rawLd !== '') {
        $decoded = json_decode($rawLd, true);
        $strings = [];
        $collect = function ($node) use (&$collect, &$strings) {
            if (is_array($node)) {
                if (array_key_exists('recipeIngredient', $node) && is_array($node['recipeIngredient'])) {
                    foreach ($node['recipeIngredient'] as $v) { if (is_string($v)) { $strings[] = $v; } }
                }
                foreach ($node as $v) { $collect($v); }
            }
        };
        if (is_array($decoded)) { $collect($decoded); }
        if (count($strings) > 0) {
            foreach ($strings as $raw) {
                $items[] = [ 'raw' => (string)$raw, 'qty' => null, 'unit' => null, 'item' => (string)$raw, 'note' => null ];
            }
            $errors[] = 'Fallback: recipeIngredient aus JSON-LD extrahiert (' . count($strings) . ' Einträge)';
        }
    }
    $recipe['recipeIngredient'] = $items;

    // Analyze JSON-LD scripts for diagnostics
    $jsonldTypes = [];
    $jsonldFoundRiLen = 0;
    if (is_string($html) && $html !== '') {
        if (preg_match_all('~<script[^>]+type\s*=\s*([\"\']?)application/ld\+json\1[^>]*>(.*?)</script>~is', $html, $m)) {
            $count = min(2, count($m[2]));
            for ($i = 0; $i < $count; $i++) {
                $txt = trim($m[2][$i]);
                $dec = json_decode($txt, true);
                if (is_array($dec)) {
                    $collectTypes = function ($node) use (&$collectTypes, &$jsonldTypes, &$jsonldFoundRiLen) {
                        if (is_array($node)) {
                            if (isset($node['@type'])) {
                                $t = $node['@type'];
                                if (is_string($t)) { $jsonldTypes[] = $t; }
                                elseif (is_array($t)) { foreach ($t as $tt) { if (is_string($tt)) { $jsonldTypes[] = $tt; } } }
                            }
                            if (isset($node['recipeIngredient']) && is_array($node['recipeIngredient'])) {
                                $jsonldFoundRiLen = max($jsonldFoundRiLen, count($node['recipeIngredient']));
                            }
                            if (isset($node['@graph']) && is_array($node['@graph'])) {
                                foreach ($node['@graph'] as $g) { $collectTypes($g); }
                            }
                        }
                    };
                    $collectTypes($dec);
                }
            }
        }
    }

    $info = [
        'paths' => [
            'plugin_root' => $pluginRoot,
            'parser_path' => $parserPhp,
            'fixture_rel' => is_string($fixtureRel) ? $fixtureRel : '',
            'fixture_abs' => $fixtureAbsReal,
        ],
        'metrics' => [
            'html_len' => is_string($html) ? strlen($html) : 0,
            'jsonld_script_count' => $jsonldScriptCount,
            'parse_ms' => $parseMs,
            'parsed_has_struct' => $origHasStruct,
            'parsed_ri_len' => is_array($items) ? count($items) : 0,
            'has_exception' => $exceptionSummary !== null,
            'exception' => $exceptionSummary,
        ],
        'parser' => [
            'found' => file_exists($parserPhp),
            'readable' => is_readable($parserPhp),
            'has_parseRecipe' => function_exists('parseRecipe'),
            'has_legacy_entry' => function_exists('sitc_parse_recipe_from_html'),
        ],
        'jsonld' => [
            'types_preview' => array_values(array_unique(array_slice($jsonldTypes, 0, 2))),
            'found_recipeIngredient_len' => $jsonldFoundRiLen,
        ],
        'errors' => [],
    ];

    // Collect flags/confidence if provided by parser
    $flags = null; $confidence = null;
    if (is_array($res)) {
        $flags = $res['flags'] ?? null;
        $confidence = $res['confidence'] ?? null;
    }

    // Mirror errors also inside info for consumers wanting structured info-only access
    $info['errors'] = $errors;

    return [ 'recipe' => $recipe, 'errors' => $errors, 'info' => $info, 'flags' => $flags, 'confidence' => $confidence ];
}
