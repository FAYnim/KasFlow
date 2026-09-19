<?php
@session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../lib/activity_log.php';
require_once __DIR__ . '/../lib/FinanceEngine.php';

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
            // Ambil daftar nama siswa untuk rincian antrean dan log
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

            // Hitung delta centang baru (masuk) dan centang batal (keluar/koreksi) beserta rincian siswanya
            $newCheckedCount = 0;
            $newUncheckedCount = 0;
            $studentsDetail = [];
            $summaryItems = [];
            $perubahan = [];

            foreach ($changes as $c) {
                $sid = (int)($c['siswa_id'] ?? 0); $m = (int)($c['minggu'] ?? 0); $chk = (int)($c['checked'] ?? 0);
                if ($sid <= 0 || !in_array($m, [1,2,3,4,5], true)) continue;
                $prev = $prevStates[$sid][$m] ?? 0;
                $namaSiswa = $namaMap[$sid] ?? ("#" . $sid);

                if ($chk === 1 && $prev === 0) {
                    $newCheckedCount++;
                    $studentsDetail[] = [
                        'siswa_id' => $sid,
                        'nama'     => $namaSiswa,
                        'minggu'   => $m,
                        'action'   => 'bayar',
                        'nominal'  => $tarif
                    ];
                } elseif ($chk === 0 && $prev === 1) {
                    $newUncheckedCount++;
                    $studentsDetail[] = [
                        'siswa_id' => $sid,
                        'nama'     => $namaSiswa,
                        'minggu'   => $m,
                        'action'   => 'batal',
                        'nominal'  => -$tarif
                    ];
                }

                $perubahan[] = [
                    'siswa_id' => $sid,
                    'nama'     => $namaSiswa,
                    'minggu'   => $m,
                    'status'   => $chk ? 'lunas' : 'batal'
                ];
                $summaryItems[] = "$namaSiswa (M$m: " . ($chk ? 'Lunas' : 'Batal') . ")";
            }
            $netUnits = $newCheckedCount - $newUncheckedCount;
            $netNominal = $netUnits * $tarif;
            $queueId = null;

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

                // Jika ada perubahan nominal bersih, otomatis catat ke antrean Uncategorized Cashflow
                if ($netNominal != 0) {
                    // Buat ringkasan nama siswa untuk keterangan transaksi
                    $studentNamesSummary = [];
                    foreach ($studentsDetail as $sd) {
                        $studentNamesSummary[] = "{$sd['nama']} (M{$sd['minggu']})";
                    }
                    $studentNamesStr = "";
                    if (!empty($studentNamesSummary)) {
                        $firstTwo = array_slice($studentNamesSummary, 0, 2);
                        $studentNamesStr = ": " . implode(', ', $firstTwo);
                        if (count($studentNamesSummary) > 2) {
                            $studentNamesStr .= " + " . (count($studentNamesSummary) - 2) . " lainnya";
                        }
                    }

                    $qKet = $netNominal > 0
                        ? "Penerimaan Kas $bulan $tahun$studentNamesStr"
                        : "Koreksi Pembatalan Kas $bulan $tahun$studentNamesStr";

                    $qDetail = json_encode([
                        'bulan' => $bulan,
                        'tahun' => $tahun,
                        'new_checked' => $newCheckedCount,
                        'new_unchecked' => $newUncheckedCount,
                        'net_nominal' => $netNominal,
                        'students' => $studentsDetail
                    ]);
                    $insQ = $pdo->prepare("INSERT INTO kas_mingguan_queue (bulan, tahun, nominal, keterangan, detail, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $insQ->execute([$bulan, $tahun, $netNominal, $qKet, $qDetail]);
                    $queueId = (int)$pdo->lastInsertId();
                }

                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                http_response_code(500); echo json_encode(['error'=>'save failed: ' . $e->getMessage()]); break;
            }
            $stmt = $pdo->prepare("SELECT siswa_id, total_bayar FROM kas_mingguan WHERE bulan=? AND tahun=?");
            $stmt->execute([$bulan, $tahun]);
            foreach ($stmt as $r) $totals[(int)$r['siswa_id']] = (float)$r['total_bayar'];

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
                'net_nominal' => $netNominal,
                'queue_id' => $queueId,
            ];
            log_activity($pdo, 'kas_mingguan', 'update_status', null, $ringkasan, $detail);
            echo json_encode(['ok' => true, 'totals' => $totals, 'saved' => count($changes), 'net_nominal' => $netNominal, 'queue_id' => $queueId]);
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
        }        // ── CENTRALIZED MONEY TRACKER ENDPOINTS ───────────────────
        case 'get_finance_overview': {
            $summary = FinanceEngine::getSummary($pdo);
            $categories = FinanceEngine::getCategories($pdo);
            $pendingQueue = FinanceEngine::getKasQueue($pdo, 'pending');
            echo json_encode([
                'ok'            => true,
                'summary'       => $summary,
                'accounts'      => $summary['accounts'],
                'categories'    => $categories,
                'pending_queue' => $pendingQueue
            ]);
            break;
        }
        case 'get_transactions': {
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = !empty($_GET['limit']) ? max(5, min(100, (int)$_GET['limit'])) : 15;
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
            $totalCount   = FinanceEngine::countTransactions($pdo, $filters);
            $totalPages   = $totalCount > 0 ? (int)ceil($totalCount / $limit) : 1;
            echo json_encode([
                'ok'           => true,
                'data'         => $rows,
                'transactions' => $rows,
                'pagination'   => [
                    'page'          => $page,
                    'limit'         => $limit,
                    'total_records' => $totalCount,
                    'total_pages'   => $totalPages,
                ],
            ]);
            break;
        }
        case 'add_transaction': {
            $adminUser = $_SESSION['admin_user'] ?? $_SESSION['admin_username'] ?? 'admin';
            $data = [
                'date'          => $_POST['date'] ?? date('Y-m-d'),
                'type'          => $_POST['type'] ?? '',
                'account_id'    => $_POST['account_id'] ?? 0,
                'to_account_id' => $_POST['to_account_id'] ?? null,
                'category_id'   => $_POST['category_id'] ?? null,
                'amount'        => (float)($_POST['amount'] ?? 0),
                'description'   => trim($_POST['description'] ?? ''),
                'ref_type'      => 'manual',
                'created_by'    => $adminUser,
            ];
            try {
                $txId = FinanceEngine::addTransaction($pdo, $data);
                $typeLabel = $data['type'] === 'income' ? 'Pemasukan' : ($data['type'] === 'expense' ? 'Pengeluaran' : 'Transfer');
                $ringkasan = "Catat $typeLabel #$txId: {$data['description']} (Rp " . number_format($data['amount'], 0, ',', '.') . ")";
                log_activity($pdo, 'cashflow', 'tambah', $txId, $ringkasan, $data);
                echo json_encode(['ok' => true, 'id' => $txId]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
        }
        case 'update_transaction': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid id']); break; }
            $data = [
                'date'          => $_POST['date'] ?? date('Y-m-d'),
                'type'          => $_POST['type'] ?? '',
                'account_id'    => $_POST['account_id'] ?? 0,
                'to_account_id' => $_POST['to_account_id'] ?? null,
                'category_id'   => $_POST['category_id'] ?? null,
                'amount'        => (float)($_POST['amount'] ?? 0),
                'description'   => trim($_POST['description'] ?? ''),
            ];
            try {
                FinanceEngine::updateTransaction($pdo, $id, $data);
                log_activity($pdo, 'cashflow', 'edit', $id, "Edit transaksi #$id (Rp " . number_format($data['amount'], 0, ',', '.') . ")", $data);
                echo json_encode(['ok' => true]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
        }
        case 'delete_transaction': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'invalid id']); break; }
            FinanceEngine::deleteTransaction($pdo, $id);
            log_activity($pdo, 'cashflow', 'hapus', $id, "Hapus transaksi #$id");
            echo json_encode(['ok' => true]);
            break;
        }
        case 'manage_account': {
            $sub = $_POST['sub_action'] ?? 'add';
            if ($sub === 'add') {
                $name = trim($_POST['name'] ?? '');
                $type = $_POST['type'] ?? 'other';
                $icon = trim($_POST['icon'] ?? 'fa-solid fa-wallet');
                $init = (float)($_POST['initial_balance'] ?? 0);
                $sort = (int)($_POST['sort_order'] ?? 0);
                if ($name === '') { http_response_code(400); echo json_encode(['error'=>'Nama akun wajib']); break; }
                $pdo->prepare("INSERT INTO accounts (name, type, icon, initial_balance, sort_order, is_active) VALUES (?, ?, ?, ?, ?, 1)")
                    ->execute([$name, $type, $icon, $init, $sort]);
                $accId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'account', 'tambah', $accId, "Tambah dompet/akun: $name");
                echo json_encode(['ok' => true, 'id' => $accId]);
            } elseif ($sub === 'edit') {
                $id   = (int)($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $type = $_POST['type'] ?? 'other';
                $icon = trim($_POST['icon'] ?? 'fa-solid fa-wallet');
                $init = (float)($_POST['initial_balance'] ?? 0);
                $sort = (int)($_POST['sort_order'] ?? 0);
                if ($id <= 0 || $name === '') { http_response_code(400); echo json_encode(['error'=>'invalid']); break; }
                $pdo->prepare("UPDATE accounts SET name=?, type=?, icon=?, initial_balance=?, sort_order=? WHERE id=?")
                    ->execute([$name, $type, $icon, $init, $sort, $id]);
                log_activity($pdo, 'account', 'edit', $id, "Edit dompet/akun: $name");
                echo json_encode(['ok' => true]);
            } elseif ($sub === 'toggle_active') {
                $id = (int)($_POST['id'] ?? 0);
                $cur = (int)$pdo->query("SELECT is_active FROM accounts WHERE id = $id")->fetchColumn();
                $new = $cur ? 0 : 1;
                $pdo->prepare("UPDATE accounts SET is_active=? WHERE id=?")->execute([$new, $id]);
                echo json_encode(['ok' => true, 'is_active' => (bool)$new]);
            } elseif ($sub === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $used = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE account_id = $id OR to_account_id = $id")->fetchColumn();
                if ($used > 0) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Akun memiliki riwayat transaksi dan tidak bisa dihapus. Silakan nonaktifkan.']);
                    break;
                }
                $pdo->prepare("DELETE FROM accounts WHERE id=?")->execute([$id]);
                log_activity($pdo, 'account', 'hapus', $id, "Hapus akun #$id");
                echo json_encode(['ok' => true]);
            }
            break;
        }
        case 'manage_category': {
            $sub = $_POST['sub_action'] ?? 'add';
            if ($sub === 'add') {
                $name  = trim($_POST['name'] ?? '');
                $type  = $_POST['type'] ?? 'both';
                $icon  = trim($_POST['icon'] ?? 'fa-solid fa-tag');
                $color = trim($_POST['color'] ?? '#3b82f6');
                if ($name === '') { http_response_code(400); echo json_encode(['error'=>'Nama kategori wajib']); break; }
                $pdo->prepare("INSERT INTO categories (name, type, icon, color, is_active) VALUES (?, ?, ?, ?, 1)")
                    ->execute([$name, $type, $icon, $color]);
                $catId = (int)$pdo->lastInsertId();
                log_activity($pdo, 'category', 'tambah', $catId, "Tambah kategori: $name");
                echo json_encode(['ok' => true, 'id' => $catId]);
            } elseif ($sub === 'edit') {
                $id    = (int)($_POST['id'] ?? 0);
                $name  = trim($_POST['name'] ?? '');
                $type  = $_POST['type'] ?? 'both';
                $icon  = trim($_POST['icon'] ?? 'fa-solid fa-tag');
                $color = trim($_POST['color'] ?? '#3b82f6');
                if ($id <= 0 || $name === '') { http_response_code(400); echo json_encode(['error'=>'invalid']); break; }
                $pdo->prepare("UPDATE categories SET name=?, type=?, icon=?, color=? WHERE id=?")
                    ->execute([$name, $type, $icon, $color, $id]);
                log_activity($pdo, 'category', 'edit', $id, "Edit kategori: $name");
                echo json_encode(['ok' => true]);
            } elseif ($sub === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                $used = (int)$pdo->query("SELECT COUNT(*) FROM transactions WHERE category_id = $id")->fetchColumn();
                if ($used > 0) {
                    $pdo->prepare("UPDATE categories SET is_active=0 WHERE id=?")->execute([$id]);
                    echo json_encode(['ok' => true, 'action' => 'deactivated']);
                    break;
                }
                $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
                log_activity($pdo, 'category', 'hapus', $id, "Hapus kategori #$id");
                echo json_encode(['ok' => true]);
            }
            break;
        }
        case 'get_kas_queue': {
            $status = $_GET['status'] ?? 'pending';
            $rows = FinanceEngine::getKasQueue($pdo, $status ?: null);
            echo json_encode(['ok' => true, 'queue' => $rows]);
            break;
        }
        case 'claim_kas_queue': {
            $queueId    = (int)($_POST['queue_id'] ?? 0);
            $accountId  = (int)($_POST['account_id'] ?? 0);
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $customDesc = trim($_POST['description'] ?? '');
            $adminUser  = $_SESSION['admin_user'] ?? $_SESSION['admin_username'] ?? 'admin';

            if ($queueId <= 0 || $accountId <= 0 || $categoryId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Antrean, akun, dan kategori harus dipilih']);
                break;
            }

            try {
                $txId = FinanceEngine::claimKasQueue($pdo, $queueId, $accountId, $categoryId, $adminUser, $customDesc ?: null);
                log_activity($pdo, 'cashflow', 'claim_kas', $txId, "Bukukan Kas Mingguan antrean #$queueId ke transaksi #$txId");
                echo json_encode(['ok' => true, 'transaction_id' => $txId]);
            } catch (Throwable $e) {
                http_response_code(400);
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;
        }
        case 'list_accounts': {
            $rows = FinanceEngine::getAccountsWithBalances($pdo, true);
            echo json_encode($rows);
            break;
        }
        case 'get_config': {
            $rows = $pdo->query("SELECT key_name, key_value FROM config")->fetchAll(PDO::FETCH_KEY_PAIR);
            echo json_encode([
                'ok'     => true,
                'config' => [
                    'nama_kelas'           => $rows['nama_kelas'] ?? 'RPL 1',
                    'tarif_kas_mingguan'   => (int)($rows['tarif_kas_mingguan'] ?? 5000),
                    'saldo_awal'           => (float)($rows['saldo_awal'] ?? 0),
                ],
            ]);
            break;
        }
        case 'update_config': {
            $namaKelas  = trim($_POST['nama_kelas'] ?? '');
            $tarif      = (int)($_POST['tarif_kas_mingguan'] ?? -1);
            $saldoAwal  = (float)($_POST['saldo_awal'] ?? -1);

            $errors = [];
            if ($namaKelas === '') $errors[] = 'Nama kelas tidak boleh kosong.';
            if (strlen($namaKelas) > 50) $errors[] = 'Nama kelas maksimal 50 karakter.';
            if ($tarif < 0) $errors[] = 'Tarif kas tidak boleh negatif.';
            if ($saldoAwal < 0) $errors[] = 'Saldo awal tidak boleh negatif.';

            if ($errors) {
                http_response_code(422);
                echo json_encode(['error' => implode(' ', $errors)]);
                break;
            }

            $upsert = $pdo->prepare(
                "INSERT INTO config (key_name, key_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)"
            );

            // Baca nilai lama untuk log
            $oldRows = $pdo->query("SELECT key_name, key_value FROM config")->fetchAll(PDO::FETCH_KEY_PAIR);

            $upsert->execute(['nama_kelas',         $namaKelas]);
            $upsert->execute(['tarif_kas_mingguan',  (string)$tarif]);
            $upsert->execute(['saldo_awal',           number_format($saldoAwal, 2, '.', '')]);

            log_activity($pdo, 'config', 'update', null,
                "Memperbarui konfigurasi kelas: nama=\"$namaKelas\", tarif={$tarif}, saldo_awal={$saldoAwal}",
                [
                    'sebelum' => [
                        'nama_kelas'         => $oldRows['nama_kelas'] ?? null,
                        'tarif_kas_mingguan' => $oldRows['tarif_kas_mingguan'] ?? null,
                        'saldo_awal'         => $oldRows['saldo_awal'] ?? null,
                    ],
                    'sesudah' => [
                        'nama_kelas'         => $namaKelas,
                        'tarif_kas_mingguan' => $tarif,
                        'saldo_awal'         => $saldoAwal,
                    ],
                ]
            );

            echo json_encode(['ok' => true, 'config' => [
                'nama_kelas'         => $namaKelas,
                'tarif_kas_mingguan' => $tarif,
                'saldo_awal'         => $saldoAwal,
            ]]);
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
