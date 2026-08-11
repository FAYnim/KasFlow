<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS config (
    key_name VARCHAR(50) PRIMARY KEY,
    key_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$seeds = [
    'tarif_kas_mingguan' => '5000',
    'nama_kelas'         => 'RPL 1',
    'saldo_awal'         => '0',
];
$sel = $pdo->prepare('SELECT key_value FROM config WHERE key_name = ?');
$ins = $pdo->prepare('INSERT INTO config (key_name, key_value) VALUES (?, ?)');
foreach ($seeds as $k => $v) {
    $sel->execute([$k]);
    if ($sel->fetchColumn() === false) {
        $ins->execute([$k, $v]);
    }
}

echo "migrated: config\n";
