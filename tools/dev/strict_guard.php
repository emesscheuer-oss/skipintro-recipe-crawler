<?php
/**
 * SkipIntro Recipe Crawler - Strict Guard (CLI)
 *
 * Policies:
 *  - tools/**           : FORBID declare(strict_types=1)
 *  - everything else    : ALLOW declare(strict_types=1) but, if present, it MUST be at line 2 (right after "<?php")
 *  - all *.php          : MUST be UTF-8 without BOM
 *  - all *.php          : MUST NOT use a real PHP close token (T_CLOSE_TAG)
 *      * In FIX mode we ONLY remove a trailing close token at EOF automatically.
 *        Any other in-file close token is REPORTED but NOT auto-removed.
 *
 * This version uses token_get_all() so strings/comments containing the sequence
 * are NOT falsely flagged. We also avoid writing the literal token in this file.
 */

$argv_list = isset($argv) ? $argv : array();
$MODE_FIX  = in_array('--fix', $argv_list, true);

// Build the PHP close token without writing it literally (editor-safe).
$PHP_CLOSE = '?' . '>';

echo "[strict_guard] Mode: " . ($MODE_FIX ? 'FIX' : 'CHECK') . PHP_EOL;

$ROOT = realpath(__DIR__ . '/../../');
if ($ROOT === false) {
    $ROOT = getcwd();
}

/**
 * Recursively collect *.php files under $base, skipping vendor/
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
        if (substr($path, -4) !== '.php') continue;
        if (strpos($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR) !== false) continue;
        $out[] = $path;
    }
    return $out;
}

/**
 * Detect first occurrence of declare(strict_types=1); and its line (1-based).
 * @param string $normalizedContent content with \n newlines and without BOM
 * @return array [bool hasDeclare, int|null declareLine]
 */
