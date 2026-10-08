@extends('layouts.admin')

@section('title', 'Forecast CBM')
@section('page_title', 'Forecast CBM')

@section('content')
<div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 mb-6">
    <i class="fa-solid fa-cube fs-2x text-primary me-4"></i>
    <div>
        <div class="fw-bold text-gray-800">Kalkulator CBM berdasarkan master item</div>
        <div class="text-gray-700 fs-7">CBM per koli diambil dari dimensi koli (P × L × T ÷ 1.000.000) di Master Item. Masukkan SKU dan jumlah koli, total CBM dihitung otomatis. Halaman ini hanya menghitung, tidak menyimpan atau mengubah data apa pun.</div>
    </div>
</div>

<div class="row g-6 mb-6">
    <div class="col-xl-7">
        <div class="card h-100"><div class="card-body py-5">
            <h3 class="fw-bolder mb-4">Tambah SKU</h3>
            <div class="row g-3 align-items-end">
                <div class="col-md-7"><label class="form-label">SKU / Nama Item</label><select id="cbm_sku_select" class="form-select form-select-solid"></select></div>
                <div class="col-md-3"><label class="form-label">Jumlah Koli</label><input id="cbm_koli_input" type="number" min="1" step="1" value="1" class="form-control form-control-solid"></div>
                <div class="col-md-2"><button id="cbm_add_btn" type="button" class="btn btn-primary w-100"><i class="fa-solid fa-plus"></i> Tambah</button></div>
            </div>
            <div class="separator my-5"></div>
            <label class="form-label">Tempel banyak SKU sekaligus</label>
            <textarea id="cbm_paste_input" rows="4" class="form-control form-control-solid font-monospace fs-7" placeholder="Satu baris per SKU: SKU [tab/koma/spasi] jumlah koli&#10;SKU-001	10&#10;SKU-002, 25"></textarea>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted fs-8">Bisa copy dua kolom (SKU &amp; Koli) langsung dari Excel. SKU yang sudah ada di daftar akan dijumlahkan kolinya.</div>
                <button id="cbm_paste_btn" type="button" class="btn btn-light-primary btn-sm ms-3 text-nowrap"><i class="fa-solid fa-paste"></i> Proses</button>
            </div>
        </div></div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100"><div class="card-body py-5">
            <h3 class="fw-bolder mb-4">Ringkasan</h3>
            <div class="row g-4 mb-4">
                <div class="col-6"><div class="text-muted fs-7">Baris SKU</div><div class="fs-2x fw-bolder" id="kpi_sku">0</div></div>
                <div class="col-6"><div class="text-muted fs-7">Total Koli</div><div class="fs-2x fw-bolder" id="kpi_koli">0</div></div>
                <div class="col-6"><div class="text-muted fs-7">Total CBM</div><div class="fs-2x fw-bolder text-primary"><span id="kpi_cbm">0</span> <span class="fs-5">m³</span></div></div>
                <div class="col-6"><div class="text-muted fs-7">Tanpa CBM</div><div class="fs-2x fw-bolder text-warning" id="kpi_incomplete">0</div></div>
            </div>
            <div class="text-muted fs-7 mb-2">Estimasi kontainer (kapasitas nominal)</div>
            <div class="d-flex flex-column gap-2" id="container_estimates">
                @foreach($containers as $key => $container)
                    <div data-capacity="{{ $container['capacity'] }}">
                        <div class="d-flex justify-content-between fs-7"><span class="fw-bold">{{ $container['label'] }} <span class="text-muted fw-normal">({{ $container['capacity'] }} m³)</span></span><span class="container-label">0 kontainer</span></div>
                        <div class="progress h-6px"><div class="progress-bar bg-primary" style="width:0%"></div></div>
                    </div>
                @endforeach
            </div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6 flex-wrap gap-3">
        <div class="d-flex align-items-center flex-grow-1" style="max-width:420px">
            <input id="cbm_title" type="text" maxlength="150" class="form-control form-control-solid" placeholder="Judul / referensi (opsional), mis. PO Oktober">
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button id="cbm_export_excel" type="button" class="btn btn-success btn-sm"><i class="fa-solid fa-file-excel"></i> Export Excel</button>
            <button id="cbm_export_pdf" type="button" class="btn btn-danger btn-sm"><i class="fa-solid fa-file-pdf"></i> Export PDF</button>
            <button id="cbm_reset" type="button" class="btn btn-light btn-sm"><i class="fa-solid fa-rotate-left"></i> Kosongkan</button>
        </div>
    </div>
    <div class="card-body py-5">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-7 gy-3" id="cbm_table">
                <thead><tr class="text-muted text-uppercase">
                    <th style="width:40px">No</th><th>SKU / Item</th><th class="text-end">P × L × T (cm)</th><th class="text-end">CBM / Koli</th><th class="text-end" style="width:130px">Koli</th><th class="text-end">Total CBM</th><th style="width:50px"></th>
                </tr></thead>
                <tbody></tbody>
                <tfoot><tr class="fw-bolder fs-6 border-top">
                    <td colspan="4" class="text-end">TOTAL</td><td class="text-end" id="foot_koli">0</td><td class="text-end text-primary" id="foot_cbm">0</td><td></td>
                </tr></tfoot>
            </table>
        </div>
    </div>
