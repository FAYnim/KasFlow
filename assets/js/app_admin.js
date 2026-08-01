$(function () {
    const $tabs = $('[data-tab-content]');
    const activate = (n) => { $tabs.addClass('hidden'); $(`[data-tab-content="${n}"]`).removeClass('hidden'); loaders[n](); };
    const loaders = { dashboard: lDash, siswa: lSiswa, kas: lKas, jurnal: lJurnal, denda: lDenda, bank: lBank, ekspor: lEkspor };
    $('[data-tab]').on('click', function () { activate($(this).data('tab')); });
    activate('dashboard');

    const fmt = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');
    const bulanList = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    const now = new Date();
    $('#admin-bulan').html(bulanList.map(b => `<option ${b===bulanList[now.getMonth()]?'selected':''}>${b}</option>`).join(''));
    $('#admin-tahun').html([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option ${y===now.getFullYear()?'selected':''}>${y}</option>`).join(''));
    $('#admin-bulan, #admin-tahun').on('change', lKas);

    function lDash() {
        $.getJSON('api_public.php?action=get_summary', s => {
            $('#admin-summary').html([
                ['Total Kas', fmt(s.total_kas_terkumpul), 'bg-blue-500'],
                ['Cash on Hand', fmt(s.cash_on_hand), 'bg-emerald-500'],
                ['Cash in Bank', fmt(s.cash_in_bank), 'bg-indigo-500'],
                ['Denda Unpaid', fmt(s.total_denda_unpaid), 'bg-rose-500'],
            ].map(([t,v,c]) => `<div class="p-4 rounded shadow text-white ${c}"><div class="text-xs uppercase opacity-80">${t}</div><div class="text-xl font-bold">${v}</div></div>`).join(''));
        });
    }

    function lSiswa() {
        $.getJSON('api_admin.php?action=list_siswa', rows => {
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2">NIS</th><th class="p-2">Nama</th><th class="p-2">Aksi</th></tr></thead><tbody>';
            h += rows.map(s =>
                `<tr class="border-t"><td class="p-2">${s.nis||''}</td><td class="p-2">${s.nama}</td>`
                + `<td class="p-2"><button class="text-rose-600 del-s" data-id="${s.id}">Hapus</button></td></tr>`
            ).join('') + '</tbody></table>';
            $('#siswa-wrap').html(h);
        });
    }
    $(document).on('click', '.del-s', function () {
        if (!confirm('Hapus siswa ini (dan data kas/denda terkait)?')) return;
        $.post('api_admin.php?action=delete_siswa', { id: $(this).data('id') }, r => lSiswa(), 'json');
    });
    // Forms
    $('#form-siswa').on('submit', function (e) {
        e.preventDefault();
        $.post('api_admin.php?action=add_siswa', $(this).serialize(), r => {
            if (r.ok) { this.reset(); lSiswa(); } else alert(r.error);
        }, 'json');
    });

    function lKas() {
        const bulan = $('#admin-bulan').val(), tahun = $('#admin-tahun').val();
        $.getJSON('api_public.php', { action:'get_kas', bulan, tahun }, rows => {
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2 text-left">Nama</th>'
                + [1,2,3,4,5].map(i => `<th class="p-2">M${i}</th>`).join('') + '<th class="p-2 text-right">Total</th></tr></thead><tbody>';
            h += rows.map(r =>
                `<tr class="border-t"><td class="p-2">${r.nama}</td>`
                + [1,2,3,4,5].map(i => `<td class="p-2 text-center"><input type="checkbox" class="kas-cb" data-siswa="${r.id}" data-minggu="${i}" ${r['m'+i]?'checked':''}></td>`).join('')
                + `<td class="p-2 text-right total-cell" data-siswa="${r.id}">${fmt(r.total_bayar)}</td></tr>`
            ).join('') + '</tbody></table>';
            $('#kas-wrap').html(h);
        });
    }
    $(document).on('change', '.kas-cb', function () {
        const cb = $(this);
        $.post('api_admin.php?action=update_kas', {
            siswa_id: cb.data('siswa'), minggu: cb.data('minggu'),
            checked: cb.is(':checked')?1:0,
            bulan: $('#admin-bulan').val(), tahun: $('#admin-tahun').val()
        }, r => {
            $(`.total-cell[data-siswa="${cb.data('siswa')}"]`).text(fmt(r.total_bayar));
        }, 'json');
    });

    // Jurnal
    $('#btn-add-jurnal').on('click', () => openJurnalModal());
    $('#modal-close').on('click', () => $('#modal-jurnal').addClass('hidden').removeClass('flex'));
    function openJurnalModal(t) {
        $('#modal-jurnal').removeClass('hidden').addClass('flex');
        const f = $('#form-jurnal')[0];
        f.reset();
        if (t) { f.id.value = t.id; f.tanggal.value = t.tanggal; f.keterangan.value = t.keterangan; f.jenis.value = t.jenis; f.nominal.value = t.nominal; }
    }
    $('#form-jurnal').on('submit', function (e) {
        e.preventDefault();
        const id = this.id.value;
        const action = id ? 'update_jurnal' : 'add_jurnal';
        $.post('api_admin.php?action=' + action, $(this).serialize(), r => {
            if (r.ok) { $('#modal-jurnal').addClass('hidden').removeClass('flex'); lJurnal(); } else alert(r.error);
        }, 'json');
    });
    function lJurnal() {
        $.getJSON('api_public.php?action=get_jurnal', r => {
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2">Tanggal</th><th class="p-2">Ket</th><th class="p-2">Jenis</th><th class="p-2 text-right">Nominal</th><th class="p-2">Aksi</th></tr></thead><tbody>';
            h += r.transaksi.map(t =>
                `<tr class="border-t"><td class="p-2">${t.tanggal}</td><td class="p-2">${t.keterangan}</td>`
                + `<td class="p-2">${t.jenis}</td><td class="p-2 text-right">${fmt(t.nominal)}</td>`
                + `<td class="p-2"><button class="text-blue-600 mr-2 edit-j" data-id="${t.id}">Edit</button><button class="text-rose-600 del-j" data-id="${t.id}">Hapus</button></td></tr>`
            ).join('') + '</tbody></table>';
            $('#jurnal-wrap').html(h);
        });
    }
    $(document).on('click', '.edit-j', function () {
        const id = $(this).data('id');
        $.getJSON('api_public.php?action=get_jurnal', r => {
            const t = r.transaksi.find(x => x.id == id);
            if (t) openJurnalModal(t);
        });
    });
    $(document).on('click', '.del-j', function () {
        if (!confirm('Hapus?')) return;
        $.post('api_admin.php?action=delete_jurnal', { id: $(this).data('id') }, r => lJurnal(), 'json');
    });

    // Denda
    function lDenda() {
        $.getJSON('api_public.php?action=get_piutang', rows => {
            $.getJSON('api_admin.php?action=list_siswa', ss => {
                $('#denda-siswa').html(ss.map(s => `<option value="${s.id}">${s.nama}</option>`).join(''));
            });
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2">Tanggal</th><th class="p-2">Siswa</th><th class="p-2">Ket</th><th class="p-2 text-right">Jumlah</th><th class="p-2">Status</th><th class="p-2">Aksi</th></tr></thead><tbody>';
            h += rows.map(p =>
                `<tr class="border-t"><td class="p-2">${p.tanggal}</td><td class="p-2">${p.siswa_nama}</td><td class="p-2">${p.keterangan}</td>`
                + `<td class="p-2 text-right">${fmt(p.jumlah)}</td>`
                + `<td class="p-2">${p.status}</td>`
                + `<td class="p-2">${p.status==='belum_dibayar' ? `<button class="text-emerald-600 lunas-btn" data-id="${p.id}">Tandai Lunas</button>` : '-'}</td></tr>`
            ).join('') + '</tbody></table>';
            $('#denda-wrap').html(h);
        });
    }
    $('#form-denda').on('submit', function (e) {
        e.preventDefault();
        $.post('api_admin.php?action=add_piutang', $(this).serialize(), r => {
            if (r.ok) { this.reset(); lDenda(); } else alert(r.error);
        }, 'json');
    });
    $(document).on('click', '.lunas-btn', function () {
        $.post('api_admin.php?action=update_piutang_status', { id: $(this).data('id'), status: 'sudah_dibayar' }, r => lDenda(), 'json');
    });

    // Bank
    function lBank() {
        $.getJSON('api_public.php?action=get_bank', rows => {
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2">Tanggal</th><th class="p-2">Ket</th><th class="p-2">Jenis</th><th class="p-2 text-right">Jumlah</th><th class="p-2">Aksi</th></tr></thead><tbody>';
            h += rows.map(b =>
                `<tr class="border-t"><td class="p-2">${b.tanggal}</td><td class="p-2">${b.keterangan}</td>`
                + `<td class="p-2">${b.jenis}</td><td class="p-2 text-right">${fmt(b.jumlah)}</td>`
                + `<td class="p-2"><button class="text-rose-600 del-b" data-id="${b.id}">Hapus</button></td></tr>`
            ).join('') + '</tbody></table>';
            $('#bank-wrap').html(h);
        });
    }
    $('#form-bank').on('submit', function (e) {
        e.preventDefault();
        $.post('api_admin.php?action=add_bank', $(this).serialize(), r => {
            if (r.ok) { this.reset(); lBank(); } else alert(r.error);
        }, 'json');
    });
    $(document).on('click', '.del-b', function () {
        if (!confirm('Hapus?')) return;
        $.post('api_admin.php?action=delete_bank', { id: $(this).data('id') }, r => lBank(), 'json');
    });

    // Ekspor
    function lEkspor() {
        const dari = $('#form-ekspor [name=dari]').val();
        const sampai = $('#form-ekspor [name=sampai]').val();
        $.getJSON('api_public.php?action=get_jurnal', r => {
            const rows = r.transaksi.filter(t => (!dari || t.tanggal >= dari) && (!sampai || t.tanggal <= sampai));
            let h = '<table class="w-full text-sm"><thead class="bg-slate-100"><tr><th class="p-2">Tanggal</th><th class="p-2">Ket</th><th class="p-2">Jenis</th><th class="p-2 text-right">Nominal</th></tr></thead><tbody>';
            h += rows.map(t => `<tr class="border-t"><td class="p-2">${t.tanggal}</td><td class="p-2">${t.keterangan}</td><td class="p-2">${t.jenis}</td><td class="p-2 text-right">${fmt(t.nominal)}</td></tr>`).join('') + '</tbody></table>';
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
        const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'laporan.csv'; a.click();
    });
    $('#btn-pdf').on('click', () => window.print());
});
