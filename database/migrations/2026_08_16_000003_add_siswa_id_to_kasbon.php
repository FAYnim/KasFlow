<?php
/**
 * Migration: 2026_08_16_000003_add_siswa_id_to_kasbon
 * Tujuan: Menghubungkan tabel kasbon ke master data siswa via siswa_id FK.
 * - Tambah kolom siswa_id INT NULL setelah kolom id.
 * - Tambah FK fk_kasbon_siswa → siswa(id) ON DELETE SET NULL.
 * - Ubah kolom nama menjadi NULLABLE untuk backward-compat data lama.
 * - Auto-patch: Hubungkan data lama yang nama-nya cocok persis dengan siswa.nama.
 */
require_once __DIR__ . '/../../config/database.php';
$pdo = db();
try {
    // 1. Tambah kolom siswa_id jika belum ada
    $colCheck = $pdo->query("SHOW COLUMNS FROM kasbon LIKE 'siswa_id'")->fetch();
    if (!$colCheck) {
        $pdo->exec("ALTER TABLE kasbon ADD COLUMN siswa_id INT NULL DEFAULT NULL AFTER id");
        echo "Migration 2026_08_16_000003: kolom siswa_id ditambahkan.\n";
    } else {
        echo "Migration 2026_08_16_000003: kolom siswa_id sudah ada, dilewati.\n";
    }

    // 2. Ubah kolom nama agar bisa NULL (backward-compat data lama)
    $pdo->exec("ALTER TABLE kasbon MODIFY COLUMN nama VARCHAR(100) NULL DEFAULT NULL");
    echo "Migration 2026_08_16_000003: kolom nama diubah jadi NULLABLE.\n";

    // 3. Tambah FK jika belum ada
    $fkCheck = $pdo->query("
        SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'kasbon'
          AND COLUMN_NAME = 'siswa_id'
          AND REFERENCED_TABLE_NAME = 'siswa'
    ")->fetch();
    if (!$fkCheck) {
        $pdo->exec("ALTER TABLE kasbon ADD CONSTRAINT fk_kasbon_siswa FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE SET NULL");
        echo "Migration 2026_08_16_000003: FK fk_kasbon_siswa ditambahkan.\n";
    } else {
        echo "Migration 2026_08_16_000003: FK fk_kasbon_siswa sudah ada, dilewati.\n";
    }

    // 4. Auto-patch: hubungkan baris kasbon lama berdasarkan kecocokan nama
    $patched = $pdo->exec("
        UPDATE kasbon k
        INNER JOIN siswa s ON s.nama = k.nama
        SET k.siswa_id = s.id
        WHERE k.siswa_id IS NULL
    ");
    if ($patched > 0) {
        echo "Migration 2026_08_16_000003: $patched baris kasbon lama berhasil dihubungkan ke siswa.\n";
    } else {
        echo "Migration 2026_08_16_000003: tidak ada baris kasbon lama yang perlu di-patch.\n";
    }

    echo "Migration 2026_08_16_000003_add_siswa_id_to_kasbon applied.\n";
} catch (Throwable $e) {
    echo "Migration 2026_08_16_000003_add_siswa_id_to_kasbon error: " . $e->getMessage() . "\n";
}
