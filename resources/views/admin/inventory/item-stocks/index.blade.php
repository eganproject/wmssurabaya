@extends('layouts.admin')

@section('title', 'Stok Item')
@section('page_title', 'Stok Item')

@section('content')
<style>
    .inventory-toolbar { gap: .75rem; }
    .inventory-toolbar .search-box { min-width: 260px; flex: 1 1 320px; }
    .stock-number { font-size: 1.05rem; line-height: 1.2; white-space: nowrap; }
    .stock-meta { font-size: .78rem; color: #7e8299; margin-top: .2rem; white-space: nowrap; }
    #item_stocks_table th.wh-col, #item_stocks_table td.wh-col { min-width: 170px; }
    #item_stocks_table td.wh-col { border-left: 1px dashed #e4e6ef; }
    #item_stocks_table th.wh-col { border-left: 1px dashed #e4e6ef; }
    .wh-col-unit { font-size: .72rem; text-transform: none; font-weight: 500; color: #a1a5b7; }
    @media (max-width: 767.98px) {
        .inventory-toolbar > * { width: 100% !important; max-width: none !important; }
        .inventory-toolbar .btn { justify-content: center; }
    }
</style>

<div class="card card-flush">
    <div class="card-header border-0 pt-6 pb-3">
        <div class="card-title d-block">
            <h2 class="fw-bolder text-gray-900 mb-1">Posisi Stok per Gudang</h2>
            <div class="text-muted fs-7">Stok setiap gudang tampil berdampingan, lengkap dengan lokasi rak dan batas minimum per gudang.</div>
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

            <select class="form-select form-select-solid w-175px" id="filter_item_status" aria-label="Status produk">
                <option value="1" selected>Produk Aktif</option>
                <option value="0">Produk Nonaktif</option>
                <option value="">Semua Produk</option>
            </select>

            <button type="button" class="btn btn-light-primary" id="btn_export_item_stocks">
                <i class="fas fa-file-excel me-2"></i>Export Excel
            </button>
        </div>

        <div class="d-flex flex-wrap gap-4 text-muted fs-7 mb-4">
            <span><i class="fas fa-info-circle me-1"></i>Gudang Kecil ditampilkan dalam satuan dasar (PCS/SET).</span>
            <span>Gudang Besar ditampilkan dalam koli, sisa pecahan dalam satuan dasar.</span>
            <span>Total = jumlah stok semua gudang dalam satuan dasar.</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="item_stocks_table">
                <thead>
                    <tr class="text-start text-gray-500 fw-bolder fs-7 text-uppercase gs-0">
                        <th class="min-w-250px">Item</th>
                        @foreach($warehouses as $warehouse)
                            <th class="text-end wh-col">
                                {{ $warehouse->name }}
                                <div class="wh-col-unit">{{ $warehouse->type === \App\Models\Warehouse::TYPE_BULK ? 'Koli' : 'Satuan dasar' }}</div>
                            </th>
                        @endforeach
                        <th class="text-end wh-col">Total Stok<div class="wh-col-unit">Satuan dasar</div></th>
                        <th class="text-end min-w-150px">Kartu Stok</th>
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
    const warehouses = @json($warehouses->map(fn ($w) => ['id' => $w->id, 'name' => $w->name, 'type' => $w->type])->values());

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#item_stocks_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const exportBtn = document.getElementById('btn_export_item_stocks');
        const statusEl = document.getElementById('filter_item_status');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const escapeHtml = value => $('<div>').text(value ?? '').html();
        const formatNumber = value => Number(value || 0).toLocaleString('id-ID');

        const statusBadge = status => {
            if (status === 'empty') return '<span class="badge badge-light-danger">Habis</span>';
            if (status === 'low') return '<span class="badge badge-light-warning">Menipis</span>';
            return '<span class="badge badge-light-success">Aman</span>';
        };

        const renderWarehouseStock = (warehouse, row) => {
            const info = row.warehouses?.[warehouse.id];
            if (!info) return '<span class="text-muted">-</span>';

            const base = escapeHtml(row.base_unit);
            let main;
            let detail = '';
            if (warehouse.type === 'bulk' && row.package_unit) {
                main = `${formatNumber(info.package_qty)} ${escapeHtml(row.package_unit)}`;
                detail = `${formatNumber(info.stock)} ${base}${info.package_remainder ? ` · sisa ${formatNumber(info.package_remainder)} ${base}` : ''}`;
            } else {
                main = `${formatNumber(info.stock)} ${base}`;
            }

            const meta = [
                info.location ? `<i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(info.location)}` : '<span class="fst-italic">Lokasi belum diatur</span>',
                `Min ${formatNumber(info.safety_stock)}`,
            ].join(' · ');

            return `<div class="d-flex justify-content-end align-items-center gap-2">
                    ${statusBadge(info.stock_status)}
                    <span class="stock-number fw-bolder text-gray-900">${main}</span>
                </div>
                ${detail ? `<div class="stock-meta">${detail}</div>` : ''}
                <div class="stock-meta">${meta}</div>`;
        };

        const columns = [
            {
                data: 'name',
                render: (value, type, row) => `
                    <div class="d-flex flex-column">
                        <span class="fw-bolder text-gray-900">${escapeHtml(value)}</span>
                        <span class="text-muted fs-7">${escapeHtml(row.sku)}</span>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <span class="badge ${row.is_active ? 'badge-light-success' : 'badge-light-danger'}">${row.is_active ? 'Aktif' : 'Nonaktif'}</span>
                            <span class="badge badge-light">${row.is_bundle ? 'Bundle (stok virtual)' : 'Reguler'}</span>
                            ${row.package_unit ? `<span class="badge badge-light-info">1 ${escapeHtml(row.package_unit)} = ${formatNumber(row.package_conversion)} ${escapeHtml(row.base_unit)}</span>` : ''}
                        </div>
                    </div>`
            },
            ...warehouses.map(warehouse => ({
                data: null,
                orderable: false,
                className: 'text-end wh-col',
                render: (value, type, row) => renderWarehouseStock(warehouse, row),
            })),
            {
                data: 'total_stock',
                orderable: false,
                className: 'text-end wh-col',
                render: (value, type, row) => `<span class="stock-number fw-bolder text-primary">${formatNumber(value)} ${escapeHtml(row.base_unit)}</span>`
            },
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'text-end',
                render: data => `<div class="d-flex flex-column align-items-end gap-1">
                    ${warehouses.map(warehouse => {
                        const url = `${showUrlTpl.replace(':id', data)}?warehouse_id=${encodeURIComponent(warehouse.id)}`;
                        return `<a href="${url}" class="btn btn-sm btn-light-primary py-1 px-3 text-nowrap">
                            <i class="fas fa-file-alt me-1"></i>${escapeHtml(warehouse.name)}
                        </a>`;
                    }).join('')}
                </div>`
            },
        ];

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            ordering: false,
            pageLength: 10,
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: params => {
                    params.q = searchInput?.value || '';
                    params.is_active = statusEl?.value ?? '1';
                }
            },
            columns,
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
        statusEl?.addEventListener('change', () => dt.ajax.reload());
        exportBtn?.addEventListener('click', () => {
            const params = new URLSearchParams({
                is_active: statusEl?.value ?? '1',
            });
            const query = searchInput?.value?.trim() || '';
            if (query) params.set('q', query);
            window.location.href = `${exportUrl}?${params.toString()}`;
        });
    });
</script>
@endpush
