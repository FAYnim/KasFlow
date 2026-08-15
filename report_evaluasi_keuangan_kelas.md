# Laporan Evaluasi & Roadmap Kesiapan Rilis (Publish Readiness Audit)
## Aplikasi Pencatatan Keuangan Kelas (Cashflow Kelas)

**Tanggal Evaluasi:** 16 Agustus 2026  
**Status Kesiapan Rilis:** 🔴 **BELUM LAYAK DIPUBLISH (NOT READY FOR PRODUCTION)**  
**Skor Kesiapan:** 55 / 100  

---

## Ringkasan Eksekutif

Aplikasi **Cashflow Kelas** memiliki fondasi antarmuka (UI/UX) yang sangat modern, bersih, responsif, dan kaya fitur visual (dukungan Dark/Light mode, Chart.js, activity log, serta animasi micro-interactions).

Namun, dari perspektif **logika akuntansi/keuangan**, **keamanan data**, dan **alur bisnis pencatatan keuangan kelas realita**, aplikasi ini memiliki beberapa **masalah krusial (Critical Bugs)** yang menyebabkan kalkulasi saldo utama terbalik, celah XSS Injection, dan pemisahan modul (*data silos*) yang tidak saling terhubung.

---

## Categorization & Roadmap Eksekusi (Urutan Perbaikan & Pengerjaan)

Untuk membawa aplikasi ini ke status **Siap Rilis (Production Ready)**, perbaikan harus dilakukan bertahap sesuai urutan prioritas berikut:

```
┌─────────────────────────────────────────────────────────────┐
│  FASE 1: PERBAIKAN DARURAT & KRITIS (Critical Fixes)        │
│  - Perbaikan Rumus Total Kas Dashboard                      │
│  - Pengamanan Celah XSS pada Seluruh Render JS              │
│  - File Konfigurasi Environment (.env)                      │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│  FASE 2: RESTRUKTURISASI LOGIKA KEUANGAN & MODUL (Core Logic)│
│  - Integrasi Kas Mingguan, Kasbon, & Jurnal Kas             │
│  - Integrasi Jurnal Kas ⟷ Alokasi Dana (Storage Accounts)   │
│  - Ganti Kasbon Nama Bebas ke Master Siswa                 │
│  - Penanganan Tarif Kas Dinamis & Historic Records          │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│  FASE 3: PENGATURAN KELAS & FITUR UTAMA LENGKAP (Essential) │
│  - Halaman Pengaturan Kelas (Nama Kelas, Tarif, Saldo Awal) │
│  - Fitur Ganti Password Bendahara                           │
│  - Laporan Ringkasan Tunggakan Kas Per Siswa                │
│  - Soft Delete Data Siswa (Status Aktif / Alumni / Pindah)  │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│  FASE 4: PENYEMPURNAAN UX & REFATORING (UX & Refactoring)   │
│  - Penggabungan Modul Kas BMS ke Alokasi Dana Bank          │
│  - Import Siswa Bulk via CSV/Excel                          │
│  - Perbaikan Generator CSV (BOM UTF-8 & Escaping)           │
│  - Penggantian Browser Prompt() dengan Custom Modal         │
└─────────────────────────────────────────────────────────────┘
```

---

## Detail Daftar Masalah & Prioritas

### 🔴 FASE 1: Perbaikan Darurat & Kritis (Critical & High Priority)

