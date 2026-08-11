<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();

$defaults = [
    ['Cash',     'cash',    'fa-solid fa-wallet',           1],
    ['E-Wallet', 'ewallet', 'fa-solid fa-mobile-screen',    2],
    ['Bank',     'bank',    'fa-solid fa-building-columns', 3],
];
$sel = $pdo->prepare("SELECT id FROM storage_accounts WHERE name = ? AND type = ?");
$ins = $pdo->prepare("INSERT INTO storage_accounts (name, type, icon, sort_order, is_active) VALUES (?, ?, ?, ?, 1)");
$added = 0;
foreach ($defaults as [$name, $type, $icon, $sort]) {
    $sel->execute([$name, $type]);
    if (!$sel->fetchColumn()) {
        $ins->execute([$name, $type, $icon, $sort]);
        $added++;
    }
}
echo "seeded storage_accounts: $added new (others already exist)\n";
