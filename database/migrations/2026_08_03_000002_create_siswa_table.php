<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    absen VARCHAR(20) UNIQUE,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "migrated: siswa\n";
