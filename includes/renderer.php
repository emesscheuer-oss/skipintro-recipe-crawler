<?php
if (!defined('ABSPATH')) exit;

// Load modular renderer parts
require_once __DIR__ . '/renderer/helpers.php';
require_once __DIR__ . '/renderer/helpers_extra.php';
require_once __DIR__ . '/renderer/header.php';
require_once __DIR__ . '/renderer/ingredients.php';
require_once __DIR__ . '/renderer/instructions.php';
require_once __DIR__ . '/renderer/dev_badge.php';

function sitc_render_recipe(array $data): string {
    $post_id      = (int)($data['post_id'] ?? 0);
    $ingredients  = (array)($data['ingredients'] ?? []);
    $instructions = (array)($data['instructions'] ?? []);
    $yield_num    = (int)($data['yield_num'] ?? 2);
    $yield_raw    = (string)($data['yield_raw'] ?? '');
    $use_fallback = (bool)($data['use_fallback'] ?? false);
    $trash_token  = (string)($data['trash_token'] ?? '');
    $show_original = (bool)($data['show_original'] ?? false);
    $source_url    = (string)($data['source_url'] ?? '');
    $groupsForRender = isset($data['groupsForRender']) ? (array)$data['groupsForRender'] : [];
    $rawLines = isset($data['rawLines']) ? (array)$data['rawLines'] : [];
    $parser_ver = isset($data['parser_ver']) ? (string)$data['parser_ver'] : null;
    $renderer_ver = isset($data['renderer_ver']) ? (string)$data['renderer_ver'] : null;

    $html = '';

    $html .= '<div class="sitc-recipe" data-post="' . esc_attr((string)(int)$post_id) . '">';
    $html .= "\n";

    $html .= sitc_render_header([
        'post_id'      => $post_id,
        'yield_num'    => $yield_num,
        'yield_raw'    => $yield_raw,
        'show_original'=> $show_original,
        'use_fallback' => $use_fallback,
        'trash_token'  => $trash_token,
        'parser_ver'   => $parser_ver,
        'renderer_ver' => $renderer_ver,
    ]);
    $html .= "\n";

    $html .= sitc_render_dev_badge([
        'post_id'     => $post_id,
        'ingredients' => $ingredients,
    ]);
    $html .= "\n";

    $html .= sitc_render_ingredients([
        'post_id'          => $post_id,
        'yield_num'        => $yield_num,
        'ingredients'      => $ingredients,
        'groupsForRender'  => $groupsForRender,
        'rawLines'         => $rawLines,
        'use_fallback'     => $use_fallback,
    ]);
    $html .= "\n";

    $html .= sitc_render_instructions([
        'instructions' => $instructions,
    ]);

    if (!empty($source_url)) {
        $html .= "\n";
        $html .= '<p class="sitc-source">Quelle: <a href="' . esc_url((string)$source_url) . '" target="_blank" rel="nofollow noopener">Originalrezept ansehen</a></p>';
    }

    $html .= "\n";
    $html .= '</div>';

    return $html;
}
// Backwards-compatible entry used by content filter
function sitc_render_recipe_shortcode($post_id = 0) {
    $post_id = $post_id ? (int)$post_id : get_the_ID();
    if (!$post_id) return '';

    // Soft invalidation: if stored ingredient parser version differs, try to reparse and refresh metas once
    try {
        $storedIngVer = get_post_meta($post_id, '_sitc_ing_parser_version', true);
        $curIngVer = function_exists('sitc_get_ing_parser_version') ? sitc_get_ing_parser_version() : '';
        if ($curIngVer !== '' && (string)$storedIngVer !== (string)$curIngVer) {
            $srcUrl = get_post_meta($post_id, '_sitc_source_url', true);
            $srcUrl = is_string($srcUrl) ? trim($srcUrl) : '';
            if ($srcUrl !== '' && function_exists('sitc_parse_recipe_from_url_v2')) {
                $re = sitc_parse_recipe_from_url_v2($srcUrl);
                if (is_array($re)) {
                    if (!empty($re['ingredients_struct'])) update_post_meta($post_id, '_sitc_ingredients_struct', $re['ingredients_struct']);
                    if (!empty($re['instructions']))       update_post_meta($post_id, '_sitc_instructions', $re['instructions']);
                    if (isset($re['yield_raw']))           update_post_meta($post_id, '_sitc_yield_raw', $re['yield_raw']);
                    if (isset($re['yield_num']))           update_post_meta($post_id, '_sitc_yield_num', $re['yield_num']);
                    if (!empty($re['meta'])) {
                        $m = $re['meta'];
                        if (!empty($m['schema_recipe'])) update_post_meta($post_id, '_sitc_schema_recipe_json', wp_json_encode($m['schema_recipe']));
                        if (array_key_exists('confidence', $m)) update_post_meta($post_id, '_sitc_confidence', (float)$m['confidence']);
                        if (!empty($m['sources']))        update_post_meta($post_id, '_sitc_sources_json', wp_json_encode($m['sources']));
                        if (!empty($m['flags']))          update_post_meta($post_id, '_sitc_flags_json', wp_json_encode($m['flags']));
                    }
                    update_post_meta($post_id, '_sitc_ing_parser_version', $curIngVer);
                }
            }
        }
    } catch (Throwable $e) { /* never break render; soft-invalidation best-effort */ }

    // Gather data from post meta
    $ingredients  = sitc_normalize_array_meta(get_post_meta($post_id, '_sitc_ingredients_struct', true));
    $instructions = sitc_normalize_array_meta(get_post_meta($post_id, '_sitc_instructions', true));

    $yield_num = get_post_meta($post_id, '_sitc_yield_num', true);
    if ($yield_num === '' || $yield_num === null) {
        $maybe_raw = get_post_meta($post_id, '_sitc_yield', true);
        if (is_numeric($maybe_raw)) $yield_num = $maybe_raw;
    }
    $yield_num = sitc_normalize_scalar_number($yield_num, 2);

    $yield_raw = get_post_meta($post_id, '_sitc_yield_raw', true);
    if ($yield_raw === '' || $yield_raw === null) {
        $legacy = get_post_meta($post_id, '_sitc_yield', true);
        if (!empty($legacy)) $yield_raw = $legacy;
    }

    $source_url = get_post_meta($post_id, '_sitc_source_url', true);

    // Safe Mode / Flags to decide on fallback rendering
    $use_fallback = false;
    if (function_exists('sitc_is_safe_mode') && sitc_is_safe_mode()) {
        $use_fallback = true;
    }

    // Trash token for delete button
    $trash_token = get_post_meta($post_id, '_sitc_trash_token', true);
    if (!$trash_token) {
        $trash_token = wp_generate_password(20, false, false);
        update_post_meta($post_id, '_sitc_trash_token', $trash_token);
    }

    // Show original yield if raw differs
    $show_original = false;
    if (!empty($yield_raw)) {
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', (string)$yield_raw, $m)) {
            $raw_num = (float)str_replace(',', '.', $m[1]);
            if ((float)$yield_num != $raw_num) $show_original = true;
        } else {
            $show_original = true;
        }
    }

    if (!$use_fallback && empty($ingredients) && empty($instructions)) return '';

    return sitc_render_recipe([
        'post_id'      => $post_id,
        'ingredients'  => $ingredients,
        'instructions' => $instructions,
        'yield_num'    => (int)$yield_num,
        'yield_raw'    => (string)$yield_raw,
        'use_fallback' => (bool)$use_fallback,
        'trash_token'  => (string)$trash_token,
        'show_original'=> (bool)$show_original,
        'source_url'   => (string)$source_url,
    ]);
}

