DROP TABLE IF EXISTS storage_transactions;
DROP TABLE IF EXISTS storage_allocations;
DROP TABLE IF EXISTS storage_accounts;
DROP TABLE IF EXISTS kas_bms;
DROP TABLE IF EXISTS jurnal_kas;
DROP TABLE IF EXISTS kas_mingguan;
DROP TABLE IF EXISTS kasbon;
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
    absen VARCHAR(20) UNIQUE,
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

CREATE TABLE kas_bms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('setor','tarik') NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kas_bms_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pengurus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default bendahara accounts. Hash via password_hash($password, PASSWORD_DEFAULT).
-- To rotate: run `php database/seeds/admin.php`.
INSERT INTO pengurus (username, password, nama) VALUES
    ('ammar', '$2y$12$zcP0hWd75iWOgRp3jqJZ6eDmHaQlE3pDh3aePBvpdj88ICNO.zwym', 'Ammar'),
    ('faris', '$2y$12$79xliM6TEho/KXgQVUw63u87qNmXtvE/LyppHLt7NxvIv6KuJuvfe', 'Faris');

CREATE TABLE kasbon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    status ENUM('belum_lunas','lunas') DEFAULT 'belum_lunas',
    tanggal_lunas DATE NULL,
    jurnal_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_kasbon_tanggal (tanggal),
    INDEX idx_kasbon_status (status),
    FOREIGN KEY (jurnal_id) REFERENCES jurnal_kas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modul VARCHAR(50) NOT NULL,
    aksi VARCHAR(20) NOT NULL,
    entitas_id INT NULL,
    ringkasan VARCHAR(500) NOT NULL,
    detail JSON NULL,
    admin_username VARCHAR(50) NULL,
    admin_nama VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_log_created (created_at),
    INDEX idx_activity_log_modul_aksi (modul, aksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE storage_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'other',
    parent_type VARCHAR(20) NOT NULL DEFAULT 'other',
    icon VARCHAR(50) NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE storage_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_id INT NOT NULL,
    tanggal DATE NOT NULL,
    jenis ENUM('masuk','keluar') NOT NULL,
    nominal DECIMAL(12,2) NOT NULL,
    ref_type ENUM('allocation','transfer_in','transfer_out','manual') NOT NULL,
    ref_id INT NULL,
    transfer_pair_id INT NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_st_account (account_id),
    INDEX idx_st_tanggal (tanggal),
    INDEX idx_st_ref (ref_type, ref_id),
    FOREIGN KEY (account_id) REFERENCES storage_accounts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE storage_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ref_type ENUM('bms_setor','bms_tarik','kas_mingguan','manual') NOT NULL,
    ref_id INT NULL,
    tanggal DATE NOT NULL,
    total_nominal DECIMAL(12,2) NOT NULL,
    keterangan TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sa_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO storage_accounts (name, type, parent_type, icon, sort_order) VALUES
    ('Cash',          'cash',          'cash',    'fa-solid fa-wallet',                1),
    ('DANA',          'ewallet_dana',  'ewallet', 'fa-solid fa-mobile-screen',         2),
    ('Gopay',         'ewallet_gopay', 'ewallet', 'fa-solid fa-mobile-screen-button',  3),
    ('E-Wallet Lain', 'ewallet',       'ewallet', 'fa-solid fa-credit-card',           4),
    ('SeaBank',       'bank_seabank',  'bank',    'fa-solid fa-building-columns',      5),
    ('Bank Mandiri',  'bank_mandiri',  'bank',    'fa-solid fa-building-columns',      6),
    ('Bank Lain',     'bank',          'bank',    'fa-solid fa-landmark',              7);
