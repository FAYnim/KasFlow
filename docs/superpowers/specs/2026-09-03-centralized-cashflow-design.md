# Desain Spesifikasi: Sistem Sentralisasi Cashflow, Alokasi Akun, dan Riwayat (Model Money Tracker)

**Tanggal:** 2026-09-03  
**Status:** Approved  
**Branch:** `feat/sentralisasi-alokasi-cashflow-riwayat`

---

## 1. Latar Belakang & Masalah
Pada sistem kas kelas sebelumnya, fitur pencatatan terfragmentasi ke berbagai modul independen:
- `jurnal_kas` (arus kas umum)
- `storage_allocations` & `storage_transactions` (tempat penyimpanan/alokasi)
- `kas_bms` (Bank Mini Sekolah)
- `kasbon` (dana talangan siswa)
- `kas_mingguan` (iuran siswa per minggu)

Fragmentasi ini menyebabkan perhitungan saldo ganda/miskalkulasi, ambiguitas istilah "alokasi", dan ketidaksinkronan data antara buku jurnal, saldo fisik/digital, dan laporan publik.

---

## 2. Tujuan & Pendekatan Baru
Mengganti ketiga modul (`alokasi dana`, `cashflow`, dan `riwayat`) dengan arsitektur **Universal Money Tracker**:
1. **Penyimpanan (Accounts):** Tempat saldo riil disimpan (Cash fisik, DANA, SeaBank, Bank Mandiri, dll) yang dapat dikelola secara dinamis.
2. **Kategori (Categories):** Pos klasifikasi transaksi masuk dan keluar (Uang Kas, Pembelian LKS, Spidol/ATK, Kasbon, BMS, dll) yang dapat di-CRUD.
3. **Ledger Sentral (Transactions):** Satu buku besar tunggal untuk semua mutasi uang (Pemasukan, Pengeluaran, dan Transfer Antar Akun).
4. **Antrean Kas Mingguan (Uncategorized Queue):** Hasil centangan iuran mingguan masuk ke antrean sementara sebelum dicatatkan ke akun dan kategori spesifik di modul Cashflow.
5. **Penonaktifan Fitur Lama:** Mematikan modul lama `kas_bms`, `storage_allocations`, dan alur `kasbon` terpisah.

---

## 3. Skema Database

### 3.1. Tabel `accounts`
Menyimpan daftar dompet/rekening tempat saldo kas disimpan.
```sql
CREATE TABLE accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('cash', 'ewallet', 'bank', 'other') NOT NULL DEFAULT 'other',
    icon VARCHAR(50) DEFAULT 'fa-solid fa-wallet',
    initial_balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.2. Tabel `categories`
Menyimpan klasifikasi pos transaksi pemasukan dan pengeluaran.
```sql
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense', 'both') NOT NULL DEFAULT 'both',
    icon VARCHAR(50) DEFAULT 'fa-solid fa-tag',
    color VARCHAR(20) DEFAULT '#3b82f6',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.3. Tabel `transactions`
Buku besar sentral (Single Source of Truth) untuk semua mutasi uang.
```sql
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    type ENUM('income', 'expense', 'transfer') NOT NULL,
    account_id INT NOT NULL,
    to_account_id INT NULL DEFAULT NULL,
    category_id INT NULL DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT NULL,
    ref_type ENUM('manual', 'kas_mingguan', 'transfer') NOT NULL DEFAULT 'manual',
    ref_id INT NULL DEFAULT NULL,
    created_by VARCHAR(50) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_trans_date (date),
    INDEX idx_trans_type (type),
    INDEX idx_trans_account (account_id),
    INDEX idx_trans_to_account (to_account_id),
    INDEX idx_trans_category (category_id),
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (to_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.4. Tabel `kas_mingguan_queue`
Menampung akumulasi centangan kas mingguan yang belum/akan dialokasikan ke akun riil.
```sql
CREATE TABLE kas_mingguan_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bulan VARCHAR(20) NOT NULL,
    tahun INT NOT NULL,
    nominal DECIMAL(12,2) NOT NULL,
    keterangan VARCHAR(255) NOT NULL,
    detail JSON NULL,
    status ENUM('pending', 'recorded') NOT NULL DEFAULT 'pending',
    transaction_id INT NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kmq_status (status),
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Logika Perhitungan Keuangan (`FinanceEngine.php`)

