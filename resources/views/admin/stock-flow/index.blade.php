@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@php
    use App\Support\Permission as Perm;
    $permMap = [];
    if (isset($routeMap['receipt'])) {
        $permMap = [
            'receipt' => [
                'create' => Perm::can(auth()->user(), 'admin.inbound.receipts.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.inbound.receipts.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.inbound.receipts.index', 'delete'),
            ],
            'return' => [
                'create' => Perm::can(auth()->user(), 'admin.inbound.returns.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.inbound.returns.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.inbound.returns.index', 'delete'),
            ],
        ];
    } elseif (isset($routeMap['picker'])) {
        $permMap = [
            'picker' => [
                'create' => Perm::can(auth()->user(), 'admin.outbound.pickers.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.outbound.pickers.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.outbound.pickers.index', 'delete'),
            ],
            'manual' => [
                'create' => Perm::can(auth()->user(), 'admin.outbound.manuals.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.outbound.manuals.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.outbound.manuals.index', 'delete'),
            ],
            'return' => [
                'create' => Perm::can(auth()->user(), 'admin.outbound.returns.index', 'create'),
                'update' => Perm::can(auth()->user(), 'admin.outbound.returns.index', 'update'),
                'delete' => Perm::can(auth()->user(), 'admin.outbound.returns.index', 'delete'),
            ],
        ];
    }
    $defaultType = $typeDefault ?? '';
    $canCreateDefault = $permMap[$defaultType]['create'] ?? false;
    $canImport = !empty($importUrl ?? null) && $canCreateDefault;
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
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
            @if($canImport)
                <button type="button" class="btn btn-light-primary me-3" id="btn_import_flow" data-bs-toggle="modal" data-bs-target="#modal_import_flow">
                    Import Excel
                </button>
            @endif
            @if($canCreateDefault)
                <button type="button" class="btn btn-primary" id="btn_open_create_flow" data-bs-toggle="modal" data-bs-target="#modal_stock_flow">
                    Tambah
                </button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_flow_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Kode</th>
                        <th>Jenis</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Submit By</th>
                        <th>Gudang</th>
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

