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
            $tarif = (int)$pdo->query("SELECT key_value FROM config WHERE key_name='tarif_kas_mingguan'")->fetchColumn();
            $totals = [];
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
                'perubahan' => $perubahan
            ];
            log_activity($pdo, 'kas_mingguan', 'update_status', null, $ringkasan, $detail);
            echo json_encode(['ok' => true, 'totals' => $totals, 'saved' => count($changes)]);
            break;
        }
        case 'add_jurnal': {
            $tgl   = $_POST['tanggal'] ?? date('Y-m-d');
            $ket   = trim($_POST['keterangan'] ?? '');
            $jenis = $_POST['jenis'] ?? '';
            $nom   = (float)($_POST['nominal'] ?? 0);
            if ($ket === '' || !in_array($jenis, ['masuk','keluar'], true) || $nom <= 0) {
                http_response_code(400); echo json_encode(['error'=>'invalid']); break;
            }
            $pdo->prepare("INSERT INTO jurnal_kas (tanggal, keterangan, jenis, nominal) VALUES (?,?,?,?)")
                ->execute([$tgl, $ket, $jenis, $nom]);
            $newId = (int)$pdo->lastInsertId();
            $labelJenis = $jenis === 'masuk' ? 'Pemasukan' : 'Pengeluaran';
            $ringkasan = "Tambah jurnal $labelJenis #$newId: $ket (Rp " . number_format($nom, 0, ',', '.') . ")";
            $detail = ['tanggal' => $tgl, 'keterangan' => $ket, 'jenis' => $labelJenis, 'nominal' => $nom];
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
            $pdo->prepare("UPDATE jurnal_kas SET tanggal=?, keterangan=?, jenis=?, nominal=? WHERE id=?")
                ->execute([$tgl,$ket,$jenis,$nom,$id]);
            $labelJenis = $jenis === 'masuk' ? 'Pemasukan' : 'Pengeluaran';
            $ringkasan = "Edit jurnal #$id ($labelJenis): $ket (Rp " . number_format($nom, 0, ',', '.') . ")";
            $detail = ['id' => $id, 'tanggal' => $tgl, 'keterangan' => $ket, 'jenis' => $labelJenis, 'nominal' => $nom];
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
            $detail = ['id' => $id, 'tanggal' => $tgl, 'keterangan' => $ket, 'jenis' => $labelJenis, 'nominal' => $nom];
            $pdo->prepare("DELETE FROM jurnal_kas WHERE id=?")->execute([$id]);
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
            $nama  = trim($_POST['nama'] ?? '');
            $tgl   = $_POST['tanggal'] ?? date('Y-m-d');
            $ket   = trim($_POST['keterangan'] ?? '');
            $jml   = (float)($_POST['jumlah'] ?? 0);
            $stat  = $_POST['status'] ?? 'belum_lunas';
            if ($nama === '' || $jml <= 0 || !in_array($stat, ['belum_lunas','lunas'], true)) {
                http_response_code(400); echo json_encode(['error' => 'invalid']); break;
            }
            $tLunas = ($stat === 'lunas') ? date('Y-m-d') : null;
            $pdo->prepare("INSERT INTO kasbon (nama, tanggal, keterangan, jumlah, status, tanggal_lunas) VALUES (?,?,?,?,?,?)")
                ->execute([$nama, $tgl, $ket, $jml, $stat, $tLunas]);
            $newId = (int)$pdo->lastInsertId();
            $statusLabel = $stat === 'lunas' ? 'Lunas' : 'Belum Lunas';
            $ringkasan = "Tambah kasbon #$newId: $nama (Rp " . number_format($jml, 0, ',', '.') . " - $statusLabel)";
            $detail = ['nama' => $nama, 'tanggal' => $tgl, 'keterangan' => $ket, 'jumlah' => $jml, 'status' => $statusLabel];
            log_activity($pdo, 'kasbon', 'tambah', $newId, $ringkasan, $detail);
            echo json_encode(['ok' => true, 'id' => $newId]);
            break;
        }
        case 'update_kasbon': {
            $id   = (int)($_POST['id'] ?? 0);
            $nama = trim($_POST['nama'] ?? '');
            $tgl  = $_POST['tanggal'] ?? date('Y-m-d');
            $ket  = trim($_POST['keterangan'] ?? '');
            $jml  = (float)($_POST['jumlah'] ?? 0);
            $stat = $_POST['status'] ?? 'belum_lunas';
            if ($id <= 0 || $nama === '' || $jml <= 0 || !in_array($stat, ['belum_lunas','lunas'], true)) {
                http_response_code(400); echo json_encode(['error' => 'invalid']); break;
            }
            if ($stat === 'lunas') {
                $cur = $pdo->prepare("SELECT tanggal_lunas FROM kasbon WHERE id=?");
                $cur->execute([$id]);
                $tLunas = $cur->fetchColumn() ?: date('Y-m-d');
            } else {
                $tLunas = null;
            }
            $pdo->prepare("UPDATE kasbon SET nama=?, tanggal=?, keterangan=?, jumlah=?, status=?, tanggal_lunas=? WHERE id=?")
                ->execute([$nama, $tgl, $ket, $jml, $stat, $tLunas, $id]);
            $statusLabel = $stat === 'lunas' ? 'Lunas' : 'Belum Lunas';
            $ringkasan = "Edit kasbon #$id: $nama (Rp " . number_format($jml, 0, ',', '.') . " - $statusLabel)";
            $detail = ['id' => $id, 'nama' => $nama, 'tanggal' => $tgl, 'keterangan' => $ket, 'jumlah' => $jml, 'status' => $statusLabel];
            log_activity($pdo, 'kasbon', 'edit', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'mark_lunas_kasbon': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid id']); break; }
            $kasbonStmt = $pdo->prepare("SELECT nama, jumlah, keterangan FROM kasbon WHERE id=?");
            $kasbonStmt->execute([$id]);
            $rowKasbon = $kasbonStmt->fetch(PDO::FETCH_ASSOC);
            $nama = $rowKasbon['nama'] ?? '';
            $jml = (float)($rowKasbon['jumlah'] ?? 0);
            $ket = $rowKasbon['keterangan'] ?? '';
            $pdo->prepare("UPDATE kasbon SET status='lunas', tanggal_lunas=? WHERE id=?")
                ->execute([date('Y-m-d'), $id]);
            $ringkasan = "Tandai lunas kasbon #$id ($nama: Rp " . number_format($jml, 0, ',', '.') . ")";
            $detail = ['id' => $id, 'nama' => $nama, 'jumlah' => $jml, 'keterangan' => $ket, 'status' => 'Lunas'];
            log_activity($pdo, 'kasbon', 'update_status', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'mark_belum_lunas_kasbon': {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid id']); break; }
            $kasbonStmt = $pdo->prepare("SELECT nama, jumlah, keterangan FROM kasbon WHERE id=?");
            $kasbonStmt->execute([$id]);
            $rowKasbon = $kasbonStmt->fetch(PDO::FETCH_ASSOC);
            $nama = $rowKasbon['nama'] ?? '';
            $jml = (float)($rowKasbon['jumlah'] ?? 0);
            $ket = $rowKasbon['keterangan'] ?? '';
            $pdo->prepare("UPDATE kasbon SET status='belum_lunas', tanggal_lunas=NULL WHERE id=?")
                ->execute([$id]);
            $ringkasan = "Tandai belum lunas kasbon #$id ($nama: Rp " . number_format($jml, 0, ',', '.') . ")";
            $detail = ['id' => $id, 'nama' => $nama, 'jumlah' => $jml, 'keterangan' => $ket, 'status' => 'Belum Lunas'];
            log_activity($pdo, 'kasbon', 'update_status', $id, $ringkasan, $detail);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'delete_kasbon': {
            $id = (int)($_REQUEST['id'] ?? 0);
            if ($id <= 0) { http_response_code(400); echo json_encode(['error' => 'invalid id']); break; }
            $kasbonStmt = $pdo->prepare("SELECT nama, jumlah, keterangan FROM kasbon WHERE id=?");
            $kasbonStmt->execute([$id]);
            $rowKasbon = $kasbonStmt->fetch(PDO::FETCH_ASSOC);
            $nama = $rowKasbon['nama'] ?? '';
            $jml = (float)($rowKasbon['jumlah'] ?? 0);
            $ket = $rowKasbon['keterangan'] ?? '';
            $ringkasan = "Hapus kasbon #$id: $nama (Rp " . number_format($jml, 0, ',', '.') . ")";
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
        default:
            http_response_code(400);
            echo json_encode(['error' => 'unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
