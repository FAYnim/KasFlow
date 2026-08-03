<?php
// Inserts a few rows, calls API via CLI-PHP built-in server is heavy;
// instead, require the API file with $_GET stubbed.
$_GET = ['action' => 'get_summary'];
ob_start();
include __DIR__ . '/../../src/api/public.php';
$out = ob_get_clean();
$data = json_decode($out, true);
foreach (['total_kas_terkumpul','saldo_bms','total_kasbon'] as $k) {
    if (!array_key_exists($k, $data)) { fwrite(STDERR, "FAIL: missing $k\n"); exit(1); }
}
echo "PASS: summary shape OK\n";
exit(0);
