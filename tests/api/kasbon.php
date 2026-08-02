<?php
ob_start();
$fail = 0;

function fail(string $msg): void {
    global $fail;
    fwrite(STDERR, "FAIL: $msg\n");
    $fail++;
}

// --- Schema test ---
require_once __DIR__ . '/../bootstrap.php';
$pdo = db();

$pdo->exec("DELETE FROM kasbon WHERE nama LIKE 'TEST_%'");

$pdo->prepare("INSERT INTO kasbon (nama, tanggal, keterangan, jumlah, status, tanggal_lunas) VALUES (?,?,?,?,?,?)")
    ->execute(['TEST_SCHEMA', '2026-08-01', 'Test entry', 50000.00, 'belum_lunas', null]);
$id = $pdo->lastInsertId();

$row = $pdo->prepare("SELECT * FROM kasbon WHERE id=?");
$row->execute([$id]);
$r = $row->fetch();

if (!$r) fail("insert did not create row");
if ($r['status'] !== 'belum_lunas') fail("default status expected belum_lunas, got {$r['status']}");
if ($r['tanggal_lunas'] !== null) fail("tanggal_lunas should be null for belum_lunas");
if ((float)$r['jumlah'] !== 50000.00) fail("jumlah mismatch");

try {
    $pdo->prepare("INSERT INTO kasbon (nama, tanggal, keterangan, jumlah, status) VALUES (?,?,?,?,?)")
        ->execute(['TEST_BAD', '2026-08-01', 'Bad', 1000, 'invalid_status']);
    fail("invalid enum should throw");
} catch (Throwable $e) { }

echo "PASS: schema test\n";

// --- Public API test ---
$_GET = ['action' => 'get_kasbon'];
ob_start();
@include __DIR__ . '/../../src/api/public.php';
$out = ob_get_clean();
$data = json_decode($out, true);
if (!is_array($data)) fail("get_kasbon default filter not array: $out");
echo "PASS: public get_kasbon default filter\n";

$_GET = ['action' => 'get_kasbon', 'bulan' => 'Agustus', 'tahun' => 2026];
ob_start();
@include __DIR__ . '/../../src/api/public.php';
$out = ob_get_clean();
$data = json_decode($out, true);
if (!is_array($data)) fail("get_kasbon not array: $out");
if (count($data) > 0 && !isset($data[0]['nama'])) fail("rows missing 'nama' field");
echo "PASS: public get_kasbon\n";

$_GET = ['action' => 'get_kasbon', 'bulan' => 'INVALIDBULAN'];
http_response_code(200);
ob_start();
@include __DIR__ . '/../../src/api/public.php';
$out = ob_get_clean();
$decoded = json_decode($out, true);
if (!isset($decoded['error'])) fail("invalid bulan should return error: $out");
echo "PASS: public get_kasbon invalid bulan\n";

// --- Admin API tests ---
session_start();
$_SESSION['admin_logged'] = true;

function call(array $req, string $action): array {
    $_POST = $req;
    $_REQUEST = array_merge($req, ['action' => $action]);
    http_response_code(200);
    ob_start();
    @include __DIR__ . '/../../src/api/admin.php';
    $raw = ob_get_clean();
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['_raw' => $raw];
}

// 403 test: admin.php calls exit on unauthorized, run in subprocess
unset($_SESSION['admin_logged']);
$_GET = []; $_POST = []; $_REQUEST = [];
$adminApiPath = realpath(__DIR__ . '/../../src/api/admin.php');
$testScript = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'kasbon_403_test.php';
file_put_contents($testScript, '<?php ob_start(); @include ' . var_export($adminApiPath, true) . '; ob_end_flush();');
$output = [];
$retval = 0;
$phpBin = PHP_BINARY;
exec('"' . $phpBin . '" ' . escapeshellarg($testScript), $output, $retval);
@unlink($testScript);
$decoded403 = json_decode(implode('', $output), true);
if (!isset($decoded403['error']) || $decoded403['error'] !== 'unauthorized') {
    fail("403 expected without session, got: " . json_encode($decoded403));
}
$_SESSION['admin_logged'] = true;
echo "PASS: admin 403 without session\n";

