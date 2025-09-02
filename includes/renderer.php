<?php
if (!defined('ABSPATH')) exit;

/**
 * Hilfsfunktionen
 */
function sitc_normalize_array_meta($value) {
    if (is_array($value)) return $value;
    if (is_string($value) && $value !== '') {
        $json = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) return $json;
        $maybe = maybe_unserialize($value);
        if (is_array($maybe)) return $maybe;
    }
    return [];
}
function sitc_normalize_scalar_number($value, $default = 2) {
    if ($value === '' || $value === null) return $default;
    $v = is_numeric($value) ? (float)$value : (float)str_replace(',', '.', (string)$value);
    return $v > 0 ? $v : $default;
}

// Einheitenausgabe (DE) – nur Anzeige-Mapping
// Siehe DEV_NOTES.md: „Einheiten-Policy (DE)“
function sitc_unit_to_de($unit) {
    $u = trim(mb_strtolower((string)$unit, 'UTF-8'));
    if ($u === '') return '';
    $map = [
        'tsp' => 'TL', 'teaspoon' => 'TL', 'teaspoons' => 'TL', 'tl' => 'TL',
        'tbsp' => 'EL', 'tablespoon' => 'EL', 'tablespoons' => 'EL', 'el' => 'EL',
        'cup' => 'Tasse', 'cups' => 'Tasse', 'tasse' => 'Tasse',
        'piece' => 'Stück', 'pieces' => 'Stück', 'stück' => 'Stück', 'stueck' => 'Stück',
        'pinch' => 'Prise', 'prisen' => 'Prise', 'prise' => 'Prise',
        'can' => 'Dose', 'dose' => 'Dose',
        'bunch' => 'Bund', 'bund' => 'Bund',
        'g' => 'g', 'gram' => 'g', 'grams' => 'g', 'gramm' => 'g',
        'kg' => 'kg',
        'ml' => 'ml', 'milliliter' => 'ml', 'millilitre' => 'ml',
        'l' => 'l', 'liter' => 'l', 'litre' => 'l',
        'oz' => 'oz', 'ounce' => 'oz', 'ounces' => 'oz',
        'lb' => 'lb', 'pound' => 'lb', 'pounds' => 'lb',
    ];
    return $map[$u] ?? $unit;
}

// Helper: Unicode-Brüche → ASCII (Vor-Normalisierung für Mengen)
function sitc_replace_unicode_fractions(string $s): string {
    $map = [
        '½' => '1/2', '¼' => '1/4', '¾' => '3/4',
        '⅓' => '1/3', '⅔' => '2/3',
        '⅛' => '1/8', '⅜' => '3/8', '⅝' => '5/8', '⅞' => '7/8',
    ];
    return strtr($s, $map);
}

// Mengen-Parsing: Einzelwert oder Bereich (a-b)
function sitc_parse_qty_or_range($qty): array {
    $res = ['low'=>null, 'high'=>null, 'isRange'=>false];
    if ($qty === null) return $res;
    $s = trim((string)$qty);
    if ($s === '') return $res;
    $s = sitc_replace_unicode_fractions($s);
    $s = preg_replace('/\s*\/\s*/', '/', $s); // Spaces um "/" entfernen
    $s = str_replace(['–','—',' to '], ['-','-','-'], $s); // Bereichsseparatoren

    $toFloat = function(string $t){
        $t = trim($t);
        $t = sitc_replace_unicode_fractions($t);
        $t = preg_replace('/\s*\/\s*/', '/', $t);
        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $t, $m)) {
            return (float)$m[1] + ((float)$m[2]/max(1,(float)$m[3]));
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $t, $m)) {
            return ((float)$m[1])/max(1,(float)$m[2]);
        }
        if (preg_match('/^\d+(?:[\.,]\d+)?$/', $t)) {
            return (float)str_replace(',', '.', $t);
        }
        return null;
    };

    if (preg_match('/^(.+?)\s*-\s*(.+)$/', $s, $rm)) {
        $a = $toFloat($rm[1]); $b = $toFloat($rm[2]);
        if ($a !== null && $b !== null) { $res['low']=$a; $res['high']=$b; $res['isRange']=true; return $res; }
    }
    $v = $toFloat($s);
    if ($v !== null) { $res['low'] = $v; }
    return $res;
}

