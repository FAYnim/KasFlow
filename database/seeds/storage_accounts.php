<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();

$defaults = [
    // [name,           type,            parent_type, icon,                               sort_order]
    ['Cash',          'cash',          'cash',    'fa-solid fa-wallet',                1],
    ['DANA',          'ewallet_dana',  'ewallet', 'fa-solid fa-mobile-screen',         2],
    ['Gopay',         'ewallet_gopay', 'ewallet', 'fa-solid fa-mobile-screen-button',  3],
    ['E-Wallet Lain', 'ewallet',       'ewallet', 'fa-solid fa-credit-card',           4],
    ['SeaBank',       'bank_seabank',  'bank',    'fa-solid fa-building-columns',      5],
    ['Bank Mandiri',  'bank_mandiri',  'bank',    'fa-solid fa-building-columns',      6],
    ['Bank Lain',     'bank',          'bank',    'fa-solid fa-landmark',              7],
];
$sel = $pdo->prepare("SELECT id FROM storage_accounts WHERE name = ?");
$ins = $pdo->prepare("INSERT INTO storage_accounts (name, type, parent_type, icon, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)");
$added = 0;
foreach ($defaults as [$name, $type, $parentType, $icon, $sort]) {
    $sel->execute([$name]);
    if ($sel->fetchColumn() === false) {
        $ins->execute([$name, $type, $parentType, $icon, $sort]);
        $added++;
    }
}
echo "seeded storage_accounts: $added new (others already exist)\n";
