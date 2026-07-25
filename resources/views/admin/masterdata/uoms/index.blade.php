@extends('layouts.admin')

@section('title', 'UOM')
@section('page_title', 'UOM')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.uoms.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.uoms.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.uoms.index', 'delete');
@endphp

@section('content')
<div class="card card-flush">
    <div class="card-header border-0 pt-6">
        <div class="card-title d-block"><h2 class="fw-bolder mb-1">Master UOM</h2><div class="text-muted fs-7">Kelola satuan yang dapat dipilih pada Master Item, misalnya PCS, BOX, DUS, atau KOLI.</div></div>
        <div class="card-toolbar">
            @if($canCreate)<button class="btn btn-primary" id="uom_create"><i class="fa-solid fa-plus me-2"></i>Tambah UOM</button>@endif
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="position-relative w-300px mb-6"><i class="fas fa-search position-absolute top-50 translate-middle-y ms-5 text-gray-500"></i><input class="form-control form-control-solid ps-12" id="uom_search" placeholder="Cari kode atau nama UOM"></div>
        <div class="table-responsive"><table class="table align-middle table-row-dashed fs-6 gy-4" id="uoms_table"><thead><tr class="text-start text-gray-600 fw-bolder fs-7 text-uppercase"><th>Kode</th><th>Nama</th><th>Deskripsi</th><th>Status</th><th>Digunakan Item</th><th class="text-end">Aksi</th></tr></thead><tbody></tbody></table></div>
    </div>
</div>
<div class="modal fade" id="uom_modal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 id="uom_title">Tambah UOM</h2><button class="btn btn-icon btn-sm" data-bs-dismiss="modal"><i class="fa-solid fa-xmark"></i></button></div><form id="uom_form"><div class="modal-body"><input type="hidden" id="uom_id"><div class="mb-5"><label class="required form-label">Kode</label><input class="form-control form-control-solid" id="uom_code" maxlength="30" required><div class="invalid-feedback" data-error="code"></div></div><div class="mb-5"><label class="required form-label">Nama</label><input class="form-control form-control-solid" id="uom_name" maxlength="100" required><div class="invalid-feedback" data-error="name"></div></div><div class="mb-5"><label class="form-label">Deskripsi</label><textarea class="form-control form-control-solid" id="uom_description" rows="3"></textarea></div><label class="form-check form-switch form-check-custom form-check-solid"><input class="form-check-input" type="checkbox" id="uom_active" checked><span class="form-check-label">Aktif dan dapat dipilih pada item baru</span></label></div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div></form></div></div></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = '{{ csrf_token() }}', dataUrl = '{{ route('admin.masterdata.uoms.data') }}', storeUrl = '{{ route('admin.masterdata.uoms.store') }}', updateTpl = '{{ route('admin.masterdata.uoms.update', ':id') }}', deleteTpl = '{{ route('admin.masterdata.uoms.destroy', ':id') }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }}, canDelete = {{ $canDelete ? 'true' : 'false' }};
    const modal = new bootstrap.Modal(document.getElementById('uom_modal')), form = document.getElementById('uom_form'), search = document.getElementById('uom_search');
    const e = s => document.querySelector(s), esc = v => $('<div>').text(v ?? '').html();
    let table = $('#uoms_table').DataTable({processing:true,serverSide:true,dom:'rtip',ajax:{url:dataUrl,data:d=>d.q=search.value},columns:[
        {data:'code',render:v=>`<span class="badge badge-light-primary">${esc(v)}</span>`},{data:'name'},{data:'description',render:v=>esc(v)||'-'},{data:'is_active',render:v=>v?'<span class="badge badge-light-success">Aktif</span>':'<span class="badge badge-light-danger">Nonaktif</span>'},{data:'item_units_count',className:'text-center'},
        {data:null,orderable:false,searchable:false,className:'text-end',render:r=>{let out='';if(canUpdate)out+=`<button class="btn btn-sm btn-light-primary me-2 edit-uom" data-row='${JSON.stringify(r).replace(/'/g,'&#39;')}'>Edit</button>`;if(canDelete)out+=`<button class="btn btn-sm btn-light-danger delete-uom" data-id="${r.id}" data-name="${esc(r.code)}">Hapus</button>`;return out;}}
    ]});
    let timer; search.addEventListener('input',()=>{clearTimeout(timer);timer=setTimeout(()=>table.ajax.reload(),300)});
    const reset = () => { form.reset();e('#uom_id').value='';e('#uom_active').checked=true;e('#uom_title').textContent='Tambah UOM';document.querySelectorAll('[data-error]').forEach(x=>{x.textContent='';x.previousElementSibling?.classList.remove('is-invalid')}) };
    e('#uom_create')?.addEventListener('click',()=>{reset();modal.show()});
    $('#uoms_table').on('click','.edit-uom',function(){reset();let r=JSON.parse($(this).attr('data-row'));e('#uom_id').value=r.id;e('#uom_code').value=r.code;e('#uom_name').value=r.name;e('#uom_description').value=r.description||'';e('#uom_active').checked=!!r.is_active;e('#uom_title').textContent='Edit UOM';modal.show()});
    $('#uoms_table').on('click','.delete-uom',function(){const btn=this;Swal.fire({title:'Hapus UOM?',text:`${btn.dataset.name} akan dihapus bila belum digunakan item.`,icon:'warning',showCancelButton:true,confirmButtonText:'Hapus'}).then(x=>{if(!x.isConfirmed)return;fetch(deleteTpl.replace(':id',btn.dataset.id),{method:'DELETE',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}}).then(async r=>{let d=await r.json();if(!r.ok)throw d;Swal.fire('Berhasil',d.message,'success');table.ajax.reload()}).catch(x=>Swal.fire('Tidak dapat dihapus',x.errors?.uom?.[0]||x.message||'Terjadi kesalahan','error'))})});
    form.addEventListener('submit',async ev=>{ev.preventDefault();document.querySelectorAll('[data-error]').forEach(x=>{x.textContent='';x.previousElementSibling?.classList.remove('is-invalid')});let id=e('#uom_id').value, body={code:e('#uom_code').value.trim().toUpperCase(),name:e('#uom_name').value.trim(),description:e('#uom_description').value.trim(),is_active:e('#uom_active').checked?1:0};let r=await fetch(id?updateTpl.replace(':id',id):storeUrl,{method:id?'PUT':'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(body)}),d=await r.json();if(!r.ok){Object.entries(d.errors||{}).forEach(([k,v])=>{let x=document.querySelector(`[data-error="${k}"]`);if(x){x.textContent=v[0];x.previousElementSibling?.classList.add('is-invalid')}});return} modal.hide();Swal.fire('Berhasil',d.message,'success');table.ajax.reload()});
});
</script>
@endpush
