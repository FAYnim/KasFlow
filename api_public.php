<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config/database.php';

$action = $_GET['action'] ?? '';
$pdo = db();

try {
    switch ($action) {
        case 'get_summary': {
            $totalKas = (float)$pdo->query("SELECT COALESCE(SUM(total_bayar),0) FROM kas_mingguan")->fetchColumn();
            $masuk    = (float)$pdo->query("SELECT COALESCE(SUM(nominal),0) FROM jurnal_kas WHERE jenis='masuk'")->fetchColumn();
            $keluar   = (float)$pdo->query("SELECT COALESCE(SUM(nominal),0) FROM jurnal_kas WHERE jenis='keluar'")->fetchColumn();
            $setor    = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM mutasi_bank WHERE jenis='setor'")->fetchColumn();
            $tarik    = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM mutasi_bank WHERE jenis='tarik'")->fetchColumn();
            $dendaUnpaid = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM piutang_denda WHERE status='belum_dibayar'")->fetchColumn();
            $cashOnHand = $masuk - $keluar - $setor + $tarik;
            $cashInBank = $setor - $tarik;
            echo json_encode([
                'total_kas_terkumpul' => $totalKas,
                'cash_on_hand' => $cashOnHand,
                'cash_in_bank' => $cashInBank,
                'total_denda_unpaid' => $dendaUnpaid,
            ]);
            break;
        }
        default:
            http_response_code(400);
            echo json_encode(['error' => 'unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
