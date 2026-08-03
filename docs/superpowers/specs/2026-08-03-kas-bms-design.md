# Kas BMS — Design Spec

**Date:** 2026-08-03
**Status:** Approved
**Scope:** Add a new `kas_bms` module to the existing Keuangan Kelas RPL 1 application. Reuse existing patterns (jurnal_kas, mutasi_bank, kasbon). One new table, three new API endpoints (one public, three admin), one new public tab, one new admin section, two new test files.

## 1. Purpose

A separate ledger for "Kas BMS" (uang kas yang dikelola oleh program/dana BMS — distinct from `kas_mingguan` iuran siswa, `jurnal_kas` arus kas umum, and `mutasi_bank` setor/tarik ke rekening). Bendahara records setor (uang masuk) and tarik (uang keluar). Siswa/wali can view the running balance and history for transparency.

## 2. Goals & Non-Goals

**Goals**
- New `kas_bms` table holds setor/tarik records with date, description, type, amount.
- Public SPA shows read-only table + 3 summary cards + date-range filter.
- Admin dashboard has CRUD: add, edit, delete via modal form.
- Consistent with existing module patterns (validation, error responses, styling).

**Non-Goals (YAGNI)**
- No per-BMS export / per-row report.
- No soft delete / archive.
- No audit log / change history.
- No notifications or reminders.
- No saldo carried forward from `saldo_awal` config — saldo is computed on-the-fly from the kas_bms table itself.

## 3. Data Model

### Table `kas_bms`

