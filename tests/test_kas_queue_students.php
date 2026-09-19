<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/FinanceEngine.php';

$pdo = db();
echo "Testing Student Detail in Kas Queue...\n";

$pdo->beginTransaction();
try {
    $siswa = $pdo->query("SELECT id, nama FROM siswa LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$siswa) {
        $pdo->exec("INSERT INTO siswa (absen, nama) VALUES ('99', 'Budi Santoso')");
        $sid = (int)$pdo->lastInsertId();
        $nama = 'Budi Santoso';
    } else {
        $sid = (int)$siswa['id'];
        $nama = $siswa['nama'];
    }

    $bulan = 'September';
    $tahun = 2026;
    $tarif = 5000;

    $pdo->prepare("DELETE FROM kas_mingguan WHERE siswa_id = ? AND bulan = ? AND tahun = ?")->execute([$sid, $bulan, $tahun]);

    // Test data structure for studentsDetail
    $changes = [
        ['siswa_id' => $sid, 'minggu' => 1, 'checked' => 1]
    ];

    $namaMap = [$sid => $nama];
    $studentsDetail = [];
    foreach ($changes as $c) {
        $studentsDetail[] = [
            'siswa_id' => $c['siswa_id'],
            'nama'     => $namaMap[$c['siswa_id']],
            'minggu'   => $c['minggu'],
            'action'   => 'bayar',
            'nominal'  => $tarif
        ];
    }

    $qDetail = json_encode([
        'bulan' => $bulan,
        'tahun' => $tahun,
        'new_checked' => 1,
        'new_unchecked' => 0,
        'net_nominal' => $tarif,
        'students' => $studentsDetail
    ]);

    $keterangan = "Penerimaan Kas $bulan $tahun: $nama (M1)";

    $insQ = $pdo->prepare("INSERT INTO kas_mingguan_queue (bulan, tahun, nominal, keterangan, detail, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $insQ->execute([$bulan, $tahun, $tarif, $keterangan, $qDetail]);
    $qId = (int)$pdo->lastInsertId();

    $qRow = $pdo->query("SELECT * FROM kas_mingguan_queue WHERE id = $qId")->fetch(PDO::FETCH_ASSOC);
    $parsedDetail = json_decode($qRow['detail'], true);

    assert(isset($parsedDetail['students']), "detail must contain students list");
    assert(count($parsedDetail['students']) === 1, "students list should have 1 entry");
    assert($parsedDetail['students'][0]['nama'] === $nama, "student name must match");
    assert($parsedDetail['students'][0]['minggu'] === 1, "student week must be 1");
    assert($parsedDetail['students'][0]['action'] === 'bayar', "student action must be bayar");
    assert(strpos($qRow['keterangan'], $nama) !== false, "keterangan must include student name");

    echo "Student Detail in Kas Queue Tests Passed!\n";
} finally {
    $pdo->rollBack();
}
