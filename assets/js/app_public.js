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
    // jurnal/piutang/bank are placeholders until next task
    function loadJurnal() {}
    function loadPiutang() {}
    function loadBank() {}

    activate('dashboard');
});