$res = call(['nama'=>'TEST_KASBON1','tanggal'=>'2026-08-01','keterangan'=>'Pinjam modal','jumlah'=>100000,'status'=>'belum_lunas'], 'add_kasbon');
if (empty($res['ok']) || empty($res['id'])) fail("add_kasbon failed: " . json_encode($res));
$kid = $res['id'];
echo "PASS: admin add_kasbon\n";

$res = call(['nama'=>'','tanggal'=>'2026-08-01','keterangan'=>'X','jumlah'=>100,'status'=>'belum_lunas'], 'add_kasbon');
if (http_response_code() !== 400) fail("add_kasbon empty nama should 400");
echo "PASS: admin add_kasbon empty nama\n";

$res = call(['nama'=>'TEST','tanggal'=>'2026-08-01','keterangan'=>'X','jumlah'=>0,'status'=>'belum_lunas'], 'add_kasbon');
if (http_response_code() !== 400) fail("add_kasbon jumlah=0 should 400");
echo "PASS: admin add_kasbon jumlah=0\n";

$res = call(['nama'=>'TEST','tanggal'=>'2026-08-01','keterangan'=>'X','jumlah'=>-100,'status'=>'belum_lunas'], 'add_kasbon');
if (http_response_code() !== 400) fail("add_kasbon jumlah=-100 should 400");
echo "PASS: admin add_kasbon jumlah negative\n";

$res = call(['nama'=>'TEST','tanggal'=>'2026-08-01','keterangan'=>'X','jumlah'=>100,'status'=>'invalid'], 'add_kasbon');
if (http_response_code() !== 400) fail("add_kasbon invalid status should 400");
echo "PASS: admin add_kasbon invalid status\n";

$res = call(['id'=>$kid,'nama'=>'TEST_KASBON1_UPDATED','tanggal'=>'2026-08-02','keterangan'=>'Updated','jumlah'=>200000,'status'=>'belum_lunas'], 'update_kasbon');
if (empty($res['ok'])) fail("update_kasbon failed: " . json_encode($res));
$row2 = $pdo->prepare("SELECT * FROM kasbon WHERE id=?");
$row2->execute([$kid]);
$r2 = $row2->fetch();
if ($r2['nama'] !== 'TEST_KASBON1_UPDATED') fail("update_kasbon nama not updated");
echo "PASS: admin update_kasbon\n";

$res = call(['id'=>$kid], 'mark_lunas_kasbon');
if (empty($res['ok'])) fail("mark_lunas failed: " . json_encode($res));
$row3 = $pdo->prepare("SELECT status, tanggal_lunas FROM kasbon WHERE id=?");
$row3->execute([$kid]);
$r3 = $row3->fetch();
if ($r3['status'] !== 'lunas') fail("mark_lunas status not set");
if ($r3['tanggal_lunas'] !== date('Y-m-d')) fail("mark_lunas tanggal_lunas not set to today: {$r3['tanggal_lunas']}");
echo "PASS: admin mark_lunas_kasbon\n";

$res = call(['id'=>$kid], 'mark_lunas_kasbon');
if (empty($res['ok'])) fail("mark_lunas idempotent should not fail");
echo "PASS: admin mark_lunas_kasbon idempotent\n";

$res = call(['id'=>$kid], 'mark_belum_lunas_kasbon');
if (empty($res['ok'])) fail("mark_belum_lunas failed: " . json_encode($res));
$row4 = $pdo->prepare("SELECT status, tanggal_lunas FROM kasbon WHERE id=?");
$row4->execute([$kid]);
$r4 = $row4->fetch();
if ($r4['status'] !== 'belum_lunas') fail("mark_belum_lunas status not set");
if ($r4['tanggal_lunas'] !== null) fail("mark_belum_lunas tanggal_lunas should be null");
echo "PASS: admin mark_belum_lunas_kasbon\n";

$res = call(['id'=>$kid], 'delete_kasbon');
if (empty($res['ok'])) fail("delete_kasbon failed: " . json_encode($res));
$row5 = $pdo->prepare("SELECT id FROM kasbon WHERE id=?");
$row5->execute([$kid]);
if ($row5->fetch()) fail("delete_kasbon row still exists");
echo "PASS: admin delete_kasbon\n";

$pdo->exec("DELETE FROM kasbon WHERE nama LIKE 'TEST_%'");

ob_end_flush();

if ($fail > 0) {
    echo "SUMMARY: $fail FAILURES\n";
    exit(1);
}
echo "\n=== ALL KASBON TESTS PASSED ===\n";
exit(0);
