<?php
$_GET = ['action' => 'get_kas', 'bulan' => 'Januari', 'tahun' => 2026];
ob_start();
include __DIR__ . '/../api_public.php';
$out = ob_get_clean();
$data = json_decode($out, true);
if (!is_array($data)) { fwrite(STDERR, "FAIL: not array: $out\n"); exit(1); }
echo "PASS: get_kas returned " . count($data) . " rows\n";
exit(0);
