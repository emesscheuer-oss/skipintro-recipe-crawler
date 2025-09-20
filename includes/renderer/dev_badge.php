<?php

if (!defined('ABSPATH')) {
    exit;
}

// Safe stringifier for dev-badge output (prevents "Array to string conversion")
if (!function_exists('sitc_badge_stringify')) {
    function sitc_badge_stringify($v, string $list_sep = ', '): string
    {
        if ($v === null || is_scalar($v)) {
            return esc_html(trim((string) $v));
        }

        if (is_array($v)) {
            $is_list = array_keys($v) === range(0, count($v) - 1);

            if ($is_list) {
                $parts = array_map(function ($x) use ($list_sep) {
                    if ($x === null || is_scalar($x)) {
                        return trim((string) $x);
                    }

                    if (is_array($x)) {
                        $is_sub_list = array_keys($x) === range(0, count($x) - 1);
                        return $is_sub_list ? implode($list_sep, array_map('strval', $x)) : 'array[' . count($x) . ']';
                    }

                    if (is_object($x)) {
                        return 'object:' . get_class($x);
                    }

                    return gettype($x);
                }, $v);

                return esc_html(implode($list_sep, array_filter($parts, function ($s) {
                    return $s !== '';
                })));
            }

            $keys = array_slice(array_keys($v), 0, 3);
            $summary = 'array[' . count($v) . ']';

            if ($keys) {
                $summary .= ' keys:' . implode(',', array_map('strval', $keys));
            }

            return esc_html($summary);
        }

        if (is_object($v)) {
            return esc_html('object:' . get_class($v));
        }

        return esc_html(gettype($v));
    }
}

