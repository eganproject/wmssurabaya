@extends('layouts.admin')

@section('title', 'Alokasi Barang Rusak')
@section('page_title', 'Alokasi Barang Rusak')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.damaged-allocations.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.damaged-allocations.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.damaged-allocations.index', 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="fa-solid fa-magnifying-glass position-absolute ms-5 text-gray-500"></i>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            @if($canCreate)
                <button type="button" class="btn btn-primary" id="btn_open_allocation" data-bs-toggle="modal" data-bs-target="#modal_damaged_allocation">Tambah</button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="damaged_allocations_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Gudang</th>
                        <th>Alokasi</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Submit By</th>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_damaged_allocation" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="allocation_modal_title">Tambah Alokasi Barang Rusak</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark fs-2"></i>
                </button>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form class="form" id="damaged_allocation_form">
                    @csrf
                    <div class="row g-3 mb-6">
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang</label>
                            <select class="form-select form-select-solid" name="warehouse_id" id="allocation_warehouse_id" required>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Jenis Alokasi</label>
                            <select class="form-select form-select-solid" name="allocation_type" id="allocation_type" required>
                                <option value="">Pilih alokasi</option>
                                <option value="dispose">Dimusnahkan</option>
                                <option value="repair">Diperbaiki</option>
                                <option value="return_vendor">Dikembalikan ke Gudang Besar</option>
                                <option value="other">Lainnya</option>
                            </select>
                            <div class="invalid-feedback" id="error_allocation_type"></div>
                        </div>
                        <div class="col-md-12">
                            <label class="fs-6 fw-bold form-label mb-2">Ref</label>
                            <input type="text" class="form-control form-control-solid" name="ref_no" id="allocation_ref_no" />
                            <div class="invalid-feedback" id="error_ref_no"></div>
                        </div>
                    </div>

                    <div id="allocation_items_container"></div>
                    <div class="mb-7">
                        <button type="button" class="btn btn-light" id="btn_add_allocation_item">Tambah Item</button>
                    </div>

                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Tanggal</label>
                        <input type="text" class="form-control form-control-solid" name="transacted_at" id="allocation_transacted_at" required />
                        <div class="invalid-feedback" id="error_transacted_at"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="allocation_note" rows="3"></textarea>
                        <div class="invalid-feedback" id="error_note"></div>
                    </div>
                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const showUrlTpl = '{{ route('admin.inventory.damaged-allocations.show', ':id') }}';
    const updateUrlTpl = '{{ route('admin.inventory.damaged-allocations.update', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.inventory.damaged-allocations.destroy', ':id') }}';
    const approveUrlTpl = '{{ route('admin.inventory.damaged-allocations.approve', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const damagedItems = @json($items);
    const damagedStocks = @json($damagedStocks);

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#damaged_allocations_table');
        const form = document.getElementById('damaged_allocation_form');
        const modalEl = document.getElementById('modal_damaged_allocation');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const itemsContainer = document.getElementById('allocation_items_container');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const titleEl = document.getElementById('allocation_modal_title');
        const dateEl = document.getElementById('allocation_transacted_at');
        const warehouseEl = document.getElementById('allocation_warehouse_id');
        let fpDate = null;

        const pad = n => String(n).padStart(2, '0');
        const nowString = () => {
            const d = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
        };
        const statusLabel = status => status === 'approved'
            ? '<span class="badge badge-light-success">Disetujui</span>'
            : '<span class="badge badge-light-warning">Menunggu</span>';
        const clearErrors = () => {
            ['error_allocation_type','error_ref_no','error_transacted_at','error_note'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            itemsContainer.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
        };
        const initSelect2 = el => {
            if (el && typeof $ !== 'undefined' && $.fn.select2) {
                $(el).select2({ placeholder: 'Pilih item', allowClear: true, width: '100%' });
            }
        };
        const itemOptionsHtml = (warehouseId) => damagedItems.map(item => {
            const stock = Number(damagedStocks?.[String(warehouseId)]?.[String(item.id)] || 0);
            return stock > 0
                ? `<option value="${item.id}" data-stock="${stock}">${item.sku} - ${item.name} (tersedia: ${stock})</option>`
                : '';
        }).join('');
        const refreshItemOptions = () => {
            itemsContainer.querySelectorAll('.allocation-item-select').forEach(select => {
                const selected = select.value;
                select.innerHTML = `<option value=""></option>${itemOptionsHtml(warehouseEl?.value || '')}`;
                if ([...select.options].some(option => option.value === selected)) select.value = selected;
                $(select).trigger('change.select2');
            });
        };
        const renumberRows = () => {
            itemsContainer.querySelectorAll('.allocation-item-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach(el => {
                    el.name = `items[${idx}][${el.getAttribute('data-name')}]`;
                });
            });
        };
        const createRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 allocation-item-row';
            row.innerHTML = `
                <div class="col-md-6">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid allocation-item-select" data-name="item_id" required>
                        <option value=""></option>${itemOptionsHtml(warehouseEl?.value || '')}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
                    <div class="invalid-feedback" data-error-for="qty"></div>
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-item">Hapus</button>
                </div>`;
            itemsContainer.appendChild(row);
            const selectEl = row.querySelector('select');
            const qtyEl = row.querySelector('[data-name="qty"]');
            const noteEl = row.querySelector('[data-name="note"]');
            if (data.item_id) selectEl.value = String(data.item_id);
            if (qtyEl) qtyEl.value = data.qty ?? '';
            if (noteEl) noteEl.value = data.note ?? '';
            initSelect2(selectEl);
            renumberRows();
        };
        const resetForm = () => {
            form.reset();
            form.dataset.editId = '';
            if (titleEl) titleEl.textContent = 'Tambah Alokasi Barang Rusak';
            itemsContainer.innerHTML = '';
            createRow();
            if (fpDate) fpDate.setDate(nowString(), true, 'Y-m-d H:i');
            else if (dateEl) dateEl.value = nowString();
            clearErrors();
        };

        document.getElementById('btn_open_allocation')?.addEventListener('click', resetForm);
        document.getElementById('btn_add_allocation_item')?.addEventListener('click', () => createRow());
        warehouseEl?.addEventListener('change', refreshItemOptions);
        itemsContainer.addEventListener('click', e => {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;
            btn.closest('.allocation-item-row')?.remove();
            if (!itemsContainer.querySelector('.allocation-item-row')) createRow();
            renumberRows();
        });
        if (typeof flatpickr !== 'undefined' && dateEl) {
            fpDate = flatpickr(dateEl, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: { url: dataUrl, dataSrc: 'data', data: params => { params.q = searchInput?.value || ''; } },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'warehouse' },
                { data: 'allocation_type' },
                { data: 'status', render: data => statusLabel(data) },
                { data: 'transacted_at' },
                { data: 'submit_by' },
                { data: 'item' },
                { data: 'qty' },
                { data: 'note' },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (id, type, row) => {
                    const approved = row.status === 'approved';
                    const approve = !approved && canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${id}">Approve</a></div>` : '';
                    const edit = !approved && canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${id}">Edit</a></div>` : '';
                    const del = !approved && canDelete ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${id}">Hapus</a></div>` : '';
                    const actions = approve + edit + del;
                    if (!actions) return '';
                    return `<a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">Actions</a><div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">${actions}</div>`;
                }},
            ],
        });
        dt.on('draw', () => window.KTMenu?.createInstances());
        searchInput?.addEventListener('keyup', () => dt.ajax.reload());

        tableEl.on('click', '.btn-edit', async function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const res = await fetch(showUrlTpl.replace(':id', id), { headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (!res.ok) return Swal?.fire('Error', json.message || 'Gagal memuat data', 'error');
            form.dataset.editId = id;
            if (titleEl) titleEl.textContent = `Edit ${json.code || ''}`.trim();
            document.getElementById('allocation_type').value = json.allocation_type || '';
            if (warehouseEl) warehouseEl.value = json.warehouse_id || warehouseEl.value;
            document.getElementById('allocation_ref_no').value = json.ref_no || '';
            document.getElementById('allocation_note').value = json.note || '';
            if (fpDate) fpDate.setDate(json.transacted_at || null, true, 'Y-m-d H:i');
            else dateEl.value = json.transacted_at || '';
            itemsContainer.innerHTML = '';
            (json.items || []).forEach(item => createRow(item));
            if (!(json.items || []).length) createRow();
            clearErrors();
            modal?.show();
        });

        tableEl.on('click', '.btn-delete,.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const isApprove = this.classList.contains('btn-approve');
            const confirm = await Swal.fire({
                title: isApprove ? 'Approve alokasi?' : 'Hapus alokasi?',
                icon: isApprove ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: isApprove ? 'Approve' : 'Hapus',
                cancelButtonText: 'Batal',
                buttonsStyling: false,
                customClass: { confirmButton: isApprove ? 'btn btn-success' : 'btn btn-danger', cancelButton: 'btn btn-light' },
            });
            if (!confirm.isConfirmed) return;
            const url = (isApprove ? approveUrlTpl : deleteUrlTpl).replace(':id', id);
            const body = isApprove ? new URLSearchParams({}) : new URLSearchParams({ _method: 'DELETE' });
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' }, body });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) return Swal.fire('Error', json.message || 'Gagal memproses data', 'error');
            Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
            dt.ajax.reload();
        });

        form?.addEventListener('submit', async e => {
            e.preventDefault();
            clearErrors();
            const id = form.dataset.editId;
            const url = id ? updateUrlTpl.replace(':id', id) : storeUrl;
            const fd = new FormData(form);
            if (id) fd.append('_method', 'PUT');
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: fd });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                Swal?.fire('Error', json.message || 'Gagal menyimpan data', 'error');
                return;
            }
            modal?.hide();
            Swal?.fire('Berhasil', json.message || 'Berhasil', 'success');
            dt.ajax.reload();
        });
    });
</script>
@endpush
