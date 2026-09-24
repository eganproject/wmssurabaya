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
<div class="card mb-6">
    <div class="card-body py-3">
        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-transparent fw-bold">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#stock_planning_current" type="button">
                    <i class="fa-solid fa-list-check me-2"></i>Perencanaan Stok
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#stock_planning_forecast" type="button">
                    <i class="fa-solid fa-chart-line me-2"></i>Analisa Forecast
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content">
<div class="tab-pane fade show active" id="stock_planning_current" role="tabpanel">
<div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 mb-6">
    <i class="fa-solid fa-lightbulb fs-2x text-primary me-4"></i>
    <div>
        <div class="fw-bold text-gray-800">Cara membaca rekomendasi</div>
        <div class="text-gray-700 fs-7">
            Secara default, stok merupakan akumulasi Gudang Besar dan Gudang Kecil. Pemakaian dihitung dari total outbound manual dan import resi yang sudah selesai diproses.
            Stok proyeksi = stok saat ini + transfer masuk yang sedang dikirim.
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
                    <option value="" selected>Gudang Besar + Gudang Kecil (Akumulasi)</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
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
                        <th class="text-end">Qty Out</th>
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
</div>

<div class="tab-pane fade" id="stock_planning_forecast" role="tabpanel">
    <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-5 mb-6">
        <i class="fa-solid fa-chart-area fs-2x text-info me-4"></i>
        <div>
            <div class="fw-bold text-gray-800">Forecast demand tanpa safety stock</div>
            <div class="text-gray-700 fs-7">
                Forecast memakai weighted moving average: 50% laju 30 hari terbaru, 30% periode sebelumnya, dan 20% sisa histori.
                Posisi stok mencakup stok saat ini dan transfer berstatus shipped. Target pengadaan = forecast harian × (lead time + siklus review).
                Lead time dipilih otomatis dari sumber pengadaan pada master item: Nanggewer (Produksi) atau Import.
            </div>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-body py-5">
            <div class="row g-3 align-items-end filter-box">
                <div class="col-xl-3 col-md-6">
                    <label>Gudang</label>
                    <select id="forecast_warehouse" class="form-select form-select-solid">
                        <option value="" selected>Gudang Besar + Gudang Kecil (Akumulasi)</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-1 col-md-3">
                    <label>Histori Hari</label>
                    <input id="forecast_history_days" type="number" min="30" max="365" value="90" class="form-control form-control-solid">
                </div>
                <div class="col-xl-1 col-md-3">
                    <label>LT Import</label>
                    <input id="forecast_import_lead" type="number" min="1" max="365" value="90" class="form-control form-control-solid">
                </div>
                <div class="col-xl-1 col-md-3">
                    <label>LT Produksi</label>
                    <input id="forecast_production_lead" type="number" min="1" max="365" value="14" class="form-control form-control-solid">
                </div>
                <div class="col-xl-1 col-md-3">
                    <label>Siklus Review</label>
                    <input id="forecast_review_days" type="number" min="1" max="180" value="30" class="form-control form-control-solid">
                </div>
                <div class="col-xl-2 col-md-3">
                    <label>Tindakan</label>
                    <select id="forecast_action" class="form-select form-select-solid">
                        <option value="">Semua</option>
                        <option value="any_action">Ada Rekomendasi</option>
                        <option value="import_now">Import Sekarang</option>
                        <option value="production_now">Produksi Sekarang</option>
                        <option value="no_demand">Tanpa Demand</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label>Sumber Pengadaan</label>
                    <select id="forecast_procurement_source" class="form-select form-select-solid">
                        <option value="">Semua Sumber</option>
                        <option value="nanggewer">Nanggewer (Produksi)</option>
                        <option value="import">Import</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-3">
                    <label>Kategori</label>
                    <select id="forecast_category" class="form-select form-select-solid">
                        <option value="">Semua</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-4 col-md-7">
                    <label>Cari SKU, nama, atau kategori</label>
                    <input id="forecast_search" class="form-control form-control-solid" placeholder="Tekan Enter untuk mencari">
                </div>
                <div class="col-xl-3 col-md-5 d-flex gap-2">
                    <button id="forecast_apply" class="btn btn-info flex-grow-1"><i class="fa-solid fa-chart-line"></i> Hitung Forecast</button>
                    <button id="forecast_reset" class="btn btn-light">Reset</button>
                </div>
                <div class="col-xl-5 text-xl-end">
                    <span class="text-muted fs-7" id="forecast_period_info">Histori forecast akan dihitung otomatis.</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-6">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="planning-kpi p-5">
                <div class="label">SKU Berdemand</div>
                <div class="value text-primary" id="forecast_kpi_demand">0</div>
                <div class="hint">Memiliki forecast harian</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="planning-kpi p-5">
                <div class="label">Import Sekarang</div>
                <div class="value text-danger" id="forecast_kpi_import_now">0</div>
                <div class="hint">Cover ≤ lead time import</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="planning-kpi p-5">
                <div class="label">Produksi Sekarang</div>
                <div class="value text-warning" id="forecast_kpi_production_now">0</div>
                <div class="hint">Cover ≤ lead time produksi</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="planning-kpi p-5">
                <div class="label">Qty Rekom. Import</div>
                <div class="value text-info" id="forecast_kpi_import_qty">0</div>
                <div class="hint">Dibulatkan ke kemasan</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="planning-kpi p-5">
                <div class="label">Qty Rekom. Produksi</div>
                <div class="value text-success" id="forecast_kpi_production_qty">0</div>
                <div class="hint">Dalam satuan dasar</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="planning-kpi p-5">
                <div class="label">Tanpa Demand</div>
                <div class="value text-muted" id="forecast_kpi_no_demand">0</div>
                <div class="hint">Tidak ada outbound valid</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <div>
                <h3 class="fw-bolder mb-1">Forecast per SKU</h3>
                <div class="text-muted fs-7">Tanggal order dihitung mundur dari days cover terhadap lead time. Rekomendasi tidak menggunakan safety stock.</div>
            </div>
        </div>
        <div class="card-body py-5">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-4" id="stock_forecast_table">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>SKU / Item</th>
                            <th class="text-end">Posisi Stok</th>
                            <th class="text-end">Histori Out</th>
                            <th class="text-end">Forecast/Hari</th>
                            <th>Trend</th>
                            <th>Days Cover</th>
                            <th>Sumber Pengadaan</th>
                            <th>Rekomendasi Sesuai Sumber</th>
                            <th>Kualitas Data</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const dataUrl = @json($dataUrl);
    const forecastDataUrl = @json($forecastDataUrl);
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
        filters.warehouse.value = '';
        filters.leadDays.value = 7;
        filters.targetDays.value = 30;
        filters.status.value = '';
        filters.category.value = '';
        filters.search.value = '';
        [filters.warehouse, filters.status, filters.category].forEach(el => {
            if ($(el).data('select2')) $(el).val('').trigger('change.select2');
        });
        dt.ajax.reload();
    });
    const forecastFilters = {
        warehouse: document.getElementById('forecast_warehouse'),
        historyDays: document.getElementById('forecast_history_days'),
        importLead: document.getElementById('forecast_import_lead'),
        productionLead: document.getElementById('forecast_production_lead'),
        reviewDays: document.getElementById('forecast_review_days'),
        action: document.getElementById('forecast_action'),
        procurementSource: document.getElementById('forecast_procurement_source'),
        category: document.getElementById('forecast_category'),
        search: document.getElementById('forecast_search'),
    };

    if ($.fn.select2) {
        [forecastFilters.warehouse, forecastFilters.action, forecastFilters.procurementSource, forecastFilters.category].forEach(el => {
            $(el).select2({width: '100%', allowClear: el !== forecastFilters.warehouse});
        });
    }

    const forecastStatus = {
        order_now: ['Order Sekarang', 'danger'],
        plan: ['Jadwalkan', 'warning'],
        covered: ['Tercukupi', 'success'],
        no_demand: ['Tanpa Demand', 'secondary'],
    };
    const trendStatus = {
        growing: ['Naik', 'danger', 'fa-arrow-trend-up'],
        declining: ['Turun', 'success', 'fa-arrow-trend-down'],
        stable: ['Stabil', 'primary', 'fa-arrow-right'],
        new: ['Demand Baru', 'warning', 'fa-star'],
        insufficient: ['Data Terbatas', 'secondary', 'fa-minus'],
    };
    const qualityStatus = {
        high: ['Baik', 'success'],
        medium: ['Cukup', 'warning'],
        low: ['Rendah', 'danger'],
        none: ['Tidak Ada', 'secondary'],
    };

    function forecastRequest(params) {
        params.warehouse_id = forecastFilters.warehouse.value;
        params.history_days = forecastFilters.historyDays.value;
        params.import_lead_days = forecastFilters.importLead.value;
        params.production_lead_days = forecastFilters.productionLead.value;
        params.review_days = forecastFilters.reviewDays.value;
        params.action = forecastFilters.action.value;
        params.procurement_source = forecastFilters.procurementSource.value;
        params.category_id = forecastFilters.category.value;
        params.q = forecastFilters.search.value;
    }

    function updateForecastSummary(summary = {}) {
        document.getElementById('forecast_kpi_demand').textContent = number(summary.demand_sku);
        document.getElementById('forecast_kpi_import_now').textContent = number(summary.import_order_now_sku);
        document.getElementById('forecast_kpi_production_now').textContent = number(summary.production_order_now_sku);
        document.getElementById('forecast_kpi_import_qty').textContent = number(summary.import_recommended_qty);
        document.getElementById('forecast_kpi_production_qty').textContent = number(summary.production_recommended_qty);
        document.getElementById('forecast_kpi_no_demand').textContent = number(summary.no_demand_sku);
        document.getElementById('forecast_period_info').textContent =
            `${summary.warehouse || '-'} · histori ${summary.date_from || '-'} s/d ${summary.date_to || '-'} (${number(summary.history_days)} hari) · LT import ${number(summary.import_lead_days)} · LT produksi ${number(summary.production_lead_days)} · review ${number(summary.review_days)} hari`;
    }

    function renderTrend(row) {
        const [label, color, icon] = trendStatus[row.trend] || trendStatus.insufficient;
        const percent = row.trend_percent === null ? '' : ` ${Number(row.trend_percent) > 0 ? '+' : ''}${number(row.trend_percent)}%`;
        return `<span class="badge badge-light-${color}"><i class="fa-solid ${icon} me-1"></i>${label}${percent}</span>
            <div class="text-muted fs-8 mt-1">30h: ${number(row.recent_daily)} · prev: ${number(row.previous_daily)}</div>`;
    }

    function renderScenario(scenario, row, source) {
        const [label, color] = forecastStatus[scenario.status] || forecastStatus.no_demand;
        if (scenario.status === 'no_demand') {
            return `<span class="badge badge-light-secondary">${label}</span>`;
        }

        const timing = scenario.status === 'order_now'
            ? 'Order sekarang'
            : `Order ${escapeHtml(scenario.order_date || '-')}`;
        const packages = source === 'import' && scenario.recommended_packages
            ? `<div class="text-muted fs-8">${number(scenario.recommended_packages)} ${escapeHtml(row.package_unit || 'kemasan')}</div>`
            : '';

        return `<div style="min-width:145px">
            <span class="badge badge-light-${color}">${label}</span>
            <div class="fw-bolder mt-2">${number(scenario.recommended_qty)} ${escapeHtml(row.base_unit)}</div>
            ${packages}
            <div class="text-muted fs-8">${timing} · LT ${number(scenario.lead_days)}h</div>
            <div class="text-muted fs-8">Demand LT ${number(scenario.lead_demand)}</div>
        </div>`;
    }

    const forecastDt = $('#stock_forecast_table').DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        order: [],
        pageLength: 10,
        ajax: {
            url: forecastDataUrl,
            data: forecastRequest,
            dataSrc: json => {
                updateForecastSummary(json.summary || {});
                return json.data || [];
            },
            error: xhr => window.AppSwal?.error(Object.values(xhr.responseJSON?.errors || {}).flat().join('\n') || 'Gagal menghitung forecast stok.'),
        },
        columns: [
            {data: null, render: row => `<div class="fw-bold">${escapeHtml(row.sku)}</div><div>${escapeHtml(row.name)}</div><div class="text-muted">${escapeHtml(row.category)}</div>`},
            {data: 'stock_position', className: 'text-end', render: (value, type, row) => `<strong>${number(value)}</strong><div class="text-muted fs-8">stok ${number(row.current_stock)} + masuk ${number(row.incoming_stock)}</div>`},
            {data: 'history_qty', className: 'text-end', render: (value, type, row) => `${number(value)}<div class="text-muted fs-8">${number(row.active_days)} hari aktif</div>`},
            {data: 'forecast_daily', className: 'text-end', render: (value, type, row) => `<strong>${number(value)}</strong><div class="text-muted fs-8">≈ ${number(row.forecast_monthly)}/30 hari</div>`},
            {data: null, render: renderTrend},
            {data: 'days_cover', render: value => value === null ? '<span class="text-muted">Tidak terukur</span>' : `<strong>${number(value)} hari</strong>`},
            {data: 'procurement_source', render: (value, type, row) => {
                const color = value === 'import' ? 'info' : 'success';
                return `<span class="badge badge-light-${color}">${escapeHtml(row.procurement_source_label)}</span>`;
            }},
            {data: 'recommendation', render: (value, type, row) => renderScenario(value, row, row.procurement_source)},
            {data: 'data_quality', render: value => {
                const [label, color] = qualityStatus[value] || qualityStatus.none;
                return `<span class="badge badge-light-${color}">${label}</span>`;
            }},
        ],
        language: {processing: 'Menghitung forecast demand...', emptyTable: 'Tidak ada item sesuai filter forecast.'},
    });

    document.getElementById('forecast_apply').addEventListener('click', () => forecastDt.ajax.reload());
    forecastFilters.search.addEventListener('keyup', event => {
        if (event.key === 'Enter') forecastDt.ajax.reload();
    });
    document.getElementById('forecast_reset').addEventListener('click', () => {
        forecastFilters.warehouse.value = '';
        forecastFilters.historyDays.value = 90;
        forecastFilters.importLead.value = 90;
        forecastFilters.productionLead.value = 14;
        forecastFilters.reviewDays.value = 30;
        forecastFilters.action.value = '';
        forecastFilters.procurementSource.value = '';
        forecastFilters.category.value = '';
        forecastFilters.search.value = '';
        [forecastFilters.warehouse, forecastFilters.action, forecastFilters.procurementSource, forecastFilters.category].forEach(el => {
            if ($(el).data('select2')) $(el).val('').trigger('change.select2');
        });
        forecastDt.ajax.reload();
    });

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', () => {
            dt.columns.adjust();
            forecastDt.columns.adjust();
        });
    });
});
</script>
@endpush
