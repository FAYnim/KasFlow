<?php
@session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

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
            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
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
            echo json_encode(['ok' => true, 'total_bayar' => (float)$row['total_bayar']]);
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
            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
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
            echo json_encode(['ok' => true]);
            break;
        }
        case 'delete_jurnal': {
            $id = (int)($_REQUEST['id'] ?? 0);
            $pdo->prepare("DELETE FROM jurnal_kas WHERE id=?")->execute([$id]);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'delete_siswa': {
            $id = (int)($_REQUEST['id'] ?? 0);
            $pdo->prepare("DELETE FROM siswa WHERE id=?")->execute([$id]);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'add_piutang': {
            $siswa_id = (int)$_POST['siswa_id'];
            $tgl      = $_POST['tanggal'] ?? date('Y-m-d');
            $ket      = trim($_POST['keterangan'] ?? '');
            $jumlah   = (float)$_POST['jumlah'];
            if ($siswa_id <= 0 || $ket === '' || $jumlah <= 0) {
                http_response_code(400); echo json_encode(['error'=>'invalid']); break;
            }
            $pdo->prepare("INSERT INTO piutang_denda (siswa_id, tanggal, keterangan, jumlah) VALUES (?,?,?,?)")
                ->execute([$siswa_id, $tgl, $ket, $jumlah]);
            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
            break;
        }
        case 'update_piutang_status': {
            $id = (int)$_POST['id'];
            $st = $_POST['status'] ?? 'sudah_dibayar';
            if (!in_array($st, ['belum_dibayar','sudah_dibayar'], true)) { http_response_code(400); break; }
            $pdo->prepare("UPDATE piutang_denda SET status=? WHERE id=?")->execute([$st, $id]);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'add_bank': {
            $tgl  = $_POST['tanggal'] ?? date('Y-m-d');
            $ket  = trim($_POST['keterangan'] ?? '');
            $jenis= $_POST['jenis'] ?? '';
            $jml  = (float)$_POST['jumlah'];
            if ($ket === '' || !in_array($jenis, ['setor','tarik'], true) || $jml <= 0) {
                http_response_code(400); echo json_encode(['error'=>'invalid']); break;
            }
            $pdo->prepare("INSERT INTO mutasi_bank (tanggal, keterangan, jenis, jumlah) VALUES (?,?,?,?)")
                ->execute([$tgl, $ket, $jenis, $jml]);
            echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
            break;
        }
        case 'delete_bank': {
            $id = (int)$_REQUEST['id'];
            $pdo->prepare("DELETE FROM mutasi_bank WHERE id=?")->execute([$id]);
            echo json_encode(['ok' => true]);
            break;
        }
        case 'list_siswa': {
            $rows = $pdo->query("SELECT id, absen, nama FROM siswa ORDER BY nama ASC")->fetchAll();
            echo json_encode($rows);
            break;
        }
        case 'update_siswa': {
            $id    = (int)$_POST['id'];
            $absen = trim($_POST['absen'] ?? '');
            $nama  = trim($_POST['nama'] ?? '');
            $pdo->prepare("UPDATE siswa SET absen=?, nama=? WHERE id=?")->execute([$absen ?: null, $nama, $id]);
            echo json_encode(['ok' => true]);
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
