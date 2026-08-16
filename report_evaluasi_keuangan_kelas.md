# Laporan Evaluasi & Roadmap Kesiapan Rilis (Publish Readiness Audit)
## Aplikasi Pencatatan Keuangan Kelas (Cashflow Kelas)

**Tanggal Evaluasi:** 16 Agustus 2026  
**Status Kesiapan Rilis:** 🟡 **HAMPIR SIAP (RELEASE CANDIDATE)**  
**Skor Kesiapan:** 85 / 100  

---

## Ringkasan Eksekutif

Aplikasi **Cashflow Kelas** memiliki fondasi antarmuka (UI/UX) yang sangat modern, bersih, responsif, dan kaya fitur visual (dukungan Dark/Light mode, Chart.js, activity log, serta animasi micro-interactions).

Fase 1 (Critical Fixes) sudah terselesaikan — kalkulasi saldo sudah benar, XSS sudah diamankan, dan konfigurasi `.env` sudah siap produksi.

Sisa masalah saat ini ada di level **integrasi modul** (data silos), **master data siswa**, **tarif kas dinamis**, serta **fitur administratif** yang perlu dikerjakan bertahap sesuai roadmap.

---

## Categorization & Roadmap Eksekusi (Urutan Perbaikan & Pengerjaan)

Untuk membawa aplikasi ini ke status **Siap Rilis (Production Ready)**, perbaikan harus dilakukan bertahap sesuai urutan prioritas berikut:

```
┌─────────────────────────────────────────────────────────────┐
│  ✅ FASE 1: PERBAIKAN DARURAT & KRITIS (COMPLETED)          │
│  [x] Perbaikan Rumus Total Kas Dashboard                    │
│  [x] Pengamanan Celah XSS pada Seluruh Render JS            │
│  [x] File Konfigurasi Environment (.env)                    │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│  🟠 FASE 2: RESTRUKTURISASI LOGIKA KEUANGAN & MODUL (HIGH)  │
│  [x] 4.  Integrasi Kas Mingguan, Kasbon, & Jurnal Kas       │
│  [ ] 5.  Integrasi Jurnal Kas ⟷ Alokasi Dana               │
│  [ ] 6.  Ganti Kasbon Nama Bebas ke Master Siswa           │
│  [ ] 7.  Penanganan Tarif Kas Dinamis & Historic Records    │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│  🟡 FASE 3: PENGATURAN KELAS & FITUR UTAMA (MEDIUM)         │
│  [ ] 8.  Halaman Pengaturan Kelas (Nama Kelas, Tarif, dll)  │
│  [ ] 9.  Fitur Ganti Password Bendahara                    │
│  [ ] 10. Laporan Ringkasan Tunggakan Kas Per Siswa          │
│  [ ] 11. Soft Delete Data Siswa                             │
└──────────────────────────────┬──────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────┐
│  🔵 FASE 4: PENYEMPURNAAN UX & REFATORING (LOW)             │
│  [ ] 12. Penggabungan Modul Kas BMS ke Alokasi Dana Bank    │
│  [ ] 13. Import Siswa Bulk via CSV/Excel                    │
│  [ ] 14. Perbaikan Generator CSV (BOM UTF-8 & Escaping)     │
│  [ ] 15. Penggantian Browser Prompt() dengan Custom Modal   │
└─────────────────────────────────────────────────────────────┘
```

---

## Detail Daftar Masalah & Prioritas

### ✅ FASE 1: Perbaikan Darurat & Kritis (Critical Fixes) — SELESAI

Semua item Fase 1 telah diselesaikan pada 16 Agustus 2026.

| No | Item | Status | Commit |
|----|------|--------|--------|
| 1 | Bug Logika Rumus Total Kas Dashboard | ✅ Selesai | `fix: correct get_summary formula` |
| 2 | Celah Keamanan XSS pada Render DOM | ✅ Selesai | `fix: sentralisasi escapeHtml` |
| 3 | Hardcoded Kredensial DB & `.env` | ✅ Selesai | `fix: enhance .env parser` |

