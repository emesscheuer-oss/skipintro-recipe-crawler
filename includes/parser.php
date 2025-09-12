<?php
if (!defined('ABSPATH')) exit;

// Load parser helpers (PCRE2-safe tokenizer, qty parsing, unit aliases)
// Keep renderer untouched; helpers are parser-internal only.
$__sitc_helpers = __DIR__ . '/parser_helpers.php';
if (is_file($__sitc_helpers)) {
    require_once $__sitc_helpers;
}

/**
 * Parser: Extrahiert Rezeptdaten aus einer URL (JSON-LD bevorzugt, Fallback HTML).
 */
function sitc_parse_recipe_from_url($url) {
    $response = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($response)) {
        error_log("SITC Parser: Fehler beim Abruf von $url ÔåÆ " . $response->get_error_message());
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

            // @graph? ÔåÆ einzelnes Recipe extrahieren
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
    error_log("SITC Parser: Kein g├╝ltiges JSON-LD gefunden ÔåÆ Fallback HTML bei $url");

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
 * New structured parser per spec: parseRecipe(string $html, ?string $pageUrl = null): array
 * Returns [ 'recipe' => RecipeObject, 'confidence' => float, 'sources' => map, 'flags' => map ]
 */
function parseRecipe(string $html, ?string $pageUrl = null): array {
    // Load DOM once for Microdata and DOM fallback
    $dom = sitc_load_dom($html);

    // 1) JSON-LD candidates
    $jsonldCandidates = sitc_extract_jsonld_recipe_nodes($html);

    // 2) Microdata
    $micro = sitc_extract_microdata_recipe($dom);

    // 3) DOM fallback within scopes, after noise removal
    $scopeSelectors = ['.recipe', '.recipe-card', '.wprm-recipe-container', '[data-recipe]'];
    $excludeSelectors = [
        '.comments','.comment-list','.related','.you-might-also-like',
        '.share','.social','.affiliate','.shop','.newsletter','.subscribe'
    ];
    $domFallback = sitc_extract_dom_fallback($dom, $scopeSelectors, $excludeSelectors, $pageUrl);

    // 4) Merge with priority JSON-LD > Microdata > DOM
    [$merged, $sources] = sitc_merge_with_priority($jsonldCandidates, $micro, $domFallback);

    // 5) Normalize
    $normalized = sitc_normalize_recipe($merged, $dom, $pageUrl);

    // 6) Validate + flags (isPartial)
    $flags = sitc_validate_and_flags($normalized);

    // 7) Score + finalize flags.lowConfidence
    $confidence = sitc_score_recipe($normalized, $sources);
    $flags['lowConfidence'] = ($confidence < 0.7);

    return [
        'recipe'     => $normalized,
        'confidence' => $confidence,
        'sources'    => $sources,
        'flags'      => $flags,
    ];
}

/**
 * Wrapper: fetch URL and return legacy + rich meta using parseRecipe()
 */
function sitc_parse_recipe_from_url_v2(string $url) {
    $response = wp_remote_get($url, ['timeout' => 20]);
    if (is_wp_error($response)) {
        error_log("SITC Parser: Fehler beim Abruf von $url ÔÇô " . $response->get_error_message());
        return null;
    }
    $html = wp_remote_retrieve_body($response);
    if (!$html) {
        error_log("SITC Parser: Leerer Body von $url");
        return null;
    }

    $result = parseRecipe($html, $url);
    $r = $result['recipe'] ?? [];

    $image = '';
    if (!empty($r['image'])) {
        if (is_array($r['image'])) { $image = reset($r['image']); }
        else { $image = (string)$r['image']; }
    }

    $yieldRaw = isset($r['recipeYield']) ? (is_array($r['recipeYield']) ? reset($r['recipeYield']) : (string)$r['recipeYield']) : '';
    $yieldNum = null;
    if (!empty($r['yieldNormalized']['count'])) $yieldNum = (float)$r['yieldNormalized']['count'];
    if ($yieldNum === null) $yieldNum = sitc_parse_yield_number($yieldRaw);

    $ingredients_struct = [];
    if (!empty($r['ingredientsParsed']) && is_array($r['ingredientsParsed'])) {
        foreach ($r['ingredientsParsed'] as $p) {
            $name = trim((string)($p['item'] ?? ''));
            if (!empty($p['note'])) $name .= ' ('.trim((string)$p['note']).')';
            $qval = $p['qty'] ?? '';
            if (is_array($qval) && isset($qval['low'],$qval['high'])) {
                $qtyOut = str_replace(',', '.', (string)((float)$qval['low'])) . '-' . str_replace(',', '.', (string)((float)$qval['high']));
            } elseif ($qval === null || $qval === '') {
                $qtyOut = '';
            } else {
                $qtyOut = is_numeric($qval) ? str_replace(',', '.', (string)$qval) : (string)$qval;
            }
            $ingredients_struct[] = [
                'qty'  => $qtyOut,
                'unit' => $p['unit'] ?? '',
                'name' => $name !== '' ? $name : ($p['raw'] ?? '')
            ];
        }
    } elseif (!empty($r['recipeIngredient']) && is_array($r['recipeIngredient'])) {
        foreach ($r['recipeIngredient'] as $raw) {
            $parsed = sitc_parse_ingredient_line((string)$raw);
            if ($parsed) $ingredients_struct[] = $parsed;
        }
    }

    // De-Dupe final structured list (case-/diacritics-/whitespace-insensitiv ├╝ber die kombinierte Zeile)
    if (!empty($ingredients_struct)) {
        $seen = [];
        $uniq = [];
        foreach ($ingredients_struct as $ing) {
            $qty  = trim((string)($ing['qty'] ?? ''));
            $unit = trim((string)($ing['unit'] ?? ''));
            $name = trim((string)($ing['name'] ?? ''));
            $line = trim(($qty !== '' ? $qty.' ' : '').($unit !== '' ? $unit.' ' : '').$name);
            $key = sitc_canon_key($line);
            if ($key === '') continue;
            if (!isset($seen[$key])) { $seen[$key] = true; $uniq[] = $ing; }
        }
        $ingredients_struct = $uniq;
    }

    $instructions = [];
    if (!empty($r['recipeInstructions']) && is_array($r['recipeInstructions'])) {
        foreach ($r['recipeInstructions'] as $st) {
            if (is_array($st)) { $txt = trim((string)($st['text'] ?? '')); if ($txt!=='') $instructions[] = $txt; }
            elseif (is_string($st)) { $txt = trim($st); if ($txt!=='') $instructions[] = $txt; }
        }
    }

    return [
        'title'              => $r['name'] ?? '',
        'description'        => $r['description'] ?? '',
        'ingredients_struct' => $ingredients_struct,
        'instructions'       => $instructions,
        'yield_raw'          => $yieldRaw,
        'yield_num'          => $yieldNum,
        'image'              => $image,
        'source_url'         => !empty($r['url']) ? $r['url'] : $url,
        'meta'               => [
            'schema_recipe' => $r,
            'sources'       => $result['sources'] ?? [],
            'confidence'    => $result['confidence'] ?? 0.0,
            'flags'         => $result['flags'] ?? ['isPartial'=>false,'lowConfidence'=>false],
        ],
    ];
}
// ============ Pipeline helpers ============

function sitc_load_dom(string $html): DOMDocument {
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    // Ensure UTF-8 handling; prepend meta if missing
    $encHtml = $html;
    if (stripos($html, '<meta charset=') === false) {
        $encHtml = preg_replace('/<head(\b[^>]*)>/i', '<head$1><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>', $html, 1);
    }
    @$doc->loadHTML($encHtml);
    libxml_clear_errors();
    return $doc;
}

function sitc_extract_jsonld_recipe_nodes(string $html): array {
    $candidates = [];
    if (!preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m)) {
        return $candidates;
    }

    foreach ($m[1] as $block) {
        $text = trim($block);
        // Tolerant: strip HTML comments and decode entities that often break JSON
        $text = preg_replace('/<!--.*?-->/s', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $decoded = sitc_jsonld_decode_tolerant($text);
        if ($decoded === null) continue;

        $flattened = sitc_jsonld_flatten($decoded);
        foreach ($flattened as $node) {
            if (!is_array($node)) continue;
            // Consider Recipe by @type or by presence of key recipeIngredient/instructions
            $types = (array)($node['@type'] ?? []);
            $isRecipe = false;
            foreach ($types as $t) {
                if (is_string($t) && stripos($t, 'Recipe') !== false) { $isRecipe = true; break; }
            }
            if (!$isRecipe) {
                if (isset($node['recipeIngredient']) || isset($node['recipeInstructions'])) {
                    $isRecipe = true;
                }
            }
            if ($isRecipe) {
                $candidates[] = $node;
            }
        }
    }

    // If multiple candidates: pick best (most recipe fields)
    if (count($candidates) > 1) {
        usort($candidates, function($a, $b){
            $scoreA = sitc_recipe_field_score($a);
            $scoreB = sitc_recipe_field_score($b);
            return $scoreB <=> $scoreA;
        });
    }
    return $candidates;
}

function sitc_jsonld_decode_tolerant(string $text) {
    // Try as-is
    $data = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE) return $data;
    // Remove JavaScript-style comments
    $t = preg_replace('#/\*.*?\*/#s', '', $text);
    $t = preg_replace('#(^|\s)//.*$#m', '$1', $t);
    $data = json_decode($t, true);
    if (json_last_error() === JSON_ERROR_NONE) return $data;
    // Replace unescaped newlines in strings (very rough)
    $t = preg_replace("/(\"[^\"]*)\n([^\"]*\")/", '$1\\n$2', $t);
    $data = json_decode($t, true);
    if (json_last_error() === JSON_ERROR_NONE) return $data;
    return null;
}

