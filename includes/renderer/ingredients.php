<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;
function sitc_render_ingredients(array $data): string {
    $post_id      = (int)($data['post_id'] ?? 0);
    $yield_num    = (int)($data['yield_num'] ?? 2);
    $ingredients  = (array)($data['ingredients'] ?? []);
    $use_fallback = (bool)($data['use_fallback'] ?? false);

    // Prefer grouped rendering if not in fallback
    $groupsForRender = [];
    if (!$use_fallback) {
        try {
            if (function_exists('sitc_build_ingredient_groups_for_render')) {
                $sourceGroups = sitc_build_ingredient_groups_for_render($post_id);
                if (!empty($sourceGroups) && function_exists('sitc_prepare_grouped_items')) {
                    $groupsForRender = sitc_prepare_grouped_items($sourceGroups);
                }
            }
        } catch (Throwable $e) { error_log('SITC Renderer grouping fatal: '.$e->getMessage()); $groupsForRender = []; }
    }

    ob_start();
    if (!$use_fallback && !empty($groupsForRender)) : ?>
        <h3>Zutaten</h3>
        <?php foreach ($groupsForRender as $group): ?>
            <h4><?php echo esc_html((string)$group['name']); ?></h4>
            <ul class="sitc-ingredients" data-base-servings="<?php echo esc_attr($yield_num); ?>">
                <?php foreach ((array)$group['items'] as $it):
                    $qtyRaw   = (string)($it['qty'] ?? '');
                    $unitRaw  = (string)($it['unit'] ?? '');
                    $name     = (string)($it['name'] ?? '');
                    sitc_render_ingredient_li($post_id, $qtyRaw, $unitRaw, $name);
                endforeach; ?>
            </ul>
        <?php endforeach; ?>
    <?php elseif (!$use_fallback && !empty($ingredients)) : ?>
        <h3>Zutaten</h3>
        <ul class="sitc-ingredients" data-base-servings="<?php echo esc_attr($yield_num); ?>">
            <?php foreach ($ingredients as $ing):
                $qtyRaw   = (string)($ing['qty'] ?? '');
                $unitRaw  = (string)($ing['unit'] ?? '');
                $name     = (string)($ing['name'] ?? '');
                sitc_render_ingredient_li($post_id, $qtyRaw, $unitRaw, $name);
            endforeach; ?>
        </ul>

        <div id="sitc-list-<?php echo $post_id; ?>" class="sitc-grocery collapsed" hidden>
            <h4>Einkaufsliste</h4>
            <ul>
                <?php foreach ($ingredients as $ing):
                    $qtyRaw  = $ing['qty'] ?? '';
                    $unitRaw = $ing['unit'] ?? '';
                    $name    = $ing['name'] ?? '';
                    $nameDisp = sitc_cased_de_ingredient(trim((string)$name));
                    $qInfo   = sitc_parse_qty_or_range($qtyRaw);
                    if ($qInfo['isRange']) {
                        $dispQty = sitc_format_qty_display($qInfo['low']) . '–' . sitc_format_qty_display($qInfo['high']);
                    } elseif ($qInfo['low'] !== null) {
                        $dispQty = sitc_format_qty_display($qInfo['low']);
                    } else {
                        $v = sitc_coerce_qty_float(sitc_qty_pre_normalize((string)$qtyRaw)); $dispQty = ($v !== null) ? sitc_format_qty_display($v) : '';
                    }
                    $unitDe  = sitc_unit_to_de($unitRaw);
                    ?>
                    <li><?php echo esc_html(trim(($dispQty !== '' ? $dispQty.' ' : '').($unitDe ? $unitDe.' ' : '').$nameDisp)); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php else: // Fallback: simple list from schema -> recipeIngredient ?>
        <?php
            $rawLines = [];
            $schema_json = get_post_meta($post_id, '_sitc_schema_recipe_json', true);
            if (is_string($schema_json) && $schema_json !== '') {
                $schema = json_decode($schema_json, true);
                if (is_array($schema) && !empty($schema['recipeIngredient'])) {
                    $src = $schema['recipeIngredient'];
                    if (!is_array($src)) { $src = [$src]; }
                    foreach ($src as $s) {
                        $t = trim((string)$s);
                        if ($t !== '') $rawLines[] = $t;
                    }
                }
            }
            if (!$rawLines && !empty($ingredients)) {
                foreach ((array)$ingredients as $ing) {
                    $qty  = trim((string)($ing['qty'] ?? ''));
                    $unit = trim((string)($ing['unit'] ?? ''));
                    $name = trim((string)($ing['name'] ?? ''));
                    $line = trim(($qty !== '' ? $qty.' ' : '') . ($unit !== '' ? $unit.' ' : '') . $name);
                    if ($line !== '') $rawLines[] = $line;
                }
            }
            $rawLines = array_values(array_filter(array_map('trim', (array)$rawLines), function($s){ return $s !== ''; }));
        ?>
        <?php if (!empty($rawLines)) : ?>
            <h3>Zutaten</h3>
            <ul class="sitc-ingredients" data-base-servings="<?php echo esc_attr($yield_num); ?>">
                <?php foreach ($rawLines as $line):
                    sitc_render_ingredient_li_from_raw($post_id, (string)$line);
                endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif;
    return (string)ob_get_clean();
}

