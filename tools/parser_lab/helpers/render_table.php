<?php

function pl_htmlesc(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function pl_render_env(array $env): string {
    $rows = [];
    foreach ($env as $k => $v) {
        if (is_array($v) || is_object($v)) {
            $v = json_encode($v, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        $rows[] = '<tr><th>'.pl_htmlesc((string)$k).'</th><td>'.pl_htmlesc((string)$v).'</td></tr>';
    }
    return '<table class="grid env"><thead><tr><th>Key</th><th>Value</th></tr></thead><tbody>'.implode('', $rows).'</tbody></table>';
}

function status_badge(string $state): string {
    $cls = ($state === 'FOUND') ? 'ok' : 'err';
    return ' <span class="badge '.$cls.'">'.pl_htmlesc($state).'</span>';
}

function pl_format_qty($q): string {
    if (is_array($q) && isset($q['low'],$q['high'])) {
        // Format ranges as "low–high" using an en dash
        $low  = rtrim(rtrim(sprintf('%.3f', (float)$q['low']), '0'), '.');
        $high = rtrim(rtrim(sprintf('%.3f', (float)$q['high']), '0'), '.');
        return $low . '–' . $high;
    }
    if ($q === null || $q === '') return '—';
    if (is_numeric($q)) {
        return rtrim(rtrim(sprintf('%.3f', (float)$q), '0'), '.');
    }
    return (string)$q;
}

function pl_render_items_table(array $items): string {
    $out = '<table class="grid items"><thead><tr>'
        .'<th>#</th><th>raw</th><th>qty</th><th>unit</th><th>item</th><th>note</th>'
        .'</tr></thead><tbody>';
    $i = 0;
    foreach ($items as $row) {
        $i++;
        $raw  = isset($row['raw'])  ? (string)$row['raw']  : '';
        $qty  = $row['qty'] ?? null;
        $unit = isset($row['unit']) ? (string)$row['unit'] : '';
        $item = isset($row['item']) ? (string)$row['item'] : '';
        $note = isset($row['note']) ? (string)$row['note'] : '';
        $out .= '<tr>'
             . '<td>'.pl_htmlesc((string)$i).'</td>'
             . '<td><small>'.pl_htmlesc($raw).'</small></td>'
             . '<td>'.pl_htmlesc(pl_format_qty($qty)).'</td>'
             . '<td>'.pl_htmlesc($unit).'</td>'
             . '<td>'.pl_htmlesc($item).'</td>'
             . '<td>'.pl_htmlesc($note).'</td>'
             . '</tr>';
    }
    if ($i === 0) { $out .= '<tr><td colspan="6"><em>Keine Einträge</em></td></tr>'; }
    $out .= '</tbody></table>';
    return $out;
}

function pl_render_errors(array $errs): string {
    if (!$errs) return '<p><em>Keine Diagnosen</em></p>';
    $lis = '';
    foreach ($errs as $e) { $lis .= '<li>'.pl_htmlesc((string)$e).'</li>'; }
    return '<ul class="diag">'.$lis.'</ul>';
}
