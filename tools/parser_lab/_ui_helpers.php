<?php
// Dev UI helpers for parser_lab pages (no strict_types, no WP deps)

if (!function_exists('sitc_html')) {
    function sitc_html($v): string {
        if ($v instanceof Throwable) { $v = $v->getMessage(); }
        if (is_array($v)) { $v = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('sitc_ui_panel')) {
    function sitc_ui_panel(string $type, $message, array $meta = []): void {
        $colors = [
            'info'  => ['#dbeafe', '#1e3a8a'],
            'warn'  => ['#fff7ed', '#9a3412'],
            'error' => ['#fee2e2', '#991b1b'],
            'ok'    => ['#dcfce7', '#166534'],
        ];
        $pair = isset($colors[$type]) ? $colors[$type] : $colors['info'];
        $bg = $pair[0];
        $fg = $pair[1];
        echo '<div style="margin:10px 0;padding:10px;border:1px solid #ddd;background:' . sitc_html($bg) . ';color:' . sitc_html($fg) . '">';
        echo '<strong>' . sitc_html(strtoupper($type)) . ':</strong> ' . sitc_html($message);
        if (!empty($meta)) {
            echo '<div style="margin-top:6px;font-size:12px;opacity:.8"><code>' . sitc_html(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</code></div>';
        }
        echo '</div>';
    }
}

if (!function_exists('sitc_info'))  { function sitc_info($m, array $meta = [])  { sitc_ui_panel('info',  $m, $meta); } }
if (!function_exists('sitc_warn'))  { function sitc_warn($m, array $meta = [])  { sitc_ui_panel('warn',  $m, $meta); } }
if (!function_exists('sitc_error')) { function sitc_error($m, array $meta = []) { sitc_ui_panel('error', $m, $meta); } }
if (!function_exists('sitc_ok'))    { function sitc_ok($m, array $meta = [])    { sitc_ui_panel('ok',    $m, $meta); } }

if (!function_exists('err_box')) { function err_box($m) { sitc_error($m); } }