function sitc_jsonld_flatten($decoded): array {
    $out = [];
    $stack = [$decoded];
    while ($stack) {
        $cur = array_pop($stack);
        if ($cur === null) continue;
        if (is_array($cur)) {
            // If has @graph, push its entries
            if (isset($cur['@graph']) && is_array($cur['@graph'])) {
                foreach ($cur['@graph'] as $g) $stack[] = $g;
            }
            // If associative array that looks like a node, include it
            $hasKeys = array_filter(array_keys($cur), 'is_string');
            if (!empty($hasKeys)) $out[] = $cur;
            // Also traverse values
            foreach ($cur as $v) $stack[] = $v;
        } elseif (is_object($cur)) {
            $arr = json_decode(json_encode($cur), true);
            $stack[] = $arr;
        } elseif (is_iterable($cur)) {
            foreach ($cur as $v) $stack[] = $v;
        }
    }
    return $out;
}

function sitc_recipe_field_score(array $node): int {
    $core = ['name','image','recipeIngredient','recipeInstructions','prepTime','cookTime','totalTime','recipeYield'];
    $score = 0;
    foreach ($core as $k) {
        if (!empty($node[$k])) $score++;
    }
    return $score;
}

function sitc_extract_microdata_recipe(DOMDocument $doc): array {
    $xp = new DOMXPath($doc);
    $nodes = $xp->query('//*[@itemscope and @itemtype]');
    $best = [];
    foreach ($nodes as $el) {
        $type = $el->getAttribute('itemtype');
        if ($type && preg_match('#schema\.org/Recipe$#i', $type)) {
            $candidate = sitc_parse_microdata_node($el);
            if (sitc_recipe_field_score($candidate) > sitc_recipe_field_score($best)) $best = $candidate;
        }
    }
    return $best;
}

