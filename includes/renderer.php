<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

/**
 * Renderer helpers for SkipIntro Recipe Crawler
 * - No raw PHP open tags inside output; use string building/HEREDOC only
 * - Dev badge is returned via sitc_render_dev_badge() and only echoed at render site
 */

if (!function_exists('sitc_is_dev_mode')) {
    function sitc_is_dev_mode(): bool {
        return (defined('WP_DEBUG') && WP_DEBUG) || (defined('SITC_DEV_MODE') && SITC_DEV_MODE);
    }
}

/**
 * Normalize meta values that may be stored as serialized/json/array
 */
function sitc_normalize_array_meta($value): array {
    if (is_array($value)) return $value;
    if (is_string($value) && $value !== '') {
        $json = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) return $json;
        $maybe = maybe_unserialize($value);
        if (is_array($maybe)) return $maybe;
    }
    return [];
}

/**
 * Render a small dev diagnostics badge (HEREDOC only, no PHP tags inside)
 */
function sitc_render_dev_badge(array $payload, array $opts = []): string {
    $title = (string)($opts['title'] ?? 'SITC Dev');
    $json  = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $html  = <<<HTML
    <div class="sitc-dev-badge" style="position:relative;margin:12px 0;padding:10px 12px;border:1px dashed #d33;background:#fff7f7;color:#900;font:12px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;">
      <strong style="display:inline-block;margin-right:8px;">{$title}</strong>
      <button type="button" class="sitc-dev-toggle" style="padding:2px 6px;border:1px solid #c99;background:#fee;color:#700;border-radius:3px;cursor:pointer">Details</button>
      <pre class="sitc-dev-details" style="display:none;margin:8px 0 0;max-height:260px;overflow:auto;background:#fff;border:1px solid #f0c;padding:8px;">{$json}</pre>
      <script>(function(){
        var root=document.currentScript&&document.currentScript.previousElementSibling&&document.currentScript.previousElementSibling.previousElementSibling&&document.currentScript.previousElementSibling.previousElementSibling.parentNode; 
        try{
          var btn=root.querySelector('.sitc-dev-toggle');
          var pre=root.querySelector('.sitc-dev-details');
          btn&&btn.addEventListener('click',function(){ pre.style.display = pre.style.display==='none' ? 'block' : 'none'; });
        }catch(e){}
      })();</script>
    </div>
    HTML;
    return $html;
}

/**
 * Basic DE unit display normalizer (minimal, safe)
 */
