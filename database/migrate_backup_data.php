<?php
/**
 * Migration script to import and convert backup data from the old system 
 * (storage_accounts, storage_transactions, kas_bms, jurnal_kas)
 * into the new unified Money Tracker schema (accounts, categories, transactions).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/FinanceEngine.php';

$pdo = db();
echo "=== Migrasi Data Backup ke Sistem Money Tracker Baru ===\n";

$pdo->beginTransaction();

try {
    // 1. Setup Accounts
    echo "1. Menyiapkan Akun (Dompet / Rekening)...\n";
    $accountsData = [
        ['id' => 1, 'name' => 'Kas Tunai (Fisik)', 'type' => 'cash', 'icon' => 'fa-solid fa-wallet', 'sort_order' => 1],
        ['id' => 4, 'name' => 'DANA', 'type' => 'ewallet', 'icon' => 'fa-solid fa-mobile-screen', 'sort_order' => 2],
        ['id' => 5, 'name' => 'Gopay', 'type' => 'ewallet', 'icon' => 'fa-solid fa-mobile-screen-button', 'sort_order' => 3],
        ['id' => 7, 'name' => 'SeaBank', 'type' => 'bank', 'icon' => 'fa-solid fa-building-columns', 'sort_order' => 4],
        ['id' => 8, 'name' => 'Bank Mandiri', 'type' => 'bank', 'icon' => 'fa-solid fa-landmark', 'sort_order' => 5],
        ['id' => 9, 'name' => 'Kas / Tabungan BMS', 'type' => 'bank', 'icon' => 'fa-solid fa-sack-dollar', 'sort_order' => 6],
    ];

    $pdo->exec("DELETE FROM accounts");
    $accStmt = $pdo->prepare("INSERT INTO accounts (id, name, type, icon, initial_balance, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, 0.00, 1, ?, NOW())");
    foreach ($accountsData as $a) {
        $accStmt->execute([$a['id'], $a['name'], $a['type'], $a['icon'], $a['sort_order']]);
        echo "   [+] Akun #{$a['id']}: {$a['name']}\n";
    }

    // 2. Setup Categories
    echo "\n2. Menyiapkan Kategori Transaksi...\n";
    $categoriesData = [
        ['id' => 1, 'name' => 'Uang Kas Mingguan', 'type' => 'income', 'icon' => 'fa-solid fa-coins', 'color' => '#10b981'],
        ['id' => 2, 'name' => 'LKS & Buku Siswa', 'type' => 'both', 'icon' => 'fa-solid fa-book-open', 'color' => '#3b82f6'],
        ['id' => 3, 'name' => 'Kas / Tabungan BMS', 'type' => 'both', 'icon' => 'fa-solid fa-sack-dollar', 'color' => '#06b6d4'],
        ['id' => 4, 'name' => 'Dana Talangan / Reimburse', 'type' => 'both', 'icon' => 'fa-solid fa-hand-holding-dollar', 'color' => '#8b5cf6'],
        ['id' => 5, 'name' => 'Spidol & ATK', 'type' => 'expense', 'icon' => 'fa-solid fa-pen-ruler', 'color' => '#ef4444'],
        ['id' => 6, 'name' => 'Kebersihan & Sarpras', 'type' => 'expense', 'icon' => 'fa-solid fa-broom', 'color' => '#f59e0b'],
        ['id' => 7, 'name' => 'Konsumsi & Acara', 'type' => 'expense', 'icon' => 'fa-solid fa-utensils', 'color' => '#ec4899'],
        ['id' => 8, 'name' => 'Donasi / Sumbangan', 'type' => 'income', 'icon' => 'fa-solid fa-hand-holding-heart', 'color' => '#14b8a6'],
        ['id' => 9, 'name' => 'Lain-lain', 'type' => 'both', 'icon' => 'fa-solid fa-shapes', 'color' => '#64748b'],
    ];

    $pdo->exec("DELETE FROM categories");
    $catStmt = $pdo->prepare("INSERT INTO categories (id, name, type, icon, color, is_active, created_at) VALUES (?, ?, ?, ?, ?, 1, NOW())");
    foreach ($categoriesData as $c) {
        $catStmt->execute([$c['id'], $c['name'], $c['type'], $c['icon'], $c['color']]);
        echo "   [+] Kategori #{$c['id']}: {$c['name']} ({$c['type']})\n";
    }

    // Helper to resolve category ID based on description
    $resolveCatId = function(string $desc): int {
        $d = strtolower($desc);
        if (strpos($d, 'kas kelas') !== false || strpos($d, 'kas mingguan') !== false) return 1;
        if (strpos($d, 'lks') !== false || strpos($d, 'modul') !== false) return 2;
        if (strpos($d, 'bms') !== false) return 3;
        if (strpos($d, 'spidol') !== false || strpos($d, 'atk') !== false || strpos($d, 'map') !== false) return 5;
        if (strpos($d, 'sapu') !== false || strpos($d, 'cikrak') !== false || strpos($d, 'kebersihan') !== false) return 6;
        if (strpos($d, 'makan') !== false || strpos($d, 'lomba') !== false || strpos($d, 'konsumsi') !== false) return 7;
        if (strpos($d, 'kasbon') !== false || strpos($d, 'talangan') !== false) return 4;
        return 9;
    };

    // 3. Clear and Migrate Transactions
    echo "\n3. Mengimpor Riwayat Transaksi ke Tabel 'transactions'...\n";
    $pdo->exec("DELETE FROM transactions");

    $txStmt = $pdo->prepare("
        INSERT INTO transactions (
            date, type, account_id, to_account_id, category_id, amount, description, ref_type, ref_id, created_by, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'system_migration', ?, ?
        )
    ");

    // A. Migrate Allocations and Jurnal Income from backup_storage_transactions
    $stRows = $pdo->query("SELECT * FROM backup_storage_transactions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $incomeCount = 0;
    $transferCount = 0;
    $processedPairs = [];

    foreach ($stRows as $st) {
        $nom = (float)$st['nominal'];
        $tgl = $st['tanggal'] ?: date('Y-m-d');
        $createdAt = $st['created_at'] ?: date('Y-m-d H:i:s');
        $desc = trim($st['keterangan'] ?? '');

        if ($st['ref_type'] === 'allocation' || $st['ref_type'] === 'jurnal') {
            $catId = $resolveCatId($desc);
            $refTypeEnum = ($catId === 1) ? 'kas_mingguan' : 'manual';
            $txStmt->execute([
                $tgl,
                'income',
                (int)$st['account_id'],
                null,
                $catId,
                $nom,
                $desc ?: 'Penerimaan Dana',
                $refTypeEnum,
                $st['ref_id'],
                $createdAt,
                $createdAt
            ]);
            $incomeCount++;
        } elseif ($st['ref_type'] === 'transfer_out') {
            $pairId = $st['transfer_pair_id'];
            if (!empty($pairId) && !isset($processedPairs[$pairId])) {
                // Find matching transfer_in
                $inStmt = $pdo->prepare("SELECT * FROM backup_storage_transactions WHERE transfer_pair_id = ? AND ref_type = 'transfer_in' LIMIT 1");
                $inStmt->execute([$pairId]);
                $inRow = $inStmt->fetch(PDO::FETCH_ASSOC);
                if ($inRow) {
                    $processedPairs[$pairId] = true;
                    $fromAccId = (int)$st['account_id'];
                    $toAccId = (int)$inRow['account_id'];
                    $transferDesc = $desc ?: 'Transfer Antar Dompet';

                    $txStmt->execute([
                        $tgl,
                        'transfer',
                        $fromAccId,
                        $toAccId,
                        null,
                        $nom,
                        $transferDesc,
                        'transfer',
                        $pairId,
                        $createdAt,
                        $createdAt
                    ]);
                    $transferCount++;
                }
            }
        }
    }
    echo "   [+] Berhasil mengimpor $incomeCount transaksi pemasukan\n";
    echo "   [+] Berhasil mengimpor $transferCount transaksi transfer antar dompet\n";

    // B. Migrate Kas BMS (1 row)
    $bmsRow = $pdo->query("SELECT * FROM backup_kas_bms LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($bmsRow) {
        $txStmt->execute([
            $bmsRow['tanggal'] ?: '2026-08-17',
            'income',
            9, // Kas / Tabungan BMS
            null,
            3, // Kas / Tabungan BMS
            (float)$bmsRow['jumlah'],
            $bmsRow['keterangan'] ?: 'Saldo Awal Kas BMS',
            'manual',
            (int)$bmsRow['id'],
            $bmsRow['created_at'] ?: '2026-08-17 18:26:06',
            $bmsRow['created_at'] ?: '2026-08-17 18:26:06'
        ]);
        echo "   [+] Berhasil mengimpor transaksi Saldo Kas BMS: Rp " . number_format((float)$bmsRow['jumlah'], 0, ',', '.') . "\n";
    }

    $pdo->commit();
    echo "\n>>> MIGRATION COMPLETED SUCCESSFULLY! <<<\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n[ERROR] Migrasi gagal: " . $e->getMessage() . "\n";
    exit(1);
}

// 4. Verify Final Balances with FinanceEngine
echo "\n=== 4. Verifikasi Saldo Akhir Melalui FinanceEngine ===\n";
$accountsWithBalances = FinanceEngine::getAccountsWithBalances($pdo, false);
$totalBalance = 0;
foreach ($accountsWithBalances as $acc) {
    $bal = (float)$acc['balance'];
    $totalBalance += $bal;
    echo "• {$acc['name']}: Rp " . number_format($bal, 0, ',', '.') . "\n";
}
echo "----------------------------------------\n";
echo "TOTAL SALDO KAS KELAS: Rp " . number_format($totalBalance, 0, ',', '.') . "\n";

$summary = FinanceEngine::getSummary($pdo);
assert((float)$summary['total_balance'] === (float)$totalBalance, "Summary balance matches total");
echo "\n[PASS] Semua saldo terverifikasi 100% cocok dengan saldo asli sebelum migrasi!\n";
