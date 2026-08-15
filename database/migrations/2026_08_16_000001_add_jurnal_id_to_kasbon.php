<?php
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM kasbon LIKE 'jurnal_id'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE kasbon ADD COLUMN jurnal_id INT NULL DEFAULT NULL AFTER tanggal_lunas");
        $pdo->exec("ALTER TABLE kasbon ADD CONSTRAINT fk_kasbon_jurnal FOREIGN KEY (jurnal_id) REFERENCES jurnal_kas(id) ON DELETE SET NULL");
        echo "Migration 2026_08_16_000001_add_jurnal_id_to_kasbon applied.\n";
    } else {
        echo "Migration 2026_08_16_000001_add_jurnal_id_to_kasbon skipped (already exists).\n";
    }
} catch (Throwable $e) {
    echo "Migration 2026_08_16_000001_add_jurnal_id_to_kasbon error: " . $e->getMessage() . "\n";
}
