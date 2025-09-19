<?php
/**
 * SkipIntro Recipe Crawler - Strict Guard (CLI)
 *
 * Policies:
 *  - tools/**           : FORBID declare(strict_types=1)
 *  - everything else    : ALLOW declare(strict_types=1) but if present it MUST be at line 2 (right after "<?php")
 *  - all *.php          : MUST NOT end with "?>"
 *  - all *.php          : MUST be UTF-8 without BOM
 *
 * Usage:
 *   php tools/dev/strict_guard.php        # Check
 *   php tools/dev/strict_guard.php --fix  # Auto-fix
 *
 * Exit codes:
 *   0 = OK/no issues (or all fixed in --fix mode)
 *   1 = Issues found (in check mode)
 */

echo "[strict_guard] Mode: " . (in_array('--fix', isset($argv) ? $argv : array(), true) ? 'FIX' : 'CHECK') . PHP_EOL;

$MODE_FIX = in_array('--fix', isset($argv) ? $argv : array(), true);
$ROOT     = realpath(__DIR__ . '/../../');
if ($ROOT === false) {
    $ROOT = getcwd();
}

/**
 * Recursively collect *.php files under $base, skipping vendor/
 *
 * @param string $base
 * @return array
 */
function sg_collect_php_files($base) {
    $out = array();
    $it  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        $path = $file->getPathname();
        if (substr($path, -4) !== '.php') {
            continue;
        }
        if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) {
            continue;
        }
        $out[] = $path;
    }
    return $out;
}

/**
 * Detect first occurrence of declare(strict_types=1); and its line (1-based).
 *
 * @param string $normalizedContent  content with \n newlines and without BOM
 * @return array [bool hasDeclare, int|null declareLine]
 */
function sg_find_declare($normalizedContent) {
    $lines = explode("\n", $normalizedContent);
    $has   = false;
    $lineN = null;
    $pattern = '~^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;~';
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
        if (preg_match($pattern, $lines[$i])) {
            $has   = true;
            $lineN = $i + 1; // 1-based
            break;
        }
    }
    return array($has, $lineN);
}

/**
 * Remove first declare(strict_types=1); line from content (normalized \n)
 *
 * @param string $normalizedContent
 * @return array [string newContent, bool removed]
 */
function sg_remove_first_declare($normalizedContent) {
    $lines = explode("\n", $normalizedContent);
    $pattern = '~^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;~';
    $removed = false;
    $out = array();
    $count = count($lines);
    for ($i = 0; $i < $count; $i++) {
        if (!$removed && preg_match($pattern, $lines[$i])) {
            $removed = true;
            continue;
        }
        $out[] = $lines[$i];
    }
    return array(implode("\n", $out), $removed);
}

/**
 * Insert declare(strict_types=1); at line 2 (after opening <?php), if the first line starts with <?php
 *
 * @param string $normalizedContent
 * @return array [string newContent, bool inserted, string|null err]
 */
function sg_insert_declare_at_line2($normalizedContent) {
    $lines = explode("\n", $normalizedContent);
    if (!isset($lines[0]) || !preg_match('~^\s*<\?php\b~', $lines[0])) {
        return array($normalizedContent, false, 'no_open_tag');
    }
    array_splice($lines, 1, 0, 'declare(strict_types=1);');
    return array(implode("\n", $lines), true, null);
}

/**
 * Trim trailing whitespace and remove a closing "?>" at end if present.
 *
 * @param string $normalizedContent
 * @return array [string newContent, bool removed]
 */
function sg_remove_trailing_close_tag($normalizedContent) {
    $trimmed = rtrim($normalizedContent);
    if (substr($trimmed, -2) === '?>') {
        $trimmed = rtrim(substr($trimmed, 0, -2));
        // ensure newline at EOF
        if ($trimmed === '' || substr($trimmed, -1) !== "\n") {
            $trimmed .= "\n";
        } else {
            // already newline
        }
        return array($trimmed, true);
    }
    return array($normalizedContent, false);
}

/**
 * Check if a string begins with UTF-8 BOM (EF BB BF)
 *
 * @param string $raw
 * @return bool
 */
function sg_has_bom($raw) {
    return (strncmp($raw, "\xEF\xBB\xBF", 3) === 0);
}

/**
 * Normalize line endings to \n and strip BOM for analysis.
 *
 * @param string $raw
 * @return array [string normalized, bool hadBOM]
 */
