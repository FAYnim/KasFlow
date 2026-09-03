<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/FinanceEngine.php';

$pdo = db();
echo "Running FinanceEngine Unit Tests...\n";

// Wrap in rollback transaction or isolate testing
$pdo->beginTransaction();

try {
    // 1. Check getAccountsWithBalances
    $accounts = FinanceEngine::getAccountsWithBalances($pdo);
    assert(is_array($accounts) && count($accounts) >= 4, "getAccountsWithBalances should return array of accounts");
    $firstAcc = $accounts[0];
    assert(isset($firstAcc['balance']), "Account must have balance field");
    assert(isset($firstAcc['name']), "Account must have name field");

    // 2. Add income transaction
    $accId1 = (int)$accounts[0]['id'];
    $accId2 = (int)$accounts[1]['id'];
    
    $catIncome = $pdo->query("SELECT id FROM categories WHERE type IN ('income','both') LIMIT 1")->fetchColumn();
    $catExpense = $pdo->query("SELECT id FROM categories WHERE type IN ('expense','both') LIMIT 1")->fetchColumn();

    $t1Id = FinanceEngine::addTransaction($pdo, [
        'date' => date('Y-m-d'),
        'type' => 'income',
        'account_id' => $accId1,
        'category_id' => $catIncome,
        'amount' => 100000,
        'description' => 'Test Setoran Awal',
        'created_by' => 'admin_test'
    ]);
    assert($t1Id > 0, "addTransaction income must return positive ID");

    // Check balance updated
    $accsUpdated = FinanceEngine::getAccountsWithBalances($pdo);
    $acc1Updated = current(array_filter($accsUpdated, fn($a) => $a['id'] == $accId1));
    assert((float)$acc1Updated['balance'] >= 100000, "Account 1 balance should increase by 100k");

    // 3. Add expense transaction
    $t2Id = FinanceEngine::addTransaction($pdo, [
        'date' => date('Y-m-d'),
        'type' => 'expense',
        'account_id' => $accId1,
        'category_id' => $catExpense,
        'amount' => 20000,
        'description' => 'Test Beli ATK',
        'created_by' => 'admin_test'
    ]);
    assert($t2Id > 0, "addTransaction expense must return positive ID");

    // Check balance after expense
    $accsUpdated2 = FinanceEngine::getAccountsWithBalances($pdo);
    $acc1Updated2 = current(array_filter($accsUpdated2, fn($a) => $a['id'] == $accId1));
    assert((float)$acc1Updated2['balance'] == (float)$acc1Updated['balance'] - 20000, "Account 1 balance should decrease by 20k");

    // 4. Add transfer transaction
    $t3Id = FinanceEngine::addTransaction($pdo, [
        'date' => date('Y-m-d'),
        'type' => 'transfer',
        'account_id' => $accId1,
        'to_account_id' => $accId2,
        'amount' => 30000,
        'description' => 'Test Pindah Kas ke DANA',
        'created_by' => 'admin_test'
    ]);
    assert($t3Id > 0, "addTransaction transfer must return positive ID");

    $accsUpdated3 = FinanceEngine::getAccountsWithBalances($pdo);
    $acc1AfterTransfer = current(array_filter($accsUpdated3, fn($a) => $a['id'] == $accId1));
    $acc2AfterTransfer = current(array_filter($accsUpdated3, fn($a) => $a['id'] == $accId2));

    assert((float)$acc1AfterTransfer['balance'] == (float)$acc1Updated2['balance'] - 30000, "Account 1 should decrease by 30k after transfer");
    assert((float)$acc2AfterTransfer['balance'] == 30000, "Account 2 should receive 30k from transfer");

    // 5. Test Kas Mingguan Queue & Claiming
    $stmtQ = $pdo->prepare("INSERT INTO kas_mingguan_queue (bulan, tahun, nominal, keterangan, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmtQ->execute(['September', 2026, 50000, 'Iuran Kas Minggu 1']);
    $queueId = (int)$pdo->lastInsertId();

    $summaryBefore = FinanceEngine::getSummary($pdo);
    assert((float)$summaryBefore['total_pending_queue'] >= 50000, "Pending queue should reflect 50k");

    $claimedTxId = FinanceEngine::claimKasQueue($pdo, $queueId, $accId1, $catIncome, 'admin_test');
    assert($claimedTxId > 0, "claimKasQueue must return new transaction ID");

    $queueRow = $pdo->query("SELECT status, transaction_id FROM kas_mingguan_queue WHERE id = $queueId")->fetch();
    assert($queueRow['status'] === 'recorded', "Queue status must be recorded after claim");
    assert((int)$queueRow['transaction_id'] === $claimedTxId, "Queue transaction_id must link to claimed tx");

    echo "ALL 5 TEST SUITES PASSED!\n";
} finally {
    // Rollback test changes to keep clean database
    $pdo->rollBack();
}
