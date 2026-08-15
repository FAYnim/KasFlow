<?php
session_start();
// Canonical URL: /dashboard/ → /dashboard (301) supaya path relatif (asset, API, logout) selalu resolve dari root
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
if ($reqPath !== '/' && substr($reqPath, -1) === '/') {
    header('Location: ' . rtrim($reqPath, '/'), true, 301);
    exit;
}
if (empty($_SESSION['admin_logged'])) { header('Location: login'); exit; }
$nama = $_SESSION['admin_nama'] ?? 'Bendahara';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bendahara - Cashflow Kelas</title>
    <script>
        (function() {
            const saved = localStorage.getItem('theme');
            const theme = saved ? saved : 'dark';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/print.css" media="print">
</head>
<body class="min-h-screen flex flex-col md:flex-row md:h-screen md:overflow-hidden">
    <!-- Admin Mobile Header Bar -->
    <header class="md:hidden sticky top-0 z-30 bg-[var(--surface-1)] border-b border-[var(--hairline)] px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button id="btn-hamburger" class="p-2 rounded-lg hover:bg-[var(--surface-2)] text-[var(--ink-subtle)] hover:text-[var(--ink)] transition-colors focus:outline-none" aria-label="Toggle Navigation">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <div class="brand-mark gap-2.5">
                <div class="brand-icon">
                    <i class="fa-solid fa-money-bill-wave text-xs"></i>
                </div>
                <span class="text-sm font-semibold">Admin Bendahara</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button id="theme-toggle-btn-mobile" class="btn-secondary p-2 w-9 h-9 flex items-center justify-center rounded-lg cursor-pointer" title="Switch Theme">
                <i id="theme-toggle-icon-mobile" class="fa-solid fa-moon text-indigo-400 text-sm"></i>
            </button>
        </div>
    </header>

    <!-- Overlay Backdrop for Mobile Sidebar -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-40 hidden md:hidden transition-opacity"></div>

    <!-- Admin Sidebar Navigation -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-[var(--surface-1)] border-r border-[var(--hairline)] p-4 flex flex-col justify-between transition-transform duration-300 ease-in-out transform -translate-x-full md:translate-x-0 md:static md:w-60 md:h-screen md:sticky md:top-0 md:z-auto md:flex-shrink-0 md:min-h-0 md:overflow-hidden">
        <div>
            <div class="brand-mark mb-6 px-2 py-1 gap-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="brand-icon">
                        <i class="fa-solid fa-money-bill-wave text-xs"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold">Admin Bendahara</span>
                        <span class="text-[11px] text-[var(--ink-muted)] font-normal">Cashflow RPL 1</span>
                    </div>
                </div>
                <button id="btn-close-sidebar" class="md:hidden p-1.5 rounded-lg text-[var(--ink-muted)] hover:text-[var(--ink)] hover:bg-[var(--surface-2)] transition-colors focus:outline-none" aria-label="Close Navigation">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <div class="eyebrow px-2 text-[11px] mb-2">Manajemen</div>
            <nav class="space-y-0.5">
                <a data-tab="dashboard" class="sidebar-nav-item active">
                    <i class="fa-solid fa-gauge w-4 text-center"></i>
                    <span>Dashboard</span>
                </a>
                <a data-tab="siswa" class="sidebar-nav-item">
                    <i class="fa-solid fa-users w-4 text-center"></i>
                    <span>Kelola Siswa</span>
                </a>
                <a data-tab="kas" class="sidebar-nav-item">
                    <i class="fa-solid fa-money-bill-wave w-4 text-center"></i>
                    <span>Kas Kelas</span>
                </a>
                <a data-tab="bms" class="sidebar-nav-item">
                    <i class="fa-solid fa-sack-dollar w-4 text-center"></i>
                    <span>Kas BMS</span>
                </a>
                <a data-tab="alokasi" class="sidebar-nav-item">
                    <i class="fa-solid fa-vault w-4 text-center"></i>
                    <span>Alokasi Dana</span>
                </a>
                <a data-tab="kasbon" class="sidebar-nav-item">
                    <i class="fa-solid fa-hand-holding-dollar w-4 text-center"></i>
                    <span>Dana Talangan</span>
                </a>
                <a data-tab="riwayat" class="sidebar-nav-item">
                    <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>
                    <span>Riwayat</span>
                </a>
                <a data-tab="jurnal" class="sidebar-nav-item">
                    <i class="fa-solid fa-receipt w-4 text-center"></i>
                    <span>Cashflow</span>
                </a>
                <a data-tab="ekspor" class="sidebar-nav-item">
                    <i class="fa-solid fa-file-export w-4 text-center"></i>
                    <span>Ekspor Laporan</span>
                </a>
            </nav>
        </div>

        <div class="mt-8 pt-4 border-t border-[var(--hairline)]">
            <a href="logout" class="btn-danger w-full justify-center gap-2">
                <i class="fa-solid fa-right-from-bracket text-xs"></i>
                <span>Keluar (Logout)</span>
            </a>
        </div>
    </aside>

    <!-- Main Admin Content -->
    <main class="flex-1 p-6 md:p-8 max-w-6xl md:h-screen md:overflow-y-auto">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-[var(--hairline)]">
            <div>
                <div class="eyebrow">Sesi Aktif</div>
                <div class="text-sm text-[var(--ink-muted)]">Login sebagai <b class="text-[var(--ink)]"><?= htmlspecialchars($nama) ?></b></div>
            </div>
            <div class="flex items-center gap-2">
                <button id="theme-toggle-btn" class="btn-secondary p-2 w-9 h-9 flex items-center justify-center rounded-lg cursor-pointer" title="Switch Theme">
                    <i id="theme-toggle-icon" class="fa-solid fa-moon text-indigo-400 text-sm"></i>
                </button>
                <a href="index" target="_blank" class="btn-secondary text-xs gap-2">
                    <span>Buka Web Publik</span>
                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- Section: Dashboard -->
        <section data-tab-content="dashboard" class="tab-content">
            <h2 class="display-md mb-4">Dashboard Admin</h2>
            <div id="admin-summary" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"></div>
        </section>

        <!-- Section: Kelola Siswa -->
        <section data-tab-content="siswa" class="tab-content hidden">
            <h2 class="display-md mb-2">Kelola Siswa</h2>
            <p class="text-sm text-[var(--ink-muted)] mb-4">Tambah dan hapus daftar siswa kelas RPL 1.</p>
            <form id="form-siswa" class="flex flex-col sm:flex-row gap-2 mb-6 card-linear p-4">
                <input name="absen" placeholder="Absen (opsional)" class="input-linear w-full sm:w-44">
                <input name="nama" placeholder="Nama lengkap siswa" required class="input-linear flex-1">
                <button class="btn-primary gap-2">
                    <i class="fa-solid fa-user-plus text-xs"></i>
                    <span>Tambah Siswa</span>
                </button>
            </form>
            <div id="siswa-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Input Kas -->
        <section data-tab-content="kas" class="tab-content hidden">
            <h2 class="display-md mb-2">Input Kas Mingguan</h2>
            <p class="text-sm text-[var(--ink-muted)] mb-4">Centang checkbox untuk mencatat pembayaran kas siswa. Perubahan belum tersimpan sampai klik <b>Simpan</b>.</p>
            <div class="flex gap-3 mb-4">
                <select id="admin-bulan" class="input-linear w-44"></select>
                <select id="admin-tahun" class="input-linear w-32"></select>
            </div>
            <div class="flex items-center gap-2 mb-3">
                <span id="kas-pending-badge" class="hidden text-xs px-2 py-1 rounded-md bg-amber-500/15 text-amber-300 border border-amber-500/30">
                    <i class="fa-solid fa-circle-exclamation text-[10px] mr-1"></i>
                    <span id="kas-pending-count">0</span> perubahan belum disimpan
                </span>
                <button id="kas-reset-btn" type="button" class="btn-secondary text-xs gap-2 hidden">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i>
                    <span>Reset</span>
                </button>
                <button id="kas-save-btn" type="button" class="btn-primary text-xs gap-2 hidden">
                    <i class="fa-solid fa-floppy-disk text-[10px]"></i>
                    <span>Simpan</span>
                </button>
            </div>
            <div id="kas-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Kelola Jurnal -->
        <section data-tab-content="jurnal" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="display-md">Kelola Jurnal Kas</h2>
                    <p class="text-sm text-[var(--ink-muted)]">Catat pengeluaran dan pemasukan kas secara akurat.</p>
                </div>
                <button id="btn-add-jurnal" class="btn-primary gap-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Tambah Transaksi</span>
                </button>
            </div>
            <div class="flex flex-wrap gap-2 mb-4 items-end">
                <div>
                    <label class="eyebrow block mb-1">Bulan</label>
                    <select id="jurnal-bulan" class="input-linear w-44"></select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Tahun</label>
                    <select id="jurnal-tahun" class="input-linear w-32"></select>
                </div>
                <button id="jurnal-reset" type="button" class="btn-secondary text-xs gap-2">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i>
                    <span>Semua Periode</span>
                </button>
            </div>
            <div id="jurnal-wrap" class="table-container overflow-x-auto"></div>
            <div id="jurnal-pagination"></div>
        </section>

        <!-- Section: Ekspor Laporan -->
        <section data-tab-content="ekspor" class="tab-content hidden">
            <h2 class="display-md mb-2">Ekspor Laporan Kas</h2>
            <p class="text-sm text-[var(--ink-muted)] mb-4">Cetak atau simpan data jurnal kas ke format CSV / PDF.</p>
            <form id="form-ekspor" class="flex flex-col sm:flex-row gap-3 items-end mb-6 card-linear p-4">
                <div class="w-full sm:w-48">
                    <label class="eyebrow block mb-1">Dari Tanggal</label>
                    <input type="date" name="dari" class="input-linear">
                </div>
                <div class="w-full sm:w-48">
                    <label class="eyebrow block mb-1">Sampai Tanggal</label>
                    <input type="date" name="sampai" class="input-linear">
                </div>
                <button class="btn-secondary gap-2" id="btn-csv">
                    <i class="fa-solid fa-file-csv text-xs text-[#60a5fa]"></i>
                    <span>Unduh CSV</span>
                </button>
                <button type="button" class="btn-primary gap-2" id="btn-pdf">
                    <i class="fa-solid fa-file-pdf text-xs"></i>
                    <span>Cetak PDF</span>
                </button>
            </form>
            <div id="ekspor-preview" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Kelola Kasbon -->
        <section data-tab-content="kasbon" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="display-md">Kelola Dana Talangan</h2>
                    <p class="text-sm text-[var(--ink-muted)]">Catat dan kelola dana talangan (reimbursement) siswa.</p>
                </div>
            </div>

            <div class="card-linear p-4 mb-6">
                <form id="form-kasbon" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <input type="hidden" id="kasbon-edit-id" value="">
                    <div>
                        <label class="eyebrow block mb-1">Nama *</label>
                        <input type="text" id="kasbon-nama" required class="input-linear">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Tanggal *</label>
                        <input type="date" id="kasbon-tanggal" required class="input-linear">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Keterangan *</label>
                        <input type="text" id="kasbon-keterangan" required class="input-linear">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Jumlah *</label>
                        <input type="number" id="kasbon-jumlah" min="1" step="any" required class="input-linear">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Status</label>
                        <select id="kasbon-status" class="input-linear">
                            <option value="belum_lunas">Belum Diganti</option>
                            <option value="lunas">Sudah Diganti</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-5 flex gap-2">
                        <button type="submit" id="kasbon-submit-btn" class="btn-primary gap-2">
                            <i class="fa-solid fa-plus text-xs"></i>
                            <span>Tambah</span>
                        </button>
                        <button type="button" id="kasbon-cancel-btn" class="hidden btn-secondary gap-2">
                            <i class="fa-solid fa-xmark text-xs"></i>
                            <span>Batal</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="flex flex-wrap gap-2 mb-4 items-end">
                <div>
                    <label class="eyebrow block mb-1">Bulan</label>
                    <select id="admin-kasbon-bulan" class="input-linear w-44"></select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Tahun</label>
                    <select id="admin-kasbon-tahun" class="input-linear w-32"></select>
                </div>
            </div>

            <div id="kasbon-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Kas BMS -->
        <section data-tab-content="bms" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="display-md">Kas BMS</h2>
                    <p class="text-sm text-[var(--ink-muted)]">Catat dan kelola dana BMS (setor/tarik).</p>
                </div>
                <button id="bms-add-btn" class="btn-primary gap-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Tambah Transaksi</span>
                </button>
            </div>

            <div id="bms-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Alokasi Dana -->
        <section data-tab-content="alokasi" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="display-md">Alokasi Dana</h2>
                    <p class="text-sm text-[var(--ink-muted)]">Pecah dana masuk ke beberapa tempat simpan, atau transfer antar akun.</p>
                </div>
                <div class="flex gap-2 flex-wrap">
                    <button id="alokasi-add-btn" class="btn-primary gap-2">
                        <i class="fa-solid fa-plus text-xs"></i><span>Alokasi Baru</span>
                    </button>
                    <button id="alokasi-transfer-btn" class="btn-secondary gap-2">
                        <i class="fa-solid fa-arrow-right-arrow-left text-xs"></i><span>Transfer</span>
                    </button>
                    <button id="alokasi-manage-accounts-btn" class="btn-secondary gap-2">
                        <i class="fa-solid fa-gear text-xs"></i><span>Kelola Akun Simpan</span>
                    </button>
                </div>
            </div>

            <div id="alokasi-accounts" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 mb-6"></div>

            <div class="card-linear p-4 mb-3">
                <div class="flex flex-wrap gap-2 items-end">
                    <div>
                        <span class="eyebrow block mb-1">Dari</span>
                        <input type="date" id="alokasi-dari" class="input-linear">
                    </div>
                    <div>
                        <span class="eyebrow block mb-1">Sampai</span>
                        <input type="date" id="alokasi-sampai" class="input-linear">
                    </div>
                    <div class="flex-1 min-w-[160px]">
                        <span class="eyebrow block mb-1">Keterangan</span>
                        <input type="text" id="alokasi-keterangan-search" placeholder="Cari keterangan…" class="input-linear w-full">
                    </div>
                    <button id="alokasi-apply" class="btn-primary text-xs gap-2">
                        <i class="fa-solid fa-filter text-[10px]"></i> <span>Terapkan</span>
                    </button>
                    <button id="alokasi-reset" class="btn-secondary text-xs gap-2">
                        <i class="fa-solid fa-rotate-left text-[10px]"></i> <span>Reset</span>
                    </button>
                </div>
            </div>
            <div id="alokasi-allocations-wrap" class="table-container overflow-x-auto"></div>
            <div id="alokasi-allocations-pagination"></div>

            <div class="mt-8 mb-3"><h3 class="headline">Histori Transfer</h3></div>
            <div id="alokasi-transfers-wrap" class="table-container overflow-x-auto"></div>
            <div id="alokasi-transfers-pagination"></div>
        </section>

        <!-- Section: Riwayat -->
        <section data-tab-content="riwayat" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="display-md">Riwayat Aktivitas</h2>
                    <p class="text-sm text-[var(--ink-muted)] mt-1">Jejak perubahan data. Hapus log lama untuk kontrol ukuran.</p>
                </div>
                <button id="riwayat-prune-btn" type="button" class="btn-secondary text-xs gap-2">
                    <i class="fa-solid fa-broom text-[10px]"></i>
                    <span>Hapus Log Lama…</span>
                </button>
            </div>
            <div class="flex flex-wrap gap-2 mb-4 items-end">
                <label class="text-xs text-[var(--ink-muted)]">
                    <span class="block mb-1">Aksi</span>
                    <select id="riwayat-aksi" class="input-linear">
                        <option value="">Semua</option>
                        <option value="tambah">Tambah</option>
                        <option value="edit">Edit</option>
                        <option value="hapus">Hapus</option>
                        <option value="update_status">Update Status</option>
                    </select>
                </label>
                <label class="text-xs text-[var(--ink-muted)]">
                    <span class="block mb-1">Dari</span>
                    <input type="date" id="riwayat-dari" class="input-linear">
                </label>
                <label class="text-xs text-[var(--ink-muted)]">
                    <span class="block mb-1">Sampai</span>
                    <input type="date" id="riwayat-sampai" class="input-linear">
                </label>
                <button id="riwayat-apply" type="button" class="btn-primary text-xs gap-2">
                    <i class="fa-solid fa-filter text-[10px]"></i> <span>Terapkan</span>
                </button>
                <button id="riwayat-reset" type="button" class="btn-secondary text-xs gap-2">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i> <span>Reset</span>
                </button>
            </div>
            <div id="riwayat-wrap" class="table-container overflow-x-auto">
                <div class="text-center py-6 text-[var(--ink-muted)]">Pilih tab Riwayat untuk memuat data.</div>
            </div>
            <div id="riwayat-pagination"></div>
        </section>
    </main>

    <!-- Modal Form Transaksi Jurnal -->
    <div id="modal-jurnal" class="modal-overlay hidden">
        <form id="form-jurnal" class="modal-card">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[var(--hairline)]">
                <h3 class="headline text-lg flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-sm text-[var(--primary)]"></i>
                    <span>Transaksi Jurnal</span>
                </h3>
                <button type="button" id="modal-close" class="text-[var(--ink-muted)] hover:text-[var(--ink)]">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <input type="hidden" name="id">
            <div class="space-y-3 mb-6">
                <div>
                    <label class="eyebrow block mb-1">Tanggal</label>
                    <input type="date" name="tanggal" required class="input-linear" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Keterangan</label>
                    <input name="keterangan" required placeholder="Contoh: Beli spidol & kertas" class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Jenis Transaksi</label>
                    <select name="jenis" class="input-linear">
                        <option value="masuk">Pemasukan (Masuk)</option>
                        <option value="keluar">Pengeluaran (Keluar)</option>
                    </select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Nominal (Rp)</label>
                    <input type="number" name="nominal" required min="1" placeholder="0" class="input-linear">
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="modal-close-btn" class="btn-secondary">Batal</button>
                <button class="btn-primary gap-2">
                    <i class="fa-solid fa-floppy-disk text-xs"></i>
                    <span>Simpan Transaksi</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Form Kas BMS -->
    <div id="bms-modal" class="modal-overlay hidden">
        <form id="bms-form" class="modal-card">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[var(--hairline)]">
                <h3 class="headline text-lg flex items-center gap-2">
                    <i class="fa-solid fa-sack-dollar text-sm text-[var(--primary)]"></i>
                    <span>Form Kas BMS</span>
                </h3>
                <button type="button" id="bms-modal-close" class="text-[var(--ink-muted)] hover:text-[var(--ink)]">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <input type="hidden" id="bms-edit-id" value="">
            <div class="space-y-3 mb-6">
                <div>
                    <label class="eyebrow block mb-1">Tanggal</label>
                    <input type="date" id="bms-tanggal" required class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Keterangan</label>
                    <input type="text" id="bms-keterangan" required placeholder="Contoh: Dana BMS masuk" class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Jenis</label>
                    <div class="flex gap-4 mt-1">
                        <label class="inline-flex items-center gap-2 text-sm text-[var(--ink)]">
                            <input type="radio" name="bms-jenis" value="setor" checked> Setor
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-[var(--ink)]">
                            <input type="radio" name="bms-jenis" value="tarik"> Tarik
                        </label>
                    </div>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Jumlah (Rp)</label>
                    <input type="number" id="bms-jumlah" min="1" step="1" required placeholder="0" class="input-linear">
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="bms-cancel-btn" class="btn-secondary">Batal</button>
                <button type="submit" id="bms-submit-btn" class="btn-primary gap-2">
                    <i class="fa-solid fa-floppy-disk text-xs"></i>
                    <span>Simpan</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Form Alokasi Dana -->
    <div id="modal-alokasi" class="modal-overlay hidden">
        <form id="form-alokasi" class="modal-card">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[var(--hairline)]">
                <h3 class="headline text-lg flex items-center gap-2">
                    <i class="fa-solid fa-vault text-sm text-[var(--primary)]"></i>
                    <span>Form Alokasi Dana</span>
                </h3>
                <button type="button" id="alokasi-modal-close" class="text-[var(--ink-muted)] hover:text-[var(--ink)]">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <input type="hidden" id="alokasi-edit-id" value="">
            <div class="space-y-3 mb-6">
                <div>
                    <label class="eyebrow block mb-1">Tanggal</label>
                    <input type="date" id="alokasi-tanggal" required class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Sumber Dana</label>
                    <select id="alokasi-ref_type" class="input-linear">
                        <option value="bms_setor">Setor BMS</option>
                        <option value="bms_tarik">Tarik BMS</option>
                        <option value="kas_mingguan">Kas Mingguan</option>
                        <option value="manual">Manual / Lainnya</option>
                    </select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Keterangan</label>
                    <input type="text" id="alokasi-keterangan" placeholder="Contoh: Hasil iuran minggu 3" class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Total Nominal (Rp)</label>
                    <input type="number" id="alokasi-total" min="1" step="any" required placeholder="0" class="input-linear">
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="eyebrow">Pembagian ke Akun</label>
                        <button type="button" id="alokasi-add-line" class="btn-secondary text-xs gap-1">
                            <i class="fa-solid fa-plus text-[10px]"></i><span>Tambah Baris</span>
                        </button>
                    </div>
                    <div id="alokasi-lines" class="space-y-2"></div>
                    <div class="text-xs text-[var(--ink-muted)] mt-2">Sisa belum dialokasikan: <b id="alokasi-remaining">Rp 0</b></div>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="alokasi-cancel-btn" class="btn-secondary">Batal</button>
                <button type="submit" id="alokasi-submit-btn" class="btn-primary gap-2">
                    <i class="fa-solid fa-floppy-disk text-xs"></i><span>Simpan Alokasi</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Form Transfer -->
    <div id="modal-transfer" class="modal-overlay hidden">
        <form id="form-transfer" class="modal-card">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[var(--hairline)]">
                <h3 class="headline text-lg flex items-center gap-2">
                    <i class="fa-solid fa-arrow-right-arrow-left text-sm text-[var(--primary)]"></i>
                    <span>Form Transfer</span>
                </h3>
                <button type="button" id="transfer-modal-close" class="text-[var(--ink-muted)] hover:text-[var(--ink)]">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="space-y-3 mb-6">
                <div>
                    <label class="eyebrow block mb-1">Tanggal</label>
                    <input type="date" id="transfer-tanggal" required class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Dari Akun</label>
                    <select id="transfer-from" class="input-linear"></select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Ke Akun</label>
                    <select id="transfer-to" class="input-linear"></select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Nominal (Rp)</label>
                    <input type="number" id="transfer-nominal" min="1" step="any" required placeholder="0" class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Keterangan</label>
                    <input type="text" id="transfer-keterangan" placeholder="Contoh: Setor tunai ke rekening" class="input-linear">
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="transfer-cancel-btn" class="btn-secondary">Batal</button>
                <button type="submit" id="transfer-submit-btn" class="btn-primary gap-2">
                    <i class="fa-solid fa-floppy-disk text-xs"></i><span>Simpan Transfer</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Modal Kelola Akun Simpan -->
    <div id="modal-storage-accounts" class="modal-overlay hidden">
        <div class="modal-card flex flex-col max-h-[90vh]" style="max-width:680px;width:95%">
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-[var(--hairline)] flex-shrink-0">
                <h3 class="headline text-lg flex items-center gap-2">
                    <i class="fa-solid fa-vault text-sm text-[var(--primary)]"></i>
                    <span>Kelola Tempat Penyimpanan</span>
                </h3>
                <button type="button" id="storage-modal-close" class="text-[var(--ink-muted)] hover:text-[var(--ink)] p-1">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 pr-1 space-y-4">
                <!-- Quick Preset -->
                <div>
                    <div class="eyebrow mb-2">Tambah Cepat (Preset)</div>
                    <div class="flex flex-wrap gap-2" id="storage-presets">
                        <!-- diisi JS -->
                    </div>
                </div>

                <!-- Form Tambah / Edit -->
                <form id="form-storage-account" class="card-linear p-3 space-y-3">
                    <input type="hidden" id="sa-edit-id" value="">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="eyebrow block mb-1">Nama Akun *</label>
                            <input type="text" id="sa-name" required class="input-linear" placeholder="Contoh: OVO, BCA, Dompet Tunai">
                        </div>
                        <div>
                            <label class="eyebrow block mb-1">Tipe Kategori</label>
                            <select id="sa-parent-type" class="input-linear">
                                <option value="cash">Uang Tunai (Cash)</option>
                                <option value="ewallet">E-Wallet</option>
                                <option value="bank">Bank / Rekening</option>
                                <option value="other">Lainnya</option>
                            </select>
                        </div>
                        <div>
                            <label class="eyebrow block mb-1">Ikon <span class="text-[var(--ink-muted)] font-normal text-[11px]">FontAwesome class</span></label>
                            <div class="flex gap-2 items-center">
                                <input type="text" id="sa-icon" class="input-linear flex-1" placeholder="fa-solid fa-wallet">
                                <span id="sa-icon-preview" class="text-[var(--ink-muted)] text-xl w-6 text-center"><i class="fa-solid fa-vault"></i></span>
                            </div>
                        </div>
                        <div>
                            <label class="eyebrow block mb-1">Urutan Tampil</label>
                            <input type="number" id="sa-sort" class="input-linear" min="1" max="999" value="99">
                        </div>
                    </div>
                    <div class="flex gap-2 pt-1">
                        <button type="submit" id="sa-submit-btn" class="btn-primary gap-2 text-sm">
                            <i class="fa-solid fa-plus text-xs"></i><span id="sa-submit-label">Tambah Akun</span>
                        </button>
                        <button type="button" id="sa-cancel-edit" class="btn-secondary text-sm hidden">
                            <i class="fa-solid fa-xmark text-xs"></i> Batal Edit
                        </button>
                    </div>
                </form>

                <!-- Daftar Akun -->
                <div>
                    <div class="eyebrow mb-2">Daftar Tempat Penyimpanan</div>
                    <div id="storage-accounts-list" class="space-y-2">
                        <div class="text-subtle text-sm py-4 text-center">Memuat...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
