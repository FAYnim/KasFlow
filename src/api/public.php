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
                ORDER BY CAST(s.absen AS UNSIGNED) ASC, s.nama ASC
            ");
            $stmt->execute([$bulan, $tahun]);
            $rows = $stmt->fetchAll();
            $tarif = (int)$pdo->query("SELECT key_value FROM config WHERE key_name='tarif_kas_mingguan'")->fetchColumn();
            echo json_encode(['tarif' => $tarif, 'rows' => $rows]);
            break;
        }
        case 'get_jurnal': {
            $bulanIdx = $_GET['bulan'] ?? '';
            $tahun    = $_GET['tahun'] ?? '';
            $page     = max(1, (int)($_GET['page'] ?? 1));
            $limit    = max(5, min(100, (int)($_GET['limit'] ?? 15)));
            $offset   = ($page - 1) * $limit;
            $where = []; $args = [];
            if ($bulanIdx !== '') {
                $bulanMap = ['Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12];
                if (isset($bulanMap[$bulanIdx])) { $where[] = 'MONTH(tanggal) = ?'; $args[] = $bulanMap[$bulanIdx]; }
            }
            if ($tahun !== '') { $where[] = 'YEAR(tanggal) = ?'; $args[] = (int)$tahun; }
            $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            // Total records for pagination meta
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM jurnal_kas $sqlWhere");
            $stmtCount->execute($args);
            $totalRecords = (int)$stmtCount->fetchColumn();
            $totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;
            // Paginated rows
            $stmt = $pdo->prepare("SELECT id, tanggal, keterangan, jenis, nominal FROM jurnal_kas $sqlWhere ORDER BY tanggal DESC, id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
            // Line chart & donut use full (unpaged) dataset
            $saldo = 0;
            $line = [];
            $allAsc = $pdo->query("SELECT tanggal, jenis, nominal FROM jurnal_kas ORDER BY tanggal ASC, id ASC")->fetchAll();
            foreach ($allAsc as $r) {
                $saldo += $r['jenis'] === 'masuk' ? (float)$r['nominal'] : -(float)$r['nominal'];
                $line[] = ['tanggal' => $r['tanggal'], 'saldo' => $saldo];
            }
            // Donut totals based on current filter (all pages)
            $stmtAll = $pdo->prepare("SELECT jenis, SUM(nominal) AS total FROM jurnal_kas $sqlWhere GROUP BY jenis");
            $stmtAll->execute($args);
            $totMasuk = 0; $totKeluar = 0;
            foreach ($stmtAll->fetchAll() as $r) {
                if ($r['jenis'] === 'masuk') $totMasuk = (float)$r['total'];
                else $totKeluar = (float)$r['total'];
            }
            echo json_encode([
                'transaksi'  => $rows,
                'pagination' => [
                    'page'          => $page,
                    'limit'         => $limit,
                    'total_records' => $totalRecords,
                    'total_pages'   => $totalPages,
                ],
                'line_chart' => $line,
                'donut'      => ['masuk' => $totMasuk, 'keluar' => $totKeluar],
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
        case 'get_storage_breakdown': {
            $rows = $pdo->query("
                SELECT a.id, a.name, a.type, a.icon,
                       COALESCE(SUM(CASE WHEN t.jenis='masuk' THEN t.nominal ELSE -t.nominal END), 0) AS saldo
                FROM storage_accounts a
                LEFT JOIN storage_transactions t ON t.account_id = a.id
                WHERE a.is_active = 1
                GROUP BY a.id
                ORDER BY a.sort_order, a.id
            ")->fetchAll(PDO::FETCH_ASSOC);
            $total = 0.0;
            foreach ($rows as &$r) {
                $r['saldo'] = (float)$r['saldo'];
                $total += $r['saldo'];
            }
            unset($r);
            $recentAllocs = $pdo->query("
                SELECT a.id, a.tanggal, a.ref_type, a.total_nominal, a.keterangan,
                       GROUP_CONCAT(CONCAT(sa.name, ':', t.nominal) SEPARATOR '|') AS line_info
                FROM storage_allocations a
                LEFT JOIN storage_transactions t ON t.ref_type='allocation' AND t.ref_id = a.id
                LEFT JOIN storage_accounts sa ON sa.id = t.account_id
                GROUP BY a.id
                ORDER BY a.tanggal DESC, a.id DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            $recentTransfers = $pdo->query("
                SELECT t.id, t.tanggal, t.nominal, t.keterangan, t.transfer_pair_id,
                       fa.name AS from_name, ta.name AS to_name
                FROM storage_transactions t
                JOIN storage_transactions t2 ON t2.transfer_pair_id = t.id AND t2.id <> t.id
                JOIN storage_accounts fa ON fa.id = (SELECT account_id FROM storage_transactions WHERE id = t.id)
                JOIN storage_accounts ta ON ta.id = t2.account_id
                WHERE t.ref_type = 'transfer_out'
                ORDER BY t.tanggal DESC, t.id DESC
                LIMIT 5
            ")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'accounts' => $rows,
                'total'    => $total,
                'donut'    => [
                    'labels' => array_map(fn($r) => $r['name'], $rows),
                    'data'   => array_map(fn($r) => $r['saldo'], $rows),
                ],
                'recent_allocations' => array_map(function($a) {
                    $lines = [];
                    if (!empty($a['line_info'])) foreach (explode('|', $a['line_info']) as $p) {
                        [$n, $v] = explode(':', $p, 2) + [null, null];
                        if ($n !== null) $lines[] = ['account' => $n, 'nominal' => (float)$v];
                    }
                    $a['total_nominal'] = (float)$a['total_nominal'];
                    $a['lines'] = $lines;
                    return $a;
                }, $recentAllocs),
                'recent_transfers' => array_map(function($t) {
                    $t['nominal'] = (float)$t['nominal'];
                    return $t;
                }, $recentTransfers),
            ]);
            break;
        }
        case 'get_allocations': {
            $page  = max(1, (int)($_GET['page']  ?? 1));
            $limit = max(5, min(100, (int)($_GET['limit'] ?? 15)));
            $dari   = $_GET['dari']   ?? '';
            $sampai = $_GET['sampai'] ?? '';
            $where = []; $args = [];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   { $where[] = 'a.tanggal >= ?'; $args[] = $dari; }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) { $where[] = 'a.tanggal <= ?'; $args[] = $sampai; }
            $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM storage_allocations a $sqlWhere");
            $stmtCount->execute($args);
            $totalRecords = (int)$stmtCount->fetchColumn();
            $totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;
            $offset = ($page - 1) * $limit;
            $stmt = $pdo->prepare("
                SELECT a.id, a.tanggal, a.ref_type, a.total_nominal, a.keterangan,
                       GROUP_CONCAT(CONCAT(sa.name, ':', t.nominal) SEPARATOR '|') AS line_info
                FROM storage_allocations a
                LEFT JOIN storage_transactions t ON t.ref_type='allocation' AND t.ref_id = a.id
                LEFT JOIN storage_accounts sa ON sa.id = t.account_id
                $sqlWhere
                GROUP BY a.id
                ORDER BY a.tanggal DESC, a.id DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($args);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $rows = array_map(function($a) {
                $lines = [];
                if (!empty($a['line_info'])) foreach (explode('|', $a['line_info']) as $p) {
                    [$n, $v] = explode(':', $p, 2) + [null, null];
                    if ($n !== null) $lines[] = ['account' => $n, 'nominal' => (float)$v];
                }
                $a['total_nominal'] = (float)$a['total_nominal'];
                $a['lines'] = $lines;
                return $a;
            }, $rows);
            echo json_encode([
                'data'       => $rows,
                'pagination' => [
                    'page' => $page, 'limit' => $limit,
                    'total_records' => $totalRecords, 'total_pages' => $totalPages,
                ],
            ]);
            break;
        }
        case 'get_transfers': {
            $page  = max(1, (int)($_GET['page']  ?? 1));
            $limit = max(5, min(100, (int)($_GET['limit'] ?? 15)));
            $offset = ($page - 1) * $limit;
            $stmtCount = $pdo->query("SELECT COUNT(DISTINCT transfer_pair_id) FROM storage_transactions WHERE ref_type='transfer_out'");
            $totalRecords = (int)$stmtCount->fetchColumn();
            $totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;
            $rows = $pdo->query("
                SELECT t.id, t.tanggal, t.nominal, t.keterangan, t.transfer_pair_id,
                       fa.name AS from_name, ta.name AS to_name
                FROM storage_transactions t
                JOIN storage_transactions t2 ON t2.transfer_pair_id = t.id AND t2.id <> t.id
                JOIN storage_accounts fa ON fa.id = t.account_id
                JOIN storage_accounts ta ON ta.id = t2.account_id
                WHERE t.ref_type = 'transfer_out'
                ORDER BY t.tanggal DESC, t.id DESC
                LIMIT $limit OFFSET $offset
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) $r['nominal'] = (float)$r['nominal'];
            unset($r);
            echo json_encode([
                'data'       => $rows,
                'pagination' => [
                    'page' => $page, 'limit' => $limit,
                    'total_records' => $totalRecords, 'total_pages' => $totalPages,
                ],
            ]);
            break;
        }
        case 'get_riwayat': {
            $where = [];
            $args  = [];
            $dari    = $_GET['dari']   ?? '';
            $sampai  = $_GET['sampai'] ?? '';
            $aksi    = $_GET['aksi']   ?? '';
            $page    = max(1, (int)($_GET['page']  ?? 1));
            $limit   = max(5, min(100, (int)($_GET['limit'] ?? 15)));
            $offset  = ($page - 1) * $limit;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari)) {
                $where[] = 'created_at >= ?';
                $args[]  = $dari . ' 00:00:00';
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) {
                $where[] = 'created_at <= ?';
                $args[]  = $sampai . ' 23:59:59';
            }
            if (in_array($aksi, ['tambah', 'edit', 'hapus', 'update_status'], true)) {
                $where[] = 'aksi = ?';
                $args[]  = $aksi;
            }
            $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            // Total records for pagination meta
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM activity_log $sqlWhere");
            $stmtCount->execute($args);
            $totalRecords = (int)$stmtCount->fetchColumn();
            $totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;
            // Paginated rows
            $stmt = $pdo->prepare("SELECT id, created_at, modul, aksi, entitas_id, ringkasan, detail, admin_username, admin_nama FROM activity_log $sqlWhere ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($args);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'data'       => $rows,
                'pagination' => [
                    'page'          => $page,
                    'limit'         => $limit,
                    'total_records' => $totalRecords,
                    'total_pages'   => $totalPages,
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
