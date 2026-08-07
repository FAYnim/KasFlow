<?php
function log_activity(PDO $pdo, string $modul, string $aksi, ?int $entitasId, string $ringkasan, mixed $detail = null): void {
    try {
        $detailJson = null;
        if ($detail !== null) {
            $detailJson = is_string($detail) ? $detail : json_encode($detail, JSON_UNESCAPED_UNICODE);
        }
        $stmt = $pdo->prepare("INSERT INTO activity_log (modul, aksi, entitas_id, ringkasan, detail, admin_username, admin_nama) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            $modul,
            $aksi,
            $entitasId,
            $ringkasan,
            $detailJson,
            $_SESSION['admin_username'] ?? null,
            $_SESSION['admin_nama'] ?? null,
        ]);
    } catch (Throwable $e) {
        // best-effort: never break the main CRUD operation
    }
}