</div>

<form id="cbm_submit_form" method="POST" class="d-none">@csrf</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const lookupUrl = @json($lookupUrl);
    const exportUrl = @json($exportUrl);
    const printUrl = @json($printUrl);
    const maxLines = @json($maxLines);
    const storageKey = 'cbm_forecast_rows_v1';

    const tbody = document.querySelector('#cbm_table tbody');
    const titleInput = document.getElementById('cbm_title');
    const koliInput = document.getElementById('cbm_koli_input');
    const pasteInput = document.getElementById('cbm_paste_input');
    const skuSelect = $('#cbm_sku_select');

    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const fmt = (value, digits) => Number(value || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: digits});
    const notify = message => window.AppSwal?.error ? window.AppSwal.error(message) : alert(message);
    const keyOf = sku => String(sku).trim().toLowerCase();

    // rows: [{sku, koli}], items: cache master item per SKU (null = tidak ditemukan)
    let rows = [];
    const items = new Map();

    const save = () => {
        try { localStorage.setItem(storageKey, JSON.stringify({title: titleInput.value, rows})); } catch (e) {}
    };

    const cbmOf = row => items.get(keyOf(row.sku))?.cbm_per_koli ?? null;

    const renderSummary = () => {
        let totalKoli = 0, totalCbm = 0, incomplete = 0;
        rows.forEach(row => {
            const cbm = cbmOf(row);
            totalKoli += row.koli;
            if (cbm !== null) totalCbm += cbm * row.koli;
            else if (items.has(keyOf(row.sku))) incomplete++;
        });

        document.getElementById('kpi_sku').textContent = fmt(rows.length, 0);
        document.getElementById('kpi_koli').textContent = fmt(totalKoli, 0);
        document.getElementById('kpi_cbm').textContent = fmt(totalCbm, 4);
        document.getElementById('kpi_incomplete').textContent = fmt(incomplete, 0);
        document.getElementById('foot_koli').textContent = fmt(totalKoli, 0);
        document.getElementById('foot_cbm').textContent = `${fmt(totalCbm, 4)} m³`;

        document.querySelectorAll('#container_estimates [data-capacity]').forEach(el => {
            const capacity = Number(el.dataset.capacity);
            const count = totalCbm > 0 ? Math.ceil(totalCbm / capacity) : 0;
            const lastFill = count > 0 ? (totalCbm - (count - 1) * capacity) / capacity * 100 : 0;
            el.querySelector('.container-label').textContent = count > 0
                ? `${count} kontainer · terakhir terisi ${fmt(lastFill, 1)}%`
                : '0 kontainer';
            el.querySelector('.progress-bar').style.width = `${Math.min(100, lastFill)}%`;
        });
        save();
    };

    const rowTotal = row => {
        const cbm = cbmOf(row);
        return cbm !== null ? fmt(cbm * row.koli, 4) : '-';
    };

    const render = () => {
        tbody.innerHTML = rows.length ? rows.map((row, index) => {
            const key = keyOf(row.sku);
            const loaded = items.has(key);
            const item = items.get(key);
            const cbm = item?.cbm_per_koli ?? null;

            let info;
            if (!loaded) info = '<span class="text-muted">Memuat...</span>';
            else if (!item) info = '<span class="badge badge-light-danger">SKU tidak ditemukan</span>';
            else info = `${esc(item.name)}${item.is_active ? '' : ' <span class="badge badge-light-danger">Nonaktif</span>'}`;
            const dims = item && item.length !== null && item.width !== null && item.height !== null
                ? `${fmt(item.length, 2)} × ${fmt(item.width, 2)} × ${fmt(item.height, 2)}`
                : (item ? '<span class="badge badge-light-warning">Dimensi belum diisi</span>' : '-');

            return `<tr data-index="${index}">
                <td>${index + 1}</td>
                <td><div class="fw-bold">${esc(item?.sku ?? row.sku)}</div><div>${info}</div></td>
                <td class="text-end">${dims}</td>
                <td class="text-end">${cbm !== null ? fmt(cbm, 6) : '-'}</td>
                <td class="text-end"><input type="number" min="1" step="1" class="form-control form-control-sm form-control-solid text-end cbm-koli" value="${row.koli}"></td>
                <td class="text-end fw-bolder cbm-row-total">${rowTotal(row)}</td>
                <td class="text-end"><button type="button" class="btn btn-icon btn-sm btn-light-danger cbm-remove" title="Hapus"><i class="fa-solid fa-trash"></i></button></td>
            </tr>`;
        }).join('') : '<tr><td colspan="7" class="text-center text-muted py-10">Belum ada SKU. Tambahkan lewat pencarian atau tempel dari Excel.</td></tr>';
        renderSummary();
    };

    const loadItems = async () => {
        const missing = [...new Set(rows.map(row => row.sku.trim()))].filter(sku => !items.has(keyOf(sku)));
        for (let i = 0; i < missing.length; i += 100) {
            const chunk = missing.slice(i, i + 100);
            try {
                const response = await fetch(`${lookupUrl}?${new URLSearchParams({skus: chunk.join(',')})}`, {headers: {Accept: 'application/json'}});
                if (!response.ok) throw new Error(response.status);
                const json = await response.json();
                (json.data || []).forEach(item => items.set(keyOf(item.sku), item));
                chunk.forEach(sku => { if (!items.has(keyOf(sku))) items.set(keyOf(sku), null); });
            } catch (e) {
                notify('Gagal memuat data item. Coba lagi.');
                break;
            }
        }
        render();
    };

    const addLines = (lines) => {
        let skipped = 0;
        lines.forEach(({sku, koli}) => {
            const existing = rows.find(row => keyOf(row.sku) === keyOf(sku));
            if (existing) existing.koli += koli;
            else if (rows.length < maxLines) rows.push({sku, koli});
            else skipped++;
        });
        if (skipped) notify(`Maksimal ${maxLines} baris SKU. ${skipped} SKU tidak ditambahkan.`);
        render();
        loadItems();
    };

    const parsePaste = (text) => {
        const lines = [];
        const invalid = [];
        text.split(/\r?\n/).forEach(raw => {
            const line = raw.trim();
            if (!line) return;
            let parts = line.includes('\t') ? line.split('\t') : (/[,;]/.test(line) ? line.split(/[,;]/) : line.split(/\s+/));
            parts = parts.map(p => p.trim()).filter(Boolean);
            const sku = parts.length > 1 ? parts.slice(0, -1).join(' ') : parts[0];
            const koliText = parts.length > 1 ? parts[parts.length - 1].replace(/\./g, '') : '1';
            const koli = Number(koliText);
            if (!Number.isInteger(koli) || koli < 1) { invalid.push(line); return; }
            lines.push({sku, koli});
        });
        return {lines, invalid};
    };

    skuSelect.select2({
        width: '100%',
        placeholder: 'Cari SKU atau nama item',
        allowClear: true,
        minimumInputLength: 1,
        ajax: {url: @json($dataUrl), dataType: 'json', delay: 250, data: params => ({q: params.term}), processResults: data => data},
    });
    skuSelect.on('select2:select', () => koliInput.focus());

    const addFromSelect = () => {
        const sku = skuSelect.val();
        const koli = Number(koliInput.value);
        if (!sku) return notify('Pilih SKU terlebih dahulu.');
        if (!Number.isInteger(koli) || koli < 1) return notify('Jumlah koli harus bilangan bulat minimal 1.');
        addLines([{sku, koli}]);
        skuSelect.val(null).trigger('change');
        koliInput.value = 1;
        skuSelect.select2('open');
    };
    document.getElementById('cbm_add_btn').addEventListener('click', addFromSelect);
    koliInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); addFromSelect(); } });

    document.getElementById('cbm_paste_btn').addEventListener('click', () => {
        const {lines, invalid} = parsePaste(pasteInput.value);
        if (!lines.length && !invalid.length) return notify('Tempel data SKU terlebih dahulu.');
        if (lines.length) addLines(lines);
        // Baris header (mis. "SKU  Koli") ikut terlewati di sini.
        pasteInput.value = invalid.join('\n');
        if (invalid.length) notify(`${invalid.length} baris dilewati karena jumlah koli tidak valid. Baris tersebut dibiarkan di kolom tempel.`);
    });

    tbody.addEventListener('input', e => {
        if (!e.target.classList.contains('cbm-koli')) return;
        const tr = e.target.closest('tr');
        const koli = Number(e.target.value);
        if (!Number.isInteger(koli) || koli < 1) return;
        const row = rows[Number(tr.dataset.index)];
        row.koli = koli;
        tr.querySelector('.cbm-row-total').textContent = rowTotal(row);
        renderSummary();
    });
    tbody.addEventListener('change', e => {
        // Kembalikan ke nilai terakhir yang valid jika input dikosongkan / tidak valid.
        if (e.target.classList.contains('cbm-koli')) e.target.value = rows[Number(e.target.closest('tr').dataset.index)].koli;
    });
    tbody.addEventListener('click', e => {
        const button = e.target.closest('.cbm-remove');
        if (!button) return;
        rows.splice(Number(button.closest('tr').dataset.index), 1);
        render();
    });

    document.getElementById('cbm_reset').addEventListener('click', () => {
        if (!rows.length || confirm('Kosongkan semua SKU di daftar?')) {
            rows = [];
            render();
        }
    });
    titleInput.addEventListener('input', save);

    const submit = (url, target) => {
        if (!rows.length) return notify('Tambahkan minimal satu SKU.');
        const form = document.getElementById('cbm_submit_form');
        form.querySelectorAll('input:not([name="_token"])').forEach(el => el.remove());
        const append = (name, value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        };
        append('title', titleInput.value.trim());
        rows.forEach((row, index) => {
            append(`lines[${index}][sku]`, row.sku);
            append(`lines[${index}][koli]`, row.koli);
        });
        form.action = url;
        form.target = target;
        form.submit();
    };
    document.getElementById('cbm_export_excel').addEventListener('click', () => submit(exportUrl, '_self'));
    document.getElementById('cbm_export_pdf').addEventListener('click', () => submit(printUrl, '_blank'));

    try {
        const stored = JSON.parse(localStorage.getItem(storageKey) || 'null');
        if (stored && Array.isArray(stored.rows)) {
            titleInput.value = stored.title || '';
            rows = stored.rows.filter(row => row && row.sku && Number.isInteger(row.koli) && row.koli > 0).slice(0, maxLines);
        }
    } catch (e) {}
    render();
    loadItems();
});
</script>
@endpush
