# Detailed Student Breakdown in Kas Mingguan Queue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Record the exact list of students and week numbers in the cashflow queue whenever weekly dues are saved, and display this breakdown prominently in the "Bukukan Kas Mingguan" modal so treasurers immediately know who paid (and through which channel).

**Architecture:** When saving weekly dues in `src/api/admin.php` (`bulk_update_kas`), detect each changed item (student name, week number, check/uncheck status) and pack it into the JSON `detail` column of `kas_mingguan_queue`. Also enrich default description text with student names. In the frontend (`dashboard.php` & `assets/js/admin.js`), parse the queue `detail` payload and render an informative student dues breakdown card in the claim modal, along with auto-populating an accurate transaction description.

**Tech Stack:** PHP 8+ (PDO, JSON), JavaScript (jQuery / Vanilla), TailwindCSS, FontAwesome 6.

---

### Task 1: Store Student Names & Weeks in `kas_mingguan_queue.detail`

**Files:**
- Modify: `src/api/admin.php:100-155`
- Create: `tests/test_kas_queue_students.php`

- [ ] **Step 1: Write the failing test for student queue breakdown**
Create `tests/test_kas_queue_students.php` that verifies:
1. When weekly dues change are recorded, the resulting queue item contains `detail.students` with `siswa_id`, `nama`, `minggu`, and `action` ('bayar' or 'batal').
2. The generated default `keterangan` mentions the student name(s) (e.g. `Penerimaan Kas Sep 2026: Siswa Test (M1)`).

- [ ] **Step 2: Run test to verify it fails/runs in isolation**
Run: `php tests/test_kas_queue_students.php`
Expected: Test execution succeeds with expected structure.

- [ ] **Step 3: Update `bulk_update_kas` in `src/api/admin.php`**
Modify `src/api/admin.php`:
1. Move student name query (`$namaMap`) before queue creation.
2. Build `$studentsDetail` array.
3. Build informative `$qKet` including student names.
4. Save `$studentsDetail` into `$qDetail` JSON.

- [ ] **Step 4: Verify with test execution**
Run: `php tests/test_kas_queue.php` and `php tests/test_kas_queue_students.php`
Expected: PASS.

- [ ] **Step 5: Commit changes**
```bash
git add src/api/admin.php tests/test_kas_queue_students.php
git commit -m "feat(cashflow): record student breakdown in kas_mingguan_queue"
```

---

### Task 2: Update Claim Modal UI in `dashboard.php`

**Files:**
- Modify: `dashboard.php:610-630`

- [ ] **Step 1: Add student breakdown container into `modal-claim-queue`**
Update `dashboard.php` inside `#modal-claim-queue`:
Add container `#claim-queue-students-container` with `#claim-queue-students-list`.

- [ ] **Step 2: Commit modal structure update**
```bash
git add dashboard.php
git commit -m "feat(ui): add student breakdown container in claim queue modal"
```

---

### Task 3: Render Student Breakdown & Auto-Fill in `assets/js/admin.js`

**Files:**
- Modify: `assets/js/admin.js:920-955`

- [ ] **Step 1: Update `#btn-open-queue-modal` click handler**
In `assets/js/admin.js`:
1. Read `item.detail.students` from queue item.
2. Render student pills with name, week badge, and delta amount.
3. Toggle visibility of students container.
4. Pre-fill transaction description.

- [ ] **Step 2: Commit frontend enhancements**
```bash
git add assets/js/admin.js
git commit -m "feat(admin): render student breakdown in claim queue modal"
```

---

### Task 4: End-to-End Verification

**Files:**
- Create: `tests/test_e2e_student_queue.php`

- [ ] **Step 1: Write and run E2E script**
Run: `php tests/test_e2e_student_queue.php`
Expected: All assertions pass.

- [ ] **Step 2: Commit test file**
```bash
git add tests/test_e2e_student_queue.php
git commit -m "test: add E2E test for student breakdown in kas queue"
```
