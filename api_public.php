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
        case 'get_kas': {
            $bulan = $_GET['bulan'] ?? date('F');
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $stmt = $pdo->prepare("
                SELECT s.id, s.nis, s.nama,
                       COALESCE(k.minggu_1,0) m1, COALESCE(k.minggu_2,0) m2,
                       COALESCE(k.minggu_3,0) m3, COALESCE(k.minggu_4,0) m4,
                       COALESCE(k.minggu_5,0) m5, COALESCE(k.total_bayar,0) total_bayar
                FROM siswa s
                LEFT JOIN kas_mingguan k ON k.siswa_id = s.id AND k.bulan = ? AND k.tahun = ?
                ORDER BY s.nama ASC
            ");
            $stmt->execute([$bulan, $tahun]);
            echo json_encode($stmt->fetchAll());
            break;
        }
        case 'get_jurnal': {
            $rows = $pdo->query("SELECT id, tanggal, keterangan, jenis, nominal FROM jurnal_kas ORDER BY tanggal DESC, id DESC")->fetchAll();
            $saldo = 0;
            $line = [];
            $allAsc = $pdo->query("SELECT tanggal, jenis, nominal FROM jurnal_kas ORDER BY tanggal ASC, id ASC")->fetchAll();
            foreach ($allAsc as $r) {
                $saldo += $r['jenis'] === 'masuk' ? (float)$r['nominal'] : -(float)$r['nominal'];
                $line[] = ['tanggal' => $r['tanggal'], 'saldo' => $saldo];
            }
            $totMasuk = array_sum(array_map(fn($r) => $r['jenis']==='masuk' ? (float)$r['nominal'] : 0, $rows));
            $totKeluar = array_sum(array_map(fn($r) => $r['jenis']==='keluar' ? (float)$r['nominal'] : 0, $rows));
            echo json_encode([
                'transaksi' => $rows,
                'line_chart' => $line,
                'donut' => ['masuk' => $totMasuk, 'keluar' => $totKeluar],
            ]);
            break;
        }
        case 'get_piutang': {
            $stmt = $pdo->query("
                SELECT p.id, p.tanggal, p.keterangan, p.jumlah, p.status, s.nama AS siswa_nama, s.nis
                FROM piutang_denda p JOIN siswa s ON s.id = p.siswa_id
                ORDER BY p.status ASC, p.tanggal DESC
            ");
            echo json_encode($stmt->fetchAll());
            break;
        }
        case 'get_bank': {
            $rows = $pdo->query("SELECT id, tanggal, keterangan, jenis, jumlah FROM mutasi_bank ORDER BY tanggal DESC, id DESC")->fetchAll();
            echo json_encode($rows);
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
