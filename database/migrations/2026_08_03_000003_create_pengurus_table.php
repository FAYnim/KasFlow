<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS pengurus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$hash = password_hash('admin123', PASSWORD_DEFAULT);
$check = $pdo->prepare("SELECT id FROM pengurus WHERE username = ?");
$check->execute(['admin']);

if ($check->fetch()) {
    $update = $pdo->prepare("UPDATE pengurus SET password = ?, nama = ? WHERE username = ?");
    $update->execute([$hash, 'Admin', 'admin']);
} else {
    $insert = $pdo->prepare("INSERT INTO pengurus (username, password, nama) VALUES (?, ?, ?)");
    $insert->execute(['admin', $hash, 'Admin']);
}

echo "migrated: pengurus\n";
