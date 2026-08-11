<?php
// Seed/reset dua akun bendahara.
// Jalankan manual kalau perlu reset password: `php database/seeds/admin.php`
require_once __DIR__ . '/../../config/database.php';

$accounts = [
    ['ammar', 'bendahara2', 'Ammar'],
    ['faris', 'bendahara1', 'Faris'],
];

$pdo = db();
foreach ($accounts as [$username, $password, $nama]) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $check = $pdo->prepare("SELECT id FROM pengurus WHERE username = ?");
    $check->execute([$username]);

    if ($check->fetch()) {
        $update = $pdo->prepare("UPDATE pengurus SET password = ?, nama = ? WHERE username = ?");
        $update->execute([$hash, $nama, $username]);
    } else {
        $insert = $pdo->prepare("INSERT INTO pengurus (username, password, nama) VALUES (?, ?, ?)");
        $insert->execute([$username, $hash, $nama]);
    }

    echo "Seeded: {$username}\n";
}
