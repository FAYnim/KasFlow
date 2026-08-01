<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan Kelas RPL 1</title>
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
                    <i class="fa-solid fa-bolt text-xs"></i>
                </div>
                <span>Cashflow RPL 1</span>
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
                <a data-tab="dashboard" class="sidebar-nav-item active">
                    <i class="fa-solid fa-gauge w-4 text-center"></i>
                    <span>Dashboard</span>
                </a>
                <a data-tab="kas" class="sidebar-nav-item">
                    <i class="fa-solid fa-money-bill-wave w-4 text-center"></i>
                    <span>Kas Mingguan</span>
                </a>
                <a data-tab="jurnal" class="sidebar-nav-item">
                    <i class="fa-solid fa-receipt w-4 text-center"></i>
                    <span>Jurnal Kas</span>
                </a>
                <a data-tab="piutang" class="sidebar-nav-item">
                    <i class="fa-solid fa-triangle-exclamation w-4 text-center"></i>
                    <span>Piutang & Denda</span>
                </a>
                <a data-tab="bank" class="sidebar-nav-item">
                    <i class="fa-solid fa-building-columns w-4 text-center"></i>
                    <span>Mutasi Bank</span>
                </a>
            </nav>
        </div>

        <div class="pt-4 border-t border-[var(--hairline)] mt-auto">
            <a href="src/auth/login.php" class="sidebar-nav-item text-xs gap-2">
                <i class="fa-solid fa-user-shield w-4 text-center"></i>
                <span>Login Bendahara</span>
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="pt-20 pb-12 px-4 md:px-8 md:ml-60 max-w-6xl">
        <!-- Dashboard Section -->
        <section data-tab-content="dashboard" class="tab-content">
            <div class="mb-6">
                <h2 class="display-md mb-1">Ikhtisar Keuangan</h2>
                <p class="text-sm text-subtle">Ringkasan real-time arus kas dan posisi keuangan kelas.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8" id="summary-cards"></div>
        </section>

        <!-- Kas Mingguan Section -->
        <section data-tab-content="kas" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Kas Mingguan</h2>
                <p class="text-sm text-subtle">Status iuran mingguan seluruh siswa per periode.</p>
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

        <!-- Jurnal Kas Section -->
        <section data-tab-content="jurnal" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Jurnal Kas</h2>
                <p class="text-sm text-subtle">Grafik analisis dan riwayat transaksi penerimaan serta pengeluaran.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
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
            <div class="table-container overflow-x-auto" id="jurnal-table-wrap"></div>
        </section>

        <!-- Piutang & Denda Section -->
        <section data-tab-content="piutang" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Piutang & Denda</h2>
                <p class="text-sm text-subtle">Daftar keterlambatan dan catatan denda siswa.</p>
            </div>
            <div class="table-container overflow-x-auto" id="piutang-wrap"></div>
        </section>

        <!-- Mutasi Bank Section -->
        <section data-tab-content="bank" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Mutasi Bank</h2>
                <p class="text-sm text-subtle">Catatan penyetoran dan penarikan kas pada rekening kelas.</p>
            </div>
            <div class="table-container overflow-x-auto" id="bank-wrap"></div>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/public.js"></script>
</body>
</html>
