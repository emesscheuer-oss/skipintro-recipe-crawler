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
        error_log("SITC Parser: Fehler beim Abruf von $url – " . $response->get_error_message());
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
            $ingredients_struct[] = [
                'qty'  => $p['qty'] ?? '',
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
            if (in_array($tag, ['h2','h3','h4','h5']) || preg_match('/^([A-ZÄÖÜ][A-ZÄÖÜ\s\-]{2,30}|.*?:)$/u', $text)) {
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

    // Ingredients + groups + parsed
    $ingList = [];
    if (isset($recipe['recipeIngredient'])) {
        $ingList = $recipe['recipeIngredient'];
        if (!is_array($ingList)) $ingList = [$ingList];
        $ingList = array_values(array_filter(array_map(function($s){ return sitc_clean_text((string)$s); }, $ingList), function($s){ return $s !== ''; }));
    }
    $recipe['recipeIngredient'] = $ingList;

    // Create ingredientsParsed
    $parsed = [];
    foreach ($ingList as $raw) {
        $parsed[] = sitc_parse_ingredient_line_v2($raw);
    }
    $recipe['ingredientsParsed'] = $parsed;

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
    $t = strtr($t, ['½'=>' 1/2','¼'=>' 1/4','¾'=>' 3/4']);
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
    if (preg_match('/(\d+(?:[.,]\d+)?)(?:\s*[-–]\s*(\d+(?:[.,]\d+)?))?/u', $raw, $m)) {
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
        $parts = preg_split('/\r?\n|\.\s+(?=[A-ZÄÖÜ])/u', $instr);
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

function sitc_clean_text($val): string {
    $s = is_string($val) ? $val : json_encode($val);
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return sitc_nfc($s);
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

// Ingredient line parser v2 -> { qty:?float, unit:?string, item:string, note:?string, raw:string }
function sitc_parse_ingredient_line_v2(string $raw): array {
    $raw = sitc_clean_text($raw);
    // map unicode fractions
    $raw = strtr($raw, ['½'=>' 1/2','¼'=>' 1/4','¾'=>' 3/4','⅓'=>' 1/3','⅔'=>' 2/3']);
    // Units mapping de<->en
    $aliases = [
        'tl'=>'tsp','teelöffel'=>'tsp','teeloeffel'=>'tsp','tsp'=>'tsp',
        'el'=>'tbsp','esslöffel'=>'tbsp','essloeffel'=>'tbsp','tbsp'=>'tbsp',
        'tasse'=>'cup','cup'=>'cup','prise'=>'pinch','pinch'=>'pinch',
        'stück'=>'piece','stueck'=>'piece','piece'=>'piece','dose'=>'can','can'=>'can',
        'bund'=>'bunch','bunch'=>'bunch','g'=>'g','gramm'=>'g','kg'=>'kg','ml'=>'ml','l'=>'l','liter'=>'l',
        'oz'=>'oz','lb'=>'lb'
    ];
    // Pattern: qty unit item (note)
    $qty=null; $unit=null; $item=''; $note=null;
    $m = [];
    if (preg_match('/^\s*(\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+|\d+\s*[–-]\s*\d+)\s*(\p{L}+)?\s*(.*)$/u', $raw, $m)) {
        $qraw = $m[1];
        if (preg_match('/(\d+)\s*[–-]\s*(\d+)/', $qraw, $r)) $qraw = $r[1]; // take lower bound for ranges
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
