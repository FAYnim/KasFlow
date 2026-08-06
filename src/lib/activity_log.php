<?php
function log_activity(PDO $pdo, string $modul, string $aksi, ?int $entitasId, string $ringkasan): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (modul, aksi, entitas_id, ringkasan, admin_username, admin_nama) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $modul,
            $aksi,
            $entitasId,
            $ringkasan,
            $_SESSION['admin_username'] ?? null,
            $_SESSION['admin_nama'] ?? null,
        ]);
    } catch (Throwable $e) {
        // best-effort: never break the main CRUD operation
    }
}