// Auto-append to content (unchanged)
add_filter('the_content', function($content){
    if (!is_singular('post')) return $content;
    $post_id = get_the_ID();
    if (!$post_id) return $content;
    if (strpos($content, 'class="sitc-recipe"') !== false) return $content;
    $has_ingredients  = get_post_meta($post_id, '_sitc_ingredients_struct', true);
    $has_instructions = get_post_meta($post_id, '_sitc_instructions', true);
    if (!$has_ingredients && !$has_instructions) return $content;
    $block = sitc_render_recipe($data = [
        'post_id'      => $post_id,
        'ingredients'  => sitc_normalize_array_meta($has_ingredients ?: []),
        'instructions' => sitc_normalize_array_meta($has_instructions ?: []),
        'yield_num'    => (int)sitc_normalize_scalar_number(get_post_meta($post_id, '_sitc_yield_num', true), 2),
        'yield_raw'    => (string)(get_post_meta($post_id, '_sitc_yield_raw', true) ?: ''),
        'use_fallback' => (function_exists('sitc_is_safe_mode') && sitc_is_safe_mode()),
        'trash_token'  => (string)(get_post_meta($post_id, '_sitc_trash_token', true) ?: ''),
        'show_original'=> false, // handled in shortcode path; harmless here
        'source_url'   => (string)(get_post_meta($post_id, '_sitc_source_url', true) ?: ''),
    ]);
    if ($block === '') return $content;
    return $content . "\n\n" . $block;
}, 9999);


