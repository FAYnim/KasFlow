<?php
ob_start();
$fail = 0;

function fail(string $msg): void {
    global $fail;
    fwrite(STDERR, "FAIL: $msg\n");
    $fail++;
}

require_once __DIR__ . '/../../config/database.php';
$pdo = db();

$pdo->exec("DELETE FROM kas_bms WHERE keterangan LIKE 'TEST_%'");

// --- 403 without session ---
$adminApiPath = realpath(__DIR__ . '/../../src/api/admin.php');
$testScript = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'bms_403_test.php';
file_put_contents($testScript, '<?php ob_start(); @include ' . var_export($adminApiPath, true) . '; ob_end_flush();');
$output = []; $retval = 0;
$phpBin = PHP_BINARY;
exec('"' . $phpBin . '" ' . escapeshellarg($testScript), $output, $retval);
@unlink($testScript);
$decoded403 = json_decode(implode('', $output), true);
if (!isset($decoded403['error']) || $decoded403['error'] !== 'unauthorized') {
    fail("403 expected without session, got: " . json_encode($decoded403));
}
echo "PASS: admin 403 without session\n";

// --- With session ---
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

$res = call(['tanggal'=>'2026-08-01','keterangan'=>'TEST_add','jenis'=>'setor','jumlah'=>50000], 'add_bms');
if (empty($res['ok']) || empty($res['id'])) fail("add_bms failed: " . json_encode($res));
$bid = $res['id'];
echo "PASS: add_bms valid\n";

$res = call(['tanggal'=>'2026-08-01','keterangan'=>'','jenis'=>'setor','jumlah'=>50000], 'add_bms');
if (http_response_code() !== 400) fail("add_bms empty ket should 400");
echo "PASS: add_bms empty ket\n";

$res = call(['tanggal'=>'2026-08-01','keterangan'=>'x','jenis'=>'bogus','jumlah'=>50000], 'add_bms');
if (http_response_code() !== 400) fail("add_bms bogus jenis should 400");
echo "PASS: add_bms bogus jenis\n";

$res = call(['tanggal'=>'2026-08-01','keterangan'=>'x','jenis'=>'setor','jumlah'=>0], 'add_bms');
if (http_response_code() !== 400) fail("add_bms jumlah=0 should 400");
echo "PASS: add_bms jumlah=0\n";

$res = call(['tanggal'=>'2026-08-01','keterangan'=>'x','jenis'=>'setor','jumlah'=>-100], 'add_bms');
if (http_response_code() !== 400) fail("add_bms jumlah negative should 400");
echo "PASS: add_bms jumlah negative\n";

$res = call(['id'=>$bid,'tanggal'=>'2026-08-02','keterangan'=>'TEST_updated','jenis'=>'tarik','jumlah'=>20000], 'update_bms');
if (empty($res['ok'])) fail("update_bms failed: " . json_encode($res));
$row = $pdo->prepare("SELECT * FROM kas_bms WHERE id=?");
$row->execute([$bid]);
$r = $row->fetch();
if ($r['keterangan'] !== 'TEST_updated') fail("update_bms ket not updated");
if ($r['jenis'] !== 'tarik') fail("update_bms jenis not updated");
if ((float)$r['jumlah'] !== 20000.0) fail("update_bms jumlah not updated");
echo "PASS: update_bms\n";

$res = call(['id'=>$bid,'tanggal'=>'2026-08-02','keterangan'=>'','jenis'=>'tarik','jumlah'=>20000], 'update_bms');
if (http_response_code() !== 400) fail("update_bms empty ket should 400");
echo "PASS: update_bms validation\n";

$res = call(['id'=>999999,'tanggal'=>'2026-08-02','keterangan'=>'x','jenis'=>'tarik','jumlah'=>20000], 'update_bms');
if (empty($res['ok'])) fail("update_bms non-existent id should be silent ok, got: " . json_encode($res));
echo "PASS: update_bms non-existent id\n";

$res = call(['id'=>$bid], 'delete_bms');
if (empty($res['ok'])) fail("delete_bms failed: " . json_encode($res));
$row = $pdo->prepare("SELECT id FROM kas_bms WHERE id=?");
$row->execute([$bid]);
if ($row->fetch()) fail("delete_bms row still exists");
echo "PASS: delete_bms\n";

$res = call(['id'=>999999], 'delete_bms');
if (empty($res['ok'])) fail("delete_bms non-existent id should be silent ok, got: " . json_encode($res));
echo "PASS: delete_bms non-existent id\n";

$pdo->exec("DELETE FROM kas_bms WHERE keterangan LIKE 'TEST_%'");
ob_end_flush();

if ($fail > 0) {
    echo "SUMMARY: $fail FAILURES\n";
    exit(1);
}
echo "\n=== ALL BMS ADMIN TESTS PASSED ===\n";
exit(0);