// Anzeige-Format (DE) mit Ganzzahl-Schwelle
function sitc_format_qty_display($val): string {
    if ($val === null) return '';
    $v = (float)$val;
    if (abs($v - round($v)) < 0.01) $v = round($v);
    $s = number_format($v, 2, ',', '');
    return rtrim(rtrim($s, '0'), ',');
}

// Mengen-Parsing: 1/2, 1 1/2, ½, 1,5 => float
function sitc_coerce_qty_float($qty) {
    if ($qty === null) return null;
    $s = trim((string)$qty);
    if ($s === '') return null;
    $s = strtr($s, ['½'=>'1/2','¼'=>'1/4','¾'=>'3/4','⅓'=>'1/3','⅔'=>'2/3','⅛'=>'1/8','⅜'=>'3/8','⅝'=>'5/8','⅞'=>'7/8']);
    if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $s, $m)) { return (float)$m[1] + ((float)$m[2]/max(1,(float)$m[3])); }
    if (preg_match('/^(\d+)\/(\d+)$/', $s, $m)) { return ((float)$m[1])/max(1,(float)$m[2]); }
    if (preg_match('/^(\d+(?:[\.,]\d+)?)\s*[-–]\s*\d+(?:[\.,]\d+)?$/u', $s, $m)) { return (float)str_replace(',', '.', $m[1]); }
    if (preg_match('/^\d+(?:[\.,]\d+)?$/', $s)) { return (float)str_replace(',', '.', $s); }
    return null;
}

function sitc_format_qty_de($val) {
    if ($val === null) return '';
    $s = number_format((float)$val, 2, ',', '');
    return rtrim(rtrim($s, '0'), ',');
}

/**
 * Renderer
 */
