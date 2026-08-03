<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("DELETE FROM kas_mingguan");
$pdo->exec("DELETE FROM siswa");
$pdo->exec("DELETE FROM jurnal_kas");
$pdo->exec("ALTER TABLE siswa AUTO_INCREMENT = 1");
$pdo->exec("ALTER TABLE jurnal_kas AUTO_INCREMENT = 1");

$stmt = $pdo->prepare("INSERT INTO siswa (absen, nama) VALUES (?, ?)");
$siswa = [
    ['1001','Ahmad Fauzi'],['1002','Budi Santoso'],['1003','Citra Lestari'],
    ['1004','Dewi Anggraini'],['1005','Eko Prasetyo'],['1006','Fitri Handayani'],
    ['1007','Galih Pratama'],['1008','Hana Safitri'],['1009','Indra Kurniawan'],['1010','Joko Susilo'],
];
foreach ($siswa as $s) $stmt->execute($s);

$bulan = 'Agustus'; $tahun = 2026;
$km = $pdo->prepare("INSERT INTO kas_mingguan (siswa_id, bulan, tahun, minggu_1, minggu_2, minggu_3, minggu_4, minggu_5, total_bayar) VALUES (?,?,?,?,?,?,?,?,?)");
foreach (range(1,10) as $sid) {
    $m1=1;$m2=1;$m3=rand(0,1);$m4=rand(0,1);$m5=0;
    $total = ($m1+$m2+$m3+$m4+$m5) * 5000;
    $km->execute([$sid,$bulan,$tahun,$m1,$m2,$m3,$m4,$m5,$total]);
}

$jk = $pdo->prepare("INSERT INTO jurnal_kas (tanggal, keterangan, jenis, nominal) VALUES (?,?,?,?)");
$jk->execute(['2026-08-01','Saldo awal Agustus','masuk',50000]);
$jk->execute(['2026-08-05','Beli alat tulis','keluar',15000]);
$jk->execute(['2026-08-12','Sumbangan sukarela','masuk',25000]);
$jk->execute(['2026-08-20','Bayar konsumsi rapat','keluar',20000]);

echo "Dummy seeded.\n";
