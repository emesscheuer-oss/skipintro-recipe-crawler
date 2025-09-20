<?php

// Simple guard to ensure CHANGELOG.md is newest-first and newest block on top.

$root = realpath(__DIR__ . '/..' . '/..') ?: dirname(__DIR__, 2);
$file = $root . '/CHANGELOG.md';
if (!is_file($file)) {
    fwrite(STDERR, "CHANGELOG.md not found at $file\n");
    exit(1);
}
$lines = file($file, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "Failed to read $file\n");
    exit(1);
}

// Parse headers like: ## [x.y.z] - YYYY-MM-DD  OR  ## [x.y.z] – YYYY-MM-DD
$re = '/^## \[(\d+)\.(\d+)\.(\d+)\] [\-\xE2\x80\x93] (\d{4})-(\d{2})-(\d{2})\s*$/u';
$headers = [];
for ($i = 0; $i < count($lines); $i++) {
    $m = [];
    if (preg_match($re, $lines[$i], $m)) {
        $ver = [ (int)$m[1], (int)$m[2], (int)$m[3] ];
        $date = sprintf('%04d-%02d-%02d', (int)$m[4], (int)$m[5], (int)$m[6]);
        $headers[] = [
            'line' => $i,
            'version' => $ver,
            'date' => $date,
            'raw' => $lines[$i],
        ];
    }
}

if (!$headers) {
    fwrite(STDERR, "No version headers found in CHANGELOG.md\n");
    exit(1);
}

// Compare by date desc, then version desc
$sorted = $headers;
usort($sorted, function($a, $b) {
    if ($a['date'] !== $b['date']) return strcmp($b['date'], $a['date']);
    // version compare: major, minor, patch desc
    for ($i = 0; $i < 3; $i++) {
        if ($a['version'][$i] !== $b['version'][$i]) return $b['version'][$i] <=> $a['version'][$i];
    }
    return 0;
});

$ok = true;
// Newest must be first header in file
if ($headers[0]['raw'] !== $sorted[0]['raw']) {
    fwrite(STDERR, "Newest changelog block is not on top.\n");
    fwrite(STDERR, "Top:    {$headers[0]['raw']}\n");
    fwrite(STDERR, "Newest: {$sorted[0]['raw']}\n");
    $ok = false;
}

// Ensure full order is newest-first
for ($i = 0; $i < count($headers); $i++) {
    if ($headers[$i]['raw'] !== $sorted[$i]['raw']) {
        fwrite(STDERR, "Out-of-order entry at index $i:\n  Found:    {$headers[$i]['raw']}\n  Expected: {$sorted[$i]['raw']}\n");
        $ok = false;
        break;
    }
}

if (!$ok) exit(1);
echo "Changelog order OK (newest-first).\n";
exit(0);

