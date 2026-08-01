<?php
session_start();
if (empty($_SESSION['admin_logged'])) { header('Location: login.php'); exit; }
$nama = $_SESSION['admin_nama'] ?? 'Bendahara';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Bendahara</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex">
    <aside class="w-60 bg-slate-800 text-slate-100 min-h-screen p-4 flex-shrink-0">
        <h1 class="font-bold text-lg mb-6">Admin Bendahara</h1>
        <ul class="space-y-1 text-sm">
            <li><a data-tab="dashboard" class="block px-3 py-2 rounded hover:bg-slate-700 cursor-pointer">Dashboard</a></li>
            <li><a data-tab="siswa"     class="block px-3 py-2 rounded hover:bg-slate-700 cursor-pointer">Kelola Siswa</a></li>
            <li><a data-tab="kas"       class="block px-3 py-2 rounded hover:bg-slate-700 cursor-pointer">Input Kas</a></li>
            <li><a data-tab="jurnal"    class="block px-3 py-2 rounded hover:bg-slate-700 cursor-pointer">Kelola Jurnal</a></li>
            <li><a data-tab="denda"     class="block px-3 py-2 rounded hover:bg-slate-700 cursor-pointer">Kelola Denda</a></li>
            <li><a data-tab="bank"      class="block px-3 py-2 rounded hover:bg-slate-700 cursor-pointer">Mutasi Bank</a></li>
            <li><a data-tab="ekspor"    class="block px-3 py-2 rounded hover:bg-slate-700 cursor-pointer">Ekspor Laporan</a></li>
        </ul>
        <a href="logout.php" class="block mt-8 px-3 py-2 rounded bg-rose-600 text-center hover:bg-rose-700">Logout</a>
    </aside>

    <main class="flex-1 p-6">
        <div class="mb-4 text-slate-600">Login sebagai <b><?= htmlspecialchars($nama) ?></b></div>

        <section data-tab-content="dashboard" class="tab-content">
            <h2 class="text-2xl font-bold mb-4">Dashboard</h2>
            <div id="admin-summary" class="grid grid-cols-1 md:grid-cols-4 gap-4"></div>
        </section>

        <section data-tab-content="siswa" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Kelola Siswa</h2>
            <form id="form-siswa" class="flex gap-2 mb-4">
                <input name="nis"  placeholder="NIS (opsional)" class="border rounded p-2">
                <input name="nama" placeholder="Nama siswa" required class="border rounded p-2 flex-1">
                <button class="bg-blue-600 text-white px-4 rounded hover:bg-blue-700">Tambah</button>
            </form>
            <div id="siswa-wrap" class="bg-white rounded shadow overflow-x-auto"></div>
        </section>

        <section data-tab-content="kas" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Input Kas Mingguan</h2>
            <div class="flex gap-2 mb-3">
                <select id="admin-bulan" class="border rounded p-2"></select>
                <select id="admin-tahun" class="border rounded p-2"></select>
            </div>
            <div id="kas-wrap" class="bg-white rounded shadow overflow-x-auto"></div>
        </section>

        <section data-tab-content="jurnal" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Kelola Jurnal</h2>
            <button id="btn-add-jurnal" class="bg-blue-600 text-white px-4 py-2 rounded mb-3">+ Tambah</button>
            <div id="jurnal-wrap" class="bg-white rounded shadow overflow-x-auto"></div>
        </section>

        <section data-tab-content="denda" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Kelola Denda</h2>
            <form id="form-denda" class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-4">
                <select id="denda-siswa" class="border rounded p-2"></select>
                <input type="date"  name="tanggal" required class="border rounded p-2" value="<?= date('Y-m-d') ?>">
                <input name="keterangan" placeholder="Keterangan" required class="border rounded p-2">
                <input type="number" name="jumlah" placeholder="Jumlah" required min="1" class="border rounded p-2">
                <button class="md:col-span-4 bg-blue-600 text-white py-2 rounded">Tambah Denda</button>
            </form>
            <div id="denda-wrap" class="bg-white rounded shadow overflow-x-auto"></div>
        </section>

        <section data-tab-content="bank" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Mutasi Bank</h2>
            <form id="form-bank" class="grid grid-cols-1 md:grid-cols-4 gap-2 mb-4">
                <input type="date" name="tanggal" required class="border rounded p-2" value="<?= date('Y-m-d') ?>">
                <input name="keterangan" placeholder="Keterangan" required class="border rounded p-2">
                <select name="jenis" class="border rounded p-2"><option>setor</option><option>tarik</option></select>
                <input type="number" name="jumlah" placeholder="Jumlah" required min="1" class="border rounded p-2">
                <button class="md:col-span-4 bg-blue-600 text-white py-2 rounded">Tambah Mutasi</button>
            </form>
            <div id="bank-wrap" class="bg-white rounded shadow overflow-x-auto"></div>
        </section>

        <section data-tab-content="ekspor" class="tab-content hidden">
            <h2 class="text-2xl font-bold mb-4">Ekspor Laporan</h2>
            <form id="form-ekspor" class="flex gap-2 items-end mb-4">
                <div><label class="block text-sm">Dari</label><input type="date" name="dari" class="border rounded p-2"></div>
                <div><label class="block text-sm">Sampai</label><input type="date" name="sampai" class="border rounded p-2"></div>
                <button class="bg-emerald-600 text-white px-4 py-2 rounded" id="btn-csv">Download CSV</button>
                <button type="button" class="bg-rose-600 text-white px-4 py-2 rounded" id="btn-pdf">Cetak PDF</button>
            </form>
            <div id="ekspor-preview" class="bg-white rounded shadow p-4"></div>
        </section>
    </main>

    <!-- Jurnal modal -->
    <div id="modal-jurnal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <form id="form-jurnal" class="bg-white p-6 rounded shadow w-96">
            <h3 class="font-bold mb-3">Transaksi Jurnal</h3>
            <input type="hidden" name="id">
            <label class="block text-sm">Tanggal</label><input type="date" name="tanggal" required class="border rounded p-2 w-full mb-2" value="<?= date('Y-m-d') ?>">
            <label class="block text-sm">Keterangan</label><input name="keterangan" required class="border rounded p-2 w-full mb-2">
            <label class="block text-sm">Jenis</label>
            <select name="jenis" class="border rounded p-2 w-full mb-2"><option>masuk</option><option>keluar</option></select>
            <label class="block text-sm">Nominal</label><input type="number" name="nominal" required min="1" class="border rounded p-2 w-full mb-3">
            <div class="flex justify-end gap-2">
                <button type="button" id="modal-close" class="px-3 py-2 rounded bg-slate-200">Batal</button>
                <button class="px-3 py-2 rounded bg-blue-600 text-white">Simpan</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="assets/js/app_admin.js"></script>
</body>
</html>