### 4.1. Formula Saldo Akun Tunggal
Untuk setiap akun $i$:
$$\text{Saldo}_i = \text{initial\_balance}_i + \sum \text{Income}_i - \sum \text{Expense}_i + \sum \text{TransferIn}_i - \sum \text{TransferOut}_i$$
Dimana:
- $\text{Income}_i$: Transaksi dengan `type = 'income'` dan `account_id = i`.
- $\text{Expense}_i$: Transaksi dengan `type = 'expense'` dan `account_id = i`.
- $\text{TransferIn}_i$: Transaksi dengan `type = 'transfer'` dan `to_account_id = i`.
- $\text{TransferOut}_i$: Transaksi dengan `type = 'transfer'` dan `account_id = i`.

### 4.2. Formula Total Kas Kelas
$$\text{Total Saldo Kas} = \sum_{i \in \text{Akun Aktif}} \text{Saldo}_i$$

### 4.3. Penanganan Uncheck Kas Mingguan
Jika admin membatalkan centang kas yang sudah pernah masuk pembukuan transaksi:
- Sistem menghitung nominal selisih negatif (misal: $-Rp 5.000$).
- Sistem memasukkan baris baru ke `kas_mingguan_queue` dengan nominal negatif.
- Pada antrean Cashflow muncul aksi koreksi keluar: admin memilih akun mana yang akan dipotong dan mencatat transaksi penyesuaian.

---

## 5. Antarmuka Pengguna (UI)

### 5.1. Tab Cashflow Terpadu
1. **Widget Kartu Saldo (Header):**
   - Kartu Total Saldo Gabungan.
   - Grid kartu per akun (*Cash*, *DANA*, *BCA*, dll) lengkap dengan ikon dan saldo aktif.
2. **Banner Peringatan Antrean (Uncategorized Alert):**
   - Alert interaktif jika ada `nominal` di `kas_mingguan_queue` berstatus `pending`.
   - Tombol satu-klik untuk membuka modal pencatatan ke akun tujuan.
3. **Bar Tindakan Cepat (Quick Action Bar):**
   - Tombol `+ Catat Pemasukan`
   - Tombol `- Catat Pengeluaran`
   - Tombol `⇄ Transfer Antar Akun`
   - Tombol `⚙ Kelola Akun & Kategori`
4. **Tabel Riwayat Transaksi:**
   - Filter Akun, Kategori, Rentang Tanggal, dan Tipe Transaksi.
   - Kolom: Tanggal, Tipe, Kategori, Akun Sumber/Tujuan, Keterangan, Nominal (+/-), Aksi (Edit/Hapus).
   - Export Data (PDF & Excel/CSV).

### 5.2. Tab Kelola Akun & Kategori
- Manajemen Akun (Tambah nama akun, pilih jenis/tipe, pilih ikon FontAwesome, saldo awal, switch aktif/nonaktif).
- Manajemen Kategori (Tambah nama kategori, tipe pemasukan/pengeluaran, warna label, ikon).

### 5.3. Halaman Publik (`index.php`)
- Menampilkan ringkasan saldo akun dan total kas yang transparan.
- Riwayat transaksi publik dengan badge tipe dan kategori.
- Tab Kas Mingguan siswa tetap berjalan presisi.
- Menonaktifkan tab lama (Alokasi Dana, BMS, Kasbon).

---

## 6. Rencana Migrasi Data Lama
- Semua data lama sudah diamankan di tabel `backup_*`.
- Akun dompet awal dapat di-seeding otomatis dari `storage_accounts` lama (Cash, DANA, SeaBank, Mandiri).
- Kategori awal disiapkan: `Uang Kas Mingguan`, `LKS / Buku`, `ATK & Operasional`, `BMS`, `Kasbon Siswa`, `Lain-lain`.
- Modul lama tidak lagi diakses di antarmuka publik maupun dashboard admin.