function sitc_parse_microdata_node(DOMElement $root): array {
    $xp = new DOMXPath($root->ownerDocument);
    $ctx = $root;
    $value = function(string $prop) use ($xp, $ctx) {
        $n = $xp->query('.//*[@itemprop="'.$prop.'"]', $ctx);
        if (!$n || $n->length === 0) return null;
        $el = $n->item(0);
        if ($el->hasAttribute('content')) return trim($el->getAttribute('content'));
        if (strtolower($el->nodeName) === 'meta') return trim($el->getAttribute('content'));
        return trim(html_entity_decode($el->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    };
    $values = function(string $prop) use ($xp, $ctx) {
        $arr = [];
        foreach ($xp->query('.//*[@itemprop="'.$prop.'"]', $ctx) as $el) {
            if ($el->hasAttribute('content')) $arr[] = trim($el->getAttribute('content'));
            else $arr[] = trim(html_entity_decode($el->textContent ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return array_values(array_filter(array_map('trim', $arr), function($s){ return $s !== ''; }));
    };

    $recipe = [
        '@context' => 'https://schema.org',
        '@type'    => 'Recipe',
    ];
    $recipe['name'] = $value('name') ?? '';
    $recipe['description'] = $value('description') ?? '';
    $imgVals = $values('image');
    if (empty($imgVals)) {
        // Sometimes image is a nested ImageObject
        $imgNodes = $xp->query('.//*[@itemprop="image"]//*[@itemprop="url" or @itemprop="contentUrl"]', $ctx);
        foreach ($imgNodes as $ie) $imgVals[] = trim($ie->getAttribute('content')) ?: trim($ie->textContent);
    }
    if (!empty($imgVals)) $recipe['image'] = $imgVals;
    $auth = $value('author');
    if ($auth) $recipe['author'] = [['@type' => 'Person', 'name' => $auth]];
    foreach (['prepTime','cookTime','totalTime','recipeYield'] as $k) {
        $v = $value($k);
        if ($v) $recipe[$k] = $v;
    }
    $ings = $values('recipeIngredient');
    if (!empty($ings)) $recipe['recipeIngredient'] = $ings;
    $instr = $values('recipeInstructions');
    if (!empty($instr)) {
        // Could be plain strings
        $recipe['recipeInstructions'] = array_map(function($t){ return ['@type'=>'HowToStep','text'=>$t]; }, $instr);
    }
    // nutrition (basic)
    $nutrition = [];
    foreach (['calories','fatContent','carbohydrateContent','proteinContent'] as $p) {
        $nv = $value($p);
        if ($nv) $nutrition[$p] = $nv;
    }
    if ($nutrition) $recipe['nutrition'] = $nutrition;
    return $recipe;
}

function sitc_extract_dom_fallback(DOMDocument $doc, array $scopeSelectors, array $excludeSelectors, ?string $pageUrl): array {
    $xp = new DOMXPath($doc);
    // Build potential scopes
    $scopes = [];
    foreach ($scopeSelectors as $sel) {
        $nodes = sitc_query_selector($xp, $sel);
        foreach ($nodes as $n) $scopes[] = $n;
    }
    if (empty($scopes)) {
        // no explicit scope; use body
        $scopes[] = $xp->query('//body')->item(0) ?: $doc->documentElement;
    }
    // Prefer the largest scope (more text) that likely contains ingredients
    usort($scopes, function($a,$b){ return strlen($b->textContent ?? '') <=> strlen($a->textContent ?? ''); });

    $root = $scopes[0];
    sitc_strip_noise($root, $excludeSelectors);

    $recipe = [
        '@context' => 'https://schema.org',
        '@type'    => 'Recipe',
    ];

    // Title
    $titleNode = null;
    foreach (['.//h1','.//h2','.//header//h1','.//header//h2'] as $q) {
        $n = (new DOMXPath($doc))->query($q, $root)->item(0);
        if ($n) { $titleNode = $n; break; }
    }
    if ($titleNode) $recipe['name'] = trim($titleNode->textContent);

    // Description
    $descNode = (new DOMXPath($doc))->query('.//p', $root)->item(0);
    if ($descNode) $recipe['description'] = trim($descNode->textContent);

    // Image: prefer og:image
    $og = (new DOMXPath($doc))->query('//meta[@property="og:image" or @name="og:image"]')->item(0);
    if ($og) $recipe['image'] = [ sitc_make_absolute_url(trim($og->getAttribute('content')), $pageUrl) ];

    // Yield from any servings widget
    $servings = '';
    $servingNodes = (new DOMXPath($doc))->query('.//*[contains(translate(@class, "SERVINGS", "servings"), "servings") or contains(translate(text(), "SERVESPORTIONENPORTIONS", "servesportionenportions"), "serves") or contains(translate(text(), "SERVINGS", "servings"), "servings")]', $root);
    foreach ($servingNodes as $sn) {
        $t = trim($sn->textContent);
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $t, $mm)) { $servings = $t; break; }
    }
    if ($servings !== '') $recipe['recipeYield'] = $servings;

    // Ingredients (try itemprop list or class contains ingredient)
    $ingNodes = (new DOMXPath($doc))->query('.//*[contains(@class, "ingredient") or @itemprop="recipeIngredient"]', $root);
    $ings = [];
    $groups = [];
    $currentGroup = null;
    if ($ingNodes && $ingNodes->length > 0) {
        foreach ($ingNodes as $node) {
            $tag = strtolower($node->nodeName);
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
            if ($text === '') continue;
            // Heading-like nodes indicate group
            if (in_array($tag, ['h2','h3','h4','h5']) || preg_match('/^([A-Z├ä├û├£][A-Z├ä├û├£\s\-]{2,30}|.*?:)$/u', $text)) {
                $name = rtrim($text, ':');
                $currentGroup = $name;
                if (!isset($groups[$currentGroup])) $groups[$currentGroup] = [];
                continue;
            }
            // Ingredient line
            if (preg_match('#^(ul|ol|li|p|span|div)$#', $tag)) {
                // Avoid capturing nested headings
                if (preg_match('/(zutaten|ingredients)/i', $text)) {
                    // skip section labels
                    continue;
                }
                if ($currentGroup) {
                    $groups[$currentGroup][] = $text;
                } else {
                    $ings[] = $text;
                }
            }
        }
    } else {
        // Generic list items inside root
        foreach ((new DOMXPath($doc))->query('.//li', $root) as $li) {
            $text = trim(preg_replace('/\s+/', ' ', $li->textContent ?? ''));
            if ($text !== '') $ings[] = $text;
        }
    }
    if (!empty($ings)) $recipe['recipeIngredient'] = $ings;
    if (!empty($groups)) {
        $ig = [];
        foreach ($groups as $gName => $items) {
            if (!$items) continue;
            $ig[] = ['name'=>$gName,'items'=>array_values($items)];
        }
        if ($ig) $recipe['ingredientGroups'] = $ig;
    }

    // Instructions: prefer ordered list
    $steps = [];
    $ol = (new DOMXPath($doc))->query('.//ol', $root)->item(0);
    if ($ol) {
        foreach ((new DOMXPath($doc))->query('.//li', $ol) as $li) {
            $t = trim(preg_replace('/\s+/', ' ', $li->textContent ?? ''));
            if ($t) $steps[] = ['@type'=>'HowToStep','text'=>$t];
        }
    } else {
        foreach ((new DOMXPath($doc))->query('.//p', $root) as $p) {
            $t = trim(preg_replace('/\s+/', ' ', $p->textContent ?? ''));
            if ($t && preg_match('/^(Schritt|Step|\d+[\).:-])\s+/i', $t)) $steps[] = ['@type'=>'HowToStep','text'=>$t];
        }
    }
    if ($steps) $recipe['recipeInstructions'] = $steps;

    return $recipe;
}

function sitc_merge_with_priority(array $jsonldCandidates, array $micro, array $dom): array {
    $priority = ['jsonld','microdata','dom'];
    $sources = [];
    $merged = [ '@context'=>'https://schema.org', '@type'=>'Recipe' ];

    $pick = function($field) use (&$merged, &$sources, $jsonldCandidates, $micro, $dom) {
        $value = null; $srcs = [];
        // jsonld
        foreach ($jsonldCandidates as $cand) {
            if (isset($cand[$field]) && $cand[$field] !== '' && $cand[$field] !== []) { $value = $cand[$field]; $srcs[]='jsonld'; break; }
        }
        if ($value === null && isset($micro[$field]) && $micro[$field] !== '') { $value = $micro[$field]; $srcs[]='microdata'; }
        if ($value === null && isset($dom[$field]) && $dom[$field] !== '') { $value = $dom[$field]; $srcs[]='dom'; }
        if ($value !== null) { $merged[$field] = $value; $sources[$field] = $srcs; }
    };

    foreach (['name','alternateName','description','image','author','prepTime','cookTime','totalTime','recipeYield','recipeIngredient','recipeInstructions','nutrition','aggregateRating','ingredientGroups'] as $f) {
        $pick($f);
    }

    // url will be resolved during normalization via canonical
    return [$merged, $sources];
}

function sitc_normalize_recipe(array $recipe, DOMDocument $doc, ?string $pageUrl): array {
    // URL canonical
    $recipe['url'] = sitc_find_canonical_url($doc, $pageUrl);

    // Clean text fields
    foreach (['name','alternateName','description'] as $tf) {
        if (isset($recipe[$tf])) $recipe[$tf] = sitc_clean_text($recipe[$tf]);
    }

    // Images -> array of absolute URLs, dedupe
    $images = [];
    $og = (new DOMXPath($doc))->query('//meta[@property="og:image" or @name="og:image"]')->item(0);
    if (isset($recipe['image'])) {
        $imgs = $recipe['image'];
        if (!is_array($imgs)) $imgs = [$imgs];
        foreach ($imgs as $im) {
            if (is_array($im)) $u = $im['url'] ?? $im['@id'] ?? '';
            else $u = (string)$im;
            $u = sitc_make_absolute_url(trim($u), $pageUrl);
            if ($u) $images[] = $u;
        }
    }
    if ($og) {
        $u = sitc_make_absolute_url($og->getAttribute('content'), $pageUrl);
        if ($u) $images[] = $u;
    }
    $images = array_values(array_unique(array_filter($images)));
    if ($images) $recipe['image'] = $images; else unset($recipe['image']);

    // Author normalize -> array of { @type: Person, name }
    if (isset($recipe['author'])) {
        $authors = $recipe['author'];
        if (!is_array($authors) || isset($authors['name'])) $authors = [$authors];
        $norm = [];
        foreach ($authors as $a) {
            if (is_string($a)) $norm[] = ['@type'=>'Person','name'=>sitc_clean_text($a)];
            elseif (is_array($a)) {
                $nm = $a['name'] ?? (is_string($a['@id'] ?? null) ? basename($a['@id']) : null);
                if ($nm) $norm[] = ['@type'=>'Person','name'=>sitc_clean_text($nm)];
            }
        }
        if ($norm) $recipe['author'] = $norm; else unset($recipe['author']);
    }

    // Times normalize
    $recipe = sitc_normalize_times($recipe);

    // recipeYield + yieldNormalized
    $rawYield = isset($recipe['recipeYield']) ? (is_array($recipe['recipeYield']) ? reset($recipe['recipeYield']) : (string)$recipe['recipeYield']) : '';
    $recipe['recipeYield'] = $rawYield;
    $yieldParsed = sitc_parse_yield_normalized($rawYield);
    $recipe['yieldNormalized'] = $yieldParsed + ['raw'=>$rawYield];

    // Ingredients -> structured [{ raw, qty, unit, item, note? }]
    $ingInput = [];
    if (isset($recipe['recipeIngredient'])) {
        $ingInput = $recipe['recipeIngredient'];
        if (!is_array($ingInput)) $ingInput = [$ingInput];
    }
    $struct = [];
    foreach ($ingInput as $entry) {
        if (is_array($entry) && (isset($entry['raw']) || isset($entry['item']))) {
            // already structured from source
            $raw = isset($entry['raw']) ? (string)$entry['raw'] : trim((string)($entry['item'] ?? ''));
            $out = sitc_struct_from_line($raw);
            foreach ($out as $s) $struct[] = $s;
        } else {
            $raw = sitc_clean_text((string)$entry);
            if ($raw === '') continue;
            foreach (sitc_struct_from_line($raw) as $s) $struct[] = $s;
        }
    }
    // De-Dupe on display-like key: qty (single or low-high) + unit + item (canon)
    $seen = [];
    $dedup = [];
    foreach ($struct as $s) {
        $q = $s['qty'];
        if (is_array($q) && isset($q['low'],$q['high'])) { $qk = sprintf('%.3f-%.3f', (float)$q['low'], (float)$q['high']); }
        elseif ($q !== null) { $qk = sprintf('%.3f', (float)$q); } else { $qk = ''; }
        $uk = mb_strtolower(trim((string)($s['unit'] ?? '')), 'UTF-8');
        $nk = sitc_canon_key((string)($s['item'] ?? ''));
        $key = $qk.'|'.$uk.'|'.$nk;
        if ($key === '||') continue;
        if (!isset($seen[$key])) { $seen[$key] = true; $dedup[] = $s; }
    }
    $struct = $dedup;

    $recipe['recipeIngredient'] = $struct;
    $recipe['ingredientsParsed'] = $struct;

    // Instructions -> flatten
    if (isset($recipe['recipeInstructions'])) {
        $recipe['recipeInstructions'] = sitc_flatten_instructions($recipe['recipeInstructions']);
    }

    // Unicode NFC when possible
    foreach (['name','alternateName','description'] as $tf) {
        if (isset($recipe[$tf])) $recipe[$tf] = sitc_nfc($recipe[$tf]);
    }

    return $recipe;
}

function sitc_validate_and_flags(array $recipe): array {
    $flags = [ 'isPartial' => false, 'lowConfidence' => false ];
    $missingIngredients = empty($recipe['recipeIngredient']);
    $missingInstructions = empty($recipe['recipeInstructions']);
    if ($missingIngredients || $missingInstructions) $flags['isPartial'] = true;
    return $flags;
}

function sitc_score_recipe(array $recipe, array $sources): float {
    $weights = ['jsonld'=>0.95,'microdata'=>0.85,'dom'=>0.70];
    $core = ['name','image','recipeIngredient','recipeInstructions','times','yield'];
    $scores = [];
    // name
    if (!empty($recipe['name'])) { $scores[] = sitc_max_source_weight($sources['name'] ?? [], $weights); }
    // image
    if (!empty($recipe['image'])) { $scores[] = sitc_max_source_weight($sources['image'] ?? [], $weights); }
    // ingredients
    if (!empty($recipe['recipeIngredient'])) { $scores[] = sitc_max_source_weight($sources['recipeIngredient'] ?? [], $weights); }
    // instructions
    if (!empty($recipe['recipeInstructions'])) { $scores[] = sitc_max_source_weight($sources['recipeInstructions'] ?? [], $weights); }
    // times (any of prep/cook/total)
    $timeSources = array_merge($sources['prepTime'] ?? [], $sources['cookTime'] ?? [], $sources['totalTime'] ?? []);
    if (!empty($recipe['prepTime']) || !empty($recipe['cookTime']) || !empty($recipe['totalTime'])) {
        $scores[] = sitc_max_source_weight($timeSources, $weights);
    }
    // yield
    if (!empty($recipe['recipeYield'])) { $scores[] = sitc_max_source_weight($sources['recipeYield'] ?? [], $weights); }

    if (!$scores) return 0.0;
    return array_sum($scores) / count($scores);
}

function sitc_max_source_weight(array $srcs, array $weights): float {
    $max = 0.0;
    foreach ($srcs as $s) { $w = $weights[$s] ?? 0.0; if ($w > $max) $max = $w; }
    return $max;
}

// ============ Utility helpers ============

function sitc_find_canonical_url(DOMDocument $doc, ?string $pageUrl): string {
    $xp = new DOMXPath($doc);
    $lnk = $xp->query('//link[@rel="canonical"]')->item(0);
    if ($lnk) {
        $u = trim($lnk->getAttribute('href'));
        if ($u) return sitc_make_absolute_url($u, $pageUrl);
    }
    $og = $xp->query('//meta[@property="og:url" or @name="og:url"]')->item(0);
    if ($og) {
        $u = trim($og->getAttribute('content'));
        if ($u) return sitc_make_absolute_url($u, $pageUrl);
    }
    return (string)$pageUrl;
}

function sitc_normalize_times(array $recipe): array {
    $prep = isset($recipe['prepTime']) ? sitc_parse_duration($recipe['prepTime']) : null;
    $cook = isset($recipe['cookTime']) ? sitc_parse_duration($recipe['cookTime']) : null;
    $total = isset($recipe['totalTime']) ? sitc_parse_duration($recipe['totalTime']) : null;
    if ($prep) $recipe['prepTime'] = $prep;
    if ($cook) $recipe['cookTime'] = $cook;
    if ($total) $recipe['totalTime'] = $total;
    if (!$total && ($prep || $cook)) {
        $sum = sitc_duration_sum($prep, $cook);
        if ($sum) $recipe['totalTime'] = $sum;
    }
    return $recipe;
}

function sitc_parse_duration(string $txt): ?string {
    $t = trim($txt);
    if ($t === '') return null;
    // Already ISO-8601
    if (preg_match('/^P(T.*)$/', $t)) return $t;
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = mb_strtolower($t, 'UTF-8');
    // Replace common language markers to unify
    $map = [
        'stunden' => 'h','stunde'=>'h','std'=>'h','h'=>'h','hour'=>'h','hours'=>'h',
        'minuten' => 'm','minute'=>'m','min'=>'m','m'=>'m','minutes'=>'m','mins'=>'m',
        'sekunden'=>'s','sekunde'=>'s','sec'=>'s','s'=>'s','seconds'=>'s'
    ];
    // Normalize digits and fractions
    $t = strtr($t, ['┬¢'=>' 1/2','┬╝'=>' 1/4','┬¥'=>' 3/4']);
    // Extract hours/minutes/seconds from patterns like "1 h 40 m", "1 hour, 40 minutes", "1 std 15 min"
    $h=$m=$s=0.0;
    // Try explicit units
    if (preg_match_all('/(\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+)\s*(stunden|stunde|std|h|hour|hours|minuten|minute|min|m|minutes|mins|sekunden|sekunde|sec|s|seconds)/u', $t, $mm, PREG_SET_ORDER)) {
        foreach ($mm as $pair) {
            $val = sitc_to_float($pair[1]);
            $unit = $map[$pair[2]] ?? $pair[2];
            if ($unit === 'h') $h += $val; elseif ($unit === 'm') $m += $val; elseif ($unit === 's') $s += $val;
        }
    } else {
        // Fallback: numbers separated by colon "1:40" or plain minutes
        if (preg_match('/^(\d+)\s*:\s*(\d{1,2})$/', trim($txt), $mm2)) {
            $h = (float)$mm2[1]; $m = (float)$mm2[2];
        } elseif (preg_match('/(\d+(?:[.,]\d+)?)/', $t, $one)) {
            $m = sitc_to_float($one[1]);
        }
    }
    if ($h==0 && $m==0 && $s==0) return null;
    $iso = 'PT';
    if ($h>0) $iso .= (int)round($h).'H';
    if ($m>0) $iso .= (int)round($m).'M';
    if ($s>0) $iso .= (int)round($s).'S';
    return $iso;
}

function sitc_duration_sum(?string $isoA, ?string $isoB): ?string {
    if (!$isoA && !$isoB) return null;
    $parse = function($iso){
        if (!$iso) return [0,0,0];
        if (!preg_match('/PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?/', $iso, $m)) return [0,0,0];
        return [ (int)($m[1]??0), (int)($m[2]??0), (int)($m[3]??0) ];
    };
    [$h1,$m1,$s1] = $parse($isoA); [$h2,$m2,$s2] = $parse($isoB);
    $h=$h1+$h2; $m=$m1+$m2; $s=$s1+$s2;
    $m += intdiv($s,60); $s = $s % 60;
    $h += intdiv($m,60); $m = $m % 60;
    $iso='PT'; if ($h>0) $iso.=$h.'H'; if ($m>0) $iso.=$m.'M'; if ($s>0) $iso.=$s.'S';
    return $iso;
}

function sitc_parse_yield_normalized(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return ['count'=>null,'unit'=>null];
    // Extract number possibly range "2-3"
    $cnt = null;
    if (preg_match('/(\d+(?:[.,]\d+)?)(?:\s*[-ÔÇô]\s*(\d+(?:[.,]\d+)?))?/u', $raw, $m)) {
        $cnt = sitc_to_float($m[1]);
    }
    $unit = null;
    $map = [ 'portionen'=>'portionen','portion'=>'portion','servings'=>'servings','serves'=>'serves','personen'=>'person','person'=>'person' ];
    $low = mb_strtolower($raw, 'UTF-8');
    foreach ($map as $k=>$v) { if (strpos($low, $k)!==false) { $unit=$v; break; } }
    return ['count'=>$cnt,'unit'=>$unit];
}

function sitc_flatten_instructions($instr): array {
    $out = [];
    if (is_array($instr)) {
        foreach ($instr as $step) {
            if (is_array($step)) {
                $type = $step['@type'] ?? '';
                if (strcasecmp($type,'HowToSection')===0 && !empty($step['itemListElement'])) {
                    $section = sitc_clean_text($step['name'] ?? '');
                    foreach ($step['itemListElement'] as $sub) {
                        if (is_string($sub)) { $out[] = ['@type'=>'HowToStep','text'=>sitc_clean_text($sub),'section'=>$section ?: null]; }
                        elseif (is_array($sub)) {
                            $text = $sub['text'] ?? $sub['name'] ?? '';
                            $text = sitc_clean_text($text);
                            if ($text!=='') { $st=['@type'=>'HowToStep','text'=>$text]; if ($section) $st['section']=$section; $out[]=$st; }
                        }
                    }
                } else {
                    $text = $step['text'] ?? $step['name'] ?? '';
                    $text = sitc_clean_text($text);
                    if ($text!=='') $out[] = ['@type'=>'HowToStep','text'=>$text];
                }
            } elseif (is_string($step)) {
                $t = sitc_clean_text($step);
                if ($t!=='') $out[] = ['@type'=>'HowToStep','text'=>$t];
            }
        }
    } elseif (is_string($instr)) {
        $parts = preg_split('/\r?\n|\.\s+(?=[A-Z├ä├û├£])/u', $instr);
        if ($parts === false) { trigger_error('Regex failed @sitc_flatten_instructions preg_split', E_USER_WARNING); $parts = [$instr]; }
        if ($parts === false) { trigger_error('Regex failed @sitc_flatten_instructions preg_split', E_USER_WARNING); $parts = [$instr]; }
        foreach ($parts as $p) { $t = sitc_clean_text($p); if ($t!=='') $out[] = ['@type'=>'HowToStep','text'=>$t]; }
    }
    return $out;
}

function sitc_strip_noise(DOMNode $root, array $excludeSelectors): void {
    $xp = new DOMXPath($root->ownerDocument);
    foreach ($excludeSelectors as $sel) {
        $nodes = sitc_query_selector($xp, $sel, $root);
        foreach ($nodes as $n) { if ($n->parentNode) $n->parentNode->removeChild($n); }
    }
}

function sitc_query_selector(DOMXPath $xp, string $selector, ?DOMNode $context=null): DOMNodeList {
    // Support simple .class and [attr] selectors
    $selector = trim($selector);
    if ($selector === '') return $xp->query('//*[false()]', $context);
    if ($selector[0] === '.') {
        $cls = substr($selector,1);
        $xpath = '//*[contains(concat(" ", normalize-space(@class), " "), " '.addslashes($cls).' ")]';
    } elseif (preg_match('/^\[(.+)\]$/', $selector, $m)) {
        $attr = $m[1];
        $xpath = '//*[@'.addslashes($attr).']';
    } else {
        // tag
        $xpath = '//'.addslashes($selector);
    }
    return $xp->query($xpath, $context);
}

// Global text sanitization for all incoming strings
// - HTML entities decode
// - Strip tags, collapse whitespace
// - Normalize to UTF-8 NFC
// - Common UTF-8 mojibake fixes
function sitc_text_sanitize($val): string {
    $s = is_string($val) ? $val : json_encode($val);
    // 1) Decode entities first
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // 2) NFC normalize early to stabilize sequences
    $s = sitc_nfc($s);
    // 3) Mojibake fixes (common pairs)
    $map = [
        'StÃ¼ck' => 'Stück', 'Ã¼' => 'ü', 'Ã¶' => 'ö', 'Ã¤' => 'ä', 'ÃŸ' => 'ß',
        'Ã„' => 'Ä', 'Ã–' => 'Ö', 'Ãœ' => 'Ü',
        'â€“' => '–', 'â€”' => '—', 'â€ž' => '„', 'â€œ' => '“', 'â€˜' => '‚', 'â€™' => '’', 'â€¦' => '…'
    ];
    $s = strtr($s, $map);
    // 4) Strip tags and collapse whitespace
    $s = strip_tags($s);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = trim($s);
    // 5) Final NFC
    $s = sitc_nfc($s);
    return $s;
}

// ===== Quantities + struct helpers (parser-scoped) =====

function sitc_unicode_fraction_decimal_map(): array {
    return [
        '¼' => 0.25,
        '½' => 0.5,
        '¾' => 0.75,
        '⅓' => 1/3,
        '⅔' => 2/3,
        '⅛' => 0.125,
    ];
}

if (!function_exists('sitc_qty_pre_normalize_parser')) {
function sitc_qty_pre_normalize_parser(string $s): string {
    $t = trim($s);
    if ($t === '') return '';
    // NBSP / NNBSP to space
    $t = preg_replace('/[\x{00A0}\x{202F}]/u', ' ', $t);
    // drop leading stopwords and (ca.) suffix
    $t = preg_replace('/^(ca\.?|circa|etwa|ungef\.?|ungef(?:ae|ä)hr\.?|about|approx\.?|approximately)\s+/iu', '', $t);
    $t = preg_replace('/\((?:ca\.?|circa|about|approx\.?)\)\s*$/iu', '', $t);
    // unify range separators
    $t = preg_replace('/[\x{2012}-\x{2015}]/u', '-', $t);
    // tighten slash
    $t = preg_replace('/\s*\/\s*/u', '/', $t);
    // unicode fractions to decimals or mixed numbers
    $map = sitc_unicode_fraction_decimal_map();
    // mixed number: 1½ -> 1 + 0.5
    $t = preg_replace_callback('/(\d)\s*(['.preg_quote(implode('', array_keys($map)), '/').'])/u', function($m) use ($map){
        $dec = $map[$m[2]] ?? null; if ($dec === null) return $m[0];
        $sum = (float)$m[1] + (float)$dec;
        return str_replace('.', ',', (string)$sum); // favor comma within text
    }, $t);
    // standalone unicode fraction -> decimal string
    $t = preg_replace_callback('/(['.preg_quote(implode('', array_keys($map)), '/').'])/u', function($m) use ($map){
        $dec = $map[$m[1]] ?? null; return $dec !== null ? str_replace('.', ',', (string)$dec) : $m[1];
    }, $t);
    return $t;
}
}

function sitc_qty_to_float(string $num): ?float {
    $n = trim($num);
    if ($n === '') return null;
    // mixed number: 1 1/2
    if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $n, $m)) return (float)$m[1] + ((float)$m[2]/max(1,(float)$m[3]));
    // fraction a/b
    if (preg_match('/^(\d+)\/(\d+)$/', $n, $m)) return ((float)$m[1])/max(1,(float)$m[2]);
    // decimal with comma or dot
    if (preg_match('/^\d+(?:[\.,]\d+)?$/', $n)) return (float)str_replace(',', '.', $n);
    return null;
}

if (!function_exists('sitc_parse_qty_or_range_parser')) {
function sitc_parse_qty_or_range_parser(string $s) {
    $t = sitc_qty_pre_normalize_parser($s);
    // range a–b (minus or unicode dashes)
    if (preg_match('/^(\S+)\h*[\-\x{2012}-\x{2015}]\h*(\S+)$/u', $t, $m)) {
        $a = sitc_qty_from_token($m[1]);
        $b = sitc_qty_from_token($m[2]);
        if ($a !== null && $b !== null) return ['low'=>(float)$a,'high'=>(float)$b];
    }
    $v = sitc_qty_from_token($t);
    if ($v !== null) return (float)$v;
    return null;
}
}

if (!function_exists('sitc_unit_alias_canonical')) {
function sitc_unit_alias_canonical(string $u): ?string {
    $m = [
        'g'=>'g','gram'=>'g','grams'=>'g','gramm'=>'g',
        'kg'=>'kg',
        'ml'=>'ml','milliliter'=>'ml','millilitre'=>'ml',
        'l'=>'l','liter'=>'l','litre'=>'l',
        'tl'=>'tsp','teeloeffel'=>'tsp','teelöffel'=>'tsp','tsp'=>'tsp','teaspoon'=>'tsp','teaspoons'=>'tsp',
        'el'=>'tbsp','essloeffel'=>'tbsp','esslöffel'=>'tbsp','tbsp'=>'tbsp','tablespoon'=>'tbsp','tablespoons'=>'tbsp',
        'tasse'=>'cup','cup'=>'cup','cups'=>'cup',
        'prise'=>'pinch','pinch'=>'pinch',
        'stueck'=>'piece','stück'=>'piece','piece'=>'piece','pieces'=>'piece',
        'bund'=>'bunch','bunch'=>'bunch',
        'zehe'=>'clove','zehen'=>'clove','clove'=>'clove','cloves'=>'clove'
    ];
    $k = mb_strtolower(trim($u), 'UTF-8');
    if ($k === 'stk' || $k === 'stück') return 'piece';
    return $m[$k] ?? null;
}
}

if (!function_exists('sitc_struct_from_line')) {
function sitc_struct_from_line(string $rawLine): array {
    $out = [];
    $pn = sitc_qty_pre_normalize_parser($rawLine);
    // split into parts by multiple qty tokens or common separators
    $parts = preg_split('/\s*[•;\u00B7]\s*|\s{2,}/u', $pn);
    if ($parts === false) { trigger_error('Regex failed @sitc_struct_from_line preg_split', E_USER_WARNING); $parts = [$pn]; }
    $parts = array_values(array_filter(array_map('trim',$parts), fn($s)=>$s!==''));
    if (!$parts) $parts = [$pn];
    foreach ($parts as $part) {
        // If more than one qty token in part, split before subsequent ones
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
                if ($u !== '') { $unitField = sitc_unit_alias_canonical($u); }
                $rest = trim((string)($m[3] ?? ''));
                // Leading TK as note
                if (preg_match('/^TK\b/u', $rest)) { $noteField = 'TK'; $rest = trim(preg_replace('/^TK\b\s*/u','',$rest)); }
                // Parentheses note
                if (preg_match('/^(.*)\(([^\)]+)\)\s*$/u', $rest, $n)) { $itemField = trim($n[1]); $noteField = trim(($noteField? $noteField.'; ':'').$n[2]); }
                else $itemField = $rest;
                // Trailing comma note
                if (strpos($itemField, ',') !== false) {
                    $parts2 = array_map('trim', explode(',', $itemField, 2));
                    if (count($parts2)===2) { $itemField = $parts2[0]; $noteField = trim(($noteField? $noteField.'; ':'').$parts2[1]); }
                }
                // Hyphen item-note like "Ingwer-Stück"
                if ($noteField===null && preg_match('/^(.+?)\s*[-–]\s*(St(ue|ü)ck|gehackt|rot)\b/u', $itemField, $h)) {
                    $itemField = trim($h[1]); $noteField = $h[3]=='Stück' || $h[3]=='Stueck' ? 'Stück' : $h[3];
                }
                // Heuristic: Knoblauchzehen -> unit clove + item Knoblauch
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

// Backward-compat: existing code calls sitc_clean_text; delegate to sanitize
function sitc_clean_text($val): string {
    return sitc_text_sanitize($val);
}

// Build a canonical key for de-duplication: trim, collapse whitespace, lower-case, remove diacritics
function sitc_canon_key(string $s): string {
    $t = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace('/\s+/u', ' ', $t);
    $t = trim($t);
    if ($t === '') return '';
    $t = mb_strtolower($t, 'UTF-8');
    // Remove diacritics via NFD + strip combining marks
    if (function_exists('normalizer_normalize')) {
        $nfd = normalizer_normalize($t, Normalizer::FORM_D);
        if ($nfd !== false) $t = preg_replace('/\p{Mn}+/u', '', $nfd);
    }
    return $t;
}

function sitc_make_absolute_url(?string $url, ?string $base): string {
    $u = trim((string)$url);
    if ($u === '') return '';
    if (preg_match('#^https?://#i', $u)) return $u;
    if (!$base || !preg_match('#^(https?://[^/]+)(/.*)?$#i', $base, $m)) return $u;
    $origin = rtrim($m[1], '/');
    if ($u[0] === '/') return $origin.$u;
    // relative path
    $path = isset($m[2]) ? $m[2] : '/';
    $dir = preg_replace('#/[^/]*$#', '/', $path);
    return $origin . $dir . $u;
}

function sitc_nfc(string $s): string {
    if (function_exists('normalizer_normalize')) {
        $n = normalizer_normalize($s, Normalizer::FORM_C);
        if ($n !== false) return $n;
    }
    return $s;
}

function sitc_to_float(string $num): float {
    $num = trim($num);
    if (preg_match('/^(\d+)\s*\/\s*(\d+)$/', $num, $m)) {
        return ((float)$m[1]) / max(1.0,(float)$m[2]);
    }
    return (float)str_replace(',', '.', $num);
}

// PCRE2-safe split helper: never returns false
if (!function_exists('sitc_pcre2_split')) {
function sitc_pcre2_split(string $pattern, string $s): array {
    $out = @preg_split($pattern, $s, -1, PREG_SPLIT_NO_EMPTY);
    return is_array($out) ? $out : [$s];
}
}

// Parse quantity token to float, handling unicode fractions, mixed numbers, and decimal comma
if (!function_exists('sitc_qty_from_token')) {
function sitc_qty_from_token(string $t): ?float {
    $s = trim($t);
    if ($s === '') return null;
    // normalize decimal comma and slash spacing
    $s = str_replace(',', '.', $s);
    $s = preg_replace('/\s*\/\s*/u', '/', $s);
    if ($s === null) $s = $t;
    // map unicode fractions
    $map = [
        "½"=>0.5, "¼"=>0.25, "¾"=>0.75, "⅓"=>0.3333, "⅔"=>0.6667, "⅛"=>0.125,
    ];
    // mixed number like 1½
    if (preg_match('/^([0-9]+)\s*([½¼¾⅓⅔⅛])$/u', $s, $m)) {
        $base = (float)$m[1]; $frac = $map[$m[2]] ?? 0.0; return $base + (float)$frac;
    }
    // mixed number like 1 1/2
    if (preg_match('/^([0-9]+)\s+([0-9]+)\/([0-9]+)$/u', $s, $m)) {
        $a=(float)$m[1]; $b=(float)$m[2]; $c=max(1.0,(float)$m[3]); return $a + ($b/$c);
    }
    // simple unicode fraction
    if (preg_match('/^([½¼¾⅓⅔⅛])$/u', $s, $m)) { return (float)($map[$m[1]] ?? 0.0); }
    // simple a/b
    if (preg_match('/^([0-9]+)\/([0-9]+)$/u', $s, $m)) { return (float)$m[1] / max(1.0,(float)$m[2]); }
    // plain decimal
    if (preg_match('/^[0-9]+(?:\.[0-9]+)?$/u', $s)) { return (float)$s; }
    return null;
}
}

// Ingredient line parser v2 -> { qty:?float, unit:?string, item:string, note:?string, raw:string }
function sitc_parse_ingredient_line_v2(string $raw): array {
    $raw = sitc_clean_text($raw);
    // Additional normalization for unicode fractions and spaces
    if (function_exists('sitc_qty_pre_normalize')) { $raw = sitc_qty_pre_normalize($raw); }
    // map unicode fractions
    $raw = strtr($raw, ['┬¢'=>' 1/2','┬╝'=>' 1/4','┬¥'=>' 3/4','Ôàô'=>' 1/3','Ôàö'=>' 2/3']);
    // Units mapping de<->en
    $aliases = [
        'tl'=>'tsp','teel├Âffel'=>'tsp','teeloeffel'=>'tsp','tsp'=>'tsp',
        'el'=>'tbsp','essl├Âffel'=>'tbsp','essloeffel'=>'tbsp','tbsp'=>'tbsp',
        'tasse'=>'cup','cup'=>'cup','prise'=>'pinch','pinch'=>'pinch',
        'st├╝ck'=>'piece','stueck'=>'piece','piece'=>'piece','dose'=>'can','can'=>'can',
        'bund'=>'bunch','bunch'=>'bunch','g'=>'g','gramm'=>'g','kg'=>'kg','ml'=>'ml','l'=>'l','liter'=>'l',
        'oz'=>'oz','lb'=>'lb'
    ];
    // Pattern: qty unit item (note)
    $qty=null; $unit=null; $item=''; $note=null;
    $m = [];
    if (preg_match('/^\s*(\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+|\d+\s*[ÔÇô-]\s*\d+)\s*(\p{L}+)?\s*(.*)$/u', $raw, $m)) {
        $qraw = $m[1];
        if (preg_match('/(\d+)\s*[ÔÇô-]\s*(\d+)/', $qraw, $r)) $qraw = $r[1]; // take lower bound for ranges
        $qty = sitc_to_float($qraw);
        $u = mb_strtolower(trim($m[2] ?? ''), 'UTF-8');
        if ($u !== '') $unit = $aliases[$u] ?? $u;
        $rest = trim($m[3] ?? '');
        if (preg_match('/^(.*)\(([^\)]+)\)\s*$/', $rest, $n)) { $item = trim($n[1]); $note = trim($n[2]); }
        else $item = $rest;
    } else {
        $item = $raw;
    }
    return [ 'qty'=>$qty, 'unit'=>$unit, 'item'=>$item, 'note'=>$note, 'raw'=>$raw ];
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
        '┬¢'=>'1/2','Ôàô'=>'1/3','Ôàö'=>'2/3',
        '┬╝'=>'1/4','┬¥'=>'3/4',
        'Ôàø'=>'1/8','Ôà£'=>'3/8','ÔàØ'=>'5/8','Ôà×'=>'7/8'
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
 * Zutatenzeile parsen ÔåÆ [qty, unit, name]
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
 * Anleitungen parsen ÔÇô akzeptiert Text, Array, verschachtelt
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
        $parts = preg_split('/\r?\n|\.\s+(?=[A-Z├ä├û├£])/u', $input);
        if ($parts === false) { trigger_error('Regex failed @sitc_parse_instructions preg_split', E_USER_WARNING); $parts = [$input]; }
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p) $steps[] = $p;
        }
    }

    return $steps;
}

// --- Enhanced ingredient line parser (v3) ---
// Robust f├╝r Br├╝che/Dezimal, Bereiche a-b, gemischte Zahlen, Freitext-Heuristik ("Saft einer halben Zitrone")
if (!function_exists('sitc_parse_ingredient_line_v3')) {
function sitc_parse_ingredient_line_v3(string $raw): array {
    $orig = $raw;
    $raw = sitc_clean_text($raw);
    $raw = strtr($raw, [
        '┬¢'=>' 1/2','┬╝'=>' 1/4','┬¥'=>' 3/4','Ôàô'=>' 1/3','Ôàö'=>' 2/3',
        'Ôàø'=>' 1/8','Ôà£'=>' 3/8','ÔàØ'=>' 5/8','Ôà×'=>' 7/8',
        'ÔÇô'=>'-','ÔÇö'=>'-'
    ]);
    $raw = preg_replace('/\s*\/\s*/', '/', $raw);

    $aliases = [
        'tl'=>'tsp','teel├Âffel'=>'tsp','teeloeffel'=>'tsp','tsp'=>'tsp',
        'el'=>'tbsp','essl├Âffel'=>'tbsp','essloeffel'=>'tbsp','tbsp'=>'tbsp',
        'tasse'=>'cup','cup'=>'cup','prise'=>'pinch','pinch'=>'pinch',
        'st├╝ck'=>'piece','stueck'=>'piece','piece'=>'piece','dose'=>'can','can'=>'can',
        'bund'=>'bunch','bunch'=>'bunch','g'=>'g','gramm'=>'g','kg'=>'kg','ml'=>'ml','l'=>'l','liter'=>'l',
        'oz'=>'oz','lb'=>'lb'
    ];

    $toFloat = function(string $t){
        $t = trim($t);
        $t = preg_replace('/\s*\/\s*/', '/', $t);
        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $t, $m)) return (float)$m[1] + ((float)$m[2]/max(1,(float)$m[3]));
        if (preg_match('/^(\d+)\/(\d+)$/', $t, $m)) return ((float)$m[1])/max(1,(float)$m[2]);
        if (preg_match('/^\d+(?:[\.,]\d+)?$/', $t)) return (float)str_replace(',', '.', $t);
        return null;
    };

    $qty=null; $unit=null; $item=''; $note=null;

    // Bereich a-b
    if (preg_match('/^\s*([^\s]+)\s*-\s*([^\s]+)\s*(\p{L}+)?\s*(.*)$/u', $raw, $m)) {
        $a = $toFloat($m[1]); $b=$toFloat($m[2]);
        if ($a !== null && $b !== null) {
            $qty = str_replace(',', '.', (string)$a) . '-' . str_replace(',', '.', (string)$b);
            $u = mb_strtolower(trim($m[3] ?? ''), 'UTF-8');
            if ($u !== '') {
                $unit = $aliases[$u] ?? $u;
            }
            $rest = trim($m[4] ?? '');
            if (preg_match('/^(.*)\(([^\)]+)\)\s*$/', $rest, $n)) { $item = trim($n[1]); $note = trim($n[2]); }
            else $item = $rest;
            return ['qty'=>$qty,'unit'=>$unit,'item'=>$item,'note'=>$note,'raw'=>$orig];
        }
    }

    // Einzelzahl
    if (preg_match('/^\s*([\d\.,\/\s]+)\s*(\p{L}+)?\s*(.*)$/u', $raw, $m)) {
        $qv = $toFloat(trim($m[1]));
        if ($qv !== null) {
            $qty = str_replace(',', '.', (string)$qv);
            $u = mb_strtolower(trim($m[2] ?? ''), 'UTF-8');
            if ($u !== '') {
                $unit = $aliases[$u] ?? $u;
            }
            $rest = trim($m[3] ?? '');
            if (preg_match('/^(.*)\(([^\)]+)\)\s*$/', $rest, $n)) { $item = trim($n[1]); $note = trim($n[2]); }
            else $item = $rest;
            return ['qty'=>$qty,'unit'=>$unit,'item'=>$item,'note'=>$note,'raw'=>$orig];
        }
    }

    // Freitext-Heuristik
    if (preg_match('/\b(saft|schale|abrieb|zeste)\b.*\b(einer|einem|eines)?\s*(halben|1\/2|┬¢)\s+(zitrone|limette|orange)\b/i', $raw, $m)) {
        $note = ucfirst(mb_strtolower($m[1], 'UTF-8'));
        $item = ucfirst(mb_strtolower($m[4], 'UTF-8'));
        $qty = '0.5';
        $unit = 'piece';
        return ['qty'=>$qty,'unit'=>$unit,'item'=>$item,'note'=>$note,'raw'=>$orig];
    }

    return ['qty'=>null,'unit'=>null,'item'=>$orig,'note'=>null,'raw'=>$orig,'ambiguous'=>true];
}
}