```sql
CREATE TABLE kas_bms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATE NOT NULL,
    keterangan TEXT NOT NULL,
    jenis ENUM('setor','tarik') NOT NULL,
    jumlah DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kas_bms_tanggal (tanggal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Rules:**
- `jumlah` is always > 0 (enforced at API layer).
- One row = one direction. If a day has both a setor and a tarik, use two rows.
- No foreign keys — table is self-contained, same as `jurnal_kas` and `mutasi_bank`.
- `saldo` is NOT stored. Computed: `SUM(jumlah WHERE jenis='setor') - SUM(jumlah WHERE jenis='tarik')` for the filtered range (or all rows if no filter).

### Migration

Append the `CREATE TABLE kas_bms` block to `database/schema.sql` after the `kasbon` table definition. No separate migration file — the project uses `schema.sql` as the single source.

## 4. API

### Public API — `src/api/public.php`

**`GET ?action=get_bms`**
- Optional query params: `dari` (YYYY-MM-DD), `sampai` (YYYY-MM-DD).
- If both bounds present and `dari > sampai`, return empty result + zero totals (no error).
- Response shape:
  ```json
  {
    "rows": [
      { "id": 1, "tanggal": "2026-08-01", "keterangan": "...", "jenis": "setor", "jumlah": "50000.00" }
    ],
    "totals": { "setor": "50000.00", "tarik": "0.00", "saldo": "50000.00" }
  }
  ```
- Order: `tanggal DESC, id DESC`.
- No auth required.

### Admin API — `src/api/admin.php`

All endpoints sit below the existing `$_SESSION['admin_logged']` guard (HTTP 403 if missing). Add three cases to the switch:

**`POST ?action=add_bms`**
- Body: `tanggal` (date, default today if empty), `keterangan` (trimmed, required), `jenis` (`setor`|`tarik`), `jumlah` (float > 0).
- Validation: empty `keterangan` OR `jenis` not in `['setor','tarik']` OR `jumlah <= 0` → HTTP 400 `{error: "invalid"}`.
- Success: insert row, return `{ok: true, id: <lastInsertId>}`.

**`POST ?action=update_bms`**
- Body: `id` (int), `tanggal`, `keterangan`, `jenis`, `jumlah`.
- Same validation as add. Updates row by id. Returns `{ok: true}`.
- If `id` not found, UPDATE matches 0 rows — no error, matches existing `update_jurnal` pattern (silent no-op).

**`DELETE ?action=delete_bms`**
- Body or query: `id` (int).
- DELETE by id. Returns `{ok: true}`.
- If `id` not found, matches 0 rows — no error, matches `delete_jurnal` pattern.

**Error pattern (consistent with existing admin endpoints):** HTTP 400 + `{error: "..."}` for validation, HTTP 500 from the global `catch (Throwable $e)` for DB errors, HTTP 403 for missing session.

## 5. UI — Public SPA (`index.php`)

### Sidebar

Add menu item "Kas BMS" in the navigation list. Position: between existing items, consistent with the linear list order. Trigger: `data-tab="bms"`.

### Tab content — `data-tab-content="bms"`

**1. Summary cards (3-up grid)**
- Total Setor — green badge / `semantic-success` color.
- Total Tarik — muted text.
- Saldo Akhir — `ink` color, prominent.
- Currency format: IDR, e.g. `Rp 50.000`. Use the same formatter already used by other tabs (`Intl.NumberFormat('id-ID', ...)` or existing helper).

**2. Filter bar**
- Two `<input type="date">`: `dari`, `sampai`.
- Button "Terapkan" → re-fetch with query params.
- Button "Reset" → clear inputs, re-fetch without params.

**3. Table**
- Columns: `Tanggal` (YYYY-MM-DD or DD/MM/YYYY per project convention) | `Keterangan` | `Jenis` (badge: setor=green, tarik=muted) | `Jumlah` (right-aligned, formatted).
- Default order: tanggal DESC.
- Empty state: row spanning all columns with text "Belum ada data kas BMS."

**4. Behavior (JS)**
- On tab open, call `get_bms` with no filter, render table + cards.
- On filter change, call `get_bms?dari=...&sampai=...`, re-render.
- Reuse existing table-render helper from `app_public.js` (the one used for kasbon/mutasi_bank). If helper signature does not match, write a small local renderer; do not generalize prematurely.

## 6. UI — Admin Dashboard (`src/admin/dashboard.php`)

### Sidebar

Add "Kas BMS" menu item. Trigger: `data-section="bms"`.

### Section — `data-section="bms"`

**1. Header bar**
- Title: "Kas BMS".
- Primary button: "Tambah Transaksi" — opens the modal form.

**2. Table**
- Columns: `Tanggal` | `Keterangan` | `Jenis` (badge) | `Jumlah` | `Aksi` (Edit icon + Hapus icon).
- Order: tanggal DESC.
- Edit icon → open modal form pre-filled with row data, mode = update.
- Hapus icon → `confirm("Hapus transaksi ini?")`, then `DELETE delete_bms?id=...`, refresh table.

**3. Modal form (re-use the same modal pattern as `add_jurnal`)**
- Hidden `data-id` field — empty in add mode, set in edit mode.
- Fields:
  - `tanggal` (`<input type="date">`, default today).
  - `keterangan` (`<input type="text">`, required).
  - `jenis` (radio: `setor` / `tarik`).
  - `jumlah` (`<input type="number" min="1" step="1">`, required).
- Buttons: "Simpan" (submit), "Batal" (close).
- Submit handler:
  - If `data-id` empty → `POST add_bms`.
  - If `data-id` set → `POST update_bms` (also send `id` in body).
- On success: close modal, refresh table.
- On error: show inline error message from API response.

**4. JS behavior (`app_admin.js`)**
- Reuse existing helpers `bindAjaxForm` and `bindDeleteButton` if their signature fits. Otherwise write small local bindings — match the local-render decision in section 5.
- No new global state object needed; module is stateless across renders.

## 7. Validation Summary

| Field | Rule | Enforced at |
|---|---|---|
| `keterangan` | trimmed, non-empty | API (admin add/update) |
| `jenis` | one of `setor`, `tarik` | API (admin add/update) |
| `jumlah` | float, > 0 | API (admin add/update), HTML `min="1"` |
| `tanggal` | YYYY-MM-DD or empty (defaults today) | API (admin add/update) |
| `id` (update/delete) | int, > 0 | API (admin update/delete) |
| `dari`, `sampai` (public filter) | optional YYYY-MM-DD; no cross-validation | API (public get_bms) |

## 8. Error Handling

- Validation errors → HTTP 400 + `{error: "..."}`. Frontend surfaces inline.
- DB errors → HTTP 500 from global catch. Frontend shows generic error, logs detail.
- Auth missing on admin → HTTP 403 + `{error: "unauthorized"}` (existing behavior).
- Empty data → return empty array + zero totals, HTTP 200.
- Invalid filter range (`dari > sampai`) → return empty data, no error.
- Missing update/delete id → silent no-op (matches `update_jurnal`/`delete_jurnal` pattern — project-wide convention).

## 9. Testing

### `tests/api/test_bms_public.php`

1. `get_bms` on empty table → `rows: []`, `totals: {setor: "0.00", tarik: "0.00", saldo: "0.00"}`.
2. After seeding 2 rows (1 setor, 1 tarik) → list has 2 entries, `totals` correct.
3. `get_bms?dari=X&sampai=Y` → only rows in range returned, totals scoped to range.
4. `get_bms?dari=2099-01-01&sampai=2099-12-31` → empty result, zero totals.

### `tests/api/test_bms_admin.php`

1. `add_bms` without session → HTTP 403.
2. `add_bms` valid → `{ok, id}`, row in DB.
3. `add_bms` invalid (empty ket / bogus jenis / jumlah 0) → HTTP 400.
4. `update_bms` valid → row updated, `get_bms` reflects new values.
5. `update_bms` with non-existent id → HTTP 200 `{ok}`, no DB change.
6. `delete_bms` valid → row gone, `get_bms` reflects.
7. `delete_bms` with non-existent id → HTTP 200 `{ok}`, no error.

### `database/seeds/seed_bms.php` (optional)

3 sample rows (mix of setor and tarik) for manual UI testing. Follow the `seed_kasbon.php` pattern if it exists; otherwise inline in the test bootstrap.

## 10. File Touch List

| File | Change |
|---|---|
| `database/schema.sql` | Append `CREATE TABLE kas_bms` |
| `src/api/public.php` | Add `get_bms` case |
| `src/api/admin.php` | Add `add_bms`, `update_bms`, `delete_bms` cases |
| `index.php` | Add sidebar menu item + tab content |
| `src/admin/dashboard.php` | Add sidebar item + section + modal form |
| `assets/js/app_public.js` (or actual filename) | Add bms loader + filter handler |
| `assets/js/app_admin.js` (or actual filename) | Add form binding + delete handler |
| `tests/api/test_bms_public.php` | New test file |
| `tests/api/test_bms_admin.php` | New test file |

9 files total. All reuse existing patterns.

## 11. Open Questions

None at design time. Concrete filenames for `app_public.js` and `app_admin.js` to be confirmed during implementation (the implementation plan will grep for the actual paths).
