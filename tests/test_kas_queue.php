<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/FinanceEngine.php';

$pdo = db();
echo "Testing Kas Mingguan Queue Integration...\n";

$pdo->beginTransaction();

try {
    $siswa = $pdo->query("SELECT id FROM siswa LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!$siswa) {
        $pdo->exec("INSERT INTO siswa (absen, nama) VALUES ('01', 'Siswa Test')");
        $sid = (int)$pdo->lastInsertId();
    } else {
        $sid = (int)$siswa['id'];
    }

    $bulan = 'September';
    $tahun = 2026;
    $tarif = 5000;

    // Reset this student's kas for test month
    $pdo->prepare("DELETE FROM kas_mingguan WHERE siswa_id = ? AND bulan = ? AND tahun = ?")->execute([$sid, $bulan, $tahun]);

    // 1. Simulate ticking Minggu 1 & 2 (2 * 5000 = 10000)
    $newChecked = 2;
    $netNominal = $newChecked * $tarif;
    $insQ = $pdo->prepare("INSERT INTO kas_mingguan_queue (bulan, tahun, nominal, keterangan, status) VALUES (?, ?, ?, 'Test Penerimaan', 'pending')");
    $insQ->execute([$bulan, $tahun, $netNominal]);
    $qId = (int)$pdo->lastInsertId();

    $qRow = $pdo->query("SELECT * FROM kas_mingguan_queue WHERE id = $qId")->fetch();
    assert($qRow['status'] === 'pending', "Queue should be pending");
    assert((float)$qRow['nominal'] === 10000.0, "Queue nominal should be 10000");

    // 2. Claim into Account 1
    $accounts = FinanceEngine::getAccountsWithBalances($pdo);
    $categories = FinanceEngine::getCategories($pdo, 'income');
    $accId = (int)$accounts[0]['id'];
    $catId = (int)$categories[0]['id'];

    $txId = FinanceEngine::claimKasQueue($pdo, $qId, $accId, $catId, 'admin_test');
    assert($txId > 0, "Claiming should create transaction");

    $claimedQ = $pdo->query("SELECT status, transaction_id FROM kas_mingguan_queue WHERE id = $qId")->fetch();
    assert($claimedQ['status'] === 'recorded', "Queue status should be recorded");
    assert((int)$claimedQ['transaction_id'] === $txId, "Transaction ID should link");

    // 3. Simulate untick Minggu 1 (-5000 correction)
    $corrNominal = -5000;
    $insCorr = $pdo->prepare("INSERT INTO kas_mingguan_queue (bulan, tahun, nominal, keterangan, status) VALUES (?, ?, ?, 'Test Koreksi', 'pending')");
    $insCorr->execute([$bulan, $tahun, $corrNominal]);
    $corrQId = (int)$pdo->lastInsertId();

    $corrTxId = FinanceEngine::claimKasQueue($pdo, $corrQId, $accId, $catId, 'admin_test');
    assert($corrTxId > 0, "Correction claim should succeed");

    $txCorr = $pdo->query("SELECT type, amount FROM transactions WHERE id = $corrTxId")->fetch();
    assert($txCorr['type'] === 'expense', "Correction should be recorded as expense transaction");
    assert((float)$txCorr['amount'] === 5000.0, "Correction amount should be 5000");

    echo "Kas Mingguan Queue Integration Tests Passed Successfully!\n";
} finally {
    $pdo->rollBack();
}