---

### 🟠 FASE 2: Restrukturisasi Logika Keuangan & Integrasi Modul (High Priority)

- [x] **4.** Terisolasinya Modul-Modul Keuangan (Data Silos) (HIGH)
* **Kategori:** Integration & Business Flow
* **Status:** ✅ SELESAI — 16 Agustus 2026 (commit `feat/fase2-integrasi-kas-jurnal-alokasi`)
* **Yang Dilakukan:**
  - Tambah kolom `storage_account_id`, `source`, `source_id` pada tabel `jurnal_kas` via migration.
  - Form Jurnal Kas kini memiliki dropdown **Tempat Penyimpanan** — saldo dompet otomatis terbarui.
  - Saat menyimpan centang Kas Mingguan dengan pembayaran baru, muncul **modal konfirmasi integrasi** yang memungkinkan pencatatan ke Jurnal Kas + pemilihan dompet tujuan dalam satu langkah.
  - Formula `get_summary` diperbarui agar tidak terjadi double-counting untuk transaksi yang bersumber dari `kas_mingguan`.
  - Tabel Jurnal Kas admin kini menampilkan badge **Sumber** (Kas Mingguan / Dana Talangan / Manual) dan nama **dompet tujuan**.

- [ ] **5.** Kasbon Tidak Terhubung ke Master Data Siswa (HIGH)
* **Kategori:** Data Integrity
* **Lokasi File:** [`database/schema.sql:L76`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/database/schema.sql#L76) (`kasbon.nama VARCHAR(100)`)
* **Deskripsi:** Nama peminjam kasbon diinput secara teks bebas, tidak memilih dari tabel `siswa`.
* **Dampak:** Berisiko salah ketik nama, tidak bisa direkap per siswa.
* **Rekomendasi Perbaikan:** Tambahkan `siswa_id INT NULL` pada tabel `kasbon` dan gunakan `<select>` dropdown siswa pada form Kasbon.

- [ ] **6.** Perubahan Tarif Kas Mempengaruhi Rekam Historis (HIGH)
* **Kategori:** Financial Logic / Data Integrity
* **Lokasi File:** [`src/api/admin.php:L46`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/src/api/admin.php#L46)
* **Deskripsi:** Total pembayaran kas mingguan dihitung ulang dari `tarif_kas_mingguan` terbaru.
* **Dampak:** Jika tarif kas dinaikkan pertengahan tahun, pembayaran bulan-bulan lalu yang sudah lunas mendadak membengkak nominalnya.
* **Rekomendasi Perbaikan:** Simpan `nominal_bayar` aktual per baris saat transaksi kas dicentang lunas, bukan menghitung secara dinamis dari config.

---

### 🟡 FASE 3: Pengaturan Kelas & Fitur Utama Tambahan (Medium Priority)

- [ ] **7.** Absennya Menu Pengaturan Kelas (Settings Page) (MEDIUM)
* **Kategori:** Feature Gap / System Configuration
* **Deskripsi:** Tidak ada antarmuka untuk mengedit **Nama Kelas**, **Tarif Kas**, atau **Saldo Awal**. Teks `"RPL 1"` di-hardcode di file HTML (`index.php`, `dashboard.php`, `login.php`).
* **Dampak:** Kelas lain tidak bisa menggunakan aplikasi ini tanpa membongkar kode HTML.
* **Rekomendasi Perbaikan:** Buat menu **Pengaturan Kelas** di admin untuk mengubah parameter di tabel `config` dan gunakan variabel `nama_kelas` secara dinamis di seluruh tampilan HTML.

- [ ] **8.** Absennya Fitur Ganti Password Bendahara (MEDIUM)
* **Kategori:** Security & User Management
* **Deskripsi:** Pengurus/Bendahara tidak dapat mengganti password login dari dashboard.
* **Dampak:** Password bawaan seed (`admin123` / hash awal) rawan diketahui orang lain dan tidak bisa diperbarui.
* **Rekomendasi Perbaikan:** Tambahkan modal/halaman **Ganti Password** di Dashboard Admin dengan validasi password lama & password baru.

- [ ] **9.** Laporan Ringkasan Tunggakan Kas Siswa (MEDIUM)
* **Kategori:** Feature Gap / Business Value
* **Deskripsi:** Belum ada ringkasan mengenai total tunggakan iuran per siswa atau daftar siswa yang paling sering menunggak.
* **Dampak:** Bendahara kesulitan melakukan penagihan iuran kas.
* **Rekomendasi Perbaikan:** Tambahkan tab/sub-menu **Laporan Tunggakan** yang menampilkan ranking/daftar siswa beserta nominal tunggakan yang belum dibayar.

- [ ] **10.** Penghapusan Siswa Merusak Rekam Jejak Kas Historis (MEDIUM)
* **Kategori:** Data Integrity
* **Lokasi File:** [`database/schema.sql:L40`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/database/schema.sql#L40) (`ON DELETE CASCADE`)
* **Deskripsi:** Menghapus siswa hapus seluruh baris `kas_mingguan` terkait.
* **Dampak:** Jika ada siswa pindah/lulus dan dihapus, total kas kelas periode lalu ikut berkurang.
* **Rekomendasi Perbaikan:** Tambahkan kolom `is_active TINYINT(1) DEFAULT 1` pada tabel `siswa` (Soft Delete), sehingga siswa yang keluar hanya dinonaktifkan tanpa menghapus riwayat kasnya.

---

### 🔵 FASE 4: Penyempurnaan UX, Refactoring, & Clean Code (Low Priority)

- [ ] **11.** Redundansi Modul Kas BMS (LOW)
* **Kategori:** UX Refactoring
* **Deskripsi:** Modul Kas BMS mencatat simpanan bank secara terpisah dari modul Alokasi Dana (Storage Accounts). Keduanya tetap berdiri sendiri.
* **Dampak:** Pengguna bingung karena ada dua tempat mencatat rekening bank.
* **Rekomendasi Perbaikan:** Peleburan/Penggabungan modul Kas BMS ke dalam **Alokasi Dana** sebagai akun tipe *Bank Sekolah*.

- [ ] **12.** Peningkatan Generator CSV (LOW)
* **Kategori:** Export & Compatibility
* **Lokasi File:** [`assets/js/admin.js:L630`](file:///c:/xampp/htdocs/faydev/cashflow-kelas/assets/js/admin.js#L630)
* **Deskripsi:** String CSV dibuat manual tanpa sanitasi tanda kutip dan tanpa BOM UTF-8.
* **Dampak:** File CSV berantakan jika keterangan berisi koma/tanda kutip atau dibuka di MS Excel Windows.
* **Rekomendasi Perbaikan:** Gunakan format CSV dengan penanganan tanda kutip ganda dan tambahkan karakter BOM `\uFEFF`.

- [ ] **13.** Penggantian Browser Prompt() dengan Custom Modal (LOW)
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

Aplikasi **Cashflow Kelas** memiliki **potensi besar** untuk menjadi aplikasi pencatatan keuangan kelas yang sangat populer karena desain visualnya yang menarik dan modern.

**Status akhir:**
- Fase 1 (Critical Fixes): ✅ **100% Selesai** — dasar keamanan dan akurasi saldo sudah solid.
- Fase 2–4: Masih perlu pengerjaan bertahap sebelum *production-ready* penuh.

Sekarang dalam posisi **siap beta release** untuk internal, dengan catatan integrasi modul dan fitur administratif masih jalan. Menargetkan **Production Ready** setelah Fase 2 selesai.