function sg_normalize_for_analysis($raw) {
    $hadBOM = sg_has_bom($raw);
    if ($hadBOM) {
        $raw = substr($raw, 3);
    }
    $normalized = str_replace(array("\r\n", "\r"), "\n", $raw);
    return array($normalized, $hadBOM);
}

/** MAIN **/

$phpFiles = sg_collect_php_files($ROOT);
$issues   = array();
$fixedEdits = 0;

foreach ($phpFiles as $absPath) {
    $rel = ltrim(str_replace($ROOT, '', $absPath), DIRECTORY_SEPARATOR);
    $isTool = (strpos($rel, 'tools' . DIRECTORY_SEPARATOR) === 0);

    $raw = @file_get_contents($absPath);
    if ($raw === false) {
        $issues[] = array($rel, 'io_error', 'Could not read file');
        continue;
    }
    $orig = $raw;

    list($normalized, $hadBOM) = sg_normalize_for_analysis($raw);

    // closing tag at EOF?
    $endsWithCloseTag = false;
    $tmpTrim = rtrim($normalized);
    if (substr($tmpTrim, -2) === '?>') {
        $endsWithCloseTag = true;
    }

    // declare detection
    list($hasDeclare, $declareLine) = sg_find_declare($normalized);

    if ($isTool) {
        // tools/** must NOT have declare(strict_types=1)
        if ($hasDeclare) {
            $issues[] = array($rel, 'forbidden_declare', 'tools/** must not declare(strict_types=1)');
            if ($MODE_FIX) {
                list($normalized2, $removed) = sg_remove_first_declare($normalized);
                if ($removed) {
                    $normalized = $normalized2;
                    $hasDeclare = false;
                    $declareLine = null;
                    $fixedEdits++;
                }
            }
        }
    } else {
        // outside tools: if declare exists, it must be at line 2
        if ($hasDeclare) {
            if ($declareLine !== 2) {
                $issues[] = array($rel, 'misplaced_declare', 'declare(strict_types=1) must be at line 2');
                if ($MODE_FIX) {
                    list($normalized2, $removedOnce) = sg_remove_first_declare($normalized);
                    if ($removedOnce) {
                        list($normalized3, $inserted, $err) = sg_insert_declare_at_line2($normalized2);
                        if ($inserted) {
                            $normalized = $normalized3;
                            $fixedEdits++;
                        } else {
                            // could not safely insert
                            $normalized = $normalized2; // at least removed misplaced
                        }
                    }
                }
            }
        }
    }

    // enforce: no closing tag at EOF
    if ($endsWithCloseTag) {
        $issues[] = array($rel, 'trailing_close_tag', 'Remove closing "?>" at EOF');
        if ($MODE_FIX) {
            list($normalized2, $removedCT) = sg_remove_trailing_close_tag($normalized);
            if ($removedCT) {
                $normalized = $normalized2;
                $fixedEdits++;
            }
        }
    }

    // enforce: remove BOM
    if ($hadBOM) {
        $issues[] = array($rel, 'bom_detected', 'Remove UTF-8 BOM');
        if ($MODE_FIX) {
            // already stripped for $normalized
            $fixedEdits++;
        }
    }

    // write back if changed
    if ($MODE_FIX) {
        // Always write WITHOUT BOM, with \n endings
        $new = $normalized;
        if ($new !== $orig) {
            @file_put_contents($absPath, $new);
        }
    }
}

echo "[strict_guard] Scanned files: " . count($phpFiles) . PHP_EOL;
echo "[strict_guard] Total issues: " . count($issues) . PHP_EOL;
if ($MODE_FIX) {
    echo "[strict_guard] Fixed edits: " . $fixedEdits . PHP_EOL;
}

for ($i = 0; $i < count($issues); $i++) {
    $it = $issues[$i]; // array(rel, code, msg)
    $rel = isset($it[0]) ? $it[0] : '';
    $code = isset($it[1]) ? $it[1] : '';
    $msg  = isset($it[2]) ? $it[2] : '';
    echo ' - ' . $rel . ': ' . $code . ($msg !== '' ? ' (' . $msg . ')' : '') . PHP_EOL;
}

if (count($issues) > 0 && !$MODE_FIX) {
    exit(1);
}
exit(0);
