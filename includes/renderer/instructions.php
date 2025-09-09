<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;
function sitc_render_instructions(array $data): string {
    $instructions = array_values(array_filter(array_map('trim', (array)($data['instructions'] ?? []))));
    if (empty($instructions)) return '';
    ob_start();
    ?>
    <h3>Zubereitung</h3>
    <ol class="sitc-instructions">
        <?php foreach ($instructions as $step):
            if ($step === '') continue;
            $step = preg_replace('/^\s*\d+[\)\.\:\-]\s+/u', '', $step);
            ?>
            <li><?php echo esc_html($step); ?></li>
        <?php endforeach; ?>
    </ol>
    <?php
    return (string)ob_get_clean();
}