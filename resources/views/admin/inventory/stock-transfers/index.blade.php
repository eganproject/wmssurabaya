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
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="fa-solid fa-magnifying-glass position-absolute ms-5 text-gray-500"></i>
                <input type="text" class="form-control form-control-solid w-250px ps-14" id="transfer_search" placeholder="Cari transfer stok" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end align-items-center gap-2">
                <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                    <i class="fa-solid fa-filter"></i> Filter
                </button>
                <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" data-kt-menu-dismiss="false">
                    <div class="px-7 py-5"><div class="fs-5 text-dark fw-bolder">Filter Transfer</div></div>
                    <div class="separator border-gray-200"></div>
                    <div class="px-7 py-5">
                        <div class="mb-7">
                            <label class="form-label fs-6 fw-bold">Status</label>
                            <select class="form-select form-select-solid" id="transfer_status_filter">
                                <option value="">Semua Status</option>
                                <option value="draft">Draft</option>
                                <option value="shipped">Dalam Pengiriman</option>
                                <option value="received">Diterima Lengkap</option>
                                <option value="received_with_discrepancy">Diterima Berselisih</option>
                                <option value="cancelled">Dibatalkan</option>
                            </select>
                        </div>
                        <div class="mb-7">
                            <label class="form-label fs-6 fw-bold">Gudang</label>
                            <select class="form-select form-select-solid" id="transfer_warehouse_filter">
                                <option value="">Semua Gudang</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light btn-active-light-primary me-2" id="transfer_filter_reset">Reset</button>
                            <button type="button" class="btn btn-primary" id="transfer_filter_apply">Terapkan</button>
                        </div>
                    </div>
                </div>
                @if($canCreate)
                    <button type="button" class="btn btn-primary" id="transfer_add">
                        <i class="fa-solid fa-plus"></i> Buat Transfer
                    </button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-6 mb-7">
            <i class="fa-solid fa-circle-info fs-2x text-primary me-4"></i>
            <div class="d-flex flex-stack flex-grow-1">
                <div class="fw-semibold">
                    <div class="fs-6 text-gray-700">
                        Gudang Besar mengirim dalam koli. Penerimaan di Gudang Kecil dihitung dalam PCS/SET agar selisih sebagian isi koli dapat dicatat.
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="transfer_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
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
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="transfer_modal_title">Buat Transfer</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal"><i class="fa-solid fa-xmark fs-2"></i></button>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-10 my-7">
                <form class="form" id="transfer_form">
                    @csrf
                    <div class="row g-4 mb-7">
                        <div class="col-md-4"><label class="required fs-6 fw-bold form-label mb-2">Gudang Asal</label><select class="form-select form-select-solid" id="transfer_source" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="required fs-6 fw-bold form-label mb-2">Gudang Tujuan</label><select class="form-select form-select-solid" id="transfer_destination" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="required fs-6 fw-bold form-label mb-2">Tanggal Transfer</label><input type="text" class="form-control form-control-solid" id="transfer_date" required></div>
                    </div>
                    <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-4 mb-7" id="transfer_unit_info"></div>
                    <div class="fv-row mb-7"><label class="fs-6 fw-bold form-label mb-2">Catatan Transfer</label><textarea class="form-control form-control-solid" id="transfer_note" rows="3"></textarea></div>
                    <div class="separator mb-7"></div>
                    <div class="d-flex justify-content-between align-items-center mb-5"><div><h5 class="fw-bolder mb-1">Item Transfer</h5><span class="text-muted fs-7">Qty mengikuti satuan kirim dan dikonversi otomatis ke satuan dasar.</span></div><button type="button" class="btn btn-sm btn-light-primary" id="transfer_add_item"><i class="fa-solid fa-plus"></i> Tambah Item</button></div>
                    <div id="transfer_items"></div>
                    <div class="text-end pt-5">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="transfer_submit">
                            <span class="indicator-label">Simpan Draft</span>
                            <span class="indicator-progress">Menyimpan... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="receive_modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <div class="modal-content">
            <div class="modal-header"><div><h2 class="fw-bolder mb-1">Konfirmasi Penerimaan</h2><div class="text-muted" id="receive_route"></div></div><button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal"><i class="fa-solid fa-xmark fs-2"></i></button></div>
            <div class="modal-body scroll-y mx-5 mx-xl-10 my-7">
              <form class="form" id="receive_form">
                <div class="alert alert-light-info" id="receive_instruction"></div>
                <div id="receive_items"></div>
                <div class="fv-row mt-7"><label class="fs-6 fw-bold form-label mb-2">Catatan Selisih Umum</label><textarea class="form-control form-control-solid" id="receive_note" rows="3" placeholder="Opsional jika alasan sudah dijelaskan per item"></textarea></div>
                <div class="text-end pt-7">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="receive_submit">
                        <span class="indicator-label">Simpan Penerimaan</span>
                        <span class="indicator-progress">Menyimpan... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
              </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="detail_modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <div class="modal-content">
            <div class="modal-header"><div><h2 class="fw-bolder mb-1" id="detail_code">Detail Transfer</h2><div class="text-muted" id="detail_route"></div></div><button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal"><i class="fa-solid fa-xmark fs-2"></i></button></div>
            <div class="modal-body scroll-y mx-5 mx-xl-10 my-7" id="detail_content"></div>
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
    const transferSubmit = document.getElementById('transfer_submit');
    const receiveSubmit = document.getElementById('receive_submit');
    const transferDate = document.getElementById('transfer_date');
    const transferUnitInfo = document.getElementById('transfer_unit_info');
    let editId = null;
    let receiveId = null;
    let transferDatePicker = null;

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
    const notify = (title, text, icon = 'info') => {
        if (typeof Swal !== 'undefined') return Swal.fire(title, text, icon);
        window.alert(text);
    };
    const confirmAction = async (title, text, confirmButtonText, icon = 'question') => {
        if (typeof Swal === 'undefined') return window.confirm(text);
        const result = await Swal.fire({
            title,
            text,
            icon,
            showCancelButton: true,
            confirmButtonText,
            cancelButtonText: 'Batal',
            buttonsStyling: false,
            customClass: {
                confirmButton: icon === 'warning' ? 'btn btn-danger' : 'btn btn-primary',
                cancelButton: 'btn btn-light ms-3',
            },
        });
        return result.isConfirmed;
    };
    const setLoading = (button, loading) => {
        if (!button) return;
        button.disabled = loading;
        button.setAttribute('data-kt-indicator', loading ? 'on' : 'off');
    };
    const initSelect2 = (element, dropdownParent = null, placeholder = null) => {
        if (!element || typeof $ === 'undefined' || !$.fn.select2) return;
        if ($(element).hasClass('select2-hidden-accessible')) $(element).select2('destroy');
        $(element).select2({
            width: '100%',
            dropdownParent: dropdownParent ? $(dropdownParent) : undefined,
            placeholder: placeholder || undefined,
        });
    };
    const refreshMenus = () => window.KTMenu?.createInstances();

    initSelect2(document.getElementById('transfer_status_filter'));
    initSelect2(document.getElementById('transfer_warehouse_filter'));
    initSelect2(document.getElementById('transfer_source'), document.getElementById('transfer_modal'));
    initSelect2(document.getElementById('transfer_destination'), document.getElementById('transfer_modal'));
    if (typeof flatpickr !== 'undefined' && transferDate) {
        transferDatePicker = flatpickr(transferDate, {
            enableTime: true,
            dateFormat: 'Y-m-d H:i',
            allowInput: true,
        });
    }

    const table = $('#transfer_table').DataTable({
        processing: true,
        serverSide: false,
        dom: 'rtip',
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
            {data: null, className: 'text-end', orderable: false, searchable: false, render: row => {
                const actions = [`<div class="menu-item px-3"><a href="#" class="menu-link px-3 detail" data-id="${row.id}">Detail</a></div>`];
                if (row.status === 'draft' && canUpdate) {
                    actions.push(`<div class="menu-item px-3"><a href="#" class="menu-link px-3 edit" data-id="${row.id}">Edit</a></div>`);
                    actions.push(`<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-primary ship" data-id="${row.id}">Kirim</a></div>`);
                }
                if (row.status === 'draft' && canDelete) actions.push(`<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger cancel" data-id="${row.id}">Batalkan</a></div>`);
                if (row.status === 'shipped' && canUpdate) actions.push(`<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success receive" data-id="${row.id}">Konfirmasi Penerimaan</a></div>`);
                return `<button type="button" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions</button>
                    <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-200px py-3" data-kt-menu="true">${actions.join('')}</div>`;
            }},
        ],
    });
    table.on('draw', refreshMenus);
    transfer_search.addEventListener('keyup', () => table.ajax.reload());
    transfer_filter_apply.addEventListener('click', () => table.ajax.reload());
    transfer_filter_reset.addEventListener('click', () => {
        transfer_status_filter.value = '';
        transfer_warehouse_filter.value = '';
        $('#transfer_status_filter, #transfer_warehouse_filter').trigger('change.select2');
        table.ajax.reload();
    });

    const itemOptions = items.map(item => `<option value="${item.id}">${escapeHtml(item.sku)} - ${escapeHtml(item.name)}</option>`).join('');
    const currentWarehouseType = select => warehouses.find(warehouse => String(warehouse.id) === String(select.value))?.type;
    function updateTransferUnitInfo() {
        const sourceBulk = currentWarehouseType(transfer_source) === 'bulk';
        const destinationBulk = currentWarehouseType(transfer_destination) === 'bulk';
        if (!transferUnitInfo) return;
        transferUnitInfo.innerHTML = sourceBulk || destinationBulk
            ? '<i class="fa-solid fa-boxes-stacked fs-2 text-info me-4"></i><div><div class="fw-bold text-gray-800">Input menggunakan satuan koli</div><div class="text-gray-700 fs-7">Karena transfer melibatkan Gudang Besar, jumlah kirim wajib berupa koli/kemasan utuh seperti DUS, BOX, atau KOLI. Input bukan dalam PCS.</div></div>'
            : '<i class="fa-solid fa-box fs-2 text-info me-4"></i><div><div class="fw-bold text-gray-800">Input menggunakan satuan dasar</div><div class="text-gray-700 fs-7">Transfer antar Gudang Kecil dapat menggunakan PCS atau SET.</div></div>';
    }
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
        const baseUnit = (item?.units || []).find(unit => unit.is_base);
        const unitName = selected?.name || 'KOLI';
        const conversion = Number(selected?.conversion_qty || 0);
        const qtyLabel = row.querySelector('.qty-label');
        const unitLabel = row.querySelector('.unit-label');
        if (unitLabel) unitLabel.textContent = requiresPackage ? 'Satuan Koli' : 'Satuan Kirim';
        if (qtyLabel) qtyLabel.textContent = requiresPackage ? `Jumlah Koli (${unitName})` : `Qty Kirim (${unitName})`;
        row.querySelector('.base-preview').textContent = conversion > 0
            ? `1 ${unitName} = ${conversion.toLocaleString('id-ID')} ${baseUnit?.name || 'dasar'}; total ${(qty * conversion).toLocaleString('id-ID')} ${baseUnit?.name || 'dasar'}`
            : 'Satuan koli belum dikonfigurasi pada master item';
        if ($(unitSelect).hasClass('select2-hidden-accessible')) $(unitSelect).trigger('change.select2');
    }
    function addRow(data = {}) {
        const row = document.createElement('div');
        row.className = 'border border-gray-300 border-dashed rounded p-5 mb-5 transfer-row';
        row.innerHTML = `<div class="row g-3 align-items-end">
            <div class="col-md-4"><label class="required fs-6 fw-bold form-label mb-2">Item</label><select class="form-select form-select-solid item">${itemOptions}</select></div>
            <div class="col-md-2"><label class="required fs-6 fw-bold form-label mb-2 unit-label">Satuan Kirim</label><select class="form-select form-select-solid unit"></select></div>
            <div class="col-md-2"><label class="required fs-6 fw-bold form-label mb-2 qty-label">Qty Kirim</label><input type="number" min="1" step="1" value="1" class="form-control form-control-solid qty"><div class="text-primary fs-8 fw-semibold mt-1 base-preview"></div></div>
            <div class="col-md-3"><label class="fs-6 fw-bold form-label mb-2">Catatan Item</label><input class="form-control form-control-solid item-note" value="${escapeHtml(data.note || '')}"></div>
            <div class="col-md-1 text-end"><button type="button" class="btn btn-icon btn-light-danger remove" title="Hapus item"><i class="fa-solid fa-trash"></i></button></div>
        </div>`;
        transfer_items.appendChild(row);
        if (data.item_id) row.querySelector('.item').value = String(data.item_id);
        if (data.qty_input) row.querySelector('.qty').value = data.qty_input;
        row.querySelector('.item').addEventListener('change', () => refreshTransferRow(row));
        row.querySelector('.unit').addEventListener('change', () => refreshTransferRow(row));
        row.querySelector('.qty').addEventListener('input', () => refreshTransferRow(row));
        row.querySelector('.remove').addEventListener('click', () => row.remove());
        refreshTransferRow(row, data.unit_id);
        initSelect2(row.querySelector('.item'), document.getElementById('transfer_modal'), 'Pilih item');
        initSelect2(row.querySelector('.unit'), document.getElementById('transfer_modal'), 'Pilih satuan');
    }
    [transfer_source, transfer_destination].forEach(select => select.addEventListener('change', () => {
        document.querySelectorAll('.transfer-row').forEach(row => refreshTransferRow(row));
        updateTransferUnitInfo();
    }));
    updateTransferUnitInfo();
    transfer_add_item.addEventListener('click', () => addRow());
    document.getElementById('transfer_add')?.addEventListener('click', () => {
        editId = null;
        transfer_modal_title.textContent = 'Buat Transfer';
        transfer_form.reset();
        $('#transfer_source, #transfer_destination').trigger('change.select2');
        transfer_items.innerHTML = '';
        if (transferDatePicker) transferDatePicker.setDate(new Date(), true);
        else transferDate.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16).replace('T', ' ');
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
        setLoading(transferSubmit, true);
        try {
            const response = await fetch(editId ? showUrl(urls.update, editId) : urls.store, {
                method: editId ? 'PUT' : 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify(payload),
            });
            const json = await response.json();
            if (!response.ok) return notify('Gagal', errorMessage(json), 'error');
            transferModal.hide();
            table.ajax.reload();
            notify('Berhasil', json.message || 'Transfer berhasil disimpan', 'success');
        } catch (error) {
            notify('Gagal', 'Tidak dapat menghubungi server', 'error');
        } finally {
            setLoading(transferSubmit, false);
        }
    });

    async function getTransfer(id) {
        const response = await fetch(showUrl(urls.show, id), {headers: {Accept: 'application/json'}});
        const json = await response.json();
        if (!response.ok) throw new Error(errorMessage(json));
        return json;
    }
    $('#transfer_table').on('click', '.edit', async function(event) {
        event.preventDefault();
        try {
            const json = await getTransfer(this.dataset.id);
            editId = json.id;
            transfer_modal_title.textContent = `Edit ${json.code}`;
            transfer_source.value = json.source_warehouse_id;
            transfer_destination.value = json.destination_warehouse_id;
            $('#transfer_source, #transfer_destination').trigger('change.select2');
            const editDate = json.transacted_at ? new Date(json.transacted_at) : new Date();
            if (transferDatePicker) transferDatePicker.setDate(editDate, true);
            else transfer_date.value = (json.transacted_at || '').slice(0, 16).replace('T', ' ');
            transfer_note.value = json.note || '';
            transfer_items.innerHTML = '';
            (json.items || []).forEach(addRow);
            transferModal.show();
        } catch (error) { notify('Gagal', error.message, 'error'); }
    });
    $('#transfer_table').on('click', '.ship,.cancel', async function(event) {
        event.preventDefault();
        const isShip = this.classList.contains('ship');
        const confirmed = await confirmAction(
            isShip ? 'Kirim transfer?' : 'Batalkan transfer?',
            isShip ? 'Stok gudang asal akan langsung berkurang.' : 'Draft transfer akan dibatalkan.',
            isShip ? 'Ya, Kirim' : 'Ya, Batalkan',
            isShip ? 'question' : 'warning'
        );
        if (!confirmed) return;
        const response = await fetch(showUrl(isShip ? urls.ship : urls.cancel, this.dataset.id), {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json'},
        });
        const json = await response.json();
        if (!response.ok) return notify('Gagal', errorMessage(json), 'error');
        table.ajax.reload();
        notify('Berhasil', json.message || 'Status transfer diperbarui', 'success');
    });
    $('#transfer_table').on('click', '.receive', async function(event) {
        event.preventDefault();
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
                return `<div class="border border-gray-300 border-dashed rounded p-5 mb-5 receive-row" data-item-id="${row.item_id}" data-conversion="${receiveUnit?.conversion_qty || 1}" data-sent-base="${row.qty_base}">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-3"><label class="fs-6 fw-bold form-label mb-2">Item</label><div class="fw-bold text-gray-800">${escapeHtml(row.item?.sku)} - ${escapeHtml(row.item?.name)}</div><div class="text-muted fs-7">${escapeHtml(row.note || '')}</div></div>
                        <div class="col-md-2"><label class="fs-6 fw-bold form-label mb-2">Dikirim</label><div class="fw-semibold">${row.qty_input} ${escapeHtml(row.unit?.name || '')}</div><div class="text-muted fs-7">${row.qty_base} satuan dasar</div></div>
                        <div class="col-md-2"><label class="required fs-6 fw-bold form-label mb-2">Diterima (${escapeHtml(receiveUnit?.name || 'UNIT')})</label><input class="form-control form-control-solid received-qty" type="number" min="0" max="${expectedReceiveQty}" value="${expectedReceiveQty}"></div>
                        <div class="col-md-2"><label class="fs-6 fw-bold form-label mb-2">Selisih</label><div class="fw-bold discrepancy-preview text-success">0 satuan dasar</div></div>
                        <div class="col-md-3"><label class="fs-6 fw-bold form-label mb-2">Alasan Selisih</label><input class="form-control form-control-solid discrepancy" placeholder="Wajib jika ada selisih"></div>
                    </div>
                </div>`;
            }).join('');
            receive_items.querySelectorAll('.received-qty').forEach(input => input.addEventListener('input', updateDiscrepancyPreview));
            receive_note.value = '';
            receiveModal.show();
        } catch (error) { notify('Gagal', error.message, 'error'); }
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
        setLoading(receiveSubmit, true);
        try {
            const response = await fetch(showUrl(urls.receive, receiveId), {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', Accept: 'application/json'},
                body: JSON.stringify(payload),
            });
            const json = await response.json();
            if (!response.ok) return notify('Gagal', errorMessage(json), 'error');
            receiveModal.hide();
            table.ajax.reload();
            notify('Berhasil', json.message || 'Penerimaan transfer berhasil disimpan', 'success');
        } catch (error) {
            notify('Gagal', 'Tidak dapat menghubungi server', 'error');
        } finally {
            setLoading(receiveSubmit, false);
        }
    });
    $('#transfer_table').on('click', '.detail', async function(event) {
        event.preventDefault();
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
        } catch (error) { notify('Gagal', error.message, 'error'); }
    });
});
</script>
@endpush
