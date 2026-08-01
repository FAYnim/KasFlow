<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Keuangan Kelas RPL 1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/print.css" media="print">
</head>
<body class="bg-slate-50 min-h-screen">
    <nav class="fixed top-0 inset-x-0 bg-white shadow z-30 flex items-center justify-between px-4 h-14">
        <div class="flex items-center gap-2">
            <button id="btn-hamburger" class="p-2 rounded hover:bg-slate-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="font-bold text-slate-800">Keuangan Kelas RPL 1</span>
        </div>
        <a href="login.php" class="text-sm text-blue-600 hover:underline">Login Bendahara</a>
    </nav>

    <aside id="sidebar" class="fixed top-14 left-0 bottom-0 w-60 bg-white shadow z-20 transform -translate-x-full transition-transform">
        <ul class="py-4">
            <li><a data-tab="dashboard" class="block px-4 py-2 hover:bg-slate-100 cursor-pointer">Dashboard</a></li>
            <li><a data-tab="kas"        class="block px-4 py-2 hover:bg-slate-100 cursor-pointer">Kas Mingguan</a></li>
            <li><a data-tab="jurnal"     class="block px-4 py-2 hover:bg-slate-100 cursor-pointer">Jurnal Kas</a></li>
            <li><a data-tab="piutang"    class="block px-4 py-2 hover:bg-slate-100 cursor-pointer">Piutang & Denda</a></li>
            <li><a data-tab="bank"       class="block px-4 py-2 hover:bg-slate-100 cursor-pointer">Mutasi Bank</a></li>
        </ul>
    </aside>

    <main class="pt-20 px-4 md:px-8 max-w-6xl mx-auto">
        <section data-tab-content="dashboard" class="tab-content">
            <h2 class="text-2xl font-bold mb-4">Dashboard</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4" id="summary-cards"></div>
        </section>
        <section data-tab-content="kas" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Kas Mingguan</h2>
            <div class="flex gap-2 mb-3">
                <select id="kas-bulan" class="border rounded p-2"></select>
                <select id="kas-tahun" class="border rounded p-2"></select>
                <input id="kas-search" placeholder="Cari nama..." class="border rounded p-2 flex-1">
            </div>
            <div class="overflow-x-auto bg-white rounded shadow"><table class="w-full text-sm" id="kas-table"></table></div>
        </section>
        <section data-tab-content="jurnal" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Jurnal Kas</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div class="bg-white p-4 rounded shadow"><canvas id="chart-line"></canvas></div>
                <div class="bg-white p-4 rounded shadow"><canvas id="chart-donut"></canvas></div>
            </div>
            <div id="jurnal-table-wrap" class="bg-white rounded shadow overflow-x-auto"></div>
        </section>
        <section data-tab-content="piutang" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Piutang & Denda</h2>
            <div id="piutang-wrap" class="bg-white rounded shadow overflow-x-auto"></div>
        </section>
        <section data-tab-content="bank" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Mutasi Bank</h2>
            <div id="bank-wrap" class="bg-white rounded shadow overflow-x-auto"></div>
        </section>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="assets/js/app_public.js"></script>
</body>
</html>
