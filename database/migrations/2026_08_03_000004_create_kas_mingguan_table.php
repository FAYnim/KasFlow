<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS kas_mingguan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    bulan VARCHAR(20) NOT NULL,
    tahun INT NOT NULL,
    minggu_1 BOOLEAN DEFAULT FALSE,
    minggu_2 BOOLEAN DEFAULT FALSE,
    minggu_3 BOOLEAN DEFAULT FALSE,
    minggu_4 BOOLEAN DEFAULT FALSE,
    minggu_5 BOOLEAN DEFAULT FALSE,
    total_bayar DECIMAL(12,2) DEFAULT 0,
    UNIQUE KEY uniq_siswa_bulan (siswa_id, bulan, tahun),
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "migrated: kas_mingguan\n";
