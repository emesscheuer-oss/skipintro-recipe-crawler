<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function sitc_render_header(array $args): string
{
    $post_id = (int) ($args['post_id'] ?? 0);
    $yield_num = $args['yield_num'] ?? 2;
    $yield_raw = (string) ($args['yield_raw'] ?? '');
    $show_original = (bool) ($args['show_original'] ?? false);
    $use_fallback = (bool) ($args['use_fallback'] ?? false);
    $trash_token = (string) ($args['trash_token'] ?? '');
    $parser_ver = isset($args['parser_ver']) ? (string) $args['parser_ver'] : null;
    $renderer_ver = isset($args['renderer_ver']) ? (string) $args['renderer_ver'] : null;

    if ($parser_ver === null) {
        $parser_ts = @filemtime(__DIR__ . '/../parser.php');
        $parser_ver = $parser_ts ? gmdate('Y-m-d.H:i:s', $parser_ts + 2 * 3600) : '-';
    }

    if ($renderer_ver === null) {
        $renderer_ts = @filemtime(__DIR__ . '/../renderer.php');
        $renderer_ver = $renderer_ts ? gmdate('Y-m-d.H:i:s', $renderer_ts + 2 * 3600) : '-';
    }

    $html = '';

    $html .= '<div class="sitc-controls">';
    if (!$use_fallback) {
        $html .= '<span class="sitc-yield-label">Personenanzahl:</span>';
        $html .= '<div class="sitc-servings-control">';
        $html .= '<button type="button" class="sitc-btn sitc-btn-minus">-</button>';
        $html .= '<span class="sitc-servings-display" data-base-servings="' . esc_attr(str_replace(',', '.', (string) $yield_num)) . '">';
        $html .= esc_html((string) $yield_num);
        $html .= '</span>';
        $html .= '<button type="button" class="sitc-btn sitc-btn-plus">+</button>';
        $html .= '</div>';
        if ($show_original) {
            $html .= '<span class="sitc-yield-raw">(Original: ' . esc_html((string) $yield_raw) . ')</span>';
        }
        $html .= '<input type="number" class="sitc-servings" value="' . esc_attr((string) $yield_num) . '" min="1" step="1" style="display:none;">';
        $html .= '<div class="sitc-actions">';
        $html .= '<button type="button" class="sitc-btn sitc-btn-grocery" data-target="#sitc-list-' . esc_attr((string) $post_id) . '" aria-expanded="false" title="Einkaufsliste"><span class="material-symbols-outlined">shopping_cart</span></button>';
        $html .= '<button type="button" class="sitc-btn sitc-btn-wake" title="Bildschirm anlassen"><span class="material-symbols-outlined">toggle_on</span></button>';
        $html .= '<button type="button" class="sitc-btn sitc-btn-trash" data-post="' . esc_attr(str_replace(',', '.', (string) $post_id)) . '" data-token="' . esc_attr($trash_token) . '" title="Rezept lÃ¶schen"><span class="material-symbols-outlined">delete</span></button>';
        $html .= '<button type="button" class="sitc-btn sitc-btn-photo" title="Foto hinzufÃ¼gen"><span class="material-symbols-outlined">add_a_photo</span></button>';
        $html .= '</div>';
    }
    $html .= '</div>';

    $html .= '<div class="sitc-version" style="margin:.5rem 0;color:#888;font-size:.9rem;">';
    $html .= 'Parser: ' . esc_html((string) $parser_ver) . ' | Renderer: ' . esc_html((string) $renderer_ver);
    $html .= '</div>';

    return $html;
}
