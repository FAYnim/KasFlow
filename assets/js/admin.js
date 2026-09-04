$(function () {
    const $tabs = $('[data-tab-content]');
    const $navItems = $('[data-tab]');
    const kasState = { saved: {}, pending: {}, tarif: 0, bulan: '', tahun: 0 };

    // Theme Switcher Management for Admin
    function updateThemeUI(theme) {
        if (theme === 'dark') {
            $('#theme-toggle-icon, #theme-toggle-icon-mobile').attr('class', 'fa-solid fa-moon text-indigo-400 text-sm');
            $('#theme-toggle-btn, #theme-toggle-btn-mobile').attr('title', 'Switch to Light Theme');
        } else {
            $('#theme-toggle-icon, #theme-toggle-icon-mobile').attr('class', 'fa-solid fa-sun text-amber-500 text-sm');
            $('#theme-toggle-btn, #theme-toggle-btn-mobile').attr('title', 'Switch to Dark Theme');
        }
    }

    const currentTheme = localStorage.getItem('theme') || 'dark';
    $('html').attr('data-theme', currentTheme);
    updateThemeUI(currentTheme);

    $('#theme-toggle-btn, #theme-toggle-btn-mobile').on('click', function () {
        const newTheme = $('html').attr('data-theme') === 'dark' ? 'light' : 'dark';
        $('html').attr('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeUI(newTheme);
        if (typeof alokasiDonut !== 'undefined' && alokasiDonut) {
            const inkColor = getComputedStyle(document.documentElement).getPropertyValue('--ink').trim() || '#fff';
            alokasiDonut.options.plugins.legend.labels.color = inkColor;
            alokasiDonut.update();
        }
    });

    // Mobile Sidebar Management for Admin
    function openSidebar() {
        $('#sidebar').removeClass('-translate-x-full');
        $('#sidebar-overlay').removeClass('hidden');
        $('body').addClass('overflow-hidden md:overflow-auto');
    }

    function closeSidebar() {
        $('#sidebar').addClass('-translate-x-full');
        $('#sidebar-overlay').addClass('hidden');
        $('body').removeClass('overflow-hidden md:overflow-auto');
    }

    $('#btn-hamburger').on('click', openSidebar);
    $('#btn-close-sidebar, #sidebar-overlay').on('click', closeSidebar);

    function loadEksporTab() {
        if (!cfOverviewData.accounts || cfOverviewData.accounts.length === 0) {
            $.getJSON('src/api/admin.php?action=get_finance_overview', res => {
                if (res && res.ok) cfOverviewData = res;
                $('#export-type').trigger('change');
            });
        } else {
            $('#export-type').trigger('change');
        }
    }

    const loaders = {
        dashboard: lDash,
        siswa: lSiswa,
        kas: lKas,
        jurnal: lCashflow,
        accounts_categories: lMaster,
        ekspor: loadEksporTab,
        riwayat: loadRiwayatAdmin,
        pengaturan: loadPengaturanAdmin,
    };

    const activate = (n) => { 
        $tabs.addClass('hidden'); 
        $(`[data-tab-content="${n}"]`).removeClass('hidden'); 
        
        $navItems.removeClass('active');
        $(`[data-tab="${n}"]`).addClass('active');

        if (loaders[n]) loaders[n](); 
    };

    $('[data-tab]').on('click', function () {
        activate($(this).data('tab'));
        if ($(window).width() < 768) {
            closeSidebar();
        }
    });

    const fmt = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    const bulanList = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const now = new Date();
    
    $('#admin-bulan').html(bulanList.map(b => `<option ${b===bulanList[now.getMonth()]?'selected':''}>${b}</option>`).join(''));
    $('#admin-tahun').html([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option ${y===now.getFullYear()?'selected':''}>${y}</option>`).join(''));
    $('#admin-bulan, #admin-tahun').on('change', lKas);

    $('#jurnal-bulan').html([''].concat(bulanList).map(b => `<option value="${b}">${b||'Semua'}</option>`).join(''));
    $('#jurnal-tahun').html([''].concat([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1]).map(y => `<option value="${y}">${y||'Semua'}</option>`).join(''));
    $('#jurnal-bulan, #jurnal-tahun').on('change', () => { adminJurnalPage = 1; lJurnal(); });
    $('#jurnal-reset').on('click', () => {
        $('#jurnal-bulan').val('');
        $('#jurnal-tahun').val('');
        adminJurnalPage = 1;
        lJurnal();
    });

    // Kasbon
    $('#admin-kasbon-bulan').html(bulanList.map(b => `<option ${b===bulanList[now.getMonth()]?'selected':''}>${b}</option>`).join(''));
    $('#admin-kasbon-tahun').html([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option ${y===now.getFullYear()?'selected':''}>${y}</option>`).join(''));
    $('#admin-kasbon-bulan, #admin-kasbon-tahun').on('change', lKasbon);

    // Load daftar siswa ke dropdown kasbon form
    function loadKasbonSiswaOptions(selectedSiswaId) {
        $.getJSON('src/api/admin.php?action=list_siswa', function(rows) {
            const bulanList2 = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            const sorted = rows.slice().sort(function(a, b) {
                const an = parseInt(a.absen), bn = parseInt(b.absen);
                if (!isNaN(an) && !isNaN(bn)) return an - bn;
                return String(a.absen||'').localeCompare(String(b.absen||''), 'id');
            });
            const opts = sorted.map(function(s) {
                const label = s.absen ? `[Absen ${s.absen}] ${escapeHtml(s.nama)}` : escapeHtml(s.nama);
                const sel = (selectedSiswaId && parseInt(selectedSiswaId) === parseInt(s.id)) ? 'selected' : '';
                return `<option value="${s.id}" ${sel}>${label}</option>`;
            });
            $('#kasbon-siswa-id').html('<option value="">— Pilih Siswa —</option>' + opts.join(''));
        });
    }
    loadKasbonSiswaOptions();

    activate('dashboard');

    function lKasbon() {
        const bulan = $('#admin-kasbon-bulan').val();
        const tahun = $('#admin-kasbon-tahun').val();
        $.getJSON('src/api/public.php', { action: 'get_kasbon', bulan, tahun }, function(data) {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-16">#</th>
                        <th class="w-32">Tanggal</th>
                        <th>Peminjam</th>
                        <th>Keterangan</th>
                        <th class="text-right w-36">Jumlah</th>
                        <th class="w-28">Status</th>
                        <th class="w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (!data || data.length === 0) {
                h += `<tr><td colspan="7" class="text-center py-6 text-[var(--ink-muted)]">Tidak ada data dana talangan.</td></tr>`;
            } else {
                h += data.map(function(r, i) {
                    const badge = r.status === 'lunas'
                        ? '<span class="badge-status badge-success font-medium"><i class="fa-solid fa-circle-check text-[10px]"></i> <span>Sudah Diganti</span></span>'
                        : '<span class="badge-status badge-warning font-medium"><i class="fa-solid fa-clock text-[10px]"></i> <span>Belum Diganti</span></span>';
                    const toggleBtn = r.status === 'lunas'
                        ? '<button class="text-yellow-400 hover:text-yellow-300 text-xs toggle-kasbon" data-id="' + r.id + '" data-status="belum_lunas" title="Batalkan Penggantian"><i class="fa-solid fa-rotate-left"></i></button>'
                        : '<button class="text-green-400 hover:text-green-300 text-xs toggle-kasbon" data-id="' + r.id + '" data-status="lunas" title="Tandai Sudah Diganti"><i class="fa-solid fa-circle-check"></i></button>';
                    // Tampilkan nomor absen di samping nama jika tersedia
                    const namaTampil = r.absen
                        ? `<span class="font-medium text-[var(--ink)]">${escapeHtml(r.nama)}</span><span class="ml-1.5 text-[10px] font-mono text-[var(--ink-muted)] bg-[var(--surface-2)] px-1.5 py-0.5 rounded">Absen ${escapeHtml(r.absen)}</span>`
                        : `<span class="font-medium text-[var(--ink)]">${escapeHtml(r.nama)}</span>`;
                    return '<tr>' +
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + (i + 1) + '</td>' +
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + escapeHtml(r.tanggal) + '</td>' +
                        '<td>' + namaTampil + ' ' + toggleBtn + '</td>' +
                        '<td class="text-[var(--ink-muted)]">' + escapeHtml(r.keterangan) + '</td>' +
                        '<td class="text-right font-mono-num font-medium text-[var(--ink)]">' + fmt(r.jumlah) + '</td>' +
                        '<td>' + badge + '</td>' +
                        '<td class="text-right space-x-1">' +
                            '<button class="btn-secondary text-xs px-2.5 py-1 edit-kasbon gap-1" data-id="' + r.id + '" data-siswa-id="' + (r.siswa_id || '') + '" data-tanggal="' + escapeHtml(r.tanggal) + '" data-keterangan="' + escapeHtml(r.keterangan) + '" data-jumlah="' + r.jumlah + '" data-status="' + r.status + '">' +
                                '<i class="fa-solid fa-pen text-[10px]"></i> <span>Edit</span>' +
                            '</button>' +
                            '<button class="btn-danger text-xs px-2.5 py-1 del-kasbon gap-1" data-id="' + r.id + '">' +
                                '<i class="fa-solid fa-trash-can text-[10px]"></i> <span>Hapus</span>' +
                            '</button>' +
                        '</td>' +
                    '</tr>';
                }).join('');
            }
            h += '</tbody></table>';
            $('#kasbon-wrap').html(h);
        }).fail(function() {
            $('#kasbon-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Gagal memuat data dana talangan.</div>');
        });
    }

    $(document).on('click', '.edit-kasbon', function () {
        const $btn = $(this);
        const siswaId = $btn.data('siswa-id');
        $('#kasbon-edit-id').val($btn.data('id'));
        $('#kasbon-tanggal').val($btn.data('tanggal'));
        $('#kasbon-keterangan').val($btn.data('keterangan'));
        $('#kasbon-jumlah').val($btn.data('jumlah'));
        $('#kasbon-status').val($btn.data('status'));
        // Isi dropdown siswa dengan nilai terkini, lalu set selected
        loadKasbonSiswaOptions(siswaId);
        $('#kasbon-submit-btn').html('<i class="fa-solid fa-floppy-disk text-xs"></i> <span>Update</span>');
        $('#kasbon-cancel-btn').removeClass('hidden');
        // Scroll ke form
        document.getElementById('form-kasbon').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    $(document).on('click', '.toggle-kasbon', function () {
        const id = $(this).data('id');
        const newStatus = $(this).data('status');
        const action = newStatus === 'lunas' ? 'mark_lunas_kasbon' : 'mark_belum_lunas_kasbon';
        $.post('src/api/admin.php?action=' + action, { id: id }, function() {
            lKasbon();
            lDash();
        }, 'json').fail(function() {
            alert('Gagal mengubah status dana talangan.');
        });
    });

    $(document).on('click', '.del-kasbon', function () {
        if (!confirm('Hapus data talangan ini?')) return;
        $.post('src/api/admin.php?action=delete_kasbon', { id: $(this).data('id') }, function() {
            lKasbon();
            lDash();
        }, 'json').fail(function() {
            alert('Gagal menghapus dana talangan.');
        });
    });

    $('#form-kasbon').on('submit', function (e) {
        e.preventDefault();
        const siswaId = $('#kasbon-siswa-id').val();
        const jumlah = parseFloat($('#kasbon-jumlah').val());
        if (!siswaId) { alert('Pilih siswa peminjam terlebih dahulu.'); return; }
        if (!jumlah || jumlah <= 0) { alert('Jumlah harus lebih dari 0.'); return; }
        const editId = $('#kasbon-edit-id').val();
        const payload = {
            action: editId ? 'update_kasbon' : 'add_kasbon',
            id: editId || undefined,
            siswa_id: siswaId,
            tanggal: $('#kasbon-tanggal').val(),
            keterangan: $('#kasbon-keterangan').val().trim(),
            jumlah: jumlah,
            status: $('#kasbon-status').val()
        };
        $.post('src/api/admin.php?action=' + payload.action, payload, function(res) {
            if (res.ok) {
                $('#form-kasbon')[0].reset();
                $('#kasbon-edit-id').val('');
                loadKasbonSiswaOptions();
                $('#kasbon-submit-btn').html('<i class="fa-solid fa-plus text-xs"></i> <span>Tambah</span>');
                $('#kasbon-cancel-btn').addClass('hidden');
                lKasbon();
                lDash();
            } else {
                alert(res.error || 'Gagal menyimpan dana talangan.');
            }
        }, 'json').fail(function() {
            alert('Gagal menyimpan dana talangan.');
        });
    });

    $('#kasbon-cancel-btn').on('click', function () {
        $('#form-kasbon')[0].reset();
        $('#kasbon-edit-id').val('');
        loadKasbonSiswaOptions();
        $('#kasbon-submit-btn').html('<i class="fa-solid fa-plus text-xs"></i> <span>Tambah</span>');
        $(this).addClass('hidden');
    });

    function lDash() {
        $.getJSON('src/api/admin.php?action=get_finance_overview', res => {
            if (!res || !res.ok) return;
            const s = res.summary || {};
            const queueNominal = (s.pending_queue_nominal !== undefined && s.pending_queue_nominal !== null)
                ? s.pending_queue_nominal
                : ((s.total_pending_queue !== undefined && s.total_pending_queue !== null) ? s.total_pending_queue : 0);
            const queueCount = s.pending_queue_count ?? 0;
            const queueText = queueCount > 0 
                ? `${queueCount} antrean (${fmt(queueNominal)})` 
                : '0 antrean';
            const totalSaldo = (s.total_saldo !== undefined && s.total_saldo !== null)
                ? s.total_saldo
                : ((s.total_balance !== undefined && s.total_balance !== null) ? s.total_balance : 0);
            const cards = [
                ['Total Saldo Kas', fmt(totalSaldo), 'text-[var(--primary)]', '<i class="fa-solid fa-wallet text-sm"></i>'],
                ['Total Pemasukan', fmt(s.total_income), 'text-emerald-500', '<i class="fa-solid fa-arrow-trend-up text-sm"></i>'],
                ['Total Pengeluaran', fmt(s.total_expense), 'text-rose-500', '<i class="fa-solid fa-arrow-trend-down text-sm"></i>'],
                ['Antrean Kas Mingguan', queueText, queueCount > 0 ? 'text-amber-500' : 'text-[var(--ink-muted)]', '<i class="fa-solid fa-bell text-sm"></i>'],
            ];
            $('#admin-summary').html(cards.map(([t, v, colorClass, icon]) =>
                `<div class="card-linear">
                    <div class="flex items-center justify-between mb-2">
                        <span class="eyebrow">${t}</span>
                        <span class="text-[var(--ink-muted)]">${icon}</span>
                    </div>
                    <div class="text-2xl font-bold font-mono-num ${colorClass}">${v}</div>
                </div>`
            ).join(''));
        });
    }

    let siswaSortDir = 'asc';
    function sortSiswa(rows) {
        const has = (s) => s.absen !== null && s.absen !== undefined && String(s.absen).trim() !== '';
        const filled = rows.filter(has);
        const empty = rows.filter(s => !has(s));
        const sign = siswaSortDir === 'asc' ? 1 : -1;
        filled.sort((a, b) => {
            const an = Number(a.absen), bn = Number(b.absen);
            if (!Number.isNaN(an) && !Number.isNaN(bn)) return (an - bn) * sign;
            return String(a.absen).localeCompare(String(b.absen), 'id') * sign;
        });
        return filled.concat(empty);
    }
    function lSiswa() {
        $.getJSON('src/api/admin.php?action=list_siswa', rows => {
            const sorted = sortSiswa(rows);
            const icon = siswaSortDir === 'asc'
                ? '<i class="fa-solid fa-arrow-up text-[9px]"></i>'
                : '<i class="fa-solid fa-arrow-down text-[9px]"></i>';
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">
                            <button id="siswa-sort-absen" type="button" class="inline-flex items-center gap-1.5 hover:text-[var(--ink)] transition-colors">
                                <span>Absen</span>
                                <span id="siswa-sort-icon" class="text-[var(--primary)]">${icon}</span>
                            </button>
                        </th>
                        <th>Nama Siswa</th>
                        <th class="w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (sorted.length === 0) {
                h += `<tr><td colspan="3" class="text-center py-6 text-[var(--ink-muted)]">Belum ada data siswa.</td></tr>`;
            } else {
                h += sorted.map(s =>
                    `<tr>
                        <td class="font-mono text-xs text-[var(--ink-muted)]">${escapeHtml(s.absen||'-')}</td>
                        <td class="font-medium text-[var(--ink)]">${escapeHtml(s.nama)}</td>
                        <td class="text-right">
                            <button class="btn-danger del-s text-xs gap-1" data-id="${s.id}">
                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                                <span>Hapus</span>
                            </button>
                        </td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
            $('#siswa-wrap').html(h);
        });
    }

    $(document).on('click', '#siswa-sort-absen', function () {
        siswaSortDir = siswaSortDir === 'asc' ? 'desc' : 'asc';
        lSiswa();
    });

    $(document).on('click', '.del-s', function () {
        if (!confirm('Hapus siswa ini beserta seluruh data kas terkait?')) return;
        $.post('src/api/admin.php?action=delete_siswa', { id: $(this).data('id') }, r => {
            lSiswa();
            loadKasbonSiswaOptions();
        }, 'json');
    });

    $('#form-siswa').on('submit', function (e) {
        e.preventDefault();
        $.post('src/api/admin.php?action=add_siswa', $(this).serialize(), r => {
            if (r.ok) { this.reset(); lSiswa(); loadKasbonSiswaOptions(); } else alert(r.error);
        }, 'json');
    });

    function lKas() {
        const bulan = $('#admin-bulan').val(), tahun = $('#admin-tahun').val();
        kasState.bulan = bulan; kasState.tahun = tahun;
        $.getJSON('src/api/public.php', { action:'get_kas', bulan, tahun }, res => {
            const rows = res.rows || [], tarif = res.tarif || 0;
            kasState.tarif = tarif; kasState.saved = {}; kasState.pending = {};
            rows.forEach(r => {
                const m = {};
                for (let i = 1; i <= 5; i++) m[i] = r['m'+i] ? 1 : 0;
                kasState.saved[r.id] = m;
            });
            renderKas(rows);
            updateKasToolbar();
        });
    }

    function renderKas(rows) {
        const tarif = kasState.tarif || 0;
        let h = `<table class="table-linear">
            <thead>
                <tr>
                    <th class="w-16">Absen</th>
                    <th>Nama Siswa</th>
                    ${[1,2,3,4,5].map(i => `<th class="text-center w-16">M${i}</th>`).join('')}
                    <th class="text-right w-36">Total Bayar</th>
                </tr>
            </thead>
            <tbody>`;
        if (rows.length === 0) {
            h += `<tr><td colspan="8" class="text-center py-6 text-[var(--ink-muted)]">Tidak ada data kas siswa.</td></tr>`;
        } else {
            h += rows.map(r => {
                const state = kasState.pending[r.id] || kasState.saved[r.id] || {};
                const total = [1,2,3,4,5].reduce((s,i) => s + (state[i] || 0), 0) * tarif;
                return `<tr>
                    <td class="text-[var(--ink-muted)] font-mono-num">${escapeHtml(r.absen || '-')}</td>
                    <td class="font-medium text-[var(--ink)]">${escapeHtml(r.nama)}</td>
                    ${[1,2,3,4,5].map(i => {
                        const dirty = kasState.pending[r.id] && kasState.pending[r.id][i] !== kasState.saved[r.id][i];
                        return `<td class="text-center">
                            <input type="checkbox" class="kas-cb" data-siswa="${r.id}" data-minggu="${i}" ${state[i]?'checked':''}>
                            ${dirty ? '<span class="block w-1.5 h-1.5 rounded-full bg-amber-400 mx-auto mt-0.5" title="Belum disimpan"></span>' : ''}
                        </td>`;
                    }).join('')}
                    <td class="text-right font-mono-num font-medium text-[var(--ink)] total-cell" data-siswa="${r.id}">${fmt(total)}</td>
                </tr>`;
            }).join('');
        }
        h += '</tbody></table>';
        $('#kas-wrap').html(h);
    }

    function updateKasToolbar() {
        const n = Object.keys(kasState.pending).length;
        if (n === 0) {
            $('#kas-pending-badge').addClass('hidden');
            $('#kas-save-btn').addClass('hidden');
            $('#kas-reset-btn').addClass('hidden');
        } else {
            $('#kas-pending-badge').removeClass('hidden');
            $('#kas-pending-count').text(n);
            $('#kas-save-btn').removeClass('hidden');
            $('#kas-reset-btn').removeClass('hidden');
        }
    }

    $(document).on('change', '.kas-cb', function () {
        const cb = $(this);
        const sid = cb.data('siswa'), m = cb.data('minggu');
        if (!kasState.pending[sid]) kasState.pending[sid] = { ...(kasState.saved[sid] || {}) };
        kasState.pending[sid][m] = cb.is(':checked') ? 1 : 0;
        if (kasState.pending[sid][m] === kasState.saved[sid][m]) {
            delete kasState.pending[sid][m];
            if (Object.keys(kasState.pending[sid]).length === 0) delete kasState.pending[sid];
        }
        const tarif = kasState.tarif || 0;
        const state = { ...(kasState.saved[sid] || {}), ...(kasState.pending[sid] || {}) };
        const total = [1,2,3,4,5].reduce((s,i) => s + (state[i] || 0), 0) * tarif;
        $(`.total-cell[data-siswa="${sid}"]`).text(fmt(total));
        const td = cb.closest('td');
        const dot = td.find('span');
        if (kasState.pending[sid] && kasState.pending[sid][m] !== undefined && kasState.pending[sid][m] !== kasState.saved[sid][m]) {
            if (!dot.length) td.append('<span class="block w-1.5 h-1.5 rounded-full bg-amber-400 mx-auto mt-0.5" title="Belum disimpan"></span>');
        } else {
            dot.remove();
        }
        updateKasToolbar();
    });

    // ── Kas Mingguan: Save dengan Modal Konfirmasi Integrasi ────────────
    // State sementara untuk ditransfer ke modal konfirmasi
    let _kasConfirmChanges = null;

    function doSaveKas(changes, extraData, $btn) {
        const data = Object.assign({ bulan: kasState.bulan, tahun: kasState.tahun, changes: JSON.stringify(changes) }, extraData);
        $.ajax({
            url: 'src/api/admin.php?action=bulk_update_kas',
            method: 'POST',
            data: data,
            dataType: 'json',
            success: r => {
                if (r.ok) {
                    Object.keys(kasState.pending).forEach(sid => { kasState.saved[sid] = { ...kasState.saved[sid], ...kasState.pending[sid] }; });
                    kasState.pending = {};
                    lKas();
                    lDash();
                    if (r.queue_id) {
                        const nomStr = r.net_nominal ? ` (${fmt(Math.abs(r.net_nominal))})` : '';
                        alert(`Perubahan kas mingguan tersimpan! Antrean baru${nomStr} telah dibuat di tab Cashflow & Dompet untuk dialokasikan.`);
                    }
                } else {
                    alert(r.error || 'Gagal menyimpan.');
                }
            },
            error: () => alert('Gagal terhubung server.'),
            complete: () => {
                if ($btn) $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk text-[10px]"></i> <span>Simpan</span>');
            }
        });
    }

    $('#kas-save-btn').on('click', function () {
        const changes = [];
        Object.keys(kasState.pending).forEach(sid => {
            Object.keys(kasState.pending[sid]).forEach(m => {
                changes.push({ siswa_id: parseInt(sid), minggu: parseInt(m), checked: kasState.pending[sid][m] });
            });
        });
        if (changes.length === 0) return;

        const $btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin text-[10px]"></i> <span>Menyimpan...</span>');
        doSaveKas(changes, {}, $btn);
    });




    $('#kas-reset-btn').on('click', function () {
        if (Object.keys(kasState.pending).length === 0) return;
        if (!confirm('Batalkan semua perubahan yang belum disimpan?')) return;
        kasState.pending = {};
        lKas();
    });

    $('#admin-bulan, #admin-tahun').on('change', () => {
        if (Object.keys(kasState.pending).length === 0) { lKas(); return; }
        if (!confirm('Ada perubahan belum disimpan. Ganti periode dan buang perubahan?')) return;
        kasState.pending = {};
        lKas();
    });

    // ── Admin pagination state & helpers ────────────────────────────────
    let adminJurnalPage  = 1;
    let adminRiwayatPage = 1;

    function renderPagination(containerId, pagination, onPage) {
        const $container = $('#' + containerId);
        if (!pagination || pagination.total_pages <= 1) {
            $container.empty();
            return;
        }
        const { page, total_pages, total_records, limit } = pagination;
        const from = ((page - 1) * limit) + 1;
        const to   = Math.min(page * limit, total_records);

        // Windowed pages: first, last, current-1..current+1
        const pages = new Set([1, total_pages]);
        for (let i = Math.max(1, page - 1); i <= Math.min(total_pages, page + 1); i++) pages.add(i);
        const sorted = Array.from(pages).sort((a, b) => a - b);

        let btns = '';
        btns += `<button class="pagination-btn${page === 1 ? ' pagination-btn-disabled' : ''}" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}>
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>`;
        let prev = 0;
        sorted.forEach(p => {
            if (p - prev > 1) btns += `<button class="pagination-btn pagination-btn-ellipsis">…</button>`;
            btns += `<button class="pagination-btn${p === page ? ' pagination-btn-active' : ''}" data-page="${p}">${p}</button>`;
            prev = p;
        });
        btns += `<button class="pagination-btn${page === total_pages ? ' pagination-btn-disabled' : ''}" data-page="${page + 1}" ${page === total_pages ? 'disabled' : ''}>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>`;

        $container.html(`
            <div class="pagination-container">
                <span class="pagination-info">Menampilkan ${from}–${to} dari ${total_records} data</span>
                <div class="pagination-controls">${btns}</div>
            </div>
        `);

        $container.find('.pagination-btn[data-page]').not('.pagination-btn-disabled').not('.pagination-btn-ellipsis').on('click', function () {
            const p = parseInt($(this).data('page'));
            if (p >= 1 && p <= total_pages && p !== page) onPage(p);
        });
    }

    // ══════════════════════════════════════════════════════════════════════════════
    //  CENTRALIZED CASHFLOW & MONEY TRACKER
    // ══════════════════════════════════════════════════════════════════════════════
    let cfOverviewData = { accounts: [], categories: [], summary: {}, pending_queue: [] };
    let cfPage = 1;

    function lCashflow() {
        $.getJSON('src/api/admin.php?action=get_finance_overview', res => {
            if (!res || !res.ok) return;
            cfOverviewData = res;

            // 1. Render Accounts & Total Balance Grid
            const s = res.summary || {};
            const accounts = res.accounts || [];
            let accGridHtml = `
                <div class="card-linear border-indigo-500/30 bg-indigo-500/5">
                    <div class="flex items-center justify-between mb-2">
                        <span class="eyebrow text-indigo-400">Total Saldo Kas Bersih</span>
                        <span class="text-indigo-400"><i class="fa-solid fa-vault text-base"></i></span>
                    </div>
                    <div class="text-2xl font-bold font-mono-num text-[var(--ink)]">${fmt(s.total_saldo ?? s.total_balance)}</div>
                    <div class="text-[11px] text-[var(--ink-muted)] mt-1 flex items-center justify-between">
                        <span>Masuk: <b class="text-emerald-500 font-mono">${fmt(s.total_income)}</b></span>
                        <span>Keluar: <b class="text-rose-500 font-mono">${fmt(s.total_expense)}</b></span>
                    </div>
                </div>
            `;

            accGridHtml += accounts.map(a => {
                const icon = a.icon || 'fa-solid fa-wallet';
                const bal = parseFloat(a.balance || 0);
                const balClass = bal >= 0 ? 'text-[var(--ink)]' : 'text-rose-500';
                return `
                    <div class="card-linear">
                        <div class="flex items-center justify-between mb-2">
                            <span class="eyebrow">${escapeHtml(a.name)}</span>
                            <span class="text-[var(--ink-muted)]"><i class="${escapeHtml(icon)} text-sm"></i></span>
                        </div>
                        <div class="text-2xl font-bold font-mono-num ${balClass}">${fmt(bal)}</div>
                        <div class="text-[11px] text-[var(--ink-muted)] mt-1 capitalize">${escapeHtml(a.type || 'Akun')}</div>
                    </div>
                `;
            }).join('');

            $('#cashflow-accounts-grid').html(accGridHtml);

            // 2. Render Uncategorized Queue Notification Banner
            const pending = res.pending_queue || [];
            if (pending.length > 0) {
                const totalQueueNominal = pending.reduce((sum, item) => sum + parseFloat(item.nominal || 0), 0);
                const displayNominal = (s.pending_queue_nominal !== undefined && s.pending_queue_nominal !== null)
                    ? s.pending_queue_nominal
                    : ((s.total_pending_queue !== undefined && s.total_pending_queue !== null) ? s.total_pending_queue : totalQueueNominal);
                $('#queue-count-badge').text(pending.length);
                $('#queue-nominal-badge').text(fmt(displayNominal));
                $('#cashflow-queue-banner').removeClass('hidden');
            } else {
                $('#cashflow-queue-banner').addClass('hidden');
            }

            // 3. Populate Filter Dropdowns
            const accFilterOpts = '<option value="">Semua Akun</option>' +
                accounts.map(a => `<option value="${a.id}">${escapeHtml(a.name)}</option>`).join('');
            $('#cf-filter-account').html(accFilterOpts);

            const catFilterOpts = '<option value="">Semua Kategori</option>' +
                (res.categories || []).map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            $('#cf-filter-category').html(catFilterOpts);

            // 4. Load Transaction Table
            loadTransactionsTable();
        });
    }

    function loadTransactionsTable(page) {
        if (page !== undefined) cfPage = page;
        const params = {
            action: 'get_transactions',
            page: cfPage,
            limit: 15,
            type: $('#cf-filter-type').val() || '',
            account_id: $('#cf-filter-account').val() || '',
            category_id: $('#cf-filter-category').val() || '',
            start_date: $('#cf-filter-dari').val() || '',
            end_date: $('#cf-filter-sampai').val() || '',
            search: $('#cf-filter-search').val() || ''
        };

        $.getJSON('src/api/admin.php', params, res => {
            if (!res || !res.ok) {
                $('#cashflow-table-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Gagal memuat transaksi.</div>');
                return;
            }

            const rows = res.data || [];
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-28">Tanggal</th>
                        <th class="w-24">Tipe</th>
                        <th class="w-36">Kategori</th>
                        <th class="w-40">Dompet / Akun</th>
                        <th>Keterangan</th>
                        <th class="text-right w-36">Nominal</th>
                        <th class="w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;

            if (rows.length === 0) {
                h += `<tr><td colspan="7" class="text-center py-6 text-[var(--ink-muted)]">Belum ada data transaksi sesuai filter.</td></tr>`;
            } else {
                h += rows.map(t => {
                    let typeBadge = '';
                    let nomColor = 'text-[var(--ink)]';
                    let nomPrefix = '';

                    if (t.type === 'income') {
                        typeBadge = '<span class="badge-status badge-success font-medium"><i class="fa-solid fa-arrow-down text-[9px]"></i> Masuk</span>';
                        nomColor = 'text-emerald-500 font-semibold';
                        nomPrefix = '+';
                    } else if (t.type === 'expense') {
                        typeBadge = '<span class="badge-status badge-danger font-medium"><i class="fa-solid fa-arrow-up text-[9px]"></i> Keluar</span>';
                        nomColor = 'text-rose-500 font-semibold';
                        nomPrefix = '-';
                    } else if (t.type === 'transfer') {
                        typeBadge = '<span class="badge-status badge-neutral font-medium"><i class="fa-solid fa-arrow-right-arrow-left text-[9px]"></i> Transfer</span>';
                        nomColor = 'text-indigo-400 font-semibold';
                    }

                    const catBadge = t.category_name 
                        ? `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[11px] font-medium" style="background:${escapeHtml(t.category_color || '#3b82f6')}15; color:${escapeHtml(t.category_color || '#3b82f6')}">
                            <i class="${escapeHtml(t.category_icon || 'fa-solid fa-tag')} text-[9px]"></i>
                            <span>${escapeHtml(t.category_name)}</span>
                        </span>`
                        : '<span class="text-[var(--ink-muted)] text-xs">—</span>';

                    let accText = escapeHtml(t.account_name || '—');
                    if (t.type === 'transfer' && t.to_account_name) {
                        accText = `<div class="text-xs flex items-center gap-1"><span>${escapeHtml(t.account_name)}</span> <i class="fa-solid fa-arrow-right text-[10px] text-[var(--ink-muted)]"></i> <span>${escapeHtml(t.to_account_name)}</span></div>`;
                    }

                    const jsonAttr = escapeHtml(JSON.stringify(t));

                    return `
                        <tr>
                            <td class="font-mono text-xs text-[var(--ink-muted)]">${escapeHtml(t.date)}</td>
                            <td>${typeBadge}</td>
                            <td>${catBadge}</td>
                            <td class="text-xs text-[var(--ink)]">${accText}</td>
                            <td class="text-xs text-[var(--ink)]">${escapeHtml(t.description || '—')}</td>
                            <td class="text-right font-mono-num text-xs ${nomColor}">${nomPrefix}${fmt(t.amount)}</td>
                            <td class="text-right space-x-1">
                                <button class="btn-secondary text-xs px-2 py-1 edit-tx-btn" data-tx='${jsonAttr}'>
                                    <i class="fa-solid fa-pen text-[9px]"></i>
                                </button>
                                <button class="btn-danger text-xs px-2 py-1 del-tx-btn" data-id="${t.id}">
                                    <i class="fa-solid fa-trash-can text-[9px]"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                }).join('');
            }

            h += '</tbody></table>';
            $('#cashflow-table-wrap').html(h);
            renderPagination('cashflow-pagination', res.pagination, p => loadTransactionsTable(p));
        });
    }

    // Filter Listeners
    $('#cf-filter-type, #cf-filter-account, #cf-filter-category, #cf-filter-dari, #cf-filter-sampai').on('change', () => {
        cfPage = 1;
        loadTransactionsTable();
    });

    let cfSearchTimer = null;
    $('#cf-filter-search').on('input', function () {
        clearTimeout(cfSearchTimer);
        cfSearchTimer = setTimeout(() => {
            cfPage = 1;
            loadTransactionsTable();
        }, 300);
    });

    $('#cf-filter-apply').on('click', () => {
        cfPage = 1;
        loadTransactionsTable();
    });

    $('#cf-filter-reset').on('click', () => {
        $('#cf-filter-type').val('');
        $('#cf-filter-account').val('');
        $('#cf-filter-category').val('');
        $('#cf-filter-dari').val('');
        $('#cf-filter-sampai').val('');
        $('#cf-filter-search').val('');
        cfPage = 1;
        loadTransactionsTable();
    });

    // Transaction Modal Helpers
    function setTxType(type) {
        $('#tx-type').val(type);
        $('.tx-type-btn').removeClass('border-emerald-500 border-rose-500 border-indigo-500 bg-[var(--surface-2)]');
        $(`.tx-type-btn[data-type="${type}"]`).addClass('bg-[var(--surface-2)]');

        const accounts = cfOverviewData.accounts || [];
        const categories = cfOverviewData.categories || [];

        // Populate accounts
        const accOpts = accounts.map(a => `<option value="${a.id}">${escapeHtml(a.name)} (Saldo: ${fmt(a.balance)})</option>`).join('');
        $('#tx-account-id').html(accOpts);
        $('#tx-to-account-id').html(accOpts);

        // Filter categories by type
        const catFiltered = categories.filter(c => c.type === 'both' || c.type === type);
        const catOpts = catFiltered.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        $('#tx-category-id').html(catOpts);

        if (type === 'transfer') {
            $(`.tx-type-btn[data-type="transfer"]`).addClass('border-indigo-500');
            $('#tx-account-label').text('Dari Dompet / Akun *');
            $('#tx-to-account-group').removeClass('hidden');
            $('#tx-category-group').addClass('hidden');
        } else {
            if (type === 'income') $(`.tx-type-btn[data-type="income"]`).addClass('border-emerald-500');
            if (type === 'expense') $(`.tx-type-btn[data-type="expense"]`).addClass('border-rose-500');
            $('#tx-account-label').text('Dompet / Akun *');
            $('#tx-to-account-group').addClass('hidden');
            $('#tx-category-group').removeClass('hidden');
        }
    }

    function openTxModal(tx, defaultType = 'income') {
        $('#modal-transaction').removeClass('hidden');
        const f = $('#form-transaction')[0];
        f.reset();

        const type = tx ? tx.type : defaultType;
        setTxType(type);

        if (tx) {
            $('#modal-tx-title span').text('Edit Transaksi');
            $('#tx-id').val(tx.id);
            $('#tx-date').val(tx.date);
            $('#tx-amount').val(tx.amount);
            $('#tx-account-id').val(tx.account_id);
            if (tx.to_account_id) $('#tx-to-account-id').val(tx.to_account_id);
            if (tx.category_id) $('#tx-category-id').val(tx.category_id);
            $('#tx-description').val(tx.description || '');
        } else {
            $('#modal-tx-title span').text('Catat Transaksi');
            $('#tx-id').val('');
            $('#tx-date').val(new Date().toISOString().slice(0, 10));
            $('#tx-amount').val('');
            $('#tx-description').val('');
        }
    }

    // Modal Close
    $('.btn-close-modal').on('click', function () {
        $(this).closest('.modal-overlay').addClass('hidden');
    });

    // Quick Action Triggers
    $('#btn-cashflow-income').on('click', () => openTxModal(null, 'income'));
    $('#btn-cashflow-expense').on('click', () => openTxModal(null, 'expense'));
    $('#btn-cashflow-transfer').on('click', () => openTxModal(null, 'transfer'));

    $('.tx-type-btn').on('click', function () {
        setTxType($(this).data('type'));
    });

    // Submit Transaction
    $('#form-transaction').on('submit', function (e) {
        e.preventDefault();
        const id = $('#tx-id').val();
        const type = $('#tx-type').val();
        const accountId = $('#tx-account-id').val();
        const toAccountId = $('#tx-to-account-id').val();
        const categoryId = $('#tx-category-id').val();
        const amount = parseFloat($('#tx-amount').val());

        if (!amount || amount <= 0) {
            alert('Nominal harus lebih dari 0.');
            return;
        }

        if (type === 'transfer' && accountId == toAccountId) {
            alert('Akun asal dan akun tujuan transfer tidak boleh sama.');
            return;
        }

        const payload = {
            id: id || undefined,
            date: $('#tx-date').val(),
            type: type,
            account_id: accountId,
            to_account_id: type === 'transfer' ? toAccountId : null,
            category_id: type !== 'transfer' ? categoryId : null,
            amount: amount,
            description: $('#tx-description').val().trim()
        };

        const action = id ? 'update_transaction' : 'add_transaction';
        const $btn = $('#btn-save-tx').prop('disabled', true).text('Menyimpan...');

        $.post(`src/api/admin.php?action=${action}`, payload, res => {
            $btn.prop('disabled', false).text('Simpan Transaksi');
            if (res && res.ok) {
                $('#modal-transaction').addClass('hidden');
                lCashflow();
                lDash();
            } else {
                alert((res && res.error) || 'Gagal menyimpan transaksi.');
            }
        }, 'json').fail(() => {
            $btn.prop('disabled', false).text('Simpan Transaksi');
            alert('Terjadi kesalahan koneksi.');
        });
    });

    // Edit & Delete Transaction
    $(document).on('click', '.edit-tx-btn', function () {
        const tx = $(this).data('tx');
        openTxModal(tx);
    });

    $(document).on('click', '.del-tx-btn', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus transaksi ini? Saldo akun akan dikembalikan secara otomatis.')) return;

        $.post('src/api/admin.php?action=delete_transaction', { id }, res => {
            if (res && res.ok) {
                lCashflow();
                lDash();
            } else {
                alert((res && res.error) || 'Gagal menghapus transaksi.');
            }
        }, 'json');
    });

    // ══════════════════════════════════════════════════════════════════════════════
    //  CLAIM KAS MINGGUAN QUEUE
    // ══════════════════════════════════════════════════════════════════════════════
    $('#btn-open-queue-modal').on('click', () => {
        const pending = cfOverviewData.pending_queue || [];
        if (pending.length === 0) {
            alert('Tidak ada antrean kas mingguan.');
            return;
        }

        const item = pending[0];
        $('#claim-queue-id').val(item.id);
        const isNeg = parseFloat(item.nominal) < 0;
        $('#claim-queue-title').text(isNeg ? 'Koreksi Kas Mingguan (Pengurangan)' : `Penerimaan Kas Mingguan: ${escapeHtml(item.bulan)} ${item.tahun}`);
        $('#claim-queue-nominal').text(fmt(Math.abs(item.nominal)));
        $('#claim-queue-info').text(isNeg ? 'Pilih dompet yang akan dipotong untuk koreksi pembatalan kas.' : 'Pilih akun/dompet tempat fisik/digital uang ini disimpan.');
        $('#claim-description').val(item.keterangan || `Penerimaan Kas Mingguan ${item.bulan} ${item.tahun}`);

        // Accounts dropdown
        const accOpts = (cfOverviewData.accounts || []).map(a => `<option value="${a.id}">${escapeHtml(a.name)} (Saldo: ${fmt(a.balance)})</option>`).join('');
        $('#claim-account-id').html(accOpts);

        // Categories dropdown
        const targetType = isNeg ? 'expense' : 'income';
        const cats = (cfOverviewData.categories || []).filter(c => c.type === 'both' || c.type === targetType);
        const catOpts = cats.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
        $('#claim-category-id').html(catOpts);

        $('#modal-claim-queue').removeClass('hidden');
    });

    $('#form-claim-queue').on('submit', function (e) {
        e.preventDefault();
        const payload = {
            queue_id: $('#claim-queue-id').val(),
            account_id: $('#claim-account-id').val(),
            category_id: $('#claim-category-id').val(),
            description: $('#claim-description').val().trim()
        };

        const $btn = $('#btn-submit-claim').prop('disabled', true).text('Membukukan...');
        $.post('src/api/admin.php?action=claim_kas_queue', payload, res => {
            $btn.prop('disabled', false).text('Catat ke Akun');
            if (res && res.ok) {
                $('#modal-claim-queue').addClass('hidden');
                alert('Antrean kas berhasil dibukukan ke dalam dompet akun!');
                lCashflow();
                lDash();
            } else {
                alert((res && res.error) || 'Gagal membukukan antrean kas.');
            }
        }, 'json').fail(() => {
            $btn.prop('disabled', false).text('Catat ke Akun');
            alert('Terjadi kesalahan koneksi.');
        });
    });

    // ══════════════════════════════════════════════════════════════════════════════
    //  KELOLA MASTER AKUN & KATEGORI
    // ══════════════════════════════════════════════════════════════════════════════
    function lMaster() {
        $.getJSON('src/api/admin.php?action=get_finance_overview', res => {
            if (!res || !res.ok) return;
            cfOverviewData = res;

            // 1. Render Master Accounts List
            const accs = res.accounts || [];
            $('#master-account-count').text(`${accs.length} akun`);
            let accHtml = '';
            if (accs.length === 0) {
                accHtml = '<div class="text-xs text-[var(--ink-muted)] py-4 text-center">Belum ada akun dompet.</div>';
            } else {
                accHtml = accs.map(a => {
                    const icon = a.icon || 'fa-solid fa-wallet';
                    const activeBadge = parseInt(a.is_active) === 1
                        ? '<span class="badge-status badge-success text-[10px]">Aktif</span>'
                        : '<span class="badge-status badge-neutral text-[10px]">Non-aktif</span>';
                    const jsonAttr = escapeHtml(JSON.stringify(a));
                    return `
                        <div class="card-linear p-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg bg-[var(--surface-2)] flex items-center justify-center text-sm text-[var(--primary)]">
                                    <i class="${escapeHtml(icon)}"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-xs text-[var(--ink)]">${escapeHtml(a.name)}</span>
                                        ${activeBadge}
                                    </div>
                                    <div class="text-[11px] text-[var(--ink-muted)] capitalize">
                                        ${escapeHtml(a.type || 'cash')} • Saldo: <b class="font-mono text-[var(--ink)]">${fmt(a.balance)}</b>
                                    </div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button class="btn-secondary text-xs px-2 py-1 toggle-acc-btn" data-id="${a.id}" data-active="${a.is_active}" title="Ubah status aktif">
                                    <i class="fa-solid ${parseInt(a.is_active)===1 ? 'fa-eye-slash' : 'fa-eye'} text-[10px]"></i>
                                </button>
                                <button class="btn-secondary text-xs px-2 py-1 edit-acc-btn" data-acc='${jsonAttr}' title="Edit akun">
                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                </button>
                                <button class="btn-danger text-xs px-2 py-1 del-acc-btn" data-id="${a.id}" title="Hapus akun">
                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            $('#master-accounts-list').html(accHtml);

            // 2. Render Master Categories List
            const cats = res.categories || [];
            $('#master-category-count').text(`${cats.length} kategori`);
            let catHtml = '';
            if (cats.length === 0) {
                catHtml = '<div class="text-xs text-[var(--ink-muted)] py-4 text-center">Belum ada kategori.</div>';
            } else {
                catHtml = cats.map(c => {
                    const color = c.color || '#3b82f6';
                    const icon = c.icon || 'fa-solid fa-tag';
                    const typeLabel = c.type === 'income' ? 'Pemasukan' : (c.type === 'expense' ? 'Pengeluaran' : 'Semua (Masuk/Keluar)');
                    const jsonAttr = escapeHtml(JSON.stringify(c));
                    return `
                        <div class="card-linear p-3 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-sm" style="background:${color}20; color:${color}">
                                    <i class="${escapeHtml(icon)}"></i>
                                </div>
                                <div>
                                    <span class="font-semibold text-xs text-[var(--ink)]">${escapeHtml(c.name)}</span>
                                    <div class="text-[11px] text-[var(--ink-muted)]">${typeLabel}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button class="btn-secondary text-xs px-2 py-1 edit-cat-btn" data-cat='${jsonAttr}' title="Edit kategori">
                                    <i class="fa-solid fa-pen text-[10px]"></i>
                                </button>
                                <button class="btn-danger text-xs px-2 py-1 del-cat-btn" data-id="${c.id}" title="Hapus kategori">
                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            $('#master-categories-list').html(catHtml);
        });
    }

    // Master Account Modal Handlers
    $('#btn-add-account-master').on('click', () => {
        $('#form-account')[0].reset();
        $('#acc-id').val('');
        $('#modal-account-title').text('Tambah Dompet / Akun');
        $('#acc-initial-balance').val(0);
        $('#acc-sort').val(1);
        $('#acc-icon').val('fa-solid fa-wallet');
        $('#acc-icon-preview').html('<i class="fa-solid fa-wallet"></i>');
        $('#modal-account').removeClass('hidden');
    });

    $('#acc-icon').on('input', function () {
        $('#acc-icon-preview').html(`<i class="${escapeHtml($(this).val())}"></i>`);
    });

    $(document).on('click', '.edit-acc-btn', function () {
        const a = $(this).data('acc');
        $('#modal-account-title').text('Edit Dompet / Akun');
        $('#acc-id').val(a.id);
        $('#acc-name').val(a.name);
        $('#acc-type').val(a.type || 'cash');
        $('#acc-sort').val(a.sort_order || 1);
        $('#acc-initial-balance').val(a.initial_balance || 0);
        $('#acc-icon').val(a.icon || 'fa-solid fa-wallet');
        $('#acc-icon-preview').html(`<i class="${escapeHtml(a.icon || 'fa-solid fa-wallet')}"></i>`);
        $('#modal-account').removeClass('hidden');
    });

    $('#form-account').on('submit', function (e) {
        e.preventDefault();
        const id = $('#acc-id').val();
        const payload = {
            sub_action: id ? 'update' : 'add',
            id: id || undefined,
            name: $('#acc-name').val().trim(),
            type: $('#acc-type').val(),
            sort_order: parseInt($('#acc-sort').val()) || 1,
            initial_balance: parseFloat($('#acc-initial-balance').val()) || 0,
            icon: $('#acc-icon').val().trim()
        };

        $.post('src/api/admin.php?action=manage_account', payload, res => {
            if (res && res.ok) {
                $('#modal-account').addClass('hidden');
                lMaster();
            } else {
                alert((res && res.error) || 'Gagal menyimpan akun.');
            }
        }, 'json');
    });

    $(document).on('click', '.toggle-acc-btn', function () {
        const id = $(this).data('id');
        $.post('src/api/admin.php?action=manage_account', { sub_action: 'toggle_active', id }, res => {
            if (res && res.ok) lMaster();
            else alert((res && res.error) || 'Gagal mengubah status akun.');
        }, 'json');
    });

    $(document).on('click', '.del-acc-btn', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus akun ini? Pastikan tidak ada transaksi yang terhubung dengan akun ini.')) return;
        $.post('src/api/admin.php?action=manage_account', { sub_action: 'delete', id }, res => {
            if (res && res.ok) lMaster();
            else alert((res && res.error) || 'Gagal menghapus akun.');
        }, 'json');
    });

    // Master Category Modal Handlers
    $('#btn-add-category-master').on('click', () => {
        $('#form-category')[0].reset();
        $('#cat-id').val('');
        $('#modal-category-title').text('Tambah Kategori');
        $('#cat-type').val('both');
        $('#cat-color').val('#3b82f6');
        $('#cat-icon').val('fa-solid fa-tag');
        $('#cat-icon-preview').html('<i class="fa-solid fa-tag"></i>');
        $('#modal-category').removeClass('hidden');
    });

    $('#cat-icon').on('input', function () {
        $('#cat-icon-preview').html(`<i class="${escapeHtml($(this).val())}"></i>`);
    });

    $(document).on('click', '.edit-cat-btn', function () {
        const c = $(this).data('cat');
        $('#modal-category-title').text('Edit Kategori');
        $('#cat-id').val(c.id);
        $('#cat-name').val(c.name);
        $('#cat-type').val(c.type || 'both');
        $('#cat-color').val(c.color || '#3b82f6');
        $('#cat-icon').val(c.icon || 'fa-solid fa-tag');
        $('#cat-icon-preview').html(`<i class="${escapeHtml(c.icon || 'fa-solid fa-tag')}"></i>`);
        $('#modal-category').removeClass('hidden');
    });

    $('#form-category').on('submit', function (e) {
        e.preventDefault();
        const id = $('#cat-id').val();
        const payload = {
            sub_action: id ? 'update' : 'add',
            id: id || undefined,
            name: $('#cat-name').val().trim(),
            type: $('#cat-type').val(),
            color: $('#cat-color').val(),
            icon: $('#cat-icon').val().trim()
        };

        $.post('src/api/admin.php?action=manage_category', payload, res => {
            if (res && res.ok) {
                $('#modal-category').addClass('hidden');
                lMaster();
            } else {
                alert((res && res.error) || 'Gagal menyimpan kategori.');
            }
        }, 'json');
    });

    $(document).on('click', '.del-cat-btn', function () {
        const id = $(this).data('id');
        if (!confirm('Hapus kategori ini? Pastikan tidak ada transaksi yang terhubung dengan kategori ini.')) return;
        $.post('src/api/admin.php?action=manage_category', { sub_action: 'delete', id }, res => {
            if (res && res.ok) lMaster();
            else alert((res && res.error) || 'Gagal menghapus kategori.');
        }, 'json');
    });

    // ===== Ekspor Laporan =====
    let _exportRows = [];
    let _lastApiRes = null;
    const EXPORT_META = {
        kasminggu: { action: 'export_kasminggu', title: 'Kas Mingguan Siswa', fileBase: 'laporan_kas_mingguan', filterTpl: 'month' },
        cashflow:  { action: 'get_jurnal_all',   title: 'Buku Kas & Transaksi', fileBase: 'laporan_buku_kas',       filterTpl: 'cashflow' }
    };

    function buildExportFilter(type) {
        if (type === 'kasminggu') {
            const bulanOpts = bulanList.map(b => `<option value="${b}">${b}</option>`).join('');
            const tahunOpts = [now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option value="${y}" ${y===now.getFullYear()?'selected':''}>${y}</option>`).join('');
            return `
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <div>
                        <label class="eyebrow block mb-1">Bulan</label>
                        <select name="bulan" class="input-linear w-full">${bulanOpts}</select>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Tahun</label>
                        <select name="tahun" class="input-linear w-full">${tahunOpts}</select>
                    </div>
                </div>`;
        } else {
            const accOpts = '<option value="">Semua Dompet / Rekening</option>' +
                (cfOverviewData.accounts || []).map(a => `<option value="${a.id}">${escapeHtml(a.name)}</option>`).join('');
            const catOpts = '<option value="">Semua Kategori</option>' +
                (cfOverviewData.categories || []).map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            return `
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="eyebrow block mb-1">Dari Tanggal</label>
                        <input type="date" name="dari" class="input-linear w-full">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Sampai Tanggal</label>
                        <input type="date" name="sampai" class="input-linear w-full">
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Tipe</label>
                        <select name="type" class="input-linear w-full">
                            <option value="">Semua Tipe</option>
                            <option value="income">Pemasukan (+)</option>
                            <option value="expense">Pengeluaran (-)</option>
                            <option value="transfer">Transfer (⇄)</option>
                        </select>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Dompet / Rekening</label>
                        <select name="account_id" class="input-linear w-full">${accOpts}</select>
                    </div>
                    <div>
                        <label class="eyebrow block mb-1">Kategori</label>
                        <select name="category_id" class="input-linear w-full">${catOpts}</select>
                    </div>
                </div>`;
        }
    }

    function loadExportData(cb) {
        const type = $('#export-type').val();
        const meta = EXPORT_META[type] || EXPORT_META.kasminggu;
        const $f = $('#export-filters');
        const params = new URLSearchParams({ action: meta.action });
        $f.find('[name=dari]').each(function(){ if ($(this).val()) params.set('dari', $(this).val()); });
        $f.find('[name=sampai]').each(function(){ if ($(this).val()) params.set('sampai', $(this).val()); });
        $f.find('[name=type]').each(function(){ if ($(this).val()) params.set('type', $(this).val()); });
        $f.find('[name=account_id]').each(function(){ if ($(this).val()) params.set('account_id', $(this).val()); });
        $f.find('[name=category_id]').each(function(){ if ($(this).val()) params.set('category_id', $(this).val()); });
        $f.find('[name=bulan]').each(function(){ if ($(this).val()) params.set('bulan', $(this).val()); });
        $f.find('[name=tahun]').each(function(){ if ($(this).val()) params.set('tahun', $(this).val()); });
        const q = params.toString();
        $.getJSON('src/api/public.php' + (q ? '?' + q : ''), function(r) {
            _exportRows = (r && r.rows) || [];
            _lastApiRes = r || {};
            renderExportPreview(type, _lastApiRes);
            cb && cb();
        }).fail(function(){ 
            $('#ekspor-preview').html('<div class="text-center py-6 text-[var(--semantic-danger)]">Gagal memuat data laporan.</div>');
        });
    }

    function renderExportPreview(type, apiRes) {
        const $p = $('#ekspor-preview');
        const rows = _exportRows;
        if (!rows || !rows.length) { 
            $p.html('<div class="text-center py-6 text-[var(--ink-muted)]">Tidak ada data untuk filter yang dipilih.</div>'); 
            return; 
        }
        let h = '<table class="table-linear"><thead><tr>';
        let body = '';

        if (type === 'cashflow') {
            h += '<th class="w-12 text-center">#</th>'
               + '<th class="w-28">Tanggal</th>'
               + '<th class="w-28 text-center">Tipe</th>'
               + '<th>Dompet / Rekening</th>'
               + '<th>Kategori</th>'
               + '<th>Keterangan</th>'
               + '<th class="text-right w-36">Nominal</th>'
               + '</tr></thead><tbody>';

            body = rows.map((t, i) => {
                let tipeBadge = '';
                let akunTampil = escapeHtml(t.account_name || '-');
                let katTampil = escapeHtml(t.category_name || '-');
                let nomColor = 'text-[var(--ink)]';

                if (t.type === 'income') {
                    tipeBadge = '<span class="badge-status badge-success font-medium"><i class="fa-solid fa-arrow-trend-up text-[10px]"></i> Masuk</span>';
                    nomColor = 'text-emerald-500 font-semibold';
                } else if (t.type === 'expense') {
                    tipeBadge = '<span class="badge-status badge-danger font-medium"><i class="fa-solid fa-arrow-trend-down text-[10px]"></i> Keluar</span>';
                    nomColor = 'text-rose-500 font-semibold';
                } else if (t.type === 'transfer') {
                    tipeBadge = '<span class="badge-status badge-neutral font-medium"><i class="fa-solid fa-right-left text-[10px]"></i> Transfer</span>';
                    akunTampil = `${escapeHtml(t.account_name || '-')} <i class="fa-solid fa-arrow-right text-[10px] mx-1 text-[var(--ink-muted)]"></i> ${escapeHtml(t.to_account_name || '-')}`;
                    katTampil = '<span class="text-[var(--ink-muted)] italic">Transfer Antar Dompet</span>';
                    nomColor = 'text-cyan-500 font-semibold';
                }

                return `<tr>
                    <td class="font-mono text-xs text-[var(--ink-muted)] text-center">${i+1}</td>
                    <td class="font-mono text-xs text-[var(--ink-muted)]">${escapeHtml(t.tanggal)}</td>
                    <td class="text-center">${tipeBadge}</td>
                    <td class="font-medium text-[var(--ink)]">${akunTampil}</td>
                    <td class="text-xs text-[var(--ink-muted)]">${katTampil}</td>
                    <td class="text-[var(--ink)]">${escapeHtml(t.keterangan || '-')}</td>
                    <td class="text-right font-mono-num ${nomColor}">${fmt(t.nominal)}</td>
                </tr>`;
            }).join('');

            const totals = apiRes?.totals || {};
            body += `<tr class="font-bold border-t-2 border-[var(--hairline)]">
                <td colspan="6" class="text-right pr-3 text-xs uppercase tracking-wide">
                    Total Masuk: <span class="text-emerald-500 font-mono-num font-bold mr-3">${fmt(totals.masuk || 0)}</span>
                    Total Keluar: <span class="text-rose-500 font-mono-num font-bold mr-3">${fmt(totals.keluar || 0)}</span>
                    Net Arus Kas:
                </td>
                <td class="text-right font-mono-num ${(totals.net || 0) >= 0 ? 'text-emerald-500' : 'text-rose-500'} font-bold">
                    ${fmt(totals.net || 0)}
                </td>
            </tr>`;
        } else {
            const tarif = Number(apiRes?.tarif) || 0;
            h += '<th class="w-12 text-center">#</th><th class="w-12 text-center">Absen</th><th>Nama Siswa</th>'
                + '<th class="text-center w-14">M1</th><th class="text-center w-14">M2</th><th class="text-center w-14">M3</th><th class="text-center w-14">M4</th><th class="text-center w-14">M5</th>'
                + '<th class="text-right w-36">Total Bayar</th><th class="text-right w-36">Selisih</th></tr></thead><tbody>';
            const checkCell = v => `<td class="text-center text-xs">${v ? '<i class="fa-solid fa-circle-check text-green-500" title="Sudah bayar"></i>' : '<span class="text-[var(--ink-muted)]">-</span>'}</td>`;
            body = rows.map((r, i) => {
                const vals = [+r.m1, +r.m2, +r.m3, +r.m4, +r.m5];
                const totalTarif = vals.filter(Boolean).length * tarif;
                const paid = +r.total_bayar || 0;
                const selisih = paid - totalTarif;
                return `<tr>
                    <td class="font-mono text-xs text-[var(--ink-muted)] text-center">${i+1}</td>
                    <td class="font-mono text-xs text-[var(--ink-muted)] text-center">${escapeHtml(r.absen||'-')}</td>
                    <td class="text-[var(--ink)] font-medium">${escapeHtml(r.nama)}</td>
                    ${vals.map(checkCell).join('')}
                    <td class="text-right font-mono-num font-medium text-[var(--ink)]">${fmt(paid)}</td>
                    <td class="text-right font-mono-num ${selisih>=0?'text-green-500':'text-red-500'}">${fmt(Math.abs(selisih))}${selisih<0?' ↓':''}</td>
                </tr>`;
            }).join('');
            const sumAll = apiRes?.totals?.sum || rows.reduce((s, r) => s + (+r.total_bayar || 0), 0);
            const countSiswa = apiRes?.totals?.count || rows.length;
            body += `<tr class="font-bold"><td colspan="3" class="text-right pr-2">Total (${countSiswa} siswa):</td><td colspan="5"></td><td class="text-right font-mono-num">${fmt(sumAll)}</td><td></td></tr>`;
        }

        h += body + '</tbody></table>';
        $p.html(h);
    }

    // CSV download
    $('#btn-csv').on('click', function (e) {
        e.preventDefault();
        const rows = _exportRows;
        if (!rows || !rows.length) { alert('Tidak ada data untuk diekspor.'); return; }
        const type = $('#export-type').val();
        const meta = EXPORT_META[type] || EXPORT_META.kasminggu;
        const sep = '\t'; // tab-separated → Excel opens nicely
        const esc = s => `"${String(s||'').replace(/"/g,'""')}"`;
        let csv = '';
        let fileName = meta.fileBase;

        if (type === 'cashflow') {
            csv = ['No','Tanggal','Tipe','Dompet Sumber','Dompet Tujuan','Kategori','Keterangan','Nominal'].join(sep) + '\n'
                + rows.map((r, i) => [
                    i + 1,
                    r.tanggal,
                    r.type === 'income' ? 'Pemasukan' : (r.type === 'expense' ? 'Pengeluaran' : 'Transfer'),
                    esc(r.account_name || '-'),
                    esc(r.to_account_name || '-'),
                    esc(r.category_name || '-'),
                    esc(r.keterangan || '-'),
                    r.nominal || 0
                ].join(sep)).join('\n');
            const totals = _lastApiRes?.totals || {};
            csv += '\n' + ['', '', '', '', '', 'Total Masuk', totals.masuk || 0].join(sep);
            csv += '\n' + ['', '', '', '', '', 'Total Keluar', totals.keluar || 0].join(sep);
            csv += '\n' + ['', '', '', '', '', 'Mutasi Bersih', totals.net || 0].join(sep);
            fileName += '_' + (new Date().toISOString().slice(0, 10));
        } else {
            csv = ['No','Absen','Nama Siswa','Minggu 1','Minggu 2','Minggu 3','Minggu 4','Minggu 5','Total Bayar'].join(sep) + '\n'
                + rows.map((r,i) => [i+1, r.absen||'-', esc(r.nama), r.m1?'✓':'-', r.m2?'✓':'-', r.m3?'✓':'-', r.m4?'✓':'-', r.m5?'✓':'-', r.total_bayar||0].join(sep)).join('\n');
            const bulan = $('#export-filters [name=bulan]').val() || 'semua';
            const tahun = $('#export-filters [name=tahun]').val() || new Date().getFullYear();
            fileName += `_${bulan}_${tahun}`;
        }

        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${fileName}.csv`;
        a.click();
    });

    // PDF via jsPDF & autoTable
    $('#btn-pdf').on('click', function () {
        const rows = _exportRows;
        if (!rows || !rows.length) { alert('Tidak ada data untuk diekspor.'); return; }
        if (typeof window.jspdf === 'undefined') { alert('jsPDF belum dimuat. Pastikan koneksi internet aktif atau tunggu sebentar.'); return; }
        const { jsPDF } = window.jspdf;
        const type = $('#export-type').val();
        const meta = EXPORT_META[type] || EXPORT_META.kasminggu;
        const kelas = window.namaKelas || '';
        const dateStr = new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm', format: 'a4' });

        let tableHeaders = [];
        let tableBody = [];
        let tableFoot = [];
        let titleSub = '';
        let fileName = meta.fileBase;

        if (type === 'cashflow') {
            const dari = $('#export-filters [name=dari]').val();
            const sampai = $('#export-filters [name=sampai]').val();
            const periodeStr = (dari || sampai) ? `Periode: ${dari || 'Awal'} s.d. ${sampai || 'Sekarang'}  •  ` : '';
            titleSub = `${periodeStr}Kas Kelas ${kelas}  •  Dicetak: ${dateStr}`;
            fileName += '_' + new Date().toISOString().slice(0, 10);

            tableHeaders = [['#', 'Tanggal', 'Tipe', 'Dompet / Rekening', 'Kategori', 'Keterangan', 'Nominal']];
            tableBody = rows.map((r, i) => {
                let dompet = r.account_name || '-';
                let kat = r.category_name || '-';
                let tipeTampil = r.type === 'income' ? 'Masuk' : (r.type === 'expense' ? 'Keluar' : 'Transfer');
                if (r.type === 'transfer') {
                    dompet = `${r.account_name || '-'} -> ${r.to_account_name || '-'}`;
                    kat = 'Transfer Antar Dompet';
                }
                return [
                    String(i + 1),
                    r.tanggal || '',
                    tipeTampil,
                    dompet,
                    kat,
                    r.keterangan || '-',
                    fmt(r.nominal)
                ];
            });

            const totals = _lastApiRes?.totals || {};
            tableFoot = [[
                { content: `Total Masuk: ${fmt(totals.masuk || 0)}   |   Total Keluar: ${fmt(totals.keluar || 0)}   |   Mutasi Bersih: ${fmt(totals.net || 0)}`, colSpan: 7, styles: { fontStyle: 'bold', halign: 'right' } }
            ]];
        } else {
            const bulan = $('#export-filters [name=bulan]').val() || '';
            const tahun = $('#export-filters [name=tahun]').val() || '';
            titleSub = `Kas Mingguan ${bulan} ${tahun}  •  Kas Kelas ${kelas}  •  Dicetak: ${dateStr}`;
            fileName += `_${bulan}_${tahun}`;

            const tarif = Number(_lastApiRes?.tarif) || 0;
            tableHeaders = [['Absen', 'Nama Siswa', 'M1', 'M2', 'M3', 'M4', 'M5', 'Total Bayar']];
            tableBody = rows.map(r => {
                const vals = [+r.m1, +r.m2, +r.m3, +r.m4, +r.m5];
                const cell = v => v ? fmt(tarif) : '-';
                return [
                    r.absen || '-',
                    r.nama || '',
                    cell(vals[0]),
                    cell(vals[1]),
                    cell(vals[2]),
                    cell(vals[3]),
                    cell(vals[4]),
                    fmt(+r.total_bayar || 0)
                ];
            });
            const sumAll = _lastApiRes?.totals?.sum || rows.reduce((s, r) => s + (+r.total_bayar || 0), 0);
            tableFoot = [[
                { content: `Total Kas Mingguan (${rows.length} Siswa): ${fmt(sumAll)}`, colSpan: 8, styles: { fontStyle: 'bold', halign: 'right' } }
            ]];
        }

        // Title and header info
        doc.setFontSize(14);
        doc.setTextColor(30, 30, 60);
        doc.text(`Laporan ${meta.title}`, 14, 15);
        doc.setFontSize(9);
        doc.setTextColor(100, 100, 100);
        doc.text(titleSub, 14, 21);

        if (typeof doc.autoTable === 'function') {
            doc.autoTable({
                head: tableHeaders,
                body: tableBody,
                foot: tableFoot,
                startY: 26,
                theme: 'grid',
                headStyles: { fillColor: [40, 44, 52], textColor: [255, 255, 255], fontStyle: 'bold' },
                footStyles: { fillColor: [240, 243, 246], textColor: [30, 30, 60] },
                styles: { fontSize: 8, cellPadding: 2.5 },
            });
        } else {
            let y = 30;
            tableBody.forEach(row => {
                doc.text(row.join('  |  '), 14, y);
                y += 6;
                if (y > 190) { doc.addPage(); y = 15; }
            });
        }

        doc.save(`${fileName}.pdf`);
    });

    // Init export filter events
    $('#export-type').on('change', function() {
        $('#export-filters').html(buildExportFilter(this.value));
        if (this.value === 'kasminggu') {
            $('#export-filters [name=bulan]').val(bulanList[now.getMonth()]);
            $('#export-filters [name=tahun]').val(now.getFullYear());
        } else if (this.value === 'cashflow') {
            const y = now.getFullYear();
            const m = String(now.getMonth()+1).padStart(2,'0');
            $('#export-filters [name=dari]').val(`${y}-${m}-01`);
            $('#export-filters [name=sampai]').val(`${y}-${m}-${new Date(y, now.getMonth()+1,0).getDate()}`);
        }
        loadExportData();
    });
    $('#export-filters').on('change', 'select, input[type=date]', function() {
        loadExportData();
    });
    $('#btn-load-export').on('click', function(e){ e.preventDefault(); loadExportData(); });


    // ── Alokasi Dana (admin) ────────────────────────────────────────────
    let alokasiPage = 1, transferPage = 1;
    let alokasiAccounts = [];
    let alokasiSaldos = {};
    let alokasiDonut = null;
    const alokasiColors = ['#60a5fa', '#a78bfa', '#34d399', '#fbbf24', '#f87171'];

    function alokasiRenderLine(a) {
        const opt = alokasiAccounts.map(x => `<option value="${x.id}" ${x.id == a.account_id ? 'selected' : ''}>${escapeHtml(x.name)}</option>`).join('');
        return `<div class="alokasi-line flex items-center gap-2">
            <select class="input-linear flex-1">${opt}</select>
            <input type="number" min="1" step="any" class="input-linear w-36" value="${a.nominal || ''}" placeholder="Nominal">
            <button type="button" class="btn-danger text-xs px-2.5 py-1 alokasi-del-line"><i class="fa-solid fa-xmark text-[10px]"></i></button>
        </div>`;
    }

    function alokasiUpdateRemaining() {
        const total = parseFloat($('#alokasi-total').val()) || 0;
        let sum = 0;
        $('#alokasi-lines .alokasi-line input[type="number"]').each(function () { sum += parseFloat($(this).val()) || 0; });
        $('#alokasi-remaining').text(fmt(total - sum));
        $('#alokasi-remaining').parent().removeClass('text-amber-400 text-rose-400 text-emerald-400');
        const diff = total - sum;
        if (Math.abs(diff) < 0.01) $('#alokasi-remaining').parent().addClass('text-emerald-400');
        else if (diff > 0) $('#alokasi-remaining').parent().addClass('text-amber-400');
        else $('#alokasi-remaining').parent().addClass('text-rose-400');
    }

    function alokasiCollectLines() {
        const lines = [];
        $('#alokasi-lines .alokasi-line').each(function () {
            lines.push({
                account_id: parseInt($(this).find('select').val(), 10),
                nominal: parseFloat($(this).find('input[type="number"]').val()) || 0,
            });
        });
        return lines;
    }

    // Modal open (add mode)
    function alokasiOpenModal(alloc) {
        $('#alokasi-edit-id').val(alloc ? alloc.id : '');
        $('#alokasi-tanggal').val(new Date().toISOString().slice(0, 10));
        $('#alokasi-ref_type').val(alloc ? alloc.ref_type : 'bms_setor');
        $('#alokasi-keterangan').val(alloc ? alloc.keterangan : '');
        $('#alokasi-total').val(alloc ? alloc.total_nominal : '');
        $('#alokasi-lines').empty();
        const lines = alloc ? alloc.lines : [{}];
        lines.forEach(l => $('#alokasi-lines').append(alokasiRenderLine(l || {})));
        $('#alokasi-submit-btn').html(alloc
            ? '<i class="fa-solid fa-floppy-disk text-xs"></i><span>Update</span>'
            : '<i class="fa-solid fa-floppy-disk text-xs"></i><span>Simpan Alokasi</span>');
        alokasiUpdateRemaining();
        $('#modal-alokasi').removeClass('hidden');
    }

    $('#alokasi-add-btn').on('click', () => alokasiOpenModal(null));
    $('#alokasi-modal-close, #alokasi-cancel-btn').on('click', () => $('#modal-alokasi').addClass('hidden'));

    $(document).on('click', '.alokasi-add-line-retry, #alokasi-add-line', () => {
        $('#alokasi-lines').append(alokasiRenderLine({}));
        alokasiUpdateRemaining();
    });
    $(document).on('click', '.alokasi-del-line', function () {
        $(this).closest('.alokasi-line').remove();
        alokasiUpdateRemaining();
    });
    $('#alokasi-total, #alokasi-lines').on('input', alokasiUpdateRemaining);
    // Wait for dynamic lines: delegate
    $(document).on('input', '#alokasi-lines input', alokasiUpdateRemaining);

    $('#form-alokasi').on('submit', function (e) {
        e.preventDefault();
        const id = $('#alokasi-edit-id').val();
        const payload = {
            tanggal:        $('#alokasi-tanggal').val(),
            ref_type:       $('#alokasi-ref_type').val(),
            keterangan:     $('#alokasi-keterangan').val(),
            total_nominal:  $('#alokasi-total').val(),
            lines: JSON.stringify(alokasiCollectLines()),
        };
        if (id) payload.id = id;
        $.post('src/api/admin.php?action=' + (id ? 'update_allocation' : 'add_allocation'), payload, function (res) {
            if (res && res.ok) {
                $('#modal-alokasi').addClass('hidden');
                loadAlokasiAdmin();
            } else {
                alert('Gagal menyimpan: ' + (res && res.error ? res.error : 'unknown'));
            }
        }, 'json').fail(function (xhr) {
            alert('Gagal menyimpan (HTTP ' + xhr.status + ').');
        });
    });

    // Transfer modal
    function transferOpenModal() {
        $('#transfer-tanggal').val(new Date().toISOString().slice(0, 10));
        $('#transfer-keterangan').val('');
        $('#transfer-nominal').val('');
        const toOpts = alokasiAccounts.map(a => `<option value="${a.id}">${escapeHtml(a.name)}</option>`).join('');
        $('#transfer-from').html(toOpts);
        $('#transfer-to').html(toOpts);
        if (alokasiAccounts.length > 1) $('#transfer-to').val(alokasiAccounts[1].id);
        $('#modal-transfer').removeClass('hidden');
    }
    $('#alokasi-transfer-btn').on('click', transferOpenModal);
    $('#transfer-modal-close, #transfer-cancel-btn').on('click', () => $('#modal-transfer').addClass('hidden'));

    $('#form-transfer').on('submit', function (e) {
        e.preventDefault();
        const fromId = parseInt($('#transfer-from').val(), 10);
        const toId   = parseInt($('#transfer-to').val(), 10);
        if (fromId === toId) { alert('Akun asal dan tujuan harus berbeda.'); return; }
        const payload = {
            tanggal:    $('#transfer-tanggal').val(),
            from_id:    fromId,
            to_id:      toId,
            nominal:    $('#transfer-nominal').val(),
            keterangan: $('#transfer-keterangan').val(),
        };
        $.post('src/api/admin.php?action=add_transfer', payload, function (res) {
            if (res && res.ok) {
                $('#modal-transfer').addClass('hidden');
                transferPage = 1;
                loadAlokasiAdmin();
            } else {
                alert('Gagal menyimpan transfer: ' + (res && res.error ? res.error : 'unknown'));
            }
        }, 'json').fail(function (xhr) {
            alert('Gagal menyimpan (HTTP ' + xhr.status + ').');
        });
    });

    function loadAlokasiAdmin() {
        loadAlokasiHistory();
        loadTransferList();

        $.getJSON('src/api/admin.php?action=list_accounts', function (accs) {
            alokasiAccounts = accs;
            // Fetch breakdown (saldos + recent transfers) and history together
            $.getJSON('src/api/public.php?action=get_storage_breakdown', function (s) {
                alokasiSaldos = {};
                (s.accounts || []).forEach(a => { alokasiSaldos[a.id] = a.saldo; });
                renderAlokasiKpiCards(s.accounts || [], s.donut, false);
                lDash();
            });
        }).fail(function () {
            $('#alokasi-accounts').html('<div class="text-subtle text-sm">Gagal memuat data alokasi.</div>');
        });
    }

    // Render KPI cards & donut dari data accounts (format sama: [{name, saldo, type, parent_type, icon}])
    function renderAlokasiKpiCards(accounts, donut, isFiltered) {
        const PT_COLORS_ADMIN = { cash: 'text-[var(--primary)]', ewallet: 'text-violet-400', bank: 'text-emerald-400', other: 'text-amber-400' };
        $('#alokasi-accounts').html(accounts.map(a => {
            const colorClass = PT_COLORS_ADMIN[a.parent_type] || PT_COLORS_ADMIN[a.type] || 'text-[var(--primary)]';
            const icon = a.icon || 'fa-solid fa-vault';
            const saldo = isFiltered ? (a.saldo || 0) : (alokasiSaldos[a.id] || a.saldo || 0);
            const badge = isFiltered ? '<span class="ml-1 text-[9px] font-semibold tracking-wide uppercase text-amber-400">filter</span>' : '';
            return `<div class="card-linear">
                <div class="flex items-center justify-between mb-2">
                    <span class="eyebrow">${escapeHtml(a.name)}${badge}</span>
                    <span class="text-[var(--ink-muted)]"><i class="${icon} text-sm"></i></span>
                </div>
                <div class="text-2xl font-bold font-mono-num ${colorClass}">${fmt(saldo)}</div>
            </div>`;
        }).join(''));
    }

    function loadAlokasiHistory(page) {
        if (page !== undefined) alokasiPage = page;
        const params = new URLSearchParams({ action: 'get_allocations', page: alokasiPage, limit: 15 });
        const dari        = $('#alokasi-dari').val();
        const sampai      = $('#alokasi-sampai').val();
        const keterangan  = $('#alokasi-keterangan-search').val().trim();
        if (dari)        params.set('dari', dari);
        if (sampai)      params.set('sampai', sampai);
        if (keterangan)  params.set('keterangan', keterangan);

        // Jika ada filter aktif, perbarui KPI cards dengan data filtered
        const hasFilter = dari || sampai || keterangan;
        if (hasFilter) {
            const kpiParams = new URLSearchParams({ action: 'get_alokasi_filtered_kpi' });
            if (dari)        kpiParams.set('dari', dari);
            if (sampai)      kpiParams.set('sampai', sampai);
            if (keterangan)  kpiParams.set('keterangan', keterangan);
            $.getJSON('src/api/public.php?' + kpiParams.toString(), function (kpi) {
                renderAlokasiKpiCards(kpi.accounts || [], kpi.donut, true);
            });
        }

        $('#alokasi-allocations-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Memuat…</div>');
        $('#alokasi-allocations-pagination').empty();
        $.getJSON('src/api/public.php?' + params.toString(), function (res) {
            const rows = res.data || [];
            const refLabel = { bms_setor: 'Setor BMS', bms_tarik: 'Tarik BMS', kas_mingguan: 'Kas Mingguan', manual: 'Manual' };
            let h = '<table class="table-linear"><thead><tr><th class="w-16">#</th><th class="w-32">Tanggal</th><th>Sumber</th><th>Keterangan</th><th>Pembagian</th><th class="text-right w-36">Total</th><th class="w-28 text-right">Aksi</th></tr></thead><tbody>';
            if (!rows.length) {
                h += '<tr><td colspan="7" class="text-center py-6 text-[var(--ink-muted)]">Belum ada alokasi.</td></tr>';
            } else {
                rows.forEach((r, i) => {
                    const lines = (r.lines || []).map(l => `${escapeHtml(l.account)} (${fmt(l.nominal)})`).join(', ');
                    h += '<tr>' +
                        `<td class="font-mono text-xs text-[var(--ink-muted)]">${(alokasiPage - 1) * 15 + i + 1}</td>` +
                        `<td class="font-mono text-xs text-[var(--ink-muted)]">${escapeHtml(r.tanggal)}</td>` +
                        `<td><span class="badge-neutral">${escapeHtml(refLabel[r.ref_type] || r.ref_type)}</span></td>` +
                        `<td class="text-[var(--ink)]">${escapeHtml(r.keterangan || '-')}</td>` +
                        `<td class="text-xs text-[var(--ink-muted)]">${lines || '-'}</td>` +
                        `<td class="text-right font-mono-num font-medium text-[var(--ink)]">${fmt(r.total_nominal)}</td>` +
                        `<td class="text-right space-x-1">
                            <button class="btn-secondary text-xs px-2.5 py-1 edit-alokasi gap-1" data-id="${r.id}"><i class="fa-solid fa-pen text-[10px]"></i><span>Edit</span></button>
                            <button class="btn-danger text-xs px-2.5 py-1 del-alokasi gap-1" data-id="${r.id}"><i class="fa-solid fa-trash-can text-[10px]"></i><span>Hapus</span></button>
                        </td>` +
                    '</tr>';
                });
            }
            h += '</tbody></table>';
            $('#alokasi-allocations-wrap').html(h);
            renderPagination('alokasi-allocations-pagination', res.pagination, (p) => loadAlokasiHistory(p));
        }).fail(function () {
            $('#alokasi-allocations-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Gagal memuat data.</div>');
        });
    }

    // Edit allocation: server returns full allocation w/ lines
    $(document).on('click', '.edit-alokasi', function () {
        const id = $(this).data('id');
        $.getJSON('src/api/public.php?action=get_allocations&limit=1000', function (res) {
            const alloc = (res.data || []).find(x => x.id == id);
            if (alloc) alokasiOpenModal(alloc);
            else alert('Alokasi tidak ditemukan.');
        });
    });

    $(document).on('click', '.del-alokasi', function () {
        if (!confirm('Hapus alokasi ini? Saldo akan dikembalikan.')) return;
        $.post('src/api/admin.php?action=delete_allocation', { id: $(this).data('id') }, function () {
            loadAlokasiAdmin();
        }, 'json').fail(function () { alert('Gagal menghapus.'); });
    });

    function loadTransferList(page) {
        if (page !== undefined) transferPage = page;
        const params = new URLSearchParams({ action: 'get_transfers', page: transferPage, limit: 15 });
        const dari   = $('#alokasi-dari').val();
        const sampai = $('#alokasi-sampai').val();
        if (dari)   params.set('dari', dari);
        if (sampai) params.set('sampai', sampai);

        $('#alokasi-transfers-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Memuat…</div>');
        $('#alokasi-transfers-pagination').empty();

        $.getJSON('src/api/public.php?' + params.toString(), function (res) {
            const rows = res.data || [];
            let h = '<table class="table-linear"><thead><tr><th class="w-16">#</th><th class="w-32">Tanggal</th><th>Dari</th><th>Ke</th><th>Keterangan</th><th class="text-right w-36">Nominal</th><th class="w-28 text-right">Aksi</th></tr></thead><tbody>';
            if (!rows.length) {
                h += '<tr><td colspan="7" class="text-center py-6 text-[var(--ink-muted)]">Belum ada transfer.</td></tr>';
            } else {
                rows.forEach((r, i) => {
                    h += '<tr>' +
                        `<td class="font-mono text-xs text-[var(--ink-muted)]">${(transferPage - 1) * 15 + i + 1}</td>` +
                        `<td class="font-mono text-xs text-[var(--ink-muted)]">${escapeHtml(r.tanggal)}</td>` +
                        `<td class="text-[var(--ink)]">${escapeHtml(r.from_name)}</td>` +
                        `<td class="text-[var(--ink)]">${escapeHtml(r.to_name)}</td>` +
                        `<td class="text-xs text-[var(--ink-muted)]">${escapeHtml(r.keterangan || '-')}</td>` +
                        `<td class="text-right font-mono-num font-medium text-[var(--ink)]">${fmt(r.nominal)}</td>` +
                        `<td class="text-right"><button class="btn-danger text-xs px-2.5 py-1 del-transfer gap-1" data-pair="${r.transfer_pair_id}"><i class="fa-solid fa-trash-can text-[10px]"></i><span>Hapus</span></button></td>` +
                    '</tr>';
                });
            }
            h += '</tbody></table>';
            $('#alokasi-transfers-wrap').html(h);
            renderPagination('alokasi-transfers-pagination', res.pagination, (p) => loadTransferList(p));
        }).fail(function () {
            $('#alokasi-transfers-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Gagal memuat data.</div>');
        });
    }

    $(document).on('click', '.del-transfer', function () {
        if (!confirm('Hapus transfer ini? Saldo akan dikembalikan.')) return;
        $.post('src/api/admin.php?action=delete_transfer', { transfer_pair_id: $(this).data('pair') }, function () {
            transferPage = 1;
            loadAlokasiAdmin();
        }, 'json').fail(function () { alert('Gagal menghapus.'); });
    });

    $('#alokasi-apply').on('click', () => {
        alokasiPage = 1;
        transferPage = 1;
        loadAlokasiHistory();
        loadTransferList();
    });

    $('#alokasi-reset').on('click', function () {
        $('#alokasi-dari').val('');
        $('#alokasi-sampai').val('');
        $('#alokasi-keterangan-search').val('');
        alokasiPage = 1;
        transferPage = 1;
        loadAlokasiAdmin();
    });
    $('#alokasi-keterangan-search').on('keyup', function (e) {
        if (e.key === 'Enter') { alokasiPage = 1; loadAlokasiHistory(); }
    });

    // ── Riwayat helpers & loader (admin) ────────────────
    function truncate(s, n) {
        s = String(s ?? '');
        return s.length > n ? s.slice(0, n) + '\u2026' : s;
    }
    function formatDateTime(s) {
        if (!s) return '-';
        const d = new Date(s.replace(' ', 'T'));
        if (isNaN(d.getTime())) return s;
        const pad = n => String(n).padStart(2, '0');
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }
    function loadRiwayatAdmin(page) {
        if (page !== undefined) adminRiwayatPage = page;
        const params = new URLSearchParams({ action: 'get_riwayat', page: adminRiwayatPage, limit: 15 });
        const modul  = $('#riwayat-modul').val();
        const aksi   = $('#riwayat-aksi').val();
        const dari   = $('#riwayat-dari').val();
        const sampai = $('#riwayat-sampai').val();
        if (modul)  params.set('modul', modul);
        if (aksi)   params.set('aksi', aksi);
        if (dari)   params.set('dari', dari);
        if (sampai) params.set('sampai', sampai);
        $('#riwayat-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Memuat…</div>');
        $('#riwayat-pagination').empty();
        $.getJSON('src/api/public.php?' + params.toString(), function(res) {
            const rows = res.data || [];
            if (!rows.length) {
                $('#riwayat-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Belum ada riwayat.</div>');
                return;
            }
            let html = '<table class="table-linear w-full"><thead><tr><th>Waktu</th><th>Modul</th><th>Aksi</th><th>Ringkasan</th><th>Oleh</th></tr></thead><tbody>';
            rows.forEach(r => {
                let cellRingkasan = escapeHtml(r.ringkasan);
                if (r.detail) {
                    try {
                        const d = (typeof r.detail === 'string') ? JSON.parse(r.detail) : r.detail;
                        if (d && typeof d === 'object') {
                            if (Array.isArray(d.perubahan) && d.perubahan.length > 0) {
                                const list = d.perubahan.map(p => {
                                    const stBadge = p.status === 'lunas' 
                                        ? '<span class="text-emerald-400 font-semibold">Lunas</span>' 
                                        : '<span class="text-amber-400 font-semibold">Belum Lunas</span>';
                                    return `• <b>${escapeHtml(p.nama)}</b> — Minggu ${escapeHtml(p.minggu)} (${stBadge})`;
                                }).join('<br>');
                                cellRingkasan += `<details class="mt-1 text-xs text-[var(--ink-muted)] cursor-pointer"><summary class="text-xs text-[var(--primary)] font-medium underline">Lihat Rincian (${d.perubahan.length} item)</summary><div class="mt-1 p-2 bg-[var(--surface-2)] rounded border border-[var(--hairline)] text-[var(--ink)] leading-relaxed">${list}</div></details>`;
                            } else {
                                const keys = Object.keys(d).filter(k => d[k] !== null && d[k] !== '');
                                if (keys.length > 0) {
                                    const labels = {
                                        nama: 'Nama', absen: 'No. Absen', tanggal: 'Tanggal',
                                        keterangan: 'Keterangan', jenis: 'Jenis', nominal: 'Nominal',
                                        amount: 'Nominal', jumlah: 'Jumlah', status: 'Status', id: 'ID Entitas',
                                        akun: 'Dompet / Akun', kategori: 'Kategori', ke_akun: 'Tujuan Transfer',
                                        account_id: 'ID Akun', to_account_id: 'ID Akun Tujuan', category_id: 'ID Kategori'
                                    };
                                    const list = keys.map(k => {
                                        let val = d[k];
                                        if ((k === 'nominal' || k === 'jumlah' || k === 'amount') && (typeof val === 'number' || !isNaN(parseFloat(val)))) {
                                            val = 'Rp ' + Math.round(parseFloat(val)).toLocaleString('id-ID');
                                        }
                                        const label = labels[k] || k;
                                        return `• <b>${escapeHtml(label)}:</b> ${escapeHtml(val)}`;
                                    }).join('<br>');
                                    cellRingkasan += `<details class="mt-1 text-xs text-[var(--ink-muted)] cursor-pointer"><summary class="text-xs text-[var(--primary)] font-medium underline">Lihat Rincian</summary><div class="mt-1 p-2 bg-[var(--surface-2)] rounded border border-[var(--hairline)] text-[var(--ink)] leading-relaxed">${list}</div></details>`;
                                }
                            }
                        }
                    } catch(e) {}
                }

                let modulBadge = '<span class="badge-neutral">' + escapeHtml(r.modul) + '</span>';
                if (r.modul === 'cashflow') {
                    modulBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-500/15 text-indigo-400 border border-indigo-500/30"><i class="fa-solid fa-money-bill-transfer text-[10px] mr-1"></i>cashflow</span>';
                } else if (r.modul === 'kas_mingguan') {
                    modulBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30"><i class="fa-solid fa-coins text-[10px] mr-1"></i>kas mingguan</span>';
                } else if (r.modul === 'account') {
                    modulBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-cyan-500/15 text-cyan-400 border border-cyan-500/30"><i class="fa-solid fa-wallet text-[10px] mr-1"></i>akun</span>';
                } else if (r.modul === 'category') {
                    modulBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-500/15 text-amber-400 border border-amber-500/30"><i class="fa-solid fa-tag text-[10px] mr-1"></i>kategori</span>';
                }

                let aksiBadge = '<span class="badge-' + escapeHtml(r.aksi) + '">' + escapeHtml(r.aksi) + '</span>';
                if (r.aksi === 'claim_kas') {
                    aksiBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-teal-500/15 text-teal-400 border border-teal-500/30">klaim kas</span>';
                }

                html += '<tr>'
                    + '<td class="text-xs text-[var(--ink-muted)] whitespace-nowrap">' + formatDateTime(r.created_at) + '</td>'
                    + '<td>' + modulBadge + '</td>'
                    + '<td>' + aksiBadge + '</td>'
                    + '<td>' + cellRingkasan + '</td>'
                    + '<td class="text-sm">' + escapeHtml(r.admin_nama || r.admin_username || '-') + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            $('#riwayat-wrap').html(html);
            renderPagination('riwayat-pagination', res.pagination, (p) => loadRiwayatAdmin(p));
        }).fail(function() {
            $('#riwayat-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Gagal memuat data.</div>');
        });
    }
    $('#riwayat-apply').on('click', () => { adminRiwayatPage = 1; loadRiwayatAdmin(); });
    $('#riwayat-reset').on('click', function() {
        $('#riwayat-modul').val('');
        $('#riwayat-aksi').val('');
        $('#riwayat-dari').val('');
        $('#riwayat-sampai').val('');
        adminRiwayatPage = 1;
        loadRiwayatAdmin();
    });
    $('#riwayat-prune-btn').on('click', function() {
        const sebelum = prompt('Hapus log sebelum tanggal (YYYY-MM-DD):');
        if (!sebelum) return;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(sebelum)) { alert('Format tanggal tidak valid. Gunakan YYYY-MM-DD.'); return; }
        if (!confirm('Hapus permanen semua log sebelum ' + sebelum + '? Tindakan ini tidak dapat dibatalkan.')) return;
        $.post('src/api/admin.php?action=prune_riwayat', {sebelum}, function(res) {
            if (res && res.ok) { alert('Dihapus: ' + res.deleted + ' entri'); loadRiwayatAdmin(); }
            else alert('Gagal: ' + (res && res.error ? res.error : 'unknown'));
        }, 'json').fail(function(xhr) { alert('Gagal: HTTP ' + xhr.status); });
    });

    // ── Kelola Tempat Penyimpanan (Storage Accounts) ─────────────────────
    const SA_PRESETS = [
        { name: 'Cash',          parent_type: 'cash',    icon: 'fa-solid fa-wallet',                type: 'cash' },
        { name: 'DANA',          parent_type: 'ewallet', icon: 'fa-solid fa-mobile-screen',         type: 'ewallet_dana' },
        { name: 'Gopay',         parent_type: 'ewallet', icon: 'fa-solid fa-mobile-screen-button',  type: 'ewallet_gopay' },
        { name: 'OVO',           parent_type: 'ewallet', icon: 'fa-solid fa-circle-dollar-to-slot', type: 'ewallet_ovo' },
        { name: 'ShopeePay',     parent_type: 'ewallet', icon: 'fa-solid fa-bag-shopping',          type: 'ewallet_shopee' },
        { name: 'LinkAja',       parent_type: 'ewallet', icon: 'fa-solid fa-link',                  type: 'ewallet_linkaja' },
        { name: 'SeaBank',       parent_type: 'bank',    icon: 'fa-solid fa-building-columns',      type: 'bank_seabank' },
        { name: 'Bank Mandiri',  parent_type: 'bank',    icon: 'fa-solid fa-building-columns',      type: 'bank_mandiri' },
        { name: 'BCA',           parent_type: 'bank',    icon: 'fa-solid fa-landmark',              type: 'bank_bca' },
        { name: 'BRI',           parent_type: 'bank',    icon: 'fa-solid fa-landmark-dome',         type: 'bank_bri' },
        { name: 'BNI',           parent_type: 'bank',    icon: 'fa-solid fa-landmark',              type: 'bank_bni' },
        { name: 'Jenius',        parent_type: 'bank',    icon: 'fa-solid fa-j',                     type: 'bank_jenius' },
    ];

    const SA_PARENT_LABELS = { cash: 'Tunai', ewallet: 'E-Wallet', bank: 'Bank', other: 'Lainnya' };
    const SA_PARENT_COLORS = { cash: 'text-[var(--primary)]', ewallet: 'text-violet-400', bank: 'text-emerald-400', other: 'text-amber-400' };

    function saResetForm() {
        $('#sa-edit-id').val('');
        $('#sa-name').val('');
        $('#sa-parent-type').val('cash');
        $('#sa-icon').val('');
        $('#sa-sort').val('99');
        $('#sa-icon-preview').html('<i class="fa-solid fa-vault"></i>');
        $('#sa-submit-label').text('Tambah Akun');
        $('#sa-submit-btn i').removeClass('fa-floppy-disk').addClass('fa-plus');
        $('#sa-cancel-edit').addClass('hidden');
    }

    function saLoadList() {
        $.getJSON('src/api/admin.php?action=list_storage_accounts_all', function (accs) {
            if (!accs.length) {
                $('#storage-accounts-list').html('<div class="text-subtle text-sm py-4 text-center">Belum ada tempat penyimpanan.</div>');
                return;
            }
            const html = accs.map(a => {
                const icon   = a.icon || 'fa-solid fa-vault';
                const pLabel = SA_PARENT_LABELS[a.parent_type] || a.parent_type;
                const pColor = SA_PARENT_COLORS[a.parent_type] || 'text-[var(--ink-muted)]';
                const activeClass   = a.is_active ? '' : 'opacity-50';
                const toggleLabel   = a.is_active ? 'Nonaktifkan' : 'Aktifkan';
                const toggleIcon    = a.is_active ? 'fa-toggle-on' : 'fa-toggle-off';
                const toggleColor   = a.is_active ? 'text-emerald-400' : 'text-[var(--ink-muted)]';
                const canDelete     = a.tx_count === 0;
                return `<div class="flex items-center gap-3 px-3 py-2 rounded-lg border border-[var(--hairline)] bg-[var(--surface-2)] ${activeClass}" data-sa-id="${a.id}">
                    <span class="text-lg ${pColor} w-5 text-center"><i class="${escapeHtml(icon)}"></i></span>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-[var(--ink)] truncate">${escapeHtml(a.name)}</div>
                        <div class="text-[11px] text-[var(--ink-muted)]">${escapeHtml(pLabel)} &middot; ${a.tx_count} transaksi &middot; Saldo: ${fmt(a.saldo)}</div>
                    </div>
                    <div class="flex gap-1.5 flex-shrink-0">
                        <button class="btn-secondary text-xs px-2 py-1 sa-edit-btn" data-id="${a.id}" title="Edit">
                            <i class="fa-solid fa-pen text-[10px]"></i>
                        </button>
                        <button class="btn-secondary text-xs px-2 py-1 sa-toggle-btn ${toggleColor}" data-id="${a.id}" title="${toggleLabel}">
                            <i class="fa-solid ${toggleIcon} text-sm"></i>
                        </button>
                        ${canDelete
                            ? `<button class="btn-danger text-xs px-2 py-1 sa-delete-btn" data-id="${a.id}" data-name="${escapeHtml(a.name)}" title="Hapus"><i class="fa-solid fa-trash text-[10px]"></i></button>`
                            : `<button class="btn-secondary text-xs px-2 py-1 opacity-40 cursor-not-allowed" title="Ada transaksi — nonaktifkan saja" disabled><i class="fa-solid fa-trash text-[10px]"></i></button>`
                        }
                    </div>
                </div>`;
            }).join('');
            $('#storage-accounts-list').html(html);

            // Preset: tampilkan yang belum ada
            const existingNames = new Set(accs.map(a => a.name));
            const presetHtml = SA_PRESETS.filter(p => !existingNames.has(p.name)).map(p =>
                `<button type="button" class="btn-secondary text-xs gap-1.5 sa-preset-btn px-2.5 py-1.5"
                    data-name="${escapeHtml(p.name)}" data-type="${p.type}" data-parent="${p.parent_type}" data-icon="${p.icon}">
                    <i class="${p.icon} text-[10px]"></i> ${escapeHtml(p.name)}
                </button>`
            ).join('');
            $('#storage-presets').html(presetHtml || '<span class="text-subtle text-xs">Semua preset sudah ditambahkan ✓</span>');
        }).fail(function () {
            $('#storage-accounts-list').html('<div class="text-rose-400 text-sm py-4 text-center">Gagal memuat daftar akun.</div>');
        });
    }

    // Buka modal
    $('#alokasi-manage-accounts-btn').on('click', function () {
        saResetForm();
        saLoadList();
        $('#modal-storage-accounts').removeClass('hidden');
    });
    // Tutup modal — refresh kartu saldo
    $('#storage-modal-close').on('click', function () {
        $('#modal-storage-accounts').addClass('hidden');
        loadAlokasiAdmin();
    });

    // Icon live preview
    $(document).on('input', '#sa-icon', function () {
        const cls = $(this).val().trim() || 'fa-solid fa-vault';
        $('#sa-icon-preview').html(`<i class="${escapeHtml(cls)}"></i>`);
    });

    // Klik preset: isi form & scroll ke form
    $(document).on('click', '.sa-preset-btn', function () {
        saResetForm();
        $('#sa-name').val($(this).data('name'));
        $('#sa-parent-type').val($(this).data('parent'));
        $('#sa-icon').val($(this).data('icon'));
        $('#sa-icon').trigger('input');
        $('#sa-submit-label').text('Tambah Akun');
        $('#sa-name').focus();
    });

    // Edit akun
    $(document).on('click', '.sa-edit-btn', function () {
        const id = $(this).data('id');
        $.getJSON('src/api/admin.php?action=list_storage_accounts_all', function (accs) {
            const a = accs.find(x => x.id == id);
            if (!a) return;
            $('#sa-edit-id').val(a.id);
            $('#sa-name').val(a.name);
            $('#sa-parent-type').val(a.parent_type || 'other');
            $('#sa-icon').val(a.icon || '');
            $('#sa-icon').trigger('input');
            $('#sa-sort').val(a.sort_order);
            $('#sa-submit-label').text('Simpan Perubahan');
            $('#sa-submit-btn i').removeClass('fa-plus').addClass('fa-floppy-disk');
            $('#sa-cancel-edit').removeClass('hidden');
            $('#sa-name').focus();
        });
    });

    // Batal edit
    $('#sa-cancel-edit').on('click', saResetForm);

    // Submit form
    $('#form-storage-account').on('submit', function (e) {
        e.preventDefault();
        const id         = $('#sa-edit-id').val();
        const name       = $('#sa-name').val().trim();
        const parentType = $('#sa-parent-type').val();
        const icon       = $('#sa-icon').val().trim() || 'fa-solid fa-vault';
        const sort       = parseInt($('#sa-sort').val()) || 99;
        const type       = id ? parentType : (parentType + '_' + name.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,''));
        const payload = { name, type, parent_type: parentType, icon, sort_order: sort };
        if (id) payload.id = id;

        const action = id ? 'update_storage_account' : 'add_storage_account';
        $.post('src/api/admin.php?action=' + action, payload, function (res) {
            if (res && res.ok) {
                saResetForm();
                saLoadList();
            } else {
                alert('Gagal: ' + (res && res.error ? res.error : 'unknown'));
            }
        }, 'json').fail(function (xhr) {
            try { const r = JSON.parse(xhr.responseText); alert('Gagal: ' + (r.error || 'unknown')); }
            catch (_) { alert('Gagal (HTTP ' + xhr.status + ')'); }
        });
    });

    // Toggle aktif/nonaktif
    $(document).on('click', '.sa-toggle-btn', function () {
        const id = $(this).data('id');
        $.post('src/api/admin.php?action=toggle_storage_account', { id }, function (res) {
            if (res && res.ok) saLoadList();
            else alert('Gagal: ' + (res && res.error ? res.error : 'unknown'));
        }, 'json');
    });

    // Hapus akun
    $(document).on('click', '.sa-delete-btn', function () {
        const id   = $(this).data('id');
        const name = $(this).data('name');
        if (!confirm(`Hapus tempat penyimpanan "${name}"?\nTindakan ini tidak dapat dibatalkan.`)) return;
        $.post('src/api/admin.php?action=delete_storage_account', { id }, function (res) {
            if (res && res.ok) saLoadList();
            else alert('Gagal: ' + (res && res.error ? res.error : 'unknown'));
        }, 'json').fail(function (xhr) {
            try { const r = JSON.parse(xhr.responseText); alert('Gagal: ' + (r.error || 'unknown')); }
            catch (_) { alert('Gagal (HTTP ' + xhr.status + ')'); }
        });
    });

    // ── Pengaturan Kelas ──────────────────────────────────────────────────────
    function loadPengaturanAdmin() {
        $.getJSON('src/api/admin.php?action=get_config', function (res) {
            if (!res || !res.ok) return;
            const cfg = res.config;
            $('#config-nama-kelas').val(cfg.nama_kelas);
            $('#config-tarif-kas').val(cfg.tarif_kas_mingguan);
            $('#config-saldo-awal').val(cfg.saldo_awal);

            // Update info panel
            $('#info-nama-kelas').text(cfg.nama_kelas);
            $('#info-tarif-kas').text(fmt(cfg.tarif_kas_mingguan) + ' / siswa');
            $('#info-saldo-awal').text(fmt(cfg.saldo_awal));
        });
    }

    $('#form-pengaturan').on('submit', function (e) {
        e.preventDefault();
        const $btn = $('#pengaturan-submit-btn');
        const $status = $('#pengaturan-status');
        $btn.prop('disabled', true);
        $status.removeClass('hidden text-[var(--semantic-success)] text-[var(--semantic-danger)]')
               .addClass('hidden');

        const payload = {
            nama_kelas:           $('#config-nama-kelas').val().trim(),
            tarif_kas_mingguan:   parseInt($('#config-tarif-kas').val()) || 0,
            saldo_awal:           parseFloat($('#config-saldo-awal').val()) || 0,
        };

        $.post('src/api/admin.php?action=update_config', payload, function (res) {
            $btn.prop('disabled', false);
            if (res && res.ok) {
                const cfg = res.config;
                // Update info panel
                $('#info-nama-kelas').text(cfg.nama_kelas);
                $('#info-tarif-kas').text(fmt(cfg.tarif_kas_mingguan) + ' / siswa');
                $('#info-saldo-awal').text(fmt(cfg.saldo_awal));

                // Reaktif: update nama kelas di seluruh UI tanpa reload
                $('#brand-nama-kelas').text('Cashflow ' + cfg.nama_kelas);
                $('.kelola-nama-kelas').text(cfg.nama_kelas);
                document.title = 'Dashboard Bendahara - Cashflow ' + cfg.nama_kelas;

                // Update tarif di kasState agar pencatatan kas mingguan langsung menggunakan tarif baru
                kasState.tarif = cfg.tarif_kas_mingguan;

                $status.text('✓ Pengaturan berhasil disimpan.')
                       .removeClass('hidden')
                       .addClass('text-[var(--semantic-success)]');
            } else {
                $status.text('✗ Gagal: ' + ((res && res.error) ? res.error : 'unknown'))
                       .removeClass('hidden')
                       .addClass('text-[var(--semantic-danger)]');
            }
        }, 'json').fail(function (xhr) {
            $btn.prop('disabled', false);
            let msg = 'Gagal (HTTP ' + xhr.status + ')';
            try { msg = JSON.parse(xhr.responseText).error || msg; } catch (_) {}
            $status.text('✗ ' + msg).removeClass('hidden').addClass('text-[var(--semantic-danger)]');
        });
    });

});
