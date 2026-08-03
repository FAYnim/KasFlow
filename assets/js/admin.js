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
        kasbon: lKasbon,
        ekspor: lEkspor,
        bms: lBms,
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
    $('#jurnal-bulan, #jurnal-tahun').on('change', lJurnal);
    $('#jurnal-reset').on('click', () => {
        $('#jurnal-bulan').val('');
        $('#jurnal-tahun').val('');
        lJurnal();
    });

    // Kasbon
    $('#admin-kasbon-bulan').html(bulanList.map(b => `<option ${b===bulanList[now.getMonth()]?'selected':''}>${b}</option>`).join(''));
    $('#admin-kasbon-tahun').html([now.getFullYear()-1, now.getFullYear(), now.getFullYear()+1].map(y => `<option ${y===now.getFullYear()?'selected':''}>${y}</option>`).join(''));
    $('#admin-kasbon-bulan, #admin-kasbon-tahun').on('change', lKasbon);

    function lKasbon() {
        const bulan = $('#admin-kasbon-bulan').val();
        const tahun = $('#admin-kasbon-tahun').val();
        $.getJSON('../../src/api/public.php', { action: 'get_kasbon', bulan, tahun }, function(data) {
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
                h += `<tr><td colspan="7" class="text-center py-6 text-[#8a8f98]">Tidak ada data kasbon.</td></tr>`;
            } else {
                h += data.map(function(r, i) {
                    const badge = r.status === 'lunas'
                        ? '<span class="badge-status badge-success font-medium"><i class="fa-solid fa-circle-check text-[10px]"></i> <span>Lunas</span></span>'
                        : '<span class="badge-status badge-warning font-medium"><i class="fa-solid fa-clock text-[10px]"></i> <span>Belum Lunas</span></span>';
                    const toggleBtn = r.status === 'lunas'
                        ? '<button class="text-yellow-400 hover:text-yellow-300 text-xs toggle-kasbon" data-id="' + r.id + '" data-status="belum_lunas" title="Tandai Belum Lunas"><i class="fa-solid fa-rotate-left"></i></button>'
                        : '<button class="text-green-400 hover:text-green-300 text-xs toggle-kasbon" data-id="' + r.id + '" data-status="lunas" title="Tandai Lunas"><i class="fa-solid fa-circle-check"></i></button>';
                    return '<tr>' +
                        '<td class="font-mono text-xs text-[#8a8f98]">' + (i + 1) + '</td>' +
                        '<td class="font-mono text-xs text-[#8a8f98]">' + r.tanggal + '</td>' +
                        '<td class="font-medium text-[#f7f8f8]">' + r.nama + '</td>' +
                        '<td class="text-[#8a8f98]">' + r.keterangan + '</td>' +
                        '<td class="text-right font-mono-num font-medium text-[#f7f8f8]">' + fmt(r.jumlah) + '</td>' +
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
            $('#kasbon-wrap').html('<div class="text-center py-6 text-[#8a8f98]">Gagal memuat data kasbon.</div>');
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
        $.post('../../src/api/admin.php?action=' + action, { id: id }, function() {
            lKasbon();
        }, 'json').fail(function() {
            alert('Gagal mengubah status kasbon.');
        });
    });

    $(document).on('click', '.del-kasbon', function () {
        if (!confirm('Hapus kasbon ini?')) return;
        $.post('../../src/api/admin.php?action=delete_kasbon', { id: $(this).data('id') }, function() {
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
        $.post('../../src/api/admin.php?action=' + payload.action, payload, function(res) {
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
        $.getJSON('../../src/api/public.php?action=get_summary', s => {
            const cards = [
                ['Total Kas', fmt(s.total_kas_terkumpul), 'text-[#828fff]', '<i class="fa-solid fa-vault text-sm"></i>'],
                ['Saldo BMS', fmt(s.saldo_bms), 'text-[#38bdf8]', '<i class="fa-solid fa-building-columns text-sm"></i>'],
                ['Total Kasbon', fmt(s.total_kasbon), 'text-[#fbbf24]', '<i class="fa-solid fa-handshake text-sm"></i>'],
            ];
            $('#admin-summary').html(cards.map(([t, v, colorClass, icon]) =>
                `<div class="card-linear">
                    <div class="flex items-center justify-between mb-2">
                        <span class="eyebrow">${t}</span>
                        <span class="text-[#8a8f98]">${icon}</span>
                    </div>
                    <div class="text-2xl font-bold font-mono-num ${colorClass}">${v}</div>
                </div>`
            ).join(''));
        });
    }

    function lSiswa() {
        $.getJSON('../../src/api/admin.php?action=list_siswa', rows => {
            let h = `<table class="table-linear">
                <thead>
                    <tr>
                        <th class="w-32">Absen</th>
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
                        <td class="font-mono text-xs text-[#8a8f98]">${s.absen||'-'}</td>
                        <td class="font-medium text-[#f7f8f8]">${s.nama}</td>
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

    $(document).on('click', '.del-s', function () {
        if (!confirm('Hapus siswa ini beserta seluruh data kas terkait?')) return;
        $.post('../../src/api/admin.php?action=delete_siswa', { id: $(this).data('id') }, r => lSiswa(), 'json');
    });

    $('#form-siswa').on('submit', function (e) {
        e.preventDefault();
        $.post('../../src/api/admin.php?action=add_siswa', $(this).serialize(), r => {
            if (r.ok) { this.reset(); lSiswa(); } else alert(r.error);
        }, 'json');
    });

    function lKas() {
        const bulan = $('#admin-bulan').val(), tahun = $('#admin-tahun').val();
        $.getJSON('../../src/api/public.php', { action:'get_kas', bulan, tahun }, rows => {
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
        $.post('../../src/api/admin.php?action=update_kas', {
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
        $.post('../../src/api/admin.php?action=' + action, $(this).serialize(), r => {
            if (r.ok) { 
                $('#modal-jurnal').addClass('hidden'); 
                lJurnal(); 
            } else {
                alert(r.error);
            }
        }, 'json');
    });

    function lJurnal() {
        const params = { action: 'get_jurnal' };
        const b = $('#jurnal-bulan').val();
        const t = $('#jurnal-tahun').val();
        if (b) params.bulan = b;
        if (t) params.tahun = t;
        $.getJSON('../../src/api/public.php', params, r => {
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
            if (r.transaksi.length === 0) {
                h += `<tr><td colspan="5" class="text-center py-6 text-[#8a8f98]">Belum ada jurnal transaksi.</td></tr>`;
            } else {
                h += r.transaksi.map(t =>
                    `<tr>
                        <td class="font-mono text-xs text-[#8a8f98]">${t.tanggal}</td>
                        <td class="text-[#f7f8f8]">${t.keterangan}</td>
                        <td>
                            <span class="badge-status ${t.jenis==='masuk'?'badge-success':'badge-danger'} font-medium">
                                <i class="fa-solid ${t.jenis==='masuk' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'} text-[10px]"></i>
                                <span>${t.jenis==='masuk' ? 'Masuk' : 'Keluar'}</span>
                            </span>
                        </td>
                        <td class="text-right font-mono-num font-medium text-[#f7f8f8]">${fmt(t.nominal)}</td>
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
        });
    }

    $(document).on('click', '.edit-j', function () {
        const id = $(this).data('id');
        $.getJSON('../../src/api/public.php?action=get_jurnal', r => {
            const t = r.transaksi.find(x => x.id == id);
            if (t) openJurnalModal(t);
        });
    });

    $(document).on('click', '.del-j', function () {
        if (!confirm('Hapus transaksi ini?')) return;
        $.post('../../src/api/admin.php?action=delete_jurnal', { id: $(this).data('id') }, r => lJurnal(), 'json');
    });

    // Ekspor
    function lEkspor() {
        const dari = $('#form-ekspor [name=dari]').val();
        const sampai = $('#form-ekspor [name=sampai]').val();
        $.getJSON('../../src/api/public.php?action=get_jurnal', r => {
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
                            <span class="badge-status ${t.jenis==='masuk'?'badge-success':'badge-danger'} font-medium">
                                <i class="fa-solid ${t.jenis==='masuk' ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'} text-[10px]"></i>
                                <span>${t.jenis==='masuk' ? 'Masuk' : 'Keluar'}</span>
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

    // Kas BMS
    function lBms() {
        $.getJSON('../../src/api/public.php?action=get_bms', function(data) {
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
                h += `<tr><td colspan="6" class="text-center py-6 text-[#8a8f98]">Belum ada data kas BMS.</td></tr>`;
            } else {
                h += rows.map(function(r, i) {
                    const badge = r.jenis === 'setor'
                        ? '<span class="badge-status badge-success font-medium"><i class="fa-solid fa-arrow-right-to-bracket text-[10px]"></i> <span>Setor</span></span>'
                        : '<span class="badge-status badge-neutral font-medium"><i class="fa-solid fa-arrow-right-from-bracket text-[10px]"></i> <span>Tarik</span></span>';
                    return '<tr>' +
                        '<td class="font-mono text-xs text-[#8a8f98]">' + (i + 1) + '</td>' +
                        '<td class="font-mono text-xs text-[#8a8f98]">' + r.tanggal + '</td>' +
                        '<td class="text-[#f7f8f8]">' + r.keterangan + '</td>' +
                        '<td>' + badge + '</td>' +
                        '<td class="text-right font-mono-num font-medium text-[#f7f8f8]">' + fmt(r.jumlah) + '</td>' +
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
            $('#bms-wrap').html('<div class="text-center py-6 text-[#8a8f98]">Gagal memuat data kas BMS.</div>');
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
        $.post('../../src/api/admin.php?action=' + action, payload, function (res) {
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
        $.post('../../src/api/admin.php?action=delete_bms', { id: id }, function () {
            lBms();
        }, 'json').fail(function () {
            alert('Gagal menghapus.');
        });
    });
});
