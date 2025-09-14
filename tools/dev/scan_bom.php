<?php
// Dev utility: scan plugin tree for UTF-8 BOM or leading whitespace before opening tag and for closing PHP tags at EOF.
// Run from CLI or via browser in a safe dev environment. Do NOT load in production.

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__, 1);
$root = realpath($root . '/..'); // plugin root

if (!$root || !is_dir($root)) {
    echo "Root not found\n";
    exit(1);
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$issues = [];

foreach ($rii as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php') continue;
    // Skip vendor-like or archive dirs if present
    if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;

    $contents = file_get_contents($path);
    if ($contents === false) continue;

    // Check BOM (UTF-8 BOM is \xEF\xBB\xBF)
    $hasBOM = strncmp($contents, "\xEF\xBB\xBF", 3) === 0;

    // Leading whitespace before first opening tag
    $leading = '';
    if (preg_match('/^\s*(<\?php|<\?=)/', $contents, $m) === 1) {
        $prefix = substr($contents, 0, strpos($contents, $m[1]));
        $leading = $prefix;
    } else {
        // No opening tag at start
        if (preg_match('/^\s+/', $contents)) $leading = 'text before php tag';
    }

    // Closing tag at EOF
    $hasClosing = false;
    if (preg_match('/\?>\s*$/', $contents) === 1) {
        $hasClosing = true;
    }

    if ($hasBOM || ($leading !== '') || $hasClosing) {
        $issues[] = [
            'path' => $path,
            'bom' => $hasBOM,
            'leading' => $leading !== '',
            'closing' => $hasClosing,
        ];
    }
}

if (!$issues) {
    echo "No BOM/leading whitespace/EOF closing tag issues found.\n";
    exit(0);
}

foreach ($issues as $i) {
    echo ($i['path']) . "\n";
    if ($i['bom']) echo "  - BOM: YES\n";
    if ($i['leading']) echo "  - Leading whitespace before opening tag: YES\n";
    if ($i['closing']) echo "  - Closing PHP tag at EOF: YES\n";
}

exit(0);

