<?php
// tools/parser_lab/run.php (manual-friendly, UTF-8, no close tag)
header('Content-Type: text/html; charset=utf-8');

$root = realpath(__DIR__ . '/../../');
if ($root === false) { $root = getcwd(); }

// --- helpers ---
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function diag_box(array $d) {
    $fixture = $d['fixture'] ?? '(unbekannt)';
    $ext     = $d['ext'] ?? '';
    $msg     = $d['message'] ?? '';
    $html  = '<div class="box"><strong>Diagnose</strong><ul style="margin:6px 0 0 18px">';
    $html .= '<li><code>message</code>: ' . h($msg) . '</li>';
    $html .= '<li><code>fixture</code>: ' . h($fixture) . '</li>';
    $html .= '<li><code>ext</code>: ' . h($ext) . '</li>';
    $html .= '</ul></div>';
    return $html;
}

// Try to load ingredient line parser & qty normalizer (best-effort)
$ing_parse_fn = null;
if (is_file($root . '/includes/ingredients/parse_line.php')) {
    require_once $root . '/includes/ingredients/parse_line.php';
}
if (is_file($root . '/includes/ingredients/qty.php')) {
    require_once $root . '/includes/ingredients/qty.php';
}
if (function_exists('sitc_ing_v2_parse_line')) { $ing_parse_fn = 'sitc_ing_v2_parse_line'; }
elseif (function_exists('sitc_ing_parse_line')) { $ing_parse_fn = 'sitc_ing_parse_line'; }
elseif (function_exists('sitc_parse_ingredient_line')) { $ing_parse_fn = 'sitc_parse_ingredient_line'; }

$engine = isset($_GET['engine']) ? (string)$_GET['engine'] : 'mod';
$f_rel  = isset($_GET['f']) ? (string)$_GET['f'] : '';
$abs   = $f_rel ? realpath($root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $f_rel)) : '';
$ext   = $abs ? strtolower(pathinfo($abs, PATHINFO_EXTENSION)) : '';

$diag  = ['fixture' => $f_rel ?: '(none)', 'ext' => $ext, 'message' => ''];

$manual_lines = [];
if ($abs && is_file($abs) && $ext === 'txt') {
    // Manual mode: read UTF-8 text file and split by lines
    $raw = file_get_contents($abs);
    if (strncmp($raw, "\xEF\xBB\xBF", 3) === 0) { $raw = substr($raw, 3); } // strip BOM
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    foreach (explode("\n", $raw) as $ln) {
        $ln = trim($ln);
        if ($ln !== '') { $manual_lines[] = $ln; }
    }
    if (empty($manual_lines)) {
        $diag['message'] = 'TXT-Quelle enthält keine nicht-leeren Zeilen.';
    }
}

// --- render ---
<!doctype html>
<html><head>
<meta charset="utf-8">
<title>Parser-Lab</title>
<style>
body{font:14px/1.45 system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:20px}
.grid{border-collapse:collapse;width:100%;max-width:1100px}
.grid th,.grid td{border:1px solid #ccc;padding:6px 8px;vertical-align:top}
.grid thead th{background:#f6f6f6}
.box{border:1px solid #ddd;background:#fafafa;padding:8px 10px;border-radius:6px;margin:10px 0}
.box.err{border-color:#d33;background:#fff0f0}
.box.warn{border-color:#e6a700;background:#fff8e1}
pre{background:#f9f9f9;border:1px solid #eee;padding:8px;overflow:auto}
a{color:#06c;text-decoration:none} a:hover{text-decoration:underline}
.dev-dump{font:12px/1.3 ui-monospace,Consolas,monospace;color:#555;background:#f7f7f7;border-left:3px solid #e0e0e0;padding:3px 6px;margin:0}
.badge{display:inline-block;padding:2px 6px;border-radius:6px;background:#eef;border:1px solid #ccd}
ul {padding-left: 18px; margin: 6px 0 0 0;}
code{background:#f0f0f0;padding:1px 4px;border-radius:4px}
</style>
</head><body>
<div class="box">
<strong>Engine:</strong>
<span class="badge"><?php echo h($engine); </span>
&nbsp;
<a href="?f=<?php echo urlencode($f_rel); &amp;engine=legacy">Legacy</a> |
<a href="?f=<?php echo urlencode($f_rel); &amp;engine=mod">Modular</a> |
<a href="?f=<?php echo urlencode($f_rel); &amp;engine=auto">Auto</a>
</div>

<?php
// If manual TXT provided, show manual section instead of static smoke test.
if (!empty($manual_lines)) {
    echo '<div class="box"><strong>Manual QTY Smoke-Test:</strong><ul>';
    foreach ($manual_lines as $ln) {
        $disp = $ln;
        $parsed_str = null;
        $qty = $unit = $item = $note = null;

        // Normalize preview (if available)
        if (function_exists('sitc_qty_normalize')) {
            $norm = sitc_qty_normalize($ln);
        } else {
            $norm = $ln;
        }

        if ($ing_parse_fn) {
            try {
                $fn = $ing_parse_fn;
                $res = call_user_func($fn, $norm);
                // Try common shapes: array with keys or object
                if (is_array($res)) {
                    $qty  = $res['qty']  ?? ($res['q'] ?? null);
                    $unit = $res['unit'] ?? ($res['u'] ?? null);
                    $item = $res['item'] ?? ($res['name'] ?? null);
                    $note = $res['note'] ?? null;
                } elseif (is_object($res)) {
                    $qty  = $res->qty  ?? null;
                    $unit = $res->unit ?? null;
                    $item = $res->item ?? null;
                    $note = $res->note ?? null;
                }
            } catch (\Throwable $e) {
                $parsed_str = 'ERROR: ' . $e->getMessage();
            }
        }

        if ($parsed_str === null) {
            // Build a conservative display string
            $parts = [];
            if ($qty !== null && $qty !== '')     $parts[] = (string)$qty;
            if ($unit !== null && $unit !== '')   $parts[] = (string)$unit;
            if ($item !== null && $item !== '')   $parts[] = (string)$item;
            if ($note !== null && $note !== '')   $parts[] = '(' . (string)$note . ')';
            $parsed_str = implode(' ', $parts);
            if ($parsed_str === '') $parsed_str = $norm;
        }

        echo '<li><code>' . h($disp) . '</code> &nbsp;→ <strong>' . h($parsed_str) . '</strong></li>';
    }
    echo '</ul></div>';
} else {
    // No manual lines → keep the page minimal without static, potentially confusing smoke data.
    echo '<div class="box warn"><strong>Hinweis:</strong> Keine manuellen Zeilen. Wähle eine TXT-Fixture mit ?f=Pfad/zur/datei.txt</div>';
}

echo '<h1>Parser-Lab — ' . h($f_rel ?: '(keine Datei)') . '</h1>';
echo '<p><small>Aktuelle Uhrzeit (Europe/Berlin): ' . h(gmdate('Y-m-d H:i:s', time()+ 2*3600)) . '</small></p>';
echo '<p><a href="?engine=' . urlencode($engine) . '">← Zur Übersicht</a></p>';

// render diag
echo diag_box($diag);


</body></html>
