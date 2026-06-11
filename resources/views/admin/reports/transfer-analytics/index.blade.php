@extends('layouts.admin')

@section('title', 'Analitik Transfer Gudang')
@section('page_title', 'Akurasi & Selisih Transfer Gudang')

@push('styles')
<style>
    .analytics-kpi { border: 1px solid #edf0f5; border-radius: .85rem; background: #fff; height: 100%; }
    .analytics-kpi .value { font-size: 1.65rem; font-weight: 800; line-height: 1.15; }
    .analytics-kpi .label { color: #7e8299; font-size: .82rem; font-weight: 600; }
    .analytics-kpi .hint { color: #a1a5b7; font-size: .72rem; margin-top: .3rem; }
    .analytics-panel { border: 1px solid #edf0f5; border-radius: .85rem; background: #fff; height: 100%; }
    .analytics-panel-title { font-weight: 700; color: #181c32; }
    .metric-bar { height: 7px; background: #eef1f6; border-radius: 10px; overflow: hidden; }
    .metric-bar > span { display: block; height: 100%; border-radius: 10px; }
    .chart-wrap { height: 260px; position: relative; }
    .chart-wrap canvas { width: 100%; height: 100%; }
    .table-compact td, .table-compact th { padding: .7rem .6rem; }
    .filter-box label { font-size: .75rem; color: #7e8299; margin-bottom: .3rem; }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-body py-5">
        <div class="row g-3 align-items-end filter-box">
            <div class="col-xl-2 col-md-4">
                <label>Dari Tanggal Dokumen</label>
                <input id="filter_date_from" class="form-control form-control-solid" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Sampai Tanggal Dokumen</label>
                <input id="filter_date_to" class="form-control form-control-solid" placeholder="YYYY-MM-DD">
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Gudang Asal</label>
                <select id="filter_source" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Gudang Tujuan</label>
                <select id="filter_destination" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Status</label>
                <select id="filter_status" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    <option value="draft">Draft</option>
                    <option value="shipped">Dalam Pengiriman</option>
                    <option value="received">Diterima Lengkap</option>
                    <option value="received_with_discrepancy">Diterima Berselisih</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Kategori</label>
                <select id="filter_category" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-4 col-md-8">
                <label>Cari transfer, SKU, gudang, atau alasan selisih</label>
                <input id="filter_search" class="form-control form-control-solid" placeholder="Tekan Enter untuk mencari">
            </div>
            <div class="col-xl-3 col-md-4 d-flex gap-2">
                <button id="filter_apply" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-filter"></i> Terapkan</button>
                <button id="filter_reset" class="btn btn-light">Reset</button>
            </div>
            <div class="col-xl-5 text-xl-end">
                <span class="text-muted fs-7">Volume dibandingkan dalam satuan dasar agar PCS, SET, BOX, dan KOLI tetap konsisten.</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="analytics-kpi p-5">
            <div class="label">Akurasi Dokumen</div>
            <div class="value text-success" id="kpi_accuracy">100%</div>
            <div class="hint">Transfer selesai tanpa selisih</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="analytics-kpi p-5">
            <div class="label">Fill Rate Unit</div>
            <div class="value text-primary" id="kpi_fill_rate">100%</div>
            <div class="hint">Diterima dibanding dikirim</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="analytics-kpi p-5">
            <div class="label">Transfer Berselisih</div>
            <div class="value text-danger" id="kpi_discrepancy_docs">0</div>
            <div class="hint" id="kpi_completed_hint">dari 0 transfer selesai</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="analytics-kpi p-5">
            <div class="label">Qty Selisih</div>
            <div class="value text-danger" id="kpi_discrepancy_qty">0</div>
            <div class="hint" id="kpi_discrepancy_rate">0% dari qty dikirim</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="analytics-kpi p-5">
            <div class="label">Dalam Pengiriman</div>
            <div class="value text-warning" id="kpi_transit_docs">0</div>
            <div class="hint"><span id="kpi_transit_qty">0</span> satuan dasar</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="analytics-kpi p-5">
            <div class="label">Rata-rata Lead Time</div>
            <div class="value" id="kpi_lead_time">0 jam</div>
            <div class="hint">Dari dikirim sampai diterima</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-xl-7">
        <div class="analytics-panel p-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <div class="analytics-panel-title">Tren Akurasi Penerimaan</div>
                    <div class="text-muted fs-7">Qty dikirim, diterima, dan selisih berdasarkan tanggal penerimaan.</div>
                </div>
                <div class="d-flex gap-3 fs-8">
                    <span><i class="fa-solid fa-circle text-primary"></i> Dikirim</span>
                    <span><i class="fa-solid fa-circle text-success"></i> Diterima</span>
                    <span><i class="fa-solid fa-circle text-danger"></i> Selisih</span>
                </div>
            </div>
            <div class="chart-wrap"><canvas id="trend_chart"></canvas></div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="analytics-panel p-5">
            <div class="analytics-panel-title mb-1">SKU dengan Selisih Terbesar</div>
            <div class="text-muted fs-7 mb-4">Prioritas investigasi berdasarkan total unit dasar.</div>
            <div id="top_items"><div class="text-muted text-center py-10">Belum ada data.</div></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-xl-7">
        <div class="analytics-panel p-5">
            <div class="analytics-panel-title mb-1">Performa Rute Gudang</div>
            <div class="text-muted fs-7 mb-4">Fill rate dan selisih per pasangan gudang.</div>
            <div class="table-responsive">
                <table class="table table-row-dashed table-compact align-middle">
                    <thead><tr class="text-muted fs-8 text-uppercase"><th>Rute</th><th class="text-end">Transfer</th><th class="text-end">Dikirim</th><th class="text-end">Selisih</th><th style="min-width:140px">Fill Rate</th></tr></thead>
                    <tbody id="route_rows"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="analytics-panel p-5">
            <div class="analytics-panel-title mb-1">Transfer In-Transit Tertua</div>
            <div class="text-muted fs-7 mb-4">Daftar tindak lanjut barang yang belum diterima.</div>
            <div id="oldest_transit"><div class="text-muted text-center py-10">Tidak ada transfer berjalan.</div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><h3 class="fw-bolder">Detail Akurasi per SKU Transfer</h3></div>
    </div>
    <div class="card-body py-5">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-7 gy-4" id="transfer_analytics_table">
                <thead>
                    <tr class="text-muted text-uppercase">
                        <th>Kode / Tanggal</th>
                        <th>Rute</th>
                        <th>SKU / Item</th>
                        <th class="text-end">Dikirim</th>
                        <th class="text-end">Diterima</th>
                        <th class="text-end">Selisih</th>
                        <th>Fill Rate</th>
                        <th>Lead Time</th>
                        <th>Status / Alasan</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json($dataUrl);
    const $table = $('#transfer_analytics_table');
    const filters = {
        dateFrom: document.getElementById('filter_date_from'),
        dateTo: document.getElementById('filter_date_to'),
        source: document.getElementById('filter_source'),
        destination: document.getElementById('filter_destination'),
        status: document.getElementById('filter_status'),
        category: document.getElementById('filter_category'),
        search: document.getElementById('filter_search'),
    };
    const number = value => Number(value || 0).toLocaleString('id-ID');
    const percent = value => `${Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 2})}%`;
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const statusMap = {
        draft: ['Draft', 'secondary'],
        shipped: ['Dalam Pengiriman', 'warning'],
        received: ['Diterima Lengkap', 'success'],
        received_with_discrepancy: ['Diterima Berselisih', 'danger'],
        cancelled: ['Dibatalkan', 'dark'],
    };
    const statusBadge = status => {
        const [label, color] = statusMap[status] || [status, 'secondary'];
        return `<span class="badge badge-light-${color}">${escapeHtml(label)}</span>`;
    };

    if (typeof flatpickr !== 'undefined') {
        flatpickr(filters.dateFrom, {dateFormat: 'Y-m-d', allowInput: true});
        flatpickr(filters.dateTo, {dateFormat: 'Y-m-d', allowInput: true});
    }
    if ($.fn.select2) {
        [filters.source, filters.destination, filters.status, filters.category].forEach(el => $(el).select2({width: '100%', allowClear: true}));
    }

    function requestData(params) {
        params.date_from = filters.dateFrom.value;
        params.date_to = filters.dateTo.value;
        params.source_warehouse_id = filters.source.value;
        params.destination_warehouse_id = filters.destination.value;
        params.status = filters.status.value;
        params.category_id = filters.category.value;
        params.q = filters.search.value;
    }

    function updateSummary(summary = {}) {
        document.getElementById('kpi_accuracy').textContent = percent(summary.document_accuracy);
        document.getElementById('kpi_fill_rate').textContent = percent(summary.fill_rate);
        document.getElementById('kpi_discrepancy_docs').textContent = number(summary.discrepancy_transfers);
        document.getElementById('kpi_completed_hint').textContent = `dari ${number(summary.completed_transfers)} transfer selesai`;
        document.getElementById('kpi_discrepancy_qty').textContent = number(summary.discrepancy_base);
        document.getElementById('kpi_discrepancy_rate').textContent = `${percent(summary.unit_discrepancy_rate)} dari qty dikirim`;
        document.getElementById('kpi_transit_docs').textContent = number(summary.in_transit_transfers);
        document.getElementById('kpi_transit_qty').textContent = number(summary.in_transit_qty);
        document.getElementById('kpi_lead_time').textContent = `${number(summary.average_lead_hours)} jam`;
    }

    function renderTopItems(rows = []) {
        const root = document.getElementById('top_items');
        if (!rows.length) {
            root.innerHTML = '<div class="text-muted text-center py-10">Tidak ada selisih pada filter ini.</div>';
            return;
        }
        const max = Math.max(...rows.map(row => Number(row.discrepancy || 0)), 1);
        root.innerHTML = rows.map((row, index) => `
            <div class="mb-4">
                <div class="d-flex justify-content-between gap-3 mb-2">
                    <div class="text-truncate"><span class="text-muted me-2">${index + 1}.</span><strong>${escapeHtml(row.sku)}</strong> <span class="text-muted">${escapeHtml(row.name)}</span></div>
                    <div class="fw-bold text-danger">${number(row.discrepancy)}</div>
                </div>
                <div class="metric-bar"><span class="bg-danger" style="width:${Math.max(3, Number(row.discrepancy) / max * 100)}%"></span></div>
                <div class="text-muted fs-8 mt-1">${number(row.transfer_count)} transfer berselisih</div>
            </div>`).join('');
    }

    function renderRoutes(rows = []) {
        document.getElementById('route_rows').innerHTML = rows.length ? rows.map(row => `
            <tr>
                <td class="fw-semibold">${escapeHtml(row.route)}</td>
                <td class="text-end">${number(row.transfer_count)}</td>
                <td class="text-end">${number(row.sent)}</td>
                <td class="text-end ${Number(row.discrepancy) ? 'text-danger fw-bold' : 'text-success'}">${number(row.discrepancy)}</td>
                <td>
                    <div class="d-flex justify-content-between fs-8 mb-1"><span>${percent(row.fill_rate)}</span></div>
                    <div class="metric-bar"><span class="${Number(row.fill_rate) >= 99 ? 'bg-success' : 'bg-warning'}" style="width:${Math.min(100, Number(row.fill_rate))}%"></span></div>
                </td>
            </tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-8">Belum ada transfer selesai.</td></tr>';
    }

    function renderTransit(rows = []) {
        const root = document.getElementById('oldest_transit');
        root.innerHTML = rows.length ? rows.map(row => {
            const age = Number(row.age_hours || 0);
            const color = age >= 48 ? 'danger' : (age >= 24 ? 'warning' : 'primary');
            return `<div class="d-flex justify-content-between align-items-start border-bottom py-3 gap-3">
                <div><div class="fw-bold">${escapeHtml(row.code)}</div><div class="text-muted fs-8">${escapeHtml(row.route)} · ${number(row.qty)} unit dasar</div></div>
                <span class="badge badge-light-${color}">${number(age)} jam</span>
            </div>`;
        }).join('') : '<div class="text-muted text-center py-10">Tidak ada transfer berjalan.</div>';
    }

    function drawTrend(rows = []) {
        const canvas = document.getElementById('trend_chart');
        const rect = canvas.getBoundingClientRect();
        const ratio = window.devicePixelRatio || 1;
        canvas.width = Math.max(300, rect.width) * ratio;
        canvas.height = Math.max(220, rect.height) * ratio;
        const ctx = canvas.getContext('2d');
        ctx.scale(ratio, ratio);
        const width = canvas.width / ratio;
        const height = canvas.height / ratio;
        ctx.clearRect(0, 0, width, height);
        if (!rows.length) {
            ctx.fillStyle = '#a1a5b7';
            ctx.font = '13px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText('Belum ada data penerimaan pada periode ini.', width / 2, height / 2);
            return;
        }
        const pad = {left: 48, right: 18, top: 18, bottom: 42};
        const innerW = width - pad.left - pad.right;
        const innerH = height - pad.top - pad.bottom;
        const max = Math.max(...rows.flatMap(row => [row.sent, row.received, row.discrepancy]), 1);
        ctx.strokeStyle = '#edf0f5';
        ctx.fillStyle = '#a1a5b7';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        for (let i = 0; i <= 4; i++) {
            const y = pad.top + innerH * i / 4;
            ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(width - pad.right, y); ctx.stroke();
            ctx.fillText(number(Math.round(max * (4 - i) / 4)), pad.left - 8, y + 4);
        }
        const x = index => pad.left + (rows.length === 1 ? innerW / 2 : innerW * index / (rows.length - 1));
        const y = value => pad.top + innerH - (Number(value) / max * innerH);
        const series = [['sent', '#3699ff'], ['received', '#50cd89'], ['discrepancy', '#f1416c']];
        series.forEach(([key, color]) => {
            ctx.strokeStyle = color; ctx.lineWidth = key === 'discrepancy' ? 2 : 2.5; ctx.beginPath();
            rows.forEach((row, index) => index ? ctx.lineTo(x(index), y(row[key])) : ctx.moveTo(x(index), y(row[key])));
            ctx.stroke();
        });
        ctx.fillStyle = '#7e8299'; ctx.textAlign = 'center';
        const step = Math.max(1, Math.ceil(rows.length / 7));
        rows.forEach((row, index) => {
            if (index % step === 0 || index === rows.length - 1) ctx.fillText(String(row.date).slice(5), x(index), height - 15);
        });
    }

    const dt = $table.DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        order: [],
        pageLength: 10,
        ajax: {
            url: dataUrl,
            data: requestData,
            dataSrc: json => {
                updateSummary(json.summary || {});
                renderTopItems(json.analytics?.top_items || []);
                renderRoutes(json.analytics?.routes || []);
                renderTransit(json.analytics?.oldest_transit || []);
                drawTrend(json.analytics?.trend || []);
                return json.data || [];
            }
        },
        columns: [
            {data: null, render: row => `<div class="fw-bold">${escapeHtml(row.code)}</div><div class="text-muted">${escapeHtml(row.transacted_at || '-')}</div>`},
            {data: 'route'},
            {data: null, render: row => `<div class="fw-bold">${escapeHtml(row.sku)}</div><div>${escapeHtml(row.item_name)}</div><div class="text-muted">${escapeHtml(row.category)}</div>`},
            {data: null, className: 'text-end', render: row => `<div>${number(row.sent_input)} ${escapeHtml(row.sent_unit)}</div><div class="text-muted">${number(row.sent_base)} dasar</div>`},
            {data: null, className: 'text-end', render: row => row.received_at ? `<div>${number(row.received_input)} ${escapeHtml(row.received_unit)}</div><div class="text-muted">${number(row.received_base)} dasar</div>` : '-'},
            {data: 'discrepancy_base', className: 'text-end', render: value => Number(value) ? `<span class="text-danger fw-bold">${number(value)}</span>` : '<span class="text-success">0</span>'},
            {data: 'fill_rate', render: value => value === null ? '-' : `<span class="fw-bold ${Number(value) < 100 ? 'text-warning' : 'text-success'}">${percent(value)}</span>`},
            {data: 'lead_time_hours', render: value => value === null ? '-' : `${number(value)} jam`},
            {data: null, render: row => `${statusBadge(row.status)}<div class="text-muted mt-2" style="max-width:220px">${escapeHtml(row.discrepancy_note)}</div>`},
        ],
        language: {processing: 'Mengolah analitik...', emptyTable: 'Tidak ada data transfer sesuai filter.'},
    });

    document.getElementById('filter_apply').addEventListener('click', () => dt.ajax.reload());
    document.getElementById('filter_search').addEventListener('keyup', event => {
        if (event.key === 'Enter') dt.ajax.reload();
    });
    document.getElementById('filter_reset').addEventListener('click', () => {
        Object.values(filters).forEach(el => {
            el.value = '';
            if ($(el).data('select2')) $(el).val('').trigger('change.select2');
        });
        dt.ajax.reload();
    });
    window.addEventListener('resize', () => {
        const json = dt.ajax.json();
        drawTrend(json?.analytics?.trend || []);
    });
});
</script>
@endpush
