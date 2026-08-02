<?php
session_start();
$_SESSION['admin_logged'] = true;
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("INSERT INTO siswa (absen, nama) VALUES ('9999','Test Siswa')");
$sid = $pdo->lastInsertId();

function call(array $req, string $action): array {
    $_POST = $req; $_REQUEST = array_merge($req, ['action'=>$action]);
    ob_start(); include __DIR__ . '/../../src/api/admin.php'; $raw = ob_get_clean();
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['_raw' => $raw];
}

$add = call(['siswa_id'=>$sid,'tanggal'=>'2026-08-01','keterangan'=>'Denda telat','jumlah'=>2000], 'add_piutang');
if (empty($add['ok'])) { fwrite(STDERR, "FAIL add_piutang\n"); exit(1); }
$pid = $add['id'];

$upd = call(['id'=>$pid,'status'=>'sudah_dibayar'], 'update_piutang_status');
if (empty($upd['ok'])) { fwrite(STDERR, "FAIL update_piutang\n"); exit(1); }

$bank = call(['tanggal'=>'2026-08-01','keterangan'=>'Setor awal','jenis'=>'setor','jumlah'=>50000], 'add_bank');
if (empty($bank['ok'])) { fwrite(STDERR, "FAIL add_bank\n"); exit(1); }
$bid = $bank['id'];

$del = call(['id'=>$bid], 'delete_bank');
if (empty($del['ok'])) { fwrite(STDERR, "FAIL delete_bank\n"); exit(1); }

$pdo->prepare("DELETE FROM piutang_denda WHERE id=?")->execute([$pid]);
$pdo->prepare("DELETE FROM siswa WHERE id=?")->execute([$sid]);

echo "PASS: piutang+bank CRUD\n";
exit(0);
