<?php
/**
 * tools/dev/strict_guard.php (v4c)
 * - Scans project PHP files for:
 *     1) Any PHP close token  "?>"   (policy: disallowed everywhere)
 *     2) Any declare(strict_types=1) (policy: disallowed everywhere)
 * - Modes:
 *     php tools/dev/strict_guard.php           → CHECK
 *     php tools/dev/strict_guard.php --fix     → FIX (auto-clean)
 *
 * Notes:
 * - UTF-8 (no BOM), no declare(), no closing tag at EOF.
 * - Uses token_get_all for reliable detection.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$ROOT = getcwd();
$MODE = in_array('--fix', $argv ?? []) ? 'FIX' : 'CHECK';

echo "[strict_guard] Mode: {$MODE}\n";

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

function sg_is_php_file(string $path): bool {
    return (bool)preg_match('/\.php$/i', $path);
}
function sg_should_skip(string $rel): bool {
    // Skip common vendor/build folders if present
    $skipDirs = ['vendor', 'node_modules', '.git', '.idea', '.vscode'];
    foreach ($skipDirs as $sd) {
        if (stripos($rel, '/'.$sd.'/') !== false || stripos($rel, '\\'.$sd.'\\') !== false) return true;
    }
    return false;
}
function sg_read_file(string $path): string {
    $buf = @file_get_contents($path);
    return is_string($buf) ? $buf : '';
}
function sg_write_file(string $path, string $data): bool {
    $ok = @file_put_contents($path, $data);
    return ($ok !== false);
}
function sg_rel(string $root, string $path): string {
    $p = str_replace('\\', '/', $path);
    $r = str_replace('\\', '/', rtrim($root, '\\/'));
    return (strpos($p, $r.'/') === 0) ? substr($p, strlen($r)+1) : $p;
}

// -----------------------------------------------------------------------------
// Collect files
// -----------------------------------------------------------------------------

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($ROOT, FilesystemIterator::SKIP_DOTS));
$files = [];
foreach ($rii as $fileInfo) {
    /** @var SplFileInfo $fileInfo */
    $p = $fileInfo->getPathname();
    if (!sg_is_php_file($p)) continue;
    $rel = sg_rel($ROOT, $p);
    if (sg_should_skip($rel)) continue;
    $files[] = $p;
}

$issues = [];
$fixedEdits = 0;

// Pre-built pattern for removing declare(strict_types=1) safely (optional whitespace, case-insensitive).
$declarePattern = '~\bdeclare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;~i';
$openTag   = '<' . '?php'; // avoid writing the literal in one piece
$closeTag  = '?' . '>';    // avoid writing the literal in one piece

// -----------------------------------------------------------------------------
// Scan & optionally fix
// -----------------------------------------------------------------------------

foreach ($files as $path) {
    $src = sg_read_file($path);
    $rel = sg_rel($ROOT, $path);
    if ($src === '') continue; // unreadable → silently skip

    // Token scan (PHP needs open tag to tokenize); prepend if missing only for analysis.
    $probe = (strpos($src, $openTag) === false) ? ($openTag . "\n" . $src) : $src;
    $tokens = @token_get_all($probe);
    if (!is_array($tokens)) $tokens = [];

    $hasClose = false;
    $hasStrict = false;

    $len = count($tokens);
    for ($i = 0; $i < $len; $i++) {
        $t = $tokens[$i];
        if (is_string($t)) {
            if ($t === $closeTag) { $hasClose = true; }
            continue;
        }
        $id = $t[0];
        $text = $t[1];

        if ($id === T_CLOSE_TAG) { $hasClose = true; }
        if ($id === T_DECLARE) {
            // Parse small window to find "strict_types"
            $window = '';
            for ($j = $i; $j < min($i+8, $len); $j++) {
                $window .= is_string($tokens[$j]) ? $tokens[$j] : $tokens[$j][1];
            }
            if (preg_match('~strict_types\s*=\s*1~i', $window)) {
                $hasStrict = true;
            }
        }
    }

    // Register issues + optional fixes
    if ($hasClose) {
        $issues[] = [$rel, 'php_close_tag_present', 'File contains a PHP close token'];
        if ($MODE === 'FIX') {
            // Remove ALL closing tags safely
            $new = str_replace($closeTag, '', $src);
            if ($new !== $src) {
                if (sg_write_file($path, $new)) { $fixedEdits++; $src = $new; }
            }
        }
    }
    if ($hasStrict) {
        $issues[] = [$rel, 'strict_types_present', 'declare(strict_types=1) found'];
        if ($MODE === 'FIX') {
            $new = preg_replace($declarePattern, '', $src);
            if ($new !== null && $new !== $src) {
                if (sg_write_file($path, $new)) { $fixedEdits++; $src = $new; }
            }
        }
    }

    // After edits in FIX mode: normalize EOLs, drop BOM, keep single trailing newline
    if ($MODE === 'FIX' && $fixedEdits > 0) {
        $norm = str_replace(["\r\n", "\r"], "\n", $src);
        if (strncmp($norm, "\xEF\xBB\xBF", 3) === 0) $norm = substr($norm, 3);
        $norm = rtrim($norm) . "\n";
        if ($norm !== $src) {
            sg_write_file($path, $norm);
        }
    }
}

// -----------------------------------------------------------------------------
// Report
// -----------------------------------------------------------------------------

echo "[strict_guard] Scanned files: " . count($files) . "\n";
echo "[strict_guard] Total issues: " . count($issues) . "\n";

foreach ($issues as [$rel, $code, $msg]) {
    echo " - {$rel}: {$code} ({$msg})\n";
}
if ($MODE === 'FIX') {
    echo "[strict_guard] Fixed edits: {$fixedEdits}\n";
}

exit(empty($issues) ? 0 : 1);
