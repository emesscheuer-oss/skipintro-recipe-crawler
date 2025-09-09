<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

function sitc_render_dev_badge(array $payload, array $opts = []): string {
    $post_id = (int)($payload['post_id'] ?? 0);
    $ingredients = (array)($payload['ingredients'] ?? []);

    if (!function_exists('sitc_is_dev_mode') || !sitc_is_dev_mode()) return '';

    // Build dev diagnostics: first 5 raw lines from schema or structured fallback
    $schema_json = get_post_meta($post_id, '_sitc_schema_recipe_json', true);
    $schema = [];
    if (is_string($schema_json) && $schema_json !== '') {
        $tmp = json_decode($schema_json, true); if (is_array($tmp)) $schema = $tmp;
    }
    $rawLinesDev = [];
    if (!empty($schema['recipeIngredient'])) {
        $src = $schema['recipeIngredient']; if (!is_array($src)) $src = [$src];
        foreach ($src as $s) { $t = trim((string)$s); if ($t!=='') $rawLinesDev[]=$t; }
    }
    if (!$rawLinesDev && !empty($ingredients)) {
        foreach ((array)$ingredients as $ing) {
            $q = trim((string)($ing['qty']??'')); $u = trim((string)($ing['unit']??'')); $n = trim((string)($ing['name']??''));
            $line = trim(($q!==''?$q.' ':'') . ($u!==''?$u.' ':'') . $n); if ($line!=='') $rawLinesDev[]=$line;
        }
    }
    $sources_json = get_post_meta($post_id, '_sitc_sources_json', true);
    $srcField = '';
    if (is_string($sources_json) && $sources_json !== '') {
        $srcMap = json_decode($sources_json, true);
        if (is_array($srcMap) && !empty($srcMap['recipeIngredient'])) { $srcField = implode('|', (array)$srcMap['recipeIngredient']); }
        elseif (is_array($srcMap) && !empty($srcMap['ingredientGroups'])) { $srcField = implode('|', (array)$srcMap['ingredientGroups']); }
    }
    if ($srcField === '') $srcField = (!empty($schema) ? 'jsonld' : 'dom');

    if (empty($rawLinesDev)) return '';

    ob_start();
    ?>
    <div class="sitc-dev-badge" style="font-size:.85rem;padding:.75rem;border:1px dashed #999;border-radius:6px;margin:.5rem 0;color:#333;background:#fafafa;">
      <strong>Rendering pipeline check (first 5 items)</strong>
      <ul style="margin:.5rem 0 0 1.25rem;">
        <?php $i=0; foreach ($rawLinesDev as $line): if ($i++>=5) break; 
            $raw = (string)$line;
            $san = sitc_text_sanitize($raw);
            $pre = sitc_qty_pre_normalize($san);
            $qi  = sitc_parse_qty_or_range($pre);
            $disp = '';
            if (!empty($qi['isRange']) && $qi['isRange']) { $disp = sitc_format_qty_display($qi['low']).'–'.sitc_format_qty_display($qi['high']); }
            elseif ($qi['low'] !== null) { $disp = sitc_format_qty_display($qi['low']); }
            if (function_exists('sitc_parse_ingredient_line_v3')) { $p = sitc_parse_ingredient_line_v3($pre); } else { $p = sitc_parse_ingredient_line_v2($pre); }
            $p_qty = isset($p['qty']) ? (string)$p['qty'] : '';
            $p_unit = isset($p['unit']) ? (string)$p['unit'] : '';
            $p_item = isset($p['item']) ? (string)$p['item'] : '';
            $p_note = isset($p['note']) ? (string)$p['note'] : '';
        ?>
        <li style="margin:.25rem 0;">
            <div>raw: <code><?php echo esc_html($raw); ?></code> (<?php echo esc_html($srcField); ?>)</div>
            <div>sanitized: <code><?php echo esc_html($san); ?></code></div>
            <div>prenorm: <code><?php echo esc_html($pre); ?></code></div>
            <div>parsed: qty=<?php echo esc_html($p_qty); ?>, unit=<?php echo esc_html($p_unit); ?>, item=<?php echo esc_html($p_item); ?><?php if($p_note!=='') echo ', note='.esc_html($p_note); ?></div>
            <div>display: <strong><?php echo esc_html($disp); ?></strong></div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php
    return (string)ob_get_clean();
}