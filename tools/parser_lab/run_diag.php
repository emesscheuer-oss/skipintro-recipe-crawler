<?php

// Mini-Diagnose für Parser-Lab: zeigt Upload/INI/OpCache/Hashes/MTime

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

$diags = [];
set_error_handler(function ($sev, $msg, $file, $line) use (&$diags) {
    $diags[] = sprintf('%s: %s in %s:%d', (string)$sev, (string)$msg, (string)$file, (int)$line);
    return true;
});

header('Content-Type: text/html; charset=utf-8');

$pluginRoot = realpath(dirname(__DIR__, 2)) ?: (__DIR__ . '/..' . '/..');
$files = [
    'run.php' => __DIR__ . '/run.php',
    'harness.php' => __DIR__ . '/lib/harness.php',
    'helpers/render_table.php' => __DIR__ . '/helpers/render_table.php',
    'includes/parser.php' => $pluginRoot . '/includes/parser.php',
];

$ini = [
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'display_errors' => ini_get('display_errors'),
    'memory_limit' => ini_get('memory_limit'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'max_execution_time' => ini_get('max_execution_time'),
];

$opcache = [ 'available' => function_exists('opcache_get_status') ];
if ($opcache['available']) {
    $st = @opcache_get_status(false);
    if (is_array($st)) {
        $opcache['enabled'] = (bool)($st['opcache_enabled'] ?? false);
        $opcache['jit'] = $st['jit'] ?? null;
        $mem = $st['memory_usage'] ?? [];
        $opcache['memory'] = [
            'used' => $mem['used_memory'] ?? null,
            'free' => $mem['free_memory'] ?? null,
            'wasted' => $mem['wasted_memory'] ?? null,
        ];
        $scripts = $st['scripts'] ?? [];
        $opcache['scripts_cached'] = is_array($scripts) ? count($scripts) : 0;
    } else { $opcache['status_error'] = 'opcache_get_status() returned non-array'; }
}

$hashes = [];
foreach ($files as $label => $path) {
    $hashes[] = [
        'label' => $label,
        'path' => $path,
        'exists' => file_exists($path),
        'readable' => is_readable($path),
        'mtime' => file_exists($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
        'sha1' => (is_file($path) && is_readable($path)) ? sha1((string)file_get_contents($path)) : null,
    ];
}

$dtBerlin = new DateTime('now', new DateTimeZone('Europe/Berlin'));
$buildStr = $dtBerlin->format('Y-m-d H:i') . ' Europe/Berlin';

echo '<!doctype html><meta charset="utf-8">';
echo '<title>Parser-Lab – Mini-Diagnose</title>';
echo '<style>body{font:14px/1.4 system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px}h1{margin:0 0 12px}pre{background:#f9f9f9;border:1px solid #eee;padding:8px;overflow:auto}table{border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px 6px}code{font-family:ui-monospace,Consolas,monospace}.badge{display:inline-block;padding:2px 8px;border:1px solid #cfe3ff;background:#eef6ff;color:#114a7a;border-radius:4px;margin-left:8px}</style>';
echo '<h1>Parser-Lab <span class="badge">Build: '.htmlspecialchars($buildStr, ENT_QUOTES, 'UTF-8').'</span></h1>';

echo '<h2>INI</h2><pre>'.htmlspecialchars(json_encode($ini, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').'</pre>';
echo '<h2>OpCache</h2><pre>'.htmlspecialchars(json_encode($opcache, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').'</pre>';

echo '<h2>Dateien (mtime / sha1)</h2>';
echo '<table><thead><tr><th>Label</th><th>Pfad</th><th>Exists</th><th>Readable</th><th>mtime</th><th>sha1</th></tr></thead><tbody>';
foreach ($hashes as $h) {
    echo '<tr>'
        .'<td>'.htmlspecialchars((string)$h['label'], ENT_QUOTES, 'UTF-8').'</td>'
        .'<td><small>'.htmlspecialchars((string)$h['path'], ENT_QUOTES, 'UTF-8').'</small></td>'
        .'<td>'.($h['exists'] ? 'yes' : 'no').'</td>'
        .'<td>'.($h['readable'] ? 'yes' : 'no').'</td>'
        .'<td>'.htmlspecialchars((string)($h['mtime'] ?? ''), ENT_QUOTES, 'UTF-8').'</td>'
        .'<td><small>'.htmlspecialchars((string)($h['sha1'] ?? ''), ENT_QUOTES, 'UTF-8').'</small></td>'
        .'</tr>';
}
echo '</tbody></table>';

if ($diags) {
    echo '<h2>Diagnostics</h2><pre>'.htmlspecialchars(json_encode($diags, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8').'</pre>';
}

ob_end_flush();