function sg_find_declare($normalizedContent) {
    $lines   = explode("\n", $normalizedContent);
    $has     = false;
    $lineN   = null;
    $pattern = '~^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;~';
    $count   = count($lines);
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
 * @param string $normalizedContent
 * @return array [string newContent, bool removed]
 */
function sg_remove_first_declare($normalizedContent) {
    $lines   = explode("\n", $normalizedContent);
    $pattern = '~^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;~';
    $removed = false;
    $out     = array();
    $count   = count($lines);
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
 * Remove trailing PHP close token only if it's the final token.
 * @param string $normalizedContent
 * @param string $phpClose
 * @return array [string newContent, bool removed]
 */
function sg_remove_trailing_close_token($normalizedContent, $phpClose) {
    $trimmed = rtrim($normalizedContent);
    $len     = strlen($phpClose);
    if ($len > 0 && substr($trimmed, -$len) === $phpClose) {
        $trimmed = rtrim(substr($trimmed, 0, -$len));
        if ($trimmed === '' || substr($trimmed, -1) !== "\n") {
            $trimmed .= "\n";
        }
        return array($trimmed, true);
    }
    return array($normalizedContent, false);
}

/**
 * Check if a string begins with UTF-8 BOM (EF BB BF)
 * @param string $raw
 * @return bool
 */
function sg_has_bom($raw) {
    return (strncmp($raw, "\xEF\xBB\xBF", 3) === 0);
}

/**
 * Normalize line endings to \n and strip BOM for analysis.
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

/**
 * Scan for REAL PHP close tokens using token_get_all().
 * Returns [bool hasAnyClose, bool endsWithClose]
 * - hasAnyClose: there is at least one T_CLOSE_TAG in tokens
 * - endsWithClose: the last non-whitespace/comment token is T_CLOSE_TAG
 *   (i.e., a trailing close at EOF)
 *
 * @param string $normalizedContent
 * @return array
 */
function sg_scan_close_tokens($normalizedContent) {
    $tokens = @token_get_all($normalizedContent);
    if (!is_array($tokens)) {
        return array(false, false);
    }
    $hasAny = false;

    // Find last non-whitespace/comment token id
    $lastMeaningful = null;

    $count = count($tokens);
    for ($i = 0; $i < $count; $i++) {
        $tok = $tokens[$i];
        if (is_array($tok)) {
            $id   = $tok[0];
            $text = $tok[1];
            if ($id === T_CLOSE_TAG) {
                $hasAny = true;
            }
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) {
                // skip for "meaningful"
            } else {
                $lastMeaningful = $id;
            }
        } else {
            // single-character tokens (like ; { } etc.)
            $char = $tok;
            // any non-space char counts as meaningful
            if (!ctype_space($char)) {
                $lastMeaningful = $char;
            }
        }
    }

    $endsWith = ($lastMeaningful === T_CLOSE_TAG);
    return array($hasAny, $endsWith);
}

/** MAIN **/

$phpFiles   = sg_collect_php_files($ROOT);
$issues     = array();
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

    $normInfo   = sg_normalize_for_analysis($raw);
    $normalized = $normInfo[0];
    $hadBOM     = $normInfo[1];

    // detect real close tokens
    $scan       = sg_scan_close_tokens($normalized);
    $hasAnyClose   = $scan[0];
    $endsWithClose = $scan[1];

    // declare detection
    $declareInfo = sg_find_declare($normalized);
    $hasDeclare  = $declareInfo[0];
    $declareLine = $declareInfo[1];

    if ($isTool) {
        // tools/** must NOT have declare(strict_types=1)
        if ($hasDeclare) {
            $issues[] = array($rel, 'forbidden_declare', 'tools/** must not declare(strict_types=1)');
            if ($MODE_FIX) {
                $rem          = sg_remove_first_declare($normalized);
                $normalized   = $rem[0];
                $removedDecl  = $rem[1];
                if ($removedDecl) {
                    $hasDeclare  = false;
                    $declareLine = null;
                    $fixedEdits++;
                }
            }
        }
    } else {
        // outside tools: if declare exists, it must be at line 2
        if ($hasDeclare && $declareLine !== 2) {
            $issues[] = array($rel, 'misplaced_declare', 'declare(strict_types=1) must be at line 2');
            if ($MODE_FIX) {
                $rem          = sg_remove_first_declare($normalized);
                $normalized   = $rem[0];
                $removedOnce  = $rem[1];
                if ($removedOnce) {
                    $ins          = sg_insert_declare_at_line2($normalized);
                    $normalized   = $ins[0];
                    $inserted     = $ins[1];
                    if ($inserted) {
                        $fixedEdits++;
                    }
                }
            }
        }
    }

    // Report REAL close tokens
    if ($hasAnyClose) {
        if ($endsWithClose) {
            $issues[] = array($rel, 'trailing_close_tag', 'Remove PHP close token at EOF');
            if ($MODE_FIX) {
                $rt          = sg_remove_trailing_close_token($normalized, $PHP_CLOSE);
                $normalized  = $rt[0];
                $removedCT   = $rt[1];
                if ($removedCT) {
                    $fixedEdits++;
                    // re-scan to see if other close tokens remain
                    $scan2 = sg_scan_close_tokens($normalized);
                    $hasAnyClose   = $scan2[0];
                    $endsWithClose = $scan2[1];
                }
            }
        }
        if ($hasAnyClose) {
            $issues[] = array($rel, 'php_close_tag_present', 'File contains a PHP close token (not auto-fixed)');
        }
    }

    // enforce: remove BOM
    if ($hadBOM) {
        $issues[] = array($rel, 'bom_detected', 'Remove UTF-8 BOM');
        if ($MODE_FIX) {
            // already stripped in $normalized
            $fixedEdits++;
        }
    }

    // write back if changed (always WITHOUT BOM, with \n newlines)
    if ($MODE_FIX) {
        $new = $normalized;
        if ($new !== $orig) {
            @file_put_contents($absPath, $new);
        }
    }
}

echo "[strict_guard] Scanned files: " . count($phpFiles) . PHP_EOL;

$issuesCount = count($issues);
echo "[strict_guard] Total issues: " . $issuesCount . PHP_EOL;
if ($MODE_FIX) {
    echo "[strict_guard] Fixed edits: " . $fixedEdits . PHP_EOL;
}

for ($i = 0; $i < $issuesCount; $i++) {
    $it  = $issues[$i]; // array(rel, code, msg)
    $rel = isset($it[0]) ? $it[0] : '';
    $code= isset($it[1]) ? $it[1] : '';
    $msg = isset($it[2]) ? $it[2] : '';
    echo ' - ' . $rel . ': ' . $code . ($msg !== '' ? ' (' . $msg . ')' : '') . PHP_EOL;
}

if ($issuesCount > 0 && !$MODE_FIX) {
    exit(1);
}
exit(0);
