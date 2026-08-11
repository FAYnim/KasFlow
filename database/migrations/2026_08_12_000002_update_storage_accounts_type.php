<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();

// 1. Ubah kolom 'type' dari ENUM menjadi VARCHAR(50) agar bisa menerima tipe custom
//    Cek dulu apakah kolom masih ENUM - kalau sudah VARCHAR skip
$colInfo = $pdo->query("
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'storage_accounts'
      AND COLUMN_NAME  = 'type'
")->fetchColumn();

if ($colInfo && stripos($colInfo, 'enum') !== false) {
    $pdo->exec("ALTER TABLE storage_accounts MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'other'");
    echo "migrated: storage_accounts.type ENUM to VARCHAR(50)\n";
} else {
    echo "already migrated: storage_accounts.type is already VARCHAR\n";
}

// 2. Tambahkan kolom 'parent_type' untuk pengelompokan (cash/ewallet/bank/other)
//    digunakan untuk display grouping; skip jika sudah ada
$hasParent = $pdo->query("
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'storage_accounts'
      AND COLUMN_NAME  = 'parent_type'
")->fetchColumn();

if (!$hasParent) {
    $pdo->exec("ALTER TABLE storage_accounts ADD COLUMN parent_type VARCHAR(20) NOT NULL DEFAULT 'other' AFTER type");
    // Set parent_type berdasarkan type lama
    $pdo->exec("UPDATE storage_accounts SET parent_type = 'cash'    WHERE type = 'cash'");
    $pdo->exec("UPDATE storage_accounts SET parent_type = 'ewallet' WHERE type = 'ewallet'");
    $pdo->exec("UPDATE storage_accounts SET parent_type = 'bank'    WHERE type = 'bank'");
    echo "migrated: added storage_accounts.parent_type column\n";
} else {
    echo "already migrated: storage_accounts.parent_type column exists\n";
}

// 3. Seed akun-akun populer (idempotent: skip kalau sudah ada nama yg sama)
// [name, type, parent_type, icon, sort_order]
$extras = [
    ['Cash',          'cash',          'cash',    'fa-solid fa-wallet',                1],
    ['DANA',          'ewallet_dana',  'ewallet', 'fa-solid fa-mobile-screen',         2],
    ['Gopay',         'ewallet_gopay', 'ewallet', 'fa-solid fa-mobile-screen-button',  3],
    ['E-Wallet Lain', 'ewallet',       'ewallet', 'fa-solid fa-credit-card',           4],
    ['SeaBank',       'bank_seabank',  'bank',    'fa-solid fa-building-columns',      5],
    ['Bank Mandiri',  'bank_mandiri',  'bank',    'fa-solid fa-building-columns',      6],
    ['Bank Lain',     'bank',          'bank',    'fa-solid fa-landmark',              7],
];

$sel = $pdo->prepare("SELECT id FROM storage_accounts WHERE name = ?");
$ins = $pdo->prepare("INSERT INTO storage_accounts (name, type, parent_type, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
$added = 0;
foreach ($extras as [$name, $type, $parentType, $icon, $sort]) {
    $sel->execute([$name]);
    if ($sel->fetchColumn() === false) {
        $ins->execute([$name, $type, $parentType, $icon, $sort]);
        $added++;
    }
}

// 4. Nonaktifkan akun generik lama (E-Wallet & Bank tanpa nama spesifik) kalau ada,
//    dan set parent_type untuk akun lama yang belum punya
$pdo->exec("
    UPDATE storage_accounts
    SET parent_type = CASE
        WHEN type = 'cash'    THEN 'cash'
        WHEN type = 'ewallet' THEN 'ewallet'
        WHEN type = 'bank'    THEN 'bank'
        WHEN type LIKE 'ewallet%' THEN 'ewallet'
        WHEN type LIKE 'bank%'    THEN 'bank'
        ELSE 'other'
    END
    WHERE parent_type = 'other'
");

echo "seeded: $added new storage accounts, parent_type patched\n";
