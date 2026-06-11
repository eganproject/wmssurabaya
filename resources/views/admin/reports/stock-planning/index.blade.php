@extends('layouts.admin')

@section('title', 'Perencanaan Pengadaan Stok')
@section('page_title', 'Analitik Kebutuhan & Pengadaan Stok')

@push('styles')
<style>
    .planning-kpi { border: 1px solid #edf0f5; border-radius: .85rem; background: #fff; height: 100%; }
    .planning-kpi .value { font-size: 1.65rem; font-weight: 800; line-height: 1.15; }
    .planning-kpi .label { color: #7e8299; font-size: .82rem; font-weight: 600; }
    .planning-kpi .hint { color: #a1a5b7; font-size: .72rem; margin-top: .3rem; }
    .planning-panel { border: 1px solid #edf0f5; border-radius: .85rem; background: #fff; height: 100%; }
    .planning-panel-title { font-weight: 700; color: #181c32; }
    .stock-meter { height: 8px; background: #eef1f6; border-radius: 10px; overflow: hidden; }
    .stock-meter > span { display: block; height: 100%; border-radius: 10px; }
    .filter-box label { font-size: .75rem; color: #7e8299; margin-bottom: .3rem; }
    .action-row { border-bottom: 1px dashed #e4e6ef; padding: .8rem 0; }
</style>
@endpush

@section('content')
<div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 mb-6">
    <i class="fa-solid fa-lightbulb fs-2x text-primary me-4"></i>
    <div>
        <div class="fw-bold text-gray-800">Cara membaca rekomendasi</div>
        <div class="text-gray-700 fs-7">
            Pemakaian dihitung dari mutasi stok keluar operasional. Stok proyeksi = stok saat ini + transfer masuk yang sedang dikirim.
            Rekomendasi membawa stok menuju target hari persediaan, dengan safety stock sebagai batas minimum.
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-body py-5">
        <div class="row g-3 align-items-end filter-box">
            <div class="col-xl-2 col-md-4">
                <label>Gudang</label>
                <select id="filter_warehouse" class="form-select form-select-solid">
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Riwayat Pemakaian Dari</label>
                <input id="filter_date_from" class="form-control form-control-solid" placeholder="30 hari terakhir">
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Sampai</label>
                <input id="filter_date_to" class="form-control form-control-solid" placeholder="Hari ini">
            </div>
            <div class="col-xl-1 col-md-3">
                <label>Lead Time</label>
                <input id="filter_lead_days" type="number" min="1" max="365" value="7" class="form-control form-control-solid">
            </div>
            <div class="col-xl-1 col-md-3">
                <label>Target Hari</label>
                <input id="filter_target_days" type="number" min="1" max="365" value="30" class="form-control form-control-solid">
            </div>
            <div class="col-xl-2 col-md-3">
                <label>Status Tindakan</label>
                <select id="filter_status" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    <option value="critical">Kritis / Habis</option>
                    <option value="reorder">Perlu Pengadaan</option>
                    <option value="healthy">Aman</option>
                    <option value="slow">Tidak Bergerak</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-3">
                <label>Kategori</label>
                <select id="filter_category" class="form-select form-select-solid">
                    <option value="">Semua</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-4 col-md-8">
                <label>Cari SKU, nama, kategori, atau lokasi</label>
                <input id="filter_search" class="form-control form-control-solid" placeholder="Tekan Enter untuk mencari">
            </div>
            <div class="col-xl-3 col-md-4 d-flex gap-2">
                <button id="filter_apply" class="btn btn-primary flex-grow-1"><i class="fa-solid fa-calculator"></i> Hitung</button>
                <button id="filter_reset" class="btn btn-light">Reset</button>
            </div>
            <div class="col-xl-5 text-xl-end">
                <span class="text-muted fs-7" id="period_info">Periode pemakaian akan dihitung otomatis.</span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="planning-kpi p-5">
            <div class="label">SKU Kritis</div>
            <div class="value text-danger" id="kpi_critical">0</div>
            <div class="hint">Stok proyeksi habis</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="planning-kpi p-5">
            <div class="label">Perlu Pengadaan</div>
            <div class="value text-warning" id="kpi_reorder">0</div>
            <div class="hint">Menyentuh reorder point</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="planning-kpi p-5">
            <div class="label">SKU Direkomendasikan</div>
            <div class="value text-primary" id="kpi_recommended_sku">0</div>
            <div class="hint">Memiliki qty rekomendasi</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="planning-kpi p-5">
            <div class="label">Total Rekomendasi</div>
            <div class="value" id="kpi_recommended_qty">0</div>
            <div class="hint">Dalam satuan dasar</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="planning-kpi p-5">
            <div class="label">Stok Sedang Masuk</div>
            <div class="value text-info" id="kpi_incoming">0</div>
            <div class="hint">Transfer berstatus shipped</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="planning-kpi p-5">
            <div class="label">Tidak Bergerak</div>
            <div class="value text-muted" id="kpi_slow">0</div>
            <div class="hint">Ada stok tanpa pemakaian</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-xl-7">
        <div class="planning-panel p-5">
            <div class="planning-panel-title mb-1">Prioritas Tindakan</div>
            <div class="text-muted fs-7 mb-4">SKU paling mendesak berdasarkan stok proyeksi dan kebutuhan target.</div>
            <div id="priority_items"><div class="text-muted text-center py-10">Memuat rekomendasi...</div></div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="planning-panel p-5">
            <div class="planning-panel-title mb-1">Kebutuhan per Kategori</div>
            <div class="text-muted fs-7 mb-4">Ringkasan untuk penyusunan rencana pengadaan.</div>
            <div id="category_needs"><div class="text-muted text-center py-10">Belum ada kebutuhan.</div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div>
            <h3 class="fw-bolder mb-1">Detail Perencanaan Stok</h3>
            <div class="text-muted fs-7">Days cover adalah estimasi berapa hari stok proyeksi bertahan berdasarkan pemakaian rata-rata.</div>
        </div>
    </div>
    <div class="card-body py-5">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-7 gy-4" id="stock_planning_table">
                <thead>
                    <tr class="text-muted text-uppercase">
                        <th>SKU / Item</th>
                        <th class="text-end">Stok Saat Ini</th>
                        <th class="text-end">Sedang Masuk</th>
                        <th class="text-end">Pemakaian</th>
                        <th class="text-end">Rata-rata/Hari</th>
                        <th>Days Cover</th>
                        <th class="text-end">Safety / ROP</th>
                        <th class="text-end">Target</th>
                        <th class="text-end">Rekomendasi</th>
                        <th>Status</th>
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
    const $table = $('#stock_planning_table');
    const filters = {
        warehouse: document.getElementById('filter_warehouse'),
        dateFrom: document.getElementById('filter_date_from'),
        dateTo: document.getElementById('filter_date_to'),
        leadDays: document.getElementById('filter_lead_days'),
        targetDays: document.getElementById('filter_target_days'),
        status: document.getElementById('filter_status'),
        category: document.getElementById('filter_category'),
        search: document.getElementById('filter_search'),
    };
    const number = value => Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 2});
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const statusMap = {
        critical: ['Kritis / Habis', 'danger'],
        reorder: ['Perlu Pengadaan', 'warning'],
        healthy: ['Aman', 'success'],
        slow: ['Tidak Bergerak', 'secondary'],
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
        [filters.warehouse, filters.status, filters.category].forEach(el => $(el).select2({width: '100%', allowClear: el !== filters.warehouse}));
    }

    function requestData(params) {
        params.warehouse_id = filters.warehouse.value;
        params.date_from = filters.dateFrom.value;
        params.date_to = filters.dateTo.value;
        params.lead_days = filters.leadDays.value;
        params.target_days = filters.targetDays.value;
        params.status = filters.status.value;
        params.category_id = filters.category.value;
        params.q = filters.search.value;
    }

    function updateSummary(summary = {}) {
        document.getElementById('kpi_critical').textContent = number(summary.critical_sku);
        document.getElementById('kpi_reorder').textContent = number(summary.reorder_sku);
        document.getElementById('kpi_recommended_sku').textContent = number(summary.recommended_sku);
        document.getElementById('kpi_recommended_qty').textContent = number(summary.recommended_qty);
        document.getElementById('kpi_incoming').textContent = number(summary.incoming_qty);
        document.getElementById('kpi_slow').textContent = number(summary.slow_sku);
        document.getElementById('period_info').textContent =
            `${summary.warehouse || '-'} · pemakaian ${summary.date_from || '-'} s/d ${summary.date_to || '-'} (${number(summary.period_days)} hari) · lead time ${number(summary.lead_days)} hari · target ${number(summary.target_days)} hari`;
    }

    function renderPriority(rows = []) {
        const root = document.getElementById('priority_items');
        if (!rows.length) {
            root.innerHTML = '<div class="text-center text-success py-10"><i class="fa-solid fa-circle-check fs-2x mb-3"></i><div>Tidak ada SKU kritis atau perlu pengadaan.</div></div>';
            return;
        }
        root.innerHTML = rows.map(row => {
            const cover = row.days_cover === null ? 'Tidak ada pemakaian' : `${number(row.days_cover)} hari cover`;
            const recommendation = row.recommended_packages
                ? `${number(row.recommended_packages)} ${escapeHtml(row.package_unit || 'koli')} (${number(row.recommended_qty)} ${escapeHtml(row.base_unit)})`
                : `${number(row.recommended_qty)} ${escapeHtml(row.base_unit)}`;
            return `<div class="action-row d-flex justify-content-between align-items-center gap-4">
                <div class="min-w-0">
                    <div class="d-flex align-items-center gap-2"><strong>${escapeHtml(row.sku)}</strong>${statusBadge(row.status)}</div>
                    <div class="text-muted text-truncate">${escapeHtml(row.name)} · ${cover}</div>
                    <div class="fs-8 text-muted">Stok ${number(row.current_stock)} + masuk ${number(row.incoming_stock)} · ROP ${number(row.reorder_point)}</div>
                </div>
                <div class="text-end flex-shrink-0"><div class="text-muted fs-8">Rekomendasi</div><div class="fw-bolder text-primary">${recommendation}</div></div>
            </div>`;
        }).join('');
    }

    function renderCategories(rows = []) {
        const root = document.getElementById('category_needs');
        if (!rows.length) {
            root.innerHTML = '<div class="text-muted text-center py-10">Belum ada kebutuhan pengadaan.</div>';
            return;
        }
        const max = Math.max(...rows.map(row => Number(row.recommended_qty || 0)), 1);
        root.innerHTML = rows.map(row => `
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-2">
                    <div><strong>${escapeHtml(row.category)}</strong><div class="text-muted fs-8">${number(row.sku_count)} SKU</div></div>
                    <div class="fw-bolder">${number(row.recommended_qty)}</div>
                </div>
                <div class="stock-meter"><span class="bg-primary" style="width:${Math.max(3, Number(row.recommended_qty) / max * 100)}%"></span></div>
            </div>`).join('');
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
                renderPriority(json.analytics?.priority_items || []);
                renderCategories(json.analytics?.category_needs || []);
                return json.data || [];
            },
            error: xhr => window.AppSwal?.error(Object.values(xhr.responseJSON?.errors || {}).flat().join('\n') || 'Gagal menghitung perencanaan stok.'),
        },
        columns: [
            {data: null, render: row => `<div class="fw-bold">${escapeHtml(row.sku)}</div><div>${escapeHtml(row.name)}</div><div class="text-muted">${escapeHtml(row.category)} · ${escapeHtml(row.location)}</div>`},
            {data: 'current_stock', className: 'text-end', render: (value, type, row) => `${number(value)} <span class="text-muted">${escapeHtml(row.base_unit)}</span>`},
            {data: 'incoming_stock', className: 'text-end', render: value => Number(value) ? `<span class="text-info fw-bold">+${number(value)}</span>` : '0'},
            {data: 'usage_qty', className: 'text-end', render: value => number(value)},
            {data: 'average_daily_usage', className: 'text-end', render: value => number(value)},
            {data: 'days_cover', render: (value, type, row) => {
                if (value === null) return '<span class="text-muted">Tidak terukur</span>';
                const target = Number(filters.targetDays.value || 30);
                const pct = Math.min(100, Number(value) / target * 100);
                const color = row.status === 'critical' ? 'danger' : (row.status === 'reorder' ? 'warning' : 'success');
                return `<div class="d-flex justify-content-between fs-8 mb-1"><span>${number(value)} hari</span></div><div class="stock-meter" style="min-width:100px"><span class="bg-${color}" style="width:${pct}%"></span></div>`;
            }},
            {data: null, className: 'text-end', render: row => `${number(row.safety_stock)} / <strong>${number(row.reorder_point)}</strong>`},
            {data: 'target_stock', className: 'text-end', render: value => number(value)},
            {data: 'recommended_qty', className: 'text-end', render: (value, type, row) => {
                if (!Number(value)) return '<span class="text-success">0</span>';
                const packages = row.recommended_packages ? `<div class="text-muted fs-8">${number(row.recommended_packages)} ${escapeHtml(row.package_unit || 'koli')}</div>` : '';
                return `<span class="text-primary fw-bolder">${number(value)} ${escapeHtml(row.base_unit)}</span>${packages}`;
            }},
            {data: 'status', render: statusBadge},
        ],
        language: {processing: 'Menghitung kebutuhan stok...', emptyTable: 'Tidak ada item sesuai filter.'},
    });

    document.getElementById('filter_apply').addEventListener('click', () => dt.ajax.reload());
    filters.search.addEventListener('keyup', event => {
        if (event.key === 'Enter') dt.ajax.reload();
    });
    document.getElementById('filter_reset').addEventListener('click', () => {
        filters.dateFrom.value = '';
        filters.dateTo.value = '';
        filters.leadDays.value = 7;
        filters.targetDays.value = 30;
        filters.status.value = '';
        filters.category.value = '';
        filters.search.value = '';
        [filters.status, filters.category].forEach(el => {
            if ($(el).data('select2')) $(el).val('').trigger('change.select2');
        });
        dt.ajax.reload();
    });
});
</script>
@endpush
