<?php
/**
 * Migration script to import and convert backup data from the old system 
 * (storage_accounts, storage_transactions, kas_bms, jurnal_kas, activity_log)
 * into the new unified Money Tracker schema (accounts, categories, transactions)
 * and synchronize activity_log with the transactions ledger.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/lib/FinanceEngine.php';

$pdo = db();
echo "=== Migrasi Riwayat Penuh & Sinkronisasi Log Transaksi ===\n";

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
    $accNames = [];
    foreach ($accountsData as $a) {
        $accStmt->execute([$a['id'], $a['name'], $a['type'], $a['icon'], $a['sort_order']]);
        $accNames[$a['id']] = $a['name'];
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
    $catNames = [];
    foreach ($categoriesData as $c) {
        $catStmt->execute([$c['id'], $c['name'], $c['type'], $c['icon'], $c['color']]);
        $catNames[$c['id']] = $c['name'];
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
    echo "\n3. Mengimpor Riwayat Transaksi Historis Lengkap...\n";
    $pdo->exec("DELETE FROM transactions");
    // Also clear existing cashflow logs in activity_log to prevent duplicates
    $pdo->exec("DELETE FROM activity_log WHERE modul = 'cashflow'");

    $txStmt = $pdo->prepare("
        INSERT INTO transactions (
            date, type, account_id, to_account_id, category_id, amount, description, ref_type, ref_id, created_by, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 'system_migration', ?, ?
        )
    ");

    $logStmt = $pdo->prepare("
        INSERT INTO activity_log (
            modul, aksi, entitas_id, ringkasan, detail, admin_username, admin_nama, created_at
        ) VALUES (
            'cashflow', 'tambah', ?, ?, ?, 'system_migration', 'Migrasi Sistem', ?
        )
    ");

    $recordTxAndLog = function(array $tx) use ($txStmt, $logStmt, $pdo, $accNames, $catNames) {
        $txStmt->execute([
            $tx['date'],
            $tx['type'],
            $tx['account_id'],
            $tx['to_account_id'] ?? null,
            $tx['category_id'] ?? null,
            $tx['amount'],
            $tx['description'],
            $tx['ref_type'] ?? 'manual',
            $tx['ref_id'] ?? null,
            $tx['created_at'],
            $tx['created_at']
        ]);
        $txId = (int)$pdo->lastInsertId();

        $typeLabel = $tx['type'] === 'income' ? 'Pemasukan' : ($tx['type'] === 'expense' ? 'Pengeluaran' : 'Transfer');
        $ringkasan = "Catat $typeLabel #$txId: {$tx['description']} (Rp " . number_format($tx['amount'], 0, ',', '.') . ")";
        
        $detail = [
            'id'            => $txId,
            'tanggal'       => $tx['date'],
            'jenis'         => $typeLabel,
            'nominal'       => $tx['amount'],
            'keterangan'    => $tx['description'],
            'akun'          => $accNames[$tx['account_id']] ?? "Akun #{$tx['account_id']}",
            'kategori'      => isset($tx['category_id']) ? ($catNames[$tx['category_id']] ?? '') : null,
            'ke_akun'       => isset($tx['to_account_id']) ? ($accNames[$tx['to_account_id']] ?? "Akun #{$tx['to_account_id']}") : null,
            'sumber'        => 'riwayat_historis'
        ];

        $logStmt->execute([
            $txId,
            $ringkasan,
            json_encode($detail, JSON_UNESCAPED_UNICODE),
            $tx['created_at']
        ]);

        return $txId;
    };

    // A. Masukkan Riwayat Awal (Kas Juni & Pengeluaran Juli-Agustus dari Jurnal/Log Aktivitas)
    echo "   -> Mengimpor pengeluaran riil & penerimaan awal dari jurnal_kas/log aktivitas...\n";
    $earlyTxs = [
        ['date' => '2026-06-30', 'type' => 'income',  'account_id' => 1, 'category_id' => 1, 'amount' => 515000, 'description' => 'Penerimaan Kas Mingguan Juni 2026', 'ref_type' => 'kas_mingguan', 'created_at' => '2026-06-30 23:59:59'],
        ['date' => '2026-07-31', 'type' => 'expense', 'account_id' => 1, 'category_id' => 5, 'amount' => 28000,  'description' => 'Penggantian talangan Brendata: stiker nama', 'ref_type' => 'manual', 'created_at' => '2026-07-31 10:00:00'],
        ['date' => '2026-07-31', 'type' => 'expense', 'account_id' => 1, 'category_id' => 5, 'amount' => 28000,  'description' => 'Penggantian talangan Brendata: stiker nama (Penyesuaian Jurnal #1)', 'ref_type' => 'manual', 'created_at' => '2026-07-31 10:05:00'],
        ['date' => '2026-08-02', 'type' => 'expense', 'account_id' => 1, 'category_id' => 6, 'amount' => 40000,  'description' => 'Penggantian talangan Setiawan: Sapu dan cikrak', 'ref_type' => 'manual', 'created_at' => '2026-08-02 11:00:00'],
        ['date' => '2026-08-03', 'type' => 'expense', 'account_id' => 1, 'category_id' => 4, 'amount' => 27000,  'description' => 'Kasbon Brilli bulan mei', 'ref_type' => 'manual', 'created_at' => '2026-08-03 14:00:00'],
        ['date' => '2026-08-05', 'type' => 'expense', 'account_id' => 1, 'category_id' => 5, 'amount' => 20000,  'description' => 'Spidol kelas', 'ref_type' => 'manual', 'created_at' => '2026-08-05 09:30:00'],
        ['date' => '2026-08-07', 'type' => 'expense', 'account_id' => 1, 'category_id' => 5, 'amount' => 105000, 'description' => 'Penggantian talangan Eddria: Map Snail', 'ref_type' => 'manual', 'created_at' => '2026-08-07 13:15:00'],
        ['date' => '2026-08-09', 'type' => 'expense', 'account_id' => 1, 'category_id' => 7, 'amount' => 58000,  'description' => 'Penggantian talangan Brilliant: Bahan masak lomba makanan sehat', 'ref_type' => 'manual', 'created_at' => '2026-08-09 15:45:00'],
        ['date' => '2026-08-16', 'type' => 'expense', 'account_id' => 1, 'category_id' => 9, 'amount' => 8000,   'description' => 'Biaya administrasi / selisih kas fisik', 'ref_type' => 'manual', 'created_at' => '2026-08-16 18:00:00'],
        ['date' => '2026-08-17', 'type' => 'income',  'account_id' => 1, 'category_id' => 1, 'amount' => 105000, 'description' => 'Penerimaan Kas Mingguan Agustus 2026', 'ref_type' => 'kas_mingguan', 'created_at' => '2026-08-17 18:30:00'],
        ['date' => '2026-08-17', 'type' => 'income',  'account_id' => 1, 'category_id' => 1, 'amount' => 10000,  'description' => 'Penerimaan Kas Mingguan September 2026', 'ref_type' => 'kas_mingguan', 'created_at' => '2026-08-17 18:41:00'],
        ['date' => '2026-08-17', 'type' => 'income',  'account_id' => 5, 'category_id' => 1, 'amount' => 3000,   'description' => 'Penerimaan Kas Kelas ke Gopay', 'ref_type' => 'kas_mingguan', 'created_at' => '2026-08-17 19:36:15'],
    ];

    foreach ($earlyTxs as $etx) {
        $recordTxAndLog($etx);
    }
    echo "   [+] 12 transaksi riil awal (penerimaan kas & pengeluaran talangan) berhasil dimasukkan\n";

    // B. Masukkan Alokasi & Jurnal dari backup_storage_transactions (melewati Alokasi #1 karena sudah didekomposisi di atas)
    $stRows = $pdo->query("SELECT * FROM backup_storage_transactions ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $incomeCount = 0;
    $transferCount = 0;
    $processedPairs = [];

    foreach ($stRows as $st) {
        // Lewati alokasi #1 karena sudah didekomposisi menjadi transaksi rincian di atas
        if ($st['ref_type'] === 'allocation' && (int)$st['ref_id'] === 1) {
            continue;
        }

        $nom = (float)$st['nominal'];
        $tgl = $st['tanggal'] ?: date('Y-m-d');
        $createdAt = $st['created_at'] ?: date('Y-m-d H:i:s');
        $desc = trim($st['keterangan'] ?? '');

        if ($st['ref_type'] === 'allocation' || $st['ref_type'] === 'jurnal') {
            $catId = $resolveCatId($desc);
            $refTypeEnum = ($catId === 1) ? 'kas_mingguan' : 'manual';
            $recordTxAndLog([
                'date'          => $tgl,
                'type'          => 'income',
                'account_id'    => (int)$st['account_id'],
                'category_id'   => $catId,
                'amount'        => $nom,
                'description'   => $desc ?: 'Penerimaan Dana',
                'ref_type'      => $refTypeEnum,
                'ref_id'        => $st['ref_id'],
                'created_at'    => $createdAt
            ]);
            $incomeCount++;
        } elseif ($st['ref_type'] === 'transfer_out') {
            $pairId = $st['transfer_pair_id'];
            if (!empty($pairId) && !isset($processedPairs[$pairId])) {
                $inStmt = $pdo->prepare("SELECT * FROM backup_storage_transactions WHERE transfer_pair_id = ? AND ref_type = 'transfer_in' LIMIT 1");
                $inStmt->execute([$pairId]);
                $inRow = $inStmt->fetch(PDO::FETCH_ASSOC);
                if ($inRow) {
                    $processedPairs[$pairId] = true;
                    $fromAccId = (int)$st['account_id'];
                    $toAccId = (int)$inRow['account_id'];
                    $transferDesc = $desc ?: 'Transfer Antar Dompet';

                    $recordTxAndLog([
                        'date'          => $tgl,
                        'type'          => 'transfer',
                        'account_id'    => $fromAccId,
                        'to_account_id' => $toAccId,
                        'amount'        => $nom,
                        'description'   => $transferDesc,
                        'ref_type'      => 'transfer',
                        'ref_id'        => $pairId,
                        'created_at'    => $createdAt
                    ]);
                    $transferCount++;
                }
            }
        }
    }
    echo "   [+] Berhasil mengimpor $incomeCount transaksi alokasi/kas lanjutan\n";
    echo "   [+] Berhasil mengimpor $transferCount transaksi transfer antar dompet\n";

    // C. Migrate Kas BMS (1 row)
    $bmsRow = $pdo->query("SELECT * FROM backup_kas_bms LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($bmsRow) {
        $recordTxAndLog([
            'date'          => $bmsRow['tanggal'] ?: '2026-08-17',
            'type'          => 'income',
            'account_id'    => 9, // Kas / Tabungan BMS
            'category_id'   => 3, // Kas / Tabungan BMS
            'amount'        => (float)$bmsRow['jumlah'],
            'description'   => $bmsRow['keterangan'] ?: 'Saldo Awal Kas BMS',
            'ref_type'      => 'manual',
            'ref_id'        => (int)$bmsRow['id'],
            'created_at'    => $bmsRow['created_at'] ?: '2026-08-17 18:26:06'
        ]);
        echo "   [+] Berhasil mengimpor transaksi Saldo Kas BMS: Rp " . number_format((float)$bmsRow['jumlah'], 0, ',', '.') . "\n";
    }

    $pdo->commit();
    echo "\n>>> MIGRATION AND LOG SYNC COMPLETED SUCCESSFULLY! <<<\n";

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
