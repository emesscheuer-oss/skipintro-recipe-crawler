<?php

if (!defined('ABSPATH')) {
    exit;
}

function sitc_render_ingredients(array $args): string
{
    $post_id = (int) ($args['post_id'] ?? 0);
    $yield_num = $args['yield_num'] ?? 2;
    $ingredients = isset($args['ingredients']) ? (array) $args['ingredients'] : [];
    $use_fallback = (bool) ($args['use_fallback'] ?? false);
    $groupsForRender = isset($args['groupsForRender']) ? (array) $args['groupsForRender'] : [];
    $rawLines = isset($args['rawLines']) ? (array) $args['rawLines'] : [];

    if (!$use_fallback && empty($groupsForRender)) {
        try {
            if (function_exists('sitc_build_ingredient_groups_for_render')) {
                $sourceGroups = sitc_build_ingredient_groups_for_render($post_id);
                if (!empty($sourceGroups) && function_exists('sitc_prepare_grouped_items')) {
                    $groupsForRender = sitc_prepare_grouped_items($sourceGroups);
                }
            }
        } catch (Throwable $e) {
            error_log('SITC Renderer grouping fatal: ' . $e->getMessage());
            $groupsForRender = [];
        }
    }

    $buildStructuredLi = static function (int $postId, string $qtyRaw, string $unitRaw, string $name): string {
        $nameDisp = sitc_cased_de_ingredient(trim($name));
        $qInfo = function_exists('sitc_parse_qty_or_range_v2')
            ? sitc_parse_qty_or_range_v2($qtyRaw)
            : sitc_parse_qty_or_range($qtyRaw);

        if (!is_array($qInfo)) {
            $qInfo = ['isRange' => false, 'low' => null, 'high' => null];
        }

        if (!empty($qInfo['isRange'])) {
            $dispQty = sitc_format_qty_display($qInfo['low']) . '&ndash;' . sitc_format_qty_display($qInfo['high']);
        } elseif ($qInfo['low'] !== null) {
            $dispQty = sitc_format_qty_display($qInfo['low']);
        } else {
            $v = function_exists('sitc_coerce_qty_float_v2')
                ? sitc_coerce_qty_float_v2((string)$qtyRaw)
                : sitc_coerce_qty_float(sitc_qty_pre_normalize((string)$qtyRaw));
            $dispQty = ($v !== null) ? sitc_format_qty_display($v) : '';
        }

        $unitDe = sitc_unit_to_de($unitRaw);
        $id_for = 'sitc_chk_' . $postId . '_' . md5($qtyRaw . '|' . $unitRaw . '|' . $name);

        $li = '<li class="sitc-ingredient"';
        if (!empty($qInfo['isRange'])) {
            $li .= ' data-qty-low="' . esc_attr(str_replace(',', '.', (string)$qInfo['low'])) . '"';
            $li .= ' data-qty-high="' . esc_attr(str_replace(',', '.', (string)$qInfo['high'])) . '"';
        } else {
            $dataQtyVal = $qInfo['low'];
            if ($dataQtyVal === null) {
                $v2 = function_exists('sitc_coerce_qty_float_v2')
                    ? sitc_coerce_qty_float_v2((string)$qtyRaw)
                    : sitc_coerce_qty_float(sitc_qty_pre_normalize((string)$qtyRaw));
                if ($v2 !== null) {
                    $dataQtyVal = (float)$v2;
                }
            }
            $li .= ' data-qty="' . esc_attr($dataQtyVal !== null ? str_replace(',', '.', (string)$dataQtyVal) : '') . '"';
        }

        $li .= ' data-unit="' . esc_attr((string)$unitDe) . '" data-name="' . esc_attr((string)$name) . '">';
        $li .= '<label for="' . esc_attr((string)$id_for) . '">';
        $li .= '<input type="checkbox" id="' . esc_attr((string)$id_for) . '" class="sitc-chk">';
        $li .= '<span class="sitc-line">';
        $li .= '<span class="sitc-qty">' . esc_html((string)$dispQty) . '</span>';
        if ($unitDe !== '') {
            $li .= '<span class="sitc-unit"> ' . esc_html((string)$unitDe) . '</span>';
        }
        $li .= '<span class="sitc-name"> ' . esc_html((string)$nameDisp) . '</span>';
        $li .= '</span>';
        $li .= '</label></li>';

        return $li;
    };

    $buildRawLi = static function (int $postId, string $line) use ($buildStructuredLi): string {
        $raw = sitc_text_sanitize((string)$line);
        if (function_exists('sitc_parse_ingredient_line_v3')) {
            $parsed = sitc_parse_ingredient_line_v3($raw);
        } else {
            $parsed = sitc_parse_ingredient_line_v2($raw);
        }

        $name = trim((string) ($parsed['item'] ?? ''));
        $note = trim((string) ($parsed['note'] ?? ''));
        if ($note !== '') {
            $name .= ' (' . $note . ')';
        }

        $qtyRaw = isset($parsed['qty']) ? (string)$parsed['qty'] : '';
        if ($qtyRaw === '' || $qtyRaw === null) {
            if (function_exists('sitc_extract_leading_qty_token_v2')) {
                $lead = sitc_extract_leading_qty_token_v2($raw);
                if ($lead !== '') {
                    $qtyRaw = $lead;
                }
            }
        }

        $unitRaw = isset($parsed['unit']) ? (string)$parsed['unit'] : '';

        return $buildStructuredLi($postId, $qtyRaw, $unitRaw, $name);
    };

    $html = '';

    if (!$use_fallback && !empty($groupsForRender)) {
        $html .= '<h3>Zutaten</h3>';
        foreach ($groupsForRender as $group) {
            $groupName = (string) ($group['name'] ?? '');
            $html .= '<h4>' . esc_html($groupName) . '</h4>';
            $html .= '<ul class="sitc-ingredients" data-base-servings="' . esc_attr((string)$yield_num) . '">';
            foreach ((array) ($group['items'] ?? []) as $item) {
                $qtyRaw = (string) ($item['qty'] ?? '');
                $unitRaw = (string) ($item['unit'] ?? '');
                $name = (string) ($item['name'] ?? '');
                $html .= $buildStructuredLi($post_id, $qtyRaw, $unitRaw, $name);
            }
            $html .= '</ul>';
        }
    } elseif (!$use_fallback && !empty($ingredients)) {
        $html .= '<h3>Zutaten</h3>';
        $html .= '<ul class="sitc-ingredients" data-base-servings="' . esc_attr((string)$yield_num) . '">';
        $displayIngredients = function_exists('sitc_merge_ingredients_for_display')
            ? sitc_merge_ingredients_for_display($ingredients)
            : $ingredients;
        foreach ($displayIngredients as $ing) {
            $qtyRaw = (string) ($ing['qty'] ?? '');
            $unitRaw = (string) ($ing['unit'] ?? '');
            $name = (string) ($ing['name'] ?? '');
            $html .= $buildStructuredLi($post_id, $qtyRaw, $unitRaw, $name);
        }
        $html .= '</ul>';

        $html .= '<div id="sitc-list-' . (int)$post_id . '" class="sitc-grocery collapsed" hidden>';
        $html .= '<h4>Einkaufsliste</h4>';
        $html .= '<ul>';
        $groceryIngredients = function_exists('sitc_merge_ingredients_for_display')
            ? sitc_merge_ingredients_for_display($ingredients)
            : $ingredients;
        foreach ($groceryIngredients as $ing) {
            $qtyRaw = $ing['qty'] ?? '';
            $unitRaw = $ing['unit'] ?? '';
            $name = $ing['name'] ?? '';
            $nameDisp = sitc_cased_de_ingredient(trim((string)$name));
            $qInfo = function_exists('sitc_parse_qty_or_range_v2')
                ? sitc_parse_qty_or_range_v2($qtyRaw)
                : sitc_parse_qty_or_range($qtyRaw);
            if (!is_array($qInfo)) {
                $qInfo = ['isRange' => false, 'low' => null, 'high' => null];
            }
            if (!empty($qInfo['isRange'])) {
                $dispQty = sitc_format_qty_display($qInfo['low']) . '&ndash;' . sitc_format_qty_display($qInfo['high']);
            } elseif ($qInfo['low'] !== null) {
                $dispQty = sitc_format_qty_display($qInfo['low']);
            } else {
                $v = sitc_coerce_qty_float(sitc_qty_pre_normalize((string)$qtyRaw));
                $dispQty = ($v !== null) ? sitc_format_qty_display($v) : '';
            }
            $unitDe = sitc_unit_to_de($unitRaw);
            $line = trim(($dispQty !== '' ? $dispQty . ' ' : '') . ($unitDe ? $unitDe . ' ' : '') . $nameDisp);
            $html .= '<li>' . esc_html($line) . '</li>';
        }
        $html .= '</ul>';
        $html .= '</div>';
    } else {
        $resolvedRaw = array_values(array_filter(array_map('trim', $rawLines), static function ($s) {
            return $s !== '';
        }));

        if (empty($resolvedRaw)) {
            $schema_json = get_post_meta($post_id, '_sitc_schema_recipe_json', true);
            if (is_string($schema_json) && $schema_json !== '') {
                $schema = json_decode($schema_json, true);
                if (is_array($schema) && !empty($schema['recipeIngredient'])) {
                    $source = $schema['recipeIngredient'];
                    if (!is_array($source)) {
                        $source = [$source];
                    }
                    foreach ($source as $entry) {
                        $text = trim((string)$entry);
                        if ($text !== '') {
                            $resolvedRaw[] = $text;
                        }
                    }
                }
            }
        }

        if (empty($resolvedRaw) && !empty($ingredients)) {
            foreach ((array)$ingredients as $ing) {
                $qty = trim((string) ($ing['qty'] ?? ''));
                $unit = trim((string) ($ing['unit'] ?? ''));
                $name = trim((string) ($ing['name'] ?? ''));
                $line = trim(($qty !== '' ? $qty . ' ' : '') . ($unit !== '' ? $unit . ' ' : '') . $name);
                if ($line !== '') {
                    $resolvedRaw[] = $line;
                }
            }
        }

        $resolvedRaw = array_values(array_filter(array_map('trim', $resolvedRaw), static function ($s) {
            return $s !== '';
        }));

        if (!empty($resolvedRaw)) {
            $html .= '<h3>Zutaten</h3>';
            $html .= '<ul class="sitc-ingredients" data-base-servings="' . esc_attr((string)$yield_num) . '">';
            foreach ($resolvedRaw as $line) {
                $html .= $buildRawLi($post_id, (string)$line);
            }
            $html .= '</ul>';
        }
    }

    return $html;
}