function sitc_unit_to_de($unit): string {
    $u = trim(mb_strtolower((string)$unit, 'UTF-8'));
    if ($u === '') return '';
    $map = [
        'tsp' => 'TL', 'teaspoon' => 'TL', 'teaspoons' => 'TL', 'tl' => 'TL',
        'tbsp' => 'EL', 'tablespoon' => 'EL', 'tablespoons' => 'EL', 'el' => 'EL',
        'cup' => 'Tasse', 'cups' => 'Tasse', 'tasse' => 'Tasse',
        'piece' => 'Stück', 'pieces' => 'Stück', 'stueck' => 'Stück', 'stück' => 'Stück',
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
    return $map[$u] ?? (string)$unit;
}

/**
 * Safe display helper for a single ingredient line (structured)
 */
function sitc_render_ingredient_line(array $ing): string {
    $qty  = trim((string)($ing['qty']  ?? ''));
    $unit = trim((string)($ing['unit'] ?? ''));
    $name = trim((string)($ing['name'] ?? ''));

    if ($unit !== '') $unit = sitc_unit_to_de($unit);
    $parts = [];
    if ($qty !== '')  $parts[] = $qty;
    if ($unit !== '') $parts[] = $unit;
    if ($name !== '') $parts[] = $name;
    $text = trim(implode(' ', $parts));
    if ($text === '') return '';
    return '<li>'.esc_html($text).'</li>';
}

/**
 * Main renderer: returns the HTML block for a recipe post
 */
function sitc_render_recipe_shortcode(int $post_id): string {
    $title        = (string)get_post_meta($post_id, '_sitc_recipe_title', true);
    $yield_num    = get_post_meta($post_id, '_sitc_yield_num', true);
    $yield_unit   = (string)get_post_meta($post_id, '_sitc_yield_unit', true);
    $notes        = (string)get_post_meta($post_id, '_sitc_recipe_notes', true);
    $source_url   = (string)get_post_meta($post_id, '_sitc_source_url', true);
    $ingredients  = sitc_normalize_array_meta(get_post_meta($post_id, '_sitc_ingredients_struct', true));
    $instructions = sitc_normalize_array_meta(get_post_meta($post_id, '_sitc_instructions', true));

    // Fallback: parse simple ingredient lines from schema json if structured empty
    if (empty($ingredients)) {
        $schema_json = (string)get_post_meta($post_id, '_sitc_schema_recipe_json', true);
        if ($schema_json !== '') {
            $schema = json_decode($schema_json, true);
            if (is_array($schema) && !empty($schema['recipeIngredient'])) {
                $lines = (array)$schema['recipeIngredient'];
                foreach ($lines as $line) {
                    $t = trim((string)$line);
                    if ($t === '') continue;
                    $ingredients[] = ['qty' => '', 'unit' => '', 'name' => $t];
                }
            }
        }
    }

    // If still nothing to show, return empty to skip appending
    if (empty($ingredients) && empty($instructions)) return '';

    $post_title = get_the_title($post_id);
    $yield_attr = $yield_num !== '' ? ' data-base-servings="'.esc_attr((string)$yield_num).'"' : '';

    $html  = '';
    $html .= '<div class="sitc-recipe" id="sitc-recipe-'.esc_attr((string)$post_id).'">';
    $html .= '<div class="sitc-recipe-header">';
    $html .= '<h2>'.esc_html($title !== '' ? $title : $post_title).'</h2>';
    if ($yield_num !== '' || $yield_unit !== '') {
        $y = trim(implode(' ', array_filter([(string)$yield_num, $yield_unit])));
        $html .= '<div class="sitc-yield">'.esc_html($y).'</div>';
    }
    $html .= '</div>';

    if (!empty($ingredients)) {
        $html .= '<h3>Zutaten</h3>';
        $html .= '<ul class="sitc-ingredients"'.$yield_attr.'>';
        foreach ($ingredients as $ing) {
            $li = sitc_render_ingredient_line(is_array($ing) ? $ing : ['name' => (string)$ing]);
            if ($li !== '') $html .= $li;
        }
        $html .= '</ul>';
    }

    if (!empty($instructions)) {
        $html .= '<h3>Zubereitung</h3>';
        $html .= '<ol class="sitc-instructions">';
        foreach ($instructions as $step) {
            $t = trim((string)$step);
            if ($t === '') continue;
            $html .= '<li>'.esc_html($t).'</li>';
        }
        $html .= '</ol>';
    }

    if ($notes !== '') {
        $html .= '<p class="sitc-notes">'.esc_html($notes).'</p>';
    }
    if ($source_url !== '') {
        $html .= '<p class="sitc-source">Quelle: <a href="'.esc_url($source_url).'" target="_blank" rel="nofollow noopener">Originalrezept ansehen</a></p>';
    }

    // Dev badge (only in dev mode)
    if (function_exists('sitc_is_dev_mode') && sitc_is_dev_mode()) {
        $diagnostics = [
            'post_id' => $post_id,
            'ingredients_count' => count($ingredients),
            'instructions_count' => count($instructions),
            'yield' => $yield_num,
            'yield_unit' => $yield_unit,
        ];
        $html .= sitc_render_dev_badge($diagnostics, ['title' => 'SITC Dev']);
    }

    $html .= '</div>';
    return $html;
}

/**
 * Auto-append block to post content if present and not already rendered
 */
add_filter('the_content', function ($content) {
    if (!is_singular('post')) return $content;
    $post_id = (int)get_the_ID();
    if (!$post_id) return $content;
    if (strpos($content, 'class="sitc-recipe"') !== false) return $content;

    $has_ingredients  = get_post_meta($post_id, '_sitc_ingredients_struct', true);
    $has_instructions = get_post_meta($post_id, '_sitc_instructions', true);
    if (empty($has_ingredients) && empty($has_instructions)) return $content;

    $block = sitc_render_recipe_shortcode($post_id);
    if ($block === '') return $content;
    return $content."\n\n".$block;
}, 9999);

