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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari kode, SKU, atau referensi" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            @if($canCreate)
                <button type="button" class="btn btn-primary" id="btn_open_allocation">Tambah</button>
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
    <div class="modal-dialog modal-dialog-centered mw-1000px">
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
                            <input type="hidden" name="warehouse_id" id="allocation_warehouse_id" value="{{ $smallWarehouse->id }}">
                            <label class="fs-6 fw-bold form-label mb-2">Sumber Barang Rusak</label>
                            <div class="rounded border border-success border-dashed bg-light-success p-4">
                                <div class="fw-bolder text-success">{{ $smallWarehouse->name }}</div>
                                <div class="text-muted fs-8">Alokasi selalu mengambil stok rusak dalam PCS/SET dari Gudang Kecil.</div>
                            </div>
                            <div id="allocation_warehouse_unit_info" class="d-none"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="required fs-6 fw-bold form-label mb-2">Jenis Alokasi</label>
                            <select class="form-select form-select-solid" name="allocation_type" id="allocation_type" required>
                                <option value="">Pilih alokasi</option>
                                <option value="dispose">Dimusnahkan</option>
                                <option value="repair">Diperbaiki</option>
                                <option value="return_vendor">Retur ke Gudang Besar / Vendor</option>
                                <option value="other">Lainnya</option>
                            </select>
                            <div class="invalid-feedback" id="error_allocation_type"></div>
                        </div>
                        <div class="col-md-12">
                            <div class="notice d-flex bg-light-info rounded border-info border border-dashed p-4" id="allocation_type_info">
                                <i class="fa-solid fa-circle-info fs-2 text-info me-4"></i>
                                <div class="text-gray-700">Pilih jenis alokasi untuk melihat dampaknya terhadap stok.</div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="fs-6 fw-bold form-label mb-2" id="allocation_ref_label">Referensi</label>
                            <input type="text" class="form-control form-control-solid" name="ref_no" id="allocation_ref_no" placeholder="Nomor dokumen atau referensi terkait" />
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
    const stocksUrl = '{{ $stocksUrl }}';
    const storeUrl = '{{ $storeUrl }}';
    const showUrlTpl = '{{ route('admin.inventory.damaged-allocations.show', ':id') }}';
    const updateUrlTpl = '{{ route('admin.inventory.damaged-allocations.update', ':id') }}';
    const deleteUrlTpl = '{{ route('admin.inventory.damaged-allocations.destroy', ':id') }}';
    const approveUrlTpl = '{{ route('admin.inventory.damaged-allocations.approve', ':id') }}';
    const csrfToken = '{{ csrf_token() }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const damagedItems = @json($items);
    const initialDamagedStocks = @json($damagedStocks);

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
        const warehouseUnitInfo = document.getElementById('allocation_warehouse_unit_info');
        const allocationTypeEl = document.getElementById('allocation_type');
        const allocationTypeInfo = document.getElementById('allocation_type_info');
        const allocationRefLabel = document.getElementById('allocation_ref_label');
        const allocationRefEl = document.getElementById('allocation_ref_no');
        let damagedStocks = initialDamagedStocks?.[String(warehouseEl?.value || '')] || {};
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
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            itemsContainer.querySelectorAll('[data-error-for]').forEach(el => el.textContent = '');
        };
        const initSelect2 = el => {
            if (el && typeof $ !== 'undefined' && $.fn.select2) {
                $(el).select2({
                    placeholder: 'Pilih item',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(modalEl),
                });
            }
        };
        const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
        const availableFor = itemId => Number(damagedStocks?.[String(itemId)]?.available ?? damagedStocks?.[String(itemId)] ?? 0);
        const itemOptionsHtml = () => damagedItems.map(item => {
            const stock = availableFor(item.id);
            return stock > 0
                ? `<option value="${item.id}" data-stock="${stock}">${escapeHtml(item.sku)} - ${escapeHtml(item.name)} (tersedia: ${stock})</option>`
                : '';
        }).join('');
        const loadStocks = async (allocationId = null, refreshRows = true) => {
            const url = new URL(stocksUrl, window.location.origin);
            if (allocationId) url.searchParams.set('allocation_id', allocationId);
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            const json = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(json.message || 'Gagal memuat saldo stok rusak');
            damagedStocks = json.stocks || {};
            if (refreshRows) refreshItemOptions();
        };
        const refreshItemOptions = () => {
            itemsContainer.querySelectorAll('.allocation-item-select').forEach(select => {
                const selected = select.value;
                if (typeof $ !== 'undefined' && $.fn.select2 && $(select).hasClass('select2-hidden-accessible')) {
                    $(select).select2('destroy');
                }
                select.innerHTML = `<option value=""></option>${itemOptionsHtml()}`;
                const options = select.options || [];
                for (let index = 0; index < options.length; index += 1) {
                    if (options[index].value === selected) {
                        select.value = selected;
                        break;
                    }
                }
                initSelect2(select);
                syncRowUnit(select.closest('.allocation-item-row'));
            });
        };
        const renumberRows = () => {
            itemsContainer.querySelectorAll('.allocation-item-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach(el => {
                    el.name = `items[${idx}][${el.getAttribute('data-name')}]`;
                });
            });
        };
        const clearItemRows = () => {
            if (typeof $ !== 'undefined' && $.fn.select2) {
                itemsContainer.querySelectorAll('.allocation-item-select').forEach(select => {
                    if ($(select).hasClass('select2-hidden-accessible')) {
                        $(select).select2('destroy');
                    }
                });
            }
            itemsContainer.innerHTML = '';
        };
        const updateWarehouseInfo = () => {
            if (!warehouseUnitInfo) return;
            warehouseUnitInfo.innerHTML = '<span class="text-success fw-bold">Gudang Kecil:</span> alokasi barang rusak menggunakan PCS/SET.';
        };
        const updateAllocationTypeInfo = () => {
            const info = {
                dispose: ['danger', 'fa-trash-can', 'Stok rusak akan berkurang permanen setelah disetujui.'],
                repair: ['success', 'fa-screwdriver-wrench', 'Setelah disetujui, stok rusak berkurang dan jumlah yang sama kembali ke stok display Gudang Kecil.'],
                return_vendor: ['warning', 'fa-truck-arrow-right', 'Setelah disetujui, sistem membuat transaksi retur outbound dari stok rusak.'],
                other: ['info', 'fa-ellipsis', 'Stok rusak akan berkurang. Catatan wajib diisi untuk menjelaskan tujuan alokasi.'],
            };
            const [color, icon, text] = info[allocationTypeEl.value] || ['info', 'fa-circle-info', 'Pilih jenis alokasi untuk melihat dampaknya terhadap stok.'];
            allocationTypeInfo.className = `notice d-flex bg-light-${color} rounded border-${color} border border-dashed p-4`;
            allocationTypeInfo.innerHTML = `<i class="fa-solid ${icon} fs-2 text-${color} me-4"></i><div class="text-gray-700">${text}</div>`;
            allocationRefLabel.textContent = allocationTypeEl.value === 'return_vendor' ? 'Referensi Retur' : 'Referensi';
            allocationRefEl.placeholder = allocationTypeEl.value === 'return_vendor'
                ? 'Contoh: nomor surat jalan / referensi vendor'
                : 'Nomor dokumen atau referensi terkait';
        };
        const syncRowUnit = (row, selectedUnitId = null) => {
            if (!row) return;
            const itemSelect = row.querySelector('.allocation-item-select');
            const unitEl = row.querySelector('[data-name="unit_id"]');
            const qtyEl = row.querySelector('[data-name="qty_input"]');
            const unitLabel = row.querySelector('.allocation-unit-label');
            const qtyLabel = row.querySelector('.allocation-qty-label');
            const qtyInfo = row.querySelector('.allocation-qty-info');
            if (!itemSelect || !unitEl || !qtyEl || !unitLabel || !qtyLabel || !qtyInfo) return;

            const item = damagedItems.find(value => String(value.id) === String(itemSelect.value));
            const units = (item?.units || []).filter(unit => unit.is_base);
            const baseUnit = (item?.units || []).find(unit => unit.is_base);
            unitEl.innerHTML = units.length ? units.map(unit => `<option value="${unit.id}">${unit.name}</option>`).join('') : '<option value="">Satuan belum dikonfigurasi</option>';
            if (selectedUnitId && units.some(unit => String(unit.id) === String(selectedUnitId))) unitEl.value = String(selectedUnitId);
            const selected = units.find(unit => String(unit.id) === String(unitEl.value));
            const qty = Number(qtyEl.value || 0);
            const available = availableFor(item?.id);
            qtyEl.max = String(available);
            unitLabel.textContent = 'Satuan';
            qtyLabel.textContent = `Qty (${selected?.name || baseUnit?.name || 'PCS'})`;
            qtyInfo.innerHTML = selected
                ? `Tersedia <strong>${available}</strong> ${escapeHtml(baseUnit?.name || 'satuan')}; dialokasikan ${qty * selected.conversion_qty}`
                : 'Satuan dasar item belum dikonfigurasi';
        };
        const createRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-3 align-items-end mb-4 allocation-item-row';
            row.innerHTML = `
                <div class="col-md-4">
                    <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                    <select class="form-select form-select-solid allocation-item-select" data-name="item_id" required>
                        <option value=""></option>${itemOptionsHtml()}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2 allocation-unit-label">Satuan</label>
                    <select class="form-select form-select-solid" data-name="unit_id" required></select>
                </div>
                <div class="col-md-2">
                    <label class="required fs-6 fw-bold form-label mb-2 allocation-qty-label">Qty</label>
                    <input type="number" min="1" step="1" class="form-control form-control-solid" data-name="qty_input" required />
                    <div class="form-text fs-8 allocation-qty-info"></div>
                    <div class="invalid-feedback" data-error-for="qty_input"></div>
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
            const qtyEl = row.querySelector('[data-name="qty_input"]');
            const noteEl = row.querySelector('[data-name="note"]');
            if (data.item_id) selectEl.value = String(data.item_id);
            if (qtyEl) qtyEl.value = data.qty_input ?? data.qty ?? '';
            if (noteEl) noteEl.value = data.note ?? '';
            initSelect2(selectEl);
            syncRowUnit(row, data.unit_id);
            renumberRows();
        };
        const resetForm = () => {
            clearItemRows();
            form.reset();
            form.dataset.editId = '';
            if (titleEl) titleEl.textContent = 'Tambah Alokasi Barang Rusak';
            createRow();
            if (fpDate) fpDate.setDate(nowString(), true, 'Y-m-d H:i');
            else if (dateEl) dateEl.value = nowString();
            clearErrors();
            updateAllocationTypeInfo();
        };

        document.getElementById('btn_open_allocation')?.addEventListener('click', async () => {
            const button = document.getElementById('btn_open_allocation');
            button.disabled = true;
            try {
                await loadStocks(null, false);
                resetForm();
                modal?.show();
            } catch (error) {
                window.AppSwal?.error(error.message);
            } finally {
                button.disabled = false;
            }
        });
        document.getElementById('btn_add_allocation_item')?.addEventListener('click', () => createRow());
        allocationTypeEl?.addEventListener('change', updateAllocationTypeInfo);
        itemsContainer.addEventListener('change', event => {
            if (event.target.matches('.allocation-item-select')) syncRowUnit(event.target.closest('.allocation-item-row'));
            if (event.target.matches('[data-name="unit_id"]')) syncRowUnit(event.target.closest('.allocation-item-row'), event.target.value);
        });
        itemsContainer.addEventListener('input', event => {
            if (event.target.matches('[data-name="qty_input"]')) {
                const row = event.target.closest('.allocation-item-row');
                syncRowUnit(row, row.querySelector('[data-name="unit_id"]').value);
            }
        });
        updateWarehouseInfo();
        updateAllocationTypeInfo();
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
                    const approve = !approved && canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-success btn-approve" data-id="${id}" data-type="${row.allocation_type_code || ''}">Setujui</a></div>` : '';
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
            if (warehouseEl) warehouseEl.value = '{{ $smallWarehouse->id }}';
            document.getElementById('allocation_ref_no').value = json.ref_no || '';
            document.getElementById('allocation_note').value = json.note || '';
            if (fpDate) fpDate.setDate(json.transacted_at || null, true, 'Y-m-d H:i');
            else dateEl.value = json.transacted_at || '';
            clearItemRows();
            try {
                await loadStocks(id, false);
            } catch (error) {
                return window.AppSwal?.error(error.message);
            }
            (json.items || []).forEach(item => createRow(item));
            if (!(json.items || []).length) createRow();
            clearErrors();
            updateAllocationTypeInfo();
            modal?.show();
        });

        tableEl.on('click', '.btn-delete,.btn-approve', async function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const isApprove = this.classList.contains('btn-approve');
            const allocationType = this.dataset.type || '';
            const approveEffects = {
                repair: 'Stok rusak akan berkurang dan stok display akan bertambah.',
                dispose: 'Stok rusak akan berkurang permanen.',
                return_vendor: 'Stok rusak akan berkurang dan retur outbound akan dibuat.',
                other: 'Stok rusak akan berkurang sesuai alokasi.',
            };
            const confirm = await Swal.fire({
                title: isApprove ? 'Setujui alokasi?' : 'Hapus alokasi?',
                text: isApprove ? (approveEffects[allocationType] || 'Stok rusak akan diproses sesuai jenis alokasi.') : 'Reservasi stok pada dokumen ini akan dilepas.',
                icon: isApprove ? 'question' : 'warning',
                showCancelButton: true,
                confirmButtonText: isApprove ? 'Ya, Setujui' : 'Hapus',
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
            loadStocks(null, false).catch(() => {});
        });

        form?.addEventListener('submit', async e => {
            e.preventDefault();
            clearErrors();
            const rows = Array.from(itemsContainer.querySelectorAll('.allocation-item-row'));
            const selectedIds = rows.map(row => row.querySelector('.allocation-item-select')?.value).filter(Boolean);
            if (new Set(selectedIds).size !== selectedIds.length) {
                return window.AppSwal?.error('Item tidak boleh dipilih lebih dari satu kali.');
            }
            for (const row of rows) {
                const itemId = row.querySelector('.allocation-item-select')?.value;
                const qtyEl = row.querySelector('[data-name="qty_input"]');
                const available = availableFor(itemId);
                if (!itemId || Number(qtyEl?.value || 0) <= 0) {
                    return window.AppSwal?.error('Item dan qty wajib diisi.');
                }
                if (Number(qtyEl.value) > available) {
                    qtyEl.classList.add('is-invalid');
                    return window.AppSwal?.error(`Qty melebihi stok rusak tersedia (${available}).`);
                }
            }
            if (allocationTypeEl.value === 'other' && !document.getElementById('allocation_note').value.trim()
                && !rows.some(row => row.querySelector('[data-name="note"]')?.value.trim())) {
                document.getElementById('allocation_note').classList.add('is-invalid');
                return window.AppSwal?.error('Catatan wajib diisi untuk jenis alokasi Lainnya.');
            }
            const id = form.dataset.editId;
            const url = id ? updateUrlTpl.replace(':id', id) : storeUrl;
            const fd = new FormData(form);
            if (id) fd.append('_method', 'PUT');
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: fd });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) {
                const messages = Object.values(json.errors || {}).flat();
                window.AppSwal?.error(messages.join('\n') || json.message || 'Gagal menyimpan data');
                return;
            }
            modal?.hide();
            Swal?.fire('Berhasil', json.message || 'Berhasil', 'success');
            dt.ajax.reload();
            loadStocks(null, false).catch(() => {});
        });
    });
</script>
@endpush
