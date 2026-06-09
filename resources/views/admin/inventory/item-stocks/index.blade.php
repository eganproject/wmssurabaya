@extends('layouts.admin')

@section('title', 'Stok Item')
@section('page_title', 'Stok Item')

@section('content')
<style>
    .inventory-toolbar { gap: .75rem; }
    .inventory-toolbar .search-box { min-width: 260px; flex: 1 1 320px; }
    .inventory-toolbar .warehouse-filter { min-width: 230px; flex: 0 1 280px; }
    .stock-number { font-size: 1.05rem; line-height: 1.2; }
    .stock-meta { font-size: .78rem; color: #7e8299; margin-top: .2rem; }
    @media (max-width: 767.98px) {
        .inventory-toolbar > * { width: 100% !important; max-width: none !important; }
        .inventory-toolbar .btn { justify-content: center; }
    }
</style>

<div class="card card-flush">
    <div class="card-header border-0 pt-6 pb-3">
        <div class="card-title d-block">
            <h2 class="fw-bolder text-gray-900 mb-1">Posisi Stok per Gudang</h2>
            <div class="text-muted fs-7">Pantau stok tersedia, satuan penyimpanan, lokasi rak, dan batas minimum item.</div>
        </div>
    </div>

    <div class="card-body pt-2">
        <div class="d-flex flex-wrap align-items-center inventory-toolbar mb-6">
            <div class="position-relative search-box">
                <span class="svg-icon svg-icon-2 position-absolute top-50 translate-middle-y ms-5">
                    <i class="fas fa-search text-gray-500"></i>
                </span>
                <input type="text" class="form-control form-control-solid ps-12" placeholder="Cari SKU, nama item, atau lokasi..." data-kt-filter="search" />
            </div>

            <select class="form-select form-select-solid warehouse-filter" id="filter_warehouse">
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" data-type="{{ $warehouse->type }}" @selected($warehouse->is_default)>
                        {{ $warehouse->name }} ({{ $warehouse->type === \App\Models\Warehouse::TYPE_BULK ? 'Gudang Besar' : 'Gudang Kecil' }})
                    </option>
                @endforeach
            </select>

            <button type="button" class="btn btn-light-primary" id="btn_export_item_stocks">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </button>
        </div>

        <div class="alert alert-primary d-flex align-items-start p-4 mb-6">
            <i class="fas fa-info-circle fs-4 me-3 mt-1"></i>
            <div>
                <div class="fw-bold" id="warehouse_hint_title">Informasi satuan gudang</div>
                <div class="fs-7" id="warehouse_hint_text">Stok ditampilkan sesuai satuan operasional gudang yang dipilih.</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="item_stocks_table">
                <thead>
                    <tr class="text-start text-gray-500 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Item</th>
                        <th>Lokasi</th>
                        <th class="text-end">Batas Minimum</th>
                        <th class="text-end">Stok Tersedia</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
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
    const dataUrl = '{{ route('admin.inventory.item-stocks.data') }}';
    const exportUrl = '{{ route('admin.inventory.item-stocks.export') }}';
    const showUrlTpl = '{{ route('admin.inventory.item-stocks.show', ':id') }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#item_stocks_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const exportBtn = document.getElementById('btn_export_item_stocks');
        const warehouseEl = document.getElementById('filter_warehouse');
        const hintTitle = document.getElementById('warehouse_hint_title');
        const hintText = document.getElementById('warehouse_hint_text');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const escapeHtml = value => $('<div>').text(value ?? '').html();
        const formatNumber = value => Number(value || 0).toLocaleString('id-ID');

        const updateWarehouseHint = () => {
            const option = warehouseEl?.selectedOptions?.[0];
            const isBulk = option?.dataset?.type === 'bulk';
            if (hintTitle) hintTitle.textContent = isBulk ? 'Gudang besar menggunakan satuan koli' : 'Gudang kecil menggunakan satuan dasar';
            if (hintText) {
                hintText.textContent = isBulk
                    ? 'Jumlah utama ditampilkan dalam koli. Sisa pecahan tetap diinformasikan dalam satuan dasar.'
                    : 'Jumlah stok ditampilkan dalam PCS atau SET sesuai satuan dasar item.';
            }
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [],
            pageLength: 10,
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: params => {
                    params.q = searchInput?.value || '';
                    params.warehouse_id = warehouseEl?.value || '';
                }
            },
            columns: [
                {
                    data: 'name',
                    render: (value, type, row) => `
                        <div class="d-flex flex-column">
                            <span class="fw-bolder text-gray-900">${escapeHtml(value)}</span>
                            <span class="text-muted fs-7">${escapeHtml(row.sku)} · ${row.is_bundle ? 'Bundle' : 'Item reguler'}</span>
                        </div>`
                },
                {
                    data: 'location',
                    render: value => value
                        ? `<span class="fw-semibold text-gray-700">${escapeHtml(value)}</span>`
                        : '<span class="text-muted">Belum diatur</span>'
                },
                {
                    data: 'safety_stock',
                    className: 'text-end',
                    render: (value, type, row) => `
                        <span class="fw-semibold">${formatNumber(value)}</span>
                        <span class="text-muted fs-8 ms-1">${escapeHtml(row.base_unit)}</span>`
                },
                {
                    data: 'stock',
                    className: 'text-end',
                    render: (value, type, row) => {
                        const selectedType = warehouseEl?.selectedOptions?.[0]?.dataset?.type;
                        if (selectedType === 'bulk' && row.package_unit) {
                            return `<div class="stock-number fw-bolder text-gray-900">${formatNumber(row.package_qty)} ${escapeHtml(row.package_unit)}</div>
                                <div class="stock-meta">Total ${formatNumber(value)} ${escapeHtml(row.base_unit)}
                                    ${row.package_remainder ? ` · sisa ${formatNumber(row.package_remainder)} ${escapeHtml(row.base_unit)}` : ''}
                                </div>`;
                        }
                        return `<div class="stock-number fw-bolder text-gray-900">${formatNumber(value)} ${escapeHtml(row.base_unit)}</div>
                            ${row.is_bundle ? '<div class="stock-meta">Stok virtual bundle</div>' : ''}`;
                    }
                },
                {
                    data: 'stock_status',
                    render: status => {
                        if (status === 'empty') return '<span class="badge badge-light-danger">Stok Habis</span>';
                        if (status === 'low') return '<span class="badge badge-light-warning">Stok Menipis</span>';
                        return '<span class="badge badge-light-success">Stok Aman</span>';
                    }
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: data => {
                        const url = `${showUrlTpl.replace(':id', data)}?warehouse_id=${encodeURIComponent(warehouseEl?.value || '')}`;
                        return `<a href="${url}" class="btn btn-sm btn-light-primary">
                            <i class="fas fa-file-alt me-1"></i>Lihat Kartu Stok
                        </a>`;
                    }
                },
            ],
            language: {
                emptyTable: 'Belum ada data item.',
                zeroRecords: 'Item tidak ditemukan.',
                processing: 'Memuat data...',
            }
        });

        let searchTimer;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => dt.ajax.reload(), 300);
        });
        warehouseEl?.addEventListener('change', () => {
            updateWarehouseHint();
            dt.ajax.reload();
        });
        exportBtn?.addEventListener('click', () => {
            const params = new URLSearchParams({ warehouse_id: warehouseEl?.value || '' });
            const query = searchInput?.value?.trim() || '';
            if (query) params.set('q', query);
            window.location.href = `${exportUrl}?${params.toString()}`;
        });

        updateWarehouseHint();
    });
</script>
@endpush
