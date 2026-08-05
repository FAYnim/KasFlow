# Activity Log / Riwayat — Design Spec

**Date:** 2026-08-05
**Status:** Approved
**Scope:** Add an activity-log module to Keuangan Kelas RPL 1. One new table (`activity_log`), one helper, one public read endpoint, one admin prune endpoint, instrumentation in 14 existing admin CRUD endpoints, one new public tab, one new admin section, one test file. Reuse existing patterns (kas_bms, kasbon).

## 1. Purpose

A simple activity log (audit trail) that records every meaningful CRUD change the bendahara makes — add/edit/delete siswa, toggle kas, add/edit/delete jurnal, kasbon lifecycle, setor/tarik kas_bms. Public and admin can both read the log (transparency). Admin can manually prune old entries. No before/after diff — just the jejak: kapan, siapa, apa.

## 2. Goals & Non-Goals

**Goals**
- One new table `activity_log` with: id, modul, aksi, entitas_id, ringkasan, admin_username, admin_nama, created_at.
- Helper `log_activity()` called from every successful admin CRUD.
- Public read endpoint with date-range and aksi filter.
- Public SPA shows read-only Riwayat tab with filter.
- Admin dashboard shows same Riwayat tab + manual prune button.
- Identitas admin (nama + username) tampil di publik.

**Non-Goals (YAGNI)**
- No before/after diff / field-level change tracking.
- No automatic retention / cron prune — manual only via UI.
- No export-to-PDF/Excel of the log.
- No per-user audit (only 1 bendahara account today; column reserved for future).
- No "undo" / rollback.
- No log of public-side reads.

## 3. Data Model

### Table `activity_log`

