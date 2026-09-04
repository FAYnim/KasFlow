<?php
/**
 * End-to-End Verification Test for Centralized Money Tracker Cashflow
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/FinanceEngine.php';

function assertEq($actual, $expected, $label) {
    if ($actual == $expected) {
        echo "[PASS] $label (Expected: $expected, Actual: $actual)\n";
    } else {
        echo "[FAIL] $label (Expected: " . var_export($expected, true) . ", Actual: " . var_export($actual, true) . ")\n";
        exit(1);
    }
}

$db = db();
echo "=== 1. Setup E2E Test Fixtures ===\n";

// Ensure clean test state
$db->exec("DELETE FROM transactions WHERE description LIKE 'E2E TEST%'");
$db->exec("DELETE FROM kas_mingguan_queue WHERE keterangan LIKE 'E2E TEST%'");

// Pick or create test accounts
$stmt = $db->query("SELECT id, name FROM accounts WHERE name = 'DANA' LIMIT 1");
$dana = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$dana) {
    $db->prepare("INSERT INTO accounts (name, type, icon, is_active) VALUES ('DANA', 'ewallet', 'fa-solid fa-wallet', 1)")->execute();
    $danaId = (int)$db->lastInsertId();
} else {
    $danaId = (int)$dana['id'];
}

$stmt = $db->query("SELECT id, name FROM accounts WHERE name = 'SeaBank' LIMIT 1");
$seabank = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$seabank) {
    $db->prepare("INSERT INTO accounts (name, type, icon, is_active) VALUES ('SeaBank', 'bank', 'fa-solid fa-building-columns', 1)")->execute();
    $seabankId = (int)$db->lastInsertId();
} else {
    $seabankId = (int)$seabank['id'];
}

// Pick or create test categories
$stmt = $db->query("SELECT id, name FROM categories WHERE name = 'Uang Kas Mingguan' LIMIT 1");
$kasCategory = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$kasCategory) {
    $db->prepare("INSERT INTO categories (name, type, icon, color) VALUES ('Uang Kas Mingguan', 'income', 'fa-solid fa-coins', '#10b981')")->execute();
    $kasCatId = (int)$db->lastInsertId();
} else {
    $kasCatId = (int)$kasCategory['id'];
}

$stmt = $db->query("SELECT id, name FROM categories WHERE name = 'Spidol & ATK' LIMIT 1");
$atkCategory = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$atkCategory) {
    $db->prepare("INSERT INTO categories (name, type, icon, color) VALUES ('Spidol & ATK', 'expense', 'fa-solid fa-pen-ruler', '#f59e0b')")->execute();
    $atkCatId = (int)$db->lastInsertId();
} else {
    $atkCatId = (int)$atkCategory['id'];
}

$initDana = FinanceEngine::getAccountBalance($db, $danaId);
$initSeaBank = FinanceEngine::getAccountBalance($db, $seabankId);

echo "Initial Balance DANA: $initDana\n";
echo "Initial Balance SeaBank: $initSeaBank\n";

echo "\n=== 2. Simulate Kas Mingguan Tick & Untick Queue ===\n";
$bulan = 'Januari';
$tahun = 2026;

// Insert positive queue items (simulating tick 2 weeks)
$stmt = $db->prepare("INSERT INTO kas_mingguan_queue (bulan, tahun, nominal, keterangan, status) VALUES (?, ?, ?, ?, 'pending')");
$stmt->execute([$bulan, $tahun, 10000, 'E2E TEST Kas Minggu 1']);
$q1 = (int)$db->lastInsertId();

$stmt->execute([$bulan, $tahun, 10000, 'E2E TEST Kas Minggu 2']);
$q2 = (int)$db->lastInsertId();

// Insert negative correction queue item (simulating untick of dues - Option A)
$stmt->execute([$bulan, $tahun, -5000, 'E2E TEST Koreksi Pembatalan Minggu 1']);
$q3 = (int)$db->lastInsertId();

// Verify pending queue count
$pending = FinanceEngine::getKasQueue($db, 'pending');
$testQueueItems = array_filter($pending, function($x) {
    return strpos($x['keterangan'], 'E2E TEST') !== false;
});
assertEq(count($testQueueItems), 3, "Found 3 E2E queue items in pending queue");

echo "\n=== 3. Claim Queue Items into DANA Account ===\n";
// Claim positive queue items
$txId1 = FinanceEngine::claimKasQueue($db, $q1, $danaId, $kasCatId, 'admin_e2e', 'E2E TEST Claim Minggu 1');
$txId2 = FinanceEngine::claimKasQueue($db, $q2, $danaId, $kasCatId, 'admin_e2e', 'E2E TEST Claim Minggu 2');
assertEq($txId1 > 0, true, "Positive queue item 1 claimed into transaction");
assertEq($txId2 > 0, true, "Positive queue item 2 claimed into transaction");

// Claim negative correction item (Option A: deducted as expense from DANA)
$txId3 = FinanceEngine::claimKasQueue($db, $q3, $danaId, $kasCatId, 'admin_e2e', 'E2E TEST Claim Koreksi');
assertEq($txId3 > 0, true, "Negative correction queue item claimed into transaction");

$balAfterClaims = FinanceEngine::getAccountBalance($db, $danaId);
$expectedAfterClaims = $initDana + 10000 + 10000 - 5000;
assertEq($balAfterClaims, $expectedAfterClaims, "DANA balance after claims (+10k, +10k, -5k = +15k net)");

echo "\n=== 4. Record Expense Transaction (Spidol & ATK) ===\n";
$expTxId = FinanceEngine::addTransaction($db, [
    'date' => date('Y-m-d'),
    'type' => 'expense',
    'account_id' => $danaId,
    'category_id' => $atkCatId,
    'amount' => 5000,
    'description' => 'E2E TEST Beli spidol whiteboard'
]);
assertEq($expTxId > 0, true, "Expense transaction recorded");

$balAfterExpense = FinanceEngine::getAccountBalance($db, $danaId);
assertEq($balAfterExpense, $expectedAfterClaims - 5000, "DANA balance reduced by 5k after expense");

echo "\n=== 5. Transfer Funds Between Dompet (DANA -> SeaBank) ===\n";
$trfTxId = FinanceEngine::addTransaction($db, [
    'date' => date('Y-m-d'),
    'type' => 'transfer',
    'account_id' => $danaId,
    'to_account_id' => $seabankId,
    'amount' => 6000,
    'description' => 'E2E TEST Transfer DANA ke SeaBank'
]);
assertEq($trfTxId > 0, true, "Transfer transaction recorded");

$finalDana = FinanceEngine::getAccountBalance($db, $danaId);
$finalSeaBank = FinanceEngine::getAccountBalance($db, $seabankId);

assertEq($finalDana, $balAfterExpense - 6000, "DANA balance reduced by 6k after transfer out");
assertEq($finalSeaBank, $initSeaBank + 6000, "SeaBank balance increased by 6k after transfer in");

echo "\n=== 6. Finance Overview & Deterministic Balances Check ===\n";
$summary = FinanceEngine::getSummary($db);
$accounts = FinanceEngine::getAccountsWithBalances($db);
$accountsMap = [];
foreach ($accounts as $a) {
    $accountsMap[$a['id']] = $a;
}

assertEq($accountsMap[$danaId]['balance'], $finalDana, "Overview DANA balance matches calculated balance");
assertEq($accountsMap[$seabankId]['balance'], $finalSeaBank, "Overview SeaBank balance matches calculated balance");

// Check transaction ledger pagination and filters
$txList = FinanceEngine::getTransactions($db, ['search' => 'E2E TEST', 'limit' => 10]);
assertEq(count($txList) >= 5, true, "Found all 5 E2E transactions in ledger");

echo "\n=== 7. Cleanup E2E Test Fixtures ===\n";
$db->exec("DELETE FROM transactions WHERE id IN ($txId1, $txId2, $txId3, $expTxId, $trfTxId)");
$db->exec("DELETE FROM kas_mingguan_queue WHERE id IN ($q1, $q2, $q3)");

$clearedDana = FinanceEngine::getAccountBalance($db, $danaId);
assertEq($clearedDana, $initDana, "DANA balance cleanly restored to initial value");

echo "\n>>> ALL END-TO-END FLOW TESTS PASSED SUCCESSFULLY! <<<\n";
