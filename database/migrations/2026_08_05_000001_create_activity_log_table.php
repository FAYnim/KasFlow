<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modul VARCHAR(50) NOT NULL,
    aksi VARCHAR(20) NOT NULL,
    entitas_id INT NULL,
    ringkasan VARCHAR(500) NOT NULL,
    admin_username VARCHAR(50) NULL,
    admin_nama VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_log_created (created_at),
    INDEX idx_activity_log_modul_aksi (modul, aksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "migrated: activity_log\n";