function sitc_render_recipe_shortcode($post_id = 0) {
    $post_id = $post_id ? (int)$post_id : get_the_ID();
    if (!$post_id) return '';

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

    $trash_token = get_post_meta($post_id, '_sitc_trash_token', true);
    if (!$trash_token) {
        $trash_token = wp_generate_password(20, false, false);
        update_post_meta($post_id, '_sitc_trash_token', $trash_token);
    }

    $show_original = false;
    if (!empty($yield_raw)) {
        if (preg_match('/(\d+(?:[.,]\d+)?)/u', $yield_raw, $m)) {
            $raw_num = (float)str_replace(',', '.', $m[1]);
            if ((float)$yield_num != $raw_num) $show_original = true;
        } else {
            $show_original = true;
        }
    }

    if (empty($ingredients) && empty($instructions)) return '';

    wp_enqueue_style('sitc-recipe');
    wp_enqueue_script('sitc-recipe');

    ob_start(); ?>
    <div class="sitc-recipe" data-post="<?php echo (int)$post_id; ?>">
        <div class="sitc-controls">
            <span class="sitc-yield-label">Personenanzahl:</span>
            <div class="sitc-servings-control">
                <button type="button" class="sitc-btn sitc-btn-minus">−</button>
                <span class="sitc-servings-display" data-base-servings="<?php echo esc_attr($yield_num); ?>">
                    <?php echo esc_html($yield_num); ?>
                </span>
                <button type="button" class="sitc-btn sitc-btn-plus">+</button>
            </div>
            <?php if ($show_original): ?>
                <span class="sitc-yield-raw">(Original: <?php echo esc_html($yield_raw); ?>)</span>
            <?php endif; ?>
            <input type="number" class="sitc-servings" value="<?php echo esc_attr($yield_num); ?>" min="1" step="1" style="display:none;">

            <div class="sitc-actions">
                <button type="button" class="sitc-btn sitc-btn-grocery" data-target="#sitc-list-<?php echo $post_id; ?>" aria-expanded="false" title="Einkaufsliste"><span class="material-symbols-outlined">shopping_cart</span></button>
                <button type="button" class="sitc-btn sitc-btn-wake" title="Bildschirm anlassen"><span class="material-symbols-outlined">toggle_on</span></button>
                <button type="button" class="sitc-btn sitc-btn-trash" data-post="<?php echo $post_id; ?>" data-token="<?php echo esc_attr($trash_token); ?>" title="Rezept löschen"><span class="material-symbols-outlined">delete</span></button>
                <button type="button" class="sitc-btn sitc-btn-photo" title="Foto hinzufügen"><span class="material-symbols-outlined">add_a_photo</span></button>
            </div>
        </div>

        <?php if (!empty($ingredients)) : ?>
            <h3>Zutaten</h3>
            <ul class="sitc-ingredients" data-base-servings="<?php echo esc_attr($yield_num); ?>">
                <?php foreach ($ingredients as $ing):
                    $qtyRaw  = $ing['qty'] ?? '';
                    $unitRaw = $ing['unit'] ?? '';
                    $name    = $ing['name'] ?? '';
                    $qInfo   = sitc_parse_qty_or_range($qtyRaw);
                    if ($qInfo['isRange']) {
                        $dispQty = sitc_format_qty_display($qInfo['low']) . '–' . sitc_format_qty_display($qInfo['high']);
                    } elseif ($qInfo['low'] !== null) {
                        $dispQty = sitc_format_qty_display($qInfo['low']);
                    } else {
                        $dispQty = trim((string)$qtyRaw);
                    }
                    $unitDe  = sitc_unit_to_de($unitRaw);

                    $id_for = 'sitc_chk_' . $post_id . '_' . md5($qtyRaw.'|'.$unitRaw.'|'.$name);
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
                                <span class="sitc-name"> <?php echo esc_html($name); ?></span>
                            </span>
                        </label>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div id="sitc-list-<?php echo $post_id; ?>" class="sitc-grocery collapsed" hidden>
                <h4>Einkaufsliste</h4>
                <ul>
                    <?php foreach ($ingredients as $ing):
                        $qtyRaw  = $ing['qty'] ?? '';
                        $unitRaw = $ing['unit'] ?? '';
                        $name    = $ing['name'] ?? '';
                        $qInfo   = sitc_parse_qty_or_range($qtyRaw);
                        if ($qInfo['isRange']) {
                            $dispQty = sitc_format_qty_display($qInfo['low']) . '–' . sitc_format_qty_display($qInfo['high']);
                        } elseif ($qInfo['low'] !== null) {
                            $dispQty = sitc_format_qty_display($qInfo['low']);
                        } else {
                            $dispQty = trim((string)$qtyRaw);
                        }
                        $unitDe  = sitc_unit_to_de($unitRaw);
                        ?>
                        <li><?php echo esc_html(trim(($dispQty !== '' ? $dispQty.' ' : '').($unitDe ? $unitDe.' ' : '').$name)); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($instructions)) : ?>
            <h3>Zubereitung</h3>
            <ol class="sitc-instructions">
                <?php foreach ($instructions as $step):
                    $step = trim((string)$step);
                    if ($step === '') continue;
                    $step = preg_replace('/^\s*\d+[\)\.\:\-]\s+/u', '', $step);
                    ?>
                    <li><?php echo esc_html($step); ?></li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if (!empty($source_url)) : ?>
            <p class="sitc-source">
                Quelle: <a href="<?php echo esc_url($source_url); ?>" target="_blank" rel="nofollow noopener">Originalrezept ansehen</a>
            </p>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Auto-Anhang
 */
add_filter('the_content', function($content){
    if (!is_singular('post')) return $content;

    $post_id = get_the_ID();
    if (!$post_id) return $content;

    if (strpos($content, 'class="sitc-recipe"') !== false) return $content;

    $has_ingredients  = get_post_meta($post_id, '_sitc_ingredients_struct', true);
    $has_instructions = get_post_meta($post_id, '_sitc_instructions', true);
    if (!$has_ingredients && !$has_instructions) return $content;

    $block = sitc_render_recipe_shortcode($post_id);
    if ($block === '') return $content;

    return $content . "\n\n" . $block;
}, 9999);
