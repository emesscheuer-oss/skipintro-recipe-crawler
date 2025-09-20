<?php
if (!defined('ABSPATH')) exit;

/**
 * Lightweight parser debug logging (dev-only).
 * - Active when WP_DEBUG or SITC_DEV_MODE or sitc_is_dev_mode() is true
 * - Writes to wp-content/uploads/skipintro-recipe-crawler/debug.log
 * - Rotates at ~1MB by moving to debug_old.log
 */
function sitc_parser_debug_logging_enabled(): bool {
    if (defined('WP_DEBUG') && WP_DEBUG) return true;
    if (defined('SITC_DEV_MODE') && SITC_DEV_MODE) return true;
    if (function_exists('sitc_is_dev_mode') && sitc_is_dev_mode()) return true;
    return false;
}

function sitc_parser_debug_log(array $fields): void {
    try {
        if (!sitc_parser_debug_logging_enabled()) return;
        $u = wp_upload_dir();
        if (empty($u['basedir'])) return;
        $dir = rtrim($u['basedir'], '/\\') . '/skipintro-recipe-crawler';
        if (!is_dir($dir)) { @wp_mkdir_p($dir); }
        if (!is_dir($dir) || !is_writable($dir)) return;

        $file = $dir . '/debug.log';
        // Rotate when >1MB
        if (is_file($file) && filesize($file) > 1024*1024) {
            @copy($file, $dir . '/debug_old.log');
            @unlink($file);
        }
        $ts = date('Y-m-d H:i:s');
        $pairs = [];
        $fields = array_change_key_case($fields, CASE_LOWER);
        // Normalize known keys
        $map = [ 'engine','post_id','fixture','exception','message','phase','fallback_triggered' ];
        foreach ($map as $k) {
            if (array_key_exists($k, $fields)) {
                $v = $fields[$k];
                if (is_bool($v)) $v = $v ? 'true' : 'false';
                if (is_array($v) || is_object($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
                $v = (string)$v;
                // quote only message/exception
                if ($k === 'message' || $k === 'exception') {
                    $pairs[] = $k.'="'.str_replace('"','\"',$v).'"';
                } else {
                    $pairs[] = $k.'='.$v;
                }
            }
        }
        // Include any additional fields
        foreach ($fields as $k=>$v) {
            if (in_array($k, $map, true)) continue;
            if ($k === '' || is_int($k)) continue;
            if (is_bool($v)) $v = $v ? 'true' : 'false';
            if (is_array($v) || is_object($v)) $v = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $v = (string)$v;
            $pairs[] = $k.'='.$v;
        }
        $line = '['.$ts.'] '.implode(' ', $pairs)."\n";
        @file_put_contents($file, $line, FILE_APPEND);
    } catch (Throwable $e) {
        // Never break runtime for logging; log to PHP error log only
        error_log('SITC parser debug log error: ' . $e->getMessage());
    }
}

/**
 * Read last lines from debug log (tail), bounded by bytes and line count.
 * @return string[] lines without trailing newlines; [] on error/inactive
 */
function sitc_debug_tail(int $max_lines = 50, int $max_bytes = 10240): array {
    try {
        if (!sitc_parser_debug_logging_enabled()) return [];
        $u = wp_upload_dir();
        if (empty($u['basedir'])) return [];
        $file = rtrim($u['basedir'], '/\\') . '/skipintro-recipe-crawler/debug.log';
        if (!is_file($file) || !is_readable($file)) return [];
        $size = filesize($file);
        if ($size === false || $size <= 0) return [];
        $read = min($size, max(1024, $max_bytes));
        $fp = @fopen($file, 'rb');
        if (!$fp) return [];
        if ($read < $size) { @fseek($fp, -$read, SEEK_END); }
        $chunk = @fread($fp, $read);
        @fclose($fp);
        if ($chunk === false) return [];
        // Normalize line endings and split
        $chunk = str_replace("\r\n", "\n", $chunk);
        $chunk = str_replace("\r", "\n", $chunk);
        $lines = explode("\n", trim($chunk));
        // Keep only the last N non-empty lines conservatively
        $lines = array_values(array_filter($lines, function($s){ return $s !== ''; }));
        if (count($lines) > $max_lines) {
            $lines = array_slice($lines, -$max_lines);
        }
        return $lines;
    } catch (Throwable $e) {
        error_log('SITC parser debug tail error: ' . $e->getMessage());
        return [];
    }
}