// --- SITC: Force v2 parser on refresh/import (root-cause fix) ---
if (!function_exists('sitc_refresh_force_v2_parser')) {
    /**
     * Re-parse recipeIngredient from RAW using v2 + Normalizer during refresh/import.
     * - Updates only the _sitc_schema_recipe_json payload.
     * - Makes a backup of the previous schema JSON.
     */
    function sitc_refresh_force_v2_parser(int $post_id): void {
        // Only run on our recipe post type (adjust if needed)
        $pt = get_post_type($post_id);
        if ($pt !== 'post' && $pt !== 'recipe') { return; } // <- ggf. anpassen

        $schema_key = '_sitc_schema_recipe_json';
        $old_json   = get_post_meta($post_id, $schema_key, true);
        if (!is_string($old_json) || $old_json === '') { return; }

        $schema = json_decode($old_json, true);
        if (!is_array($schema) || empty($schema['recipeIngredient'])) { return; }

        // Collect RAW lines (string or ['raw'=>...])
        $rawLines = [];
        $src = is_array($schema['recipeIngredient']) ? $schema['recipeIngredient'] : [$schema['recipeIngredient']];
        foreach ($src as $entry) {
            if (is_array($entry) && isset($entry['raw']) && is_string($entry['raw'])) {
                $t = trim($entry['raw']);
            } else {
                $t = trim((string)$entry);
            }
            if ($t !== '') $rawLines[] = $t;
        }
        if (empty($rawLines)) { return; }

        // Parse each line with v2 (falls back to mod-adapter)
        $parsed = [];
        foreach ($rawLines as $line) {
            if (class_exists('\\Skipintro\\RecipeCrawler\\Parser\\Filters\\Ingredient_Text_Normalizer')) {
                $line = \Skipintro\RecipeCrawler\Parser\Filters\Ingredient_Text_Normalizer::normalize($line);
            }
            $res = null;
            if (function_exists('sitc_ing_v2_parse_line')) {
                $res = @sitc_ing_v2_parse_line($line, 'de');
            } elseif (function_exists('sitc_ing_parse_line_mod')) {
                $res = @sitc_ing_parse_line_mod($line, 'de');
            }
            if (is_array($res)) {
                $parsed[] = [
                    'raw'  => $line,
                    'qty'  => $res['qty']  ?? null,
                    'unit' => $res['unit'] ?? null,
                    'item' => $res['item'] ?? ($res['name'] ?? null),
                    'note' => $res['note'] ?? null,
                ];
            } else {
                $parsed[] = ['raw'=>$line, 'qty'=>null, 'unit'=>null, 'item'=>$line, 'note'=>null];
            }
        }

        // Backup + write back to schema (cause fix for future renders that read schema)
        $stamp = gmdate('Ymd_His');
        add_post_meta($post_id, $schema_key . '_bak_' . $stamp, $old_json);
        $schema['recipeIngredient']  = $parsed;
        $schema['ingredientsParsed'] = $parsed;

        $new_json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($new_json) && $new_json !== '') {
            update_post_meta($post_id, $schema_key, $new_json);
        }

        // Optional: mark parser version (hilft später bei gezielten Rewrites)
        update_post_meta($post_id, '_sitc_parser_version', '0.6.01');
    }

    // Hook into your refresh/import pipeline.
    // Passe den Hook an deine tatsächliche Refresh-Stelle an (z. B. eigener Action/Filter).
    add_action('sitc_after_refresh', 'sitc_refresh_force_v2_parser', 10, 1);
    // Fallback: beim manuellen Aktualisieren (nur wenn sinnvoll)
    add_action('save_post', function ($post_id, $post) {
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        // Optional: nur laufen lassen, wenn ein spezieller Query-Param gesetzt ist, um nicht alles zu berühren:
        if (!isset($_GET['sitc_fix_v2']) || $_GET['sitc_fix_v2'] !== '1') return;
        sitc_refresh_force_v2_parser((int)$post_id);
    }, 10, 2);
}
