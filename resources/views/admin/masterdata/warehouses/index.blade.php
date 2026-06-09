@extends('layouts.admin')

@section('title', 'Gudang')
@section('page_title', 'Master Gudang')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <input type="text" class="form-control form-control-solid w-250px" id="warehouse_search" placeholder="Cari gudang" />
        </div>
        <div class="card-toolbar">
            <button class="btn btn-primary" id="warehouse_add">Tambah Gudang</button>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle" id="warehouse_table">
                <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Alamat</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="warehouse_modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="warehouse_form">
            @csrf
            <input type="hidden" id="warehouse_id">
            <div class="modal-header"><h3 class="modal-title">Gudang</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-4"><label class="required form-label">Kode</label><input class="form-control" id="warehouse_code" required></div>
                <div class="mb-4"><label class="required form-label">Nama</label><input class="form-control" id="warehouse_name" required></div>
                <div class="mb-4"><label class="required form-label">Tipe</label><select class="form-select" id="warehouse_type"><option value="bulk">Gudang Besar / Bulk</option><option value="fulfillment">Gudang Kecil / Fulfillment</option></select></div>
                <div class="mb-4"><label class="form-label">Alamat</label><textarea class="form-control" id="warehouse_address"></textarea></div>
                <label class="form-check form-switch"><input class="form-check-input" type="checkbox" id="warehouse_active" checked><span class="form-check-label">Aktif</span></label>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = '{{ csrf_token() }}';
    const dataUrl = '{{ route('admin.masterdata.warehouses.data') }}';
    const storeUrl = '{{ route('admin.masterdata.warehouses.store') }}';
    const updateUrl = '{{ route('admin.masterdata.warehouses.update', ':id') }}';
    const deleteUrl = '{{ route('admin.masterdata.warehouses.destroy', ':id') }}';
    const modal = new bootstrap.Modal(document.getElementById('warehouse_modal'));
    const table = $('#warehouse_table').DataTable({
        ajax: { url: dataUrl, data: d => d.q = document.getElementById('warehouse_search').value },
        columns: [
            {data:'code'}, {data:'name'},
            {data:'type', render:v => v === 'bulk' ? 'Gudang Besar' : 'Gudang Kecil'},
            {data:'address', defaultContent:'-'},
            {data:'is_active', render:v => v ? '<span class="badge badge-light-success">Aktif</span>' : '<span class="badge badge-light-secondary">Nonaktif</span>'},
            {data:null, className:'text-end', orderable:false, render:r => `<button class="btn btn-sm btn-light-primary edit">Edit</button> ${r.is_default ? '' : '<button class="btn btn-sm btn-light-danger delete">Hapus</button>'}`}
        ]
    });
    document.getElementById('warehouse_search').addEventListener('keyup', () => table.ajax.reload());
    document.getElementById('warehouse_add').addEventListener('click', () => {
        document.getElementById('warehouse_form').reset();
        document.getElementById('warehouse_id').value = '';
        document.getElementById('warehouse_active').checked = true;
        modal.show();
    });
    $('#warehouse_table tbody').on('click', '.edit', function() {
        const r = table.row($(this).closest('tr')).data();
        warehouse_id.value=r.id; warehouse_code.value=r.code; warehouse_name.value=r.name;
        warehouse_type.value=r.type; warehouse_address.value=r.address || ''; warehouse_active.checked=!!r.is_active;
        modal.show();
    }).on('click', '.delete', async function() {
        const r = table.row($(this).closest('tr')).data();
        if (!confirm(`Hapus gudang ${r.name}?`)) return;
        const res = await fetch(deleteUrl.replace(':id', r.id), {method:'DELETE', headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});
        const json = await res.json(); if (!res.ok) return alert(json.message || 'Gagal menghapus');
        table.ajax.reload();
    });
    document.getElementById('warehouse_form').addEventListener('submit', async e => {
        e.preventDefault();
        const id=warehouse_id.value;
        const body={code:warehouse_code.value,name:warehouse_name.value,type:warehouse_type.value,address:warehouse_address.value,is_active:warehouse_active.checked?1:0};
        const res=await fetch(id?updateUrl.replace(':id',id):storeUrl,{method:id?'PUT':'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body)});
        const json=await res.json(); if(!res.ok) return alert(json.message || Object.values(json.errors||{}).flat().join('\n'));
        modal.hide(); table.ajax.reload();
    });
});
</script>
@endpush
