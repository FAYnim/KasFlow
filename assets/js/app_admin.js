$(function () {
    const $tabs = $('[data-tab-content]');
    const $navItems = $('[data-tab]');

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
        denda: lDenda, 
        bank: lBank, 
        ekspor: lEkspor 
    };

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
            const cards = [
                ['Total Kas', fmt(s.total_kas_terkumpul), 'text-[#828fff]', '⚡'],
                ['Cash on Hand', fmt(s.cash_on_hand), 'text-[#4ade80]', '💵'],
                ['Cash in Bank', fmt(s.cash_in_bank), 'text-[#60a5fa]', '🏦'],
                ['Denda Unpaid', fmt(s.total_denda_unpaid), 'text-[#f87171]', '⚠️'],
            ];
            $('#admin-summary').html(cards.map(([t, v, colorClass, icon]) =>
                `<div class="card-linear">
                    <div class="flex items-center justify-between mb-2">
                        <span class="eyebrow">${t}</span>
                        <span class="text-sm">${icon}</span>
                    </div>
                    <div class="text-2xl font-bold font-mono-num ${colorClass}">${v}</div>
                </div>`
            ).join(''));
        });
    }

    function lSiswa() {
        $.getJSON('api_admin.php?action=list_siswa', rows => {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">NIS</th>
                        <th>Nama Siswa</th>
                        <th class="w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="3" class="text-center py-6 text-[#8a8f98]">Belum ada data siswa.</td></tr>`;
            } else {
                h += rows.map(s =>
                    `<tr>
                        <td class="font-mono text-xs text-[#8a8f98]">${s.nis||'-'}</td>
                        <td class="font-medium text-[#f7f8f8]">${s.nama}</td>
                        <td class="text-right">
                            <button class="btn-danger del-s text-xs" data-id="${s.id}">Hapus</button>
                        </td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
            $('#siswa-wrap').html(h);
        });
    }

    $(document).on('click', '.del-s', function () {
        if (!confirm('Hapus siswa ini beserta seluruh data kas dan denda terkait?')) return;
        $.post('api_admin.php?action=delete_siswa', { id: $(this).data('id') }, r => lSiswa(), 'json');
    });

    $('#form-siswa').on('submit', function (e) {
        e.preventDefault();
        $.post('api_admin.php?action=add_siswa', $(this).serialize(), r => {
            if (r.ok) { this.reset(); lSiswa(); } else alert(r.error);
        }, 'json');
    });

    function lKas() {
        const bulan = $('#admin-bulan').val(), tahun = $('#admin-tahun').val();
        $.getJSON('api_public.php', { action:'get_kas', bulan, tahun }, rows => {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th>Nama Siswa</th>
                        ${[1,2,3,4,5].map(i => `<th class="text-center w-16">M${i}</th>`).join('')}
                        <th class="text-right w-36">Total Bayar</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="7" class="text-center py-6 text-[#8a8f98]">Tidak ada data kas siswa.</td></tr>`;
            } else {
                h += rows.map(r =>
                    `<tr>
                        <td class="font-medium text-[#f7f8f8]">${r.nama}</td>
                        ${[1,2,3,4,5].map(i => 
                            `<td class="text-center">
                                <input type="checkbox" class="kas-cb" data-siswa="${r.id}" data-minggu="${i}" ${r['m'+i]?'checked':''}>
                            </td>`
                        ).join('')}
                        <td class="text-right font-mono-num font-medium text-[#f7f8f8] total-cell" data-siswa="${r.id}">${fmt(r.total_bayar)}</td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
            $('#kas-wrap').html(h);
        });
    }

    $(document).on('change', '.kas-cb', function () {
        const cb = $(this);
        $.post('api_admin.php?action=update_kas', {
            siswa_id: cb.data('siswa'), 
            minggu: cb.data('minggu'),
            checked: cb.is(':checked')?1:0,
            bulan: $('#admin-bulan').val(), 
            tahun: $('#admin-tahun').val()
        }, r => {
            $(`.total-cell[data-siswa="${cb.data('siswa')}"]`).text(fmt(r.total_bayar));
        }, 'json');
    });

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
        $.post('api_admin.php?action=' + action, $(this).serialize(), r => {
            if (r.ok) { 
                $('#modal-jurnal').addClass('hidden'); 
                lJurnal(); 
            } else {
                alert(r.error);
            }
        }, 'json');
    });

    function lJurnal() {
        $.getJSON('api_public.php?action=get_jurnal', r => {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Tanggal</th>
                        <th>Keterangan</th>
                        <th class="w-28">Jenis</th>
                        <th class="text-right w-36">Nominal</th>
                        <th class="w-32 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (r.transaksi.length === 0) {
                h += `<tr><td colspan="5" class="text-center py-6 text-[#8a8f98]">Belum ada jurnal transaksi.</td></tr>`;
            } else {
                h += r.transaksi.map(t =>
                    `<tr>
                        <td class="font-mono text-xs text-[#8a8f98]">${t.tanggal}</td>
                        <td class="text-[#f7f8f8]">${t.keterangan}</td>
                        <td>
                            <span class="badge-status ${t.jenis==='masuk'?'badge-success':'badge-danger'}">
                                ${t.jenis==='masuk' ? '▲ Masuk' : '▼ Keluar'}
                            </span>
                        </td>
                        <td class="text-right font-mono-num font-medium text-[#f7f8f8]">${fmt(t.nominal)}</td>
                        <td class="text-right space-x-1">
                            <button class="btn-secondary text-xs px-2 py-1 edit-j" data-id="${t.id}">Edit</button>
                            <button class="btn-danger text-xs px-2 py-1 del-j" data-id="${t.id}">Hapus</button>
                        </td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
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
        if (!confirm('Hapus transaksi ini?')) return;
        $.post('api_admin.php?action=delete_jurnal', { id: $(this).data('id') }, r => lJurnal(), 'json');
    });

    // Denda
    function lDenda() {
        $.getJSON('api_public.php?action=get_piutang', rows => {
            $.getJSON('api_admin.php?action=list_siswa', ss => {
                $('#denda-siswa').html(ss.map(s => `<option value="${s.id}">${s.nama}</option>`).join(''));
            });

            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Tanggal</th>
                        <th>Nama Siswa</th>
                        <th>Keterangan</th>
                        <th class="text-right w-36">Jumlah</th>
                        <th class="w-36">Status</th>
                        <th class="w-36 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="6" class="text-center py-6 text-[#8a8f98]">Tidak ada tagihan denda.</td></tr>`;
            } else {
                h += rows.map(p =>
                    `<tr>
                        <td class="font-mono text-xs text-[#8a8f98]">${p.tanggal}</td>
                        <td class="font-medium text-[#f7f8f8]">${p.siswa_nama}</td>
                        <td class="text-[#d0d6e0]">${p.keterangan}</td>
                        <td class="text-right font-mono-num font-medium text-[#f7f8f8]">${fmt(p.jumlah)}</td>
                        <td>
                            <span class="badge-status ${p.status==='sudah_dibayar'?'badge-success':'badge-danger'}">
                                ${p.status==='sudah_dibayar' ? '✓ Sudah Dibayar' : '✕ Belum Dibayar'}
                            </span>
                        </td>
                        <td class="text-right">
                            ${p.status==='belum_dibayar' ? `<button class="btn-secondary text-xs px-2 py-1 lunas-btn" data-id="${p.id}">Tandai Lunas</button>` : '<span class="text-xs text-[#62666d]">Lunas</span>'}
                        </td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
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
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Tanggal</th>
                        <th>Keterangan Mutasi</th>
                        <th class="w-28">Jenis</th>
                        <th class="text-right w-36">Jumlah</th>
                        <th class="w-28 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>`;
            if (rows.length === 0) {
                h += `<tr><td colspan="5" class="text-center py-6 text-[#8a8f98]">Belum ada mutasi bank.</td></tr>`;
            } else {
                h += rows.map(b =>
                    `<tr>
                        <td class="font-mono text-xs text-[#8a8f98]">${b.tanggal}</td>
                        <td class="text-[#f7f8f8]">${b.keterangan}</td>
                        <td>
                            <span class="badge-status ${b.jenis==='setor'?'badge-success':'badge-neutral'}">
                                ${b.jenis==='setor' ? '↗ Setor' : '↘ Tarik'}
                            </span>
                        </td>
                        <td class="text-right font-mono-num font-medium text-[#f7f8f8]">${fmt(b.jumlah)}</td>
                        <td class="text-right">
                            <button class="btn-danger text-xs px-2 py-1 del-b" data-id="${b.id}">Hapus</button>
                        </td>
                    </tr>`
                ).join('');
            }
            h += '</tbody></table>';
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
        if (!confirm('Hapus data mutasi ini?')) return;
        $.post('api_admin.php?action=delete_bank', { id: $(this).data('id') }, r => lBank(), 'json');
    });

    // Ekspor
    function lEkspor() {
        const dari = $('#form-ekspor [name=dari]').val();
        const sampai = $('#form-ekspor [name=sampai]').val();
        $.getJSON('api_public.php?action=get_jurnal', r => {
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
                h += `<tr><td colspan="4" class="text-center py-6 text-[#8a8f98]">Tidak ada data jurnal untuk rentang tanggal ini.</td></tr>`;
            } else {
                h += rows.map(t => 
                    `<tr>
                        <td class="font-mono text-xs text-[#8a8f98]">${t.tanggal}</td>
                        <td class="text-[#f7f8f8]">${t.keterangan}</td>
                        <td>
                            <span class="badge-status ${t.jenis==='masuk'?'badge-success':'badge-danger'}">
                                ${t.jenis==='masuk' ? '▲ Masuk' : '▼ Keluar'}
                            </span>
                        </td>
                        <td class="text-right font-mono-num font-medium text-[#f7f8f8]">${fmt(t.nominal)}</td>
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
});
