@extends('layouts.admin')

@section('title', 'Laporan Stok Per Tanggal')
@section('page_title', 'Laporan Stok Per Tanggal')

@section('content')
<div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5 mb-6">
    <i class="fa-solid fa-calendar-day fs-2x text-primary me-4"></i>
    <div>
        <div class="fw-bold text-gray-800">Saldo stok akhir hari</div>
        <div class="text-gray-700 fs-7">Menampilkan stok penutup pada pukul 23:59:59 tanggal yang dipilih, berdasarkan riwayat mutasi stok.</div>
    </div>
</div>

<div class="card mb-6"><div class="card-body py-5">
    <div class="row g-3 align-items-end">
        <div class="col-xl-2 col-md-4"><label class="form-label">Tanggal</label><input id="filter_date" type="date" class="form-control form-control-solid" value="{{ $defaultDate }}"></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Gudang</label><select id="filter_warehouse" class="form-select form-select-solid"><option value="">Semua Gudang</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>{{ $warehouse->type === 'bulk' ? 'Gudang Besar' : 'Gudang Kecil' }} - {{ $warehouse->name }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Kategori</label><select id="filter_category" class="form-select form-select-solid"><option value="">Semua Kategori</option><option value="0">Tanpa Kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-4"><label class="form-label">Status</label><select id="filter_status" class="form-select form-select-solid"><option value="">Semua Status</option><option value="empty">Stok Habis</option><option value="low">Stok Menipis</option><option value="safe">Aman</option><option value="has_stock">Ada Stok</option></select></div>
        <div class="col-xl-2 col-md-6"><label class="form-label">Cari</label><input id="filter_search" class="form-control form-control-solid" placeholder="SKU, item, gudang, kategori, atau lokasi"></div>
        <div class="col-xl-1 col-md-2"><button id="filter_apply" class="btn btn-primary w-100"><i class="fa-solid fa-filter"></i> Tampilkan</button></div>
        <div class="col-xl-1 col-md-2"><button id="export_excel" class="btn btn-success w-100"><i class="fa-solid fa-file-excel"></i> Excel</button></div>
    </div>
</div></div>

<div class="row g-4 mb-6">
    <div class="col-md-3 col-6"><div class="card card-body"><div class="text-muted fs-7">Baris Stok</div><div class="fs-2x fw-bolder" id="kpi_total_rows">0</div></div></div>
    <div class="col-md-3 col-6"><div class="card card-body"><div class="text-muted fs-7">Total Qty Stok</div><div class="fs-2x fw-bolder text-primary" id="kpi_total_stock">0</div></div></div>
    <div class="col-md-3 col-6"><div class="card card-body"><div class="text-muted fs-7">Stok Habis / Menipis</div><div class="fs-2x fw-bolder text-warning"><span id="kpi_empty_rows">0</span> / <span id="kpi_low_rows">0</span></div></div></div>
    <div class="col-md-3 col-6"><div class="card card-body"><div class="text-muted fs-7">Gap Safety</div><div class="fs-2x fw-bolder" id="kpi_safety_gap">0</div></div></div>
</div>

<div class="card"><div class="card-header border-0 pt-6"><div><h3 class="fw-bolder mb-1">Detail Stok Akhir</h3><div class="text-muted fs-7" id="as_of_label">Memuat tanggal laporan...</div></div></div><div class="card-body py-5"><div class="table-responsive"><table class="table align-middle table-row-dashed fs-7 gy-4" id="stock_as_of_date_table"><thead><tr class="text-muted text-uppercase"><th>SKU / Item</th><th>Gudang</th><th>Kategori</th><th>Lokasi</th><th class="text-end">Stok Akhir</th><th class="text-end">Kemasan</th><th class="text-end">Safety</th><th>Status</th></tr></thead><tbody></tbody></table></div></div></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const filters = {date: document.getElementById('filter_date'), warehouse: document.getElementById('filter_warehouse'), category: document.getElementById('filter_category'), status: document.getElementById('filter_status'), search: document.getElementById('filter_search')};
    const number = value => Number(value || 0).toLocaleString('id-ID');
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const exportUrl = @json(route('admin.reports.stock-as-of-date.export'));
    const queryParams = () => new URLSearchParams({date: filters.date.value, warehouse_id: filters.warehouse.value, category_id: filters.category.value, status: filters.status.value, q: filters.search.value});
    const table = $('#stock_as_of_date_table').DataTable({
        processing: true, serverSide: true, dom: 'rtip', pageLength: 10,
        ajax: {url: @json($dataUrl), data: params => { params.date = filters.date.value; params.warehouse_id = filters.warehouse.value; params.category_id = filters.category.value; params.status = filters.status.value; params.q = filters.search.value; }, dataSrc: json => { const s = json.summary || {}; ['total_rows','total_stock','empty_rows','low_rows','safety_gap'].forEach(k => document.getElementById('kpi_'+k).textContent = number(s[k])); document.getElementById('as_of_label').textContent = 'Stok akhir per '+(json.as_of_date || filters.date.value); return json.data || []; }, error: xhr => window.AppSwal?.error(Object.values(xhr.responseJSON?.errors || {}).flat().join('\n') || 'Gagal memuat laporan stok per tanggal.')},
        columns: [
            {data: null, render: row => `<div class="fw-bold">${esc(row.sku)}${row.is_bundle ? ' <span class="badge badge-light-info">Bundle</span>' : ''}</div><div>${esc(row.name)}</div>`},
            {data: null, render: row => `<div class="fw-bold">${esc(row.warehouse)}</div><div class="text-muted fs-8">${esc(row.warehouse_type)}</div>`}, {data: 'category', render: esc}, {data: 'location', render: esc},
            {data: 'stock', className: 'text-end', render: (v,t,row) => `<span class="fw-bolder">${number(v)}</span> <span class="text-muted">${esc(row.base_unit)}</span>`},
            {data: null, className: 'text-end', render: row => row.package_unit ? `<div>${number(row.package_qty)} ${esc(row.package_unit)}</div><div class="text-muted fs-8">sisa ${number(row.package_remainder)} ${esc(row.base_unit)}</div>` : '-'}, {data: 'safety_stock', className: 'text-end', render: number},
            {data: null, render: row => `<span class="badge badge-light-${row.status_key === 'empty' ? 'danger' : row.status_key === 'low' ? 'warning' : 'success'}">${esc(row.status_label)}</span>`}
        ], language: {processing: 'Menghitung stok akhir...', emptyTable: 'Tidak ada data stok sesuai filter.'}
    });
    const reload = () => table.ajax.reload();
    document.getElementById('filter_apply').addEventListener('click', reload);
    document.getElementById('export_excel').addEventListener('click', () => window.location.assign(`${exportUrl}?${queryParams().toString()}`));
    [filters.date, filters.warehouse, filters.category, filters.status].forEach(el => el.addEventListener('change', reload));
    filters.search.addEventListener('keyup', e => { if (e.key === 'Enter') reload(); });
});
</script>
@endpush
