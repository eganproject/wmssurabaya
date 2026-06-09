@extends('layouts.admin')

@section('title', 'Item Stocks')
@section('page_title', 'Item Stocks')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search items" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <select class="form-select form-select-solid w-200px me-3" id="filter_warehouse">
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-light-primary" id="btn_export_item_stocks">Export Excel</button>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="item_stocks_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Lokasi</th>
                        <th class="text-end">Safety Stock</th>
                        <th class="text-end">Stok</th>
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

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.warehouse_id = warehouseEl?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'is_bundle', render: v => v
                    ? '<span class="badge badge-light-primary">Bundle</span>'
                    : '<span class="badge badge-light-secondary">Regular</span>' },
                { data: 'location' },
                { data: 'safety_stock', className: 'text-end' },
                { data: 'stock', className: 'text-end', render: (v, t, row) => row.is_bundle
                    ? `<span class="fw-bold">${v}</span> <span class="text-muted fs-8">virtual</span>`
                    : `<span class="fw-bold">${v}</span>` },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data) => {
                    const url = `${showUrlTpl.replace(':id', data)}?warehouse_id=${encodeURIComponent(warehouseEl?.value || '')}`;
                    return `<a href="${url}" class="btn btn-sm btn-light btn-active-light-primary">Detail</a>`;
                }},
            ]
        });

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        warehouseEl?.addEventListener('change', reloadTable);
        exportBtn?.addEventListener('click', () => {
            const q = searchInput?.value?.trim() || '';
            const params = new URLSearchParams({warehouse_id: warehouseEl?.value || ''});
            if (q) params.set('q', q);
            const url = `${exportUrl}?${params.toString()}`;
            window.location.href = url;
        });
    });
</script>
@endpush
