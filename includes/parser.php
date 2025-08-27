<?php
if (!defined('ABSPATH')) exit;

/**
 * Parser: Extrahiert Rezeptdaten aus einer URL (JSON-LD bevorzugt, Fallback HTML).
 */
function sitc_parse_recipe_from_url($url) {
    $response = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($response)) {
        error_log("SITC Parser: Fehler beim Abruf von $url → " . $response->get_error_message());
        return null;
    }
    $html = wp_remote_retrieve_body($response);
    if (!$html) {
        error_log("SITC Parser: Leerer Body von $url");
        return null;
    }

    $recipe = [
        'title'             => '',
        'description'       => '',
        'ingredients_struct'=> [],
        'instructions'      => [],
        'yield_raw'         => '',
        'yield_num'         => '',
        'image'             => '',
        'source_url'        => $url
    ];

    // --- 1. JSON-LD auslesen ---
    if (preg_match_all('/<script[^>]+application\/ld\+json[^>]*>(.*?)<\/script>/is', $html, $matches)) {
        foreach ($matches[1] as $block) {
            $json = json_decode(trim($block), true);
            if (!$json) continue;

            // @graph? → einzelnes Recipe extrahieren
            if (isset($json['@graph'])) {
                foreach ($json['@graph'] as $node) {
                    if (isset($node['@type']) && in_array('Recipe', (array)$node['@type'])) {
                        $json = $node;
                        break;
                    }
                }
            }

            if (isset($json['@type']) && in_array('Recipe', (array)$json['@type'])) {
                error_log("SITC Parser: Recipe-JSON gefunden auf $url");
                error_log("SITC Parser Keys: " . implode(', ', array_keys($json)));

                $recipe['title']       = $json['name'] ?? '';
                $recipe['description'] = $json['description'] ?? '';
                $recipe['image']       = is_array($json['image'] ?? '') ? reset($json['image']) : ($json['image'] ?? '');
                $recipe['yield_raw']   = $json['recipeYield'] ?? '';
                $recipe['yield_num']   = sitc_parse_yield_number($recipe['yield_raw']);

                // Zutaten
                if (!empty($json['recipeIngredient'])) {
                    foreach ((array)$json['recipeIngredient'] as $raw) {
                        $parsed = sitc_parse_ingredient_line($raw);
                        if ($parsed) $recipe['ingredients_struct'][] = $parsed;
                    }
                }

                // Anleitungen
                if (!empty($json['recipeInstructions'])) {
                    $recipe['instructions'] = sitc_parse_instructions($json['recipeInstructions']);
                }

                return $recipe;
            }
        }
    }

    // --- 2. Fallback: HTML Scraping ---
    error_log("SITC Parser: Kein gültiges JSON-LD gefunden → Fallback HTML bei $url");

    // Zutaten
    if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $matches)) {
        foreach ($matches[1] as $line) {
            $clean = wp_strip_all_tags($line);
            $parsed = sitc_parse_ingredient_line($clean);
            if ($parsed) $recipe['ingredients_struct'][] = $parsed;
        }
    }

    // Anleitungen: ol/ul oder p
    if (preg_match_all('/<ol.*?>(.*?)<\/ol>/is', $html, $ol)) {
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $ol[1][0], $steps)) {
            foreach ($steps[1] as $line) {
                $step = trim(wp_strip_all_tags($line));
                if ($step !== '') $recipe['instructions'][] = $step;
            }
        }
    } elseif (preg_match_all('/<p[^>]*>(.*?)<\/p>/is', $html, $matches)) {
        foreach ($matches[1] as $p) {
            $step = trim(wp_strip_all_tags($p));
            if (preg_match('/^(Step|Schritt|\d+[\.\)])\s+/i', $step)) {
                $recipe['instructions'][] = $step;
            }
        }
    }

    return $recipe;
}

/**
 * Yield robust parsen (Portionsangabe)
 */
function sitc_parse_yield_number($raw) {
    if (empty($raw)) return null;
    if (is_array($raw)) $raw = reset($raw);
    if (!is_string($raw)) return null;

    $raw = trim($raw);

    $fractions = [
        '½'=>'1/2','⅓'=>'1/3','⅔'=>'2/3',
        '¼'=>'1/4','¾'=>'3/4',
        '⅛'=>'1/8','⅜'=>'3/8','⅝'=>'5/8','⅞'=>'7/8'
    ];
    $raw = strtr($raw, $fractions);

    if (preg_match('/(\d+(?:[.,]\d+)?)(?:\s*-\s*\d+(?:[.,]\d+)?)?/u', $raw, $m)) {
        return (float) str_replace(',', '.', $m[1]);
    }
    if (preg_match('/(\d+)\s*\/\s*(\d+)/', $raw, $m)) {
        return $m[2] != 0 ? (float)$m[1] / (float)$m[2] : null;
    }
    return null;
}

/**
 * Zutatenzeile parsen → [qty, unit, name]
 */
function sitc_parse_ingredient_line($raw) {
    $raw = trim(preg_replace('/\s+/', ' ', (string)$raw));
    if ($raw === '') return null;

    if (preg_match('/^([\d\/\.\,\-\s]+)\s*([^\d\s]+)?\s*(.+)$/u', $raw, $m)) {
        return [
            'qty'  => trim($m[1]),
            'unit' => trim($m[2] ?? ''),
            'name' => trim($m[3] ?? '')
        ];
    }
    return [
        'qty'  => '',
        'unit' => '',
        'name' => $raw
    ];
}

/**
 * Anleitungen parsen – akzeptiert Text, Array, verschachtelt
 */
function sitc_parse_instructions($input) {
    $steps = [];

    if (is_array($input)) {
        foreach ($input as $step) {
            if (is_array($step) && isset($step['text'])) {
                $steps[] = trim($step['text']);
            } elseif (is_string($step)) {
                $steps[] = trim($step);
            }
        }
    } elseif (is_string($input)) {
        $parts = preg_split('/\r?\n|\.\s+(?=[A-ZÄÖÜ])/u', $input);
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p) $steps[] = $p;
        }
    }

    return $steps;
}
