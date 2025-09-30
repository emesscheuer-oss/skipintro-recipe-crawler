<?php
// includes/refresh_v2_helpers.php
// UTF-8, no BOM. No closing PHP tag.

if (!defined('ABSPATH')) exit;

/**
 * Skipintro Recipe Crawler — Refresh v2 Helpers (ohne UI / Metabox)
 * - Re-parst gespeicherte RAW-Zeilen mit Parser v2 + Normalizer
 * - Schreibt _sitc_schema_recipe_json (recipeIngredient + ingredientsParsed) mit Backup
 * - Materialisiert _sitc_ingredients_struct mit Backup
 * - Bietet eine einzige API-Funktion: sitc_refresh_current_post_v2($post_id, $dry_run=false)
 */

if (!function_exists('sitc__decode_unicode_like')) {
    function sitc__decode_unicode_like(string $s): string {
        if ($s === '') return $s;
        if (strpos($s, '\u00') !== false) {
            $json = '"' . str_replace(['\\','"'], ['\\\\','\"'], $s) . '"';
            $dec  = json_decode($json, true);
            if (is_string($dec)) $s = $dec;
        }
        $s = preg_replace_callback('/u00([0-9a-fA-F]{2})/u', static function ($m) {
            return iconv('ISO-8859-1', 'UTF-8//IGNORE', chr(hexdec($m[1])));
        }, $s) ?? $s;
        return $s;
    }
}

if (!function_exists('sitc__parse_line_v2')) {
    function sitc__parse_line_v2(string $raw, string $locale = 'de'): array {
        $line = sitc__decode_unicode_like($raw);
        if (class_exists('\\Skipintro\\RecipeCrawler\\Parser\\Filters\\Ingredient_Text_Normalizer')) {
            $line = \Skipintro\RecipeCrawler\Parser\Filters\Ingredient_Text_Normalizer::normalize($line);
        }
        $res = null;
        if (function_exists('sitc_ing_v2_parse_line')) {
            $res = @sitc_ing_v2_parse_line($line, $locale);
        } elseif (function_exists('sitc_ing_parse_line_mod')) {
            $res = @sitc_ing_parse_line_mod($line, $locale);
        }
        if (is_array($res)) {
            return [
                'raw'  => $raw,
                'qty'  => $res['qty']  ?? null,
                'unit' => $res['unit'] ?? null,
                'item' => $res['item'] ?? ($res['name'] ?? $line),
                'note' => $res['note'] ?? null,
            ];
        }
        return ['raw'=>$raw, 'qty'=>null, 'unit'=>null, 'item'=>$line, 'note'=>null];
    }
}

if (!function_exists('sitc__format_qty_safe')) {
    function sitc__format_qty_safe($q): string {
        if ($q === null || $q === '') return '';
        if (is_array($q)) {
            if (isset($q['raw']) && $q['raw'] !== '') { $q = $q['raw']; }
            else {
                $low  = $q['low']  ?? null;
                $high = $q['high'] ?? null;
                if ($low !== null && $high !== null && $low !== $high) $q = $low . '-' . $high;
                elseif ($low !== null) $q = $low;
                elseif ($high !== null) $q = $high;
                else $q = $q['value'] ?? '';
            }
        }
        $s = (string)$q;
        if (!preg_match('/^\s*(?:\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+)(?:\s*-\s*(?:\d+(?:[.,]\d+)?|\d+\s*\/\s*\d+))?\s*$/u', $s)) return '';
        if (function_exists('sitc_format_qty_display')) return (string) sitc_format_qty_display($s);
        $s = str_replace(',', '.', $s);
        if (is_numeric($s)) { $n=(float)$s; return rtrim(rtrim(number_format($n,3,'.',''),'0'),'.'); }
        return trim($s);
    }
}

if (!function_exists('sitc__format_unit_safe')) {
    function sitc__format_unit_safe($u): string {
        if ($u === null || $u === '') return '';
        if (is_array($u)) {
            foreach (['name','label','item','text','value','raw'] as $k) {
                if (!empty($u[$k])) { $u = $u[$k]; break; }
            }
        }
        $u = (string)$u;
        $u = preg_replace('/^\s*(?:\d+(?:[.,]\d+)?(?:\s*\/\s*\d+)?)\s*/u', '', $u) ?? '';
        $u = trim($u);
        if ($u === '') return '';
        if (function_exists('sitc_unit_to_de')) $u = (string) sitc_unit_to_de($u);
        return $u;
    }
}

