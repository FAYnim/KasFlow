# Design: Fitur Kasbon

**Tanggal:** 2026-08-02
**Status:** Approved (desain), menunggu implementasi

## 1. Tujuan

Menambahkan fitur pencatatan kasbon (uang yang dipinjamkan) ke Sistem Keuangan Kelas RPL 1. Fitur ini berdiri sendiri, tidak terikat dengan tabel siswa atau jurnal kas.

## 2. Ruang Lingkup

### In-scope
- Tabel baru `kasbon` dengan field: `nama`, `tanggal`, `keterangan`, `jumlah`, `status`.
- CRUD admin (bisa akses via dashboard bendahara).
- Tampilan read-only di web publik (SPA).
- Filter per bulan + tahun di kedua aplikasi.
- Status `belum_lunas` / `lunas` dengan timestamp `tanggal_lunas` otomatis.

### Out-of-scope (YAGNI)
- Tidak ada relasi ke tabel `siswa` (kasbon bersifat umum).
- Tidak ada auto-sync ke `jurnal_kas` (bisa ditambahkan nanti jika saldo kas perlu match).
- Tidak ada export PDF/Excel khusus.
- Tidak ada notifikasi jatuh tempo.
- Tidak ada pagination.
- Tidak ada soft-delete.

## 3. Schema Database

```sql
CREATE TABLE kasbon (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    status ENUM('belum_lunas','lunas') DEFAULT 'belum_lunas',
    tanggal_lunas DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_kasbon_tanggal ON kasbon (tanggal);
CREATE INDEX idx_kasbon_status ON kasbon (status);
```

Field `tanggal_lunas` nullable — hanya diisi saat `status = 'lunas'`. Diisi otomatis oleh server (`CURDATE()`) saat endpoint `mark_lunas_kasbon` dipanggil, bukan oleh input user.

## 4. API

Semua endpoint mengembalikan JSON. Response sukses: `{ok: true, ...}`. Error: HTTP 4xx/5xx + `{error: "..."}`.

### Public (`src/api/public.php`)

**`GET ?action=get_kasbon&bulan=<NamaBulan>&tahun=<YYYY>`**

- `bulan` = nama bulan English (Januari/Januari, Februari, dst.) — konsisten dengan `kas_mingguan` existing.
- `tahun` integer, default tahun sekarang.
- Return: `[{id, nama, tanggal, keterangan, jumlah, status, tanggal_lunas}]` terurut ASC by tanggal.

**Error cases:**
- 400 jika `bulan` tidak dikenal.

### Admin (`src/api/admin.php`)

Semua endpoint cek `$_SESSION['admin_logged']` (pattern existing). 403 jika tidak login.

**`POST ?action=add_kasbon`**
- Body: `nama`, `tanggal` (default today), `keterangan`, `jumlah` (> 0), `status` (default `belum_lunas`).
- Validasi: `nama` non-empty, `jumlah > 0`, `status` ∈ enum.
- 400 jika gagal validasi. Return `{ok:true, id}` saat sukses.

**`POST ?action=update_kasbon`**
- Body: `id`, `nama`, `tanggal`, `keterangan`, `jumlah`, `status`.
- Sama dengan add. Update `updated_at` otomatis via MySQL trigger.
- 400 jika `id` tidak ada atau invalid.

**`POST ?action=mark_lunas_kasbon`**
- Body: `id`.
- Set `status='lunas'`, `tanggal_lunas=CURDATE()`.
- Idempotent: jika sudah lunas, tidak error.

**`POST ?action=mark_belum_lunas_kasbon`**
- Body: `id`.
- Set `status='belum_lunas'`, `tanggal_lunas=NULL`.
- Idempotent.

**`DELETE ?action=delete_kasbon`**
- Body/Query: `id`.
- Hard delete. Tidak ada soft-delete.

## 5. UI

### Web Publik (`index.php`)

- Tambah tab **"Kasbon"** di sidebar (posisi: setelah "Mutasi Bank" — di akhir list).
- Konten tab:
  - Dropdown `Bulan` + `Tahun` di header (default bulan & tahun berjalan).
  - Tabel kolom: `#` | `Tanggal` | `Nama` | `Keterangan` | `Jumlah` (format rupiah) | `Status` (badge).
  - Badge status: kuning `#fbbf24` untuk "Belum Lunas", hijau `#22c55e` untuk "Lunas".
