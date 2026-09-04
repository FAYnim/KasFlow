<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();

// 1. Create accounts table
$pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('cash', 'ewallet', 'bank', 'other') NOT NULL DEFAULT 'other',
    icon VARCHAR(50) DEFAULT 'fa-solid fa-wallet',
    initial_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 2. Create categories table
$pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense', 'both') NOT NULL DEFAULT 'both',
    icon VARCHAR(50) DEFAULT 'fa-solid fa-tag',
    color VARCHAR(20) DEFAULT '#3b82f6',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 3. Create transactions table
$pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    type ENUM('income', 'expense', 'transfer') NOT NULL,
    account_id INT NOT NULL,
    to_account_id INT NULL DEFAULT NULL,
    category_id INT NULL DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT NULL,
    ref_type ENUM('manual', 'kas_mingguan', 'transfer') NOT NULL DEFAULT 'manual',
    ref_id INT NULL DEFAULT NULL,
    created_by VARCHAR(50) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_trans_date (date),
    INDEX idx_trans_type (type),
    INDEX idx_trans_account (account_id),
    INDEX idx_trans_to_account (to_account_id),
    INDEX idx_trans_category (category_id),
    CONSTRAINT fk_trans_acc FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_trans_to_acc FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    CONSTRAINT fk_trans_cat FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 4. Create kas_mingguan_queue table
$pdo->exec("CREATE TABLE IF NOT EXISTS kas_mingguan_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulan VARCHAR(20) NOT NULL,
    tahun INT NOT NULL,
    nominal DECIMAL(12,2) NOT NULL,
    keterangan VARCHAR(255) NOT NULL,
    detail JSON NULL,
    status ENUM('pending', 'recorded') NOT NULL DEFAULT 'pending',
    transaction_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kmq_status (status),
    CONSTRAINT fk_kmq_trans FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 5. Seed default accounts if empty
$accountCount = (int)$pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
if ($accountCount === 0) {
    $defaultAccounts = [
        ['Kas Tunai (Fisik)', 'cash',    'fa-solid fa-wallet',                0.00, 1],
        ['DANA',              'ewallet', 'fa-solid fa-mobile-screen-button',  0.00, 2],
        ['SeaBank',           'bank',    'fa-solid fa-building-columns',      0.00, 3],
        ['Bank Mandiri',      'bank',    'fa-solid fa-landmark',              0.00, 4],
    ];
    $insAcc = $pdo->prepare("INSERT INTO accounts (name, type, icon, initial_balance, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
    foreach ($defaultAccounts as $acc) {
        $insAcc->execute($acc);
    }
}

// 6. Seed default categories if empty
$categoryCount = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
if ($categoryCount === 0) {
    $defaultCategories = [
        ['Uang Kas Mingguan', 'income',  'fa-solid fa-coins',                 '#10b981'],
        ['Donasi / Sumbangan','income',  'fa-solid fa-hand-holding-heart',    '#059669'],
        ['Hadiah Lomba',      'income',  'fa-solid fa-trophy',                '#14b8a6'],
        ['Buku / LKS',        'expense', 'fa-solid fa-book',                  '#f59e0b'],
        ['Spidol & ATK',      'expense', 'fa-solid fa-pen-ruler',             '#ef4444'],
        ['Konsumsi & Acara',  'expense', 'fa-solid fa-utensils',              '#ec4899'],
        ['Fotokopi & Print',  'expense', 'fa-solid fa-print',                 '#8b5cf6'],
        ['Dana Sosial',       'expense', 'fa-solid fa-users',                 '#6366f1'],
        ['Lain-lain',         'both',    'fa-solid fa-shapes',                '#64748b'],
    ];
    $insCat = $pdo->prepare("INSERT INTO categories (name, type, icon, color, is_active) VALUES (?, ?, ?, ?, 1)");
    foreach ($defaultCategories as $cat) {
        $insCat->execute($cat);
    }
}

echo "migrated: centralized cashflow tables, accounts, categories, transactions, kas_mingguan_queue\n";
