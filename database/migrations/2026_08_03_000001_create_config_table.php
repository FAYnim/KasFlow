<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS config (
    key_name VARCHAR(50) PRIMARY KEY,
    key_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "migrated: config\n";