function sitc_render_dev_badge(array $payload, array $opts = []): string
{
    $post_id = (int) ($payload['post_id'] ?? 0);
    $ingredients = (array) ($payload['ingredients'] ?? []);

    if (!function_exists('sitc_is_dev_mode') || !sitc_is_dev_mode()) {
        return '';
    }

    // Engine + flags overview (effective engine, requested engine, flag state, confidence)
    $requestedEngine = isset($GLOBALS['SITC_ING_ENGINE']) ? (string) $GLOBALS['SITC_ING_ENGINE'] : 'auto';
    $effectiveEngine = function_exists('sitc_get_ing_engine') ? sitc_get_ing_engine() : ($requestedEngine === 'auto' ? ((defined('SITC_ING_V2') && SITC_ING_V2) ? 'mod' : 'legacy') : $requestedEngine);
    $flagOn = (defined('SITC_ING_V2') && SITC_ING_V2 === true);
    $flags = [];
    $confidence = null;
    $flags_raw = get_post_meta($post_id, '_sitc_flags_json', true);
    if (is_string($flags_raw) && $flags_raw !== '') {
        $tmp = json_decode($flags_raw, true);
        if (is_array($tmp)) {
            $flags = $tmp;
        }
    }
    $c = get_post_meta($post_id, '_sitc_confidence', true);
    if ($c !== '' && $c !== null) {
        $confidence = (float) $c;
    }

    // Build dev diagnostics: first 5 raw lines from schema or structured fallback
    $schema_json = get_post_meta($post_id, '_sitc_schema_recipe_json', true);
    $schema = [];
    if (is_string($schema_json) && $schema_json !== '') {
        $tmp = json_decode($schema_json, true);
        if (is_array($tmp)) {
            $schema = $tmp;
        }
    }
    $rawLinesDev = [];
    if (!empty($schema['recipeIngredient'])) {
        $src = $schema['recipeIngredient'];
        if (!is_array($src)) {
            $src = [$src];
        }
        foreach ($src as $s) {
            $t = is_scalar($s) ? trim((string) $s) : '';
            if ($t !== '') {
                $rawLinesDev[] = $t;
            }
        }
    }
    if (!$rawLinesDev && !empty($ingredients)) {
        foreach ((array) $ingredients as $ing) {
            $qv = $ing['qty'] ?? '';
            $uv = $ing['unit'] ?? '';
            $nv = $ing['name'] ?? '';
            $q = is_scalar($qv) ? trim((string) $qv) : '';
            $u = is_scalar($uv) ? trim((string) $uv) : '';
            $n = is_scalar($nv) ? trim((string) $nv) : '';
            $line = trim(($q !== '' ? $q . ' ' : '') . ($u !== '' ? $u . ' ' : '') . $n);
            if ($line !== '') {
                $rawLinesDev[] = $line;
            }
        }
    }
    $sources_json = get_post_meta($post_id, '_sitc_sources_json', true);
    $srcField = '';
    if (is_string($sources_json) && $sources_json !== '') {
        $srcMap = json_decode($sources_json, true);
        if (is_array($srcMap) && !empty($srcMap['recipeIngredient'])) {
            $arr = (array) $srcMap['recipeIngredient'];
            $arr = array_map(function ($v) {
                if ($v === null || is_scalar($v)) {
                    return (string) $v;
                }
                if (is_array($v)) {
                    return 'array[' . count($v) . ']';
                }
                if (is_object($v)) {
                    return 'object:' . get_class($v);
                }
                return gettype($v);
            }, $arr);
            $srcField = implode('|', $arr);
        } elseif (is_array($srcMap) && !empty($srcMap['ingredientGroups'])) {
            $arr = (array) $srcMap['ingredientGroups'];
            $arr = array_map(function ($v) {
                if ($v === null || is_scalar($v)) {
                    return (string) $v;
                }
                if (is_array($v)) {
                    return 'array[' . count($v) . ']';
                }
                if (is_object($v)) {
                    return 'object:' . get_class($v);
                }
                return gettype($v);
            }, $arr);
            $srcField = implode('|', $arr);
        }
    }
    if ($srcField === '') {
        $srcField = (!empty($schema) ? 'jsonld' : 'dom');
    }

    if (empty($rawLinesDev)) {
        return '';
    }

    $html  = '<details class="sitc-pipeline sitc-dev-badge" aria-label="Rendering pipeline check (all items)" style="font-size:.85rem;padding:.75rem;border:1px dashed #999;border-radius:6px;margin:.5rem 0;color:#333;background:#fafafa;">';
    $html .= "\n      <summary>Rendering pipeline check (all items)</summary>";
    $html .= "\n      <div style=\"margin:.25rem 0 .5rem 0;color:#555\">";
    $html .= "\n        engine: <code>" . sitc_badge_stringify($effectiveEngine ?: '-') . '</code>';
    if ($requestedEngine !== $effectiveEngine) {
        $html .= ' (requested: <code>' . sitc_badge_stringify($requestedEngine ?: '-') . '</code>)';
    }
    $html .= "\n        &nbsp;|&nbsp; flag SITC_ING_V2: <strong>" . ($flagOn ? 'ON' : 'OFF') . '</strong>';
    if ($confidence !== null) {
        $html .= "\n        &nbsp;|&nbsp; confidence: <code>" . sitc_badge_stringify(number_format_i18n($confidence, 2)) . '</code>';
    }
    if (!empty($flags)) {
        $flagList = implode(',', array_keys(array_filter($flags, function ($v) {
            return (bool) $v;
        })));
        $html .= "\n        &nbsp;|&nbsp; flags: <code>" . sitc_badge_stringify($flagList) . '</code>';
    }
    $html .= "\n      </div>";
    $html .= "\n      <ul style=\"margin:.5rem 0 0 1.25rem;\">";
    $i = 0;
    foreach ($rawLinesDev as $line) {
        if ($i++ >= 5) {
            break;
        }
        $raw = (string) $line;
        $san = sitc_text_sanitize($raw);
        $pre = function_exists('sitc_qty_pre_normalize_v2') ? sitc_qty_pre_normalize_v2($san) : sitc_qty_pre_normalize($san);
        $qi  = function_exists('sitc_parse_qty_or_range_v2') ? sitc_parse_qty_or_range_v2($pre) : sitc_parse_qty_or_range($pre);
        $disp = '';
        if (!empty($qi['isRange']) && $qi['isRange']) {
            $disp = sitc_format_qty_display($qi['low']) . '–' . sitc_format_qty_display($qi['high']);
        } elseif ($qi['low'] !== null) {
            $disp = sitc_format_qty_display($qi['low']);
        }
        if (function_exists('sitc_parse_ingredient_line_v3')) {
            $p = sitc_parse_ingredient_line_v3($pre);
        } else {
            $p = sitc_parse_ingredient_line_v2($pre);
        }
        $p_qty = $p['qty'] ?? '';
        $p_unit = $p['unit'] ?? '';
        $p_item = $p['item'] ?? '';
        $p_note = $p['note'] ?? '';
        $html .= "\n        <li style=\"margin:.25rem 0;\">";
        $html .= "\n            <div>raw: <code>" . esc_html($raw) . '</code> (' . esc_html($srcField) . ')</div>';
        $html .= "\n            <div>sanitized: <code>" . esc_html($san) . '</code></div>';
        $html .= "\n            <div>prenorm: <code>" . esc_html($pre) . '</code></div>';
        $html .= "\n            <div>parsed: qty=" . sitc_badge_stringify($p_qty) . ', unit=' . sitc_badge_stringify($p_unit) . ', item=' . sitc_badge_stringify($p_item);
        if ($p_note !== '') {
            $html .= ', note=' . sitc_badge_stringify($p_note);
        }
        $html .= '</div>';
        $html .= "\n            <div>display: <strong>" . esc_html($disp) . '</strong></div>';
        $html .= "\n        </li>";
    }
    $html .= "\n      </ul>";
    $html .= "\n      <style>.sitc-pipeline summary{cursor:pointer;user-select:none}.sitc-pipeline pre,.sitc-pipeline .dump{max-height:320px;overflow:auto}</style>";
    $html .= "\n    </details>";

    return $html;
}