- Filter dropdown trigger AJAX `get_kasbon` ulang (debounce 200ms).
- Read-only: tidak ada tombol edit/hapus.

### Web Admin (`admin_dashboard.php`)

- Tambah menu **"Kelola Kasbon"** di sidebar admin (posisi: setelah "Mutasi Bank").
- Konten modul:
  - Form input di atas (nama, tanggal, keterangan, jumlah, status).
  - Filter dropdown bulan+tahun di bawah form.
  - Tabel kolom sama dengan publik + kolom **Aksi** (Edit, Tandai Lunas/Belum Lunas, Hapus).
- Tombol "Tambah" di form → `add_kasbon`.
- Tombol "Edit" di baris → populate form, ubah tombol jadi "Update" → `update_kasbon`.
- Tombol "Tandai Lunas" → `mark_lunas_kasbon` (tampil hanya jika `belum_lunas`).
- Tombol "Tandai Belum Lunas" → `mark_belum_lunas_kasbon` (tampil hanya jika `lunas`).
- Tombol "Hapus" → konfirmasi `confirm()` → `delete_kasbon`.

## 6. File yang Diubah

1. `database/schema.sql` — tambah `CREATE TABLE kasbon` + 2 index.
2. `src/api/public.php` — tambah case `get_kasbon`.
3. `src/api/admin.php` — tambah 5 case (`add_kasbon`, `update_kasbon`, `mark_lunas_kasbon`, `mark_belum_lunas_kasbon`, `delete_kasbon`).
4. `index.php` — tambah tab Kasbon + handler `data-tab="kasbon"` + JS render.
5. `src/admin/dashboard.php` — tambah menu "Kelola Kasbon" + form + tabel + JS handler.
6. `tests/api/test_kasbon.php` — file test baru.

## 7. Testing

File test `tests/api/test_kasbon.php` (PHP native, mengikuti pattern existing):

1. **Schema test** — INSERT, validasi enum, default `belum_lunas`, `tanggal_lunas` NULL by default.
2. **Public API:**
   - `get_kasbon` tanpa filter → return bulan ini.
   - `get_kasbon&bulan=Januari&tahun=2026` → return hanya data Januari 2026.
   - `get_kasbon&bulan=InvalidBulan` → HTTP 400.
3. **Admin API (auth):**
   - Tanpa session → HTTP 403 (semua endpoint).
4. **Admin API (CRUD):**
   - `add_kasbon` sukses → return id.
   - `add_kasbon` tanpa `nama` → 400.
   - `add_kasbon` `jumlah=0` → 400.
   - `add_kasbon` `jumlah=-100` → 400.
   - `add_kasbon` `status='invalid'` → 400.
   - `update_kasbon` field ter-update, `updated_at` berubah.
   - `mark_lunas_kasbon` set `tanggal_lunas=CURDATE()`.
   - `mark_lunas_kasbon` idempotent (panggil 2x tidak error).
   - `mark_belum_lunas_kasbon` clear `tanggal_lunas`.
   - `delete_kasbon` hilang dari DB.
5. **Cleanup** — hapus data test di akhir via TRUNCATE atau DELETE WHERE nama LIKE 'TEST_%'.

## 8. Pertimbangan Desain

- **Mengapa bukan FK ke siswa?** Kasbon bersifat umum, tidak semua peminjam adalah siswa (bisa untuk acara kelas, atau pengurus).
- **Mengapa `tanggal_lunas` auto-fill?** Mencegah input tidak konsisten (user bisa lupa isi tanggal saat menandai lunas).
- **Mengapa hard delete?** Konsisten dengan pattern `delete_jurnal` existing. Soft-delete bisa ditambah nanti jika perlu audit trail.
- **Mengapa tanpa integrasi jurnal?** YAGNI — saat ini saldo kas hanya dihitung dari `jurnal_kas` + `mutasi_bank`. Kasbon adalah catatan piutang, bukan pergerakan kas. Jika nanti perlu integrasi, tambahkan kolom `jurnal_id` nullable.
