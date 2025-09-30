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


add_action('wp', function () {
    if (!isset($_GET['sitc_reparse_preview']) || $_GET['sitc_reparse_preview'] !== '1') return;
    if (!is_singular()) return;

    $post_id = get_queried_object_id();
    if (!$post_id) return;

    // 1) Rohzeilen beschaffen: bevorzugt aus Schema.org recipeIngredient
    $rawLines = [];
    $schema_json = get_post_meta($post_id, '_sitc_schema_recipe_json', true);
    if (is_string($schema_json) && $schema_json !== '') {
        $schema = json_decode($schema_json, true);
        if (is_array($schema) && !empty($schema['recipeIngredient'])) {
            $src = $schema['recipeIngredient'];
            if (!is_array($src)) $src = [$src];
            foreach ($src as $entry) {
                // entry kann String oder Array sein
                if (is_array($entry)) {
                    if (isset($entry['raw']) && is_string($entry['raw'])) {
                        $t = trim($entry['raw']);
                    } else {
                        // best effort Rekonstruktion
                        $parts = [];
                        if (isset($entry['qty']) && $entry['qty'] !== '' && $entry['qty'] !== null) $parts[] = (string)$entry['qty'];
                        if (isset($entry['unit']) && $entry['unit'] !== '' && $entry['unit'] !== null) $parts[] = (string)$entry['unit'];
                        if (isset($entry['item']) && is_string($entry['item'])) $parts[] = $entry['item'];
                        elseif (isset($entry['name']) && is_string($entry['name'])) $parts[] = $entry['name'];
                        $t = trim(implode(' ', $parts));
                    }
                } else {
                    $t = trim((string)$entry);
                }
                if ($t !== '') $rawLines[] = $t;
            }
        }
    }

    // 2) Fallback: wenn nichts im Schema, versuche gruppierte Meta (falls deine Seite die nutzt)
    if (empty($rawLines) && function_exists('sitc_build_ingredient_groups_for_render')) {
        try {
            $groups = sitc_build_ingredient_groups_for_render($post_id);
            foreach ((array)$groups as $g) {
                foreach ((array)($g['items'] ?? []) as $it) {
                    if (is_string($it)) { $rawLines[] = $it; continue; }
                    if (is_array($it)) {
                        // best effort Rekonstruktion
                        $parts = [];
                        if (!empty($it['qty']))  $parts[] = is_scalar($it['qty']) ? (string)$it['qty'] : '';
                        if (!empty($it['unit'])) $parts[] = is_scalar($it['unit']) ? (string)$it['unit'] : '';
                        if (!empty($it['name'])) $parts[] = (string)$it['name'];
                        elseif (!empty($it['item'])) $parts[] = (string)$it['item'];
                        $t = trim(implode(' ', array_filter($parts, fn($s)=>$s!=='')));
                        if ($t !== '') $rawLines[] = $t;
                    }
                }
            }
        } catch (Throwable $e) { /* ignore */ }
    }

    // 3) Jetzt jede Zeile mit v2 + Normalizer testen (ohne zu speichern)
    $parsed = [];
    foreach ($rawLines as $line) {
        $res = null;
        if (function_exists('sitc_ing_v2_parse_line')) {
            $res = @sitc_ing_v2_parse_line($line, 'de');
        } elseif (function_exists('sitc_ing_parse_line_mod')) {
            $res = @sitc_ing_parse_line_mod($line, 'de');
        }
        if (is_array($res)) {
            $parsed[] = $res + ['raw'=>$line];
        } else {
            $parsed[] = ['raw'=>$line, 'item'=>$line, 'qty'=>null, 'unit'=>null, 'note'=>null];
        }
    }

    // 4) Ausgabe: Log + sichtbarer PRE-Block (nur zur Vorschau)
    error_log('[SITC REPARSE PREVIEW post '.$post_id.'] raw='.json_encode($rawLines, JSON_UNESCAPED_UNICODE));
    error_log('[SITC REPARSE PREVIEW post '.$post_id.'] parsed='.json_encode($parsed, JSON_UNESCAPED_UNICODE));

    header('Content-Type: text/html; charset=utf-8');
    echo "<pre style='white-space:pre-wrap; font: 12px/1.4 monospace; background:#111; color:#eee; padding:12px; border-radius:8px'>";
    echo "SITC REPARSE PREVIEW (post {$post_id})\n\n";
    echo "RAW LINES:\n";
    foreach ($rawLines as $r) echo "  - ".htmlspecialchars($r, ENT_QUOTES, 'UTF-8')."\n";
    echo "\nPARSED (v2 + Normalizer):\n";
    foreach ($parsed as $p) {
        $line = trim(
            (isset($p['qty'])  && $p['qty']  !== '' && $p['qty']  !== null ? (string)$p['qty'].' ' : '') .
            (isset($p['unit']) && $p['unit'] !== '' && $p['unit'] !== null ? (string)$p['unit'].' ' : '') .
            (isset($p['item']) && is_string($p['item']) ? $p['item'] : '')
        );
        if (!empty($p['note'])) $line .= ' ('.$p['note'].')';
        echo "  → ".htmlspecialchars($line, ENT_QUOTES, 'UTF-8')."\n";
    }
    echo "</pre>";
    exit; // wichtig: kein Theme-Render
});

