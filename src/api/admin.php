<?php
@session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/activity_log.php';

if (empty($_SESSION['admin_logged'])) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$pdo = db();

try {
    switch ($action) {
        case 'add_siswa': {
            $absen = trim($_POST['absen'] ?? '');
            $nama  = trim($_POST['nama'] ?? '');
            if ($nama === '') { http_response_code(400); echo json_encode(['error'=>'nama required']); break; }
            $stmt = $pdo->prepare('INSERT INTO siswa (absen, nama) VALUES (?, ?)');
            $stmt->execute([$absen ?: null, $nama]);
            $newId = (int)$pdo->lastInsertId();
            log_activity($pdo, 'siswa', 'tambah', $newId, 'Tambah siswa: ' . $nama);
            echo json_encode(['ok' => true, 'id' => $newId]);
            break;
        }
        case 'update_kas': {
            $siswa_id = (int)($_POST['siswa_id'] ?? 0);
            $bulan    = $_POST['bulan'] ?? date('F');
            $tahun    = (int)($_POST['tahun'] ?? date('Y'));
            $minggu   = (int)($_POST['minggu'] ?? 0);
            $checked  = (int)($_POST['checked'] ?? 0);
            if (!in_array($minggu, [1,2,3,4,5], true)) { http_response_code(400); echo json_encode(['error'=>'invalid minggu']); break; }
            $tarif = (int)$pdo->query("SELECT key_value FROM config WHERE key_name='tarif_kas_mingguan'")->fetchColumn();
            $col = "minggu_$minggu";
            $pdo->prepare("
                INSERT INTO kas_mingguan (siswa_id, bulan, tahun, $col, total_bayar)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE $col = VALUES($col), total_bayar = ?
            ")->execute([$siswa_id, $bulan, $tahun, $checked, $tarif, $tarif]);
            // Recompute total_bayar correctly:
            $pdo->prepare("
                UPDATE kas_mingguan
                SET total_bayar = (minggu_1+minggu_2+minggu_3+minggu_4+minggu_5) * ?
                WHERE siswa_id=? AND bulan=? AND tahun=?
            ")->execute([$tarif, $siswa_id, $bulan, $tahun]);
            $stmt = $pdo->prepare("SELECT total_bayar FROM kas_mingguan WHERE siswa_id=? AND bulan=? AND tahun=?");
            $stmt->execute([$siswa_id, $bulan, $tahun]);
            $row = $stmt->fetch();
            $namaStmt = $pdo->prepare("SELECT nama FROM siswa WHERE id=?");
            $namaStmt->execute([$siswa_id]);
            $namaSiswa = $namaStmt->fetchColumn() ?: ('#' . $siswa_id);
            $verb = $checked ? 'Centang' : 'Hapus centang';
            $detail = [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'total_perubahan' => 1,
                'perubahan' => [
                    [
                        'siswa_id' => $siswa_id,
                        'nama' => $namaSiswa,
                        'minggu' => $minggu,
                        'status' => $checked ? 'lunas' : 'batal'
                    ]
                ]
            ];
            log_activity($pdo, 'kas_mingguan', 'update_status', $siswa_id, "$verb kas $namaSiswa minggu $minggu ($bulan $tahun)", $detail);
            echo json_encode(['ok' => true, 'total_bayar' => (float)$row['total_bayar']]);
            break;
        }
        case 'bulk_update_kas': {
            $bulan = $_POST['bulan'] ?? date('F');
            $tahun = (int)($_POST['tahun'] ?? date('Y'));
            $changesJson = $_POST['changes'] ?? '[]';
            $changes = json_decode($changesJson, true);
            if (!is_array($changes)) { http_response_code(400); echo json_encode(['error'=>'invalid changes']); break; }
            // Integrasi parameter (opsional)
            $catatJurnal  = (int)($_POST['catat_jurnal'] ?? 0);
            $storAccId    = ($_POST['storage_account_id'] ?? '') !== '' ? (int)$_POST['storage_account_id'] : null;
            $jurKet       = trim($_POST['jurnal_keterangan'] ?? "Penerimaan Kas Mingguan $bulan $tahun");
            $jurTgl       = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['jurnal_tanggal'] ?? '') ? $_POST['jurnal_tanggal'] : date('Y-m-d');
            $tarif = (int)$pdo->query("SELECT key_value FROM config WHERE key_name='tarif_kas_mingguan'")->fetchColumn();
            $totals = [];
            // Ambil state sebelumnya untuk hitung delta (centang baru = 1, sebelumnya = 0)
            $prevStates = [];
            $allSids = array_unique(array_filter(array_map(fn($c) => (int)($c['siswa_id'] ?? 0), $changes)));
            if (!empty($allSids)) {
                $inC = implode(',', array_fill(0, count($allSids), '?'));
                $prevStmt = $pdo->prepare("SELECT siswa_id, minggu_1, minggu_2, minggu_3, minggu_4, minggu_5 FROM kas_mingguan WHERE siswa_id IN ($inC) AND bulan=? AND tahun=?");
                $prevStmt->execute([...array_values($allSids), $bulan, $tahun]);
                foreach ($prevStmt->fetchAll() as $r) {
                    $prevStates[(int)$r['siswa_id']] = [
                        1 => (int)$r['minggu_1'], 2 => (int)$r['minggu_2'],
                        3 => (int)$r['minggu_3'], 4 => (int)$r['minggu_4'], 5 => (int)$r['minggu_5'],
                    ];
                }
            }
            // Hitung berapa unit baru yang baru dicentang (untuk nominal jurnal)
            $newCheckedCount = 0;
            foreach ($changes as $c) {
                $sid = (int)($c['siswa_id'] ?? 0); $m = (int)($c['minggu'] ?? 0); $chk = (int)($c['checked'] ?? 0);
                if ($sid <= 0 || !in_array($m, [1,2,3,4,5], true)) continue;
                $prev = $prevStates[$sid][$m] ?? 0;
                if ($chk === 1 && $prev === 0) $newCheckedCount++;
            }
            $nominalBaru = $newCheckedCount * $tarif;
            $pdo->beginTransaction();
            try {
                foreach ($changes as $c) {
                    $sid = (int)($c['siswa_id'] ?? 0);
                    $m   = (int)($c['minggu'] ?? 0);
                    $chk = (int)($c['checked'] ?? 0);
                    if ($sid <= 0 || !in_array($m, [1,2,3,4,5], true)) continue;
                    $col = "minggu_$m";
                    $pdo->prepare("
                        INSERT INTO kas_mingguan (siswa_id, bulan, tahun, $col, total_bayar)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE $col = VALUES($col)
                    ")->execute([$sid, $bulan, $tahun, $chk, 0]);
                    $pdo->prepare("
                        UPDATE kas_mingguan
                        SET total_bayar = (minggu_1+minggu_2+minggu_3+minggu_4+minggu_5) * ?
                        WHERE siswa_id=? AND bulan=? AND tahun=?
                    ")->execute([$tarif, $sid, $bulan, $tahun]);
                }
                // Otomatis catat ke Jurnal Kas & Tempat Penyimpanan jika dipilih
                $jurnalKasId = null;
                if ($catatJurnal && $nominalBaru > 0) {
                    $pdo->prepare("INSERT INTO jurnal_kas (tanggal, keterangan, jenis, nominal, storage_account_id, source) VALUES (?,?,'masuk',?,?,?)")
                        ->execute([$jurTgl, $jurKet, $nominalBaru, $storAccId ?: null, 'kas_mingguan']);
                    $jurnalKasId = (int)$pdo->lastInsertId();
                    if ($storAccId) {
                        $pdo->prepare("INSERT INTO storage_transactions (account_id, tanggal, jenis, nominal, ref_type, ref_id, keterangan) VALUES (?,?,'masuk',?,'jurnal',?,?)")
                            ->execute([$storAccId, $jurTgl, $nominalBaru, $jurnalKasId, $jurKet]);
                    }
                    log_activity($pdo, 'jurnal_kas', 'tambah', $jurnalKasId, "Auto-catat Kas Mingguan #$jurnalKasId: $jurKet (Rp " . number_format($nominalBaru, 0, ',', '.') . ")", [
                        'source' => 'kas_mingguan', 'tanggal' => $jurTgl, 'nominal' => $nominalBaru, 'storage_account_id' => $storAccId
                    ]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'save failed']); break;
            }
            $stmt = $pdo->prepare("SELECT siswa_id, total_bayar FROM kas_mingguan WHERE bulan=? AND tahun=?");
            $stmt->execute([$bulan, $tahun]);
            foreach ($stmt as $r) $totals[(int)$r['siswa_id']] = (float)$r['total_bayar'];

            // Build detailed log
            $namaMap = [];
            $sids = array_unique(array_filter(array_map(fn($c) => (int)($c['siswa_id'] ?? 0), $changes)));
            if (!empty($sids)) {
                $inClause = implode(',', array_fill(0, count($sids), '?'));
                $stmtSiswa = $pdo->prepare("SELECT id, nama FROM siswa WHERE id IN ($inClause)");
                $stmtSiswa->execute(array_values($sids));
                while ($row = $stmtSiswa->fetch()) {
                    $namaMap[(int)$row['id']] = $row['nama'];
                }
            }

            $perubahan = [];
            $summaryItems = [];
            foreach ($changes as $c) {
                $sid = (int)($c['siswa_id'] ?? 0);
                $m   = (int)($c['minggu'] ?? 0);
                $chk = (int)($c['checked'] ?? 0);
                if ($sid <= 0 || !in_array($m, [1,2,3,4,5], true)) continue;
                $namaSiswa = $namaMap[$sid] ?? ("#" . $sid);
                $perubahan[] = [
                    'siswa_id' => $sid,
                    'nama' => $namaSiswa,
                    'minggu' => $m,
                    'status' => $chk ? 'lunas' : 'batal'
                ];
                $summaryItems[] = "$namaSiswa (M$m: " . ($chk ? 'Lunas' : 'Batal') . ")";
            }

            $totalPerubahan = count($perubahan);
            $summaryStr = "";
            if ($totalPerubahan > 0) {
                $firstFew = array_slice($summaryItems, 0, 3);
                $summaryStr = ": " . implode(', ', $firstFew);
                if ($totalPerubahan > 3) {
                    $summaryStr .= " + " . ($totalPerubahan - 3) . " lainnya";
                }
            }
            $ringkasan = "Update kas $bulan $tahun ($totalPerubahan perubahan)$summaryStr";

            $detail = [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'total_perubahan' => $totalPerubahan,
                'perubahan' => $perubahan,
                'catat_jurnal' => $catatJurnal,
                'nominal_baru' => $nominalBaru,
                'jurnal_kas_id' => $jurnalKasId,
            ];
            log_activity($pdo, 'kas_mingguan', 'update_status', null, $ringkasan, $detail);
            echo json_encode(['ok' => true, 'totals' => $totals, 'saved' => count($changes), 'nominal_baru' => $nominalBaru, 'jurnal_kas_id' => $jurnalKasId]);
            break;
        }
        case 'add_jurnal': {
            $tgl   = $_POST['tanggal'] ?? date('Y-m-d');
            $ket   = trim($_POST['keterangan'] ?? '');
            $jenis = $_POST['jenis'] ?? '';
            $nom   = (float)($_POST['nominal'] ?? 0);
            $storAccId = ($_POST['storage_account_id'] ?? '') !== '' ? (int)$_POST['storage_account_id'] : null;
            $src   = 'manual'; // default source
            if ($ket === '' || !in_array($jenis, ['masuk','keluar'], true) || $nom <= 0) {
                http_response_code(400); echo json_encode(['error'=>'invalid']); break;
            }
            // Validate storage account if provided
            if ($storAccId !== null) {
                $chkAcc = $pdo->prepare("SELECT id FROM storage_accounts WHERE id=? AND is_active=1");
                $chkAcc->execute([$storAccId]);
                if (!$chkAcc->fetchColumn()) { http_response_code(400); echo json_encode(['error'=>'invalid storage account']); break; }
            }
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO jurnal_kas (tanggal, keterangan, jenis, nominal, storage_account_id, source) VALUES (?,?,?,?,?,?)")
                    ->execute([$tgl, $ket, $jenis, $nom, $storAccId, $src]);
                $newId = (int)$pdo->lastInsertId();
                // Auto-create storage_transaction jika tempat penyimpanan dipilih
                if ($storAccId !== null) {
                    $pdo->prepare("INSERT INTO storage_transactions (account_id, tanggal, jenis, nominal, ref_type, ref_id, keterangan) VALUES (?,?,?,?,'jurnal',?,?)")
                        ->execute([$storAccId, $tgl, $jenis, $nom, $newId, $ket]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'save failed']); break;
            }
            $labelJenis = $jenis === 'masuk' ? 'Pemasukan' : 'Pengeluaran';
            $storName = '';
            if ($storAccId !== null) {
                $storName = $pdo->prepare("SELECT name FROM storage_accounts WHERE id=?");
                $storName->execute([$storAccId]);
                $storName = ' → ' . ($storName->fetchColumn() ?: 'Akun #'.$storAccId);
            }
            $ringkasan = "Tambah jurnal $labelJenis #$newId: $ket (Rp " . number_format($nom, 0, ',', '.') . ")$storName";
            $detail = ['tanggal'=>$tgl, 'keterangan'=>$ket, 'jenis'=>$labelJenis, 'nominal'=>$nom, 'storage_account_id'=>$storAccId];
            log_activity($pdo, 'jurnal_kas', 'tambah', $newId, $ringkasan, $detail);
            echo json_encode(['ok' => true, 'id' => $newId]);
            break;
        }
        case 'update_jurnal': {
            $id   = (int)($_POST['id'] ?? 0);
            $tgl  = $_POST['tanggal'];
            $ket  = trim($_POST['keterangan'] ?? '');
            $jenis= $_POST['jenis'];
            $nom  = (float)$_POST['nominal'];
            $storAccId = ($_POST['storage_account_id'] ?? '') !== '' ? (int)$_POST['storage_account_id'] : null;
            // Validate storage account if provided
            if ($storAccId !== null) {
                $chkAcc = $pdo->prepare("SELECT id FROM storage_accounts WHERE id=? AND is_active=1");
                $chkAcc->execute([$storAccId]);
                if (!$chkAcc->fetchColumn()) { http_response_code(400); echo json_encode(['error'=>'invalid storage account']); break; }
            }
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE jurnal_kas SET tanggal=?, keterangan=?, jenis=?, nominal=?, storage_account_id=? WHERE id=?")
                    ->execute([$tgl, $ket, $jenis, $nom, $storAccId, $id]);
                // Hapus storage_transaction lama (ref_type='jurnal') & buat baru jika ada akun
                $pdo->prepare("DELETE FROM storage_transactions WHERE ref_type='jurnal' AND ref_id=?")->execute([$id]);
                if ($storAccId !== null) {
                    $pdo->prepare("INSERT INTO storage_transactions (account_id, tanggal, jenis, nominal, ref_type, ref_id, keterangan) VALUES (?,?,?,?,'jurnal',?,?)")
                        ->execute([$storAccId, $tgl, $jenis, $nom, $id, $ket]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'save failed']); break;
            }
            $labelJenis = $jenis === 'masuk' ? 'Pemasukan' : 'Pengeluaran';
            $ringkasan = "Edit jurnal #$id ($labelJenis): $ket (Rp " . number_format($nom, 0, ',', '.') . ")";
            $detail = ['id'=>$id, 'tanggal'=>$tgl, 'keterangan'=>$ket, 'jenis'=>$labelJenis, 'nominal'=>$nom, 'storage_account_id'=>$storAccId];
            log_activity($pdo, 'jurnal_kas', 'edit', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'delete_jurnal': {
            $id = (int)($_REQUEST['id'] ?? 0);
            $jurnalStmt = $pdo->prepare("SELECT tanggal, keterangan, jenis, nominal FROM jurnal_kas WHERE id=?");
            $jurnalStmt->execute([$id]);
            $rowJurnal = $jurnalStmt->fetch(PDO::FETCH_ASSOC);
            $ket = $rowJurnal['keterangan'] ?? '';
            $tgl = $rowJurnal['tanggal'] ?? '';
            $jenis = $rowJurnal['jenis'] ?? '';
            $nom = (float)($rowJurnal['nominal'] ?? 0);
            $labelJenis = $jenis === 'masuk' ? 'Pemasukan' : ($jenis === 'keluar' ? 'Pengeluaran' : $jenis);
            $ringkasan = "Hapus jurnal #$id ($labelJenis): $ket (Rp " . number_format($nom, 0, ',', '.') . ")";
            $detail = ['id'=>$id, 'tanggal'=>$tgl, 'keterangan'=>$ket, 'jenis'=>$labelJenis, 'nominal'=>$nom];
            $pdo->beginTransaction();
            try {
                // Hapus storage_transaction terkait
                $pdo->prepare("DELETE FROM storage_transactions WHERE ref_type='jurnal' AND ref_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM jurnal_kas WHERE id=?")->execute([$id]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'delete failed']); break;
            }
            log_activity($pdo, 'jurnal_kas', 'hapus', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'delete_siswa': {
            $id = (int)($_REQUEST['id'] ?? 0);
            $siswaStmt = $pdo->prepare("SELECT absen, nama FROM siswa WHERE id=?");
            $siswaStmt->execute([$id]);
            $rowSiswa = $siswaStmt->fetch(PDO::FETCH_ASSOC);
            $nama = $rowSiswa['nama'] ?? '';
            $absen = $rowSiswa['absen'] ?? '';
            $ringkasan = 'Hapus siswa #' . $id . ($nama !== '' ? ": $nama" : '') . ($absen !== '' ? " (Absen $absen)" : '');
            $detail = ['id' => $id, 'nama' => $nama, 'absen' => $absen ?: '-'];
            $pdo->prepare("DELETE FROM siswa WHERE id=?")->execute([$id]);
            log_activity($pdo, 'siswa', 'hapus', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'list_siswa': {
            $rows = $pdo->query("SELECT id, absen, nama FROM siswa ORDER BY absen ASC, nama ASC")->fetchAll();
            echo json_encode($rows);
            break;
        }
        case 'update_siswa': {
            $id    = (int)$_POST['id'];
            $absen = trim($_POST['absen'] ?? '');
            $nama  = trim($_POST['nama'] ?? '');
            $pdo->prepare("UPDATE siswa SET absen=?, nama=? WHERE id=?")->execute([$absen ?: null, $nama, $id]);
            $ringkasan = 'Edit siswa #' . $id . ': ' . $nama . ($absen !== '' ? " (Absen $absen)" : '');
            $detail = ['id' => $id, 'nama' => $nama, 'absen' => $absen ?: '-'];
            log_activity($pdo, 'siswa', 'edit', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'add_kasbon': {
            $siswaId = (int)($_POST['siswa_id'] ?? 0) ?: null;
            $nama    = trim($_POST['nama'] ?? '');
            $tgl     = $_POST['tanggal'] ?? date('Y-m-d');
            $ket     = trim($_POST['keterangan'] ?? '');
            $jml     = (float)($_POST['jumlah'] ?? 0);
            $stat    = $_POST['status'] ?? 'belum_lunas';
            // Jika siswa dipilih dari dropdown, ambil nama dari tabel siswa
            if ($siswaId) {
                $namaRow = $pdo->prepare("SELECT nama FROM siswa WHERE id=?");
                $namaRow->execute([$siswaId]);
                $fetchedNama = $namaRow->fetchColumn();
                if ($fetchedNama) $nama = $fetchedNama;
            }
            if ($nama === '' || $jml <= 0 || !in_array($stat, ['belum_lunas','lunas'], true)) {
                http_response_code(400); echo json_encode(['error' => 'invalid']); break;
            }
            $tLunas = ($stat === 'lunas') ? date('Y-m-d') : null;
            $jurnalId = null;
            if ($stat === 'lunas') {
                $jKet = "Penggantian talangan " . $nama . ": " . $ket;
                $pdo->prepare("INSERT INTO jurnal_kas (tanggal, keterangan, jenis, nominal) VALUES (?,?,'keluar',?)")
                    ->execute([$tgl, $jKet, $jml]);
                $jurnalId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'jurnal_kas', 'tambah', $jurnalId, "Tambah pengeluaran (Talangan #$jurnalId): $jKet (Rp " . number_format($jml, 0, ',', '.') . ")", ['tanggal'=>$tgl, 'keterangan'=>$jKet, 'jenis'=>'Pengeluaran', 'nominal'=>$jml]);
            }
            $pdo->prepare("INSERT INTO kasbon (siswa_id, nama, tanggal, keterangan, jumlah, status, tanggal_lunas, jurnal_id) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$siswaId, $nama, $tgl, $ket, $jml, $stat, $tLunas, $jurnalId]);
            $newId = (int)$pdo->lastInsertId();
            $statusLabel = $stat === 'lunas' ? 'Sudah Diganti' : 'Belum Diganti';
            $ringkasan = "Tambah talangan #$newId: $nama (Rp " . number_format($jml, 0, ',', '.') . " - $statusLabel)";
            $detail = ['siswa_id' => $siswaId, 'nama' => $nama, 'tanggal' => $tgl, 'keterangan' => $ket, 'jumlah' => $jml, 'status' => $statusLabel];
            log_activity($pdo, 'kasbon', 'tambah', $newId, $ringkasan, $detail);
            echo json_encode(['ok' => true, 'id' => $newId]);
            break;
        }
        case 'update_kasbon': {
            $id      = (int)($_POST['id'] ?? 0);
            $siswaId = (int)($_POST['siswa_id'] ?? 0) ?: null;
            $nama    = trim($_POST['nama'] ?? '');
            $tgl     = $_POST['tanggal'] ?? date('Y-m-d');
            $ket     = trim($_POST['keterangan'] ?? '');
            $jml     = (float)($_POST['jumlah'] ?? 0);
            $stat    = $_POST['status'] ?? 'belum_lunas';
            // Jika siswa dipilih dari dropdown, ambil nama dari tabel siswa
            if ($siswaId) {
                $namaRow = $pdo->prepare("SELECT nama FROM siswa WHERE id=?");
                $namaRow->execute([$siswaId]);
                $fetchedNama = $namaRow->fetchColumn();
                if ($fetchedNama) $nama = $fetchedNama;
            }
            if ($id <= 0 || $nama === '' || $jml <= 0 || !in_array($stat, ['belum_lunas','lunas'], true)) {
                http_response_code(400); echo json_encode(['error' => 'invalid']); break;
            }
            $curStmt = $pdo->prepare("SELECT status, tanggal_lunas, jurnal_id FROM kasbon WHERE id=?");
            $curStmt->execute([$id]);
            $curRow = $curStmt->fetch(PDO::FETCH_ASSOC);
            if (!$curRow) { http_response_code(404); echo json_encode(['error'=>'not found']); break; }
            $jurnalId = $curRow['jurnal_id'] ? (int)$curRow['jurnal_id'] : null;
            $tLunas = $curRow['tanggal_lunas'];

            if ($stat === 'lunas') {
                $tLunas = $tLunas ?: date('Y-m-d');
                $jKet = "Penggantian talangan " . $nama . ": " . $ket;
                if ($jurnalId) {
                    $pdo->prepare("UPDATE jurnal_kas SET tanggal=?, keterangan=?, nominal=? WHERE id=?")
                        ->execute([$tgl, $jKet, $jml, $jurnalId]);
                } else {
                    $pdo->prepare("INSERT INTO jurnal_kas (tanggal, keterangan, jenis, nominal) VALUES (?,?,'keluar',?)")
                        ->execute([$tgl, $jKet, $jml]);
                    $jurnalId = (int)$pdo->lastInsertId();
                    log_activity($pdo, 'jurnal_kas', 'tambah', $jurnalId, "Tambah pengeluaran (Talangan #$jurnalId): $jKet (Rp " . number_format($jml, 0, ',', '.') . ")", ['tanggal'=>$tgl, 'keterangan'=>$jKet, 'jenis'=>'Pengeluaran', 'nominal'=>$jml]);
                }
            } else {
                $tLunas = null;
                if ($jurnalId) {
                    $pdo->prepare("DELETE FROM jurnal_kas WHERE id=?")->execute([$jurnalId]);
                    log_activity($pdo, 'jurnal_kas', 'hapus', $jurnalId, "Batal penggantian talangan: Hapus jurnal #$jurnalId");
                    $jurnalId = null;
                }
            }

            $pdo->prepare("UPDATE kasbon SET siswa_id=?, nama=?, tanggal=?, keterangan=?, jumlah=?, status=?, tanggal_lunas=?, jurnal_id=? WHERE id=?")
                ->execute([$siswaId, $nama, $tgl, $ket, $jml, $stat, $tLunas, $jurnalId, $id]);
            $statusLabel = $stat === 'lunas' ? 'Sudah Diganti' : 'Belum Diganti';
            $ringkasan = "Edit talangan #$id: $nama (Rp " . number_format($jml, 0, ',', '.') . " - $statusLabel)";
            $detail = ['id' => $id, 'siswa_id' => $siswaId, 'nama' => $nama, 'tanggal' => $tgl, 'keterangan' => $ket, 'jumlah' => $jml, 'status' => $statusLabel];
            log_activity($pdo, 'kasbon', 'edit', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'mark_lunas_kasbon': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid id']); break; }
            // JOIN siswa untuk dapatkan nama terbaru yang terhubung
            $kasbonStmt = $pdo->prepare("
                SELECT k.jumlah, k.keterangan, k.status, k.jurnal_id,
                       COALESCE(s.nama, k.nama) AS nama
                FROM kasbon k LEFT JOIN siswa s ON s.id = k.siswa_id
                WHERE k.id=?
            ");
            $kasbonStmt->execute([$id]);
            $rowKasbon = $kasbonStmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowKasbon) { http_response_code(404); echo json_encode(['error'=>'not found']); break; }
            $nama = $rowKasbon['nama'] ?? '';
            $jml  = (float)($rowKasbon['jumlah'] ?? 0);
            $ket  = $rowKasbon['keterangan'] ?? '';
            $tgl  = date('Y-m-d');
            $jurnalId = $rowKasbon['jurnal_id'] ? (int)$rowKasbon['jurnal_id'] : null;

            if (!$jurnalId) {
                $jKet = "Penggantian talangan " . $nama . ": " . $ket;
                $pdo->prepare("INSERT INTO jurnal_kas (tanggal, keterangan, jenis, nominal) VALUES (?,?,'keluar',?)")
                    ->execute([$tgl, $jKet, $jml]);
                $jurnalId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'jurnal_kas', 'tambah', $jurnalId, "Tambah pengeluaran (Talangan #$jurnalId): $jKet (Rp " . number_format($jml, 0, ',', '.') . ")", ['tanggal'=>$tgl, 'keterangan'=>$jKet, 'jenis'=>'Pengeluaran', 'nominal'=>$jml]);
            }

            $pdo->prepare("UPDATE kasbon SET status='lunas', tanggal_lunas=?, jurnal_id=? WHERE id=?")
                ->execute([$tgl, $jurnalId, $id]);
            $ringkasan = "Tandai sudah diganti talangan #$id ($nama: Rp " . number_format($jml, 0, ',', '.') . ")";
            $detail = ['id' => $id, 'nama' => $nama, 'jumlah' => $jml, 'keterangan' => $ket, 'status' => 'Sudah Diganti', 'jurnal_id' => $jurnalId];
            log_activity($pdo, 'kasbon', 'update_status', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'mark_belum_lunas_kasbon': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid id']); break; }
            $kasbonStmt = $pdo->prepare("
                SELECT k.jumlah, k.keterangan, k.jurnal_id,
                       COALESCE(s.nama, k.nama) AS nama
                FROM kasbon k LEFT JOIN siswa s ON s.id = k.siswa_id
                WHERE k.id=?
            ");
            $kasbonStmt->execute([$id]);
            $rowKasbon = $kasbonStmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowKasbon) { http_response_code(404); echo json_encode(['error'=>'not found']); break; }
            $nama = $rowKasbon['nama'] ?? '';
            $jml  = (float)($rowKasbon['jumlah'] ?? 0);
            $ket  = $rowKasbon['keterangan'] ?? '';
            $jurnalId = $rowKasbon['jurnal_id'] ? (int)$rowKasbon['jurnal_id'] : null;

            if ($jurnalId) {
                $pdo->prepare("DELETE FROM jurnal_kas WHERE id=?")->execute([$jurnalId]);
                log_activity($pdo, 'jurnal_kas', 'hapus', $jurnalId, "Batal penggantian talangan: Hapus jurnal #$jurnalId");
            }

            $pdo->prepare("UPDATE kasbon SET status='belum_lunas', tanggal_lunas=NULL, jurnal_id=NULL WHERE id=?")
                ->execute([$id]);
            $ringkasan = "Batalkan penggantian talangan #$id ($nama: Rp " . number_format($jml, 0, ',', '.') . ")";
            $detail = ['id' => $id, 'nama' => $nama, 'jumlah' => $jml, 'keterangan' => $ket, 'status' => 'Belum Diganti'];
            log_activity($pdo, 'kasbon', 'update_status', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'delete_kasbon': {
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid id']); break; }
            $kasbonStmt = $pdo->prepare("
                SELECT k.jumlah, k.keterangan, k.jurnal_id,
                       COALESCE(s.nama, k.nama) AS nama
                FROM kasbon k LEFT JOIN siswa s ON s.id = k.siswa_id
                WHERE k.id=?
            ");
            $kasbonStmt->execute([$id]);
            $rowKasbon = $kasbonStmt->fetch(PDO::FETCH_ASSOC);
            if (!$rowKasbon) { http_response_code(404); echo json_encode(['error'=>'not found']); break; }
            $nama = $rowKasbon['nama'] ?? '';
            $jml  = (float)($rowKasbon['jumlah'] ?? 0);
            $ket  = $rowKasbon['keterangan'] ?? '';
            $jurnalId = $rowKasbon['jurnal_id'] ? (int)$rowKasbon['jurnal_id'] : null;

            if ($jurnalId) {
                $pdo->prepare("DELETE FROM jurnal_kas WHERE id=?")->execute([$jurnalId]);
                log_activity($pdo, 'jurnal_kas', 'hapus', $jurnalId, "Hapus talangan: Hapus jurnal pengeluaran #$jurnalId");
            }

            $ringkasan = "Hapus talangan #$id: $nama (Rp " . number_format($jml, 0, ',', '.') . ")";
            $detail = ['id' => $id, 'nama' => $nama, 'jumlah' => $jml, 'keterangan' => $ket];
            $pdo->prepare("DELETE FROM kasbon WHERE id=?")->execute([$id]);
            log_activity($pdo, 'kasbon', 'hapus', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'add_bms': {
            $tgl  = $_POST['tanggal'] ?? date('Y-m-d');
            $ket  = trim($_POST['keterangan'] ?? '');
            $jenis= $_POST['jenis'] ?? '';
            $jml  = (float)($_POST['jumlah'] ?? 0);
            if ($ket === '' || !in_array($jenis, ['setor','tarik'], true) || $jml <= 0) {
                http_response_code(400); echo json_encode(['error'=>'invalid']); break;
            }
            $pdo->prepare("INSERT INTO kas_bms (tanggal, keterangan, jenis, jumlah) VALUES (?,?,?,?)")
                ->execute([$tgl, $ket, $jenis, $jml]);
            $newId = (int)$pdo->lastInsertId();
            $jenisLabel = $jenis === 'setor' ? 'Setor' : 'Tarik';
            $ringkasan = "Tambah BMS ($jenisLabel) #$newId: $ket (Rp " . number_format($jml, 0, ',', '.') . ")";
            $detail = ['tanggal' => $tgl, 'keterangan' => $ket, 'jenis' => $jenisLabel, 'jumlah' => $jml];
            log_activity($pdo, 'kas_bms', 'tambah', $newId, $ringkasan, $detail);
            echo json_encode(['ok' => true, 'id' => $newId]);
            break;
        }
        case 'update_bms': {
            $id   = (int)($_POST['id'] ?? 0);
            $tgl  = $_POST['tanggal'] ?? date('Y-m-d');
            $ket  = trim($_POST['keterangan'] ?? '');
            $jenis= $_POST['jenis'] ?? '';
            $jml  = (float)($_POST['jumlah'] ?? 0);
            if ($id <= 0 || $ket === '' || !in_array($jenis, ['setor','tarik'], true) || $jml <= 0) {
                http_response_code(400); echo json_encode(['error'=>'invalid']); break;
            }
            $pdo->prepare("UPDATE kas_bms SET tanggal=?, keterangan=?, jenis=?, jumlah=? WHERE id=?")
                ->execute([$tgl, $ket, $jenis, $jml, $id]);
            $jenisLabel = $jenis === 'setor' ? 'Setor' : 'Tarik';
            $ringkasan = "Edit BMS #$id ($jenisLabel): $ket (Rp " . number_format($jml, 0, ',', '.') . ")";
            $detail = ['id' => $id, 'tanggal' => $tgl, 'keterangan' => $ket, 'jenis' => $jenisLabel, 'jumlah' => $jml];
            log_activity($pdo, 'kas_bms', 'edit', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'delete_bms': {
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid id']); break; }
            $bmsStmt = $pdo->prepare("SELECT tanggal, keterangan, jenis, jumlah FROM kas_bms WHERE id=?");
            $bmsStmt->execute([$id]);
            $rowBms = $bmsStmt->fetch(PDO::FETCH_ASSOC);
            $ket = $rowBms['keterangan'] ?? '';
            $tgl = $rowBms['tanggal'] ?? '';
            $jenis = $rowBms['jenis'] ?? '';
            $jml = (float)($rowBms['jumlah'] ?? 0);
            $jenisLabel = $jenis === 'setor' ? 'Setor' : ($jenis === 'tarik' ? 'Tarik' : $jenis);
            $ringkasan = "Hapus BMS #$id ($jenisLabel): $ket (Rp " . number_format($jml, 0, ',', '.') . ")";
            $detail = ['id' => $id, 'tanggal' => $tgl, 'keterangan' => $ket, 'jenis' => $jenisLabel, 'jumlah' => $jml];
            $pdo->prepare("DELETE FROM kas_bms WHERE id=?")->execute([$id]);
            log_activity($pdo, 'kas_bms', 'hapus', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'list_accounts': {
            $rows = $pdo->query("SELECT id, name, type, icon, is_active, sort_order FROM storage_accounts WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows);
            break;
        }
        case 'add_allocation': {
            $tgl    = $_POST['tanggal'] ?? date('Y-m-d');
            $refT   = $_POST['ref_type'] ?? '';
            $ket    = trim($_POST['keterangan'] ?? '');
            $total  = (float)($_POST['total_nominal'] ?? 0);
            $lines  = json_decode($_POST['lines'] ?? '[]', true);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) { http_response_code(400); echo json_encode(['error'=>'invalid tanggal']); break; }
            if (!in_array($refT, ['bms_setor','bms_tarik','kas_mingguan','manual'], true)) { http_response_code(400); echo json_encode(['error'=>'invalid ref_type']); break; }
            if ($total <= 0 || !is_array($lines) || empty($lines)) { http_response_code(400); echo json_encode(['error'=>'invalid total or lines']); break; }
            // Validate lines: account exists+active, nominal > 0, sum matches
            $sum = 0.0;
            $ids = [];
            foreach ($lines as $l) {
                $aid = (int)($l['account_id'] ?? 0);
                $nom = (float)($l['nominal'] ?? 0);
                if ($aid <= 0 || $nom <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid line']); break 2; }
                $ids[] = $aid;
                $sum += $nom;
            }
            if (abs($sum - $total) > 0.01) { http_response_code(400); echo json_encode(['error'=>'lines sum mismatch', 'sum'=>$sum, 'total'=>$total]); break; }
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $check = $pdo->prepare("SELECT id FROM storage_accounts WHERE id IN ($inClause) AND is_active = 1");
            $check->execute($ids);
            $found = $check->fetchAll(PDO::FETCH_COLUMN, 0);
            if (count($found) !== count(array_unique($ids))) { http_response_code(400); echo json_encode(['error'=>'unknown or inactive account']); break; }
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO storage_allocations (ref_type, tanggal, total_nominal, keterangan) VALUES (?,?,?,?)")
                    ->execute([$refT, $tgl, $total, $ket]);
                $newId = (int)$pdo->lastInsertId();
                $ins = $pdo->prepare("INSERT INTO storage_transactions (account_id, tanggal, jenis, nominal, ref_type, ref_id, keterangan) VALUES (?,?,?,?,?,?,?)");
                $lineSummary = [];
                $namaMap = [];
                $nm = $pdo->prepare("SELECT id, name FROM storage_accounts WHERE id IN ($inClause)");
                $nm->execute($ids);
                foreach ($nm->fetchAll(PDO::FETCH_ASSOC) as $r) $namaMap[(int)$r['id']] = $r['name'];
                foreach ($lines as $l) {
                    $aid = (int)$l['account_id'];
                    $nom = (float)$l['nominal'];
                    $ins->execute([$aid, $tgl, 'masuk', $nom, 'allocation', $newId, $ket]);
                    $lineSummary[] = $namaMap[$aid] . ' (Rp ' . number_format($nom, 0, ',', '.') . ')';
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'save failed']); break;
            }
            $refLabel = ['bms_setor'=>'Setor BMS','bms_tarik'=>'Tarik BMS','kas_mingguan'=>'Kas Mingguan','manual'=>'Manual'][$refT];
            $ringkasan = "Alokasi #$newId ($refLabel) Rp " . number_format($total, 0, ',', '.') . " → " . implode(', ', $lineSummary);
            $detail = ['tanggal'=>$tgl, 'ref_type'=>$refT, 'total_nominal'=>$total, 'keterangan'=>$ket, 'lines'=>$lines];
            log_activity($pdo, 'alokasi', 'tambah', $newId, $ringkasan, $detail);
            echo json_encode(['ok'=>true, 'id'=>$newId]);
            break;
        }
        case 'update_allocation': {
            $id    = (int)($_POST['id'] ?? 0);
            $tgl   = $_POST['tanggal'] ?? date('Y-m-d');
            $refT  = $_POST['ref_type'] ?? '';
            $ket   = trim($_POST['keterangan'] ?? '');
            $total = (float)($_POST['total_nominal'] ?? 0);
            $lines = json_decode($_POST['lines'] ?? '[]', true);
            if ($id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) || !in_array($refT, ['bms_setor','bms_tarik','kas_mingguan','manual'], true) || $total <= 0 || !is_array($lines) || empty($lines)) {
                http_response_code(400); echo json_encode(['error'=>'invalid']); break;
            }
            $sum = 0.0; $ids = [];
            foreach ($lines as $l) {
                $aid = (int)($l['account_id'] ?? 0);
                $nom = (float)($l['nominal'] ?? 0);
                if ($aid <= 0 || $nom <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid line']); break 2; }
                $ids[] = $aid; $sum += $nom;
            }
            if (abs($sum - $total) > 0.01) { http_response_code(400); echo json_encode(['error'=>'lines sum mismatch']); break; }
            $inClause = implode(',', array_fill(0, count($ids), '?'));
            $check = $pdo->prepare("SELECT id FROM storage_accounts WHERE id IN ($inClause) AND is_active = 1");
            $check->execute($ids);
            $found = $check->fetchAll(PDO::FETCH_COLUMN, 0);
            if (count($found) !== count(array_unique($ids))) { http_response_code(400); echo json_encode(['error'=>'unknown or inactive account']); break; }
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE storage_allocations SET ref_type=?, tanggal=?, total_nominal=?, keterangan=? WHERE id=?")
                    ->execute([$refT, $tgl, $total, $ket, $id]);
                $pdo->prepare("DELETE FROM storage_transactions WHERE ref_type='allocation' AND ref_id=?")->execute([$id]);
                $ins = $pdo->prepare("INSERT INTO storage_transactions (account_id, tanggal, jenis, nominal, ref_type, ref_id, keterangan) VALUES (?,?,?,?,?,?,?)");
                $namaMap = [];
                $nm = $pdo->prepare("SELECT id, name FROM storage_accounts WHERE id IN ($inClause)");
                $nm->execute($ids);
                foreach ($nm->fetchAll(PDO::FETCH_ASSOC) as $r) $namaMap[(int)$r['id']] = $r['name'];
                foreach ($lines as $l) {
                    $ins->execute([(int)$l['account_id'], $tgl, 'masuk', (float)$l['nominal'], 'allocation', $id, $ket]);
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'save failed']); break;
            }
            $ringkasan = "Edit alokasi #$id (Rp " . number_format($total, 0, ',', '.') . ")";
            $detail = ['id'=>$id, 'tanggal'=>$tgl, 'ref_type'=>$refT, 'total_nominal'=>$total, 'keterangan'=>$ket, 'lines'=>$lines];
            log_activity($pdo, 'alokasi', 'edit', $id, $ringkasan, $detail);
            echo json_encode(['ok'=>true]);
            break;
        }
        case 'delete_allocation': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid id']); break; }
            $stmt = $pdo->prepare("SELECT tanggal, ref_type, total_nominal, keterangan FROM storage_allocations WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { http_response_code(404); echo json_encode(['error'=>'not found']); break; }
            $pdo->beginTransaction();
            try {
                $pdo->prepare("DELETE FROM storage_transactions WHERE ref_type='allocation' AND ref_id=?")->execute([$id]);
                $pdo->prepare("DELETE FROM storage_allocations WHERE id=?")->execute([$id]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'delete failed']); break;
            }
            $refLabel = ['bms_setor'=>'Setor BMS','bms_tarik'=>'Tarik BMS','kas_mingguan'=>'Kas Mingguan','manual'=>'Manual'][$row['ref_type']] ?? $row['ref_type'];
            $ringkasan = "Hapus alokasi #$id ($refLabel Rp " . number_format($row['total_nominal'], 0, ',', '.') . ")";
            $detail = ['id'=>$id, 'tanggal'=>$row['tanggal'], 'ref_type'=>$row['ref_type'], 'total_nominal'=>(float)$row['total_nominal'], 'keterangan'=>$row['keterangan']];
            log_activity($pdo, 'alokasi', 'hapus', $id, $ringkasan, $detail);
            echo json_encode(['ok'=>true]);
            break;
        }
        case 'add_transfer': {
            $tgl   = $_POST['tanggal'] ?? date('Y-m-d');
            $from  = (int)($_POST['from_id'] ?? 0);
            $to    = (int)($_POST['to_id']   ?? 0);
            $nom   = (float)($_POST['nominal'] ?? 0);
            $ket   = trim($_POST['keterangan'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl) || $from <= 0 || $to <= 0 || $from === $to || $nom <= 0) {
                http_response_code(400); echo json_encode(['error'=>'invalid']); break;
            }
            $check = $pdo->prepare("SELECT id, name FROM storage_accounts WHERE id IN (?, ?) AND is_active = 1");
            $check->execute([$from, $to]);
            $names = [];
            foreach ($check->fetchAll(PDO::FETCH_ASSOC) as $r) $names[(int)$r['id']] = $r['name'];
            if (count($names) !== 2) { http_response_code(400); echo json_encode(['error'=>'unknown or inactive account']); break; }
            $pdo->beginTransaction();
            try {
                $outStmt = $pdo->prepare("INSERT INTO storage_transactions (account_id, tanggal, jenis, nominal, ref_type, keterangan) VALUES (?,?,?,?,?,?)");
                $outStmt->execute([$from, $tgl, 'keluar', $nom, 'transfer_out', $ket]);
                $outId = (int)$pdo->lastInsertId();
                $outStmt->execute([$to, $tgl, 'masuk', $nom, 'transfer_in', $ket]);
                $inId = (int)$pdo->lastInsertId();
                $pdo->prepare("UPDATE storage_transactions SET transfer_pair_id=? WHERE id IN (?, ?)")->execute([$outId, $outId, $inId]);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'save failed']); break;
            }
            $ringkasan = "Transfer $nom dari " . $names[$from] . " → " . $names[$to] . " (Rp " . number_format($nom, 0, ',', '.') . ")";
            $detail = ['tanggal'=>$tgl, 'from_id'=>$from, 'to_id'=>$to, 'nominal'=>$nom, 'keterangan'=>$ket, 'transfer_pair_id'=>$outId];
            log_activity($pdo, 'storage_transfer', 'tambah', $outId, $ringkasan, $detail);
            echo json_encode(['ok'=>true, 'id'=>$outId]);
            break;
        }
        case 'delete_transfer': {
            $pairId = (int)($_POST['transfer_pair_id'] ?? 0);
            if ($pairId <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid']); break; }
            $stmt = $pdo->prepare("SELECT tanggal, nominal, keterangan, account_id FROM storage_transactions WHERE id=? OR transfer_pair_id=?");
            $stmt->execute([$pairId, $pairId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) < 2) { http_response_code(404); echo json_encode(['error'=>'not found']); break; }
            $nom = (float)$rows[0]['nominal'];
            $tgl = $rows[0]['tanggal'];
            $ket = $rows[0]['keterangan'];
            $pdo->prepare("DELETE FROM storage_transactions WHERE id=? OR transfer_pair_id=?")->execute([$pairId, $pairId]);
            $ringkasan = "Hapus transfer pair #$pairId (Rp " . number_format($nom, 0, ',', '.') . ")";
            $detail = ['transfer_pair_id'=>$pairId, 'tanggal'=>$tgl, 'nominal'=>$nom, 'keterangan'=>$ket];
            log_activity($pdo, 'storage_transfer', 'hapus', $pairId, $ringkasan, $detail);
            echo json_encode(['ok'=>true]);
            break;
        }
        case 'prune_riwayat': {
            $sebelum = $_POST['sebelum'] ?? '';
            if ($sebelum === '') {
                http_response_code(400);
                echo json_encode(['error' => 'sebelum required']);
                break;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $sebelum)) {
                http_response_code(400);
                echo json_encode(['error' => 'invalid date']);
                break;
            }
            $maxDate = date('Y-m-d', strtotime('+30 days'));
            if ($sebelum > $maxDate) {
                http_response_code(400);
                echo json_encode(['error' => 'sebelum cannot be future date > 30 days']);
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM activity_log WHERE created_at < ?");
            $stmt->execute([$sebelum . ' 00:00:00']);
            $deleted = $stmt->rowCount();
            echo json_encode(['ok' => true, 'deleted' => $deleted]);
            break;
        }
        // ── Kelola Tempat Penyimpanan (Storage Accounts CRUD) ──────────────
        case 'list_storage_accounts_all': {
            // Semua akun + statistik transaksi (digunakan admin)
            $rows = $pdo->query("
                SELECT a.id, a.name, a.type, a.parent_type, a.icon, a.is_active, a.sort_order,
                       COALESCE(SUM(CASE WHEN t.jenis='masuk' THEN t.nominal ELSE -t.nominal END), 0) AS saldo,
                       COUNT(DISTINCT t.id) AS tx_count
                FROM storage_accounts a
                LEFT JOIN storage_transactions t ON t.account_id = a.id
                GROUP BY a.id
                ORDER BY a.sort_order, a.id
            ")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['saldo']    = (float)$r['saldo'];
                $r['tx_count'] = (int)$r['tx_count'];
                $r['is_active'] = (bool)(int)$r['is_active'];
            }
            unset($r);
            echo json_encode($rows);
            break;
        }
        case 'add_storage_account': {
            $name   = trim($_POST['name'] ?? '');
            $type   = trim($_POST['type'] ?? 'other');
            $ptype  = trim($_POST['parent_type'] ?? 'other');
            $icon   = trim($_POST['icon'] ?? 'fa-solid fa-vault');
            $sort   = (int)($_POST['sort_order'] ?? 99);
            if ($name === '') { http_response_code(400); echo json_encode(['error'=>'name required']); break; }
            // Cek duplikasi nama
            $dup = $pdo->prepare("SELECT id FROM storage_accounts WHERE name = ?");
            $dup->execute([$name]);
            if ($dup->fetchColumn() !== false) { http_response_code(409); echo json_encode(['error'=>'Nama akun sudah ada']); break; }
            $ins = $pdo->prepare("INSERT INTO storage_accounts (name, type, parent_type, icon, sort_order, is_active) VALUES (?,?,?,?,?,1)");
            $ins->execute([$name, $type, $ptype, $icon, $sort]);
            $newId = (int)$pdo->lastInsertId();
            log_activity($pdo, 'storage_account', 'tambah', $newId, "Tambah tempat simpan: $name ($type)", ['name'=>$name,'type'=>$type,'parent_type'=>$ptype,'icon'=>$icon]);
            echo json_encode(['ok'=>true, 'id'=>$newId]);
            break;
        }
        case 'update_storage_account': {
            $id    = (int)($_POST['id'] ?? 0);
            $name  = trim($_POST['name'] ?? '');
            $type  = trim($_POST['type'] ?? 'other');
            $ptype = trim($_POST['parent_type'] ?? 'other');
            $icon  = trim($_POST['icon'] ?? 'fa-solid fa-vault');
            $sort  = (int)($_POST['sort_order'] ?? 99);
            if ($id <= 0 || $name === '') { http_response_code(400); echo json_encode(['error'=>'id and name required']); break; }
            // Cek duplikasi nama (selain diri sendiri)
            $dup = $pdo->prepare("SELECT id FROM storage_accounts WHERE name = ? AND id <> ?");
            $dup->execute([$name, $id]);
            if ($dup->fetchColumn() !== false) { http_response_code(409); echo json_encode(['error'=>'Nama akun sudah ada']); break; }
            $pdo->prepare("UPDATE storage_accounts SET name=?, type=?, parent_type=?, icon=?, sort_order=? WHERE id=?")->execute([$name, $type, $ptype, $icon, $sort, $id]);
            log_activity($pdo, 'storage_account', 'ubah', $id, "Ubah tempat simpan #$id → $name", ['name'=>$name,'type'=>$type,'parent_type'=>$ptype,'icon'=>$icon]);
            echo json_encode(['ok'=>true]);
            break;
        }
        case 'toggle_storage_account': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid id']); break; }
            $row = $pdo->prepare("SELECT name, is_active FROM storage_accounts WHERE id=?");
            $row->execute([$id]);
            $acc = $row->fetch(PDO::FETCH_ASSOC);
            if (!$acc) { http_response_code(404); echo json_encode(['error'=>'not found']); break; }
            $newState = $acc['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE storage_accounts SET is_active=? WHERE id=?")->execute([$newState, $id]);
            $label = $newState ? 'aktifkan' : 'nonaktifkan';
            log_activity($pdo, 'storage_account', $label, $id, ucfirst($label) . " tempat simpan: {$acc['name']}", ['id'=>$id,'is_active'=>$newState]);
            echo json_encode(['ok'=>true, 'is_active'=>(bool)$newState]);
            break;
        }
        case 'delete_storage_account': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid id']); break; }
            // Cek apakah sudah pernah ada transaksi — kalau iya, tolak
            $usedCheck = $pdo->prepare("SELECT COUNT(*) FROM storage_transactions WHERE account_id = ?");
            $usedCheck->execute([$id]);
            if ($usedCheck->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode(['error'=>'Akun sudah memiliki riwayat transaksi dan tidak bisa dihapus. Nonaktifkan saja.']);
                break;
            }
            $row = $pdo->prepare("SELECT name FROM storage_accounts WHERE id=?");
            $row->execute([$id]);
            $name = $row->fetchColumn();
            if (!$name) { http_response_code(404); echo json_encode(['error'=>'not found']); break; }
            $pdo->prepare("DELETE FROM storage_accounts WHERE id=?")->execute([$id]);
            log_activity($pdo, 'storage_account', 'hapus', $id, "Hapus tempat simpan: $name", ['id'=>$id,'name'=>$name]);
            echo json_encode(['ok'=>true]);
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
