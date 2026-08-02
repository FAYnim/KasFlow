<?php
require_once __DIR__ . '/bootstrap.php';
$hash = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = db()->prepare("UPDATE pengurus SET password = ? WHERE username = 'admin'");
$stmt->execute([$hash]);
echo "Seeded admin hash: $hash\n";
