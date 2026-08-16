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
        alokasi: loadAlokasiAdmin,
        riwayat: loadRiwayatAdmin,
    };

    $('[data-tab]').on('click', function () {
        activate($(this).data('tab'));
        if ($(window).width() < 768) {
            closeSidebar();
        }
    });

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
                        <th>Nama</th>
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
                    return '<tr>' +
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + (i + 1) + '</td>' +
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + escapeHtml(r.tanggal) + '</td>' +
                        '<td class="font-medium text-[var(--ink)]">' + escapeHtml(r.nama) + '</td>' +
                        '<td class="text-[var(--ink-muted)]">' + escapeHtml(r.keterangan) + '</td>' +
                        '<td class="text-right font-mono-num font-medium text-[var(--ink)]">' + fmt(r.jumlah) + '</td>' +
                        '<td>' + badge + '</td>' +
                        '<td class="text-right space-x-1">' +
                            '<button class="btn-secondary text-xs px-2.5 py-1 edit-kasbon gap-1" data-id="' + r.id + '" data-nama="' + escapeHtml(r.nama) + '" data-tanggal="' + escapeHtml(r.tanggal) + '" data-keterangan="' + escapeHtml(r.keterangan) + '" data-jumlah="' + r.jumlah + '" data-status="' + r.status + '">' +
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
        $('#kasbon-submit-btn').html('<i class="fa-solid fa-plus text-xs"></i> <span>Tambah</span>');
        $(this).addClass('hidden');
    });

    function lDash() {
        $.getJSON('src/api/public.php?action=get_summary', s => {
            const cards = [
                ['Total Kas', fmt(s.total_kas_terkumpul), 'text-[var(--primary)]', '<i class="fa-solid fa-vault text-sm"></i>'],
                ['Saldo BMS', fmt(s.saldo_bms), 'text-[var(--accent-sky)]', '<i class="fa-solid fa-building-columns text-sm"></i>'],
                ['Talangan Belum Diganti', fmt(s.total_kasbon), 'text-[var(--accent-orange)]', '<i class="fa-solid fa-handshake text-sm"></i>'],
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
                        <td class="font-mono text-xs text-[var(--ink-muted)]">${escapeHtml(t.tanggal)}</td>
                        <td class="text-[var(--ink)]">${escapeHtml(t.keterangan)}</td>
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
                        <td class="font-mono text-xs text-[var(--ink-muted)]">${escapeHtml(t.tanggal)}</td>
                        <td class="text-[var(--ink)]">${escapeHtml(t.keterangan)}</td>
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
                        '<td class="font-mono text-xs text-[var(--ink-muted)]">' + escapeHtml(r.tanggal) + '</td>' +
                        '<td class="text-[var(--ink)]">' + escapeHtml(r.keterangan) + '</td>' +
                        '<td>' + badge + '</td>' +
                        '<td class="text-right font-mono-num font-medium text-[var(--ink)]">' + fmt(r.jumlah) + '</td>' +
                        '<td class="text-right space-x-1">' +
                            '<button class="btn-secondary text-xs px-2.5 py-1 edit-bms gap-1" data-id="' + r.id + '" data-tanggal="' + escapeHtml(r.tanggal) + '" data-keterangan="' + escapeHtml(r.keterangan) + '" data-jenis="' + escapeHtml(r.jenis) + '" data-jumlah="' + r.jumlah + '">' +
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
                        `<td class="font-mono text-xs text-[var(--ink-muted)]">${r.tanggal}</td>` +
                        `<td><span class="badge-neutral">${refLabel[r.ref_type] || r.ref_type}</span></td>` +
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
                        `<td class="font-mono text-xs text-[var(--ink-muted)]">${r.tanggal}</td>` +
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

});
