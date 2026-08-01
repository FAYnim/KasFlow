# Document Requirement Product (PRD): Sistem Keuangan Kelas RPL 1

## 1. Ringkasan Proyek

Sistem pencatatan dan transparansi keuangan kelas RPL 1. Terdiri dari 2 aplikasi web:

* **App 1 (Web Publik)**: Single Page Application (SPA), *read-only*, akses tanpa login buat siswa/wali murid.
* **App 2 (Web Admin)**: Dashboard manajemen keuangan khusus 1 akun Bendahara (*full access*).

---

## 2. Tech Stack & Dependensi

* **Frontend**: HTML5, Tailwind CSS (CDN), jQuery 3.7.1 (CDN), Chart.js (CDN - grafik jurnal).
* **Backend**: PHP Native (PDO/MySQLi).
* **Database**: MySQL.

---

## 3. User & Hak Akses

| Role | Auth | Akses Fitur |
| --- | --- | --- |
| **Publik (Siswa/Wali)** | Tanpa Login | View Kas Mingguan, View Jurnal, View Denda, View Bank, View Chart. |
| **Admin (Bendahara)** | Session Login (1 Akun) | All View + Input/Edit/Hapus Data + Input Siswa Manual + Ekspor PDF/Excel. |

---

## 4. Schema Database (MySQL)

```sql
-- Tabel Config (Tarif Kas Tetap)
CREATE TABLE config (
    key_name VARCHAR(50) PRIMARY KEY,
    key_value VARCHAR(255) NOT NULL
);
INSERT INTO config (key_name, key_value) VALUES ('tarif_kas_mingguan', '5000');

-- Tabel Siswa
CREATE TABLE siswa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nis VARCHAR(20) UNIQUE,
    nama VARCHAR(100) NOT NULL
);

-- Tabel Kas Mingguan
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
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);

-- Tabel Jurnal Arus Kas
CREATE TABLE jurnal_kas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('masuk', 'keluar') NOT NULL,
    nominal DECIMAL(12,2) NOT NULL
);

-- Tabel Piutang / Denda
CREATE TABLE piutang_denda (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siswa_id INT NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    status ENUM('belum_dibayar', 'sudah_dibayar') DEFAULT 'belum_dibayar',
    FOREIGN KEY (siswa_id) REFERENCES siswa(id) ON DELETE CASCADE
);

-- Tabel Mutasi Bank
CREATE TABLE mutasi_bank (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('setor', 'tarik') NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL
);

-- Tabel Pengurus (Admin)
CREATE TABLE pengurus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL
);

```

---

## 5. Spesifikasi Fitur App 1: Web Publik (SPA)

### Tampilan & Komponen UI

1. **Navbar Fixed**: Logo kelas + Tombol Hamburger (`#btn-hamburger`).
2. **Sidebar Slide-Over**:
* Tampil/sembunyi via toggle class `-translate-x-full` jQuery.
* Menu navigasi: Dashboard, Kas Mingguan, Jurnal Kas, Piutang & Denda, Mutasi Bank.


3. **Container Content (SPA Mechanism)**:
* Pindah tab manipulasi class `.hidden` DOM via jQuery `data-tab`.
* Tanpa *page reload*.



### Detail Modul Tab

* **Tab Dashboard**:
* 4 Summary Cards: Total Kas Terkumpul, Cash on Hand, Cash in Bank, Total Denda Unpaid.


* **Tab Kas Mingguan**:
* Dropdown filter bulan & tahun.
* Search bar nama siswa (filter tabel client-side/AJAX).
* Tabel status Minggu 1-5 (Ikon Centang/Silang) + Total Bayar per Siswa.


* **Tab Jurnal Kas**:
* *Line Chart* (Chart.js): Tren akumulasi saldo.
* *Donut Chart* (Chart.js): Rasio Uang Masuk vs Uang Keluar.
* Tabel riwayat transaksi + filter jenis/tanggal.


* **Tab Piutang & Denda**:
* Tabel tagihan personal. Badge merah (*Belum Dibayar*) & hijau (*Sudah Dibayar*).


* **Tab Mutasi Bank**:
* Tabel riwayat setor & tarik uang kas ke rekening.



---

## 6. Spesifikasi Fitur App 2: Web Admin / Bendahara

### Tampilan & Komponen UI

1. **Halaman Login (`login.php`)**: Form Username & Password.
2. **Sidebar Admin Fixed**: Dashboard, Kelola Siswa, Input Kas, Kelola Jurnal, Kelola Denda, Mutasi Bank, Ekspor Laporan.

### Detail Modul Management

* **Modul Kelola Siswa**:
* Form input manual nama & NIS siswa baru.
* Tabel master siswa (Edit / Hapus).


* **Modul Input Kas Mingguan**:
* Grid tabel siswa dengan **Checkbox Interaktif**.
* Klik checkbox -> AJAX `POST` otomatis hitung `total_bayar` berdasarkan tarif tetap di tabel `config`.


* **Modul Kelola Jurnal Arus Kas**:
* Modal Form Tambah Transaksi (Tanggal, Keterangan, Jenis `masuk`/`keluar`, Nominal).
* Action Edit & Delete transaksi via AJAX.


* **Modul Kelola Piutang & Denda**:
* Form buat tagihan denda baru per siswa.
* Button "Tandai Lunas" -> AJAX `POST` ubah status ke `sudah_dibayar`.


* **Modul Mutasi Bank**:
* Form pencatatan Setor/Tarik tunai ke bank.


* **Modul Ekspor Laporan (Khusus Admin)**:
* Tombol Cetak PDF (`window.print()` CSS print style) / Download CSV-Excel.
* Filter rentang tanggal laporan.



---

## 7. Arsitektur API Backend (PHP)

### `api_public.php` (Read-Only JSON)

* `GET ?action=get_summary` -> Return totals summary cards.
* `GET ?action=get_kas&bulan=X&tahun=Y` -> Return status kas siswa.
* `GET ?action=get_jurnal` -> Return data transaksi + data chart.
* `GET ?action=get_piutang` -> Return data denda.
* `GET ?action=get_bank` -> Return data mutasi bank.

### `api_admin.php` (Protected Auth JSON)

* Check `$_SESSION['admin_logged']`. Reject HTTP 403 jika unauthorized.
* `POST ?action=add_siswa` -> Tambah siswa manual.
* `POST ?action=update_kas` -> Toggle status bayar kas.
* `POST ?action=add_jurnal` / `DELETE ?action=delete_jurnal` -> CRUD Jurnal.
* `POST ?action=add_piutang` / `POST ?action=update_piutang_status` -> Management denda.
* `POST ?action=add_bank` -> Management mutasi bank.

---

## 8. Struktur Folder Proyek

```text
keuangan-kelas/
├── config/
│   └── database.php        # PDO Database Connection
├── assets/
│   └── js/
│       ├── app_public.js   # Logic SPA App 1 (jQuery + AJAX)
│       └── app_admin.js    # Logic Dashboard App 2 (AJAX CRUD)
├── api_public.php          # Backend Endpoint Publik
├── api_admin.php           # Backend Endpoint Admin
├── index.php               # App 1 (Web Publik SPA)
├── login.php               # Form Login Bendahara
├── admin_dashboard.php     # App 2 (Dashboard Admin)
└── logout.php              # Destroy Session

```