```sql
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modul VARCHAR(50) NOT NULL,
    aksi VARCHAR(20) NOT NULL,
    entitas_id INT NULL,
    ringkasan VARCHAR(500) NOT NULL,
    admin_username VARCHAR(50) NULL,
    admin_nama VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activity_log_created (created_at),
    INDEX idx_activity_log_modul_aksi (modul, aksi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Field semantics:**
- `modul`: one of `siswa`, `kas_mingguan`, `jurnal_kas`, `kas_bms`, `kasbon`.
- `aksi`: one of `tambah`, `edit`, `hapus`, `update_status`.
- `entitas_id`: optional FK-ish reference to the affected row (nullable, not enforced).
- `ringkasan`: human-readable, ≤500 chars. Examples: `"Tambah siswa: Budi Santoso"`, `"Edit kas Ani minggu 3"`, `"Hapus jurnal #42: Beli spidol"`, `"Tandai lunas kasbon #7"`.
- `admin_username`, `admin_nama`: from `$_SESSION` at log time. Nullable for future-proofing (when bendahara is the only account, will always be set).
- `created_at`: set by DB default.

### Migration

New file `database/migrations/2026_08_05_000001_create_activity_log_table.php`. Pattern matches existing migrations (require DB, run `CREATE TABLE IF NOT EXISTS`, echo name). `database/migrate.php` auto-picks it up via `glob` + `sort`.

## 4. Helper

### `src/lib/activity_log.php`

```php
<?php
function log_activity(PDO $pdo, string $modul, string $aksi, ?int $entitasId, string $ringkasan): void {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_log (modul, aksi, entitas_id, ringkasan, admin_username, admin_nama) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $modul,
            $aksi,
            $entitasId,
            $ringkasan,
            $_SESSION['admin_username'] ?? null,
            $_SESSION['admin_nama'] ?? null,
        ]);
    } catch (Throwable $e) {
        // best-effort: never break the main CRUD operation
    }
}
```

`require_once` di `src/api/admin.php` top. Tidak dipakai di publik (read-only path).

**Session contract:** expects `admin_username` dan `admin_nama` di-set saat login. Cek `src/auth/login.php` saat implementasi — kalau belum di-set, tambah `$_SESSION['admin_username'] = $row['username']` dan `$_SESSION['admin_nama'] = $row['nama']`.

## 5. API

### Public API — `src/api/public.php`

Add case `get_riwayat` to the switch:

**`GET ?action=get_riwayat`**
- Optional query params: `dari` (YYYY-MM-DD), `sampai` (YYYY-MM-DD), `aksi` (`tambah|edit|hapus|update_status`).
- Build WHERE:
  - `created_at >= ?` (if `dari`)
  - `created_at <= ?` (if `sampai`, append ` 23:59:59`)
  - `aksi = ?` (if `aksi` valid enum)
- Ignore malformed params silently (no error).
- Order: `created_at DESC, id DESC`.
- Limit: `50` if no filter applied, `500` if any filter applied.
- Response: array of rows:
  ```json
  [
    {
      "id": 42,
      "created_at": "2026-08-05 14:23:11",
      "modul": "siswa",
      "aksi": "tambah",
      "ringkasan": "Tambah siswa: Budi Santoso",
      "entitas_id": 17,
      "admin_username": "admin",
      "admin_nama": "Bendahara RPL 1"
    }
  ]
  ```
- No auth required. Read-only.

### Admin API — `src/api/admin.php`

Add case `prune_riwayat`:

**`POST ?action=prune_riwayat`**
- Body: `sebelum` (required, YYYY-MM-DD).
- Validation: missing `sebelum` → HTTP 400 `{error: "sebelum required"}`. Format invalid (regex `^\d{4}-\d{2}-\d{2}$`) → HTTP 400 `{error: "invalid date"}`. Date lebih dari 30 hari ke depan → HTTP 400 `{error: "sebelum cannot be future date > 30 days"}` (sanity cap).
- Run: `DELETE FROM activity_log WHERE created_at < ?` with `sebelum` value (no time suffix; matches "before this calendar day").
- Response: `{ok: true, deleted: <int>}`.
- Auth required (existing 403 guard).

### Admin endpoint instrumentation

Tambah 1 baris `log_activity(...)` SEBELUM `echo json_encode(['ok' => true, ...])` di setiap case berikut. Jika `INSERT/UPDATE/DELETE` gagal, `log_activity` tidak dipanggil (call happens only after the row is in place, or we need to capture `$id`/`$pdo->lastInsertId()`).

| Case | modul | aksi | entitas_id | ringkasan format |
|---|---|---|---|---|
| `add_siswa` | siswa | tambah | `lastInsertId()` | `Tambah siswa: {nama}` |
| `update_siswa` | siswa | edit | `$_POST['id']` | `Edit siswa: {nama}` |
| `delete_siswa` | siswa | hapus | `$_REQUEST['id']` | `Hapus siswa #{id}` |
| `update_kas` | kas_mingguan | update_status | `$siswa_id` | `Toggle kas {nama_siswa} minggu {minggu}` — nama siswa fetched via SELECT before logging |
| `bulk_update_kas` | kas_mingguan | update_status | null | `Bulk update kas {bulan} {tahun} ({count} perubahan)` |
| `add_jurnal` | jurnal_kas | tambah | `lastInsertId()` | `Tambah jurnal #{id}: {keterangan}` |
| `update_jurnal` | jurnal_kas | edit | `$id` | `Edit jurnal #{id}: {keterangan}` |
| `delete_jurnal` | jurnal_kas | hapus | `$id` | `Hapus jurnal #{id}: {keterangan}` |
| `add_kasbon` | kasbon | tambah | `lastInsertId()` | `Tambah kasbon {nama}: {jumlah}` |
| `update_kasbon` | kasbon | edit | `$id` | `Edit kasbon #{id}: {nama}` |
| `delete_kasbon` | kasbon | hapus | `$id` | `Hapus kasbon #{id}: {nama}` |
| `mark_lunas_kasbon` | kasbon | update_status | `$id` | `Tandai lunas kasbon #{id}` |
| `mark_belum_lunas_kasbon` | kasbon | update_status | `$id` | `Tandai belum lunas kasbon #{id}` |
| `add_bms` | kas_bms | tambah | `lastInsertId()` | `Tambah kas_bms #{id}: {keterangan} ({jenis})` |
| `update_bms` | kas_bms | edit | `$id` | `Edit kas_bms #{id}: {keterangan}` |
| `delete_bms` | kas_bms | hapus | `$id` | `Hapus kas_bms #{id}: {keterangan}` |

