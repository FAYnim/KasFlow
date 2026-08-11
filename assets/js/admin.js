$(function () {
    const $tabs = $('[data-tab-content]');
    const $navItems = $('[data-tab]');
    const kasState = { saved: {}, pending: {}, tarif: 0, bulan: '', tahun: 0 };

    // Theme Switcher Management for Admin
    function updateThemeUI(theme) {
        if (theme === 'dark') {
            $('#theme-toggle-icon').attr('class', 'fa-solid fa-moon text-indigo-400 text-sm');
            $('#theme-toggle-btn').attr('title', 'Switch to Light Theme');
        } else {
            $('#theme-toggle-icon').attr('class', 'fa-solid fa-sun text-amber-500 text-sm');
            $('#theme-toggle-btn').attr('title', 'Switch to Dark Theme');
        }
    }

    const currentTheme = localStorage.getItem('theme') || 'dark';
    $('html').attr('data-theme', currentTheme);
    updateThemeUI(currentTheme);

    $('#theme-toggle-btn').on('click', function () {
        const newTheme = $('html').attr('data-theme') === 'dark' ? 'light' : 'dark';
        $('html').attr('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeUI(newTheme);
    });

    const activate = (n) => { 
        $tabs.addClass('hidden'); 
        $(`[data-tab-content="${n}"]`).removeClass('hidden'); 
        
        $navItems.removeClass('active');
        $(`[data-tab="${n}"]`).addClass('active');

        if (loaders[n]) loaders[n](); 
    };

    const loaders = {
        dashboard: lDash,
        siswa: lSiswa,
        kas: lKas,
        jurnal: lJurnal,
        kasbon: lKasbon,
        ekspor: lEkspor,
        bms: lBms,
        riwayat: loadRiwayatAdmin,
    };

    $('[data-tab]').on('click', function () { activate($(this).data('tab')); });
    activate('dashboard');

    const fmt = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');
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

    function lKasbon() {
        const bulan = $('#admin-kasbon-bulan').val();
        const tahun = $('#admin-kasbon-tahun').val();
        $.getJSON('src/api/public.php', { action: 'get_kasbon', bulan, tahun }, function(data) {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-16">#</th>
                        <th class="w-32">Tanggal</th>
                        <th>Nama</th>
                        <th>Keterangan</th>
                        <th class="text-right w-36">Jumlah</th>
                        <th class="w-28">Status</th>
                        <th class="w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (!data || data.length === 0) {
                h += `<tr><td colspan="7" class="text-center py-6 text-[var(--ink-muted)]">Tidak ada data kasbon.</td></tr>`;
            } else {
                h += data.map(function(r, i) {
                    const badge = r.status === 'lunas'
                        ? '<span class="badge-status badge-success font-medium"><i class="fa-solid fa-circle-check text-[10px]"></i> <span>Lunas</span></span>'
                        : '<span class="badge-status badge-warning font-medium"><i class="fa-solid fa-clock text-[10px]"></i> <span>Belum Lunas</span></span>';
                    const toggleBtn = r.status === 'lunas'
                        ? '<button class="text-yellow-400 hover:text-yellow-300 text-xs toggle-kasbon" data-id="' + r.id + '" data-status="belum_lunas" title="Tandai Belum Lunas"><i class="fa-solid fa-rotate-left"></i></button>'
                        : '<button class="text-green-400 hover:text-green-300 text-xs toggle-kasbon" data-id="' + r.id + '" data-status="lunas" title="Tandai Lunas"><i class="fa-solid fa-circle-check"></i></button>';
                    return '<tr>' +
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + (i + 1) + '</td>' +
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + r.tanggal + '</td>' +
                        '<td class="font-medium text-[var(--ink)]">' + r.nama + '</td>' +
                        '<td class="text-[var(--ink-muted)]">' + r.keterangan + '</td>' +
                        '<td class="text-right font-mono-num font-medium text-[var(--ink)]">' + fmt(r.jumlah) + '</td>' +
                        '<td>' + badge + '</td>' +
                        '<td class="text-right space-x-1">' +
                            '<button class="btn-secondary text-xs px-2.5 py-1 edit-kasbon gap-1" data-id="' + r.id + '" data-nama="' + r.nama + '" data-tanggal="' + r.tanggal + '" data-keterangan="' + r.keterangan + '" data-jumlah="' + r.jumlah + '" data-status="' + r.status + '">' +
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
            $('#kasbon-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Gagal memuat data kasbon.</div>');
        });
    }

    $(document).on('click', '.edit-kasbon', function () {
        const $btn = $(this);
        $('#kasbon-edit-id').val($btn.data('id'));
        $('#kasbon-nama').val($btn.data('nama'));
        $('#kasbon-tanggal').val($btn.data('tanggal'));
        $('#kasbon-keterangan').val($btn.data('keterangan'));
        $('#kasbon-jumlah').val($btn.data('jumlah'));
        $('#kasbon-status').val($btn.data('status'));
        $('#kasbon-submit-btn').html('<i class="fa-solid fa-floppy-disk text-xs"></i> <span>Update</span>');
        $('#kasbon-cancel-btn').removeClass('hidden');
    });

    $(document).on('click', '.toggle-kasbon', function () {
        const id = $(this).data('id');
        const newStatus = $(this).data('status');
        const action = newStatus === 'lunas' ? 'mark_lunas_kasbon' : 'mark_belum_lunas_kasbon';
        $.post('src/api/admin.php?action=' + action, { id: id }, function() {
            lKasbon();
        }, 'json').fail(function() {
            alert('Gagal mengubah status kasbon.');
        });
    });

    $(document).on('click', '.del-kasbon', function () {
        if (!confirm('Hapus kasbon ini?')) return;
        $.post('src/api/admin.php?action=delete_kasbon', { id: $(this).data('id') }, function() {
            lKasbon();
        }, 'json').fail(function() {
            alert('Gagal menghapus kasbon.');
        });
    });

    $('#form-kasbon').on('submit', function (e) {
        e.preventDefault();
        const nama = $('#kasbon-nama').val().trim();
        const jumlah = parseFloat($('#kasbon-jumlah').val());
        if (!nama) { alert('Nama harus diisi.'); return; }
        if (!jumlah || jumlah <= 0) { alert('Jumlah harus lebih dari 0.'); return; }
        const editId = $('#kasbon-edit-id').val();
        const payload = {
            action: editId ? 'update_kasbon' : 'add_kasbon',
            id: editId || undefined,
            nama: nama,
            tanggal: $('#kasbon-tanggal').val(),
            keterangan: $('#kasbon-keterangan').val().trim(),
            jumlah: jumlah,
            status: $('#kasbon-status').val()
        };
        $.post('src/api/admin.php?action=' + payload.action, payload, function(res) {
            if (res.ok) {
                $('#form-kasbon')[0].reset();
                $('#kasbon-edit-id').val('');
                $('#kasbon-submit-btn').html('<i class="fa-solid fa-plus text-xs"></i> <span>Tambah</span>');
                $('#kasbon-cancel-btn').addClass('hidden');
                lKasbon();
            } else {
                alert(res.error || 'Gagal menyimpan kasbon.');
            }
        }, 'json').fail(function() {
            alert('Gagal menyimpan kasbon.');
        });
    });

    $('#kasbon-cancel-btn').on('click', function () {
        $('#form-kasbon')[0].reset();
        $('#kasbon-edit-id').val('');
        $('#kasbon-submit-btn').html('<i class="fa-solid fa-plus text-xs"></i> <span>Tambah</span>');
        $(this).addClass('hidden');
    });

    function lDash() {
        $.getJSON('src/api/public.php?action=get_summary', s => {
            const cards = [
                ['Total Kas', fmt(s.total_kas_terkumpul), 'text-[var(--primary)]', '<i class="fa-solid fa-vault text-sm"></i>'],
                ['Saldo BMS', fmt(s.saldo_bms), 'text-[var(--accent-sky)]', '<i class="fa-solid fa-building-columns text-sm"></i>'],
                ['Total Kasbon', fmt(s.total_kasbon), 'text-[var(--accent-orange)]', '<i class="fa-solid fa-handshake text-sm"></i>'],
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
                        <td class="font-mono text-xs text-[var(--ink-muted)]">${s.absen||'-'}</td>
                        <td class="font-medium text-[var(--ink)]">${s.nama}</td>
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
        $.post('src/api/admin.php?action=delete_siswa', { id: $(this).data('id') }, r => lSiswa(), 'json');
    });

    $('#form-siswa').on('submit', function (e) {
        e.preventDefault();
        $.post('src/api/admin.php?action=add_siswa', $(this).serialize(), r => {
            if (r.ok) { this.reset(); lSiswa(); } else alert(r.error);
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

    $('#kas-save-btn').on('click', function () {
        const changes = [];
        Object.keys(kasState.pending).forEach(sid => {
            Object.keys(kasState.pending[sid]).forEach(m => {
                changes.push({ siswa_id: parseInt(sid), minggu: parseInt(m), checked: kasState.pending[sid][m] });
            });
        });
        if (changes.length === 0) return;
        const $btn = $(this).prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin text-[10px]"></i> <span>Menyimpan...</span>');
        $.ajax({
            url: 'src/api/admin.php?action=bulk_update_kas',
            method: 'POST',
            data: { bulan: kasState.bulan, tahun: kasState.tahun, changes: JSON.stringify(changes) },
            dataType: 'json',
            success: r => {
                if (r.ok) {
                    Object.keys(r.totals || {}).forEach(sid => { kasState.saved[sid] = { ...(kasState.pending[sid] || kasState.saved[sid] || {}) }; });
                    Object.keys(kasState.pending).forEach(sid => { kasState.saved[sid] = { ...kasState.saved[sid], ...kasState.pending[sid] }; });
                    kasState.pending = {};
                    lKas();
                } else {
                    alert(r.error || 'Gagal menyimpan.');
                }
            },
            error: () => alert('Gagal terhubung server.'),
            complete: () => { $btn.prop('disabled', false).html('<i class="fa-solid fa-floppy-disk text-[10px]"></i> <span>Simpan</span>'); }
        });
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

    // Jurnal Modal & CRUD
    $('#btn-add-jurnal').on('click', () => openJurnalModal());
    $('#modal-close, #modal-close-btn').on('click', () => $('#modal-jurnal').addClass('hidden'));

    function openJurnalModal(t) {
        $('#modal-jurnal').removeClass('hidden');
        const f = $('#form-jurnal')[0];
        f.reset();
        if (t) { 
            f.id.value = t.id; 
            f.tanggal.value = t.tanggal; 
            f.keterangan.value = t.keterangan; 
            f.jenis.value = t.jenis; 
            f.nominal.value = t.nominal; 
        }
    }

    $('#form-jurnal').on('submit', function (e) {
        e.preventDefault();
        const id = this.id.value;
        const action = id ? 'update_jurnal' : 'add_jurnal';
        $.post('src/api/admin.php?action=' + action, $(this).serialize(), r => {
            if (r.ok) { 
                $('#modal-jurnal').addClass('hidden'); 
                lJurnal(); 
            } else {
                alert(r.error);
            }
        }, 'json');
    });

    function lJurnal(page) {
        if (page !== undefined) adminJurnalPage = page;
        const params = { action: 'get_jurnal', page: adminJurnalPage, limit: 15 };
        const b = $('#jurnal-bulan').val();
        const t = $('#jurnal-tahun').val();
        if (b) params.bulan = b;
        if (t) params.tahun = t;
        $.getJSON('src/api/public.php', params, r => {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Tanggal</th>
                        <th>Keterangan</th>
                        <th class="w-28">Jenis</th>
                        <th class="text-right w-36">Nominal</th>
                        <th class="w-36 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (!r.transaksi || r.transaksi.length === 0) {
                h += `<tr><td colspan="5" class="text-center py-6 text-[var(--ink-muted)]">Belum ada jurnal transaksi.</td></tr>`;
            } else {
                h += r.transaksi.map(t =>
                    `<tr>
                        <td class="font-mono text-xs text-[var(--ink-muted)]">${t.tanggal}</td>
                        <td class="text-[var(--ink)]">${t.keterangan}</td>
                        <td>
                            <span class="badge-status ${t.jenis==='masuk'?'badge-success':'badge-danger'} font-medium">
                                <i class="fa-solid ${t.jenis==='masuk' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'} text-[10px]"></i>
                                <span>${t.jenis==='masuk' ? 'Masuk' : 'Keluar'}</span>
                            </span>
                        </td>
                        <td class="text-right font-mono-num font-medium text-[var(--ink)]">${fmt(t.nominal)}</td>
                        <td class="text-right space-x-1">
                            <button class="btn-secondary text-xs px-2.5 py-1 edit-j gap-1" data-id="${t.id}">
                                <i class="fa-solid fa-pen text-[10px]"></i>
                                <span>Edit</span>
                            </button>
                            <button class="btn-danger text-xs px-2.5 py-1 del-j gap-1" data-id="${t.id}">
                                <i class="fa-solid fa-trash-can text-[10px]"></i>
                                <span>Hapus</span>
                            </button>
                        </td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
            $('#jurnal-wrap').html(h);
            renderPagination('jurnal-pagination', r.pagination, (p) => lJurnal(p));
        });
    }

    $(document).on('click', '.edit-j', function () {
        const id = $(this).data('id');
        $.getJSON('src/api/public.php?action=get_jurnal', r => {
            const t = r.transaksi.find(x => x.id == id);
            if (t) openJurnalModal(t);
        });
    });

    $(document).on('click', '.del-j', function () {
        if (!confirm('Hapus transaksi ini?')) return;
        $.post('src/api/admin.php?action=delete_jurnal', { id: $(this).data('id') }, r => lJurnal(), 'json');
    });

    // Ekspor
    function lEkspor() {
        const dari = $('#form-ekspor [name=dari]').val();
        const sampai = $('#form-ekspor [name=sampai]').val();
        $.getJSON('src/api/public.php?action=get_jurnal', r => {
            const rows = r.transaksi.filter(t => (!dari || t.tanggal >= dari) && (!sampai || t.tanggal <= sampai));
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Tanggal</th>
                        <th>Keterangan</th>
                        <th class="w-28">Jenis</th>
                        <th class="text-right w-36">Nominal</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="4" class="text-center py-6 text-[var(--ink-muted)]">Tidak ada data jurnal untuk rentang tanggal ini.</td></tr>`;
            } else {
                h += rows.map(t => 
                    `<tr>
                        <td class="font-mono text-xs text-[var(--ink-muted)]">${t.tanggal}</td>
                        <td class="text-[var(--ink)]">${t.keterangan}</td>
                        <td>
                            <span class="badge-status ${t.jenis==='masuk'?'badge-success':'badge-danger'} font-medium">
                                <i class="fa-solid ${t.jenis==='masuk' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'} text-[10px]"></i>
                                <span>${t.jenis==='masuk' ? 'Masuk' : 'Keluar'}</span>
                            </span>
                        </td>
                        <td class="text-right font-mono-num font-medium text-[var(--ink)]">${fmt(t.nominal)}</td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
            $('#ekspor-preview').html(h);
            $('#ekspor-preview').data('rows', rows);
        });
    }

    $('#form-ekspor [name=dari], #form-ekspor [name=sampai]').on('change', lEkspor);
    
    $('#btn-csv').on('click', function (e) {
        e.preventDefault();
        const rows = $('#ekspor-preview').data('rows') || [];
        const csv = 'Tanggal,Keterangan,Jenis,Nominal\n' + rows.map(t => `${t.tanggal},"${t.keterangan}",${t.jenis},${t.nominal}`).join('\n');
        const blob = new Blob([csv], { type:'text/csv' });
        const a = document.createElement('a'); 
        a.href = URL.createObjectURL(blob); 
        a.download = 'laporan_kas.csv'; 
        a.click();
    });

    $('#btn-pdf').on('click', () => window.print());

    // Kas BMS
    function lBms() {
        $.getJSON('src/api/public.php?action=get_bms', function(data) {
            const rows = (data && data.rows) || [];
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-16">#</th>
                        <th class="w-32">Tanggal</th>
                        <th>Keterangan</th>
                        <th class="w-28">Jenis</th>
                        <th class="text-right w-36">Jumlah</th>
                        <th class="w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="6" class="text-center py-6 text-[var(--ink-muted)]">Belum ada data kas BMS.</td></tr>`;
            } else {
                h += rows.map(function(r, i) {
                    const badge = r.jenis === 'setor'
                        ? '<span class="badge-status badge-success font-medium"><i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i> <span>Setor</span></span>'
                        : '<span class="badge-status badge-neutral font-medium"><i class="fa-solid fa-arrow-right-from-bracket text-[10px]"></i> <span>Tarik</span></span>';
                    return '<tr>' +
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + (i + 1) + '</td>' +
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + r.tanggal + '</td>' +
                        '<td class="text-[var(--ink)]">' + r.keterangan + '</td>' +
                        '<td>' + badge + '</td>' +
                        '<td class="text-right font-mono-num font-medium text-[var(--ink)]">' + fmt(r.jumlah) + '</td>' +
                        '<td class="text-right space-x-1">' +
                            '<button class="btn-secondary text-xs px-2.5 py-1 edit-bms gap-1" data-id="' + r.id + '" data-tanggal="' + r.tanggal + '" data-keterangan="' + r.keterangan + '" data-jenis="' + r.jenis + '" data-jumlah="' + r.jumlah + '">' +
                                '<i class="fa-solid fa-pen text-[10px]"></i> <span>Edit</span>' +
                            '</button>' +
                            '<button class="btn-danger text-xs px-2.5 py-1 del-bms gap-1" data-id="' + r.id + '">' +
                                '<i class="fa-solid fa-trash-can text-[10px]"></i> <span>Hapus</span>' +
                            '</button>' +
                        '</td>' +
                    '</tr>';
                }).join('');
            }
            h += '</tbody></table>';
            $('#bms-wrap').html(h);
        }).fail(function() {
            $('#bms-wrap').html('<div class="text-center py-6 text-[var(--ink-muted)]">Gagal memuat data kas BMS.</div>');
        });
    }

    // Open modal in add mode
    $('#bms-add-btn').on('click', function () {
        $('#bms-edit-id').val('');
        $('#bms-form')[0].reset();
        $('#bms-tanggal').val(new Date().toISOString().slice(0, 10));
        $('input[name="bms-jenis"][value="setor"]').prop('checked', true);
        $('#bms-submit-btn').html('<i class="fa-solid fa-floppy-disk text-xs"></i> <span>Simpan</span>');
        $('#bms-modal').removeClass('hidden');
    });

    $('#bms-modal-close, #bms-cancel-btn').on('click', function () {
        $('#bms-modal').addClass('hidden');
    });

    $(document).on('click', '.edit-bms', function () {
        const $btn = $(this);
        $('#bms-edit-id').val($btn.data('id'));
        $('#bms-tanggal').val($btn.data('tanggal'));
        $('#bms-keterangan').val($btn.data('keterangan'));
        $('#bms-jumlah').val($btn.data('jumlah'));
        $('input[name="bms-jenis"][value="' + $btn.data('jenis') + '"]').prop('checked', true);
        $('#bms-submit-btn').html('<i class="fa-solid fa-floppy-disk text-xs"></i> <span>Update</span>');
        $('#bms-modal').removeClass('hidden');
    });

    $('#bms-form').on('submit', function (e) {
        e.preventDefault();
        const id = $('#bms-edit-id').val();
        const payload = {
            tanggal:     $('#bms-tanggal').val(),
            keterangan:  $('#bms-keterangan').val(),
            jenis:       $('input[name="bms-jenis"]:checked').val(),
            jumlah:      $('#bms-jumlah').val(),
        };
        const action = id ? 'update_bms' : 'add_bms';
        if (id) payload.id = id;
        $.post('src/api/admin.php?action=' + action, payload, function (res) {
            if (res && res.ok) {
                $('#bms-modal').addClass('hidden');
                lBms();
            } else {
                alert('Gagal menyimpan: ' + (res && res.error ? res.error : 'unknown'));
            }
        }, 'json').fail(function (xhr) {
            alert('Gagal menyimpan (HTTP ' + xhr.status + ').');
        });
    });

    $(document).on('click', '.del-bms', function () {
        if (!confirm('Hapus transaksi kas BMS ini?')) return;
        const id = $(this).data('id');
        $.post('src/api/admin.php?action=delete_bms', { id: id }, function () {
            lBms();
        }, 'json').fail(function () {
            alert('Gagal menghapus.');
        });
    });

    // ── Riwayat helpers & loader (admin) ────────────────────────────
    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
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
        const aksi   = $('#riwayat-aksi').val();
        const dari   = $('#riwayat-dari').val();
        const sampai = $('#riwayat-sampai').val();
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
                                        jumlah: 'Jumlah', status: 'Status', id: 'ID Entitas'
                                    };
                                    const list = keys.map(k => {
                                        let val = d[k];
                                        if ((k === 'nominal' || k === 'jumlah') && typeof val === 'number') {
                                            val = 'Rp ' + val.toLocaleString('id-ID');
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
                html += '<tr>'
                    + '<td class="text-xs text-[var(--ink-muted)] whitespace-nowrap">' + formatDateTime(r.created_at) + '</td>'
                    + '<td><span class="badge-neutral">' + escapeHtml(r.modul) + '</span></td>'
                    + '<td><span class="badge-' + escapeHtml(r.aksi) + '">' + escapeHtml(r.aksi) + '</span></td>'
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
});
