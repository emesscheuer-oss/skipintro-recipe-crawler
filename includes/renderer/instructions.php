<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('sitc_render_instructions')) {
    function sitc_render_instructions(array $args): string
    {
        $instructions = isset($args['instructions']) && is_array($args['instructions']) ? $args['instructions'] : [];

        if ($instructions === []) {
            return '';
        }

        $html = '<ol class="sitc-instructions">';

        foreach ($instructions as $step) {
            $html .= '<li>' . esc_html((string) $step) . '</li>';
        }

        $html .= '</ol>';

        return $html;
    }
}