**16 calls total** (corrected from prior 17 — recount in implementation plan).

**Note on `update_kas`:** butuh fetch nama siswa sebelum log. Tambahkan 1 query `SELECT nama FROM siswa WHERE id = ?` sebelum `log_activity`. Atau, ambil dari `nama` di payload POST jika frontend kirim. Pilih: fetch via SELECT (lebih reliable, source of truth = DB).

## 6. UI — Public SPA (`index.php`)

### Sidebar

Tambah entry di antara `kasbon` dan `jurnal` (atau setelah `jurnal` — pilih posisi konsisten; default: setelah `jurnal` karena Riwayat adalah "view-only" summary, bukan modul utama):

```html
<a data-tab="riwayat" class="sidebar-nav-item">
    <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>
    <span>Riwayat</span>
</a>
```

### Tab content — `data-tab-content="riwayat"`

Layout sama dengan tab kasbon/bms: filter bar + table.

**Filter bar:**
- Dropdown `Aksi`: Semua / Tambah / Edit / Hapus / Update Status.
- Input `Dari` (date).
- Input `Sampai` (date).
- Button "Terapkan" (btn-primary).
- Button "Reset" (btn-secondary) → clear all filters, reload.

**Table columns:**
- Waktu (format: `DD/MM/YYYY HH:mm`, Indonesia).
- Modul (badge: siswa / kas_mingguan / jurnal_kas / kas_bms / kasbon).
- Aksi (badge warna: tambah=green, edit=blue, hapus=red, update_status=amber).
- Ringkasan (text, truncate ke 80 char dengan ellipsis + title attr).
- Oleh (admin_nama, fallback ke username).

**Empty state:** row spanning all columns, icon kalender + "Belum ada riwayat."

## 7. UI — Admin Dashboard (`src/admin/dashboard.php`)

### Sidebar

Tambah entry sebelum `Ekspor Laporan`:

```html
<a data-tab="riwayat" class="sidebar-nav-item">
    <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>
    <span>Riwayat</span>
</a>
```

### Section — `data-tab-content="riwayat"`

Sama dengan publik + tombol prune di kanan atas:

```html
<div class="flex items-center justify-between mb-4">
  <h2 class="display-md">Riwayat Aktivitas</h2>
  <button id="riwayat-prune-btn" class="btn-secondary text-xs gap-2">
    <i class="fa-solid fa-broom text-[10px]"></i>
    <span>Hapus Log Lama…</span>
  </button>
</div>
```

Filter bar + table identik dengan publik (salin).

**Prune flow (admin):**
1. User klik "Hapus Log Lama…".
2. Browser `prompt("Hapus log sebelum tanggal (YYYY-MM-DD):")`.
3. Validasi format di client (regex YYYY-MM-DD) sebelum submit.
4. `confirm("Hapus permanen semua log sebelum {tanggal}? Tindakan ini tidak dapat dibatalkan.")`.
5. `POST src/api/admin.php?action=prune_riwayat` dengan `{sebelum: <date>}`.
6. Response `{ok, deleted}` → `alert("Dihapus: {deleted} entri")` + reload tabel.
7. Error response → `alert("Gagal: {error}")`.

## 8. JS — `assets/js/public.js`

Tambah fungsi `loadRiwayat()` + handler. Reuse `formatDateTime` (cek apakah sudah ada di file; jika tidak, tulis inline). Reuse `escapeHtml` (sama).

