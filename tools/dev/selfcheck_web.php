<?php
// tools/dev/selfcheck_web.php (token-based; no PHP close tag; no declare)
header('Content-Type: text/html; charset=utf-8');

$root = realpath(__DIR__ . '/../../');
if ($root === false) {
    $root = getcwd();
}

function collectPhpFiles($base) {
    $out = [];
    $it  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        $path = $file->getPathname();
        if (substr($path, -4) !== '.php') continue;
        if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
        $out[] = $path;
    }
    sort($out);
    return $out;
}

function hasCloseToken($src) {
    $tokens = @token_get_all($src);
    if (!is_array($tokens)) return false;
    foreach ($tokens as $tk) {
        if (is_array($tk) && $tk[0] === T_CLOSE_TAG) {
            return true;
        }
    }
    return false;
}

function hasBom($raw) {
    return strncmp($raw, "\xEF\xBB\xBF", 3) === 0;
}

$files = collectPhpFiles($root);
$warns = [];
$errors = [];

foreach ($files as $abs) {
    $rel = ltrim(str_replace($root, '', $abs), DIRECTORY_SEPARATOR);
    $raw = @file_get_contents($abs);
    if ($raw === false) {
        $errors[] = "I/O error: " . htmlspecialchars($rel, ENT_QUOTES, 'UTF-8');
        continue;
    }
    if (hasBom($raw)) {
        $errors[] = htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . ": BOM detected";
    }
    if (hasCloseToken($raw)) {
        $warns[] = htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') . ": PHP close token detected";
    }
}

$html = '';
$html .= '<!DOCTYPE html><html><head>';
$html .= '<meta charset="utf-8"><title>Plugin Syntax Self-Check</title>';
$html .= '<style>';
$html .= 'body{font:14px/1.45 system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px}';
$html .= 'a{color:#0366d6;text-decoration:none}a:hover{text-decoration:underline}';
$html .= '.box{margin:10px 0;padding:10px;border:1px solid #ddd}';
$html .= '.ok{background:#dcfce7;color:#166534}.warn{background:#fff7ed;color:#9a3412}.err{background:#fee2e2;color:#991b1b}';
$html .= '.info{background:#dbeafe;color:#1e3a8a}';
$html .= 'code{font:13px/1.4 ui-monospace,Consolas,monospace}';
$html .= '</style></head><body>';
$html .= '<h1>Plugin Syntax Self-Check</h1>';
$html .= '<p><a href="/wp-content/plugins/skipintro-recipe-crawler/tools/parser_lab/auto_eval_web.php">Auto-Eval</a> | ';
$html .= '<a href="/wp-content/plugins/skipintro-recipe-crawler/tools/parser_lab/run.php">Parser Lab</a></p>';

if (empty($errors)) {
    $html .= '<div class="box ok"><strong>OK:</strong> No syntax errors detected.</div>';
} else {
    foreach ($errors as $e) {
        $html .= '<div class="box err"><strong>ERROR:</strong> ' . $e . '</div>';
    }
}

foreach ($warns as $w) {
    $html .= '<div class="box warn"><strong>WARN:</strong> ' . $w . '</div>';
}

$html .= '<div class="box info"><strong>INFO:</strong> Files scanned: ' . count($files) . '</div>';
$html .= '</body></html>';

echo $html;
