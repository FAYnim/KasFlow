<?php
function run(array $get): array {
    $_GET = $get;
    ob_start();
    include __DIR__ . '/../../src/api/public.php';
    return json_decode(ob_get_clean(), true);
}
$j = run(['action' => 'get_jurnal']);
if (!isset($j['transaksi'], $j['line_chart'], $j['donut'])) { fwrite(STDERR, "FAIL: jurnal shape\n"); exit(1); }
echo "PASS: jurnal shape OK\n";
exit(0);
