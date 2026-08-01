<?php
session_start();
$_SESSION['admin_logged'] = true;

function call(array $post, string $action): array {
    $_POST = $post; $_REQUEST = array_merge($post, ['action'=>$action]);
    ob_start(); include __DIR__ . '/../api_admin.php'; $raw = ob_get_clean();
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['_raw' => $raw];
}

$add = call(['tanggal'=>'2026-08-01','keterangan'=>'Test','jenis'=>'masuk','nominal'=>10000], 'add_jurnal');
if (empty($add['ok'])) { fwrite(STDERR, "FAIL: add_jurnal: ".json_encode($add)."\n"); exit(1); }
$id = $add['id'];

$upd = call(['id'=>$id,'tanggal'=>'2026-08-02','keterangan'=>'Test2','jenis'=>'keluar','nominal'=>5000], 'update_jurnal');
if (empty($upd['ok'])) { fwrite(STDERR, "FAIL: update_jurnal\n"); exit(1); }

$del = call(['id'=>$id], 'delete_jurnal');
if (empty($del['ok'])) { fwrite(STDERR, "FAIL: delete_jurnal\n"); exit(1); }

echo "PASS: jurnal CRUD\n";
exit(0);
