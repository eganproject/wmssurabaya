@extends('layouts.admin')

@section('title', 'Laporan Stok')
@section('page_title', 'Laporan Stok')

@push('styles')
<style>
    .stock-report-filter label {
        font-size: .75rem;
        color: #7e8299;
        margin-bottom: .35rem;
    }
    .stock-report-kpi {
        border: 1px solid #edf0f5;
        border-radius: .85rem;
        background: #fff;
        height: 100%;
    }
    .stock-report-kpi .value {
        font-size: 1.65rem;
        font-weight: 800;
        line-height: 1.15;
    }
    .stock-report-kpi .label {
        color: #7e8299;
        font-size: .82rem;
        font-weight: 600;
    }
    .stock-report-kpi .hint {
        color: #a1a5b7;
        font-size: .72rem;
        margin-top: .3rem;
    }
    .stock-report-item {
        min-width: 220px;
    }
    .stock-report-tabs {
        gap: .5rem;
    }
    .stock-report-tabs .nav-link {
        border: 1px solid #e4e6ef;
        border-radius: .75rem;
        color: #5e6278;
        font-weight: 700;
        padding: .8rem 1.15rem;
    }
    .stock-report-tabs .nav-link.active {
        border-color: #009ef7;
        background: #009ef7;
        color: #fff;
        box-shadow: 0 .35rem .9rem rgba(0, 158, 247, .16);
    }
    .movement-definition {
        border-left: 4px solid var(--movement-color, #a1a5b7);
        border-radius: .65rem;
        background: #f9f9f9;
        height: 100%;
        padding: 1rem 1.1rem;
    }
    .movement-definition .title {
        color: #181c32;
        font-size: .8rem;
        font-weight: 800;
        margin-bottom: .2rem;
    }
    .movement-definition .description {
        color: #7e8299;
        font-size: .72rem;
        line-height: 1.45;
    }
    .movement-meta {
        min-width: 170px;
    }
    @media (max-width: 575px) {
        .stock-report-actions {
            width: 100%;
        }
        .stock-report-actions .btn {
            flex: 1 1 0;
        }
    }
</style>
@endpush

@section('content')
<div class="card mb-6">
    <div class="card-body py-4">
        <ul class="nav stock-report-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="stock-position-tab" data-bs-toggle="tab" data-bs-target="#stock-position-pane" type="button" role="tab" aria-controls="stock-position-pane" aria-selected="true">
                    <i class="fa-solid fa-boxes-stacked me-2"></i> Posisi Stok
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stock-movement-tab" data-bs-toggle="tab" data-bs-target="#stock-movement-pane" type="button" role="tab" aria-controls="stock-movement-pane" aria-selected="false">
                    <i class="fa-solid fa-chart-line me-2"></i> Analisis Pergerakan
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content">
<div class="tab-pane fade show active" id="stock-position-pane" role="tabpanel" aria-labelledby="stock-position-tab">
<div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 mb-6">
    <i class="fa-solid fa-circle-info fs-2x text-primary me-4"></i>
    <div>
        <div class="fw-bold text-gray-800">Laporan posisi stok</div>
        <div class="text-gray-700 fs-7">
            Gunakan laporan ini untuk melihat stok per gudang, status stok, lokasi, safety stock, dan selisih terhadap batas pengaman.
        </div>
    </div>
</div>

<div class="card mb-6">
    <div class="card-body py-5">
        <div class="row g-3 align-items-end stock-report-filter">
            <div class="col-xl-2 col-md-4">
                <label>Gudang</label>
                <select id="filter_warehouse" class="form-select form-select-solid">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>
                            {{ $warehouse->type === 'bulk' ? 'Gudang Besar' : 'Gudang Kecil' }} - {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Kategori</label>
                <select id="filter_category" class="form-select form-select-solid">
                    <option value="">Semua Kategori</option>
                    <option value="0">Tanpa Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Status Stok</label>
                <select id="filter_status" class="form-select form-select-solid">
                    <option value="">Semua Status</option>
                    <option value="empty">Stok Habis</option>
                    <option value="low">Stok Menipis</option>
                    <option value="safe">Aman</option>
                    <option value="has_stock">Ada Stok</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-4">
                <label>Status Produk</label>
                <select id="filter_item_status" class="form-select form-select-solid">
                    <option value="1" selected>Produk Aktif</option>
                    <option value="0">Produk Nonaktif</option>
                    <option value="">Semua Produk</option>
                </select>
            </div>
            <div class="col-xl-3 col-md-6">
                <label>Cari SKU, nama, gudang, kategori, atau lokasi</label>
                <input id="filter_search" class="form-control form-control-solid" placeholder="Tekan Enter untuk mencari">
            </div>
            <div class="col-xl-1 col-md-3">
                <label>Limit</label>
                <select id="filter_limit" class="form-select form-select-solid">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="col-xl-2 col-md-3 d-flex gap-2 stock-report-actions">
                <button id="filter_apply" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Terapkan</button>
                <button id="filter_reset" class="btn btn-light">Reset</button>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="stock-report-kpi p-5">
            <div class="label">Baris Stok</div>
            <div class="value" id="kpi_total_rows">0</div>
            <div class="hint">Item per gudang</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="stock-report-kpi p-5">
            <div class="label">Total Qty Stok</div>
            <div class="value text-primary" id="kpi_total_stock">0</div>
            <div class="hint">Sesuai filter aktif</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="stock-report-kpi p-5">
            <div class="label">Stok Habis</div>
            <div class="value text-danger" id="kpi_empty_rows">0</div>
            <div class="hint">Stok 0 atau kurang</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="stock-report-kpi p-5">
            <div class="label">Stok Menipis</div>
            <div class="value text-warning" id="kpi_low_rows">0</div>
            <div class="hint">Di bawah safety stock</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="stock-report-kpi p-5">
            <div class="label">Stok Aman</div>
            <div class="value text-success" id="kpi_safe_rows">0</div>
            <div class="hint">Di atas batas pengaman</div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="stock-report-kpi p-5">
            <div class="label">Gap Safety</div>
            <div class="value" id="kpi_safety_gap">0</div>
            <div class="hint">Akumulasi kekurangan</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div>
            <h3 class="fw-bolder mb-1">Detail Laporan Stok</h3>
            <div class="text-muted fs-7">Menampilkan posisi stok item berdasarkan gudang dan filter yang dipilih.</div>
        </div>
    </div>
    <div class="card-body py-5">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-7 gy-4" id="stock_report_table">
                <thead>
                    <tr class="text-muted text-uppercase">
                        <th>SKU / Item</th>
                        <th>Gudang</th>
                        <th>Kategori</th>
                        <th>Lokasi</th>
                        <th class="text-end">Stok</th>
                        <th class="text-end">Kemasan</th>
                        <th class="text-end">Safety</th>
                        <th class="text-end">Gap</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<div class="tab-pane fade" id="stock-movement-pane" role="tabpanel" aria-labelledby="stock-movement-tab">
    <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-5 mb-5">
        <i class="fa-solid fa-chart-simple fs-2x text-info me-4"></i>
        <div>
            <div class="fw-bold text-gray-800">Analisis kecepatan pergerakan stok</div>
            <div class="text-gray-700 fs-7">
                Klasifikasi dihitung dari jumlah barang keluar operasional pada periode terpilih. Retur dan perpindahan internal antar-gudang tidak dihitung.
            </div>
        </div>
    </div>

    <div class="row g-3 mb-5">
        <div class="col-xl-3 col-md-6">
            <div class="movement-definition" style="--movement-color:#009ef7">
                <div class="title"><span class="badge badge-light-primary me-1">Fast</span> Kontributor utama</div>
                <div class="description">SKU dengan kontribusi kumulatif awal hingga 70% dari seluruh qty keluar.</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="movement-definition" style="--movement-color:#50cd89">
                <div class="title"><span class="badge badge-light-success me-1">Medium</span> Pergerakan menengah</div>
                <div class="description">SKU pada lapisan kontribusi berikutnya, dari 70% hingga 90% qty keluar.</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="movement-definition" style="--movement-color:#ffc700">
                <div class="title"><span class="badge badge-light-warning me-1">Slow</span> Pergerakan rendah</div>
                <div class="description">SKU bergerak pada sisa kontribusi setelah 90% qty keluar.</div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="movement-definition" style="--movement-color:#f1416c">
                <div class="title"><span class="badge badge-light-danger me-1">Non-moving</span> Tidak bergerak</div>
                <div class="description">Tidak memiliki barang keluar operasional selama periode terpilih.</div>
            </div>
        </div>
    </div>

    <div class="card mb-6">
        <div class="card-body py-5">
            <div class="row g-3 align-items-end stock-report-filter">
                <div class="col-xl-2 col-md-4">
                    <label>Gudang</label>
                    <select id="movement_warehouse" class="form-select form-select-solid">
                        <option value="">Semua Gudang</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>
                                {{ $warehouse->type === 'bulk' ? 'Gudang Besar' : 'Gudang Kecil' }} - {{ $warehouse->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label>Kategori</label>
                    <select id="movement_category" class="form-select form-select-solid">
                        <option value="">Semua Kategori</option>
                        <option value="0">Tanpa Kategori</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label>Klasifikasi</label>
                    <select id="movement_class" class="form-select form-select-solid">
                        <option value="">Semua Pergerakan</option>
                        <option value="fast">Fast moving</option>
                        <option value="medium">Medium moving</option>
                        <option value="slow">Slow moving</option>
                        <option value="non_moving">Non-moving</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label>Status Produk</label>
                    <select id="movement_item_status" class="form-select form-select-solid">
                        <option value="1" selected>Produk Aktif</option>
                        <option value="0">Produk Nonaktif</option>
                        <option value="">Semua Produk</option>
                    </select>
                </div>
                <div class="col-xl-2 col-md-4">
                    <label>Dari Tanggal</label>
                    <input id="movement_date_from" type="date" class="form-control form-control-solid" value="{{ now()->subDays(29)->toDateString() }}">
                </div>
                <div class="col-xl-2 col-md-4">
                    <label>Sampai Tanggal</label>
                    <input id="movement_date_to" type="date" class="form-control form-control-solid" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-xl-4 col-md-6">
                    <label>Cari SKU, nama, gudang, kategori, atau lokasi</label>
                    <input id="movement_search" class="form-control form-control-solid" placeholder="Tekan Enter untuk mencari">
                </div>
                <div class="col-xl-1 col-md-3">
                    <label>Limit</label>
                    <select id="movement_limit" class="form-select form-select-solid">
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-xl-3 col-md-3 d-flex gap-2 stock-report-actions">
                    <button id="movement_apply" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Terapkan</button>
                    <button id="movement_reset" class="btn btn-light">Reset</button>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-6">
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stock-report-kpi p-5">
                <div class="label">SKU Dianalisis</div>
                <div class="value" id="movement_kpi_total">0</div>
                <div class="hint" id="movement_period_hint">Periode aktif</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stock-report-kpi p-5">
                <div class="label">Qty Keluar</div>
                <div class="value text-dark" id="movement_kpi_outbound">0</div>
                <div class="hint">Total operasional</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stock-report-kpi p-5">
                <div class="label">Fast Moving</div>
                <div class="value text-primary" id="movement_kpi_fast">0</div>
                <div class="hint">Kontributor utama</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stock-report-kpi p-5">
                <div class="label">Medium Moving</div>
                <div class="value text-success" id="movement_kpi_medium">0</div>
                <div class="hint">Kontribusi menengah</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stock-report-kpi p-5">
                <div class="label">Slow Moving</div>
                <div class="value text-warning" id="movement_kpi_slow">0</div>
                <div class="hint">Masih bergerak rendah</div>
            </div>
        </div>
        <div class="col-xl-2 col-md-4 col-6">
            <div class="stock-report-kpi p-5">
                <div class="label">Non-moving</div>
                <div class="value text-danger" id="movement_kpi_non_moving">0</div>
                <div class="hint">Tanpa barang keluar</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <div>
                <h3 class="fw-bolder mb-1">Detail Pergerakan SKU</h3>
                <div class="text-muted fs-7">Klik judul kolom untuk mengurutkan data naik atau turun.</div>
            </div>
        </div>
        <div class="card-body py-5">
            <div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-7 gy-4" id="stock_movement_table">
                    <thead>
                        <tr class="text-muted text-uppercase">
                            <th>SKU / Item</th>
                            <th>Gudang / Lokasi</th>
                            <th>Klasifikasi</th>
                            <th class="text-end">Stok Saat Ini</th>
                            <th class="text-end">Qty Keluar</th>
                            <th class="text-end">Rata-rata / Hari</th>
                            <th class="text-end">Kontribusi</th>
                            <th class="text-end">Frekuensi</th>
                            <th class="text-end">Days Cover</th>
                            <th>Terakhir Keluar</th>
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
    const tableEl = $('#stock_report_table');
    const filters = {
        warehouse: document.getElementById('filter_warehouse'),
        category: document.getElementById('filter_category'),
        status: document.getElementById('filter_status'),
        itemStatus: document.getElementById('filter_item_status'),
        search: document.getElementById('filter_search'),
        limit: document.getElementById('filter_limit'),
    };
    const number = value => Number(value || 0).toLocaleString('id-ID');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const statusMap = {
        empty: ['Stok Habis', 'danger'],
        low: ['Stok Menipis', 'warning'],
        safe: ['Aman', 'success'],
    };
    const statusBadge = row => {
        const [label, color] = statusMap[row.status_key] || [row.status_label || '-', 'secondary'];
        return `<span class="badge badge-light-${color}">${escapeHtml(label)}</span>`;
    };

    if (!tableEl.length || !$.fn.DataTable) {
        console.error('DataTables unavailable');
        return;
    }

    if ($.fn.select2) {
        [filters.warehouse, filters.category, filters.status, filters.itemStatus].forEach(el => $(el).select2({width: '100%', allowClear: el !== filters.warehouse && el !== filters.itemStatus}));
    }

    function requestData(params) {
        params.warehouse_id = filters.warehouse.value;
        params.category_id = filters.category.value;
        params.status = filters.status.value;
        params.is_active = filters.itemStatus.value;
        params.q = filters.search.value;
    }

    function updateSummary(summary = {}) {
        document.getElementById('kpi_total_rows').textContent = number(summary.total_rows);
        document.getElementById('kpi_total_stock').textContent = number(summary.total_stock);
        document.getElementById('kpi_empty_rows').textContent = number(summary.empty_rows);
        document.getElementById('kpi_low_rows').textContent = number(summary.low_rows);
        document.getElementById('kpi_safe_rows').textContent = number(summary.safe_rows);
        document.getElementById('kpi_safety_gap').textContent = number(summary.safety_gap);
    }

    const dt = tableEl.DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        order: [],
        pageLength: Number(filters.limit?.value || 10),
        ajax: {
            url: dataUrl,
            data: requestData,
            dataSrc: json => {
                updateSummary(json.summary || {});
                return json.data || [];
            },
            error: xhr => window.AppSwal?.error(Object.values(xhr.responseJSON?.errors || {}).flat().join('\n') || 'Gagal memuat laporan stok.'),
        },
        columns: [
            {data: null, className: 'stock-report-item', render: row => {
                const bundle = row.is_bundle ? '<span class="badge badge-light-info ms-2">Bundle</span>' : '';
                const active = `<span class="badge ${row.is_active ? 'badge-light-success' : 'badge-light-danger'} ms-2">${row.is_active ? 'Aktif' : 'Nonaktif'}</span>`;
                return `<div class="fw-bold">${escapeHtml(row.sku)}${bundle}${active}</div><div>${escapeHtml(row.name)}</div>`;
            }},
            {data: null, render: row => `<div class="fw-bold">${escapeHtml(row.warehouse)}</div><div class="text-muted fs-8">${escapeHtml(row.warehouse_type)}</div>`},
            {data: 'category', render: value => escapeHtml(value)},
            {data: 'location', render: value => escapeHtml(value || '-')},
            {data: 'stock', className: 'text-end', render: (value, type, row) => `<span class="fw-bolder">${number(value)}</span> <span class="text-muted">${escapeHtml(row.base_unit)}</span>`},
            {data: null, className: 'text-end', render: row => {
                if (!row.package_unit) return '<span class="text-muted">-</span>';
                return `<div>${number(row.package_qty)} ${escapeHtml(row.package_unit)}</div><div class="text-muted fs-8">sisa ${number(row.package_remainder)} ${escapeHtml(row.base_unit)}</div>`;
            }},
            {data: 'safety_stock', className: 'text-end', render: value => number(value)},
            {data: 'gap_to_safety', className: 'text-end', render: value => Number(value) > 0 ? `<span class="text-danger fw-bolder">${number(value)}</span>` : '<span class="text-success">0</span>'},
            {data: null, render: statusBadge},
        ],
        language: {
            processing: 'Memuat laporan stok...',
            emptyTable: 'Tidak ada data stok sesuai filter.',
        },
    });

    const reload = () => dt.ajax.reload();
    document.getElementById('filter_apply').addEventListener('click', reload);
    filters.search.addEventListener('keyup', event => {
        if (event.key === 'Enter') reload();
    });
    [filters.warehouse, filters.category, filters.status, filters.itemStatus].forEach(el => el?.addEventListener('change', reload));
    filters.limit?.addEventListener('change', () => dt.page.len(Number(filters.limit.value || 10)).draw());
    document.getElementById('filter_reset').addEventListener('click', () => {
        filters.warehouse.value = '';
        filters.category.value = '';
        filters.status.value = '';
        filters.itemStatus.value = '1';
        filters.search.value = '';
        filters.limit.value = '10';
        [filters.warehouse, filters.category, filters.status, filters.itemStatus].forEach(el => {
            if ($(el).data('select2')) $(el).val(el === filters.itemStatus ? '1' : '').trigger('change.select2');
        });
        dt.page.len(10).draw();
        reload();
    });

    const movementDataUrl = @json($movementDataUrl);
    const movementTableEl = $('#stock_movement_table');
    const movementFilters = {
        warehouse: document.getElementById('movement_warehouse'),
        category: document.getElementById('movement_category'),
        movement: document.getElementById('movement_class'),
        itemStatus: document.getElementById('movement_item_status'),
        dateFrom: document.getElementById('movement_date_from'),
        dateTo: document.getElementById('movement_date_to'),
        search: document.getElementById('movement_search'),
        limit: document.getElementById('movement_limit'),
    };
    const movementDefaults = {
        warehouse: movementFilters.warehouse.value,
        dateFrom: movementFilters.dateFrom.value,
        dateTo: movementFilters.dateTo.value,
    };
    const movementMap = {
        fast: ['Fast moving', 'primary'],
        medium: ['Medium moving', 'success'],
        slow: ['Slow moving', 'warning'],
        non_moving: ['Non-moving', 'danger'],
    };
    let movementDt = null;

    function movementRequestData(params) {
        params.warehouse_id = movementFilters.warehouse.value;
        params.category_id = movementFilters.category.value;
        params.movement = movementFilters.movement.value;
        params.is_active = movementFilters.itemStatus.value;
        params.date_from = movementFilters.dateFrom.value;
        params.date_to = movementFilters.dateTo.value;
        params.q = movementFilters.search.value;
    }

    function updateMovementSummary(summary = {}) {
        document.getElementById('movement_kpi_total').textContent = number(summary.total_sku);
        document.getElementById('movement_kpi_outbound').textContent = number(summary.total_outbound_qty);
        document.getElementById('movement_kpi_fast').textContent = number(summary.fast_sku);
        document.getElementById('movement_kpi_medium').textContent = number(summary.medium_sku);
        document.getElementById('movement_kpi_slow').textContent = number(summary.slow_sku);
        document.getElementById('movement_kpi_non_moving').textContent = number(summary.non_moving_sku);
        document.getElementById('movement_period_hint').textContent = `${number(summary.period_days)} hari (${summary.date_from || '-'} s/d ${summary.date_to || '-'})`;
    }

    function movementBadge(row) {
        const [label, color] = movementMap[row.movement_key] || [row.movement_label || '-', 'secondary'];
        return `<span class="badge badge-light-${color}">${escapeHtml(label)}</span>`;
    }

    function formatDate(value) {
        if (!value) return '<span class="text-muted">Tidak ada</span>';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return escapeHtml(value);
        return date.toLocaleDateString('id-ID', {day: '2-digit', month: 'short', year: 'numeric'});
    }

    function initializeMovementTable() {
        if (movementDt || !movementTableEl.length) {
            movementDt?.columns.adjust();
            return;
        }

        if ($.fn.select2) {
            [movementFilters.warehouse, movementFilters.category, movementFilters.movement, movementFilters.itemStatus]
                .forEach(el => $(el).select2({
                    width: '100%',
                    allowClear: el !== movementFilters.warehouse && el !== movementFilters.itemStatus,
                }));
        }

        movementDt = movementTableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [],
            pageLength: Number(movementFilters.limit.value || 10),
            ajax: {
                url: movementDataUrl,
                data: movementRequestData,
                dataSrc: json => {
                    updateMovementSummary(json.summary || {});
                    return json.data || [];
                },
                error: xhr => window.AppSwal?.error(Object.values(xhr.responseJSON?.errors || {}).flat().join('\n') || 'Gagal memuat analisis pergerakan stok.'),
            },
            columns: [
                {data: null, className: 'stock-report-item', render: row => {
                    const active = `<span class="badge ${row.is_active ? 'badge-light-success' : 'badge-light-danger'} ms-2">${row.is_active ? 'Aktif' : 'Nonaktif'}</span>`;
                    return `<div class="fw-bold">${escapeHtml(row.sku)}${active}</div><div>${escapeHtml(row.name)}</div><div class="text-muted fs-8">${escapeHtml(row.category)}</div>`;
                }},
                {data: null, className: 'movement-meta', render: row => `<div class="fw-bold">${escapeHtml(row.warehouse)}</div><div class="text-muted fs-8">${escapeHtml(row.warehouse_type)} · ${escapeHtml(row.location || '-')}</div>`},
                {data: null, render: movementBadge},
                {data: 'stock', className: 'text-end', render: (value, type, row) => {
                    const low = Number(row.safety_stock) > 0 && Number(value) <= Number(row.safety_stock);
                    return `<div class="fw-bolder ${low ? 'text-danger' : ''}">${number(value)} <span class="text-muted fw-normal">${escapeHtml(row.base_unit)}</span></div><div class="text-muted fs-8">Safety ${number(row.safety_stock)}</div>`;
                }},
                {data: 'outbound_qty', className: 'text-end', render: (value, type, row) => `<span class="fw-bolder">${number(value)}</span> <span class="text-muted">${escapeHtml(row.base_unit)}</span>`},
                {data: 'average_daily_outbound', className: 'text-end', render: value => Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 2})},
                {data: 'contribution_percent', className: 'text-end', render: value => `${Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 2})}%`},
                {data: null, className: 'text-end', render: row => `<div>${number(row.outbound_transactions)} transaksi</div><div class="text-muted fs-8">${number(row.active_days)} hari aktif</div>`},
                {data: 'days_cover', className: 'text-end', render: value => value === null ? '<span class="text-muted">-</span>' : `${Number(value).toLocaleString('id-ID', {maximumFractionDigits: 1})} hari`},
                {data: 'last_outbound_at', render: formatDate},
            ],
            language: {
                processing: 'Menghitung pergerakan stok...',
                emptyTable: 'Tidak ada data pergerakan sesuai filter.',
                zeroRecords: 'Data pergerakan tidak ditemukan.',
            },
        });
    }

    const reloadMovement = () => {
        if (movementDt) {
            movementDt.ajax.reload();
        } else {
            initializeMovementTable();
        }
    };
    document.getElementById('movement_apply').addEventListener('click', reloadMovement);
    movementFilters.search.addEventListener('keyup', event => {
        if (event.key === 'Enter') reloadMovement();
    });
    [movementFilters.warehouse, movementFilters.category, movementFilters.movement, movementFilters.itemStatus]
        .forEach(el => el?.addEventListener('change', () => movementDt?.ajax.reload()));
    movementFilters.limit.addEventListener('change', () => {
        initializeMovementTable();
        movementDt.page.len(Number(movementFilters.limit.value || 10)).draw();
    });
    document.getElementById('movement_reset').addEventListener('click', () => {
        movementFilters.warehouse.value = movementDefaults.warehouse;
        movementFilters.category.value = '';
        movementFilters.movement.value = '';
        movementFilters.itemStatus.value = '1';
        movementFilters.dateFrom.value = movementDefaults.dateFrom;
        movementFilters.dateTo.value = movementDefaults.dateTo;
        movementFilters.search.value = '';
        movementFilters.limit.value = '10';
        [movementFilters.warehouse, movementFilters.category, movementFilters.movement, movementFilters.itemStatus].forEach(el => {
            if ($(el).data('select2')) $(el).val(el === movementFilters.warehouse ? movementDefaults.warehouse : (el === movementFilters.itemStatus ? '1' : '')).trigger('change.select2');
        });
        if (movementDt) {
            movementDt.page.len(10).draw();
        } else {
            initializeMovementTable();
        }
    });

    document.getElementById('stock-movement-tab').addEventListener('shown.bs.tab', () => {
        window.history.replaceState(null, '', '#stock-movement-pane');
        initializeMovementTable();
    });
    document.getElementById('stock-position-tab').addEventListener('shown.bs.tab', () => {
        window.history.replaceState(null, '', '#stock-position-pane');
        dt.columns.adjust();
    });

    initializeMovementTable();

    if (window.location.hash === '#stock-movement-pane' && window.bootstrap?.Tab) {
        window.bootstrap.Tab.getOrCreateInstance(document.getElementById('stock-movement-tab')).show();
    }
});
</script>
@endpush