```js
function loadRiwayat() {
  const params = new URLSearchParams({action: 'get_riwayat'});
  const aksi = $('#riwayat-aksi').val();
  const dari = $('#riwayat-dari').val();
  const sampai = $('#riwayat-sampai').val();
  if (aksi) params.set('aksi', aksi);
  if (dari) params.set('dari', dari);
  if (sampai) params.set('sampai', sampai);
  $('#riwayat-wrap').html('<div class="text-center py-6 text-subtle">Memuat…</div>');
  $.getJSON('src/api/public.php?' + params.toString(), function(rows) {
    if (!rows.length) {
      $('#riwayat-wrap').html('<div class="text-center py-6 text-subtle">Belum ada riwayat.</div>');
      return;
    }
    let html = '<table class="table-linear w-full"><thead><tr><th>Waktu</th><th>Modul</th><th>Aksi</th><th>Ringkasan</th><th>Oleh</th></tr></thead><tbody>';
    rows.forEach(r => {
      html += `<tr>
        <td class="text-xs text-subtle whitespace-nowrap">${formatDateTime(r.created_at)}</td>
        <td><span class="badge-neutral">${escapeHtml(r.modul)}</span></td>
        <td><span class="badge-${r.aksi}">${escapeHtml(r.aksi)}</span></td>
        <td title="${escapeHtml(r.ringkasan)}">${escapeHtml(truncate(r.ringkasan, 80))}</td>
        <td class="text-sm">${escapeHtml(r.admin_nama || r.admin_username || '-')}</td>
      </tr>`;
    });
    html += '</tbody></table>';
    $('#riwayat-wrap').html(html);
  });
}

$(document).on('click', '[data-tab="riwayat"]', loadRiwayat);
$('#riwayat-apply').on('click', loadRiwayat);
$('#riwayat-reset').on('click', function() {
  $('#riwayat-aksi').val('');
  $('#riwayat-dari').val('');
  $('#riwayat-sampai').val('');
  loadRiwayat();
});
```

## 9. JS — `assets/js/admin.js`

Tambah handler prune (sama `loadRiwayat` reuse dari public, atau copy kecil — pilih copy kecil untuk avoid coupling public/admin JS):

```js
$(document).on('click', '[data-tab="riwayat"]', loadRiwayatAdmin);
$('#riwayat-apply').on('click', loadRiwayatAdmin);
$('#riwayat-reset').on('click', function() {
  $('#riwayat-aksi').val('');
  $('#riwayat-dari').val('');
  $('#riwayat-sampai').val('');
  loadRiwayatAdmin();
});

$('#riwayat-prune-btn').on('click', function() {
  const sebelum = prompt('Hapus log sebelum tanggal (YYYY-MM-DD):');
  if (!sebelum) return;
  if (!/^\d{4}-\d{2}-\d{2}$/.test(sebelum)) { alert('Format tanggal tidak valid.'); return; }
  if (!confirm(`Hapus permanen semua log sebelum ${sebelum}?`)) return;
  $.post('src/api/admin.php?action=prune_riwayat', {sebelum}, function(res) {
    if (res && res.ok) { alert(`Dihapus: ${res.deleted} entri`); loadRiwayatAdmin(); }
    else alert('Gagal: ' + (res && res.error ? res.error : 'unknown'));
  }, 'json').fail(function(xhr) { alert('Gagal: HTTP ' + xhr.status); });
});

function loadRiwayatAdmin() { /* identical body to loadRiwayat — duplicate small, not generalize */ }
```

## 10. CSS — `assets/css/style.css`

Tambah badge classes (cek apakah sudah ada, jika belum):

```css
.badge-tambah       { background: var(--surface-2); color: #27a644; padding: 2px 8px; border-radius: 9999px; font-size: 12px; }
.badge-edit         { background: var(--surface-2); color: #5e6ad2; padding: 2px 8px; border-radius: 9999px; font-size: 12px; }
.badge-hapus        { background: var(--surface-2); color: #ef4444; padding: 2px 8px; border-radius: 9999px; font-size: 12px; }
.badge-update_status{ background: var(--surface-2); color: #f59e0b; padding: 2px 8px; border-radius: 9999px; font-size: 12px; }
.badge-neutral      { background: var(--surface-2); color: var(--ink-muted); padding: 2px 8px; border-radius: 9999px; font-size: 12px; }
```

Jika badge classes sudah ada (untuk modul badge yang sudah ada), reuse + tambah yang missing. Cek saat implementasi.

## 11. Validation Summary