<div class="modal fade" id="modal_stock_flow" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-1100px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="flow_modal_title">Tambah</h2>
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
                <form class="form" id="stock_flow_form">
                    @csrf
                    @if($locksToSmallWarehouse ?? false)
                        <input type="hidden" name="warehouse_id" id="flow_warehouse_id" value="{{ $smallWarehouse->id }}">
                        <div class="alert bg-light-success border border-success border-dashed d-flex align-items-center p-5 mb-7">
                            <i class="fa-solid fa-warehouse fs-2 text-success me-4"></i>
                            <div>
                                <div class="fw-bolder text-gray-800">Gudang Kecil Otomatis</div>
                                <div class="text-gray-600 fs-7">
                                    Transaksi retur selalu diproses di {{ $smallWarehouse->name }} menggunakan satuan PCS/SET.
                                </div>
                            </div>
                        </div>
                        <div id="flow_warehouse_unit_info" class="d-none"></div>
                    @else
                        <div class="fv-row mb-7">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang Tujuan</label>
                            <select class="form-select form-select-solid" name="warehouse_id" id="flow_warehouse_id" required>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" data-type="{{ $warehouse->type }}" @selected($warehouse->is_default)>{{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text" id="flow_warehouse_unit_info"></div>
                        </div>
                    @endif
                    <div id="flow_items_container"></div>
                    <div class="mb-7">
                        <button type="button" class="btn btn-light" id="btn_add_flow_item">Tambah Item</button>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Tanggal</label>
                        <input type="text" class="form-control form-control-solid" name="transacted_at" id="flow_transacted_at" placeholder="YYYY-MM-DD HH:mm" />
                        <div class="invalid-feedback" id="error_transacted_at"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Ref No</label>
                        <input type="text" class="form-control form-control-solid" name="ref_no" id="flow_ref_no" />
                        <div class="invalid-feedback" id="error_ref_no"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <textarea class="form-control form-control-solid" name="note" id="flow_note" rows="3"></textarea>
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
    <div class="modal fade" id="modal_import_flow" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bolder">{{ $importTitle ?? 'Import Data' }}</h2>
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
                            @if(($typeDefault ?? '') === 'return' && isset($routeMap['receipt']))
                                Header minimal: <strong>sku</strong>, <strong>qty_diterima</strong>, <strong>qty_bagus</strong>, <strong>qty_rusak</strong>.<br>
                            @else
                                Header minimal: <strong>sku</strong>, <strong>qty</strong>.<br>
                            @endif
                            Opsional: <strong>ref_no</strong>, <strong>note</strong>, <strong>item_note</strong>, <strong>transacted_at</strong>.
                        </div>
                        @if(!empty($templateUrl ?? null))
                            <a href="{{ $templateUrl }}" class="btn btn-sm btn-light-success mt-3">Download Template Excel</a>
                        @endif
                    </div>
                    @if(($typeDefault ?? '') === 'receipt')
                        <div class="fv-row mb-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Gudang Tujuan</label>
                            <select class="form-select form-select-solid" id="import_flow_warehouse_id">
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" data-type="{{ $warehouse->type }}" @selected($warehouse->is_default)>
                                        {{ $warehouse->name }} {{ $warehouse->type === 'bulk' ? '(qty file = koli)' : '(qty file = PCS/SET)' }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text">Gudang besar membaca qty sebagai jumlah koli; gudang kecil membaca qty sebagai PCS/SET.</div>
                        </div>
                    @endif
                    <div class="fv-row mb-6">
                        <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                        <input type="file" class="form-control form-control-solid" id="import_flow_file" accept=".xlsx,.xls" />
                        <div class="invalid-feedback d-block" id="error_import_flow_file"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn_import_flow_submit">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@php
    $itemUnitsJson = $items->mapWithKeys(function ($item) {
        return [
            (string) $item->id => $item->units->map(function ($unit) {
                return [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'conversion_qty' => (int) $unit->conversion_qty,
                    'is_base' => (bool) $unit->is_base,
                ];
            })->values(),
        ];
    });
@endphp

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const showUrlTpl = '{{ $showUrlTpl }}';
    const updateUrlTpl = '{{ $updateUrlTpl }}';
    const deleteUrlTpl = '{{ $deleteUrlTpl }}';
    const detailUrlTpl = '{{ $detailUrlTpl }}';
    const approveUrlTpl = '{{ $approveUrlTpl ?? '' }}';
    const importUrl = '{{ $importUrl ?? '' }}';
    const routeMap = @json($routeMap ?? []);
    const typeLabelMap = @json($typeOptions ?? []);
    const csrfToken = '{{ csrf_token() }}';
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const itemUnits = @json($itemUnitsJson);
    const defaultTypeFilter = '{{ $typeDefault ?? '' }}';
    const permMap = @json($permMap ?? []);
    const canCreateDefault = {{ $canCreateDefault ? 'true' : 'false' }};
    const locksToSmallWarehouse = {{ ($locksToSmallWarehouse ?? false) ? 'true' : 'false' }};
    const smallWarehouseId = '{{ $smallWarehouse->id ?? '' }}';
    const isInboundReturnFlow = defaultTypeFilter === 'return' && !!routeMap?.receipt;
    const isOutboundReturnFlow = defaultTypeFilter === 'return' && !!routeMap?.picker;

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_flow_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('stock_flow_form');
        const modalEl = document.getElementById('modal_stock_flow');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const itemsContainer = document.getElementById('flow_items_container');
        const addItemBtn = document.getElementById('btn_add_flow_item');
        const openCreateBtn = document.getElementById('btn_open_create_flow');
        const modalTitle = document.getElementById('flow_modal_title');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const transactedAtEl = document.getElementById('flow_transacted_at');
        const warehouseEl = document.getElementById('flow_warehouse_id');
        const warehouseUnitInfo = document.getElementById('flow_warehouse_unit_info');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const importBtn = document.getElementById('btn_import_flow');
        const importModalEl = document.getElementById('modal_import_flow');
        const importModal = importModalEl ? new bootstrap.Modal(importModalEl) : null;
        const importInput = document.getElementById('import_flow_file');
        const importError = document.getElementById('error_import_flow_file');
        const importSubmit = document.getElementById('btn_import_flow_submit');
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

        const resolveRoute = (type, key) => {
            if (routeMap && routeMap[type] && routeMap[type][key]) return routeMap[type][key];
            if (routeMap && routeMap[defaultTypeFilter] && routeMap[defaultTypeFilter][key]) return routeMap[defaultTypeFilter][key];
            return { store: storeUrl, show: showUrlTpl, update: updateUrlTpl, delete: deleteUrlTpl, detail: detailUrlTpl, approve: approveUrlTpl }[key] || '';
        };

        const statusLabel = (status, row = {}) => {
            if (status === 'finalized') return '<span class="badge badge-light-success">Finalisasi</span>';
            if (status === 'approved' && row?.type === 'return' && isInboundReturnFlow) {
                return '<span class="badge badge-light-primary">Area Retur Gudang Kecil</span>';
            }
            if (status === 'approved') return '<span class="badge badge-light-success">Disetujui</span>';
            if (row?.type === 'manual') {
                if (row?.scan_status === 'complete') {
                    return '<span class="badge badge-light-info">Scan Complete</span>';
                }
                if (row?.scan_status === 'in_progress') {
                    return '<span class="badge badge-light-primary">Scan Progress</span>';
                }
            }
            return '<span class="badge badge-light-warning">Menunggu</span>';
        };

        const clearErrors = () => {
            ['error_transacted_at','error_ref_no','error_note'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
            itemsContainer?.querySelectorAll('[data-error-for]')?.forEach(el => { el.textContent = ''; });
            itemsContainer?.querySelectorAll('.flow-item-select.is-invalid')?.forEach(el => { el.classList.remove('is-invalid'); });
        };

        const validateUniqueItems = () => {
            if (!itemsContainer) return true;
            const rows = Array.from(itemsContainer.querySelectorAll('.flow-item-row'));
            const rowKey = (row) => {
                const item = row.querySelector('.flow-item-select')?.value || '';
                if (!item) return '';
                const src = row.querySelector('select[data-name="stock_source"]')?.value || 'regular';
                return `${item}|${src}`;
            };
            const counts = {};
            rows.forEach((row) => {
                const key = rowKey(row);
                if (key) counts[key] = (counts[key] || 0) + 1;
            });
            let hasDuplicate = false;
            rows.forEach((row) => {
                const selectEl = row.querySelector('.flow-item-select');
                const key = rowKey(row);
                const errEl = row.querySelector('[data-error-for="item_id"]');
                if (selectEl && key && counts[key] > 1) {
                    hasDuplicate = true;
                    if (errEl) errEl.textContent = 'Item dengan sumber yang sama tidak boleh duplikat';
                    selectEl.classList.add('is-invalid');
                } else {
                    if (errEl && (errEl.textContent === 'Item tidak boleh duplikat' || errEl.textContent === 'Item dengan sumber yang sama tidak boleh duplikat')) {
                        errEl.textContent = '';
                    }
                    selectEl?.classList.remove('is-invalid');
                }
            });
            return !hasDuplicate;
        };

        const toQty = (value) => {
            const qty = parseInt(value, 10);
            return Number.isFinite(qty) && qty > 0 ? qty : 0;
        };

        const clampQty = (value, max) => Math.min(Math.max(toQty(value), 0), Math.max(toQty(max), 0));

        const syncReturnQtyRow = (row, changedField) => {
            if (!isInboundReturnFlow || !row) return;

            const receivedEl = row.querySelector('input[data-name="qty_received"]');
            const goodEl = row.querySelector('input[data-name="qty_good"]');
            const damagedEl = row.querySelector('input[data-name="qty_damaged"]');
            if (!receivedEl || !goodEl || !damagedEl) return;

            const received = toQty(receivedEl.value);
            if (changedField === 'qty_damaged') {
                const damaged = clampQty(damagedEl.value, received);
                damagedEl.value = damaged;
                goodEl.value = Math.max(0, received - damaged);
                return;
            }

            const good = clampQty(goodEl.value, received);
            goodEl.value = good;
            damagedEl.value = Math.max(0, received - good);
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

        const updateWarehouseUnitInfo = () => {
            if (!warehouseUnitInfo) return;
            const isBulkWarehouse = warehouseEl?.selectedOptions?.[0]?.dataset?.type === 'bulk';
            warehouseUnitInfo.innerHTML = isBulkWarehouse
                ? '<span class="text-primary fw-bold">Gudang Besar:</span> qty wajib diinput dalam satuan koli/kemasan (DUS, BOX, KOLI), bukan PCS.'
                : '<span class="text-success fw-bold">Gudang Kecil:</span> qty diinput dalam satuan dasar seperti PCS atau SET.';
        };

        const updateQtyUnitInfo = (row) => {
            if (!row || isInboundReturnFlow) return;
            const unitEl = row.querySelector('.flow-unit-select');
            const qtyEl = row.querySelector('input[data-name="qty"]');
            const selected = unitEl?.selectedOptions?.[0];
            const conversion = Number(selected?.dataset?.conversion || 0);
            const unitName = selected?.dataset?.name || 'UNIT';
            const baseName = selected?.dataset?.baseName || 'satuan dasar';
            const isBulkWarehouse = warehouseEl?.selectedOptions?.[0]?.dataset?.type === 'bulk';
            const unitLabel = row.querySelector('.flow-unit-label');
            const qtyLabel = row.querySelector('.flow-qty-label');
            const infoEl = row.querySelector('.flow-qty-info');
            if (unitLabel) unitLabel.textContent = isBulkWarehouse ? 'Satuan Koli' : 'Satuan';
            if (qtyLabel) qtyLabel.textContent = isBulkWarehouse ? `Jumlah Koli (${unitName})` : `Qty (${unitName})`;
            if (infoEl) {
                const qty = Number(qtyEl?.value || 0);
                infoEl.textContent = conversion > 0
                    ? `1 ${unitName} = ${conversion.toLocaleString('id-ID')} ${baseName}; total ${Math.round(qty * conversion).toLocaleString('id-ID')} ${baseName}`
                    : '';
            }
        };

        const syncUnitOptions = (row, selectedUnitId = null) => {
            const itemId = row?.querySelector('.flow-item-select')?.value || '';
            const unitEl = row?.querySelector('.flow-unit-select');
            if (!unitEl) return;
            const isBulkWarehouse = warehouseEl?.selectedOptions?.[0]?.dataset?.type === 'bulk';
            const allUnits = itemUnits[String(itemId)] || [];
            const units = isBulkWarehouse
                ? allUnits.filter(unit => !unit.is_base)
                : allUnits.filter(unit => unit.is_base);
            const baseUnit = allUnits.find(unit => unit.is_base);
            unitEl.innerHTML = units.length ? units.map(unit => {
                const suffix = unit.conversion_qty > 1 ? ` - 1 ${unit.name} = ${unit.conversion_qty} ${baseUnit?.name || 'dasar'}` : '';
                return `<option value="${unit.id}" data-name="${unit.name}" data-conversion="${unit.conversion_qty}" data-base-name="${baseUnit?.name || 'dasar'}">${unit.name}${suffix}</option>`;
            }).join('') : `<option value="">${isBulkWarehouse ? 'Atur satuan koli pada master item' : 'Satuan dasar (1)'}</option>`;
            if (selectedUnitId && units.some(unit => String(unit.id) === String(selectedUnitId))) {
                unitEl.value = String(selectedUnitId);
            }
            updateQtyUnitInfo(row);
        };

        warehouseEl?.addEventListener('change', () => {
            itemsContainer?.querySelectorAll('.flow-item-row').forEach(row => syncUnitOptions(row));
            updateWarehouseUnitInfo();
        });
        updateWarehouseUnitInfo();

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
            const rows = itemsContainer.querySelectorAll('.flow-item-row');
            rows.forEach((row, idx) => {
                row.querySelectorAll('[data-name]')?.forEach((el) => {
                    const key = el.getAttribute('data-name');
                    el.name = `items[${idx}][${key}]`;
                });
            });
        };

        const createItemRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 flow-item-row';
            row.innerHTML = isInboundReturnFlow ? `
                <div class="col-md-3">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid flow-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty Diterima</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty_received" required />
                    <div class="invalid-feedback" data-error-for="qty_received"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty Bagus</label>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="qty_good" required />
                    <div class="invalid-feedback" data-error-for="qty_good"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Qty Rusak</label>
                    <input type="number" min="0" class="form-control form-control-solid" data-name="qty_damaged" required />
                    <div class="invalid-feedback" data-error-for="qty_damaged"></div>
                </div>
                <div class="col-md-1">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-item">Hapus</button>
                </div>
            ` : (isOutboundReturnFlow ? `
                <div class="col-md-4">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid flow-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2">Sumber</label>
                    <select class="form-select form-select-solid" data-name="stock_source">
                        <option value="regular">Stok Normal Gudang Kecil</option>
                        <option value="damaged">Stok Rusak Gudang Kecil</option>
                    </select>
                    <div class="invalid-feedback" data-error-for="stock_source"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2 flow-unit-label">Satuan</label>
                    <select class="form-select form-select-solid flow-unit-select" data-name="unit_id"></select>
                    <div class="invalid-feedback" data-error-for="unit_id"></div>
                </div>
                <div class="col-md-1">
                    <label class="required fs-6 fw-bold form-label mb-2 flow-qty-label">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
                    <div class="form-text fs-8 flow-qty-info"></div>
                    <div class="invalid-feedback" data-error-for="qty"></div>
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-item">Hapus</button>
                </div>
            ` : `
                <div class="col-md-4">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid flow-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2 flow-unit-label">Satuan</label>
                    <select class="form-select form-select-solid flow-unit-select" data-name="unit_id"></select>
                    <div class="invalid-feedback" data-error-for="unit_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2 flow-qty-label">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required />
                    <div class="form-text fs-8 flow-qty-info"></div>
                    <div class="invalid-feedback" data-error-for="qty"></div>
                </div>
                <div class="col-md-3">
                    <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note" />
                </div>
                <div class="col-md-1 text-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-item">Hapus</button>
                </div>
            `);
            itemsContainer.appendChild(row);

            const selectEl = row.querySelector('.flow-item-select');
            if (data.item_id) {
                selectEl.value = String(data.item_id);
            }
            syncUnitOptions(row, data.unit_id);
            const qtyEl = row.querySelector('input[data-name="qty"]');
            if (qtyEl) qtyEl.value = data.qty ?? '';
            const qtyReceivedEl = row.querySelector('input[data-name="qty_received"]');
            if (qtyReceivedEl) qtyReceivedEl.value = data.qty_received ?? data.qty ?? '';
            const qtyGoodEl = row.querySelector('input[data-name="qty_good"]');
            if (qtyGoodEl) qtyGoodEl.value = data.qty_good ?? 0;
            const qtyDamagedEl = row.querySelector('input[data-name="qty_damaged"]');
            if (qtyDamagedEl) qtyDamagedEl.value = data.qty_damaged ?? data.qty ?? 0;
            const noteEl = row.querySelector('input[data-name="note"]');
            if (noteEl) noteEl.value = data.note ?? '';
            const stockSourceEl = row.querySelector('select[data-name="stock_source"]');
            if (stockSourceEl) stockSourceEl.value = data.stock_source ?? 'regular';

            initSelect2(selectEl);
            renumberRows();
            validateUniqueItems();
        };

        const resetForm = () => {
            form?.reset();
            form.dataset.editId = '';
            form.dataset.flowType = defaultTypeFilter || '';
            if (modalTitle) modalTitle.textContent = 'Tambah';
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

        addItemBtn?.addEventListener('click', () => createItemRow());
        if (!canCreateDefault && openCreateBtn) {
            openCreateBtn.remove();
        } else {
            openCreateBtn?.addEventListener('click', resetForm);
        }

        itemsContainer?.addEventListener('change', (e) => {
            if (e.target.matches('.flow-item-select')) {
                syncUnitOptions(e.target.closest('.flow-item-row'));
            }
            if (e.target.matches('.flow-unit-select')) {
                updateQtyUnitInfo(e.target.closest('.flow-item-row'));
            }
            if (e.target.matches('.flow-item-select') || e.target.matches('select[data-name="stock_source"]')) {
                validateUniqueItems();
            }
        });

        itemsContainer?.addEventListener('input', (e) => {
            if (e.target.matches('input[data-name="qty"]')) {
                updateQtyUnitInfo(e.target.closest('.flow-item-row'));
            }
            if (e.target.matches('input[data-name="qty_received"], input[data-name="qty_good"], input[data-name="qty_damaged"]')) {
                syncReturnQtyRow(e.target.closest('.flow-item-row'), e.target.getAttribute('data-name'));
            }
        });

        itemsContainer?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-remove-item');
            if (!btn) return;
            const row = btn.closest('.flow-item-row');
            if (row) row.remove();
            if (itemsContainer.querySelectorAll('.flow-item-row').length === 0) {
                createItemRow();
            } else {
                renumberRows();
            }
            validateUniqueItems();
        });

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };

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
                { data: 'type', render: (data) => typeLabelMap?.[data] || data || '-' },
                { data: 'status', orderable:false, searchable:false, render: (data, type, row) => statusLabel(data, row) },
                { data: 'transacted_at' },
                { data: 'submit_by' },
                { data: 'warehouse' },
                { data: 'item' },
                { data: 'qty', render: (data, type, row) => {
                    const qty = Number(data || 0).toLocaleString('id-ID');
                    const details = Array.isArray(row?.qty_details) ? row.qty_details : [];
                    if (row?.type === 'receipt' && details.length) {
                        return details.map(detail => {
                            const input = Number(detail.qty_input || 0).toLocaleString('id-ID');
                            const base = Number(detail.qty_base || 0).toLocaleString('id-ID');
                            const unit = String(detail.unit || 'PCS/SET').replace(/[&<>"']/g, char => ({
                                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
                            }[char]));
                            return Number(detail.conversion_qty || 1) > 1
                                ? `<div class="fw-bold text-primary">${input} ${unit}</div><div class="text-muted fs-8">= ${base} PCS/SET</div>`
                                : `<div class="fw-semibold">${input} ${unit}</div>`;
                        }).join('<div class="separator separator-dashed my-1"></div>');
                    }
                    const returnQty = Number(row?.return_warehouse_qty || 0);
                    if (returnQty > 0) {
                        return `${qty}<div class="text-primary fs-8 fw-bold">Area retur Gudang Kecil: ${returnQty.toLocaleString('id-ID')} PCS/SET</div>`;
                    }
                    return qty;
                } },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row)=>{
                    const rowType = row?.type || defaultTypeFilter;
                    const perms = permMap?.[rowType] || {};
                    const isApproved = row?.status === 'approved';
                    const isFinalized = row?.status === 'finalized';
                    const canFinalizeReturn = isInboundReturnFlow && rowType === 'return' && isApproved && perms.update;
                    const canApprove = !(isInboundReturnFlow && rowType === 'return') && !isApproved && !isFinalized && perms.update;
                    const detailItem = `<div class="menu-item px-3"><a href="${resolveRoute(rowType, 'detail').replace(':id', data)}" class="menu-link px-3">Detail</a></div>`;
                    const scanUrl = resolveRoute(rowType, 'scan');
                    const scanItem = rowType === 'manual' && perms.update && !isApproved && !isFinalized && scanUrl
                        ? `<div class="menu-item px-3"><a href="${scanUrl.replace(':id', data)}" class="menu-link px-3 text-primary">Scan</a></div>`
                        : '';
                    const approveItem = canApprove
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${data}" data-type="${rowType}">Approve</a></div>`
                        : '';
                    const finalizeItem = canFinalizeReturn
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-primary btn-finalize" data-id="${data}" data-type="${rowType}">Finalisasi</a></div>`
                        : '';
                    const canEdit = perms.update && !isFinalized && (
                        !isApproved || (isInboundReturnFlow && rowType === 'return')
                    );
                    const editItem = canEdit
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}" data-type="${rowType}">Edit</a></div>`
                        : '';
                    const delItem = (!isApproved && !isFinalized && perms.delete)
                        ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}" data-type="${rowType}">Hapus</a></div>`
                        : '';
                    const actions = `${detailItem}${scanItem}${approveItem}${finalizeItem}${editItem}${delItem}`;
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
                }}
            ]
        });
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
            const importWarehouse = document.getElementById('import_flow_warehouse_id');
            if (importWarehouse) formData.append('warehouse_id', importWarehouse.value);
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
            const rowType = this.getAttribute('data-type') || defaultTypeFilter;
            const res = await fetch(resolveRoute(rowType, 'show').replace(':id', id), { headers: { 'Accept': 'application/json' }});
            const json = await res.json();
            if (!res.ok) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error');
                return;
            }
            form.dataset.editId = id;
            form.dataset.flowType = rowType;
            if (modalTitle) modalTitle.textContent = `Edit ${json.code || ''}`.trim();
                if (warehouseEl) {
                    warehouseEl.value = locksToSmallWarehouse
                        ? smallWarehouseId
                        : (json.warehouse_id || warehouseEl.value);
                }
                document.getElementById('flow_ref_no').value = json.ref_no || '';
                document.getElementById('flow_note').value = json.note || '';
                if (fpTransacted) {
                    fpTransacted.setDate(json.transacted_at || null, true, 'Y-m-d\\TH:i');
                } else {
                    document.getElementById('flow_transacted_at').value = json.transacted_at || '';
                }

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
            const rowType = this.getAttribute('data-type') || defaultTypeFilter;
            if (!id) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Data akan dihapus dan stok akan dikembalikan',
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
                const res = await fetch(resolveRoute(rowType, 'delete').replace(':id', id), {
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
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menghapus', 'error');
            }
        });

        tableEl.on('click', '.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const rowType = this.getAttribute('data-type') || defaultTypeFilter;
            if (!id) return;
            const approveUrl = resolveRoute(rowType, 'approve')?.replace(':id', id);
            if (!approveUrl) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const isReturnApproval = isInboundReturnFlow && rowType === 'return';
                const res = await Swal.fire({
                    title: isReturnApproval ? 'Masukkan ke Gudang Retur?' : 'Setujui data ini?',
                    text: isReturnApproval
                        ? 'Barang akan tercatat sebagai stok Gudang Retur dan belum masuk stok reguler/barang rusak sampai finalisasi.'
                        : 'Setelah disetujui, data tidak bisa diubah atau dihapus.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: isReturnApproval ? 'Masuk Gudang Retur' : 'Approve',
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
                const res = await fetch(approveUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) {
                    const msg = json?.errors?.scan?.[0] || json.message || 'Gagal menyetujui';
                    if (typeof Swal !== 'undefined') Swal.fire('Error', msg, 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyetujui', 'error');
            }
        });

        tableEl.on('click', '.btn-finalize', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const rowType = this.getAttribute('data-type') || defaultTypeFilter;
            if (!id) return;
            const finalizeUrl = resolveRoute(rowType, 'finalize')?.replace(':id', id);
            if (!finalizeUrl) return;
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Finalisasi retur ini?',
                    text: 'Qty bagus akan masuk stok reguler dan qty rusak akan masuk stok barang rusak.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Finalisasi',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-primary',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;
            try {
                const res = await fetch(finalizeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal finalisasi', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal finalisasi', 'error');
            }
        });

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            if (!validateUniqueItems()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item tidak boleh duplikat', 'error');
                return;
            }

            const isEdit = !!form.dataset.editId;
            const flowType = form.dataset.flowType || defaultTypeFilter || '';
            const url = isEdit
                ? resolveRoute(flowType, 'update').replace(':id', form.dataset.editId)
                : resolveRoute(flowType, 'store');
            const formData = new FormData(form);
            if (isEdit) formData.append('_method', 'PUT');

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
                                if (!Number.isInteger(idx) || field === undefined) {
                                    unhandled.push(msgs.join(', '));
                                    return;
                                }
                                const row = itemsContainer.querySelectorAll('.flow-item-row')[idx];
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
