<?php
require_once __DIR__ . '/../../config/database.php';

$pdo = db();

try {
    // Check if column detail already exists
    $stmt = $pdo->query("SHOW COLUMNS FROM activity_log LIKE 'detail'");
    $exists = $stmt->fetch();
    
    if (!$exists) {
        $pdo->exec("ALTER TABLE activity_log ADD COLUMN detail JSON NULL AFTER ringkasan");
        echo "migrated: add_detail_to_activity_log\n";
    } else {
        echo "already migrated: add_detail_to_activity_log\n";
    }
} catch (Throwable $e) {
    // MySQL older version fallback if JSON type fails
    try {
        $pdo->exec("ALTER TABLE activity_log ADD COLUMN detail LONGTEXT NULL AFTER ringkasan");
        echo "migrated (LONGTEXT fallback): add_detail_to_activity_log\n";
    } catch (Throwable $e2) {
        echo "error migrating add_detail_to_activity_log: " . $e2->getMessage() . "\n";
    }
}
