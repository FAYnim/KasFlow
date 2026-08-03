<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS kas_bms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('setor','tarik') NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kas_bms_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "migrated: kas_bms\n";