if (!function_exists('sitc_refresh_reparse_schema_v2')) {
    function sitc_refresh_reparse_schema_v2(int $post_id): void {
        $schema_key = '_sitc_schema_recipe_json';
        $old_json   = get_post_meta($post_id, $schema_key, true);
        if (!is_string($old_json) || $old_json === '') return;

        $schema = json_decode($old_json, true);
        if (!is_array($schema)) return;

        $raw_map = [];
        foreach (['recipeIngredient','ingredientsParsed'] as $k) {
            if (empty($schema[$k])) continue;
            $arr = is_array($schema[$k]) ? $schema[$k] : [$schema[$k]];
            foreach ($arr as $e) {
                if (is_array($e) && isset($e['raw']) && is_string($e['raw'])) $t = trim($e['raw']);
                else $t = trim((string)$e);
                if ($t !== '') $raw_map[$t] = true;
            }
        }
        $rawLines = array_keys($raw_map);
        if (empty($rawLines)) return;

        $parsed = [];
        foreach ($rawLines as $line) {
            $parsed[] = sitc__parse_line_v2($line, 'de');
        }

        $stamp = gmdate('Ymd_His');
        add_post_meta($post_id, $schema_key . '_bak_' . $stamp, $old_json);

        $schema['recipeIngredient']  = $parsed;
        $schema['ingredientsParsed'] = $parsed;

        $new_json = wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($new_json) && $new_json !== '') update_post_meta($post_id, $schema_key, $new_json);

        update_post_meta($post_id, '_sitc_parser_version', '0.6.02');
    }
}

if (!function_exists('sitc_refresh_materialize_struct_from_schema')) {
    function sitc_refresh_materialize_struct_from_schema(int $post_id): void {
        $schema_key = '_sitc_schema_recipe_json';
        $json = get_post_meta($post_id, $schema_key, true);
        if (!is_string($json) || $json === '') return;
        $schema = json_decode($json, true);
        if (!is_array($schema)) return;

        $src = [];
        if (!empty($schema['ingredientsParsed']) && is_array($schema['ingredientsParsed'])) {
            $src = $schema['ingredientsParsed'];
        } elseif (!empty($schema['recipeIngredient'])) {
            $src = is_array($schema['recipeIngredient']) ? $schema['recipeIngredient'] : [$schema['recipeIngredient']];
        }
        if (empty($src)) return;

        $struct = [];
        foreach ($src as $it) {
            if (is_string($it)) { $struct[] = ['qty'=>'','unit'=>'','name'=>sitc__decode_unicode_like(trim($it))]; continue; }
            if (!is_array($it)) continue;
            $qty  = sitc__format_qty_safe($it['qty'] ?? '');
            $unit = sitc__format_unit_safe($it['unit'] ?? '');
            $name = sitc__decode_unicode_like((string)($it['item'] ?? $it['name'] ?? $it['raw'] ?? ''));
            $struct[] = ['qty'=>$qty,'unit'=>$unit,'name'=>$name];
        }

        $meta_key = '_sitc_ingredients_struct';
        $old = get_post_meta($post_id, $meta_key, true);
        $stamp = gmdate('Ymd_His');
        if ($old !== '') add_post_meta($post_id, $meta_key . '_bak_' . $stamp, $old);
        update_post_meta($post_id, $meta_key, $struct);
    }
}

if (!function_exists('sitc_refresh_current_post_v2')) {
    /**
     * Public helper: rewrite schema with v2 + materialize struct.
     */
    function sitc_refresh_current_post_v2(int $post_id, bool $dry_run = false): bool {
        if ($post_id <= 0) return false;
        if (!$dry_run) {
            sitc_refresh_reparse_schema_v2($post_id);
            sitc_refresh_materialize_struct_from_schema($post_id);
        }
        return true;
    }
}
