<?php
// Web-based syntax self-check (no strict_types)

require_once __DIR__ . '/../parser_lab/_ui_helpers.php';

function sitc_sc_html_head(string $title): void {
    echo "<!doctype html><meta charset='utf-8'><title>" . sitc_html($title) . "</title>";
    echo '<style>body{font:14px/1.45 system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px;}a{color:#0366d6;text-decoration:none;}a:hover{text-decoration:underline;}code{font:13px/1.4 ui-monospace,Consolas,monospace;}</style>';
}

$toolsDir = dirname(__DIR__);
$pluginRoot = dirname($toolsDir);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS));
$errors = array();
$warnings = array();
$scanned = 0;

$forbiddenStrictPrefixes = array('tools/', 'includes/renderer/', 'includes/admin/', 'includes/Admin/');
$allowedStrictPrefixes = array('includes/ingredients/', 'includes/parser.php', 'includes/parser_helpers.php');

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) {
        continue;
    }
    if (substr($file->getFilename(), -4) !== '.php') {
        continue;
    }
    $scanned++;
    $path = $file->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($pluginRoot) + 1));
    $contents = @file_get_contents($path);
    if ($contents === false) {
        $errors[] = array($rel, 'unreadable');
        continue;
    }

    // BOM
    if (strncmp($contents, "\xEF\xBB\xBF", 3) === 0) {
        $errors[] = array($rel, 'BOM detected');
        $contentsWithoutBom = substr($contents, 3);
    } else {
        $contentsWithoutBom = $contents;
    }

    // Leading whitespace before <?php
    if ($contentsWithoutBom !== '' && strpos($contentsWithoutBom, '<?php') !== 0) {
        $trimmed = ltrim($contentsWithoutBom);
        if ($trimmed !== $contentsWithoutBom) {
            $errors[] = array($rel, 'Whitespace before <?php');
        } elseif (strpos($trimmed, '<?php') !== 0) {
            // file without PHP tag: ignore
        }
    }

    // Arrow functions
    if (preg_match('/\bfn\s*\(/', $contents)) {
        $errors[] = array($rel, 'Arrow function (fn) found');
    }

    // match expression
    if (preg_match('/\bmatch\s*\(/', $contents)) {
        $errors[] = array($rel, 'match expression found');
    }

    // Array unpack
    if (preg_match('/\.\.\.\s*\[/u', $contents) || preg_match('/\[\s*\.\.\./u', $contents)) {
        $errors[] = array($rel, 'Array unpack (spread) found');
    }

    // Named arguments (heuristic)
    if (preg_match('/[(,]\s*[A-Za-z_]\w*\s*:\s*\$[A-Za-z_]/', $contents)) {
        $errors[] = array($rel, 'Named argument syntax found');
    }

    // PHP close tag
    if (preg_match('/\?>/', $contents)) {
        $warnings[] = array($rel, 'PHP close tag detected');
    }

    // strict_types policy
    if (preg_match('/declare\s*\(\s*strict_types\s*=\s*1\s*\)/', $contents)) {
        $allowed = false;
        foreach ($allowedStrictPrefixes as $prefix) {
            if (strpos($rel, $prefix) === 0 || $rel === $prefix) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            foreach ($forbiddenStrictPrefixes as $prefix) {
                if (strpos($rel, $prefix) === 0) {
                    $errors[] = array($rel, 'strict_types not allowed here');
                    $allowed = null;
                    break;
                }
            }
            if ($allowed !== null && !$allowed) {
                $errors[] = array($rel, 'strict_types usage needs review');
            }
        } else {
            // Must appear immediately after <?php
            $normalized = $contentsWithoutBom;
            if (!preg_match('/^<\?php\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/', $normalized)) {
                $errors[] = array($rel, 'strict_types not first statement');
            }
        }
    }
}

sitc_sc_html_head('Self-Check');

echo '<h1>Plugin Syntax Self-Check</h1>';
echo '<p><a href="../parser_lab/auto_eval_web.php">Auto-Eval</a> | <a href="../parser_lab/run.php">Parser Lab</a></p>';

if ($errors) {
    foreach ($errors as $item) {
        sitc_error($item[0] . ': ' . $item[1]);
    }
} else {
    sitc_ok('No syntax errors detected.');
}

if ($warnings) {
    foreach ($warnings as $item) {
        sitc_warn($item[0] . ': ' . $item[1]);
    }
}

sitc_info('Files scanned: ' . $scanned);
