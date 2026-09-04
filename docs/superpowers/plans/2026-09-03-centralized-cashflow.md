# Centralized Cashflow & Money Tracker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the fragmented allocation, cashflow, and history features into a unified, centralized Money Tracker system with accounts, categories, a single ledger, and an uncategorized queue for weekly student dues.

**Architecture:** A single ledger table (`transactions`) backed by dynamic `accounts` (wallets) and `categories`. A centralized PHP engine (`src/lib/FinanceEngine.php`) calculates real-time account balances, total balance, and processes transactions atomically using PDO transactions. Weekly class dues are collected into a pending queue (`kas_mingguan_queue`) before being booked into specific accounts and categories via the Cashflow module.

**Tech Stack:** PHP 8+, MySQL (PDO), TailwindCSS, FontAwesome 6, Vanilla JavaScript.

---

### Task 1: Database Migration & Seeds for New Schema

**Files:**
- Create: `database/migrations/2026_09_03_000001_create_centralized_cashflow_tables.php`
- Modify: `database/schema.sql`
- Test: `database/migrate.php`

- [x] **Step 1: Write the migration script**
Create `database/migrations/2026_09_03_000001_create_centralized_cashflow_tables.php` with:
- `accounts` table: `id`, `name`, `type`, `icon`, `initial_balance`, `is_active`, `sort_order`, `created_at`.
- `categories` table: `id`, `name`, `type`, `icon`, `color`, `is_active`, `created_at`.
- `transactions` table: `id`, `date`, `type`, `account_id`, `to_account_id`, `category_id`, `amount`, `description`, `ref_type`, `ref_id`, `created_by`, `created_at`, `updated_at`.
- `kas_mingguan_queue` table: `id`, `bulan`, `tahun`, `nominal`, `keterangan`, `detail`, `status`, `transaction_id`, `created_at`.
- Initial seed data for standard accounts (Cash, DANA, SeaBank, Mandiri) and categories (Uang Kas Mingguan, LKS & Buku, ATK & Operasional, Acara & Lomba, Dana Sosial, Kasbon Siswa).

- [x] **Step 2: Run migration to create tables**
Run: `php database/migrate.php`
Expected: Migration executes successfully with all tables created and seeded.

- [x] **Step 3: Verify created tables in database**
Run: `php -r "require_once 'config/database.php'; \$stmt = db()->query('SHOW TABLES LIKE \'accounts\''); echo \$stmt->rowCount() ? 'OK' : 'FAIL';"`
Expected: Output `OK`.

- [x] **Step 4: Commit database migration**
Run:
```bash
git add database/migrations/2026_09_03_000001_create_centralized_cashflow_tables.php database/schema.sql
git commit -m "feat(db): add centralized cashflow tables and seeds"
```

---

### Task 2: Centralized Finance Engine (`FinanceEngine.php`)

**Files:**
- Create: `src/lib/FinanceEngine.php`
- Create: `tests/test_finance_engine.php`

- [x] **Step 1: Write test cases for FinanceEngine**
Create `tests/test_finance_engine.php` verifying:
1. `getAccountsWithBalances(PDO $pdo)` calculates initial balance + income - expense + transfer_in - transfer_out.
2. `addTransaction(PDO $pdo, array $data)` validates inputs, ensures non-negative amounts, and handles transfer pairs atomically.
3. `getSummary(PDO $pdo)` computes total active balance and pending uncategorized queue amount accurately.
4. `claimKasQueue(PDO $pdo, int $queueId, int $accountId, int $categoryId, string $adminUser)` books queue into `transactions` and sets queue status to `recorded`.

- [x] **Step 2: Run test to verify it fails initially**
Run: `php tests/test_finance_engine.php`
Expected: FAIL ("FinanceEngine class/file not found").

- [x] **Step 3: Implement `FinanceEngine.php`**
Implement `FinanceEngine` class in `src/lib/FinanceEngine.php` with robust PDO transaction handling and exact financial formulas.

- [x] **Step 4: Run tests to verify they pass**
Run: `php tests/test_finance_engine.php`
Expected: PASS (All test assertions pass).

- [x] **Step 5: Commit FinanceEngine**
Run:
```bash
git add src/lib/FinanceEngine.php tests/test_finance_engine.php
git commit -m "feat(finance): add FinanceEngine core logic and unit tests"
```

---

### Task 3: Admin and Public API Endpoints

**Files:**
- Modify: `src/api/admin.php`
- Modify: `src/api/public.php`
- Test: `tests/test_api_endpoints.php`

- [x] **Step 1: Write API tests**
Create `tests/test_api_endpoints.php` simulating API requests for:
1. `action=get_finance_overview` (accounts, balances, summary, queue).
2. `action=manage_account` (add, edit, toggle active).
3. `action=manage_category` (add, edit, delete).
4. `action=add_transaction`, `action=edit_transaction`, `action=delete_transaction`.
5. `action=claim_kas_queue`.

