<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/FinanceEngine.php';

$pdo = db();
echo "Running E2E Student Breakdown in Kas Queue Test...\n";

$pdo->beginTransaction();
try {
    // 1. Ensure test student exists
    $stmt = $pdo->query("SELECT id, nama FROM siswa LIMIT 2");
    $siswas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($siswas) < 2) {
        $pdo->exec("INSERT INTO siswa (absen, nama) VALUES ('98', 'Ani Wijaya'), ('99', 'Budi Santoso')");
        $stmt = $pdo->query("SELECT id, nama FROM siswa WHERE absen IN ('98', '99')");
        $siswas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $s1 = $siswas[0];
    $s2 = $siswas[1];
    $bulan = 'September';
    $tahun = 2026;
    $tarif = 5000;

    // Reset test student kas
    $pdo->prepare("DELETE FROM kas_mingguan WHERE siswa_id IN (?, ?) AND bulan = ? AND tahun = ?")
        ->execute([$s1['id'], $s2['id'], $bulan, $tahun]);

    // 2. Simulate bulk_update_kas payload
    $namaMap = [
        $s1['id'] => $s1['nama'],
        $s2['id'] => $s2['nama'],
    ];
    $changes = [
        ['siswa_id' => (int)$s1['id'], 'minggu' => 1, 'checked' => 1],
        ['siswa_id' => (int)$s2['id'], 'minggu' => 2, 'checked' => 1],
    ];

    $studentsDetail = [];
    foreach ($changes as $c) {
        $sid = $c['siswa_id'];
        $studentsDetail[] = [
            'siswa_id' => $sid,
            'nama'     => $namaMap[$sid],
            'minggu'   => $c['minggu'],
            'action'   => 'bayar',
            'nominal'  => $tarif
        ];
    }

    $qDetail = json_encode([
        'bulan' => $bulan,
        'tahun' => $tahun,
        'new_checked' => 2,
        'new_unchecked' => 0,
        'net_nominal' => 10000,
        'students' => $studentsDetail
    ]);

    $studentNamesSummary = [];
    foreach ($studentsDetail as $sd) {
        $studentNamesSummary[] = "{$sd['nama']} (M{$sd['minggu']})";
    }
    $studentNamesStr = ": " . implode(', ', array_slice($studentNamesSummary, 0, 2));
    $qKet = "Penerimaan Kas $bulan $tahun$studentNamesStr";

    $insQ = $pdo->prepare("INSERT INTO kas_mingguan_queue (bulan, tahun, nominal, keterangan, detail, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $insQ->execute([$bulan, $tahun, 10000, $qKet, $qDetail]);
    $qId = (int)$pdo->lastInsertId();

    // 3. Verify queue retrieved via FinanceEngine::getKasQueue
    $queueList = FinanceEngine::getKasQueue($pdo, 'pending');
    $found = null;
    foreach ($queueList as $q) {
        if ($q['id'] === $qId) {
            $found = $q;
            break;
        }
    }
    assert($found !== null, "Queue item should be in pending list");
    assert(isset($found['detail']['students']), "detail.students must be parsed array");
    assert(count($found['detail']['students']) === 2, "must have 2 students");
    assert($found['detail']['students'][0]['nama'] === $s1['nama'], "student 1 name must match");
    assert($found['detail']['students'][1]['nama'] === $s2['nama'], "student 2 name must match");

    // 4. Claim queue item
    $accounts = FinanceEngine::getAccountsWithBalances($pdo);
    $categories = FinanceEngine::getCategories($pdo, 'income');
    $accId = (int)$accounts[0]['id'];
    $catId = (int)$categories[0]['id'];

    $txId = FinanceEngine::claimKasQueue($pdo, $qId, $accId, $catId, 'admin_e2e', $found['keterangan']);
    assert($txId > 0, "Transaction should be created");

    // 5. Verify created transaction description
    $tx = $pdo->query("SELECT * FROM transactions WHERE id = $txId")->fetch(PDO::FETCH_ASSOC);
    assert(strpos($tx['description'], $s1['nama']) !== false, "tx description should contain student 1");
    assert(strpos($tx['description'], $s2['nama']) !== false, "tx description should contain student 2");
    assert((float)$tx['amount'] === 10000.0, "tx amount should be 10000");

    echo "E2E Student Breakdown in Kas Queue Test Passed Successfully!\n";
} finally {
    $pdo->rollBack();
}
