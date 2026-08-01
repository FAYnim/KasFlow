<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keuangan Kelas RPL 1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/print.css" media="print">
</head>
<body class="bg-[#010102] text-[#f7f8f8] min-h-screen">
    <!-- Top Navigation Bar -->
    <header class="top-nav">
        <div class="flex items-center gap-3">
            <button id="btn-hamburger" class="p-1.5 rounded-md hover:bg-[#141516] text-[#8a8f98] hover:text-[#f7f8f8] transition-colors md:hidden">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="brand-mark">
                <div class="brand-icon">⚡</div>
                <span>Cashflow RPL 1</span>
            </div>
        </div>
        <a href="login.php" class="btn-secondary text-xs">
            Login Bendahara
        </a>
    </header>

    <!-- Sidebar Navigation -->
    <aside id="sidebar" class="sidebar-linear transform -translate-x-full md:translate-x-0">
        <div class="eyebrow px-3 py-2 text-[11px] mb-1">Navigasi Utama</div>
        <nav class="space-y-0.5">
            <a data-tab="dashboard" class="sidebar-nav-item active">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                Dashboard
            </a>
            <a data-tab="kas" class="sidebar-nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Kas Mingguan
            </a>
            <a data-tab="jurnal" class="sidebar-nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Jurnal Kas
            </a>
            <a data-tab="piutang" class="sidebar-nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Piutang & Denda
            </a>
            <a data-tab="bank" class="sidebar-nav-item">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                Mutasi Bank
            </a>
        </nav>
    </aside>

    <!-- Main Content Area -->
    <main class="pt-20 pb-12 px-4 md:px-8 md:ml-60 max-w-6xl">
        <!-- Dashboard Section -->
        <section data-tab-content="dashboard" class="tab-content">
            <div class="mb-6">
                <h2 class="display-md mb-1">Ikhtisar Keuangan</h2>
                <p class="text-sm text-[#8a8f98]">Ringkasan real-time arus kas dan posisi keuangan kelas.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8" id="summary-cards"></div>
        </section>

        <!-- Kas Mingguan Section -->
        <section data-tab-content="kas" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Kas Mingguan</h2>
                <p class="text-sm text-[#8a8f98]">Status iuran mingguan seluruh siswa per periode.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <div class="w-full sm:w-44">
                    <select id="kas-bulan" class="input-linear"></select>
                </div>
                <div class="w-full sm:w-32">
                    <select id="kas-tahun" class="input-linear"></select>
                </div>
                <div class="flex-1">
                    <input id="kas-search" placeholder="Cari nama siswa..." class="input-linear">
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
                <p class="text-sm text-[#8a8f98]">Grafik analisis dan riwayat transaksi penerimaan serta pengeluaran.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div class="card-linear p-5">
                    <div class="eyebrow mb-3">Tren Akumulasi Saldo</div>
                    <canvas id="chart-line" height="200"></canvas>
                </div>
                <div class="card-linear p-5">
                    <div class="eyebrow mb-3">Rasio Masuk vs Keluar</div>
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
                <p class="text-sm text-[#8a8f98]">Daftar keterlambatan dan catatan denda siswa.</p>
            </div>
            <div class="table-container overflow-x-auto" id="piutang-wrap"></div>
        </section>

        <!-- Mutasi Bank Section -->
        <section data-tab-content="bank" class="tab-content hidden">
            <div class="mb-6">
                <h2 class="display-md mb-1">Mutasi Bank</h2>
                <p class="text-sm text-[#8a8f98]">Catatan penyetoran dan penarikan kas pada rekening kelas.</p>
            </div>
            <div class="table-container overflow-x-auto" id="bank-wrap"></div>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/app_public.js"></script>
</body>
</html>