add_action('wp', function () {
    if (!isset($_GET['sitc_repair_ing']) || $_GET['sitc_repair_ing'] !== '1') return;
    if (!is_singular()) return;

    $post_id = get_queried_object_id();
    if (!$post_id) return;

    // 1) Schema laden
    $schema_key = '_sitc_schema_recipe_json';
    $old_json = get_post_meta($post_id, $schema_key, true);
    if (!is_string($old_json) || $old_json === '') {
        wp_die('SITC: Kein Schema gefunden (' . esc_html($schema_key) . ').');
    }
    $schema = json_decode($old_json, true);
    if (!is_array($schema)) {
        wp_die('SITC: Schema ist kein JSON-Objekt.');
    }

    // 2) RAW-Zeilen ermitteln
    $rawLines = [];
    if (!empty($schema['recipeIngredient'])) {
        $src = is_array($schema['recipeIngredient']) ? $schema['recipeIngredient'] : [$schema['recipeIngredient']];
        foreach ($src as $entry) {
            if (is_array($entry)) {
                if (isset($entry['raw']) && is_string($entry['raw'])) {
                    $t = trim($entry['raw']);
                } else {
                    // best effort Rekonstruktion
                    $parts = [];
                    if (isset($entry['qty']) && $entry['qty'] !== '' && $entry['qty'] !== null) $parts[] = (string)$entry['qty'];
                    if (isset($entry['unit']) && $entry['unit'] !== '' && $entry['unit'] !== null) $parts[] = (string)$entry['unit'];
                    if (!empty($entry['item']) && is_string($entry['item']))      $parts[] = $entry['item'];
                    elseif (!empty($entry['name']) && is_string($entry['name'])) $parts[] = $entry['name'];
                    $t = trim(implode(' ', $parts));
                }
            } else {
                $t = trim((string)$entry);
            }
            if ($t !== '') $rawLines[] = $t;
        }
    }

    if (empty($rawLines)) {
        wp_die('SITC: Keine RAW-Zeilen gefunden (recipeIngredient leer).');
    }

    // 3) Re-Parse mit v2 + Normalizer (ohne Qty/Unit-Logik anzurühren)
    $parsed = [];
    foreach ($rawLines as $line) {
        $res = null;
        if (function_exists('sitc_ing_v2_parse_line')) {
            $res = @sitc_ing_v2_parse_line($line, 'de');
        } elseif (function_exists('sitc_ing_parse_line_mod')) {
            $res = @sitc_ing_parse_line_mod($line, 'de');
        }
        if (is_array($res)) {
            // vereinheitlichen + raw mitgeben
            $parsed[] = [
                'raw'  => $line,
                'qty'  => $res['qty']  ?? null,
                'unit' => $res['unit'] ?? null,
                'item' => $res['item'] ?? ($res['name'] ?? null),
                'note' => $res['note'] ?? null,
            ];
        } else {
            $parsed[] = ['raw'=>$line, 'qty'=>null, 'unit'=>null, 'item'=>$line, 'note'=>null];
        }
    }

    // 4) Backup + Schreiben
    $stamp = gmdate('Ymd_His');
    add_post_meta($post_id, $schema_key . '_bak_' . $stamp, $old_json); // Backup behalten
    $schema['recipeIngredient'] = $parsed;
    // Optional aktualisieren wir ebenfalls ein Spiegel-Feld (falls genutzt):
    $schema['ingredientsParsed'] = $parsed;

    $new_json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    update_post_meta($post_id, $schema_key, (string)$new_json);

    // 5) Kurzer Abschluss & sichtbare Quittung
    error_log('[SITC REPAIR] post '.$post_id.' repaired '.count($parsed).' lines.');
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre style='white-space:pre-wrap; font:12px/1.4 monospace; background:#111; color:#eee; padding:12px; border-radius:8px'>";
    echo "SITC REPAIR DONE (post {$post_id})\n";
    echo "Lines: ".count($parsed)."\n";
    echo "Backup key: {$schema_key}_bak_{$stamp}\n";
    echo "Hinweis: Gruppen/Meta werden hier NICHT gelöscht. Wenn dein Renderer Gruppen aus Meta bevorzugt,\n";
    echo "siehst du die Reparatur erst nach einem Gruppen-Refresh. Das machen wir im nächsten Schritt.\n";
    echo "</pre>";
    exit;
});

