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
require_once __DIR__ . '/config/database.php';
try {
    $cfgRows   = db()->query("SELECT key_name, key_value FROM config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $namaKelas = htmlspecialchars($cfgRows['nama_kelas'] ?? 'RPL 1', ENT_QUOTES);
} catch (Throwable $e) {
    $namaKelas = 'RPL 1';
}
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
                        <span id="brand-nama-kelas" class="text-[11px] text-[var(--ink-muted)] font-normal">Cashflow <?= $namaKelas ?></span>
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
                <a data-tab="jurnal" class="sidebar-nav-item">
                    <i class="fa-solid fa-wallet w-4 text-center"></i>
                    <span>Cashflow & Dompet</span>
                </a>
                <a data-tab="accounts_categories" class="sidebar-nav-item">
                    <i class="fa-solid fa-layer-group w-4 text-center"></i>
                    <span>Akun & Kategori</span>
                </a>
                <a data-tab="riwayat" class="sidebar-nav-item">
                    <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>
                    <span>Log Aktivitas</span>
                </a>
                <a data-tab="ekspor" class="sidebar-nav-item">
                    <i class="fa-solid fa-file-export w-4 text-center"></i>
                    <span>Ekspor Laporan</span>
                </a>
                <a data-tab="pengaturan" class="sidebar-nav-item">
                    <i class="fa-solid fa-gear w-4 text-center"></i>
                    <span>Pengaturan</span>
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
            <p class="text-sm text-[var(--ink-muted)] mb-4">Tambah dan hapus daftar siswa kelas <span class="kelola-nama-kelas font-medium text-[var(--ink)]"><?= $namaKelas ?></span>.</p>
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

        <!-- Section: Centralized Cashflow & Money Tracker -->
        <section data-tab-content="jurnal" class="tab-content hidden">
            <!-- Header & Action Bar -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="display-md">Cashflow & Saldo Dompet</h2>
                    <p class="text-sm text-[var(--ink-muted)]">Pencatatan arus kas sentral, dompet simpanan, dan mutasi saldo.</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button id="btn-cashflow-income" class="btn-primary bg-emerald-600 hover:bg-emerald-700 text-white text-xs gap-1.5 px-3 py-2">
                        <i class="fa-solid fa-arrow-down text-[11px]"></i>
                        <span>+ Pemasukan</span>
                    </button>
                    <button id="btn-cashflow-expense" class="btn-primary bg-rose-600 hover:bg-rose-700 text-white text-xs gap-1.5 px-3 py-2">
                        <i class="fa-solid fa-arrow-up text-[11px]"></i>
                        <span>- Pengeluaran</span>
                    </button>
                    <button id="btn-cashflow-transfer" class="btn-secondary text-xs gap-1.5 px-3 py-2">
                        <i class="fa-solid fa-arrow-right-arrow-left text-[11px]"></i>
                        <span>⇄ Transfer Dompet</span>
                    </button>
                </div>
            </div>

            <!-- Queue Notification Banner -->
            <div id="cashflow-queue-banner" class="hidden mb-6 p-4 rounded-xl border border-amber-500/30 bg-amber-500/10 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-500/20 text-amber-500 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-bell text-base"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-[var(--ink)]">Uang Kas Mingguan Belum Dicatat ke Dompet</h4>
                        <p class="text-xs text-[var(--ink-muted)]">Ada <span id="queue-count-badge" class="font-bold text-amber-600">0</span> antrean (<span id="queue-nominal-badge" class="font-bold text-amber-600">Rp 0</span>) dari centangan kas mingguan yang siap dimasukkan ke pembukuan akun.</p>
                    </div>
                </div>
                <button id="btn-open-queue-modal" class="btn-primary bg-amber-600 hover:bg-amber-700 text-white text-xs px-4 py-2 flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Catat ke Akun Sekarang</span>
                </button>
            </div>

            <!-- Total Kas & Accounts Grid -->
            <div class="mb-6">
                <div class="eyebrow mb-2">Ringkasan Dompet & Saldo Kas</div>
                <div id="cashflow-accounts-grid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4"></div>
            </div>

            <!-- Transactions Filter Bar -->
            <div class="card-linear p-4 mb-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 items-end">
                    <div>
                        <label class="eyebrow block mb-1">Tipe</label>
                        <select id="cf-filter-type" class="input-linear w-full">
                            <option value="">Semua Tipe</option>
                            <option value="income">Pemasukan (+)</option>
                            <option value="expense">Pengeluaran (-)</option>
                            <option value="transfer">Transfer (⇄)</option>
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Dompet / Akun</label>
                        <select id="cf-filter-account" class="input-linear w-full">
                            <option value="">Semua Akun</option>
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Kategori</label>
                        <select id="cf-filter-category" class="input-linear w-full">
                            <option value="">Semua Kategori</option>
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Dari Tanggal</label>
                        <input type="date" id="cf-filter-dari" class="input-linear w-full">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Sampai Tanggal</label>
                        <input type="date" id="cf-filter-sampai" class="input-linear w-full">
                    </div>
                    <div class="flex gap-2">
                        <button id="cf-filter-apply" type="button" class="btn-primary text-xs flex-1 justify-center gap-1.5">
                            <i class="fa-solid fa-filter"></i> <span>Terapkan</span>
                        </button>
                        <button id="cf-filter-reset" type="button" class="btn-secondary text-xs flex-1 justify-center gap-1.5">
                            <i class="fa-solid fa-rotate-left"></i> <span>Reset</span>
                        </button>
                    </div>
                </div>
                <div class="mt-3">
                    <input type="text" id="cf-filter-search" placeholder="Cari keterangan transaksi..." class="input-linear w-full text-xs">
                </div>
            </div>

            <!-- Transactions Table -->
            <div id="cashflow-table-wrap" class="table-container overflow-x-auto mb-4"></div>
            <div id="cashflow-pagination"></div>
        </section>

            <section data-tab-content="ekspor" class="tab-content hidden">
                <h2 class="display-md mb-2">Ekspor Laporan</h2>
                <p class="text-sm text-[var(--ink-muted)] mb-4">Pilih jenis laporan, atur filter, unduh CSV atau cetak PDF.</p>
                <div class="card-linear p-4 mb-6 flex flex-col sm:flex-row gap-3 items-end">
                    <div class="w-full sm:w-56">
                        <label class="eyebrow block mb-1">Jenis Laporan</label>
                        <select id="export-type" class="input-linear w-full">
                            <option value="jurnal">Cashflow (Jurnal Kas)</option>
                            <option value="kasminggu">Kas Mingguan per Siswa</option>
                            <option value="kasbon">Dana Talangan (Kasbon)</option>
                            <option value="bms">Kas BMS</option>
                            <option value="alokasi">Alokasi Dana</option>
                        </select>
                    </div>
                    <div id="export-filters" class="flex flex-wrap gap-3 items-end flex-1"></div>
                    <button class="btn-secondary gap-2" id="btn-load-export">
                        <i class="fa-solid fa-arrow-rotate-right text-xs"></i>
                        <span>Muat Data</span>
                    </button>
                </div>
                <div class="flex flex-wrap gap-2 mb-4">
                    <button class="btn-secondary gap-2" id="btn-csv">
                        <i class="fa-solid fa-file-csv text-xs text-[#60a5fa]"></i>
                        <span>Unduh CSV</span>
                    </button>
                    <button class="btn-primary gap-2" id="btn-pdf">
                        <i class="fa-solid fa-file-pdf text-xs"></i>
                        <span>Cetak PDF</span>
                    </button>
                </div>
                <div id="ekspor-preview" class="table-container overflow-x-auto"></div>
            </section>

        <!-- Section: Pengaturan Kelas -->
        <section data-tab-content="pengaturan" class="tab-content hidden">
            <h2 class="display-md mb-1">Pengaturan Kelas</h2>
            <p class="text-sm text-[var(--ink-muted)] mb-6">Konfigurasi parameter kelas yang berlaku untuk seluruh tampilan dan perhitungan keuangan.</p>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                <!-- Form Pengaturan -->
                <div class="lg:col-span-2">
                    <form id="form-pengaturan" class="card-linear p-6 space-y-5">
                        <div>
                            <label class="eyebrow block mb-1" for="config-nama-kelas">Nama Kelas</label>
                            <div class="relative">
                                <i class="fa-solid fa-school absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[var(--ink-tertiary)]"></i>
                                <input type="text" id="config-nama-kelas" name="nama_kelas"
                                    placeholder="Contoh: XII RPL 1"
                                    maxlength="50" required
                                    class="input-linear pl-9">
                            </div>
                            <p class="text-xs text-[var(--ink-muted)] mt-1">Nama kelas ditampilkan di header, login, dan seluruh halaman publik.</p>
                        </div>

                        <div>
                            <label class="eyebrow block mb-1" for="config-tarif-kas">Tarif Kas Mingguan per Siswa (Rp)</label>
                            <div class="relative">
                                <i class="fa-solid fa-coins absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[var(--ink-tertiary)]"></i>
                                <input type="number" id="config-tarif-kas" name="tarif_kas_mingguan"
                                    placeholder="5000" min="0" step="500" required
                                    class="input-linear pl-9">
                            </div>
                            <p class="text-xs text-[var(--ink-muted)] mt-1">Nominal iuran mingguan per-siswa. Berlaku untuk pencatatan kas mingguan ke depan.</p>
                        </div>

                        <div>
                            <label class="eyebrow block mb-1" for="config-saldo-awal">Saldo Awal Kas (Rp)</label>
                            <div class="relative">
                                <i class="fa-solid fa-vault absolute left-3 top-1/2 -translate-y-1/2 text-xs text-[var(--ink-tertiary)]"></i>
                                <input type="number" id="config-saldo-awal" name="saldo_awal"
                                    placeholder="0" min="0" step="any"
                                    class="input-linear pl-9">
                            </div>
                            <p class="text-xs text-[var(--ink-muted)] mt-1">Saldo pembukaan sebelum pencatatan dimulai. Dihitung ke dalam Total Kas dan grafik tren.</p>
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit" id="pengaturan-submit-btn" class="btn-primary gap-2">
                                <i class="fa-solid fa-floppy-disk text-xs"></i>
                                <span>Simpan Pengaturan</span>
                            </button>
                            <span id="pengaturan-status" class="text-xs hidden"></span>
                        </div>
                    </form>
                </div>

                <!-- Info Panel -->
                <div class="space-y-4">
                    <div class="card-linear p-5">
                        <div class="eyebrow mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-circle-info text-[var(--primary)]"></i>
                            <span>Parameter Aktif</span>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <div class="text-xs text-[var(--ink-muted)] mb-0.5">Nama Kelas</div>
                                <div id="info-nama-kelas" class="text-sm font-semibold text-[var(--ink)]">—</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ink-muted)] mb-0.5">Tarif Kas Mingguan</div>
                                <div id="info-tarif-kas" class="text-sm font-semibold text-[var(--ink)]">—</div>
                            </div>
                            <div>
                                <div class="text-xs text-[var(--ink-muted)] mb-0.5">Saldo Awal</div>
                                <div id="info-saldo-awal" class="text-sm font-semibold text-[var(--ink)]">—</div>
                            </div>
                        </div>
                    </div>

                    <div class="card-linear p-5">
                        <div class="eyebrow mb-2 flex items-center gap-2">
                            <i class="fa-solid fa-triangle-exclamation text-amber-400"></i>
                            <span>Catatan Penting</span>
                        </div>
                        <ul class="text-xs text-[var(--ink-muted)] space-y-2">
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-circle-dot text-[8px] mt-1.5 text-[var(--ink-tertiary)]"></i>
                                <span>Perubahan <b>Nama Kelas</b> langsung diterapkan ke header dan seluruh tampilan tanpa reload.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-circle-dot text-[8px] mt-1.5 text-[var(--ink-tertiary)]"></i>
                                <span>Perubahan <b>Tarif Kas</b> hanya berlaku untuk input baru. Data historis kas yang sudah tercatat tidak terpengaruh.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fa-solid fa-circle-dot text-[8px] mt-1.5 text-[var(--ink-tertiary)]"></i>
                                <span><b>Saldo Awal</b> ditambahkan ke Total Kas di Dashboard dan menjadi titik awal grafik tren.</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section: Kelola Akun & Kategori -->
        <section data-tab-content="accounts_categories" class="tab-content hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h2 class="display-md">Kelola Akun & Kategori</h2>
                    <p class="text-sm text-[var(--ink-muted)]">Atur tempat penyimpanan saldo (dompet/rekening) dan pos kategori keuangan kelas.</p>
                </div>
                <div class="flex gap-2">
                    <button id="btn-add-account-master" class="btn-primary text-xs gap-1.5 px-3 py-2">
                        <i class="fa-solid fa-plus"></i> <span>Tambah Dompet / Akun</span>
                    </button>
                    <button id="btn-add-category-master" class="btn-secondary text-xs gap-1.5 px-3 py-2">
                        <i class="fa-solid fa-plus"></i> <span>Tambah Kategori</span>
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Kolom 1: Daftar Akun / Dompet -->
                <div class="card-linear p-5">
                    <div class="flex items-center justify-between mb-4 border-b border-[var(--hairline)] pb-3">
                        <h3 class="headline flex items-center gap-2">
                            <i class="fa-solid fa-wallet text-indigo-500"></i>
                            <span>Daftar Dompet / Rekening</span>
                        </h3>
                        <span id="master-account-count" class="text-xs text-[var(--ink-muted)]">0 akun</span>
                    </div>
                    <div id="master-accounts-list" class="space-y-3"></div>
                </div>

                <!-- Kolom 2: Daftar Kategori -->
                <div class="card-linear p-5">
                    <div class="flex items-center justify-between mb-4 border-b border-[var(--hairline)] pb-3">
                        <h3 class="headline flex items-center gap-2">
                            <i class="fa-solid fa-tags text-emerald-500"></i>
                            <span>Daftar Kategori Transaksi</span>
                        </h3>
                        <span id="master-category-count" class="text-xs text-[var(--ink-muted)]">0 kategori</span>
                    </div>
                    <div id="master-categories-list" class="space-y-3"></div>
                </div>
            </div>
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
                    <span class="block mb-1">Modul</span>
                    <select id="riwayat-modul" class="input-linear">
                        <option value="">Semua Modul</option>
                        <option value="cashflow">Cashflow (Transaksi)</option>
                        <option value="kas_mingguan">Kas Mingguan</option>
                        <option value="account">Dompet / Akun</option>
                        <option value="category">Kategori</option>
                        <option value="siswa">Data Siswa</option>
                        <option value="config">Pengaturan</option>
                        <option value="legacy">Riwayat Lama (Arsip)</option>
                    </select>
                </label>
                <label class="text-xs text-[var(--ink-muted)]">
                    <span class="block mb-1">Aksi</span>
                    <select id="riwayat-aksi" class="input-linear">
                        <option value="">Semua Aksi</option>
                        <option value="tambah">Tambah</option>
                        <option value="edit">Edit</option>
                        <option value="hapus">Hapus</option>
                        <option value="update_status">Update Status</option>
                        <option value="claim_kas">Klaim Kas</option>
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

    <!-- Modal: Form Transaksi Sentral (Pemasukan / Pengeluaran / Transfer) -->
    <div id="modal-transaction" class="modal-overlay hidden">
        <form id="form-transaction" class="modal-card max-w-lg">
            <input type="hidden" id="tx-id" value="">
            <div class="flex items-center justify-between mb-4 border-b border-[var(--hairline)] pb-3">
                <h3 id="modal-tx-title" class="headline flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-indigo-500"></i>
                    <span>Catat Transaksi</span>
                </h3>
                <button type="button" class="btn-close-modal text-[var(--ink-muted)] hover:text-[var(--ink)]">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="eyebrow block mb-1">Tipe Transaksi *</label>
                    <div class="grid grid-cols-3 gap-2" id="tx-type-selector">
                        <button type="button" data-type="income" class="tx-type-btn p-2.5 rounded-lg border border-[var(--hairline)] text-center text-xs font-semibold hover:border-emerald-500 hover:text-emerald-500 transition">
                            <i class="fa-solid fa-arrow-down block mb-1 text-sm text-emerald-500"></i> Pemasukan
                        </button>
                        <button type="button" data-type="expense" class="tx-type-btn p-2.5 rounded-lg border border-[var(--hairline)] text-center text-xs font-semibold hover:border-rose-500 hover:text-rose-500 transition">
                            <i class="fa-solid fa-arrow-up block mb-1 text-sm text-rose-500"></i> Pengeluaran
                        </button>
                        <button type="button" data-type="transfer" class="tx-type-btn p-2.5 rounded-lg border border-[var(--hairline)] text-center text-xs font-semibold hover:border-indigo-500 hover:text-indigo-500 transition">
                            <i class="fa-solid fa-arrow-right-arrow-left block mb-1 text-sm text-indigo-500"></i> Transfer
                        </button>
                    </div>
                    <input type="hidden" id="tx-type" name="type" value="income" required>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="eyebrow block mb-1">Tanggal *</label>
                        <input type="date" id="tx-date" name="date" required class="input-linear w-full" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Nominal (Rp) *</label>
                        <input type="number" id="tx-amount" name="amount" min="1" step="any" placeholder="0" required class="input-linear w-full font-mono font-semibold">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label id="tx-account-label" class="eyebrow block mb-1">Dompet / Akun *</label>
                        <select id="tx-account-id" name="account_id" required class="input-linear w-full"></select>
                    </div>
                    <div id="tx-to-account-group" class="hidden">
                        <label class="eyebrow block mb-1">Tujuan Transfer *</label>
                        <select id="tx-to-account-id" name="to_account_id" class="input-linear w-full"></select>
                    </div>
                    <div id="tx-category-group">
                        <label class="eyebrow block mb-1">Kategori *</label>
                        <select id="tx-category-id" name="category_id" class="input-linear w-full"></select>
                    </div>
                </div>

                <div>
                    <label class="eyebrow block mb-1">Keterangan / Rincian</label>
                    <textarea id="tx-description" name="description" rows="2" placeholder="Contoh: Beli spidol & kertas HVS" class="input-linear w-full text-xs"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-[var(--hairline)]">
                <button type="button" class="btn-close-modal btn-secondary">Batal</button>
                <button type="submit" id="btn-save-tx" class="btn-primary">Simpan Transaksi</button>
            </div>
        </form>
    </div>

    <!-- Modal: Bukukan Antrean Kas Mingguan -->
    <div id="modal-claim-queue" class="modal-overlay hidden">
        <form id="form-claim-queue" class="modal-card max-w-md">
            <input type="hidden" id="claim-queue-id" value="">
            <div class="flex items-center justify-between mb-4 border-b border-[var(--hairline)] pb-3">
                <h3 class="headline flex items-center gap-2">
                    <i class="fa-solid fa-coins text-amber-500"></i>
                    <span>Bukukan Kas Mingguan</span>
                </h3>
                <button type="button" class="btn-close-modal text-[var(--ink-muted)] hover:text-[var(--ink)]">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl mb-4 text-xs">
                <div class="font-semibold text-amber-600 mb-1" id="claim-queue-title">Penerimaan Kas Mingguan</div>
                <div class="text-2xl font-bold font-mono text-[var(--ink)]" id="claim-queue-nominal">Rp 0</div>
                <div class="text-[var(--ink-muted)] mt-1" id="claim-queue-info">Pilih dompet tempat fisik/digital uang ini disimpan.</div>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="eyebrow block mb-1">Masukkan ke Akun / Dompet *</label>
                    <select id="claim-account-id" required class="input-linear w-full"></select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Pos Kategori *</label>
                    <select id="claim-category-id" required class="input-linear w-full"></select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Keterangan Transaksi</label>
                    <input type="text" id="claim-description" class="input-linear w-full text-xs" placeholder="Contoh: Penerimaan kas minggu 1">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-[var(--hairline)]">
                <button type="button" class="btn-close-modal btn-secondary">Batal</button>
                <button type="submit" id="btn-submit-claim" class="btn-primary bg-emerald-600 hover:bg-emerald-700 text-white">Catat ke Akun</button>
            </div>
        </form>
    </div>

    <!-- Modal: Tambah/Edit Akun Dompet Master -->
    <div id="modal-account" class="modal-overlay hidden">
        <form id="form-account" class="modal-card max-w-md">
            <input type="hidden" id="acc-id" value="">
            <div class="flex items-center justify-between mb-4 border-b border-[var(--hairline)] pb-3">
                <h3 id="modal-account-title" class="headline">Tambah Dompet / Akun</h3>
                <button type="button" class="btn-close-modal text-[var(--ink-muted)] hover:text-[var(--ink)]">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="eyebrow block mb-1">Nama Akun *</label>
                    <input type="text" id="acc-name" required class="input-linear w-full" placeholder="Contoh: Kas Tunai, DANA, BCA">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="eyebrow block mb-1">Tipe Akun</label>
                        <select id="acc-type" class="input-linear w-full">
                            <option value="cash">Uang Tunai</option>
                            <option value="ewallet">E-Wallet</option>
                            <option value="bank">Bank / Rekening</option>
                            <option value="other">Lainnya</option>
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Urutan Tampil</label>
                        <input type="number" id="acc-sort" class="input-linear w-full" value="1" min="0">
                    </div>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Saldo Awal (Rp)</label>
                    <input type="number" id="acc-initial-balance" class="input-linear w-full font-mono" value="0" min="0" step="any">
                    <p class="text-[11px] text-[var(--ink-muted)] mt-1">Saldo saat sistem baru mulai digunakan.</p>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Ikon FontAwesome</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="acc-icon" class="input-linear flex-1" value="fa-solid fa-wallet">
                        <span id="acc-icon-preview" class="w-8 h-8 rounded-lg bg-[var(--surface-2)] flex items-center justify-center text-sm"><i class="fa-solid fa-wallet"></i></span>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-[var(--hairline)]">
                <button type="button" class="btn-close-modal btn-secondary">Batal</button>
                <button type="submit" id="btn-save-acc" class="btn-primary">Simpan Akun</button>
            </div>
        </form>
    </div>

    <!-- Modal: Tambah/Edit Kategori Master -->
    <div id="modal-category" class="modal-overlay hidden">
        <form id="form-category" class="modal-card max-w-md">
            <input type="hidden" id="cat-id" value="">
            <div class="flex items-center justify-between mb-4 border-b border-[var(--hairline)] pb-3">
                <h3 id="modal-category-title" class="headline">Tambah Kategori</h3>
                <button type="button" class="btn-close-modal text-[var(--ink-muted)] hover:text-[var(--ink)]">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="space-y-3">
                <div>
                    <label class="eyebrow block mb-1">Nama Kategori *</label>
                    <input type="text" id="cat-name" required class="input-linear w-full" placeholder="Contoh: Uang Kas, Pembelian ATK, Hadiah Lomba">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="eyebrow block mb-1">Jenis Kategori</label>
                        <select id="cat-type" class="input-linear w-full">
                            <option value="both">Pemasukan & Pengeluaran</option>
                            <option value="income">Hanya Pemasukan</option>
                            <option value="expense">Hanya Pengeluaran</option>
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Warna Label</label>
                        <input type="color" id="cat-color" class="input-linear w-full h-9 p-1 cursor-pointer" value="#3b82f6">
                    </div>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Ikon FontAwesome</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="cat-icon" class="input-linear flex-1" value="fa-solid fa-tag">
                        <span id="cat-icon-preview" class="w-8 h-8 rounded-lg bg-[var(--surface-2)] flex items-center justify-center text-sm"><i class="fa-solid fa-tag"></i></span>
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6 pt-4 border-t border-[var(--hairline)]">
                <button type="button" class="btn-close-modal btn-secondary">Batal</button>
                <button type="submit" id="btn-save-cat" class="btn-primary">Simpan Kategori</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <script>window.namaKelas = <?= json_encode($namaKelas) ?>;</script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
