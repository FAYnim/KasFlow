<?php
function run(array $get): array {
    $_GET = $get;
    ob_start();
    include __DIR__ . '/../api_public.php';
    return json_decode(ob_get_clean(), true);
}
$j = run(['action' => 'get_jurnal']);
if (!isset($j['transaksi'], $j['line_chart'], $j['donut'])) { fwrite(STDERR, "FAIL: jurnal shape\n"); exit(1); }
$p = run(['action' => 'get_piutang']);
if (!is_array($p)) { fwrite(STDERR, "FAIL: piutang not array\n"); exit(1); }
$b = run(['action' => 'get_bank']);
if (!is_array($b)) { fwrite(STDERR, "FAIL: bank not array\n"); exit(1); }
echo "PASS: jurnal+piutang+bank shape OK\n";
exit(0);
