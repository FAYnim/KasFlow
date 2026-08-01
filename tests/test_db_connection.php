<?php
require_once __DIR__ . '/bootstrap.php';
try {
    $pdo = db();
    $row = $pdo->query('SELECT key_value FROM config WHERE key_name = "tarif_kas_mingguan"')->fetch();
    if ($row['key_value'] !== '5000') {
        fwrite(STDERR, "Expected 5000, got " . $row['key_value'] . "\n");
        exit(1);
    }
    echo "PASS: db connection + seed OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "FAIL: " . $e->getMessage() . "\n");
    exit(1);
}
