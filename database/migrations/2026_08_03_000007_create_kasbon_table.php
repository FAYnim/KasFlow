<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS kasbon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    status ENUM('belum_lunas','lunas') DEFAULT 'belum_lunas',
    tanggal_lunas DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kasbon_tanggal (tanggal),
    INDEX idx_kasbon_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "migrated: kasbon\n";
