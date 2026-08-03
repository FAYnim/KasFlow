<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach (['kas_mingguan','kasbon','jurnal_kas','kas_bms','pengurus','siswa','config'] as $t) {
    $pdo->exec("TRUNCATE TABLE $t");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
echo "All tables truncated. Structure preserved.\n";
