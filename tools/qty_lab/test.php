<?php
declare(strict_types=1);

require __DIR__ . '/lib_qty.php';

$tests = [
    '½ TK Ente, vorgegart und aufgetaut',
    '½ Paprika, rot',
    '1½ EL Joghurt',
    '1 ½ EL Joghurt',
    'ca. 60 ml Öl zum Anbraten',
    'ca. 15 g Ingwer-Stück',
];

echo "<meta charset='utf-8'><style>table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px 8px}</style>";
echo "<h2>qty_lab – Probe</h2>";
echo "<table>";
echo "<tr><th>raw</th><th>prenorm</th><th>qty</th><th>unit</th><th>item</th><th>display (DE)</th></tr>";

foreach ($tests as $raw) {
    $pn   = sitc_qty_prenorm($raw);
    $res  = sitc_qty_parse($pn);
    $disp = '';
    if ($res['qty'] !== null) {
        $disp_qty = de_format($res['qty']);
        $disp_unit = $res['unit'] ?? '';
        $disp = trim($disp_qty . ' ' . $disp_unit . ' ' . $res['item']);
    }
    echo "<tr>";
    echo "<td>" . htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($pn,  ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($res['qty'] === null ? '—' : (string)$res['qty'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($res['unit'] ?? '—', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($res['item'], ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($disp, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "</tr>";
}

echo "</table>";
