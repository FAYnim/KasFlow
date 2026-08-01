<?php
session_start();
if (empty($_SESSION['admin_logged'])) { header('Location: login.php'); exit; }
$nama = $_SESSION['admin_nama'] ?? 'Bendahara';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bendahara - Cashflow Kelas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/print.css" media="print">
</head>
<body class="bg-[#010102] text-[#f7f8f8] min-h-screen flex flex-col md:flex-row">
    <!-- Admin Sidebar Navigation -->
    <aside class="w-full md:w-60 bg-[#0f1011] border-r border-[#23252a] min-h-screen p-4 flex-shrink-0 flex flex-col justify-between">
        <div>
            <div class="brand-mark mb-6 px-2 py-1">
                <div class="brand-icon">⚡</div>
                <div class="flex flex-col">
                    <span class="text-sm font-semibold">Admin Bendahara</span>
                    <span class="text-[11px] text-[#8a8f98] font-normal">Cashflow RPL 1</span>
                </div>
            </div>

            <div class="eyebrow px-2 text-[11px] mb-2">Manajemen</div>
            <nav class="space-y-0.5">
                <a data-tab="dashboard" class="sidebar-nav-item active">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                    Dashboard
                </a>
                <a data-tab="siswa" class="sidebar-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    Kelola Siswa
                </a>
                <a data-tab="kas" class="sidebar-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Input Kas
                </a>
                <a data-tab="jurnal" class="sidebar-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Kelola Jurnal
                </a>
                <a data-tab="denda" class="sidebar-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Kelola Denda
                </a>
                <a data-tab="bank" class="sidebar-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>
                    Mutasi Bank
                </a>
                <a data-tab="ekspor" class="sidebar-nav-item">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Ekspor Laporan
                </a>
            </nav>
        </div>

        <div class="mt-8 pt-4 border-t border-[#23252a]">
            <a href="logout.php" class="btn-danger w-full justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Keluar (Logout)
            </a>
        </div>
    </aside>

    <!-- Main Admin Content -->
    <main class="flex-1 p-6 md:p-8 max-w-6xl">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-[#23252a]">
            <div>
                <div class="eyebrow">Sesi Aktif</div>
                <div class="text-sm text-[#8a8f98]">Login sebagai <b class="text-[#f7f8f8]"><?= htmlspecialchars($nama) ?></b></div>
            </div>
            <a href="index.php" target="_blank" class="btn-secondary text-xs gap-1">
                <span>Buka Web Publik</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        </div>

        <!-- Section: Dashboard -->
        <section data-tab-content="dashboard" class="tab-content">
            <h2 class="display-md mb-4">Dashboard Admin</h2>
            <div id="admin-summary" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"></div>
        </section>

        <!-- Section: Kelola Siswa -->
        <section data-tab-content="siswa" class="tab-content hidden">
            <h2 class="display-md mb-2">Kelola Siswa</h2>
            <p class="text-sm text-[#8a8f98] mb-4">Tambah dan hapus daftar siswa kelas RPL 1.</p>
            <form id="form-siswa" class="flex flex-col sm:flex-row gap-2 mb-6 card-linear p-4">
                <input name="nis" placeholder="NIS (opsional)" class="input-linear w-full sm:w-44">
                <input name="nama" placeholder="Nama lengkap siswa" required class="input-linear flex-1">
                <button class="btn-primary">Tambah Siswa</button>
            </form>
            <div id="siswa-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Input Kas -->
        <section data-tab-content="kas" class="tab-content hidden">
            <h2 class="display-md mb-2">Input Kas Mingguan</h2>
            <p class="text-sm text-[#8a8f98] mb-4">Centang checkbox untuk mencatat pembayaran kas siswa.</p>
            <div class="flex gap-3 mb-4">
                <select id="admin-bulan" class="input-linear w-44"></select>
                <select id="admin-tahun" class="input-linear w-32"></select>
            </div>
            <div id="kas-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Kelola Jurnal -->
        <section data-tab-content="jurnal" class="tab-content hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="display-md">Kelola Jurnal Kas</h2>
                    <p class="text-sm text-[#8a8f98]">Catat pengeluaran dan pemasukan kas secara akurat.</p>
                </div>
                <button id="btn-add-jurnal" class="btn-primary gap-1">
                    <span>+ Tambah Transaksi</span>
                </button>
            </div>
            <div id="jurnal-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Kelola Denda -->
        <section data-tab-content="denda" class="tab-content hidden">
            <h2 class="display-md mb-2">Kelola Denda</h2>
            <p class="text-sm text-[#8a8f98] mb-4">Buat dan update status pembayaran denda siswa.</p>
            <form id="form-denda" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6 card-linear p-4">
                <div>
                    <label class="eyebrow block mb-1">Pilih Siswa</label>
                    <select id="denda-siswa" class="input-linear"></select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Tanggal</label>
                    <input type="date" name="tanggal" required class="input-linear" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Keterangan</label>
                    <input name="keterangan" placeholder="Alasan denda" required class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Jumlah (Rp)</label>
                    <input type="number" name="jumlah" placeholder="Contoh: 5000" required min="1" class="input-linear">
                </div>
                <button class="btn-primary sm:col-span-2 lg:col-span-4 mt-2">Tambah Tagihan Denda</button>
            </form>
            <div id="denda-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Mutasi Bank -->
        <section data-tab-content="bank" class="tab-content hidden">
            <h2 class="display-md mb-2">Mutasi Rekening Bank</h2>
            <p class="text-sm text-[#8a8f98] mb-4">Pencatatan setor tunai & tarik tunai rekening kas.</p>
            <form id="form-bank" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-6 card-linear p-4">
                <div>
                    <label class="eyebrow block mb-1">Tanggal</label>
                    <input type="date" name="tanggal" required class="input-linear" value="<?= date('Y-m-d') ?>">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Keterangan</label>
                    <input name="keterangan" placeholder="Berita mutasi" required class="input-linear">
                </div>
                <div>
                    <label class="eyebrow block mb-1">Jenis Transaksi</label>
                    <select name="jenis" class="input-linear"><option value="setor">Setor Tunai</option><option value="tarik">Tarik Tunai</option></select>
                </div>
                <div>
                    <label class="eyebrow block mb-1">Jumlah (Rp)</label>
                    <input type="number" name="jumlah" placeholder="Contoh: 50000" required min="1" class="input-linear">
                </div>
                <button class="btn-primary sm:col-span-2 lg:col-span-4 mt-2">Simpan Mutasi Bank</button>
            </form>
            <div id="bank-wrap" class="table-container overflow-x-auto"></div>
        </section>

        <!-- Section: Ekspor Laporan -->
        <section data-tab-content="ekspor" class="tab-content hidden">
            <h2 class="display-md mb-2">Ekspor Laporan Kas</h2>
            <p class="text-sm text-[#8a8f98] mb-4">Cetak atau simpan data jurnal kas ke format CSV / PDF.</p>
            <form id="form-ekspor" class="flex flex-col sm:flex-row gap-3 items-end mb-6 card-linear p-4">
                <div class="w-full sm:w-48">
                    <label class="eyebrow block mb-1">Dari Tanggal</label>
                    <input type="date" name="dari" class="input-linear">
                </div>
                <div class="w-full sm:w-48">
                    <label class="eyebrow block mb-1">Sampai Tanggal</label>
                    <input type="date" name="sampai" class="input-linear">
                </div>
                <button class="btn-secondary" id="btn-csv">Unduh CSV</button>
                <button type="button" class="btn-primary" id="btn-pdf">Cetak PDF</button>
            </form>
            <div id="ekspor-preview" class="table-container overflow-x-auto"></div>
        </section>
    </main>

    <!-- Modal Form Transaksi Jurnal -->
    <div id="modal-jurnal" class="modal-overlay hidden">
        <form id="form-jurnal" class="modal-card">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#23252a]">
                <h3 class="headline text-lg">Transaksi Jurnal</h3>
                <button type="button" id="modal-close" class="text-[#8a8f98] hover:text-[#f7f8f8]">✕</button>
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
                <button class="btn-primary">Simpan Transaksi</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/app_admin.js"></script>
</body>
</html>
