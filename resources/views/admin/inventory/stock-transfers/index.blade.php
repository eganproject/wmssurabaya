@extends('layouts.admin')

@section('title', 'Transfer Stok')
@section('page_title', 'Transfer Antar Gudang')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.stock-transfers.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.stock-transfers.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.stock-transfers.index', 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title d-flex flex-wrap gap-3">
            <input class="form-control form-control-solid w-250px" id="transfer_search" placeholder="Kode, gudang, SKU, atau item">
            <select class="form-select form-select-solid w-200px" id="transfer_status_filter">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="shipped">Dalam Pengiriman</option>
                <option value="received">Diterima Lengkap</option>
                <option value="received_with_discrepancy">Diterima Berselisih</option>
                <option value="cancelled">Dibatalkan</option>
            </select>
            <select class="form-select form-select-solid w-200px" id="transfer_warehouse_filter">
                <option value="">Semua Gudang</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>
        </div>
        @if($canCreate)
            <div class="card-toolbar"><button class="btn btn-primary" id="transfer_add">Buat Transfer</button></div>
        @endif
    </div>
    <div class="card-body">
        <div class="alert alert-light-primary mb-6">
            Transfer dari Gudang Besar dikirim dalam koli. Jika tujuannya Gudang Kecil, penerimaan dihitung dalam satuan dasar
            (PCS/SET), sehingga selisih sebagian isi koli tetap dapat dicatat.
        </div>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle" id="transfer_table">
                <thead>
                    <tr>
                        <th>Kode / Tanggal</th>
                        <th>Rute</th>
                        <th>Item</th>
                        <th>Dikirim</th>
                        <th>Diterima</th>
                        <th>Selisih</th>
                        <th>Status</th>
                        <th>Dibuat Oleh</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="transfer_modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <form class="modal-content" id="transfer_form">
            @csrf
            <div class="modal-header"><h3 id="transfer_modal_title">Buat Transfer</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row mb-5">
                    <div class="col-md-4"><label class="required form-label">Gudang Asal</label><select class="form-select" id="transfer_source" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="required form-label">Gudang Tujuan</label><select class="form-select" id="transfer_destination" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="required form-label">Tanggal</label><input type="datetime-local" class="form-control" id="transfer_date" required></div>
                </div>
                <div class="mb-5"><label class="form-label">Catatan Transfer</label><textarea class="form-control" id="transfer_note" rows="2"></textarea></div>
                <div class="d-flex justify-content-between align-items-center mb-3"><div><h5 class="mb-1">Item Transfer</h5><span class="text-muted fs-7">Qty mengikuti satuan kirim; nilai PCS dihitung otomatis.</span></div><button type="button" class="btn btn-sm btn-light-primary" id="transfer_add_item">Tambah Item</button></div>
                <div id="transfer_items"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan Draft</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="receive_modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <form class="modal-content" id="receive_form">
            <div class="modal-header"><div><h3 class="mb-1">Konfirmasi Penerimaan</h3><div class="text-muted" id="receive_route"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="alert alert-light-info" id="receive_instruction"></div>
                <div id="receive_items"></div>
                <div class="mt-5"><label class="form-label">Catatan Selisih Umum</label><textarea class="form-control" id="receive_note" rows="2" placeholder="Opsional jika alasan sudah dijelaskan per item"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-success">Simpan Penerimaan</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="detail_modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <div class="modal-content">
            <div class="modal-header"><div><h3 class="mb-1" id="detail_code">Detail Transfer</h3><div class="text-muted" id="detail_route"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="detail_content"></div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrf = '{{ csrf_token() }}';
    const items = @json($items);
    const warehouses = @json($warehouses);
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const urls = {
        data: '{{ route('admin.inventory.stock-transfers.data') }}',
        store: '{{ route('admin.inventory.stock-transfers.store') }}',
        show: '{{ route('admin.inventory.stock-transfers.show', ':id') }}',
        update: '{{ route('admin.inventory.stock-transfers.update', ':id') }}',
        cancel: '{{ route('admin.inventory.stock-transfers.cancel', ':id') }}',
        ship: '{{ route('admin.inventory.stock-transfers.ship', ':id') }}',
        receive: '{{ route('admin.inventory.stock-transfers.receive', ':id') }}',
    };
    const transferModal = new bootstrap.Modal(document.getElementById('transfer_modal'));
    const receiveModal = new bootstrap.Modal(document.getElementById('receive_modal'));
    const detailModal = new bootstrap.Modal(document.getElementById('detail_modal'));
    let editId = null;
    let receiveId = null;

    const statusLabels = {
        draft: ['Draft', 'secondary'],
        shipped: ['Dalam Pengiriman', 'warning'],
        received: ['Diterima Lengkap', 'success'],
        received_with_discrepancy: ['Diterima Berselisih', 'danger'],
        cancelled: ['Dibatalkan', 'dark'],
    };
    const badge = status => {
        const [label, color] = statusLabels[status] || [status, 'secondary'];
        return `<span class="badge badge-light-${color}">${label}</span>`;
    };
    const formatDate = value => value ? new Date(value).toLocaleString('id-ID') : '-';
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    const errorMessage = json => Object.values(json.errors || {}).flat().join('\n') || json.message || 'Terjadi kesalahan';
    const showUrl = (template, id) => template.replace(':id', id);

    const table = $('#transfer_table').DataTable({
        ajax: {
            url: urls.data,
            data: data => {
                data.q = transfer_search.value;
                data.status = transfer_status_filter.value;
                data.warehouse_id = transfer_warehouse_filter.value;
            },
        },
        order: [[0, 'desc']],
        columns: [
            {data: null, render: row => `<div class="fw-bold">${row.code}</div><div class="text-muted fs-7">${row.transacted_at || '-'}</div>`},
            {data: null, render: row => `<div>${row.source}</div><div class="text-muted fs-7">ke ${row.destination}</div>`},
            {data: 'items_count', className: 'text-center', render: value => `${value} SKU`},
            {data: null, render: row => `<div>${row.sent_summary}</div><div class="text-muted fs-7">${row.qty_base} satuan dasar</div>`},
            {data: 'qty_received_base', className: 'text-end', render: (value, type, row) => row.status === 'draft' ? '-' : `${value} satuan dasar`},
            {data: 'qty_discrepancy_base', className: 'text-end', render: (value, type, row) => {
                if (!['received', 'received_with_discrepancy'].includes(row.status)) return '-';
                return value === 0 ? '<span class="text-success">0</span>' : `<span class="text-danger fw-bold">${value} satuan dasar</span>`;
            }},
            {data: 'status', render: badge},
            {data: 'creator'},
            {data: null, className: 'text-end', orderable: false, render: row => {
                const actions = [`<button class="btn btn-sm btn-light-info detail me-1" data-id="${row.id}">Detail</button>`];
                if (row.status === 'draft' && canUpdate) {
                    actions.push(`<button class="btn btn-sm btn-light-primary edit me-1" data-id="${row.id}">Edit</button>`);
                    actions.push(`<button class="btn btn-sm btn-primary ship me-1" data-id="${row.id}">Kirim</button>`);
                }
                if (row.status === 'draft' && canDelete) actions.push(`<button class="btn btn-sm btn-light-danger cancel" data-id="${row.id}">Batal</button>`);
                if (row.status === 'shipped' && canUpdate) actions.push(`<button class="btn btn-sm btn-success receive" data-id="${row.id}">Terima</button>`);
                return actions.join('');
            }},
        ],
    });
    [transfer_search, transfer_status_filter, transfer_warehouse_filter].forEach(element => {
        element.addEventListener(element.tagName === 'INPUT' ? 'keyup' : 'change', () => table.ajax.reload());
    });

    const itemOptions = items.map(item => `<option value="${item.id}">${escapeHtml(item.sku)} - ${escapeHtml(item.name)}</option>`).join('');
    const currentWarehouseType = select => warehouses.find(warehouse => String(warehouse.id) === String(select.value))?.type;
    function refreshTransferRow(row, selectedUnitId = null) {
        const item = items.find(value => String(value.id) === String(row.querySelector('.item').value));
        const requiresPackage = currentWarehouseType(transfer_source) === 'bulk' || currentWarehouseType(transfer_destination) === 'bulk';
        const units = (item?.units || []).filter(unit => !requiresPackage || !unit.is_base);
        const unitSelect = row.querySelector('.unit');
        unitSelect.innerHTML = units.length
            ? units.map(unit => `<option value="${unit.id}">${escapeHtml(unit.name)} (1 = ${unit.conversion_qty})</option>`).join('')
            : '<option value="">Satuan koli belum dikonfigurasi</option>';
        if (selectedUnitId && units.some(unit => String(unit.id) === String(selectedUnitId))) unitSelect.value = String(selectedUnitId);
        const selected = units.find(unit => String(unit.id) === String(unitSelect.value));
        const qty = Number(row.querySelector('.qty').value || 0);
        row.querySelector('.base-preview').textContent = `${qty * Number(selected?.conversion_qty || 0)} satuan dasar`;
    }
    function addRow(data = {}) {
        const row = document.createElement('div');
        row.className = 'border rounded p-4 mb-3 transfer-row';
        row.innerHTML = `<div class="row g-3 align-items-end">
            <div class="col-md-4"><label class="required form-label">Item</label><select class="form-select item">${itemOptions}</select></div>
            <div class="col-md-2"><label class="required form-label">Satuan Kirim</label><select class="form-select unit"></select></div>
            <div class="col-md-2"><label class="required form-label">Qty Kirim</label><input type="number" min="1" value="1" class="form-control qty"><div class="text-muted fs-8 base-preview"></div></div>
            <div class="col-md-3"><label class="form-label">Catatan Item</label><input class="form-control item-note" value="${escapeHtml(data.note || '')}"></div>
            <div class="col-md-1"><button type="button" class="btn btn-icon btn-light-danger remove">×</button></div>
        </div>`;
        transfer_items.appendChild(row);
        if (data.item_id) row.querySelector('.item').value = String(data.item_id);
        if (data.qty_input) row.querySelector('.qty').value = data.qty_input;
        row.querySelector('.item').addEventListener('change', () => refreshTransferRow(row));
        row.querySelector('.unit').addEventListener('change', () => refreshTransferRow(row));
        row.querySelector('.qty').addEventListener('input', () => refreshTransferRow(row));
        row.querySelector('.remove').addEventListener('click', () => row.remove());
        refreshTransferRow(row, data.unit_id);
    }
    [transfer_source, transfer_destination].forEach(select => select.addEventListener('change', () => {
        document.querySelectorAll('.transfer-row').forEach(row => refreshTransferRow(row));
    }));
    transfer_add_item.addEventListener('click', () => addRow());
    document.getElementById('transfer_add')?.addEventListener('click', () => {
        editId = null;
        transfer_modal_title.textContent = 'Buat Transfer';
        transfer_form.reset();
        transfer_items.innerHTML = '';
        transfer_date.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        addRow();
        transferModal.show();
    });
    transfer_form.addEventListener('submit', async event => {
        event.preventDefault();
        const payload = {
            source_warehouse_id: transfer_source.value,
            destination_warehouse_id: transfer_destination.value,
            transacted_at: transfer_date.value,
            note: transfer_note.value,
            items: [...document.querySelectorAll('.transfer-row')].map(row => ({
                item_id: row.querySelector('.item').value,
                unit_id: row.querySelector('.unit').value,
                qty_input: row.querySelector('.qty').value,
                note: row.querySelector('.item-note').value,
            })),
        };
        const response = await fetch(editId ? showUrl(urls.update, editId) : urls.store, {
            method: editId ? 'PUT' : 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json'},
            body: JSON.stringify(payload),
        });
        const json = await response.json();
        if (!response.ok) return alert(errorMessage(json));
        transferModal.hide();
        table.ajax.reload();
    });

    async function getTransfer(id) {
        const response = await fetch(showUrl(urls.show, id), {headers: {Accept: 'application/json'}});
        const json = await response.json();
        if (!response.ok) throw new Error(errorMessage(json));
        return json;
    }
    $('#transfer_table').on('click', '.edit', async function() {
        try {
            const json = await getTransfer(this.dataset.id);
            editId = json.id;
            transfer_modal_title.textContent = `Edit ${json.code}`;
            transfer_source.value = json.source_warehouse_id;
            transfer_destination.value = json.destination_warehouse_id;
            transfer_date.value = (json.transacted_at || '').slice(0, 16);
            transfer_note.value = json.note || '';
            transfer_items.innerHTML = '';
            (json.items || []).forEach(addRow);
            transferModal.show();
        } catch (error) { alert(error.message); }
    });
    $('#transfer_table').on('click', '.ship,.cancel', async function() {
        const isShip = this.classList.contains('ship');
        if (!confirm(isShip ? 'Kirim transfer dan kurangi stok gudang asal?' : 'Batalkan draft transfer?')) return;
        const response = await fetch(showUrl(isShip ? urls.ship : urls.cancel, this.dataset.id), {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json'},
        });
        const json = await response.json();
        if (!response.ok) return alert(errorMessage(json));
        table.ajax.reload();
    });
    $('#transfer_table').on('click', '.receive', async function() {
        try {
            const json = await getTransfer(this.dataset.id);
            receiveId = json.id;
            const destinationIsBulk = json.destination_warehouse?.type === 'bulk';
            receive_route.textContent = `${json.source_warehouse?.name || '-'} ke ${json.destination_warehouse?.name || '-'}`;
            receive_instruction.textContent = destinationIsBulk
                ? 'Gudang tujuan adalah Gudang Besar. Masukkan jumlah koli utuh yang diterima.'
                : 'Gudang tujuan adalah Gudang Kecil. Hitung dan masukkan jumlah aktual dalam PCS/SET, termasuk bila satu koli terbuka atau kurang isi.';
            receive_items.innerHTML = (json.items || []).map(row => {
                const receiveUnit = destinationIsBulk ? row.item?.package_unit : row.item?.base_unit;
                const expectedReceiveQty = destinationIsBulk ? row.qty_input : row.qty_base;
                return `<div class="border rounded p-4 mb-4 receive-row" data-item-id="${row.item_id}" data-conversion="${receiveUnit?.conversion_qty || 1}" data-sent-base="${row.qty_base}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3"><label class="form-label">Item</label><div class="fw-bold">${escapeHtml(row.item?.sku)} - ${escapeHtml(row.item?.name)}</div><div class="text-muted fs-7">${escapeHtml(row.note || '')}</div></div>
                        <div class="col-md-2"><label class="form-label">Dikirim</label><div>${row.qty_input} ${escapeHtml(row.unit?.name || '')}</div><div class="text-muted fs-7">${row.qty_base} satuan dasar</div></div>
                        <div class="col-md-2"><label class="required form-label">Diterima (${escapeHtml(receiveUnit?.name || 'UNIT')})</label><input class="form-control received-qty" type="number" min="0" max="${expectedReceiveQty}" value="${expectedReceiveQty}"></div>
                        <div class="col-md-2"><label class="form-label">Selisih</label><div class="fw-bold discrepancy-preview text-success">0 satuan dasar</div></div>
                        <div class="col-md-3"><label class="form-label">Alasan Selisih</label><input class="form-control discrepancy" placeholder="Wajib jika ada selisih"></div>
                    </div>
                </div>`;
            }).join('');
            receive_items.querySelectorAll('.received-qty').forEach(input => input.addEventListener('input', updateDiscrepancyPreview));
            receive_note.value = '';
            receiveModal.show();
        } catch (error) { alert(error.message); }
    });
    function updateDiscrepancyPreview(event) {
        const row = event.target.closest('.receive-row');
        const receivedBase = Number(event.target.value || 0) * Number(row.dataset.conversion || 1);
        const discrepancy = Number(row.dataset.sentBase || 0) - receivedBase;
        const preview = row.querySelector('.discrepancy-preview');
        preview.textContent = `${discrepancy} satuan dasar`;
        preview.classList.toggle('text-success', discrepancy === 0);
        preview.classList.toggle('text-danger', discrepancy !== 0);
    }
    receive_form.addEventListener('submit', async event => {
        event.preventDefault();
        const payload = {
            discrepancy_note: receive_note.value,
            items: [...document.querySelectorAll('.receive-row')].map(row => ({
                item_id: row.dataset.itemId,
                qty_received: row.querySelector('.received-qty').value,
                discrepancy_note: row.querySelector('.discrepancy').value,
            })),
        };
        const response = await fetch(showUrl(urls.receive, receiveId), {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json'},
            body: JSON.stringify(payload),
        });
        const json = await response.json();
        if (!response.ok) return alert(errorMessage(json));
        receiveModal.hide();
        table.ajax.reload();
    });
    $('#transfer_table').on('click', '.detail', async function() {
        try {
            const json = await getTransfer(this.dataset.id);
            detail_code.innerHTML = `${escapeHtml(json.code)} ${badge(json.status)}`;
            detail_route.textContent = `${json.source_warehouse?.name || '-'} ke ${json.destination_warehouse?.name || '-'}`;
            const itemRows = (json.items || []).map(row => `<tr>
                <td><div class="fw-bold">${escapeHtml(row.item?.sku)}</div><div>${escapeHtml(row.item?.name)}</div></td>
                <td>${row.qty_input} ${escapeHtml(row.unit?.name || '')}<div class="text-muted fs-7">${row.qty_base} satuan dasar</div></td>
                <td>${row.received_unit ? `${row.qty_received_unit} ${escapeHtml(row.received_unit.name)}` : '-'}<div class="text-muted fs-7">${row.qty_received_base} satuan dasar</div></td>
                <td class="${row.qty_discrepancy_base ? 'text-danger fw-bold' : 'text-success'}">${row.qty_discrepancy_base}</td>
                <td>${escapeHtml(row.discrepancy_note || '-')}</td>
            </tr>`).join('');
            detail_content.innerHTML = `
                <div class="row g-4 mb-6">
                    <div class="col-md-3"><div class="text-muted">Tanggal Dokumen</div><div class="fw-bold">${formatDate(json.transacted_at)}</div></div>
                    <div class="col-md-3"><div class="text-muted">Dibuat</div><div class="fw-bold">${escapeHtml(json.creator || '-')}</div></div>
                    <div class="col-md-3"><div class="text-muted">Dikirim</div><div class="fw-bold">${escapeHtml(json.shipper || '-')}</div><div class="fs-7">${formatDate(json.shipped_at)}</div></div>
                    <div class="col-md-3"><div class="text-muted">Diterima</div><div class="fw-bold">${escapeHtml(json.receiver || '-')}</div><div class="fs-7">${formatDate(json.received_at)}</div></div>
                </div>
                <div class="mb-5"><div class="text-muted">Catatan Transfer</div><div>${escapeHtml(json.note || '-')}</div></div>
                <div class="table-responsive"><table class="table table-row-dashed"><thead><tr><th>Item</th><th>Dikirim</th><th>Diterima</th><th>Selisih Dasar</th><th>Alasan</th></tr></thead><tbody>${itemRows}</tbody></table></div>
                ${json.discrepancy_note ? `<div class="alert alert-light-danger mt-4"><strong>Catatan selisih umum:</strong> ${escapeHtml(json.discrepancy_note)}</div>` : ''}`;
            detailModal.show();
        } catch (error) { alert(error.message); }
    });
});
</script>
@endpush
