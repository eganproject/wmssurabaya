@extends('layouts.admin')

@section('title', 'Transfer Stok')
@section('page_title', 'Transfer Antar Gudang')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title"><input class="form-control form-control-solid w-250px" id="transfer_search" placeholder="Cari transfer"></div>
        <div class="card-toolbar"><button class="btn btn-primary" id="transfer_add">Buat Transfer</button></div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle" id="transfer_table">
                <thead><tr><th>Kode</th><th>Tanggal</th><th>Dari</th><th>Ke</th><th>SKU</th><th>Dikirim</th><th>Diterima</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="transfer_modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <form class="modal-content" id="transfer_form">
            @csrf
            <div class="modal-header"><h3 id="transfer_modal_title">Buat Transfer</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-5">
                    <div class="col-md-4"><label class="required form-label">Gudang Asal</label><select class="form-select" id="transfer_source" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="required form-label">Gudang Tujuan</label><select class="form-select" id="transfer_destination" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="required form-label">Tanggal</label><input type="datetime-local" class="form-control" id="transfer_date" required></div>
                </div>
                <div class="mb-5"><label class="form-label">Catatan</label><textarea class="form-control" id="transfer_note"></textarea></div>
                <div class="d-flex justify-content-between mb-3"><h5>Item</h5><button type="button" class="btn btn-sm btn-light-primary" id="transfer_add_item">Tambah Item</button></div>
                <div id="transfer_items"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Draft</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="receive_modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-800px">
        <form class="modal-content" id="receive_form">
            <div class="modal-header"><h3>Konfirmasi Penerimaan Transfer</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div id="receive_items"></div>
                <div class="mt-5"><label class="form-label">Catatan Selisih Umum</label><textarea class="form-control" id="receive_note"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-success">Simpan Penerimaan</button></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf='{{ csrf_token() }}';
    const items=@json($items);
    const warehouses=@json($warehouses->map(fn ($warehouse) => ['id' => $warehouse->id, 'type' => $warehouse->type])->values());
    const dataUrl='{{ route('admin.inventory.stock-transfers.data') }}';
    const storeUrl='{{ route('admin.inventory.stock-transfers.store') }}';
    const showUrl='{{ route('admin.inventory.stock-transfers.show', ':id') }}';
    const updateUrl='{{ route('admin.inventory.stock-transfers.update', ':id') }}';
    const cancelUrl='{{ route('admin.inventory.stock-transfers.cancel', ':id') }}';
    const shipUrl='{{ route('admin.inventory.stock-transfers.ship', ':id') }}';
    const receiveUrl='{{ route('admin.inventory.stock-transfers.receive', ':id') }}';
    const modal=new bootstrap.Modal(document.getElementById('transfer_modal'));
    const receiveModal=new bootstrap.Modal(document.getElementById('receive_modal'));
    let editId=null;
    let receiveId=null;
    const table=$('#transfer_table').DataTable({
        ajax:{url:dataUrl,data:d=>d.q=transfer_search.value},
        order:[[1,'desc']],
        columns:[
            {data:'code'},{data:'transacted_at'},{data:'source'},{data:'destination'},{data:'items_count'},{data:'qty_base'},{data:'qty_received_base'},
            {data:'status',render:v=>`<span class="badge badge-light-${v==='received'?'success':v==='received_with_discrepancy'?'danger':v==='shipped'?'warning':v==='cancelled'?'dark':'secondary'}">${v}</span>`},
            {data:null,className:'text-end',orderable:false,render:r=>{
                if(r.status==='draft') return `<button class="btn btn-sm btn-light-primary edit me-1" data-id="${r.id}">Edit</button><button class="btn btn-sm btn-primary ship me-1" data-id="${r.id}">Kirim</button><button class="btn btn-sm btn-light-danger cancel" data-id="${r.id}">Batal</button>`;
                if(r.status==='shipped') return `<button class="btn btn-sm btn-success receive" data-id="${r.id}">Terima</button>`;
                return '';
            }}
        ]
    });
    transfer_search.addEventListener('keyup',()=>table.ajax.reload());
    const itemOptions=items.map(i=>`<option value="${i.id}">${i.sku} - ${i.name}</option>`).join('');
    function addRow(data={}) {
        const row=document.createElement('div'); row.className='row g-3 align-items-end mb-3 transfer-row';
        row.innerHTML=`<div class="col-md-5"><label class="form-label">Item</label><select class="form-select item">${itemOptions}</select></div><div class="col-md-3"><label class="form-label">Satuan</label><select class="form-select unit"></select></div><div class="col-md-2"><label class="form-label">Qty</label><input type="number" min="1" value="1" class="form-control qty"></div><div class="col-md-2"><button type="button" class="btn btn-light-danger remove">Hapus</button></div>`;
        transfer_items.appendChild(row);
        if(data.item_id) row.querySelector('.item').value=String(data.item_id);
        const refresh=()=>{
            const item=items.find(i=>i.id==row.querySelector('.item').value);
            const sourceBulk=warehouses.find(w=>String(w.id)===String(transfer_source.value))?.type==='bulk';
            const destinationBulk=warehouses.find(w=>String(w.id)===String(transfer_destination.value))?.type==='bulk';
            const allUnits=item?.units||[];
            const units=(sourceBulk||destinationBulk)?allUnits.filter(unit=>!unit.is_base):allUnits;
            row.querySelector('.unit').innerHTML=units.length
                ? units.map(u=>`<option value="${u.id}">${u.name} (${u.conversion_qty})</option>`).join('')
                : '<option value="">Atur satuan koli pada master item</option>';
            if(data.unit_id&&units.some(unit=>String(unit.id)===String(data.unit_id)))row.querySelector('.unit').value=String(data.unit_id);
        };
        row.querySelector('.item').addEventListener('change',refresh); row.querySelector('.remove').addEventListener('click',()=>row.remove()); refresh();
        if(data.qty_input) row.querySelector('.qty').value=data.qty_input;
    }
    [transfer_source,transfer_destination].forEach(select=>select.addEventListener('change',()=>document.querySelectorAll('.transfer-row').forEach(row=>row.querySelector('.item').dispatchEvent(new Event('change')))));
    transfer_add_item.addEventListener('click',addRow);
    transfer_add.addEventListener('click',()=>{editId=null;transfer_modal_title.textContent='Buat Transfer';transfer_form.reset();transfer_items.innerHTML='';transfer_date.value=new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,16);addRow();modal.show();});
    transfer_form.addEventListener('submit',async e=>{
        e.preventDefault();
        const payload={source_warehouse_id:transfer_source.value,destination_warehouse_id:transfer_destination.value,transacted_at:transfer_date.value,note:transfer_note.value,items:[...document.querySelectorAll('.transfer-row')].map(r=>({item_id:r.querySelector('.item').value,unit_id:r.querySelector('.unit').value,qty_input:r.querySelector('.qty').value}))};
        const res=await fetch(editId?updateUrl.replace(':id',editId):storeUrl,{method:editId?'PUT':'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload)});
        const json=await res.json();if(!res.ok)return alert(json.message||Object.values(json.errors||{}).flat().join('\n'));modal.hide();table.ajax.reload();
    });
    $('#transfer_table').on('click','.edit',async function(){
        const res=await fetch(showUrl.replace(':id',this.dataset.id),{headers:{Accept:'application/json'}});const json=await res.json();if(!res.ok)return alert(json.message);
        editId=json.id;transfer_modal_title.textContent=`Edit ${json.code}`;transfer_source.value=json.source_warehouse_id;transfer_destination.value=json.destination_warehouse_id;transfer_date.value=(json.transacted_at||'').slice(0,16);transfer_note.value=json.note||'';transfer_items.innerHTML='';(json.items||[]).forEach(addRow);modal.show();
    });
    $('#transfer_table').on('click','.ship,.cancel',async function(){
        const isShip=this.classList.contains('ship');if(!confirm(isShip?'Kirim transfer dan kurangi stok gudang asal?':'Batalkan draft transfer?'))return;
        const url=(isShip?shipUrl:cancelUrl).replace(':id',this.dataset.id);const res=await fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'}});const json=await res.json();if(!res.ok)return alert(json.message||Object.values(json.errors||{}).flat().join('\n'));table.ajax.reload();
    });
    $('#transfer_table').on('click','.receive',async function(){
        const res=await fetch(showUrl.replace(':id',this.dataset.id),{headers:{Accept:'application/json'}});const json=await res.json();if(!res.ok)return alert(json.message);
        receiveId=json.id;receive_items.innerHTML=(json.items||[]).map(row=>`<div class="row g-3 mb-4 receive-row" data-item-id="${row.item_id}"><div class="col-md-4"><label class="form-label">Item</label><div class="form-control bg-light">${row.item?.sku||''} - ${row.item?.name||''}</div></div><div class="col-md-2"><label class="form-label">Dikirim</label><div class="form-control bg-light">${row.qty_input} ${row.unit?.name||''}</div></div><div class="col-md-2"><label class="required form-label">Diterima</label><input class="form-control received-qty" type="number" min="0" max="${row.qty_input}" value="${row.qty_input}"></div><div class="col-md-4"><label class="form-label">Alasan Selisih</label><input class="form-control discrepancy"></div></div>`).join('');receive_note.value='';receiveModal.show();
    });
    receive_form.addEventListener('submit',async e=>{
        e.preventDefault();const payload={discrepancy_note:receive_note.value,items:[...document.querySelectorAll('.receive-row')].map(row=>({item_id:row.dataset.itemId,qty_received_input:row.querySelector('.received-qty').value,discrepancy_note:row.querySelector('.discrepancy').value}))};
        const res=await fetch(receiveUrl.replace(':id',receiveId),{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload)});const json=await res.json();if(!res.ok)return alert(json.message||Object.values(json.errors||{}).flat().join('\n'));receiveModal.hide();table.ajax.reload();
    });
});
</script>
@endpush
