<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS storage_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    type ENUM('cash','ewallet','bank') NOT NULL,
    icon VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS storage_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jenis ENUM('masuk','keluar') NOT NULL,
    nominal DECIMAL(12,2) NOT NULL,
    ref_type ENUM('allocation','transfer_in','transfer_out','manual') NOT NULL,
    ref_id INT NULL,
    transfer_pair_id INT NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_st_account (account_id),
    INDEX idx_st_tanggal (tanggal),
    INDEX idx_st_ref (ref_type, ref_id),
    FOREIGN KEY (account_id) REFERENCES storage_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS storage_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_type ENUM('bms_setor','bms_tarik','kas_mingguan','manual') NOT NULL,
    ref_id INT NULL,
    tanggal DATE NOT NULL,
    total_nominal DECIMAL(12,2) NOT NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sa_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Default accounts (idempotent via name+type uniqueness check using INSERT IGNORE-friendly name-based seeding).
$defaults = [
    ['Cash',     'cash',    'fa-solid fa-wallet',           1],
    ['E-Wallet', 'ewallet', 'fa-solid fa-mobile-screen',    2],
    ['Bank',     'bank',    'fa-solid fa-building-columns', 3],
];
$sel = $pdo->prepare("SELECT id FROM storage_accounts WHERE name = ? AND type = ?");
$ins = $pdo->prepare("INSERT INTO storage_accounts (name, type, icon, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
foreach ($defaults as [$name, $type, $icon, $sort]) {
    $sel->execute([$name, $type]);
    if (!$sel->fetchColumn()) {
        $ins->execute([$name, $type, $icon, $sort]);
    }
}

echo "migrated: storage tables + default accounts\n";
