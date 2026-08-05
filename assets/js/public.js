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
        jurnal: loadJurnal,
        kasbon: loadKasbon,
        bms: loadBms,
        riwayat: loadRiwayat,
    };

    $('#btn-hamburger').on('click', () => $('#sidebar').toggleClass('-translate-x-full'));
    $('[data-tab]').on('click', function () { 
        activate($(this).data('tab')); 
        $('#sidebar').addClass('-translate-x-full'); 
    });

    const now = new Date();
    $('#kas-bulan').html(bulanList.map(b => `<option ${b===bulanList[now.getMonth()]?'selected':''}>${b}</option>`).join(''));
    $('#kas-tahun').html([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option ${y===now.getFullYear()?'selected':''}>${y}</option>`).join(''));
    $('#kas-bulan, #kas-tahun').on('change', loadKas);
    $('#kas-search').on('input', filterKas);

    $('#jurnal-bulan').html([''].concat(bulanList).map(b => `<option value="${b}">${b||'Semua'}</option>`).join(''));
    $('#jurnal-tahun').html([''].concat([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1]).map(y => `<option value="${y}">${y||'Semua'}</option>`).join(''));
    $('#jurnal-bulan, #jurnal-tahun').on('change', loadJurnal);
    $('#jurnal-reset').on('click', () => {
        $('#jurnal-bulan').val('');
        $('#jurnal-tahun').val('');
        loadJurnal();
    });

    $('#kasbon-bulan').html(bulanList.map(b => `<option ${b===bulanList[now.getMonth()]?'selected':''}>${b}</option>`).join(''));
    $('#kasbon-tahun').html([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option ${y===now.getFullYear()?'selected':''}>${y}</option>`).join(''));
    $('#kasbon-bulan, #kasbon-tahun').on('change', loadKasbon);

    $('#bms-apply').on('click', loadBms);
    $('#bms-reset').on('click', () => {
        $('#bms-dari').val('');
        $('#bms-sampai').val('');
        loadBms();
    });

    const fmt = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');

    let lastChartData = null;
    let lineChart, donutChart;
    function loadDashboard() {
        $.getJSON('src/api/public.php?action=get_summary', function (s) {
            const cards = [
                ['Total Kas', fmt(s.total_kas_terkumpul), 'text-[var(--primary)]', '<i class="fa-solid fa-vault text-sm"></i>'],
                ['Saldo BMS', fmt(s.saldo_bms), 'text-[var(--semantic-info)]', '<i class="fa-solid fa-building-columns text-sm"></i>'],
                ['Total Kasbon', fmt(s.total_kasbon), 'text-amber-400', '<i class="fa-solid fa-handshake text-sm"></i>'],
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

    function renderCharts(r) {
        const labels = r.line_chart.map(x => x.tanggal);
        const data   = r.line_chart.map(x => x.saldo);

        const isDark = $('html').attr('data-theme') === 'dark';
        const gridColor = isDark ? '#23252a' : '#e2e8f0';
        const textColor = isDark ? '#8a8f98' : '#64748b';
        const donutBorderColor = isDark ? '#0f1011' : '#ffffff';
        const primaryColor = isDark ? '#5e6ad2' : '#4f46e5';

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
                    backgroundColor: isDark ? 'rgba(94, 106, 210, 0.12)' : 'rgba(79, 70, 229, 0.12)',
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
                    backgroundColor: [primaryColor, isDark ? '#e5484d' : '#dc2626'],
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
        const rows = kasData.filter(r => r.nama.toLowerCase().includes(q));
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
                    <td class="font-mono text-xs text-subtle">${r.absen||'-'}</td>
                    <td class="font-medium text-ink">${r.nama}</td>
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

    function loadJurnal() {
        const params = { action: 'get_jurnal' };
        const b = $('#jurnal-bulan').val();
        const t = $('#jurnal-tahun').val();
        if (b) params.bulan = b;
        if (t) params.tahun = t;
        $.getJSON('src/api/public.php', params, function (r) {
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
            if (r.transaksi.length === 0) {
                h += `<tr><td colspan="4" class="text-center py-6 text-subtle">Belum ada transaksi jurnal.</td></tr>`;
            } else {
                h += r.transaksi.map(t =>
                    `<tr>
                        <td class="font-mono text-xs text-subtle">${t.tanggal}</td>
                        <td class="text-ink">${t.keterangan}</td>
                        <td>
                            <span class="badge-status ${t.jenis==='masuk'?'badge-success':'badge-danger'} font-medium">
                                <i class="fa-solid ${t.jenis==='masuk' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'} text-[10px]"></i>
                                <span>${t.jenis==='masuk' ? 'Masuk' : 'Keluar'}</span>
                            </span>
                        </td>
                        <td class="text-right font-mono-num font-medium text-ink">${fmt(t.nominal)}</td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
            $('#jurnal-table-wrap').html(h);
        });
    }

    function loadKasbon() {
        const bulan = $('#kasbon-bulan').val();
        const tahun = $('#kasbon-tahun').val();
        $.getJSON('src/api/public.php', { action: 'get_kasbon', bulan, tahun }, function (data) {
            const tbody = $('#kasbon-table-body');
            if (!data.length) {
                tbody.html('<tr><td colspan="6" class="text-center py-6 text-subtle">Tidak ada data kasbon.</td></tr>');
                return;
            }
            tbody.html(data.map((r, i) => {
                const badge = r.status === 'lunas'
                    ? '<span class="badge-status badge-success font-medium"><i class="fa-solid fa-circle-check text-[10px]"></i><span>Lunas</span></span>'
                    : '<span class="badge-status badge-warning font-medium"><i class="fa-solid fa-clock text-[10px]"></i><span>Belum Lunas</span></span>';
                return `<tr>
                    <td class="text-center text-subtle">${i + 1}</td>
                    <td class="font-mono text-xs text-subtle">${r.tanggal}</td>
                    <td class="text-ink">${r.nama}</td>
                    <td class="text-subtle">${r.keterangan}</td>
                    <td class="text-right font-mono-num font-medium text-ink">${fmt(r.jumlah)}</td>
                    <td>${badge}</td>
                </tr>`;
            }).join(''));
        }).fail(function() {
            $('#kasbon-table-body').html('<tr><td colspan="6" class="text-center py-6 text-subtle">Gagal memuat data.</td></tr>');
        });
    }

    function loadBms() {
        const params = { action: 'get_bms' };
        const dari = $('#bms-dari').val();
        const sampai = $('#bms-sampai').val();
        if (dari) params.dari = dari;
        if (sampai) params.sampai = sampai;

        $.getJSON('src/api/public.php', params, function (data) {
            const totals = data.totals || { setor: 0, tarik: 0, saldo: 0 };
            const cards = [
                ['Total Setor',  fmt(totals.setor), 'text-[var(--semantic-success)]', '<i class="fa-solid fa-arrow-trend-up text-sm"></i>'],
                ['Total Tarik',  fmt(totals.tarik), 'text-subtle',                     '<i class="fa-solid fa-arrow-trend-down text-sm"></i>'],
                ['Saldo Akhir',  fmt(totals.saldo), 'text-[var(--primary)]',           '<i class="fa-solid fa-scale-balanced text-sm"></i>'],
            ];
            $('#bms-summary-cards').html(cards.map(([t, v, colorClass, icon]) =>
                `<div class="card-linear">
                    <div class="flex items-center justify-between mb-2">
                        <span class="eyebrow">${t}</span>
                        <span class="text-subtle">${icon}</span>
                    </div>
                    <div class="text-2xl font-bold font-mono-num ${colorClass}">${v}</div>
                </div>`
            ).join(''));

            const rows = data.rows || [];
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Tanggal</th>
                        <th>Keterangan</th>
                        <th class="w-28">Jenis</th>
                        <th class="text-right w-36">Jumlah</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="4" class="text-center py-6 text-subtle">Belum ada data kas BMS.</td></tr>`;
            } else {
                h += rows.map(b =>
                    `<tr>
                        <td class="font-mono text-xs text-subtle">${b.tanggal}</td>
                        <td class="text-ink">${b.keterangan}</td>
                        <td>
                            <span class="badge-status ${b.jenis==='setor'?'badge-success':'badge-neutral'} font-medium">
                                <i class="fa-solid ${b.jenis==='setor' ? 'fa-arrow-right-to-bracket' : 'fa-arrow-right-from-bracket'} text-[10px]"></i>
                                <span>${b.jenis==='setor' ? 'Setor' : 'Tarik'}</span>
                            </span>
                        </td>
                        <td class="text-right font-mono-num font-medium text-ink">${fmt(b.jumlah)}</td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
            $('#bms-wrap').html(h);
        }).fail(function () {
            $('#bms-wrap').html('<div class="text-center py-6 text-subtle">Gagal memuat data.</div>');
        });
    }

    activate('dashboard');

    // ── Riwayat helpers & loader ──────────────────────────────────────────
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
    function loadRiwayat() {
        const params = new URLSearchParams({action: 'get_riwayat'});
        const aksi = $('#riwayat-aksi').val();
        const dari = $('#riwayat-dari').val();
        const sampai = $('#riwayat-sampai').val();
        if (aksi) params.set('aksi', aksi);
        if (dari) params.set('dari', dari);
        if (sampai) params.set('sampai', sampai);
        $('#riwayat-wrap').html('<div class="text-center py-6 text-subtle">Memuat…</div>');
        $.getJSON('src/api/public.php?' + params.toString(), function(rows) {
            if (!rows || !rows.length) {
                $('#riwayat-wrap').html('<div class="text-center py-6 text-subtle">Belum ada riwayat.</div>');
                return;
            }
            let html = '<table class="table-linear w-full"><thead><tr><th>Waktu</th><th>Modul</th><th>Aksi</th><th>Ringkasan</th><th>Oleh</th></tr></thead><tbody>';
            rows.forEach(r => {
                html += '<tr>'
                    + '<td class="text-xs text-subtle whitespace-nowrap">' + formatDateTime(r.created_at) + '</td>'
                    + '<td><span class="badge-neutral">' + escapeHtml(r.modul) + '</span></td>'
                    + '<td><span class="badge-' + escapeHtml(r.aksi) + '">' + escapeHtml(r.aksi) + '</span></td>'
                    + '<td title="' + escapeHtml(r.ringkasan) + '">' + escapeHtml(truncate(r.ringkasan, 80)) + '</td>'
                    + '<td class="text-sm">' + escapeHtml(r.admin_nama || r.admin_username || '-') + '</td>'
                    + '</tr>';
            });
            html += '</tbody></table>';
            $('#riwayat-wrap').html(html);
        }).fail(function() {
            $('#riwayat-wrap').html('<div class="text-center py-6 text-subtle">Gagal memuat data.</div>');
        });
    }
    $('#riwayat-apply').on('click', loadRiwayat);
    $('#riwayat-reset').on('click', function() {
        $('#riwayat-aksi').val('');
        $('#riwayat-dari').val('');
        $('#riwayat-sampai').val('');
        loadRiwayat();
    });
});
