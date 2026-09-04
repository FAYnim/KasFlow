<?php
require_once __DIR__ . '/../config/database.php';
$pdo = db();

$expectedTables = ['accounts', 'categories', 'transactions', 'kas_mingguan_queue'];
$existing = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($expectedTables as $t) {
    if (!in_array($t, $existing)) {
        echo "FAIL: Table $t missing\n";
        exit(1);
    }
}

$accCount = $pdo->query("SELECT COUNT(*) FROM accounts")->fetchColumn();
$catCount = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

if ($accCount < 4 || $catCount < 9) {
    echo "FAIL: Seeds missing (acc=$accCount, cat=$catCount)\n";
    exit(1);
}

echo "OK: All tables and seeds verified (accounts=$accCount, categories=$catCount)\n";
