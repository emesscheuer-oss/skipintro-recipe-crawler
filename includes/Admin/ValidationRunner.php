<?php

namespace SITC\Admin;

if (!defined('ABSPATH')) exit;

/**
 * ValidationRunner: orchestrates running parser lab fixtures inside WP.
 * - No frontend impact. Pure PHP utilities.
 */
class ValidationRunner
{
    /**
     * Run all fixtures and return structured report data.
     * @param string $engine 'legacy'|'mod'|'auto'
     * @return array{status:string, totals:array, engine:string, fixtures:array<int, array{name:string,status:string,messages:array<string>}>, env:array}
     */
    public static function run(string $engine = 'auto'): array
    {
        $t0 = microtime(true);

        // Raise execution time a bit for this page only
        if (!headers_sent()) {
            @set_time_limit(20);
        }

        // Optional engine switcher from parser.php (if available)
        if (function_exists('sitc_set_ing_engine')) {
            if (in_array($engine, ['legacy','mod','auto'], true)) {
                try { \sitc_set_ing_engine($engine); } catch (\Throwable $e) { /* ignore */ }
            }
        }

        // Include parser (needed in admin context) and parser_lab harness for robust, normalized execution
        $pluginRoot = plugin_dir_path(dirname(__DIR__, 1)); // .../wp-content/plugins/skipintro-recipe-crawler/
        $parserPhp = $pluginRoot . 'includes/parser.php';
        if (is_file($parserPhp)) { require_once $parserPhp; }
        $harness = $pluginRoot . 'tools/parser_lab/lib/harness.php';
        if (is_file($harness)) {
            require_once $harness;
        }

        $fixturesDir = $pluginRoot . 'tools/parser_lab/fixtures';

        $fixtures = [];
        $failures = 0; $warnings = 0; $total = 0;

        try {
            if (!is_dir($fixturesDir)) {
                // No fixtures is not an error per requirements
                $fixtures = [];
            } else {
                $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($fixturesDir, \FilesystemIterator::SKIP_DOTS));
                foreach ($rii as $fileInfo) {
                    /** @var \SplFileInfo $fileInfo */
                    if (!$fileInfo->isFile()) continue;
                    $ext = strtolower($fileInfo->getExtension());
                    if (!in_array($ext, ['html','json','jsonld'], true)) continue;
                    $fixtures[] = str_replace('\\', '/', $fileInfo->getRealPath());
                }
                sort($fixtures, SORT_NATURAL);
            }
        } catch (\Throwable $e) {
            // Treat as global failure if directory traversal itself fails
            $fixtures = [];
            $failures = 1;
        }

        $fixtureReports = [];
        foreach ($fixtures as $abs) {
            $total++;
            $messages = [];
            $status = 'ok';
            $name = pathinfo((string)$abs, PATHINFO_FILENAME);
            try {
                // Prefer harness helper when present
                if (function_exists('sitc_run_fixture')) {
                    $res = \sitc_run_fixture($abs, 'https://fixtures.local/' . basename((string)$abs));
                    $errs = is_array($res['errors'] ?? null) ? $res['errors'] : [];
                    $recipe = is_array($res['recipe'] ?? null) ? $res['recipe'] : [];
                    $ings = is_array($recipe['recipeIngredient'] ?? null) ? $recipe['recipeIngredient'] : [];

                    // Classify messages: errors vs warnings
                    foreach ($errs as $m) {
                        $msg = (string)$m;
                        if (stripos($msg, 'fallback:') === 0 || stripos($msg, 'hinweis:') === 0) {
                            $messages[] = $msg;
                        } else {
                            $messages[] = $msg;
                        }
                    }

                    // Determine status
                    if (!empty($errs)) {
                        // If only non-critical (fallback/hinweis) and we have items, call warn; else fail
                        $hasCritical = false;
                        foreach ($errs as $m2) {
                            $mm = strtolower((string)$m2);
                            if (strpos($mm, 'fallback:') === 0 || strpos($mm, 'hinweis:') === 0) continue;
                            $hasCritical = true; break;
                        }
                        if ($hasCritical) {
                            $status = 'fail';
                        } elseif (is_array($ings) && count($ings) > 0) {
                            $status = 'warn';
                        } else {
                            $status = 'fail';
                        }
                    } else {
                        // No errors: treat 0 ingredients as fail, else ok
                        if (!is_array($ings) || count($ings) === 0) {
                            $status = 'fail';
                            $messages[] = 'Keine Zutaten erkannt (recipeIngredient leer).';
                        }
                    }
                } else {
                    // Minimal fallback without harness
                    $raw = @file_get_contents($abs);
                    $ext = strtolower(pathinfo((string)$abs, PATHINFO_EXTENSION));
                    $html = in_array($ext, ['json','jsonld'], true)
                        ? "<!doctype html><meta charset='utf-8'>\n<script type=\"application/ld+json\">" . (string)$raw . "</script>"
                        : (string)$raw;
                    $out = function_exists('parseRecipe') ? \parseRecipe((string)$html, 'https://fixtures.local/' . basename((string)$abs)) : null;
                    $ings = is_array($out) ? ($out['recipe']['recipeIngredient'] ?? []) : [];
                    if (!is_array($ings) || count($ings) === 0) {
                        $status = 'fail';
                        $messages[] = 'Keine Zutaten erkannt (fallback runner).';
                    }
                }
            } catch (\Throwable $e) {
                $status = 'fail';
                $messages[] = sprintf('Exception %s: %s', get_class($e), (string)$e->getMessage());
            }

            if ($status === 'fail') $failures++;
            if ($status === 'warn') $warnings++;

            $fixtureReports[] = [
                'name' => (string)$name,
                'status' => (string)$status,
                'messages' => array_values(array_map('strval', $messages)),
            ];
        }

        $t1 = microtime(true);
        $durationMs = (int)round(($t1 - $t0) * 1000);

        $statusTop = ($failures > 0) ? 'fail' : 'ok';

        return [
            'status' => $statusTop,
            'totals' => [
                'total' => (int)$total,
                'failures' => (int)$failures,
                'warnings' => (int)$warnings,
                'duration_ms' => (int)$durationMs,
            ],
            'engine' => in_array($engine, ['legacy','mod','auto'], true) ? $engine : 'auto',
            'fixtures' => $fixtureReports,
            'env' => [
                'php' => PHP_VERSION,
                'wp' => (defined('WP_VERSION') ? WP_VERSION : (function_exists('get_bloginfo') ? (string)get_bloginfo('version') : 'unknown')),
            ],
        ];
    }
}
