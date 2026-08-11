<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS pengurus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Default bendahara accounts. To rotate: run `php database/seeds/admin.php`.
$defaults = [
    ['ammar', 'bendahara2', 'Ammar'],
    ['faris', 'bendahara1', 'Faris'],
];
foreach ($defaults as [$u, $p, $n]) {
    $hash = password_hash($p, PASSWORD_DEFAULT);
    $check = $pdo->prepare("SELECT id FROM pengurus WHERE username = ?");
    $check->execute([$u]);
    if ($check->fetch()) {
        $upd = $pdo->prepare("UPDATE pengurus SET password = ?, nama = ? WHERE username = ?");
        $upd->execute([$hash, $n, $u]);
    } else {
        $ins = $pdo->prepare("INSERT INTO pengurus (username, password, nama) VALUES (?, ?, ?)");
        $ins->execute([$u, $hash, $n]);
    }
}

echo "migrated: pengurus\n";