| Field | Rule | Enforced at |
|---|---|---|
| `dari`, `sampai` (public filter) | optional YYYY-MM-DD; ignore malformed | API (public get_riwayat) |
| `aksi` (public filter) | optional, must be one of valid enums; ignore invalid | API (public get_riwayat) |
| `sebelum` (admin prune) | required, YYYY-MM-DD, not > today+30d | API (admin prune_riwayat) |
| `modul`, `aksi` (log row) | enum values listed in §5 | helper call sites |
| `ringkasan` | VARCHAR(500), max 500 chars | truncate at log site if needed |

## 12. Error Handling

- `log_activity()` failure (DB down) → caught, swallowed. User-facing CRUD tetap sukses.
- Public `get_riwayat` malformed params → ignored, return 50 latest.
- Admin `prune_riwayat` missing/invalid `sebelum` → HTTP 400 with `{error: "..."}`.
- Prune di luar window 30 hari → HTTP 400.
- Empty log result → return `[]` (empty array), HTTP 200.
- Auth missing on admin `prune_riwayat` → HTTP 403 (existing guard).

## 13. Testing

### `tests/test_riwayat.php`

Pattern: single PHP script, run via `php tests/test_riwayat.php`. Print "PASS" lines, "FAIL" lines, exit code 0/1.

Steps:
1. `require_once bootstrap.php` → get `$pdo`.
2. `TRUNCATE activity_log`.
3. Insert 5 dummy rows via `log_activity()`:
   - 3× `siswa / tambah`
   - 1× `siswa / edit`
   - 1× `siswa / hapus`
4. Simulate public GET: `$_GET = ['action' => 'get_riwayat']`, `ob_start` + `@include src/api/public.php`, capture output, decode JSON, assert count === 5.
5. Filter `aksi=tambah`: `$_GET = ['action' => 'get_riwayat', 'aksi' => 'tambah']`, assert count === 3.
6. Filter `dari=<tomorrow>`: assert count === 0.
7. Simulate admin prune: set `$_SESSION['admin_logged'] = true` + admin_username/nama (auth guard satisfied). Run `prune_riwayat` with `sebelum = date('Y-m-d', strtotime('+1 day'))`. After: SELECT count from activity_log → assert === 0.
8. Cleanup: `TRUNCATE activity_log`.

For prune simulation: pattern matches `tests/api/kasbon.php` line 69-78 (`call()` helper that sets `$_POST`/`$_REQUEST`, includes admin.php, captures output).

If `log_activity()` requires `$_SESSION['admin_username']`/`admin_nama`, set them in the test before calling:
```php
$_SESSION['admin_username'] = 'admin';
$_SESSION['admin_nama'] = 'Bendahara RPL 1';
```

Exit code 0 on all PASS, 1 on any FAIL.

## 14. File Touch List

| File | Change |
|---|---|
| `database/migrations/2026_08_05_000001_create_activity_log_table.php` | NEW — table creation |
| `database/schema.sql` | Append `CREATE TABLE activity_log` (so fresh installs include it; mirrors kas_bms pattern) |
| `src/lib/activity_log.php` | NEW — helper function |
| `src/api/public.php` | Add `get_riwayat` case |
| `src/api/admin.php` | `require_once` helper; add `prune_riwayat` case; insert 16 `log_activity()` calls in existing cases |
| `src/auth/login.php` | Verify `$_SESSION['admin_username']` and `$_SESSION['admin_nama']` are set on login; add if missing |
| `index.php` | Add sidebar item + tab content (filter bar + table wrap) |
| `src/admin/dashboard.php` | Add sidebar item + section (with prune button) |
| `assets/js/public.js` | Add `loadRiwayat()` + handlers |
| `assets/js/admin.js` | Add `loadRiwayatAdmin()` + prune handler |
| `assets/css/style.css` | Add badge-* classes if not present |
| `tests/test_riwayat.php` | NEW — test file |

12 files total. All reuse existing patterns.

## 15. Out of Scope / Future

- Per-row detail view (klik row → modal dengan full info).
- Export to CSV/PDF.
- Search by ringkasan text.
- Auto-prune cron.
- Diff before/after.
- Multi-user audit (currently only 1 bendahara).
