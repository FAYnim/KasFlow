DROP TABLE IF EXISTS mutasi_bank;
DROP TABLE IF EXISTS piutang_denda;
DROP TABLE IF EXISTS jurnal_kas;
DROP TABLE IF EXISTS kas_mingguan;
DROP TABLE IF EXISTS pengurus;
DROP TABLE IF EXISTS siswa;
DROP TABLE IF EXISTS config;

CREATE TABLE config (
    key_name VARCHAR(50) PRIMARY KEY,
    key_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO config (key_name, key_value) VALUES
    ('tarif_kas_mingguan', '5000'),
    ('nama_kelas', 'RPL 1'),
    ('saldo_awal', '0');

CREATE TABLE siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) UNIQUE,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE kas_mingguan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    bulan VARCHAR(20) NOT NULL,
    tahun INT NOT NULL,
    minggu_1 BOOLEAN DEFAULT FALSE,
    minggu_2 BOOLEAN DEFAULT FALSE,
    minggu_3 BOOLEAN DEFAULT FALSE,
    minggu_4 BOOLEAN DEFAULT FALSE,
    minggu_5 BOOLEAN DEFAULT FALSE,
    total_bayar DECIMAL(12,2) DEFAULT 0,
    UNIQUE KEY uniq_siswa_bulan (siswa_id, bulan, tahun),
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE jurnal_kas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('masuk','keluar') NOT NULL,
    nominal DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE piutang_denda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    status ENUM('belum_dibayar','sudah_dibayar') DEFAULT 'belum_dibayar',
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mutasi_bank (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('setor','tarik') NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pengurus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default bendahara: username=admin, password=admin123
-- Hash generated via password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO pengurus (username, password, nama) VALUES
    ('admin', '$2y$10$REPLACE_WITH_REAL_HASH', 'Bendahara RPL 1');
