<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS jurnal_kas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('masuk','keluar') NOT NULL,
    nominal DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "migrated: jurnal_kas\n";
