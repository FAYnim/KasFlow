$(function () {
    const bulanList = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const $tabs = $('[data-tab-content]');
    const $navItems = $('[data-tab]');

    // Theme Switcher Management
    function updateThemeUI(theme) {
        if (theme === 'dark') {
            $('#theme-toggle-icon').attr('class', 'fa-solid fa-moon text-indigo-400');
            $('#theme-toggle-text').text('Dark');
        } else {
            $('#theme-toggle-icon').attr('class', 'fa-solid fa-sun text-amber-500');
            $('#theme-toggle-text').text('Light');
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
        
        // Re-render chart if active in Jurnal section
        if (lineChart || donutChart) {
            loadJurnal();
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
        piutang: loadPiutang,
        bank: loadBank,
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

    const fmt = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');

    function loadDashboard() {
        $.getJSON('api_public.php?action=get_summary', function (s) {
            const cards = [
                ['Total Kas Terkumpul', fmt(s.total_kas_terkumpul), 'text-[var(--primary)]', '<i class="fa-solid fa-vault text-sm"></i>'],
                ['Cash on Hand', fmt(s.cash_on_hand), 'text-[var(--semantic-success)]', '<i class="fa-solid fa-hand-holding-dollar text-sm"></i>'],
                ['Cash in Bank', fmt(s.cash_in_bank), 'text-blue-500', '<i class="fa-solid fa-building-columns text-sm"></i>'],
                ['Denda Unpaid', fmt(s.total_denda_unpaid), 'text-[var(--semantic-danger)]', '<i class="fa-solid fa-circle-exclamation text-sm"></i>'],
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
    }

    let kasData = [];
    function loadKas() {
        const bulan = $('#kas-bulan').val(), tahun = $('#kas-tahun').val();
        $.getJSON('api_public.php', { action:'get_kas', bulan, tahun }, function (rows) {
            kasData = rows; 
            renderKas();
        });
    }

    function renderKas() {
        const q = ($('#kas-search').val() || '').toLowerCase();
        const rows = kasData.filter(r => r.nama.toLowerCase().includes(q));
        let html = `<thead>
            <tr>
                <th class="w-24">NIS</th>
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
                    <td class="font-mono text-xs text-subtle">${r.nis||'-'}</td>
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

    let lineChart, donutChart;
    function loadJurnal() {
        $.getJSON('api_public.php?action=get_jurnal', function (r) {
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
                    plugins: {
                        legend: { display: false }
                    },
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
                    plugins: {
                        legend: { position: 'bottom', labels: { color: textColor, padding: 16 } }
                    }
                }
            });

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

    function loadPiutang() {
        $.getJSON('api_public.php?action=get_piutang', function (rows) {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Tanggal</th>
                        <th>Siswa</th>
                        <th>Keterangan</th>
                        <th class="text-right w-36">Jumlah</th>
                        <th class="w-36">Status</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="5" class="text-center py-6 text-subtle">Tidak ada piutang/denda recorded.</td></tr>`;
            } else {
                h += rows.map(p =>
                    `<tr>
                        <td class="font-mono text-xs text-subtle">${p.tanggal}</td>
                        <td class="font-medium text-ink">${p.siswa_nama}</td>
                        <td class="text-muted">${p.keterangan}</td>
                        <td class="text-right font-mono-num font-medium text-ink">${fmt(p.jumlah)}</td>
                        <td>
                            <span class="badge-status ${p.status==='sudah_dibayar'?'badge-success':'badge-danger'} font-medium">
                                <i class="fa-solid ${p.status==='sudah_dibayar' ? 'fa-circle-check' : 'fa-clock'} text-[10px]"></i>
                                <span>${p.status==='sudah_dibayar' ? 'Sudah Dibayar' : 'Belum Dibayar'}</span>
                            </span>
                        </td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
            $('#piutang-wrap').html(h);
        });
    }

    function loadBank() {
        $.getJSON('api_public.php?action=get_bank', function (rows) {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Tanggal</th>
                        <th>Keterangan Transaksi</th>
                        <th class="w-28">Jenis Mutasi</th>
                        <th class="text-right w-36">Jumlah</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="4" class="text-center py-6 text-subtle">Belum ada mutasi bank.</td></tr>`;
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
            $('#bank-wrap').html(h);
        });
    }

    activate('dashboard');
});
