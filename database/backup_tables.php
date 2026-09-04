<?php
require_once __DIR__ . '/../config/database.php';
$pdo = db();

echo "=== Backup Database Tables ===\n";

$tablesToBackup = [
    'jurnal_kas',
    'storage_allocations',
    'storage_transactions',
    'storage_accounts',
    'kas_bms',
    'kasbon',
    'activity_log'
];

$existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tablesToBackup as $table) {
    if (!in_array($table, $existingTables)) {
        echo "[-] Table '$table' does not exist in database, skipped.\n";
        continue;
    }
    
    $backupTable = "backup_" . $table . "_" . date('Ymd_His');
    // Also create a static alias backup table without timestamp for easy reference
    $staticBackupTable = "backup_" . $table;

    // Drop and copy to static backup
    $pdo->exec("DROP TABLE IF EXISTS `$staticBackupTable`");
    $pdo->exec("CREATE TABLE `$staticBackupTable` LIKE `$table`");
    $pdo->exec("INSERT INTO `$staticBackupTable` SELECT * FROM `$table`");

    // Copy to timestamped backup
    $pdo->exec("CREATE TABLE IF NOT EXISTS `$backupTable` LIKE `$table`");
    $pdo->exec("INSERT INTO `$backupTable` SELECT * FROM `$table`");

    $count = $pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo "[+] Backed up '$table' ($count rows) -> '$staticBackupTable' & '$backupTable'\n";
}

echo "\nBackup completed successfully!\n";
