<?php
require_once __DIR__ . '/config/database.php';
try {
    $cfgRows    = db()->query("SELECT key_name, key_value FROM config")->fetchAll(PDO::FETCH_KEY_PAIR);
    $namaKelas  = htmlspecialchars($cfgRows['nama_kelas'] ?? 'RPL 1', ENT_QUOTES);
} catch (Throwable $e) {
    $namaKelas = 'RPL 1';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan Kelas <?= $namaKelas ?></title>
    <script>
        (function() {
            const saved = localStorage.getItem('theme');
            const theme = saved ? saved : 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/print.css" media="print">
</head>
<body class="min-h-screen">
    <!-- Top Navigation Bar -->
    <header class="top-nav">
        <div class="flex items-center gap-3">
            <button id="btn-hamburger" class="p-1.5 rounded-md hover:bg-[var(--surface-2)] text-[var(--ink-subtle)] hover:text-[var(--ink)] transition-colors md:hidden">
                <i class="fa-solid fa-bars text-base"></i>
            </button>
            <div class="brand-mark">
                <div class="brand-icon">
                    <i class="fa-solid fa-money-bill-wave text-xs"></i>
                </div>
                <span>Cashflow <?= $namaKelas ?></span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button id="theme-toggle-btn" class="btn-secondary p-2 w-9 h-9 flex items-center justify-center rounded-lg cursor-pointer" title="Switch Theme">
                <i id="theme-toggle-icon" class="fa-solid fa-sun text-amber-500 text-sm"></i>
            </button>
        </div>
    </header>

    <!-- Sidebar Navigation -->
    <aside id="sidebar" class="sidebar-linear transform -translate-x-full md:translate-x-0 flex flex-col justify-between">
        <div>
            <div class="eyebrow px-3 py-2 text-[11px] mb-1">Navigasi Utama</div>
            <nav class="space-y-0.5">
                <a data-tab="kas" class="sidebar-nav-item active">
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
                <a data-tab="jurnal" class="sidebar-nav-item">
                    <i class="fa-solid fa-receipt w-4 text-center"></i>
                    <span>Cashflow</span>
                </a>
                <a data-tab="dashboard" class="sidebar-nav-item">
                    <i class="fa-solid fa-gauge w-4 text-center"></i>
                    <span>Statistik</span>
                </a>
                <a data-tab="riwayat" class="sidebar-nav-item">
                    <i class="fa-solid fa-clock-rotate-left w-4 text-center"></i>
                    <span>Riwayat</span>
                </a>
            </nav>
        </div>

        <div class="pt-4 border-t border-[var(--hairline)] mt-auto">
            <a href="login" class="sidebar-nav-item text-xs gap-2">
                <i class="fa-solid fa-user-shield w-4 text-center"></i>
                <span>Login Bendahara</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="pt-20 pb-12 px-4 md:px-8 md:ml-60 max-w-6xl">
        <!-- Dashboard Section (Statistik) -->
        <section data-tab-content="dashboard" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Statistik Keuangan</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8" id="summary-cards"></div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="card-linear p-5">
                    <div class="eyebrow mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Tren Akumulasi Saldo</span>
                    </div>
                    <canvas id="chart-line" height="200"></canvas>
                </div>
                <div class="card-linear p-5">
                    <div class="eyebrow mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Rasio Masuk vs Keluar</span>
                    </div>
                    <div class="h-[200px] flex items-center justify-center">
                        <canvas id="chart-donut"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- Kas Kelas Section -->
        <section data-tab-content="kas" class="tab-content">
            <div class="mb-6">
                <h2 class="display-md mb-1">Kas Kelas</h2>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <div class="w-full sm:w-44">
                    <select id="kas-bulan" class="input-linear"></select>
                </div>
                <div class="w-full sm:w-32">
                    <select id="kas-tahun" class="input-linear"></select>
                </div>
                <div class="flex-1 relative">
                    <input id="kas-search" placeholder="Cari nama siswa..." class="input-linear pl-9">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-subtle"></i>
                </div>
            </div>
            <div class="table-container overflow-x-auto">
                <table class="table-linear" id="kas-table"></table>
            </div>
        </section>

        <!-- Cashflow Section -->
        <section data-tab-content="jurnal" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Cashflow</h2>
            </div>
            <div class="flex flex-wrap gap-2 mb-4 items-end">
                <div class="w-full sm:w-44">
                    <label class="eyebrow block mb-1">Bulan</label>
                    <select id="jurnal-bulan" class="input-linear"></select>
                </div>
                <div class="w-full sm:w-32">
                    <label class="eyebrow block mb-1">Tahun</label>
                    <select id="jurnal-tahun" class="input-linear"></select>
                </div>
                <button id="jurnal-reset" type="button" class="btn-secondary text-xs gap-2">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i>
                    <span>Semua Periode</span>
                </button>
            </div>
            <div class="table-container overflow-x-auto" id="jurnal-table-wrap"></div>
            <div id="jurnal-pagination"></div>
        </section>

        <!-- Kasbon Section -->
        <section data-tab-content="kasbon" class="tab-content hidden">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="display-md mb-1">Dana Talangan / Reimbursement</h2>
                </div>
                <div class="flex items-center gap-2">
                    <select id="kasbon-bulan" class="input-linear"></select>
                    <select id="kasbon-tahun" class="input-linear"></select>
                </div>
            </div>
            <div class="table-container overflow-x-auto">
                <table class="table-linear w-full">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">#</th>
                            <th>Tanggal</th>
                            <th>Nama</th>
                            <th>Keterangan</th>
                            <th class="text-right">Jumlah</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="kasbon-table-body">
                        <tr><td colspan="6" class="text-center py-6 text-subtle">Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Kas BMS Section -->
        <section data-tab-content="bms" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Kas BMS</h2>
            </div>

            <div id="bms-summary-cards" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6"></div>

            <div class="flex flex-wrap items-end gap-3 mb-4">
                <label class="text-xs text-subtle">
                    <span class="block mb-1">Dari</span>
                    <input type="date" id="bms-dari" class="input-linear">
                </label>
                <label class="text-xs text-subtle">
                    <span class="block mb-1">Sampai</span>
                    <input type="date" id="bms-sampai" class="input-linear">
                </label>
                <button id="bms-apply" class="btn-primary text-xs gap-2">
                    <i class="fa-solid fa-filter text-[10px]"></i> <span>Terapkan</span>
                </button>
                <button id="bms-reset" class="btn-secondary text-xs gap-2">
                    <i class="fa-solid fa-rotate-left text-[10px]"></i> <span>Reset</span>
                </button>
            </div>

            <div id="bms-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Alokasi Dana Section -->
        <section data-tab-content="alokasi" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Alokasi Dana</h2>
                <p class="text-sm text-subtle">Sebaran saldo kas kelas ke beberapa tempat simpan (cash, e-wallet, bank).</p>
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
        </section>

        <!-- Riwayat Section -->
        <section data-tab-content="riwayat" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Riwayat</h2>
                <p class="text-sm text-subtle">Catatan perubahan data keuangan kelas.</p>
            </div>
            <div class="flex flex-wrap gap-2 mb-4 items-end">
                <label class="text-xs text-subtle">
                    <span class="block mb-1">Aksi</span>
                    <select id="riwayat-aksi" class="input-linear">
                        <option value="">Semua</option>
                        <option value="tambah">Tambah</option>
                        <option value="edit">Edit</option>
                        <option value="hapus">Hapus</option>
                        <option value="update_status">Update Status</option>
                    </select>
                </label>
                <label class="text-xs text-subtle">
                    <span class="block mb-1">Dari</span>
                    <input type="date" id="riwayat-dari" class="input-linear">
                </label>
                <label class="text-xs text-subtle">
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
                <div class="text-center py-6 text-subtle">Pilih tab Riwayat untuk memuat data.</div>
            </div>
            <div id="riwayat-pagination"></div>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/public.js"></script>
</body>
</html>
