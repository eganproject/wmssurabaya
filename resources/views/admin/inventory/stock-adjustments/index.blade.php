@extends('layouts.admin')

@section('title', 'Penyesuaian Stok')
@section('page_title', 'Penyesuaian Stok')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.stock-adjustments.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.stock-adjustments.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.stock-adjustments.index', 'delete');
    $canImport = $canCreate && !empty($importUrl ?? null);
@endphp

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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 me-4">
                <select class="form-select form-select-solid w-200px" id="filter_status">
                    <option value="">Semua Status</option>
                    <option value="pending">Menunggu Persetujuan</option>
                    <option value="approved">Disetujui</option>
                </select>
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
            @if($canImport)
                <button type="button" class="btn btn-light-primary me-3" id="btn_import_adjustment" data-bs-toggle="modal" data-bs-target="#modal_import_adjustment">
                    Import Excel
                </button>
            @endif
            @if($canCreate)
                <button type="button" class="btn btn-primary" id="btn_open_adjustment" data-bs-toggle="modal" data-bs-target="#modal_stock_adjustment">Tambah</button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_adjustments_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Submit By</th>
                        <th>Item</th>
                        <th>Qty In</th>
                        <th>Qty Out</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_stock_adjustment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="adjustment_modal_title">Tambah Penyesuaian</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                <form class="form" id="stock_adjustment_form">
                    @csrf
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Gudang</label>
                        <select class="form-select form-select-solid" name="warehouse_id" id="adjustment_warehouse_id" required>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" data-type="{{ $warehouse->type }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="adjustment_warehouse_unit_info"></div>
                    </div>
                    <div id="adjustment_items_container"></div>
                    <div class="mb-7">
                        <button type="button" class="btn btn-light" id="btn_add_adjustment_item">Tambah Item</button>
                    </div>

                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Tanggal</label>
                        <input type="text" class="form-control form-control-solid" name="transacted_at" id="adjustment_transacted_at" placeholder="YYYY-MM-DD HH:mm" required />
                        <div class="invalid-feedback" id="error_transacted_at"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="adjustment_note" rows="3"></textarea>
                        <div class="invalid-feedback" id="error_note"></div>
                    </div>
                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Simpan</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if($canImport)
    <div class="modal fade" id="modal_import_adjustment" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bolder">Import Penyesuaian Stok</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body scroll-y mx-5 mx-xl-15 my-7">
                    <div class="mb-6">
                        <div class="text-muted fs-7">
                            Header minimal: <strong>sku</strong>, <strong>qty_input</strong>, <strong>direction/arah</strong> (in/out atau tambah/kurangi).<br>
                            Pada Gudang Besar, <strong>qty_input</strong> adalah jumlah koli. Pada Gudang Kecil, nilainya adalah PCS/SET.<br>
                            Header lama <strong>qty</strong> tetap didukung.
                            Opsional: <strong>note</strong>, <strong>item_note</strong>, <strong>transacted_at</strong>.
                        </div>
                        @if(!empty($templateUrl ?? null))
                            <a href="{{ $templateUrl }}" class="btn btn-sm btn-light-success mt-3">Download Template Excel</a>
                        @endif
                    </div>
                    <div class="fv-row mb-6">
                        <label class="required fs-6 fw-bold form-label mb-2">Gudang</label>
                        <select class="form-select form-select-solid" id="import_adjustment_warehouse_id">
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" data-type="{{ $warehouse->type }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fv-row mb-6">
                        <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                        <input type="file" class="form-control form-control-solid" id="import_adjustment_file" accept=".xlsx,.xls" />
                        <div class="invalid-feedback d-block" id="error_import_adjustment_file"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn_import_adjustment_submit">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const detailUrlTpl = '{{ route('admin.inventory.stock-adjustments.detail', ':id') }}';
    const showUrlTpl = '{{ route('admin.inventory.stock-adjustments.show', ':id') }}';
    const updateUrlTpl = '{{ route('admin.inventory.stock-adjustments.update', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.inventory.stock-adjustments.destroy', ':id') }}';
    const approveUrlTpl = '{{ route('admin.inventory.stock-adjustments.approve', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const importUrl = '{{ $importUrl ?? '' }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const adjustmentItems = @json($items);

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_adjustments_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('stock_adjustment_form');
        const modalEl = document.getElementById('modal_stock_adjustment');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const itemsContainer = document.getElementById('adjustment_items_container');
        const addItemBtn = document.getElementById('btn_add_adjustment_item');
        const openBtn = document.getElementById('btn_open_adjustment');
        const modalTitle = document.getElementById('adjustment_modal_title');
        const transactedAtEl = document.getElementById('adjustment_transacted_at');
        const warehouseEl = document.getElementById('adjustment_warehouse_id');
        const warehouseUnitInfo = document.getElementById('adjustment_warehouse_unit_info');
        const statusEl = document.getElementById('filter_status');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const importBtn = document.getElementById('btn_import_adjustment');
        const importModalEl = document.getElementById('modal_import_adjustment');
        const importModal = importModalEl ? new bootstrap.Modal(importModalEl) : null;
        const importInput = document.getElementById('import_adjustment_file');
        const importError = document.getElementById('error_import_adjustment_file');
        const importSubmit = document.getElementById('btn_import_adjustment_submit');
        let fpFrom = null;
        let fpTo = null;
        let fpTransacted = null;

        const formatDateTime = (date) => {
            const pad = (n) => String(n).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        };

        const getJakartaNow = () => {
            const jkt = new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            return formatDateTime(jkt);
        };

        const statusLabel = (status) => {
            if (status === 'approved') return '<span class="badge badge-light-success">Disetujui</span>';
            return '<span class="badge badge-light-warning">Menunggu</span>';
        };

        const clearErrors = () => {
            ['error_transacted_at','error_note'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            itemsContainer?.querySelectorAll('[data-error-for]')?.forEach(el => { el.textContent = ''; });
            itemsContainer?.querySelectorAll('.adjustment-item-select.is-invalid')?.forEach(el => { el.classList.remove('is-invalid'); });
        };

        const validateUniqueItems = () => {
            if (!itemsContainer) return true;
            const rows = Array.from(itemsContainer.querySelectorAll('.adjustment-item-row'));
            const counts = {};
            rows.forEach((row) => {
                const selectEl = row.querySelector('.adjustment-item-select');
                const val = selectEl?.value;
                if (val) {
                    counts[val] = (counts[val] || 0) + 1;
                }
            });
            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.adjustment-item-select');
                const val = selectEl?.value;
                const errEl = row.querySelector('[data-error-for="item_id"]');
                if (selectEl && val && counts[val] > 1) {
                    hasDuplicate = true;
                    if (errEl) errEl.textContent = 'Item tidak boleh duplikat';
                    selectEl.classList.add('is-invalid');
                } else {
                    if (errEl && errEl.textContent === 'Item tidak boleh duplikat') {
                        errEl.textContent = '';
                    }
                    selectEl?.classList.remove('is-invalid');
                }
            });
            return !hasDuplicate;
        };

        const initSelect2 = (selectEl) => {
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Pilih item',
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: 0,
                })
                    .on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
            }
        };

        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (transactedAtEl) {
                fpTransacted = flatpickr(transactedAtEl, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
            }
        }

        const renumberRows = () => {
            const rows = itemsContainer.querySelectorAll('.adjustment-item-row');
            rows.forEach((row, idx) => {
                row.querySelectorAll('[data-name]')?.forEach((el) => {
                    const key = el.getAttribute('data-name');
                    el.name = `items[${idx}][${key}]`;
                });
            });
        };

        const isBulkWarehouse = () => warehouseEl?.selectedOptions?.[0]?.dataset?.type === 'bulk';
        const updateWarehouseInfo = () => {
            if (!warehouseUnitInfo) return;
            warehouseUnitInfo.innerHTML = isBulkWarehouse()
                ? '<span class="text-primary fw-bold">Gudang Besar:</span> penyesuaian wajib diinput dalam koli/kemasan utuh.'
                : '<span class="text-success fw-bold">Gudang Kecil:</span> penyesuaian menggunakan PCS/SET.';
        };
        const syncRowUnit = (row, selectedUnitId = null) => {
            const item = adjustmentItems.find(value => String(value.id) === String(row.querySelector('.adjustment-item-select')?.value));
            const units = (item?.units || []).filter(unit => isBulkWarehouse() ? !unit.is_base : unit.is_base);
            const baseUnit = (item?.units || []).find(unit => unit.is_base);
            const unitEl = row.querySelector('[data-name="unit_id"]');
            unitEl.innerHTML = units.length
                ? units.map(unit => `<option value="${unit.id}" data-conversion="${unit.conversion_qty}" data-name="${unit.name}">${unit.name}</option>`).join('')
                : '<option value="">Satuan belum dikonfigurasi</option>';
            if (selectedUnitId && units.some(unit => String(unit.id) === String(selectedUnitId))) unitEl.value = String(selectedUnitId);
            const selected = units.find(unit => String(unit.id) === String(unitEl.value));
            const qty = Number(row.querySelector('[data-name="qty_input"]')?.value || 0);
            row.querySelector('.adjustment-unit-label').textContent = isBulkWarehouse() ? 'Satuan Koli' : 'Satuan';
            row.querySelector('.adjustment-qty-label').textContent = isBulkWarehouse() ? `Jumlah Koli (${selected?.name || 'KOLI'})` : `Qty (${selected?.name || baseUnit?.name || 'PCS'})`;
            row.querySelector('.adjustment-qty-info').textContent = selected
                ? `1 ${selected.name} = ${selected.conversion_qty} ${baseUnit?.name || 'dasar'}; total ${qty * selected.conversion_qty} ${baseUnit?.name || 'dasar'}`
                : '';
        };

        const createItemRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 adjustment-item-row';
            row.innerHTML = `
                <div class="col-md-4">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid adjustment-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Arah</label>
                    <select class="form-select form-select-solid" data-name="direction" required>
                        <option value="in">Tambah (In)</option>
                        <option value="out">Kurangi (Out)</option>
                    </select>
                    <div class="invalid-feedback" data-error-for="direction"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2 adjustment-unit-label">Satuan</label>
                    <select class="form-select form-select-solid" data-name="unit_id" required></select>
                    <div class="invalid-feedback" data-error-for="unit_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2 adjustment-qty-label">Qty</label>
                    <input type="number" min="1" step="1" class="form-control form-control-solid" data-name="qty_input" required />
                    <div class="form-text fs-8 adjustment-qty-info"></div>
                    <div class="invalid-feedback" data-error-for="qty_input"></div>
                </div>
                <div class="col-md-1">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-item">Hapus</button>
                </div>
            `;
            itemsContainer.appendChild(row);

            const selectEl = row.querySelector('.adjustment-item-select');
            if (data.item_id) {
                selectEl.value = String(data.item_id);
            }
            const dirEl = row.querySelector('[data-name="direction"]');
            if (dirEl && data.direction) dirEl.value = data.direction;
            const qtyEl = row.querySelector('input[data-name="qty_input"]');
            if (qtyEl) qtyEl.value = data.qty_input ?? data.qty ?? '';
            const noteEl = row.querySelector('input[data-name="note"]');
            if (noteEl) noteEl.value = data.note ?? '';

            initSelect2(selectEl);
            syncRowUnit(row, data.unit_id);
            renumberRows();
            validateUniqueItems();
        };

        warehouseEl?.addEventListener('change', () => {
            updateWarehouseInfo();
            itemsContainer.querySelectorAll('.adjustment-item-row').forEach(row => syncRowUnit(row));
        });
        itemsContainer?.addEventListener('change', event => {
            if (event.target.matches('.adjustment-item-select')) syncRowUnit(event.target.closest('.adjustment-item-row'));
            if (event.target.matches('[data-name="unit_id"]')) syncRowUnit(event.target.closest('.adjustment-item-row'), event.target.value);
        });
        itemsContainer?.addEventListener('input', event => {
            if (event.target.matches('[data-name="qty_input"]')) syncRowUnit(event.target.closest('.adjustment-item-row'), event.target.closest('.adjustment-item-row').querySelector('[data-name="unit_id"]').value);
        });
        updateWarehouseInfo();

        const resetForm = () => {
            form?.reset();
            form.dataset.editId = '';
            if (modalTitle) modalTitle.textContent = 'Tambah Penyesuaian';
            const nowJkt = getJakartaNow();
            if (fpTransacted) {
                fpTransacted.setDate(nowJkt, true, 'Y-m-d H:i');
            } else if (transactedAtEl) {
                transactedAtEl.value = nowJkt;
            }
            itemsContainer.innerHTML = '';
            createItemRow();
            clearErrors();
            validateUniqueItems();
        };

        itemsContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.adjustment-item-select')) {
                validateUniqueItems();
            }
        });

        itemsContainer?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;
            const row = btn.closest('.adjustment-item-row');
            if (row) row.remove();
            if (itemsContainer.querySelectorAll('.adjustment-item-row').length === 0) {
                createItemRow();
            } else {
                renumberRows();
            }
            validateUniqueItems();
        });

        addItemBtn?.addEventListener('click', () => createItemRow());
        openBtn?.addEventListener('click', resetForm);

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
                    if (statusEl?.value) params.status = statusEl.value;
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'code' },
                { data: 'status', orderable: false, searchable: false, render: (data) => statusLabel(data) },
                { data: 'transacted_at' },
                { data: 'submit_by' },
                { data: 'item' },
                { data: 'qty_in' },
                { data: 'qty_out' },
                { data: 'note' },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                    const isApproved = row?.status === 'approved';
                    const detailItem = `<div class="menu-item px-3"><a href="${detailUrlTpl.replace(':id', data)}" class="menu-link px-3">Detail</a></div>`;
                    const approveItem = (!isApproved && canUpdate)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${data}">Approve</a></div>`
                        : '';
                    const editItem = (!isApproved && canUpdate) ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}">Edit</a></div>` : '';
                    const delItem = (!isApproved && canDelete) ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>` : '';
                    const actions = `${detailItem}${approveItem}${editItem}${delItem}`;
                    if (!actions) return '';
                    return `
                        <div class="text-end">
                            <a href="#" class="btn btn-sm btn-light btn-active-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                Actions
                                <span class="svg-icon svg-icon-5 m-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                    </svg>
                                </span>
                            </a>
                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-175px py-3" data-kt-menu="true">
                                ${actions}
                            </div>
                        </div>
                    `;
                } },
            ]
        });

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        statusEl?.addEventListener('change', reloadTable);
        filterApplyBtn?.addEventListener('click', reloadTable);
        filterResetBtn?.addEventListener('click', () => {
            if (statusEl) statusEl.value = '';
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            reloadTable();
        });

        importBtn?.addEventListener('click', () => {
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
        });

        importSubmit?.addEventListener('click', async () => {
            if (!importUrl) return;
            if (importError) importError.textContent = '';
            const file = importInput?.files?.[0];
            if (!file) {
                if (importError) importError.textContent = 'Pilih file Excel terlebih dahulu.';
                return;
            }
            const formData = new FormData();
            formData.append('file', file);
            formData.append('warehouse_id', document.getElementById('import_adjustment_warehouse_id')?.value || '');
            try {
                const res = await fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    const msg = json?.errors?.file?.[0] || json?.message;
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg || 'Gagal import', 'error');
                    } else if (importError) {
                        importError.textContent = msg || 'Gagal import';
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json.message || 'Import berhasil', 'success');
                }
                if (importInput) importInput.value = '';
                importModal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal import', 'error');
            }
        });

        tableEl.on('click', '.btn-edit', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            try {
                const res = await fetch(showUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' }});
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error');
                    return;
                }
                form.dataset.editId = id;
                if (modalTitle) modalTitle.textContent = `Edit ${json.code || ''}`.trim();
                document.getElementById('adjustment_note').value = json.note || '';
                if (fpTransacted) {
                    fpTransacted.setDate(json.transacted_at || null, true, 'Y-m-d H:i');
                } else {
                    document.getElementById('adjustment_transacted_at').value = json.transacted_at || '';
                }
                if (warehouseEl) warehouseEl.value = json.warehouse_id || warehouseEl.value;

                itemsContainer.innerHTML = '';
                (json.items || []).forEach(item => createItemRow(item));
                if ((json.items || []).length === 0) {
                    createItemRow();
                }
                clearErrors();
                validateUniqueItems();
                modal?.show();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal memuat data', 'error');
            }
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Data penyesuaian stok akan dihapus',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;
            try {
                const res = await fetch(deleteUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menghapus', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menghapus', 'error');
            }
        });

        tableEl.on('click', '.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Setujui data ini?',
                    text: 'Setelah disetujui, data tidak bisa diubah atau dihapus.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Approve',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-success',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;
            try {
                const res = await fetch(approveUrlTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menyetujui', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyetujui', 'error');
            }
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            if (!validateUniqueItems()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item tidak boleh duplikat', 'error');
                return;
            }
            const formData = new FormData(form);
            const editId = form.dataset.editId;
            let url = storeUrl;
            if (editId) {
                url = updateUrlTpl.replace(':id', editId);
                formData.append('_method', 'PUT');
            }
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (err) {
                    console.error('Invalid JSON', text);
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (json?.errors) {
                        const unhandled = [];
                        Object.entries(json.errors).forEach(([key, msgs]) => {
                            if (key.startsWith('items.')) {
                                const parts = key.split('.');
                                const idx = parseInt(parts[1], 10);
                                const field = parts[2];
                                const row = itemsContainer.querySelectorAll('.adjustment-item-row')[idx];
                                const errEl = row ? row.querySelector(`[data-error-for="${field}"]`) : null;
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            } else {
                                const errEl = document.getElementById(`error_${key}`);
                                if (errEl) errEl.textContent = msgs.join(', ');
                                else unhandled.push(msgs.join(', '));
                            }
                        });
                        if (unhandled.length && typeof Swal !== 'undefined') {
                            Swal.fire('Error', unhandled.join(', '), 'error');
                        }
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json.message || 'Gagal menyimpan', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                resetForm();
                reloadTable();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan', 'error');
            }
        });
    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