- [x] **Step 2: Implement Admin API actions in `src/api/admin.php`**
Add the new handlers calling `FinanceEngine`, log activities using `log_activity()`, and remove/deprecate obsolete `storage_allocations` and `kas_bms` actions.

- [x] **Step 3: Implement Public API actions in `src/api/public.php`**
Update public endpoints to serve clean financial summaries, accounts, categories, and unified transaction history without exposing sensitive admin logs.

- [x] **Step 4: Run API tests to verify**
Run: `php tests/test_api_endpoints.php`
Expected: PASS.

- [x] **Step 5: Commit API updates**
Run:
```bash
git add src/api/admin.php src/api/public.php tests/test_api_endpoints.php
git commit -m "feat(api): add centralized cashflow endpoints and deprecate legacy actions"
```

---

### Task 4: Kas Mingguan Queue Integration

**Files:**
- Modify: `src/api/admin.php` (update `update_kas` and `bulk_update_kas`)
- Test: `tests/test_kas_queue.php`

- [x] **Step 1: Write tests for Kas Mingguan queueing**
Test that ticking weeks creates positive queue records, and unticking already recorded weeks generates negative queue records for adjustment.

- [x] **Step 2: Run test to confirm behavior**
Run: `php tests/test_kas_queue.php`
Expected: FAIL before implementation.

- [x] **Step 3: Modify `update_kas` and `bulk_update_kas` in `src/api/admin.php`**
Update logic to automatically insert delta payments into `kas_mingguan_queue` with status `'pending'`.

- [x] **Step 4: Run test to confirm pass**
Run: `php tests/test_kas_queue.php`
Expected: PASS.

- [x] **Step 5: Commit Kas Mingguan queue integration**
Run:
```bash
git add src/api/admin.php tests/test_kas_queue.php
git commit -m "feat(kas): integrate weekly dues into uncategorized cashflow queue"
```

---

### Task 5: Admin Dashboard UI Refactoring (`dashboard.php`)

**Files:**
- Modify: `dashboard.php`
- Modify: `assets/js/admin.js`

- [x] **Step 1: Remove legacy tabs from Sidebar & Content**
Remove Tab Alokasi Dana, Tab Kas BMS, and Tab Dana Talangan/Kasbon terpisah.

- [x] **Step 2: Build new Cashflow Tab (Money Tracker UI)**
1. Account Balances Grid (Total Kas card + individual account cards with icons).
2. Uncategorized Queue Alert banner (with badge count and "Catat ke Akun" modal trigger).
3. Quick Action Buttons: `+ Pemasukan`, `- Pengeluaran`, `⇄ Transfer Antar Akun`, `⚙ Kelola Akun & Kategori`.
4. Unified Transaction History Table with filters (type, account, category, search) and pagination.

- [x] **Step 3: Build Modals for Cashflow Operations**
1. Modal Catat Transaksi (Income / Expense / Transfer with dynamic account and category selects).
2. Modal Catat Antrean Kas Mingguan.
3. Modal Kelola Akun & Kategori (Tabs: Akun & Kategori with inline add/edit).

- [x] **Step 4: Connect JavaScript to API endpoints**
Wire up asynchronous fetch calls, balance updates, form validation, and toast notifications.

- [x] **Step 5: Commit Dashboard UI refactor**
Run:
```bash
git add dashboard.php assets/
git commit -m "feat(ui): implement modern money tracker dashboard and remove legacy tabs"
```

---

### Task 6: Public Page UI Refactoring (`index.php`)

**Files:**
- Modify: `index.php`
- Modify: `assets/js/public.js`

- [x] **Step 1: Clean up public sidebar & navigation**
Remove Alokasi, BMS, and Kasbon tabs from public navigation. Retain: Kas Kelas, Cashflow & Statistik, Riwayat.

- [x] **Step 2: Update public Cashflow & Accounts summary**
Render real-time accounts summary cards and transparent transactions table with category badges.

- [x] **Step 3: Commit Public Page UI refactor**
Run:
```bash
git add index.php assets/
git commit -m "feat(ui): update public interface to reflect centralized cashflow"
```

---

### Task 7: End-to-End Verification & Sanity Check

**Files:**
- Create: `tests/test_e2e_flow.php`

- [x] **Step 1: Write and run end-to-end integration test script**
Test full lifecycle:
1. Initial account balances.
2. Ticking Kas Mingguan -> Queue created.
3. Claiming queue into Cash account with category 'Uang Kas'.
4. Expense transaction out of Cash account.
5. Transfer transaction from Cash to DANA.
6. Verify all balances match exactly and no orphan rows exist.

- [x] **Step 2: Run E2E test**
Run: `php tests/test_e2e_flow.php`
Expected: ALL CHECKS PASSED.

- [x] **Step 3: Clean up temporary test files & finalize**
Run:
```bash
git add tests/
git commit -m "test: add comprehensive end-to-end verification suite"
```
