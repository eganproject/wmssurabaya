@extends('layouts.admin')

@section('title', 'Stock Opname')
@section('page_title', 'Stock Opname')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.stock-opname.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.inventory.stock-opname.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.inventory.stock-opname.index', 'delete');
    $canImport = $canCreate && !empty($importUrl ?? null);
@endphp

@push('styles')
<style>
    .stock-opname-toolbar { flex-wrap: wrap; gap: .75rem; }
    .stock-opname-filter { flex-wrap: wrap; }
    .opname-import-panel {
        border: 1px dashed #cbd5e1;
        background: #f8fafc;
        border-radius: 8px;
        padding: 1rem;
    }
    .opname-item-row {
        border: 1px solid #eef0f6;
        border-radius: 8px;
        padding: 1rem;
        background: #fff;
    }
    @media (max-width: 991.98px) {
        .stock-opname-toolbar,
        .stock-opname-filter,
        .stock-opname-filter > *,
        .stock-opname-toolbar > .btn {
            width: 100% !important;
            max-width: none !important;
        }
        .modal-stock-opname-dialog {
            margin: .5rem;
        }
        .modal-stock-opname-body {
            margin-left: 1rem !important;
            margin-right: 1rem !important;
        }
    }
</style>
@endpush

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
            <div class="d-flex align-items-center stock-opname-toolbar">
                <div class="d-flex align-items-center gap-2 me-4 stock-opname-filter">
                    <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" />
                    <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" />
                    <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                    <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                </div>
                @if($canCreate)
                    <button type="button" class="btn btn-primary" id="btn_open_opname" data-bs-toggle="modal" data-bs-target="#modal_stock_opname">Tambah</button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_opname_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Submit By</th>
                        <th>Total Item</th>
                        <th>Total Adjust</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_stock_opname" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-1200px modal-stock-opname-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Tambah Stock Opname</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll-y mx-5 mx-xl-10 my-7 modal-stock-opname-body">
                <form class="form" id="stock_opname_form">
                    @csrf
                    <div class="row g-5 mb-7">
                        <div class="col-lg-5">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang</label>
                            <select class="form-select form-select-solid" name="warehouse_id" id="opname_warehouse_id" required>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" data-type="{{ $warehouse->type }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" id="opname_warehouse_unit_info"></div>
                        </div>
                        <div class="col-lg-7">
                            @if($canImport)
                                <div class="opname-import-panel">
                                    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end">
                                        <div class="flex-grow-1">
                                            <label class="fs-6 fw-bold form-label mb-2">Import Data Stock Opname</label>
                                            <input type="file" class="form-control form-control-solid" id="opname_import_file" accept=".xlsx,.xls,.csv" />
                                            <div class="invalid-feedback d-block" id="error_opname_import_file"></div>
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap">
                                            @if(!empty($templateUrl ?? null))
                                                <a href="{{ $templateUrl }}" class="btn btn-light-success">Template</a>
                                            @endif
                                            <button type="button" class="btn btn-light-primary" id="btn_import_opname_submit">Muat ke Form</button>
                                        </div>
                                    </div>
                                    <div class="form-text mt-3">
                                        Header minimal: <strong>sku</strong>, <strong>counted_qty_input</strong>. Opsional: <strong>unit/satuan</strong>, <strong>note</strong>, <strong>item_note</strong>, <strong>transacted_at</strong>.
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div id="opname_items_container"></div>
                    <div class="mb-7">
                        <button type="button" class="btn btn-light" id="btn_add_opname_item">Tambah Item</button>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Tanggal</label>
                        <input type="text" class="form-control form-control-solid" name="transacted_at" id="opname_transacted_at" placeholder="YYYY-MM-DD HH:mm" />
                        <div class="invalid-feedback" id="error_transacted_at"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="opname_note" rows="3"></textarea>
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
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const importUrl = '{{ $importUrl ?? '' }}';
    const itemsUrl = '{{ route('admin.inventory.stock-opname.items') }}';
    const detailUrlTpl = '{{ route('admin.inventory.stock-opname.show', ':id') }}';
    const approveUrlTpl = '{{ route('admin.inventory.stock-opname.approve', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.inventory.stock-opname.destroy', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    let itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}" data-stock="{{ $item->stock }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    let opnameItems = @json($items);

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_opname_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('stock_opname_form');
        const modalEl = document.getElementById('modal_stock_opname');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const itemsContainer = document.getElementById('opname_items_container');
        const addItemBtn = document.getElementById('btn_add_opname_item');
        const openBtn = document.getElementById('btn_open_opname');
        const transactedAtEl = document.getElementById('opname_transacted_at');
        const warehouseEl = document.getElementById('opname_warehouse_id');
        const warehouseUnitInfo = document.getElementById('opname_warehouse_unit_info');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const importInput = document.getElementById('opname_import_file');
        const importError = document.getElementById('error_opname_import_file');
        const importSubmit = document.getElementById('btn_import_opname_submit');
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
            if (status === 'completed') return '<span class="badge badge-light-success">Selesai</span>';
            return '<span class="badge badge-light-warning">Berjalan</span>';
        };

        const clearErrors = () => {
            ['error_transacted_at','error_note'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            itemsContainer?.querySelectorAll('[data-error-for]')?.forEach(el => { el.textContent = ''; });
            itemsContainer?.querySelectorAll('.opname-item-select.is-invalid')?.forEach(el => { el.classList.remove('is-invalid'); });
        };

        const validateUniqueItems = () => {
            if (!itemsContainer) return true;
            const rows = Array.from(itemsContainer.querySelectorAll('.opname-item-row'));
            const counts = {};
            rows.forEach((row) => {
                const selectEl = row.querySelector('.opname-item-select');
                const val = selectEl?.value;
                if (val) {
                    counts[val] = (counts[val] || 0) + 1;
                }
            });
            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.opname-item-select');
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
            const rows = itemsContainer.querySelectorAll('.opname-item-row');
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
                ? '<span class="text-primary fw-bold">Gudang Besar:</span> hasil hitung fisik wajib dimasukkan sebagai koli/kemasan utuh.'
                : '<span class="text-success fw-bold">Gudang Kecil:</span> hasil hitung fisik menggunakan PCS/SET.';
        };
        const syncRowUnit = (row, selectedUnitId = null) => {
            const item = opnameItems.find(value => String(value.id) === String(row.querySelector('.opname-item-select')?.value));
            const units = (item?.units || []).filter(unit => isBulkWarehouse() ? !unit.is_base : unit.is_base);
            const baseUnit = (item?.units || []).find(unit => unit.is_base);
            const unitEl = row.querySelector('[data-name="unit_id"]');
            unitEl.innerHTML = units.length
                ? units.map(unit => `<option value="${unit.id}" data-conversion="${unit.conversion_qty}" data-name="${unit.name}">${unit.name}</option>`).join('')
                : '<option value="">Satuan belum dikonfigurasi</option>';
            if (selectedUnitId && units.some(unit => String(unit.id) === String(selectedUnitId))) unitEl.value = String(selectedUnitId);
            const selected = units.find(unit => String(unit.id) === String(unitEl.value));
            const stockBase = Number(row.querySelector('.opname-item-select')?.selectedOptions?.[0]?.dataset?.stock || 0);
            const conversion = Number(selected?.conversion_qty || 1);
            const counted = Number(row.querySelector('[data-name="counted_qty_input"]')?.value || 0);
            row.querySelector('.opname-unit-label').textContent = isBulkWarehouse() ? 'Satuan Koli' : 'Satuan';
            row.querySelector('.opname-counted-label').textContent = isBulkWarehouse() ? `Jumlah Koli (${selected?.name || 'KOLI'})` : `Counted (${selected?.name || baseUnit?.name || 'PCS'})`;
            row.querySelector('.opname-count-info').textContent = selected
                ? `1 ${selected.name} = ${conversion} ${baseUnit?.name || 'dasar'}; hasil hitung ${counted * conversion} ${baseUnit?.name || 'dasar'}`
                : '';
            const systemEl = row.querySelector('[data-name="system_qty"]');
            if (systemEl) systemEl.value = conversion > 0 ? stockBase / conversion : stockBase;
            row.querySelector('.opname-system-label').textContent = isBulkWarehouse() ? `System (${selected?.name || 'KOLI'})` : `System (${baseUnit?.name || 'PCS'})`;
        };

        const updateSystemQty = (row) => {
            const selectEl = row.querySelector('.opname-item-select');
            const selected = selectEl?.selectedOptions?.[0];
            if (selected) syncRowUnit(row);
        };

        const createItemRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 opname-item-row';
            row.innerHTML = `
                <div class="col-12 col-lg-4">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid opname-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="required fs-6 fw-bold form-label mb-2 opname-unit-label">Satuan</label>
                    <select class="form-select form-select-solid" data-name="unit_id" required></select>
                    <div class="invalid-feedback" data-error-for="unit_id"></div>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="fs-6 fw-bold form-label mb-2 opname-system-label">System</label>
                    <input type="number" class="form-control form-control-solid" data-name="system_qty" readonly />
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="required fs-6 fw-bold form-label mb-2 opname-counted-label">Counted</label>
                    <input type="number" min="0" step="1" class="form-control form-control-solid" data-name="counted_qty_input" required />
                    <div class="form-text fs-8 opname-count-info"></div>
                    <div class="invalid-feedback" data-error-for="counted_qty_input"></div>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-light-danger btn-sm btn-remove-item">Hapus</button>
                </div>
            `;
            itemsContainer.appendChild(row);

            const selectEl = row.querySelector('.opname-item-select');
            if (data.item_id) {
                selectEl.value = String(data.item_id);
            }
            const countedEl = row.querySelector('input[data-name="counted_qty_input"]');
            if (countedEl) countedEl.value = data.counted_qty_input ?? data.counted_qty ?? '';
            const noteEl = row.querySelector('input[data-name="note"]');
            if (noteEl) noteEl.value = data.note ?? '';

            initSelect2(selectEl);
            syncRowUnit(row, data.unit_id);
            renumberRows();
            validateUniqueItems();
        };

        const resetForm = () => {
            form?.reset();
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
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
            if (e.target.matches('.opname-item-select')) {
                const row = e.target.closest('.opname-item-row');
                if (row) updateSystemQty(row);
                validateUniqueItems();
            }
            if (e.target.matches('[data-name="unit_id"]')) syncRowUnit(e.target.closest('.opname-item-row'), e.target.value);
        });
        itemsContainer?.addEventListener('input', event => {
            if (event.target.matches('[data-name="counted_qty_input"]')) {
                const row = event.target.closest('.opname-item-row');
                syncRowUnit(row, row.querySelector('[data-name="unit_id"]').value);
            }
        });

        itemsContainer?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;
            const row = btn.closest('.opname-item-row');
            if (row) row.remove();
            if (itemsContainer.querySelectorAll('.opname-item-row').length === 0) {
                createItemRow();
            } else {
                renumberRows();
            }
            validateUniqueItems();
        });

        addItemBtn?.addEventListener('click', () => createItemRow());
        openBtn?.addEventListener('click', resetForm);
        warehouseEl?.addEventListener('change', async () => {
            const res = await fetch(`${itemsUrl}?warehouse_id=${encodeURIComponent(warehouseEl.value)}`, {
                headers: {Accept: 'application/json'}
            });
            const json = await res.json();
            opnameItems = json.data || [];
            itemOptionsHtml = (json.data || []).map(item =>
                `<option value="${item.id}" data-stock="${item.stock}">${item.sku} - ${item.name}</option>`
            ).join('');
            itemsContainer.innerHTML = '';
            createItemRow();
            updateWarehouseInfo();
        });
        updateWarehouseInfo();

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
                { data: 'items_count' },
                { data: 'total_adjustment' },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row) => {
                    const detailItem = `<div class="menu-item px-3"><a href="${detailUrlTpl.replace(':id', data)}" class="menu-link px-3">Detail</a></div>`;
                    const isCompleted = row?.status === 'completed';
                    const approveItem = (!isCompleted && canUpdate)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${data}">Selesaikan</a></div>`
                        : '';
                    const delItem = (!isCompleted && canDelete)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>`
                        : '';
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
                                ${detailItem}${approveItem}${delItem}
                            </div>
                        </div>
                    `;
                }},
            ]
        });

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        filterApplyBtn?.addEventListener('click', reloadTable);
        filterResetBtn?.addEventListener('click', () => {
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            reloadTable();
        });

        importInput?.addEventListener('change', () => {
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
            formData.append('warehouse_id', warehouseEl?.value || '');
            importSubmit.disabled = true;

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
                    const firstError = json?.errors ? Object.values(json.errors).flat()[0] : null;
                    const msg = firstError || json?.message || 'Gagal import';
                    if (importError) importError.textContent = msg;
                    if (typeof Swal !== 'undefined') Swal.fire('Error', msg, 'error');
                    return;
                }

                itemsContainer.innerHTML = '';
                (json.items || []).forEach(item => createItemRow(item));
                if (!itemsContainer.querySelector('.opname-item-row')) createItemRow();

                if (json.transacted_at) {
                    if (fpTransacted) fpTransacted.setDate(json.transacted_at, true, 'Y-m-d H:i');
                    else if (transactedAtEl) transactedAtEl.value = json.transacted_at;
                }
                const noteEl = document.getElementById('opname_note');
                if (json.note && noteEl && !noteEl.value) noteEl.value = json.note;
                if (importInput) importInput.value = '';
                validateUniqueItems();

                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', `${json.items?.length || 0} item dimuat ke form. Periksa kembali sebelum simpan.`, 'success');
                }
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal import', 'error');
                if (importError) importError.textContent = 'Gagal import';
            } finally {
                importSubmit.disabled = false;
            }
        });

        tableEl.on('click', '.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Selesaikan batch ini?',
                    text: 'Setelah selesai, batch tidak bisa diubah atau ditambah item.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Selesaikan',
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
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menyelesaikan', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyelesaikan', 'error');
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
                    text: 'Stock opname akan dihapus',
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

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            if (!validateUniqueItems()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item tidak boleh duplikat', 'error');
                return;
            }
            const formData = new FormData(form);
            try {
                const res = await fetch(storeUrl, {
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
                                const row = itemsContainer.querySelectorAll('.opname-item-row')[idx];
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
