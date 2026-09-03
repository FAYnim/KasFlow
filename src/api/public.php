<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/FinanceEngine.php';

$action = $_GET['action'] ?? '';
$pdo = db();

try {
    switch ($action) {
        case 'get_summary': {
            $summary = FinanceEngine::getSummary($pdo);
            echo json_encode([
                'total_kas_terkumpul' => $summary['total_balance'],
                'total_balance'       => $summary['total_balance'],
                'total_income'        => $summary['total_income'],
                'total_expense'       => $summary['total_expense'],
                'total_pending_queue' => $summary['total_pending_queue'],
                'accounts'            => $summary['accounts'],
                'saldo_bms'           => 0,
                'total_kasbon'        => 0,
                'saldo_awal'          => 0,
            ]);
            break;
        }
        case 'get_finance_public': {
            $summary = FinanceEngine::getSummary($pdo);
            $categories = FinanceEngine::getCategories($pdo, null, true);
            echo json_encode([
                'ok'         => true,
                'summary'    => $summary,
                'accounts'   => $summary['accounts'],
                'categories' => $categories
            ]);
            break;
        }
        case 'get_transactions_public': {
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = max(5, min(100, (int)($_GET['limit'] ?? 15)));
            $offset = ($page - 1) * $limit;
            $filters = [
                'start_date'  => $_GET['start_date'] ?? null,
                'end_date'    => $_GET['end_date'] ?? null,
                'type'        => $_GET['type'] ?? null,
                'account_id'  => $_GET['account_id'] ?? null,
                'category_id' => $_GET['category_id'] ?? null,
                'search'      => $_GET['search'] ?? null,
                'limit'       => $limit,
                'offset'      => $offset,
            ];
            $rows = FinanceEngine::getTransactions($pdo, $filters);
            $totalCount = FinanceEngine::countTransactions($pdo, $filters);
            $totalPages = $totalCount > 0 ? (int)ceil($totalCount / $limit) : 1;
            echo json_encode([
                'ok'           => true,
                'data'         => $rows,
                'transactions' => $rows,
                'pagination'   => [
                    'page'          => $page,
                    'limit'         => $limit,
                    'total_records' => $totalCount,
                    'total_pages'   => $totalPages
                ]
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
                if (isset($bulanMap[$bulanIdx])) { $where[] = 'MONTH(t.date) = ?'; $args[] = $bulanMap[$bulanIdx]; }
            }
            if ($tahun !== '') { $where[] = 'YEAR(t.date) = ?'; $args[] = (int)$tahun; }
            $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            // Total records for pagination meta
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM transactions t $sqlWhere");
            $stmtCount->execute($args);
            $totalRecords = (int)$stmtCount->fetchColumn();
            $totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;
            // Paginated rows — include storage account info & category
            $stmt = $pdo->prepare("
                SELECT t.id, t.date AS tanggal, t.description AS keterangan,
                       CASE t.type WHEN 'income' THEN 'masuk' WHEN 'expense' THEN 'keluar' ELSE 'transfer' END AS jenis,
                       t.amount AS nominal,
                       t.ref_type AS source, t.account_id AS storage_account_id,
                       a.name AS storage_account_name, c.name AS category_name
                FROM transactions t
                LEFT JOIN accounts a ON a.id = t.account_id
                LEFT JOIN categories c ON c.id = t.category_id
                $sqlWhere
                ORDER BY t.date DESC, t.id DESC LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($args);
            $rows = $stmt->fetchAll();
            // Line chart & donut use full (unpaged) dataset
            $saldoAwalChart = (float)$pdo->query("SELECT key_value FROM config WHERE key_name='saldo_awal'")->fetchColumn();
            $saldo = $saldoAwalChart;
            $line = [];
            $allAsc = $pdo->query("SELECT date, type, amount FROM transactions ORDER BY date ASC, id ASC")->fetchAll();
            foreach ($allAsc as $r) {
                if ($r['type'] === 'income') {
                    $saldo += (float)$r['amount'];
                } elseif ($r['type'] === 'expense') {
                    $saldo -= (float)$r['amount'];
                }
                $line[] = ['tanggal' => $r['date'], 'saldo' => $saldo];
            }
            // Donut totals based on current filter (all pages)
            $stmtAll = $pdo->prepare("SELECT t.type, SUM(t.amount) AS total FROM transactions t $sqlWhere GROUP BY t.type");
            $stmtAll->execute($args);
            $totMasuk = 0; $totKeluar = 0;
            foreach ($stmtAll->fetchAll() as $r) {
                if ($r['type'] === 'income') $totMasuk = (float)$r['total'];
                elseif ($r['type'] === 'expense') $totKeluar = (float)$r['total'];
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
        case 'get_jurnal_all': {
            $dari       = $_GET['dari'] ?? '';
            $sampai     = $_GET['sampai'] ?? '';
            $type       = $_GET['type'] ?? '';
            $accountId  = !empty($_GET['account_id']) ? (int)$_GET['account_id'] : null;
            $categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;

            $where = [];
            $args  = [];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   { $where[] = 't.date >= ?'; $args[] = $dari; }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) { $where[] = 't.date <= ?'; $args[] = $sampai; }
            if (!empty($type)) {
                $where[] = 't.type = ?';
                $args[] = $type;
            }
            if ($accountId) {
                $where[] = '(t.account_id = ? OR t.to_account_id = ?)';
                $args[] = $accountId;
                $args[] = $accountId;
            }
            if ($categoryId) {
                $where[] = 't.category_id = ?';
                $args[] = $categoryId;
            }

            $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $pdo->prepare("
                SELECT t.id, t.date AS tanggal, t.type,
                       t.description AS keterangan,
                       CASE t.type WHEN 'income' THEN 'masuk' WHEN 'expense' THEN 'keluar' ELSE 'transfer' END AS jenis,
                       t.amount AS nominal,
                       COALESCE(t.ref_type, 'manual') AS source,
                       a.name AS account_name,
                       to_a.name AS to_account_name,
                       c.name AS category_name
                FROM transactions t
                LEFT JOIN accounts a ON a.id = t.account_id
                LEFT JOIN accounts to_a ON to_a.id = t.to_account_id
                LEFT JOIN categories c ON c.id = t.category_id
                $sqlWhere
                ORDER BY t.date ASC, t.id ASC
            ");
            $stmt->execute($args);
            $rows = array_map(function($r) {
                $r['nominal'] = (float)$r['nominal'];
                return $r;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));

            $totMasuk    = array_sum(array_column(array_filter($rows, fn($r)=>$r['type']==='income'), 'nominal'));
            $totKeluar   = array_sum(array_column(array_filter($rows, fn($r)=>$r['type']==='expense'), 'nominal'));
            $totTransfer = array_sum(array_column(array_filter($rows, fn($r)=>$r['type']==='transfer'), 'nominal'));

            echo json_encode([
                'rows'   => $rows,
                'totals' => [
                    'masuk'    => $totMasuk,
                    'keluar'   => $totKeluar,
                    'transfer' => $totTransfer,
                    'net'      => $totMasuk - $totKeluar,
                    'count'    => count($rows)
                ]
            ]);
            break;
        }
        case 'get_kasbon': {
            $bulanMap = ['Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12];
            $bulanIdx = $_GET['bulan'] ?? array_search((int)date('n'), $bulanMap, true);
            $tahun    = (int)($_GET['tahun'] ?? date('Y'));
            $where = []; $args = [];
            if (isset($bulanMap[$bulanIdx])) {
                $where[] = 'MONTH(k.tanggal) = ?';
                $args[]  = $bulanMap[$bulanIdx];
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'bulan tidak valid']);
                break;
            }
            $where[] = 'YEAR(k.tanggal) = ?';
            $args[]  = $tahun;
            $sqlWhere = 'WHERE ' . implode(' AND ', $where);
            $stmt = $pdo->prepare("
                SELECT k.id, k.siswa_id, k.tanggal, k.keterangan, k.jumlah, k.status, k.tanggal_lunas,
                       COALESCE(s.nama, k.nama) AS nama,
                       s.absen AS absen
                FROM kasbon k
                LEFT JOIN siswa s ON s.id = k.siswa_id
                $sqlWhere
                ORDER BY k.tanggal DESC, k.id DESC
            ");
            $stmt->execute($args);
            $rows = array_map(function($r) {
                $r['jumlah']    = (float)$r['jumlah'];
                $r['siswa_id']  = $r['siswa_id'] ? (int)$r['siswa_id'] : null;
                return $r;
            }, $stmt->fetchAll());
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
        case 'get_storage_breakdown': {            $rows = FinanceEngine::getAccountsWithBalances($pdo, true);
            $total = 0.0;
            foreach ($rows as $r) {
                $total += (float)$r['balance'];
            }
            echo json_encode([
                'accounts' => $rows,
                'total'    => $total,
                'donut'    => [
                    'labels' => array_map(fn($r) => $r['name'], $rows),
                    'data'   => array_map(fn($r) => max(0, (float)$r['balance']), $rows),
                ],
                'recent_allocations' => [],
                'recent_transfers'   => [],
            ]);
            break;
        }
        case 'get_allocations': {
            $page  = max(1, (int)($_GET['page']  ?? 1));
            $limit = max(5, min(100, (int)($_GET['limit'] ?? 15)));
            $dari       = $_GET['dari']       ?? '';
            $sampai     = $_GET['sampai']     ?? '';
            $keterangan = trim($_GET['keterangan'] ?? '');
            $where = []; $args = [];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   { $where[] = 'a.tanggal >= ?'; $args[] = $dari; }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) { $where[] = 'a.tanggal <= ?'; $args[] = $sampai; }
            if ($keterangan !== '')                             { $where[] = 'a.keterangan LIKE ?'; $args[] = '%' . $keterangan . '%'; }
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
        case 'get_alokasi_filtered_kpi': {
            // Kembalikan total nominal per-akun dari alokasi yang cocok filter
            $dari       = $_GET['dari']       ?? '';
            $sampai     = $_GET['sampai']     ?? '';
            $keterangan = trim($_GET['keterangan'] ?? '');
            $where = []; $args = [];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   { $where[] = 'a.tanggal >= ?'; $args[] = $dari; }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) { $where[] = 'a.tanggal <= ?'; $args[] = $sampai; }
            if ($keterangan !== '')                             { $where[] = 'a.keterangan LIKE ?'; $args[] = '%' . $keterangan . '%'; }
            $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            // Ambil semua akun aktif dulu
            $accs = $pdo->query("
                SELECT id, name, type, parent_type, icon FROM storage_accounts WHERE is_active=1 ORDER BY sort_order, id
            ")->fetchAll(PDO::FETCH_ASSOC);
            // Hitung total per-akun dari storage_transactions yang ref_type=allocation dan ref_id cocok filter
            $stmt = $pdo->prepare("
                SELECT t.account_id, SUM(t.nominal) AS total
                FROM storage_transactions t
                JOIN storage_allocations a ON a.id = t.ref_id AND t.ref_type = 'allocation'
                $sqlWhere
                GROUP BY t.account_id
            ");
            $stmt->execute($args);
            $totalsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $totals = [];
            foreach ($totalsRaw as $row) $totals[(int)$row['account_id']] = (float)$row['total'];
            $grandTotal = 0.0;
            $accounts = array_map(function($a) use ($totals, &$grandTotal) {
                $saldo = $totals[(int)$a['id']] ?? 0.0;
                $grandTotal += $saldo;
                $a['saldo'] = $saldo;
                return $a;
            }, $accs);
            echo json_encode([
                'accounts' => $accounts,
                'total'    => $grandTotal,
                'donut'    => [
                    'labels' => array_map(fn($a) => $a['name'], $accounts),
                    'data'   => array_map(fn($a) => $a['saldo'], $accounts),
                ],
            ]);
            break;
        }
        case 'get_transfers': {
            $page   = max(1, (int)($_GET['page']   ?? 1));
            $limit  = max(5, min(100, (int)($_GET['limit'] ?? 15)));
            $dari   = $_GET['dari']   ?? '';
            $sampai = $_GET['sampai'] ?? '';
            $where  = ["t.ref_type = 'transfer_out'"];
            $args   = [];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   { $where[] = 't.tanggal >= ?'; $args[] = $dari; }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) { $where[] = 't.tanggal <= ?'; $args[] = $sampai; }
            $sqlWhere = 'WHERE ' . implode(' AND ', $where);

            $stmtCount = $pdo->prepare("SELECT COUNT(DISTINCT t.transfer_pair_id) FROM storage_transactions t $sqlWhere");
            $stmtCount->execute($args);
            $totalRecords = (int)$stmtCount->fetchColumn();
            $totalPages   = $totalRecords > 0 ? (int)ceil($totalRecords / $limit) : 1;
            $offset = ($page - 1) * $limit;

            $stmt = $pdo->prepare("
                SELECT t.id, t.tanggal, t.nominal, t.keterangan, t.transfer_pair_id,
                       fa.name AS from_name, ta.name AS to_name
                FROM storage_transactions t
                JOIN storage_transactions t2 ON t2.transfer_pair_id = t.id AND t2.id <> t.id
                JOIN storage_accounts fa ON fa.id = t.account_id
                JOIN storage_accounts ta ON ta.id = t2.account_id
                $sqlWhere
                ORDER BY t.tanggal DESC, t.id DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute($args);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            $modul   = trim($_GET['modul'] ?? '');
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
            if (in_array($aksi, ['tambah', 'edit', 'hapus', 'update_status', 'claim_kas'], true)) {
                $where[] = 'aksi = ?';
                $args[]  = $aksi;
            }
            if ($modul !== '') {
                if ($modul === 'legacy') {
                    $where[] = "modul IN ('alokasi', 'jurnal_kas', 'kasbon', 'kas_bms', 'storage_transfer', 'storage_account')";
                } elseif (in_array($modul, ['cashflow', 'kas_mingguan', 'account', 'category', 'siswa', 'config'], true)) {
                    $where[] = 'modul = ?';
                    $args[]  = $modul;
                }
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
        case 'get_config': {
            $rows = $pdo->query("SELECT key_name, key_value FROM config")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode([
                'ok'     => true,
                'config' => [
                    'nama_kelas'         => $rows['nama_kelas'] ?? 'RPL 1',
                    'tarif_kas_mingguan' => (int)($rows['tarif_kas_mingguan'] ?? 5000),
                    'saldo_awal'         => (float)($rows['saldo_awal'] ?? 0),
                ],
            ]);
            break;
        }
        case 'export_kasminggu': {
            $bulanMap  = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
            $bulanInput = $_GET['bulan'] ?? $bulanMap[(int)date('n')];
            if (is_numeric($bulanInput) && isset($bulanMap[(int)$bulanInput])) {
                $bulan = $bulanMap[(int)$bulanInput];
            } else {
                $bulan = (string)$bulanInput;
            }
            $tahun = (int)($_GET['tahun'] ?? date('Y'));
            $tarif = (int)$pdo->query("SELECT key_value FROM config WHERE key_name='tarif_kas_mingguan'")->fetchColumn();
            $rows  = $pdo->prepare("
                SELECT s.absen, s.nama,
                       COALESCE(k.minggu_1,0) m1, COALESCE(k.minggu_2,0) m2,
                       COALESCE(k.minggu_3,0) m3, COALESCE(k.minggu_4,0) m4,
                       COALESCE(k.minggu_5,0) m5, COALESCE(k.total_bayar,0) total_bayar
                FROM siswa s
                LEFT JOIN kas_mingguan k ON k.siswa_id = s.id AND k.bulan = ? AND k.tahun = ?
                ORDER BY CAST(s.absen AS UNSIGNED) ASC, s.nama ASC
            ");
            $rows->execute([$bulan, $tahun]);
            $data = $rows->fetchAll(PDO::FETCH_ASSOC);
            $sumTotal = array_sum(array_map(fn($r) => (float)$r['total_bayar'], $data));
            echo json_encode([
                'bulan' => $bulan, 'tahun' => $tahun, 'tarif' => $tarif,
                'rows'  => $data, 'totals' => ['sum' => $sumTotal, 'count' => count($data)],
            ]);
            break;
        }
        case 'export_kasbon': {
            $bulanMap  = ['Januari'=>1,'Februari'=>2,'Maret'=>3,'April'=>4,'Mei'=>5,'Juni'=>6,'Juli'=>7,'Agustus'=>8,'September'=>9,'Oktober'=>10,'November'=>11,'Desember'=>12];
            $bulanName = $_GET['bulan'] ?? array_search((int)date('n'), $bulanMap, true);
            $bulanInt  = is_numeric($bulanName) ? (int)$bulanName : ($bulanMap[$bulanName] ?? (int)date('n'));
            $tahun     = (int)($_GET['tahun'] ?? date('Y'));
            $rows = $pdo->prepare("
                SELECT k.tanggal, COALESCE(s.nama, k.nama) AS nama, s.absen AS absen, k.keterangan, k.jumlah, k.status
                FROM kasbon k LEFT JOIN siswa s ON s.id = k.siswa_id
                WHERE MONTH(k.tanggal) = ? AND YEAR(k.tanggal) = ?
                ORDER BY k.tanggal DESC
            ");
            $rows->execute([$bulanInt, $tahun]);
            $data = $rows->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['bulan' => $bulanName, 'tahun' => $tahun, 'rows' => $data]);
            break;
        }
        case 'export_bms': {
            $dari   = $_GET['dari'] ?? null;
            $sampai = $_GET['sampai'] ?? null;
            $where  = []; $args = [];
            if ($dari)   { $where[] = 'tanggal >= ?'; $args[] = $dari; }
            if ($sampai) { $where[] = 'tanggal <= ?'; $args[] = $sampai; }
            $sql = 'SELECT id, tanggal, keterangan, jenis, jumlah FROM kas_bms'
                 . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                 . ' ORDER BY tanggal DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($args);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $setor = $tarik = 0.0;
            foreach ($data as $r) { if ($r['jenis'] === 'setor') $setor += (float)$r['jumlah']; else $tarik += (float)$r['jumlah']; }
            echo json_encode(['rows' => $data, 'totals' => ['setor' => $setor, 'tarik' => $tarik, 'saldo' => $setor - $tarik]]);
            break;
        }
        case 'export_alokasi': {
            $dari       = $_GET['dari'] ?? '';
            $sampai     = $_GET['sampai'] ?? '';
            $keterangan = trim($_GET['keterangan'] ?? '');
            $where      = []; $args = [];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dari))   { $where[] = 'a.tanggal >= ?'; $args[] = $dari; }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $sampai)) { $where[] = 'a.tanggal <= ?'; $args[] = $sampai; }
            if ($keterangan !== '')                             { $where[] = 'a.keterangan LIKE ?'; $args[] = '%' . $keterangan . '%'; }
            $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $pdo->prepare("
                SELECT a.id, a.tanggal, a.ref_type, a.total_nominal, a.keterangan,
                       GROUP_CONCAT(CONCAT(sa.name, ':', t.nominal) SEPARATOR '|') AS line_info
                FROM storage_allocations a
                LEFT JOIN storage_transactions t ON t.ref_type='allocation' AND t.ref_id = a.id
                LEFT JOIN storage_accounts sa ON sa.id = t.account_id
                $sqlWhere
                GROUP BY a.id
                ORDER BY a.tanggal DESC, a.id DESC
            ");
            $stmt->execute($args);
            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $rows = array_map(function($a) {
                $lines = [];
                if (!empty($a['line_info'])) foreach (explode('|', $a['line_info']) as $p) {
                    [$n, $v] = explode(':', $p, 2) + [null, null];
                    if ($n !== null) $lines[] = $n . ' (' . number_format((float)$v, 0, ',', '.') . ')';
                }
                $a['lines_str'] = implode(', ', $lines);
                $a['total_nominal'] = (float)$a['total_nominal'];
                return $a;
            }, $raw);
            // KPI alokasi: total nominal per-akun (dana) pada rentang/filter yang sama
            $kpiAccs = $pdo->query("
                SELECT id, name, type, parent_type, icon FROM storage_accounts WHERE is_active=1 ORDER BY sort_order, id
            ")->fetchAll(PDO::FETCH_ASSOC);
            $kpiStmt = $pdo->prepare("
                SELECT t.account_id, SUM(t.nominal) AS total
                FROM storage_transactions t
                JOIN storage_allocations a ON a.id = t.ref_id AND t.ref_type = 'allocation'
                $sqlWhere
                GROUP BY t.account_id
            ");
            $kpiStmt->execute($args);
            $kpiTotals = [];
            foreach ($kpiStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $kpiTotals[(int)$row['account_id']] = (float)$row['total'];
            }
            $kpiGrand = 0.0;
            $kpiAccounts = array_map(function($a) use ($kpiTotals, &$kpiGrand) {
                $saldo = $kpiTotals[(int)$a['id']] ?? 0.0;
                $kpiGrand += $saldo;
                $a['saldo'] = $saldo;
                return $a;
            }, $kpiAccs);
            echo json_encode([
                'rows' => $rows,
                'kpi'  => [
                    'accounts' => $kpiAccounts,
                    'total'    => $kpiGrand,
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
