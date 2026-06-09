@extends('layouts.admin')

@section('title', 'Mutasi Stok')
@section('page_title', 'Mutasi Stok')

@section('content')
<style>
    .mutation-filters { gap: .75rem; }
    .mutation-filters .search-box { min-width: 260px; flex: 1 1 320px; }
    .mutation-filters .warehouse-filter { min-width: 210px; }
    .stock-flow-line { min-width: 210px; }
    @media (max-width: 991.98px) {
        .mutation-filters > * { flex: 1 1 210px; }
    }
    @media (max-width: 575.98px) {
        .mutation-filters > * { width: 100% !important; max-width: none !important; flex-basis: 100%; }
    }
</style>

<div class="card card-flush">
    <div class="card-header border-0 pt-6 pb-3">
        <div class="card-title d-block">
            <h2 class="fw-bolder text-gray-900 mb-1">Riwayat Pergerakan Stok</h2>
            <div class="text-muted fs-7">Setiap perubahan stok dilengkapi saldo sebelum, saldo sesudah, dan dokumen sumber.</div>
        </div>
    </div>

    <div class="card-body pt-2">
        <div class="d-flex flex-wrap align-items-center mutation-filters mb-6">
            <div class="position-relative search-box">
                <i class="fas fa-search position-absolute top-50 translate-middle-y ms-5 text-gray-500"></i>
                <input type="text" class="form-control form-control-solid ps-12" placeholder="Cari item, kode, atau petugas..." data-kt-filter="search" />
            </div>

            <select class="form-select form-select-solid warehouse-filter" id="filter_warehouse">
                <option value="">Semua Gudang</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>

            <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Tanggal mulai" />
            <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Tanggal akhir" />

            <button type="button" class="btn btn-primary" id="filter_apply">
                <i class="fas fa-filter me-1"></i>Terapkan
            </button>
            <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_mutations_table">
                <thead>
                    <tr class="text-start text-gray-500 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Waktu</th>
                        <th>Item & Gudang</th>
                        <th>Pergerakan</th>
                        <th>Saldo Stok</th>
                        <th>Dokumen Sumber</th>
                        <th>Petugas</th>
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
    const dataUrl = '{{ route('admin.inventory.stock-mutations.data') }}';
    const detailUrlTpl = '{{ route('admin.inventory.stock-mutations.show', ':id') }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_mutations_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const warehouseEl = document.getElementById('filter_warehouse');
        let fpFrom = null;
        let fpTo = null;

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const escapeHtml = value => $('<div>').text(value ?? '').html();
        const formatNumber = value => Number(value || 0).toLocaleString('id-ID');

        if (typeof flatpickr !== 'undefined') {
            fpFrom = dateFromEl ? flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true }) : null;
            fpTo = dateToEl ? flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true }) : null;
        }

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
                    if (warehouseEl?.value) params.warehouse_id = warehouseEl.value;
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                {
                    data: 'occurred_at',
                    render: value => `<span class="text-nowrap fw-semibold text-gray-700">${escapeHtml(value || '-')}</span>`
                },
                {
                    data: 'item',
                    render: (value, type, row) => `
                        <div class="d-flex flex-column">
                            <span class="fw-bolder text-gray-900">${escapeHtml(value)}</span>
                            <span class="text-muted fs-7"><i class="fas fa-warehouse me-1"></i>${escapeHtml(row.warehouse)}</span>
                        </div>`
                },
                {
                    data: 'qty',
                    render: (value, type, row) => {
                        const incoming = row.direction === 'IN';
                        const unitText = row.unit
                            ? `${formatNumber(row.qty_input)} ${escapeHtml(row.unit)}`
                            : `${formatNumber(value)} unit`;
                        return `<span class="badge ${incoming ? 'badge-light-success' : 'badge-light-danger'}">
                                <i class="fas ${incoming ? 'fa-arrow-down' : 'fa-arrow-up'} me-1"></i>
                                ${incoming ? 'Masuk' : 'Keluar'}
                            </span>
                            <div class="fw-bolder ${incoming ? 'text-success' : 'text-danger'} mt-2">
                                ${incoming ? '+' : '-'}${unitText}
                            </div>
                            ${row.unit && Number(row.qty_input) !== Number(value)
                                ? `<div class="text-muted fs-8">${formatNumber(value)} satuan dasar</div>`
                                : ''}`;
                    }
                },
                {
                    data: 'stock_before',
                    render: (value, type, row) => `
                        <div class="stock-flow-line d-flex align-items-center gap-2">
                            <span class="badge badge-light">${formatNumber(value)}</span>
                            <i class="fas fa-long-arrow-alt-right text-gray-400"></i>
                            <span class="badge badge-light-primary">${formatNumber(row.stock_after)}</span>
                        </div>`
                },
                {
                    data: 'source',
                    render: (value, type, row) => `
                        <div class="fw-semibold text-gray-800">${escapeHtml(value || '-')}</div>
                        <div class="text-muted fs-7">${escapeHtml(row.source_code || 'Tanpa kode')}</div>
                        ${row.note ? `<div class="text-muted fs-8 text-truncate mw-200px" title="${escapeHtml(row.note)}">${escapeHtml(row.note)}</div>` : ''}`
                },
                {
                    data: 'user',
                    render: value => `<span class="text-gray-700">${escapeHtml(value || '-')}</span>`
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: id => `<a href="${detailUrlTpl.replace(':id', id)}" class="btn btn-sm btn-light-primary">
                        <i class="fas fa-file-alt me-1"></i>Lihat Bukti
                    </a>`
                },
            ],
            language: {
                emptyTable: 'Belum ada mutasi stok.',
                zeroRecords: 'Mutasi stok tidak ditemukan.',
                processing: 'Memuat data...',
            }
        });

        const reloadTable = () => dt.ajax.reload();
        let searchTimer;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadTable, 300);
        });
        filterApplyBtn?.addEventListener('click', reloadTable);
        warehouseEl?.addEventListener('change', reloadTable);
        filterResetBtn?.addEventListener('click', () => {
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            if (warehouseEl) warehouseEl.value = '';
            if (searchInput) searchInput.value = '';
            reloadTable();
        });
    });
</script>
@endpush
