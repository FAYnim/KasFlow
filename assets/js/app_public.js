$(function () {
    const bulanList = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const $tabs = $('[data-tab-content]');
    const activate = (name) => {
        $tabs.addClass('hidden');
        $('[data-tab-content="' + name + '"]').removeClass('hidden');
        loaders[name]();
    };
    const loaders = {
        dashboard: loadDashboard,
        kas: loadKas,
        jurnal: loadJurnal,
        piutang: loadPiutang,
        bank: loadBank,
    };

    $('#btn-hamburger').on('click', () => $('#sidebar').toggleClass('-translate-x-full'));
    $('[data-tab]').on('click', function () { activate($(this).data('tab')); $('#sidebar').addClass('-translate-x-full'); });

    const now = new Date();
    $('#kas-bulan').html(bulanList.map(b => `<option ${b===bulanList[now.getMonth()]?'selected':''}>${b}</option>`).join(''));
    $('#kas-tahun').html([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option ${y===now.getFullYear()?'selected':''}>${y}</option>`).join(''));
    $('#kas-bulan, #kas-tahun').on('change', loadKas);
    $('#kas-search').on('input', filterKas);

    const fmt = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');
    function loadDashboard() {
        $.getJSON('api_public.php?action=get_summary', function (s) {
            const cards = [
                ['Total Kas Terkumpul', fmt(s.total_kas_terkumpul), 'bg-blue-500'],
                ['Cash on Hand', fmt(s.cash_on_hand), 'bg-emerald-500'],
                ['Cash in Bank', fmt(s.cash_in_bank), 'bg-indigo-500'],
                ['Denda Unpaid', fmt(s.total_denda_unpaid), 'bg-rose-500'],
            ];
            $('#summary-cards').html(cards.map(([t,v,c]) =>
                `<div class="p-4 rounded shadow text-white ${c}"><div class="text-xs uppercase opacity-80">${t}</div><div class="text-xl font-bold mt-1">${v}</div></div>`
            ).join(''));
        });
    }
    let kasData = [];
    function loadKas() {
        const bulan = $('#kas-bulan').val(), tahun = $('#kas-tahun').val();
        $.getJSON('api_public.php', { action:'get_kas', bulan, tahun }, function (rows) {
            kasData = rows; renderKas();
        });
    }
    function renderKas() {
        const q = ($('#kas-search').val() || '').toLowerCase();
        const rows = kasData.filter(r => r.nama.toLowerCase().includes(q));
        let html = '<thead class="bg-slate-100"><tr><th class="p-2 text-left">NIS</th><th class="p-2 text-left">Nama</th>'
            + [1,2,3,4,5].map(i => `<th class="p-2">M${i}</th>`).join('')
            + '<th class="p-2 text-right">Total</th></tr></thead><tbody>';
        html += rows.map(r =>
            `<tr class="border-t"><td class="p-2">${r.nis||''}</td><td class="p-2">${r.nama}</td>`
            + [r.m1,r.m2,r.m3,r.m4,r.m5].map(v => `<td class="p-2 text-center">${v ? '✅':'❌'}</td>`).join('')
            + `<td class="p-2 text-right">${fmt(r.total_bayar)}</td></tr>`
        ).join('');
        html += '</tbody>';
        $('#kas-table').html(html);
    }
    function filterKas() { renderKas(); }
    let lineChart, donutChart;
    function loadJurnal() {
        $.getJSON('api_public.php?action=get_jurnal', function (r) {
            const labels = r.line_chart.map(x => x.tanggal);
            const data   = r.line_chart.map(x => x.saldo);
            if (lineChart) lineChart.destroy();
            lineChart = new Chart(document.getElementById('chart-line'), {
                type: 'line',
                data: { labels, datasets: [{ label: 'Saldo', data, borderColor: '#2563eb', backgroundColor:'rgba(37,99,235,.1)', fill: true, tension:.3 }] },
                options: { responsive: true }
            });
            if (donutChart) donutChart.destroy();
            donutChart = new Chart(document.getElementById('chart-donut'), {
                type: 'doughnut',
                data: { labels: ['Masuk','Keluar'], datasets: [{ data: [r.donut.masuk, r.donut.keluar], backgroundColor:['#10b981','#ef4444'] }] }
            });
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2">Tanggal</th><th class="p-2">Keterangan</th><th class="p-2">Jenis</th><th class="p-2 text-right">Nominal</th></tr></thead><tbody>';
            h += r.transaksi.map(t =>
                `<tr class="border-t"><td class="p-2">${t.tanggal}</td><td class="p-2">${t.keterangan}</td>`
                + `<td class="p-2"><span class="px-2 py-1 rounded text-xs ${t.jenis==='masuk'?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700'}">${t.jenis}</span></td>`
                + `<td class="p-2 text-right">${fmt(t.nominal)}</td></tr>`
            ).join('') + '</tbody></table>';
            $('#jurnal-table-wrap').html(h);
        });
    }
    function loadPiutang() {
        $.getJSON('api_public.php?action=get_piutang', function (rows) {
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2">Tanggal</th><th class="p-2">Siswa</th><th class="p-2">Keterangan</th><th class="p-2 text-right">Jumlah</th><th class="p-2">Status</th></tr></thead><tbody>';
            h += rows.map(p =>
                `<tr class="border-t"><td class="p-2">${p.tanggal}</td><td class="p-2">${p.siswa_nama}</td><td class="p-2">${p.keterangan}</td>`
                + `<td class="p-2 text-right">${fmt(p.jumlah)}</td>`
                + `<td class="p-2"><span class="px-2 py-1 rounded text-xs ${p.status==='sudah_dibayar'?'bg-emerald-100 text-emerald-700':'bg-rose-100 text-rose-700'}">${p.status}</span></td></tr>`
            ).join('') + '</tbody></table>';
            $('#piutang-wrap').html(h);
        });
    }
    function loadBank() {
        $.getJSON('api_public.php?action=get_bank', function (rows) {
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2">Tanggal</th><th class="p-2">Keterangan</th><th class="p-2">Jenis</th><th class="p-2 text-right">Jumlah</th></tr></thead><tbody>';
            h += rows.map(b =>
                `<tr class="border-t"><td class="p-2">${b.tanggal}</td><td class="p-2">${b.keterangan}</td>`
                + `<td class="p-2">${b.jenis}</td><td class="p-2 text-right">${fmt(b.jumlah)}</td></tr>`
            ).join('') + '</tbody></table>';
            $('#bank-wrap').html(h);
        });
    }

    activate('dashboard');
});
