<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$action = $_GET['action'] ?? '';
$pdo = db();

try {
    switch ($action) {
        case 'get_summary': {
            $totalKas = (float)$pdo->query("SELECT COALESCE(SUM(total_bayar),0) FROM kas_mingguan")->fetchColumn();
            $sumSetor = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM kas_bms WHERE jenis='setor'")->fetchColumn();
            $sumTarik = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM kas_bms WHERE jenis='tarik'")->fetchColumn();
            $saldoBms = $sumSetor - $sumTarik;
            $totalKasbon = (float)$pdo->query("SELECT COALESCE(SUM(jumlah),0) FROM kasbon")->fetchColumn();
            echo json_encode([
                'total_kas_terkumpul' => $totalKas,
                'saldo_bms' => $saldoBms,
                'total_kasbon' => $totalKasbon,
            ]);
            break;
        }
        case 'get_kas': {
            $bulan = $_GET['bulan'] ?? date('F');
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $stmt = $pdo->prepare("
                SELECT s.id, s.absen, s.nama,
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
            $bulan = $_GET['bulan'] ?? '';
            $tahun = $_GET['tahun'] ?? '';
            $where = []; $args = [];
            $bulanIdx = $_GET['bulan'] ?? '';
            $tahun = $_GET['tahun'] ?? '';
            $where = []; $args = [];
            if ($bulanIdx !== '') {
                $bulanMap = ['Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12];
                if (isset($bulanMap[$bulanIdx])) { $where[] = 'MONTH(tanggal) = ?'; $args[] = $bulanMap[$bulanIdx]; }
            }
            if ($tahun !== '') { $where[] = 'YEAR(tanggal) = ?'; $args[] = (int)$tahun; }
            $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $pdo->prepare("SELECT id, tanggal, keterangan, jenis, nominal FROM jurnal_kas $sqlWhere ORDER BY tanggal DESC, id DESC");
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
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
        case 'get_kasbon': {
            $bulanMap = ['Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12];
            $bulanIdx = $_GET['bulan'] ?? array_search((int)date('n'), $bulanMap, true);
            $tahun    = (int)($_GET['tahun'] ?? date('Y'));
            $where = []; $args = [];
            if (isset($bulanMap[$bulanIdx])) {
                $where[] = 'MONTH(tanggal) = ?';
                $args[]  = $bulanMap[$bulanIdx];
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'bulan tidak valid']);
                break;
            }
            $where[] = 'YEAR(tanggal) = ?';
            $args[]  = $tahun;
            $sqlWhere = 'WHERE ' . implode(' AND ', $where);
            $stmt = $pdo->prepare("SELECT id, nama, tanggal, keterangan, jumlah, status, tanggal_lunas FROM kasbon $sqlWhere ORDER BY tanggal DESC, id DESC");
            $stmt->execute($args);
            $rows = array_map(function($r) { $r['jumlah'] = (float)$r['jumlah']; return $r; }, $stmt->fetchAll());
            echo json_encode($rows);
            break;
        }
        case 'get_bms': {
            $dari   = $_GET['dari'] ?? null;
            $sampai = $_GET['sampai'] ?? null;
            $where = [];
            $params = [];
            if ($dari)   { $where[] = 'tanggal >= ?'; $params[] = $dari; }
            if ($sampai) { $where[] = 'tanggal <= ?'; $params[] = $sampai; }
            $sql = 'SELECT id, tanggal, keterangan, jenis, jumlah FROM kas_bms'
                 . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                 . ' ORDER BY tanggal DESC, id DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $sumSetor = 0.0; $sumTarik = 0.0;
            foreach ($rows as $r) {
                if ($r['jenis'] === 'setor') $sumSetor += (float)$r['jumlah'];
                else                          $sumTarik += (float)$r['jumlah'];
            }
            echo json_encode([
                'rows'   => $rows,
                'totals' => [
                    'setor' => number_format($sumSetor, 2, '.', ''),
                    'tarik' => number_format($sumTarik, 2, '.', ''),
                    'saldo' => number_format($sumSetor - $sumTarik, 2, '.', ''),
                ],
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