// Centralized renderer for one ingredient li from structured fields
function sitc_render_ingredient_li(int $post_id, string $qtyRaw, string $unitRaw, string $name): void {
    $nameDisp = sitc_cased_de_ingredient(trim((string)$name));
    $qInfo   = sitc_parse_qty_or_range($qtyRaw);
    if ($qInfo['isRange']) {
        $dispQty = sitc_format_qty_display($qInfo['low']) . '&ndash;' . sitc_format_qty_display($qInfo['high']);
    } elseif ($qInfo['low'] !== null) {
        $dispQty = sitc_format_qty_display($qInfo['low']);
    } else {
        $v = sitc_coerce_qty_float(sitc_qty_pre_normalize((string)$qtyRaw));
        $dispQty = ($v !== null) ? sitc_format_qty_display($v) : '';
    }
    $unitDe  = sitc_unit_to_de($unitRaw);
    $id_for  = 'sitc_chk_' . $post_id . '_' . md5($qtyRaw.'|'.$unitRaw.'|'.$name);
    ?>
    <li class="sitc-ingredient"
        <?php if ($qInfo['isRange']): ?>
            data-qty-low="<?php echo esc_attr(str_replace(',','.', (string)$qInfo['low'])); ?>"
            data-qty-high="<?php echo esc_attr(str_replace(',','.', (string)$qInfo['high'])); ?>"
        <?php else: ?>
            data-qty="<?php echo esc_attr($qInfo['low'] !== null ? str_replace(',','.', (string)$qInfo['low']) : ''); ?>"
        <?php endif; ?>
        data-unit="<?php echo esc_attr($unitDe); ?>" data-name="<?php echo esc_attr($name); ?>">
        <label for="<?php echo esc_attr($id_for); ?>">
            <input type="checkbox" id="<?php echo esc_attr($id_for); ?>" class="sitc-chk">
            <span class="sitc-line">
                <span class="sitc-qty"><?php echo esc_html($dispQty); ?></span>
                <?php if ($unitDe !== ''): ?><span class="sitc-unit"> <?php echo esc_html($unitDe); ?></span><?php endif; ?>
                <span class="sitc-name"> <?php echo esc_html($nameDisp); ?></span>
            </span>
        </label>
    </li>
    <?php
}

// Render li from a raw source line via central pipeline
function sitc_render_ingredient_li_from_raw(int $post_id, string $line): void {
    $raw = sitc_text_sanitize((string)$line);
    if (function_exists('sitc_parse_ingredient_line_v3')) {
        $p = sitc_parse_ingredient_line_v3($raw);
    } else {
        $p = sitc_parse_ingredient_line_v2($raw);
    }
    $name = trim((string)($p['item'] ?? ''));
    $note = trim((string)($p['note'] ?? ''));
    if ($note !== '') $name .= ' (' . $note . ')';
    $qtyRaw = isset($p['qty']) ? (string)$p['qty'] : '';
    $unitRaw = isset($p['unit']) ? (string)$p['unit'] : '';
    sitc_render_ingredient_li($post_id, $qtyRaw, $unitRaw, $name);
}
