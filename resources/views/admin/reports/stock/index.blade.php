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
        [filters.warehouse, filters.category, filters.status].forEach(el => $(el).select2({width: '100%', allowClear: el !== filters.warehouse}));
    }

    function requestData(params) {
        params.warehouse_id = filters.warehouse.value;
        params.category_id = filters.category.value;
        params.status = filters.status.value;
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
                return `<div class="fw-bold">${escapeHtml(row.sku)}${bundle}</div><div>${escapeHtml(row.name)}</div>`;
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
    [filters.warehouse, filters.category, filters.status].forEach(el => el?.addEventListener('change', reload));
    filters.limit?.addEventListener('change', () => dt.page.len(Number(filters.limit.value || 10)).draw());
    document.getElementById('filter_reset').addEventListener('click', () => {
        filters.warehouse.value = '';
        filters.category.value = '';
        filters.status.value = '';
        filters.search.value = '';
        filters.limit.value = '10';
        [filters.warehouse, filters.category, filters.status].forEach(el => {
            if ($(el).data('select2')) $(el).val('').trigger('change.select2');
        });
        dt.page.len(10).draw();
        reload();
    });
});
</script>
@endpush
