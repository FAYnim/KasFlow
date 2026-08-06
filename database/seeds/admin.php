<?php
require_once __DIR__ . '/../../config/database.php';
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$pdo = db();

$check = $pdo->prepare("SELECT id FROM pengurus WHERE username = ?");
$check->execute(['admin']);

if ($check->fetch()) {
    $update = $pdo->prepare("UPDATE pengurus SET password = ?, nama = ? WHERE username = ?");
    $update->execute([$hash, 'Admin', 'admin']);
} else {
    $insert = $pdo->prepare("INSERT INTO pengurus (username, password, nama) VALUES (?, ?, ?)");
    $insert->execute(['admin', $hash, 'Admin']);
}

echo "Seeded admin account: admin\n";
