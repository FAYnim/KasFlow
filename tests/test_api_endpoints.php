<?php
// Test API Endpoints logic
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/FinanceEngine.php';
require_once __DIR__ . '/../src/lib/activity_log.php';

$pdo = db();
echo "Testing Centralized Cashflow API handlers logic...\n";

$pdo->beginTransaction();

try {
    // 1. Test get_finance_overview
    $accounts = FinanceEngine::getAccountsWithBalances($pdo);
    $categories = FinanceEngine::getCategories($pdo);
    $summary = FinanceEngine::getSummary($pdo);
    $pendingQueue = FinanceEngine::getKasQueue($pdo, 'pending');

    assert(!empty($accounts), "Accounts should not be empty");
    assert(!empty($categories), "Categories should not be empty");
    assert(isset($summary['total_balance']), "Summary total_balance must exist");

    // 2. Test manage_account (add and toggle)
    $stmtAcc = $pdo->prepare("INSERT INTO accounts (name, type, icon, initial_balance, sort_order) VALUES (?, ?, ?, ?, ?)");
    $stmtAcc->execute(['Dompet Uji Coba', 'other', 'fa-solid fa-box', 50000, 10]);
    $newAccId = (int)$pdo->lastInsertId();
    assert($newAccId > 0, "New account inserted");

    $pdo->prepare("UPDATE accounts SET is_active = 0 WHERE id = ?")->execute([$newAccId]);
    $checkInactive = $pdo->query("SELECT is_active FROM accounts WHERE id = $newAccId")->fetchColumn();
    assert($checkInactive == 0, "Account is_active toggled to 0");

    // 3. Test manage_category (add and edit)
    $stmtCat = $pdo->prepare("INSERT INTO categories (name, type, icon, color) VALUES (?, ?, ?, ?)");
    $stmtCat->execute(['Kategori Uji Coba', 'both', 'fa-solid fa-star', '#ff0000']);
    $newCatId = (int)$pdo->lastInsertId();
    assert($newCatId > 0, "New category inserted");

    // 4. Test add_transaction & get_transactions
    $txId = FinanceEngine::addTransaction($pdo, [
        'date' => date('Y-m-d'),
        'type' => 'income',
        'account_id' => $accounts[0]['id'],
        'category_id' => $newCatId,
        'amount' => 75000,
        'description' => 'Test Transaksi API',
        'created_by' => 'admin_test'
    ]);
    assert($txId > 0, "Transaction created");

    $fetchedTx = FinanceEngine::getTransactions($pdo, ['limit' => 10]);
    $found = array_filter($fetchedTx, fn($t) => $t['id'] === $txId);
    assert(!empty($found), "Fetched transactions should contain the newly created transaction");

    // 5. Test update_transaction
    FinanceEngine::updateTransaction($pdo, $txId, [
        'amount' => 85000,
        'description' => 'Updated Test Transaksi'
    ]);
    $updatedTx = FinanceEngine::getTransactions($pdo, ['search' => 'Updated Test']);
    assert(!empty($updatedTx) && $updatedTx[0]['amount'] == 85000, "Transaction updated successfully");

    // 6. Test delete_transaction
    FinanceEngine::deleteTransaction($pdo, $txId);
    $deletedTx = FinanceEngine::getTransactions($pdo, ['search' => 'Updated Test']);
    assert(empty($deletedTx), "Transaction deleted successfully");

    echo "API Endpoints Logic Tests Passed Successfully!\n";
} finally {
    $pdo->rollBack();
}