// --- TEMP (korrigiert): _sitc_ingredients_struct aus repariertem Schema neu aufbauen ---
// Aufruf: ?sitc_rebuild_groups=1  (nur auf Einzelbeitragsseite)
add_action('wp', function () {
    if (!isset($_GET['sitc_rebuild_groups']) || $_GET['sitc_rebuild_groups'] !== '1') return;
    if (!is_singular()) return;

    $post_id = get_queried_object_id();
    if (!$post_id) return;

    // 1) Repariertes Schema laden
    $schema_key = '_sitc_schema_recipe_json';
    $json = get_post_meta($post_id, $schema_key, true);
    if (!is_string($json) || $json === '') {
        wp_die('SITC: Kein Schema gefunden.');
    }
    $schema = json_decode($json, true);
    if (!is_array($schema)) {
        wp_die('SITC: Schema ist kein JSON-Objekt.');
    }

    // Quelle bevorzugen: ingredientsParsed (falls beim Repair gesetzt), sonst recipeIngredient
    $source = [];
    if (!empty($schema['ingredientsParsed']) && is_array($schema['ingredientsParsed'])) {
        $source = $schema['ingredientsParsed'];
    } elseif (!empty($schema['recipeIngredient'])) {
        $source = is_array($schema['recipeIngredient']) ? $schema['recipeIngredient'] : [$schema['recipeIngredient']];
    }
    if (empty($source)) {
        wp_die('SITC: Keine Zutaten im Schema gefunden.');
    }

    // kleine Helfer
    $flatten = function ($v): string {
        if ($v === null) return '';
        if (is_scalar($v)) return (string)$v;
        if (is_array($v)) {
            foreach (['name','item','label','text','value','raw'] as $k) {
                if (isset($v[$k]) && $v[$k] !== '') return $this($v[$k]);
            }
            foreach ($v as $leaf) {
                if (is_scalar($leaf)) return (string)$leaf;
            }
            return '';
        }
        if (is_object($v)) return $this((array)$v);
        return '';
    };
    $format_qty = function ($q): string {
        // Nur echte Mengen anzeigen; sonst leerer String
        if ($q === null || $q === '') return '';
        if (is_array($q)) {
            if (isset($q['raw']) && $q['raw'] !== '') {
                $q = $q['raw'];
            } else {
                $low  = $q['low']  ?? null;
                $high = $q['high'] ?? null;
                if ($low !== null && $high !== null && $low !== $high) {
                    $q = $low . '-' . $high;
                } elseif ($low !== null) {
                    $q = $low;
                } elseif ($high !== null) {
                    $q = $high;
                } else {
                    $q = $q['value'] ?? '';
                }
            }
        }
        // Nur numerisch wirkende Mengen übernehmen; alles andere (wie "Frischer Koriander") -> ''
        $qs = (string)$q;
        if (!preg_match('/^\s*(?:\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+)(?:\s*-\s*(?:\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+))?\s*$/u', $qs)) {
            return '';
        }
        if (function_exists('sitc_format_qty_display')) {
            return (string) sitc_format_qty_display($qs);
        }
        // einfache Formatierung: Komma -> Punkt, Nachkommata säubern
        $qs = str_replace(',', '.', $qs);
        if (is_numeric($qs)) {
            $n = (float)$qs;
            return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
        }
        return trim($qs);
    };
    $format_unit = function ($u) use ($flatten): string {
        if ($u === null || $u === '') return '';
        if (is_array($u)) {
            foreach (['name','label','item','text','value','raw'] as $k) {
                if (!empty($u[$k])) { $u = $u[$k]; break; }
            }
        }
        $u = $flatten($u);
        // führende Mengenfragmente entfernen (z.B. "30 ml" -> "ml")
        $u = preg_replace('/^\s*(?:\d+(?:[.,]\d+)?(?:\s*\/\s*\d+)?)\s*/u', '', $u) ?? '';
        $u = trim($u);
        if ($u === '') return '';
        if (function_exists('sitc_unit_to_de')) $u = (string) sitc_unit_to_de($u);
        return $u;
    };
    $decode_unicode = function (string $s): string {
        // "\u00f6" -> "ö"
        if (strpos($s, '\u00') !== false) {
            $json = '"' . str_replace(['\\','"'], ['\\\\','\"'], $s) . '"';
            $dec  = json_decode($json, true);
            if (is_string($dec)) $s = $dec;
        }
        // "u00f6" -> "ö"
        $s = preg_replace_callback('/u00([0-9a-fA-F]{2})/u', function($m){
            return iconv('ISO-8859-1', 'UTF-8//IGNORE', chr(hexdec($m[1])));
        }, $s) ?? $s;
        return $s;
    };

    // 2) Zielstruktur exakt für deinen Renderer: ['qty'=>string,'unit'=>string,'name'=>string]
    $struct = [];
    foreach ($source as $it) {
        if (is_string($it)) {
            $struct[] = ['qty'=>'', 'unit'=>'', 'name'=>$decode_unicode(trim($it))];
            continue;
        }
        if (!is_array($it)) continue;

        // Wichtig: ITEM aus dem v2-Parser-Ergebnis (erhält „Salz & Pfeffer“ vollständig)
        $raw_item = $it['item'] ?? ($it['name'] ?? ($it['raw'] ?? ''));
        $qty      = $format_qty($it['qty'] ?? '');
        $unit     = $format_unit($it['unit'] ?? '');
        $name     = $decode_unicode($flatten($raw_item));

        $struct[] = [
            'qty'  => $qty,         // '' falls keine echte Menge → KEIN "0"
            'unit' => $unit,        // sauber, ohne führende Zahlen
            'name' => $name,        // inkl. „Salz & Pfeffer“, Umlaute ok
        ];
    }

    // 3) Backup + schreiben
    $meta_key = '_sitc_ingredients_struct';
    $old = get_post_meta($post_id, $meta_key, true);
    $stamp = gmdate('Ymd_His');
    if ($old !== '') {
        add_post_meta($post_id, $meta_key . '_bak_' . $stamp, $old);
    }
    update_post_meta($post_id, $meta_key, $struct);

    // 4) Quittung
    error_log('[SITC GROUP REBUILD FIX] post '.$post_id.' items='.count($struct));
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre style='white-space:pre-wrap; font:12px/1.4 monospace; background:#111; color:#eee; padding:12px; border-radius:8px'>";
    echo "SITC GROUP REBUILD FIX DONE (post {$post_id})\n";
    echo "Items: ".count($struct)."\n";
    echo "Backup key (falls alt vorhanden): {$meta_key}_bak_{$stamp}\n";
    echo "</pre>";
    exit;
});
