<?php
ob_start();
$fail = 0;

function fail(string $msg): void {
    global $fail;
    fwrite(STDERR, "FAIL: $msg\n");
    $fail++;
}

require_once __DIR__ . '/../bootstrap.php';
$pdo = db();

$pdo->exec("DELETE FROM kas_bms WHERE keterangan LIKE 'TEST_%'");

$pdo->prepare("INSERT INTO kas_bms (tanggal, keterangan, jenis, jumlah) VALUES (?,?,?,?)")
    ->execute(['2026-08-01', 'TEST_seed_setor', 'setor', 100000.00]);
$idSetor = $pdo->lastInsertId();
$pdo->prepare("INSERT INTO kas_bms (tanggal, keterangan, jenis, jumlah) VALUES (?,?,?,?)")
    ->execute(['2026-08-02', 'TEST_seed_tarik', 'tarik', 30000.00]);
$idTarik = $pdo->lastInsertId();

// Empty filter
$_GET = ['action' => 'get_bms'];
http_response_code(200);
ob_start();
@include __DIR__ . '/../../src/api/public.php';
$out = ob_get_clean();
$data = json_decode($out, true);
if (!is_array($data) || !isset($data['rows']) || !isset($data['totals'])) {
    fail("get_bms default not expected shape: $out");
}
if (count($data['rows']) < 2) fail("get_bms default should return at least 2 seeded rows");
if (abs((float)$data['totals']['setor'] - 100000.00) > 0.001) fail("totals.setor mismatch: " . json_encode($data['totals']));
if (abs((float)$data['totals']['tarik'] - 30000.00) > 0.001) fail("totals.tarik mismatch");
if (abs((float)$data['totals']['saldo'] - 70000.00) > 0.001) fail("totals.saldo mismatch");
echo "PASS: get_bms default + totals\n";

// Date filter
$_GET = ['action' => 'get_bms', 'dari' => '2026-08-02', 'sampai' => '2026-08-02'];
http_response_code(200);
ob_start();
@include __DIR__ . '/../../src/api/public.php';
$out = ob_get_clean();
$data = json_decode($out, true);
if (count($data['rows']) !== 1) fail("date filter should return 1 row, got " . count($data['rows']));
if ($data['rows'][0]['jenis'] !== 'tarik') fail("date filter returned wrong row");
if (abs((float)$data['totals']['tarik'] - 30000.00) > 0.001) fail("date filter totals.tarik mismatch");
echo "PASS: get_bms date filter\n";

// Inverted range (dari > sampai) — empty result, no error
$_GET = ['action' => 'get_bms', 'dari' => '2099-01-01', 'sampai' => '2099-12-31'];
http_response_code(200);
ob_start();
@include __DIR__ . '/../../src/api/public.php';
$out = ob_get_clean();
$data = json_decode($out, true);
if (count($data['rows']) !== 0) fail("inverted range should return 0 rows");
if ((float)$data['totals']['saldo'] !== 0.0) fail("inverted range saldo should be 0");
echo "PASS: get_bms inverted range\n";

// Verify row shape
$_GET = ['action' => 'get_bms'];
http_response_code(200);
ob_start();
@include __DIR__ . '/../../src/api/public.php';
$out = ob_get_clean();
$data = json_decode($out, true);
$row = $data['rows'][0];
foreach (['id','tanggal','keterangan','jenis','jumlah'] as $f) {
    if (!array_key_exists($f, $row)) fail("row missing field: $f");
}
echo "PASS: get_bms row shape\n";

$pdo->exec("DELETE FROM kas_bms WHERE keterangan LIKE 'TEST_%'");
ob_end_flush();

if ($fail > 0) {
    echo "SUMMARY: $fail FAILURES\n";
    exit(1);
}
echo "\n=== ALL BMS PUBLIC TESTS PASSED ===\n";
exit(0);
