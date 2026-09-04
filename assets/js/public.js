    $(function () {
    const bulanList = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const $tabs = $('[data-tab-content]');
    const $navItems = $('[data-tab]');

    // Theme Switcher Management
    function updateThemeUI(theme) {
        if (theme === 'dark') {
            $('#theme-toggle-icon').attr('class', 'fa-solid fa-moon text-indigo-400 text-sm');
            $('#theme-toggle-btn').attr('title', 'Switch to Light Theme');
        } else {
            $('#theme-toggle-icon').attr('class', 'fa-solid fa-sun text-amber-500 text-sm');
            $('#theme-toggle-btn').attr('title', 'Switch to Dark Theme');
        }
    }

    const currentTheme = localStorage.getItem('theme') || 'light';
    $('html').attr('data-theme', currentTheme);
    updateThemeUI(currentTheme);

    $('#theme-toggle-btn').on('click', function () {
        const newTheme = $('html').attr('data-theme') === 'dark' ? 'light' : 'dark';
        $('html').attr('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        updateThemeUI(newTheme);

        if (lineChart || donutChart) {
            renderCharts(lastChartData);
        }
    });

    const activate = (name) => {
        $tabs.addClass('hidden');
        $('[data-tab-content="' + name + '"]').removeClass('hidden');
        
        $navItems.removeClass('active');
        $('[data-tab="' + name + '"]').addClass('active');

        if (loaders[name]) loaders[name]();
    };

    const loaders = {
        dashboard: loadDashboard,
        kas: loadKas,
        jurnal: loadPublicCashflow,
        riwayat: loadRiwayat,
    };

    $('#btn-hamburger').on('click', () => $('#sidebar').toggleClass('-translate-x-full'));
    $('[data-tab]').on('click', function () { 
        activate($(this).data('tab')); 
        if ($(window).width() < 768) {
            $('#sidebar').addClass('-translate-x-full'); 
        }
    });

    const now = new Date();
    $('#kas-bulan').html(bulanList.map(b => `<option ${b===bulanList[now.getMonth()]?'selected':''}>${b}</option>`).join(''));
    $('#kas-tahun').html([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option ${y===now.getFullYear()?'selected':''}>${y}</option>`).join(''));
    $('#kas-bulan, #kas-tahun').on('change', loadKas);
    $('#kas-search').on('input', filterKas);

    $('#pub-filter-type, #pub-filter-account, #pub-filter-category').on('change', () => {
        pubCfPage = 1;
        loadPublicTransactionsTable();
    });
    let pubSearchTimer = null;
    $('#pub-filter-search').on('input', function () {
        clearTimeout(pubSearchTimer);
        pubSearchTimer = setTimeout(() => {
            pubCfPage = 1;
            loadPublicTransactionsTable();
        }, 300);
    });

    const fmt = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');

    function escapeHtml(s) {
        if (s == null) return '';
        return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    let lastChartData = null;
    let lineChart, donutChart;
    function loadDashboard() {
        $.getJSON('src/api/public.php?action=get_finance_public', function (res) {
            if (!res || !res.ok) return;
            const s = res.summary || {};
            const accounts = res.accounts || [];
            const cards = [
                ['Total Saldo Kas', fmt(s.total_saldo), 'text-[var(--primary)]', '<i class="fa-solid fa-wallet text-sm"></i>'],
                ['Total Pemasukan', fmt(s.total_income), 'text-emerald-500', '<i class="fa-solid fa-arrow-trend-up text-sm"></i>'],
                ['Total Pengeluaran', fmt(s.total_expense), 'text-rose-500', '<i class="fa-solid fa-arrow-trend-down text-sm"></i>'],
                ['Dompet Simpanan', `${accounts.length} Akun`, 'text-indigo-400', '<i class="fa-solid fa-vault text-sm"></i>'],
            ];
            $('#summary-cards').html(cards.map(([t, v, colorClass, icon]) =>
                `<div class="card-linear">
                    <div class="flex items-center justify-between mb-2">
                        <span class="eyebrow">${t}</span>
                        <span class="text-subtle">${icon}</span>
                    </div>
                    <div class="text-2xl font-bold font-mono-num ${colorClass}">${v}</div>
                </div>`
            ).join(''));
        });

        $.getJSON('src/api/public.php', { action: 'get_jurnal' }, function (r) {
            lastChartData = r;
            renderCharts(r);
        });
    }

    function renderDonutChart(accounts) {
        if (!accounts || accounts.length === 0) return;
        const labels = accounts.map(a => a.name);
        const data = accounts.map(a => Math.max(0, parseFloat(a.balance || 0)));
        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4'];

        const isDark = $('html').attr('data-theme') === 'dark';
        const donutBorderColor = isDark ? '#202020' : '#ffffff';

        if (donutChart) donutChart.destroy();
        const canvas = document.getElementById('chart-donut');
        if (!canvas) return;
        donutChart = new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors.slice(0, labels.length),
                    borderColor: donutBorderColor,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, padding: 12 }
                    }
                },
                cutout: '68%'
            }
        });
    }

    function renderCharts(r) {
        const labels = r.line_chart.map(x => x.tanggal);
        const data   = r.line_chart.map(x => x.saldo);

        const isDark = $('html').attr('data-theme') === 'dark';
        const gridColor = isDark ? '#2f2f2f' : '#e6e6e6';
        const textColor = isDark ? '#9b9b9b' : '#615d59';
        const donutBorderColor = isDark ? '#202020' : '#ffffff';
        const primaryColor = isDark ? '#2383e2' : '#0075de';
        const expenseColor = isDark ? '#ff8c3a' : '#dd5b00';

        Chart.defaults.color = textColor;
        Chart.defaults.font.family = "'Inter', sans-serif";

        if (lineChart) lineChart.destroy();
        lineChart = new Chart(document.getElementById('chart-line'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Saldo',
                    data,
                    borderColor: primaryColor,
                    backgroundColor: isDark ? 'rgba(35, 131, 226, 0.12)' : 'rgba(0, 117, 222, 0.12)',
                    borderWidth: 2,
                    pointBackgroundColor: primaryColor,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor } },
                    y: { grid: { color: gridColor }, ticks: { color: textColor } }
                }
            }
        });

        if (donutChart) donutChart.destroy();
        donutChart = new Chart(document.getElementById('chart-donut'), {
            type: 'doughnut',
            data: {
                labels: ['Pemasukan', 'Pengeluaran'],
                datasets: [{
                    data: [r.donut.masuk, r.donut.keluar],
                    backgroundColor: [primaryColor, expenseColor],
                    borderColor: donutBorderColor,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { color: textColor, padding: 16 } } }
            }
        });
    }

    let kasData = [];
    function loadKas() {
        const bulan = $('#kas-bulan').val(), tahun = $('#kas-tahun').val();
        $.getJSON('src/api/public.php', { action:'get_kas', bulan, tahun }, function (res) {
            kasData = (res && res.rows) ? res.rows : (Array.isArray(res) ? res : []);
            renderKas();
        });
    }

    function renderKas() {
        const q = ($('#kas-search').val() || '').toLowerCase();
        const rows = kasData.filter(r => (r.nama || '').toLowerCase().includes(q));
        let html = `<thead>
            <tr>
                <th class="w-24">Absen</th>
                <th>Nama Siswa</th>
                ${[1,2,3,4,5].map(i => `<th class="text-center w-16">M${i}</th>`).join('')}
                <th class="text-right">Total Bayar</th>
            </tr>
        </thead>
        <tbody>`;
        
        if (rows.length === 0) {
            html += `<tr><td colspan="8" class="text-center py-6 text-subtle">Tidak ada data siswa ditemukan.</td></tr>`;
        } else {
            html += rows.map(r =>
                `<tr>
                    <td class="font-mono text-xs text-subtle">${escapeHtml(r.absen||'-')}</td>
                    <td class="font-medium text-ink">${escapeHtml(r.nama)}</td>
                    ${[r.m1,r.m2,r.m3,r.m4,r.m5].map(v => 
                        `<td class="text-center">${v ? '<i class="fa-solid fa-circle-check text-[var(--semantic-success)] text-xs"></i>' : '<i class="fa-solid fa-circle-xmark text-[var(--hairline-strong)] text-xs"></i>'}</td>`
                    ).join('')}
                    <td class="text-right font-mono-num font-medium text-ink">${fmt(r.total_bayar)}</td>
                </tr>`
            ).join('');
        }
        html += '</tbody>';
        $('#kas-table').html(html);
    }

    function filterKas() { renderKas(); }

    // ── Pagination state ─────────────────────────────────────────────────
    let jurnalPage  = 1;
    let riwayatPage = 1;

    /**
     * Render pagination controls into a container element.
     * @param {string} containerId  - jQuery selector id (without #)
     * @param {object} pagination   - { page, limit, total_records, total_pages }
     * @param {function} onPage     - callback(pageNumber) when user clicks a page button
     */
    function renderPagination(containerId, pagination, onPage) {
        const $container = $('#' + containerId);
        if (!pagination || pagination.total_pages <= 1) {
            $container.empty();
            return;
        }
        const { page, total_pages, total_records, limit } = pagination;
        const from = ((page - 1) * limit) + 1;
        const to   = Math.min(page * limit, total_records);

        // Build page buttons (windowed: always show first, last, current±1)
        const pages = new Set([1, total_pages]);
        for (let i = Math.max(1, page - 1); i <= Math.min(total_pages, page + 1); i++) pages.add(i);
        const sorted = Array.from(pages).sort((a, b) => a - b);

        let btns = '';
        // Prev button
        btns += `<button class="pagination-btn${page === 1 ? ' pagination-btn-disabled' : ''}" data-page="${page - 1}" ${page === 1 ? 'disabled' : ''}>
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                </button>`;

        let prev = 0;
        sorted.forEach(p => {
            if (p - prev > 1) {
                btns += `<button class="pagination-btn pagination-btn-ellipsis">…</button>`;
            }
            btns += `<button class="pagination-btn${p === page ? ' pagination-btn-active' : ''}" data-page="${p}">${p}</button>`;
            prev = p;
        });

        // Next button
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
    //  PUBLIC CASHFLOW & MONEY TRACKER
    // ══════════════════════════════════════════════════════════════════════════════
    let pubCfPage = 1;
    let pubOverviewData = { accounts: [], categories: [], summary: {} };

    function loadPublicCashflow() {
        $.getJSON('src/api/public.php?action=get_finance_public', function (res) {
            if (!res || !res.ok) return;
            pubOverviewData = res;

            // 1. Render Account Cards
            const accounts = res.accounts || [];
            let cardsHtml = '';
            if (accounts.length === 0) {
                cardsHtml = '<div class="text-subtle text-xs py-4 col-span-full">Belum ada akun dompet aktif.</div>';
            } else {
                cardsHtml = accounts.map(a => {
                    const icon = a.icon || 'fa-solid fa-wallet';
                    const bal = parseFloat(a.balance || 0);
                    const balClass = bal >= 0 ? 'text-[var(--ink)]' : 'text-rose-500';
                    return `
                        <div class="card-linear">
                            <div class="flex items-center justify-between mb-2">
                                <span class="eyebrow">${escapeHtml(a.name)}</span>
                                <span class="text-subtle"><i class="${escapeHtml(icon)} text-sm"></i></span>
                            </div>
                            <div class="text-2xl font-bold font-mono-num ${balClass}">${fmt(bal)}</div>
                            <div class="text-[11px] text-subtle mt-1 capitalize">${escapeHtml(a.type || 'Akun')}</div>
                        </div>
                    `;
                }).join('');
            }
            $('#public-accounts-grid').html(cardsHtml);

            // 2. Populate Dropdowns
            const accOpts = '<option value="">Semua Akun</option>' +
                accounts.map(a => `<option value="${a.id}">${escapeHtml(a.name)}</option>`).join('');
            $('#pub-filter-account').html(accOpts);

            const catOpts = '<option value="">Semua Kategori</option>' +
                (res.categories || []).map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
            $('#pub-filter-category').html(catOpts);

            // 3. Load Transactions
            loadPublicTransactionsTable();
        });
    }

    function loadPublicTransactionsTable(page) {
        if (page !== undefined) pubCfPage = page;
        const params = {
            action: 'get_transactions_public',
            page: pubCfPage,
            limit: 15,
            type: $('#pub-filter-type').val() || '',
            account_id: $('#pub-filter-account').val() || '',
            category_id: $('#pub-filter-category').val() || '',
            search: $('#pub-filter-search').val() || ''
        };

        $.getJSON('src/api/public.php', params, function (res) {
            if (!res || !res.ok) {
                $('#public-cashflow-wrap').html('<div class="text-center py-6 text-subtle">Gagal memuat data transaksi.</div>');
                return;
            }

            const rows = res.data || res.transactions || [];
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-28">Tanggal</th>
                        <th class="w-24">Tipe</th>
                        <th class="w-36">Kategori</th>
                        <th class="w-36">Dompet / Akun</th>
                        <th>Keterangan</th>
                        <th class="text-right w-36">Nominal</th>
                    </tr>
                </thead>
                <tbody>`;

            if (rows.length === 0) {
                h += `<tr><td colspan="6" class="text-center py-6 text-subtle">Belum ada transaksi sesuai filter.</td></tr>`;
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
                        : '<span class="text-subtle text-xs">—</span>';

                    let accText = escapeHtml(t.account_name || '—');
                    if (t.type === 'transfer' && t.to_account_name) {
                        accText = `<div class="text-xs flex items-center gap-1"><span>${escapeHtml(t.account_name)}</span> <i class="fa-solid fa-arrow-right text-[10px] text-subtle"></i> <span>${escapeHtml(t.to_account_name)}</span></div>`;
                    }

                    return `
                        <tr>
                            <td class="font-mono text-xs text-subtle">${escapeHtml(t.date)}</td>
                            <td>${typeBadge}</td>
                            <td>${catBadge}</td>
                            <td class="text-xs text-ink">${accText}</td>
                            <td class="text-xs text-ink">${escapeHtml(t.description || '—')}</td>
                            <td class="text-right font-mono-num text-xs ${nomColor}">${nomPrefix}${fmt(t.amount)}</td>
                        </tr>
                    `;
                }).join('');
            }

            h += '</tbody></table>';
            $('#public-cashflow-wrap').html(h);
            renderPagination('public-cashflow-pagination', res.pagination, p => loadPublicTransactionsTable(p));
        });
    }





    activate('kas');


    // ── Riwayat helpers & loader ──────────────────────────────────────────
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
    function loadRiwayat(page) {
        if (page !== undefined) riwayatPage = page;
        const params = new URLSearchParams({ action: 'get_riwayat', page: riwayatPage, limit: 15 });
        const modul  = $('#riwayat-modul').val();
        const aksi   = $('#riwayat-aksi').val();
        const dari   = $('#riwayat-dari').val();
        const sampai = $('#riwayat-sampai').val();
        if (modul)  params.set('modul', modul);
        if (aksi)   params.set('aksi', aksi);
        if (dari)   params.set('dari', dari);
        if (sampai) params.set('sampai', sampai);
        $('#riwayat-wrap').html('<div class="text-center py-6 text-subtle">Memuat…</div>');
        $('#riwayat-pagination').empty();
        $.getJSON('src/api/public.php?' + params.toString(), function(res) {
            const rows = res.data || [];
            if (!rows.length) {
                $('#riwayat-wrap').html('<div class="text-center py-6 text-subtle">Belum ada riwayat.</div>');
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
                                        ? '<span class="text-emerald-600 dark:text-emerald-400 font-semibold">Lunas</span>' 
                                        : '<span class="text-amber-600 dark:text-amber-400 font-semibold">Belum Lunas</span>';
                                    return `• <b>${escapeHtml(p.nama)}</b> — Minggu ${escapeHtml(p.minggu)} (${stBadge})`;
                                }).join('<br>');
                                cellRingkasan += `<details class="mt-1 text-xs text-subtle cursor-pointer"><summary class="text-xs text-primary font-medium underline">Lihat Rincian (${d.perubahan.length} item)</summary><div class="mt-1 p-2 bg-surface-subtle rounded border border-subtle leading-relaxed">${list}</div></details>`;
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
                                    cellRingkasan += `<details class="mt-1 text-xs text-subtle cursor-pointer"><summary class="text-xs text-primary font-medium underline">Lihat Rincian</summary><div class="mt-1 p-2 bg-surface-subtle rounded border border-subtle leading-relaxed">${list}</div></details>`;
                                }
                            }
                        }
                    } catch(e) {}
                }

                let modulBadge = '<span class="badge-neutral">' + escapeHtml(r.modul) + '</span>';
                if (r.modul === 'cashflow') {
                    modulBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-500/15 text-indigo-500 dark:text-indigo-400 border border-indigo-500/30"><i class="fa-solid fa-money-bill-transfer text-[10px] mr-1"></i>cashflow</span>';
                } else if (r.modul === 'kas_mingguan') {
                    modulBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/15 text-emerald-500 dark:text-emerald-400 border border-emerald-500/30"><i class="fa-solid fa-coins text-[10px] mr-1"></i>kas mingguan</span>';
                } else if (r.modul === 'account') {
                    modulBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-cyan-500/15 text-cyan-500 dark:text-cyan-400 border border-cyan-500/30"><i class="fa-solid fa-wallet text-[10px] mr-1"></i>akun</span>';
                } else if (r.modul === 'category') {
                    modulBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-500/15 text-amber-500 dark:text-amber-400 border border-amber-500/30"><i class="fa-solid fa-tag text-[10px] mr-1"></i>kategori</span>';
                }

                let aksiBadge = '<span class="badge-' + escapeHtml(r.aksi) + '">' + escapeHtml(r.aksi) + '</span>';
                if (r.aksi === 'claim_kas') {
                    aksiBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-teal-500/15 text-teal-500 dark:text-teal-400 border border-teal-500/30">klaim kas</span>';
                }

                html += '<tr>'
                    + '<td class="text-xs text-subtle whitespace-nowrap">' + formatDateTime(r.created_at) + '</td>'
                    + '<td>' + modulBadge + '</td>'
                    + '<td>' + aksiBadge + '</td>'
                    + '<td>' + cellRingkasan + '</td>'
                    + '<td class="text-sm">' + escapeHtml(r.admin_nama || r.admin_username || '-') + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            $('#riwayat-wrap').html(html);
            renderPagination('riwayat-pagination', res.pagination, (p) => loadRiwayat(p));
        }).fail(function() {
            $('#riwayat-wrap').html('<div class="text-center py-6 text-subtle">Gagal memuat data.</div>');
        });
    }
    $('#riwayat-apply').on('click', () => { riwayatPage = 1; loadRiwayat(); });
    $('#riwayat-reset').on('click', function() {
        $('#riwayat-modul').val('');
        $('#riwayat-aksi').val('');
        $('#riwayat-dari').val('');
        $('#riwayat-sampai').val('');
        riwayatPage = 1;
        loadRiwayat();
    });
});
