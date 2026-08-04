<?php
session_start();
if (empty($_SESSION['admin_logged'])) { header('Location: ../auth/login.php'); exit; }
$nama = $_SESSION['admin_nama'] ?? 'Bendahara';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Bendahara - Cashflow Kelas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="../../assets/css/print.css" media="print">
</head>
<body data-theme="dark" class="bg-[#010102] text-[#f7f8f8] min-h-screen flex flex-col md:flex-row md:h-screen md:overflow-hidden">
    <!-- Admin Sidebar Navigation -->
    <aside class="w-full md:w-60 bg-[#0f1011] border-r border-[#23252a] min-h-screen p-4 flex-shrink-0 flex flex-col justify-between md:h-screen md:overflow-hidden md:sticky md:top-0">
        <div>
            <div class="brand-mark mb-6 px-2 py-1">
                <div class="brand-icon">
                    <i class="fa-solid fa-bolt text-xs"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-semibold">Admin Bendahara</span>
                    <span class="text-[11px] text-[#8a8f98] font-normal">Cashflow RPL 1</span>
                </div>
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
                <a data-tab="kasbon" class="sidebar-nav-item">
                    <i class="fa-solid fa-hand-holding-dollar w-4 text-center"></i>
                    <span>Kasbon</span>
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

        <div class="mt-8 pt-4 border-t border-[#23252a]">
            <a href="../auth/logout.php" class="btn-danger w-full justify-center gap-2">
                <i class="fa-solid fa-right-from-bracket text-xs"></i>
                <span>Keluar (Logout)</span>
            </a>
        </div>
    </aside>

    <!-- Main Admin Content -->
    <main class="flex-1 p-6 md:p-8 max-w-6xl md:h-screen md:overflow-y-auto">
        <div class="flex items-center justify-between mb-6 pb-4 border-b border-[#23252a]">
            <div>
                <div class="eyebrow">Sesi Aktif</div>
                <div class="text-sm text-[#8a8f98]">Login sebagai <b class="text-[#f7f8f8]"><?= htmlspecialchars($nama) ?></b></div>
            </div>
            <a href="../../index.php" target="_blank" class="btn-secondary text-xs gap-2">
                <span>Buka Web Publik</span>
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
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
                    <h2 class="display-md">Kelola Kasbon</h2>
                    <p class="text-sm text-[#8a8f98]">Catat dan kelola kasbon (piutang) siswa.</p>
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
                            <option value="belum_lunas">Belum Lunas</option>
                            <option value="lunas">Lunas</option>
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
                    <p class="text-sm text-[#8a8f98]">Catat dan kelola dana BMS (setor/tarik).</p>
                </div>
                <button id="bms-add-btn" class="btn-primary gap-2">
                    <i class="fa-solid fa-plus text-xs"></i>
                    <span>Tambah Transaksi</span>
                </button>
            </div>

            <div id="bms-wrap" class="table-container overflow-x-auto"></div>
        </section>
    </main>

    <!-- Modal Form Transaksi Jurnal -->
    <div id="modal-jurnal" class="modal-overlay hidden">
        <form id="form-jurnal" class="modal-card">
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#23252a]">
                <h3 class="headline text-lg flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-sm text-[#5e6ad2]"></i>
                    <span>Transaksi Jurnal</span>
                </h3>
                <button type="button" id="modal-close" class="text-[#8a8f98] hover:text-[#f7f8f8]">
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
            <div class="flex items-center justify-between mb-4 pb-2 border-b border-[#23252a]">
                <h3 class="headline text-lg flex items-center gap-2">
                    <i class="fa-solid fa-sack-dollar text-sm text-[#5e6ad2]"></i>
                    <span>Form Kas BMS</span>
                </h3>
                <button type="button" id="bms-modal-close" class="text-[#8a8f98] hover:text-[#f7f8f8]">
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
                        <label class="inline-flex items-center gap-2 text-sm text-[#f7f8f8]">
                            <input type="radio" name="bms-jenis" value="setor" checked> Setor
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-[#f7f8f8]">
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="../../assets/js/admin.js"></script>
</body>
</html>