#### 1. Bug Logika Rumus Total Kas Dashboard (CRITICAL)
* **Kategori:** Logical Bug / Financial Calculation Error
* **Lokasi File:** [`src/api/public.php:L11-L23`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/src/api/public.php#L11-L23)
* **Deskripsi:** Rumus yang digunakan saat ini adalah `$totalKas = $totalKasMingguan - $kasbonLunas;`. Mengurangi `kasbonLunas` dari kas mingguan menyebabkan saldo kas kelas di dashboard **makin berkurang ketika kasbon dilunasi**. Selain itu, pengeluaran di Jurnal Kas sama sekali tidak mengurangi Total Kas.
* **Dampak:** Informasi saldo kelas di halaman utama 100% salah dan menyesatkan pengguna.
* **Rekomendasi Perbaikan:**
  Ubah rumus menjadi:
  $$\text{Total Kas} = (\text{Total Kas Mingguan} + \text{Pemasukan Jurnal Kas} + \text{Kasbon Lunas}) - (\text{Pengeluaran Jurnal Kas} + \text{Kasbon Belum Lunas})$$

#### 2. Celah Keamanan XSS (Cross-Site Scripting) pada Tampilan HTML (CRITICAL)
* **Kategori:** Security Vulnerability
* **Lokasi File:** 
  - [`assets/js/public.js:L199`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/assets/js/public.js#L199) (`renderKas`)
  - [`assets/js/public.js:L295`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/assets/js/public.js#L295) (`loadJurnal`)
  - [`assets/js/public.js:L328`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/assets/js/public.js#L328) (`loadKasbon`)
  - [`assets/js/admin.js:L283`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/assets/js/admin.js#L283) (`lSiswa`)
  - [`assets/js/admin.js:L549`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/assets/js/admin.js#L549) (`lJurnal`)
* **Deskripsi:** String dari database seperti nama siswa dan keterangan transaksi langsung di-interpolate ke DOM HTML tanpa melewati fungsi sanitasi `escapeHtml()`.
* **Dampak:** Pengguna/admin dapat menginput teks mengandung script (`<script>...`) yang akan dieksekusi di browser pengunjung lain.
* **Rekomendasi Perbaikan:** Bungkus semua variabel string yang di-render ke HTML menggunakan `escapeHtml(str)`.

#### 3. Hardcoded Kredensial Database & Absence of `.env` (HIGH)
* **Kategori:** Architecture & Deployment Readiness
* **Lokasi File:** [`config/database.php:L5-L8`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/config/database.php#L5-L8)
* **Deskripsi:** Kredensial DB di-hardcode ke `127.0.0.1`, `user=root`, `pass=""`.
* **Dampak:** Aplikasi gagal terkoneksi saat di-upload ke server hosting/VPS tanpa mengedit file secara manual.
* **Rekomendasi Perbaikan:** Buat mekanisme pembacaan file `.env` atau konfigurasi berbasis environment variable (`getenv()`).

---

### 🟠 FASE 2: Restrukturisasi Logika Keuangan & Integrasi Modul (High Priority)

#### 4. Terisolasinya Modul-Modul Keuangan (Data Silos) (HIGH)
* **Kategori:** Integration & Business Flow
* **Deskripsi:** Modul Kas Mingguan, Jurnal Kas, Kasbon, dan Alokasi Dana berdiri sendiri-sendiri tanpa sinkronisasi otomatis.
* **Dampak:** Pengguna harus menginput data berulang kali di tempat terpisah (misal centang kas mingguan, lalu membuat jurnal masuk, lalu menambah alokasi dompet).
* **Rekomendasi Perbaikan:**
  - Tambahkan opsi otomatis: Saat Kas Mingguan dicentang / Kasbon dilunasi, sistem menawarkan/otomatis mencatat transaksi ke Jurnal Kas.
  - Pada form Jurnal Kas, tambahkan dropdown "Simpan ke Tempat Penyimpanan" (Cash/DANA/BCA) agar saldo Alokasi Dana ter-update otomatis.

#### 5. Kasbon Tidak Terhubung ke Master Data Siswa (HIGH)
* **Kategori:** Data Integrity
* **Lokasi File:** [`database/schema.sql:L76`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/database/schema.sql#L76) (`kasbon.nama VARCHAR(100)`)
* **Deskripsi:** Nama peminjam kasbon diinput secara teks bebas, tidak memilih dari tabel `siswa`.
* **Dampak:** Berisiko salah ketik nama, tidak bisa direkap per siswa.
* **Rekomendasi Perbaikan:** Tambahkan `siswa_id INT NULL` pada tabel `kasbon` dan gunakan `<select>` dropdown siswa pada form Kasbon.

#### 6. Perubahan Tarif Kas Mempengaruhi Rekam Historis (HIGH)
* **Kategori:** Financial Logic / Data Integrity
* **Lokasi File:** [`src/api/admin.php:L46`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/src/api/admin.php#L46)
* **Deskripsi:** Total pembayaran kas mingguan dihitung ulang dari `tarif_kas_mingguan` terbaru.
* **Dampak:** Jika tarif kas dinaikkan pertengahan tahun, pembayaran bulan-bulan lalu yang sudah lunas mendadak membengkak nominalnya.
* **Rekomendasi Perbaikan:** Simpan `nominal_bayar` aktual per baris saat transaksi kas dicentang lunas, bukan menghitung secara dinamis dari config.

---

### 🟡 FASE 3: Pengaturan Kelas & Fitur Utama Tambahan (Medium Priority)

#### 7. Absennya Menu Pengaturan Kelas (Settings Page) (MEDIUM)
* **Kategori:** Feature Gap / System Configuration
* **Deskripsi:** Tidak ada antarmuka untuk mengedit **Nama Kelas**, **Tarif Kas**, atau **Saldo Awal**. Teks `"RPL 1"` di-hardcode di file HTML (`index.php`, `dashboard.php`, `login.php`).
* **Dampak:** Kelas lain tidak bisa menggunakan aplikasi ini tanpa membongkar kode HTML.
* **Rekomendasi Perbaikan:** Buat menu **Pengaturan Kelas** di admin untuk mengubah parameter di tabel `config` dan gunakan variabel `nama_kelas` secara dinamis di seluruh tampilan HTML.

#### 8. Absennya Fitur Ganti Password Bendahara (MEDIUM)
* **Kategori:** Security & User Management
* **Deskripsi:** Pengurus/Bendahara tidak dapat mengganti password login dari dashboard.
* **Dampak:** Password bawaan seed (`admin123` / hash awal) rawan diketahui orang lain dan tidak bisa diperbarui.
* **Rekomendasi Perbaikan:** Tambahkan modal/halaman **Ganti Password** di Dashboard Admin dengan validasi password lama & password baru.

#### 9. Laporan Ringkasan Tunggakan Kas Siswa (MEDIUM)
* **Kategori:** Feature Gap / Business Value
* **Deskripsi:** Belum ada ringkasan mengenai total tunggakan iuran per siswa atau daftar siswa yang paling sering menunggak.
* **Dampak:** Bendahara kesulitan melakukan penagihan iuran kas.
* **Rekomendasi Perbaikan:** Tambahkan tab/sub-menu **Laporan Tunggakan** yang menampilkan ranking/daftar siswa beserta nominal tunggakan yang belum dibayar.

#### 10. Penghapusan Siswa Merusak Rekam Jejak Kas Historis (MEDIUM)
* **Kategori:** Data Integrity
* **Lokasi File:** [`database/schema.sql:L40`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/database/schema.sql#L40) (`ON DELETE CASCADE`)
* **Deskripsi:** Menghapus siswa hapus seluruh baris `kas_mingguan` terkait.
* **Dampak:** Jika ada siswa pindah/lulus dan dihapus, total kas kelas periode lalu ikut berkurang.
* **Rekomendasi Perbaikan:** Tambahkan kolom `is_active TINYINT(1) DEFAULT 1` pada tabel `siswa` (Soft Delete), sehingga siswa yang keluar hanya dinonaktifkan tanpa menghapus riwayat kasnya.

---

### 🔵 FASE 4: Penyempurnaan UX, Refactoring, & Clean Code (Low Priority)

#### 11. Redundansi Modul Kas BMS (LOW)
* **Kategori:** UX Refactoring
* **Deskripsi:** Modul Kas BMS mencatat simpanan bank secara terpisah dari modul Alokasi Dana (Storage Accounts).
* **Dampak:** Pengguna bingung karena ada dua tempat mencatat rekening bank.
* **Rekomendasi Perbaikan:** Peleburan/Penggabungan modul Kas BMS ke dalam **Alokasi Dana** sebagai akun tipe *Bank Sekolah*.

#### 12. Peningkatan Generator CSV (LOW)
* **Kategori:** Export & Compatibility
* **Lokasi File:** [`assets/js/admin.js:L630`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/assets/js/admin.js#L630)
* **Deskripsi:** String CSV dibuat manual tanpa sanitasi tanda kutip dan tanpa BOM UTF-8.
* **Dampak:** File CSV berantakan jika keterangan berisi koma/tanda kutip atau dibuka di MS Excel Windows.
* **Rekomendasi Perbaikan:** Gunakan format CSV dengan penanganan tanda kutip ganda dan tambahkan karakter BOM `\uFEFF`.

#### 13. Penggantian Browser Prompt() dengan Custom Modal (LOW)
* **Kategori:** UI/UX Polish
* **Lokasi File:** [`assets/js/admin.js:L1146`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/assets/js/admin.js#L1146)
* **Deskripsi:** Menanyakan input tanggal prune log menggunakan `prompt()` bawaan browser.
* **Dampak:** Pengalaman pengguna kurang konsisten dengan modal UI lainnya.
* **Rekomendasi Perbaikan:** Buat modal dialog HTML/Tailwind terstandar untuk konfirmasi pembersihan log.

---

## Matriks Rekomendasi Perubahan Fitur

| Tindakan | Fitur / Modul | Alasan & Manfaat |
| :---: | :--- | :--- |
| **TAMBAH** | Menu Pengaturan Kelas & Password | Memungkinkan fleksibilitas nama kelas, tarif, dan keamanan akun bendahara. |
| **TAMBAH** | Laporan Tunggakan Kas Per Siswa | Memudahkan bendahara menagih iuran kas yang belum dibayar. |
| **TAMBAH** | Soft Delete Siswa (`is_active`) | Menjaga integritas data historis kas ketika siswa pindah/lulus. |
| **TAMBAH** | Import Bulk Siswa via CSV | Menghemat waktu inisialisasi data kelas baru. |
| **GABUNG** | Kas BMS ➔ Alokasi Dana (Storage Accounts) | Menghilangkan redundansi pencatatan rekening simpanan bank. |
| **INTEGRASI** | Kas Mingguan & Kasbon ➔ Jurnal Kas | Memastikan seluruh arus kas tercatat di Jurnal Kas utama secara otomatis. |
| **HAPUS** | Browser `prompt()` & Hardcoded "RPL 1" | Meningkatkan profesionalitas UI/UX dan fleksibilitas aplikasi. |

---

## Kesimpulan Evaluasi

Aplikasi **Cashflow Kelas** memiliki **potensi besar** untuk menjadi aplikasi pencatatan keuangan kelas yang sangat populer karena desain visualnya yang menarik dan modern. Namun, aplikasi ini **belum siap dipublish saat ini** sebelum perbaikan **Fase 1 (Critical Fixes)** dan **Fase 2 (Financial Logic & Integration)** selesaikan.

Dengan mengikuti roadmap perbaikan di atas, aplikasi ini dapat diubah dari sekadar prototipe visual menjadi **aplikasi keuangan kelas yang handal, akurat, aman, dan siap pakai (Production-Ready)**.
