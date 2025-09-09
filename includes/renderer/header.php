<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

function sitc_render_recipe_header(array $data): string {
    $post_id = (int)($data['post_id'] ?? 0);
    $yield_num = (int)($data['yield_num'] ?? 2);
    $yield_raw = (string)($data['yield_raw'] ?? '');
    $show_original = (bool)($data['show_original'] ?? false);
    $use_fallback = (bool)($data['use_fallback'] ?? false);
    $trash_token = (string)($data['trash_token'] ?? '');

    // versions
    $parser_ts   = @filemtime(__DIR__ . '/../parser.php');
    $renderer_ts = @filemtime(__DIR__ . '/../renderer.php');
    $parser_ver = $parser_ts ? gmdate('Y-m-d.H:i:s', $parser_ts + 2*3600) : '-';
    $renderer_ver = $renderer_ts ? gmdate('Y-m-d.H:i:s', $renderer_ts + 2*3600) : '-';

    ob_start();
    ?>
    <div class="sitc-controls"><?php if (!$use_fallback): ?>
        <span class="sitc-yield-label">Personenanzahl:</span>
        <div class="sitc-servings-control">
            <button type="button" class="sitc-btn sitc-btn-minus">-</button>
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
    </div><?php endif; ?>
    <div class="sitc-version" style="margin:.5rem 0;color:#888;font-size:.9rem;">
        Parser: <?php echo esc_html($parser_ver); ?> | Renderer: <?php echo esc_html($renderer_ver); ?>
    </div>
    <?php
    return (string)ob_get_clean();
}