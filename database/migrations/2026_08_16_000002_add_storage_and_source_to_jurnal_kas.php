<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();

try {
    // 1. Tambahkan storage_account_id ke jurnal_kas
    $colCheck = $pdo->query("SHOW COLUMNS FROM jurnal_kas LIKE 'storage_account_id'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE jurnal_kas ADD COLUMN storage_account_id INT NULL DEFAULT NULL AFTER nominal");
        $pdo->exec("ALTER TABLE jurnal_kas ADD CONSTRAINT fk_jurnal_kas_storage FOREIGN KEY (storage_account_id) REFERENCES storage_accounts(id) ON DELETE SET NULL");
        echo "Added storage_account_id to jurnal_kas.\n";
    } else {
        echo "Column storage_account_id already exists in jurnal_kas.\n";
    }

    // 2. Tambahkan kolom source & source_id ke jurnal_kas
    $srcCheck = $pdo->query("SHOW COLUMNS FROM jurnal_kas LIKE 'source'")->fetch();
    if (!$srcCheck) {
        $pdo->exec("ALTER TABLE jurnal_kas ADD COLUMN source ENUM('manual','kas_mingguan','kasbon') NOT NULL DEFAULT 'manual' AFTER storage_account_id");
        $pdo->exec("ALTER TABLE jurnal_kas ADD COLUMN source_id INT NULL DEFAULT NULL AFTER source");
        echo "Added source & source_id to jurnal_kas.\n";
    } else {
        echo "Column source already exists in jurnal_kas.\n";
    }

    // 3. Perluas ref_type pada storage_transactions agar mendukung 'jurnal'
    //    Kita harus DROP & RE-ADD constraint CHECK atau modifikasi ENUM.
    //    Gunakan ALTER COLUMN MODIFY untuk MySQL ENUM.
    $stCheck = $pdo->query("SHOW COLUMNS FROM storage_transactions LIKE 'ref_type'")->fetch();
    if ($stCheck) {
        $current = $stCheck['Type'] ?? '';
        if (strpos($current, "'jurnal'") === false) {
            $pdo->exec("ALTER TABLE storage_transactions MODIFY COLUMN ref_type ENUM('allocation','transfer_in','transfer_out','manual','jurnal') NOT NULL");
            echo "Extended storage_transactions.ref_type ENUM to include 'jurnal'.\n";
        } else {
            echo "storage_transactions.ref_type already includes 'jurnal'.\n";
        }
    }

    echo "\nMigration 2026_08_16_000002 completed successfully.\n";
} catch (Throwable $e) {
    echo "Migration 2026_08_16_000002 error: " . $e->getMessage() . "\n";
}
