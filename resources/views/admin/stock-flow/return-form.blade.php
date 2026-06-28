@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@section('page_breadcrumbs')
    <span class="text-muted">Home</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Outbound</span>
    <span class="mx-2">-</span>
    <a href="{{ $indexUrl }}" class="text-muted text-hover-primary">Retur Customer</a>
    <span class="mx-2">-</span>
    <span class="text-dark">{{ $transaction ? 'Edit' : 'Tambah' }}</span>
@endsection

@php
    $initialItems = $transaction
        ? $transaction->items->map(fn ($row) => [
            'item_id' => $row->item_id,
            'qty' => $row->qty_input ?: $row->qty,
            'stock_source' => $row->stock_source ?? 'regular',
            'note' => $row->note ?? '',
        ])->values()
        : collect();
    $transactionPayload = $transaction ? [
        'id' => $transaction->id,
        'ref_no' => $transaction->ref_no,
        'note' => $transaction->note,
        'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
    ] : null;
@endphp

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid" id="kt_content_container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-6">
            <div>
                <div class="text-muted fs-7">Retur customer diproses dari Gudang Kecil. Pilih sumber stok normal atau stok rusak per SKU.</div>
            </div>
            <a href="{{ $indexUrl }}" class="btn btn-light">Kembali</a>
        </div>

        @if($transaction && ($transaction->status ?? 'pending') === 'approved')
            <div class="alert alert-warning d-flex align-items-center p-5 mb-6">
                <i class="fa-solid fa-circle-info fs-2 text-warning me-4"></i>
                <div>
                    <div class="fw-bold">Data sudah disetujui</div>
                    <div class="text-muted">Data yang sudah approved tidak bisa diubah. Halaman ini hanya untuk melihat susunan item.</div>
                </div>
            </div>
        @endif

        <form id="return_customer_form" class="form">
            @csrf
            <input type="hidden" name="warehouse_id" value="{{ $smallWarehouse->id }}">

            <div class="card mb-6">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <h2 class="fw-bolder mb-0">Informasi Retur</h2>
                    </div>
                </div>
                <div class="card-body py-6">
                    <div class="row g-5">
                        <div class="col-lg-4">
                            <label class="required form-label">Tanggal</label>
                            <input type="text" class="form-control form-control-solid" name="transacted_at" id="return_transacted_at" placeholder="YYYY-MM-DD HH:mm" required>
                            <div class="invalid-feedback" data-error="transacted_at"></div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Ref No</label>
                            <input type="text" class="form-control form-control-solid" name="ref_no" id="return_ref_no" placeholder="Nomor retur / catatan customer">
                            <div class="invalid-feedback" data-error="ref_no"></div>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label">Gudang</label>
                            <input type="text" class="form-control form-control-solid" value="{{ $smallWarehouse->name }}" readonly>
                            <div class="form-text">Retur customer otomatis memakai stok Gudang Kecil.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Catatan</label>
                            <textarea class="form-control form-control-solid" name="note" id="return_note" rows="3" placeholder="Catatan transaksi retur"></textarea>
                            <div class="invalid-feedback" data-error="note"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div>
                            <h2 class="fw-bolder mb-1">Item Retur</h2>
                            <div class="text-muted fs-7">SKU yang sama boleh dipakai dua kali jika sumber stoknya berbeda.</div>
                        </div>
                    </div>
                    <div class="card-toolbar">
                        <button type="button" class="btn btn-light-primary" id="btn_add_return_item">Tambah Item</button>
                    </div>
                </div>
                <div class="card-body py-6">
                    <div id="return_items_container"></div>
                    <div class="invalid-feedback d-block" data-error="items"></div>
                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-3 pt-6">
                        <a href="{{ $indexUrl }}" class="btn btn-light">Batal</a>
                        <button type="submit" class="btn btn-primary" @disabled($transaction && ($transaction->status ?? 'pending') === 'approved')>
                            {{ $transaction ? 'Simpan Perubahan' : 'Simpan Retur' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const itemOptionsHtml = `@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->sku }} - {{ $item->name }}</option>@endforeach`;
    const itemUnits = @json($itemUnitsJson);
    const initialItems = @json($initialItems);
    const transaction = @json($transactionPayload);
    const submitUrl = '{{ $transaction ? $updateUrl : $storeUrl }}';
    const indexUrl = '{{ $indexUrl }}';
    const csrfToken = '{{ csrf_token() }}';
    const isEdit = {{ $transaction ? 'true' : 'false' }};

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('return_customer_form');
        const container = document.getElementById('return_items_container');
        const addBtn = document.getElementById('btn_add_return_item');
        const transactedAtEl = document.getElementById('return_transacted_at');
        const refNoEl = document.getElementById('return_ref_no');
        const noteEl = document.getElementById('return_note');
        let fpTransacted = null;

        const pad = (n) => String(n).padStart(2, '0');
        const formatDateTime = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        const nowJakarta = () => formatDateTime(new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' })));

        if (typeof flatpickr !== 'undefined') {
            fpTransacted = flatpickr(transactedAtEl, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
        }

        if (transaction) {
            if (refNoEl) refNoEl.value = transaction.ref_no || '';
            if (noteEl) noteEl.value = transaction.note || '';
            if (fpTransacted) fpTransacted.setDate(transaction.transacted_at || null, true, 'Y-m-d H:i');
            else if (transactedAtEl) transactedAtEl.value = transaction.transacted_at || '';
        } else if (fpTransacted) {
            fpTransacted.setDate(nowJakarta(), true, 'Y-m-d H:i');
        } else if (transactedAtEl) {
            transactedAtEl.value = nowJakarta();
        }

        const initSelect2 = (selectEl) => {
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Pilih item',
                    allowClear: true,
                    width: '100%',
                    minimumResultsForSearch: 0,
                });
            }
        };

        const clearErrors = () => {
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('[data-error]').forEach(el => { el.textContent = ''; });
            container.querySelectorAll('[data-error-for]').forEach(el => { el.textContent = ''; });
        };

        const renumberRows = () => {
            container.querySelectorAll('.return-item-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach(el => {
                    el.name = `items[${idx}][${el.getAttribute('data-name')}]`;
                });
            });
        };

        const validateUniqueRows = () => {
            const rows = Array.from(container.querySelectorAll('.return-item-row'));
            const counts = {};
            rows.forEach(row => {
                const itemId = row.querySelector('[data-name="item_id"]')?.value || '';
                const source = row.querySelector('[data-name="stock_source"]')?.value || 'regular';
                const key = itemId ? `${itemId}|${source}` : '';
                if (key) counts[key] = (counts[key] || 0) + 1;
            });

            let valid = true;
            rows.forEach(row => {
                const itemEl = row.querySelector('[data-name="item_id"]');
                const source = row.querySelector('[data-name="stock_source"]')?.value || 'regular';
                const key = itemEl?.value ? `${itemEl.value}|${source}` : '';
                const errEl = row.querySelector('[data-error-for="item_id"]');
                if (key && counts[key] > 1) {
                    valid = false;
                    itemEl?.classList.add('is-invalid');
                    if (errEl) errEl.textContent = 'Item dengan sumber stok yang sama tidak boleh duplikat';
                } else {
                    itemEl?.classList.remove('is-invalid');
                    if (errEl) errEl.textContent = '';
                }
            });
            return valid;
        };

        const createRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'row g-4 align-items-end border-bottom border-dashed pb-5 mb-5 return-item-row';
            row.innerHTML = `
                <div class="col-lg-5 col-md-6">
                    <label class="required form-label">Item</label>
                    <select class="form-select form-select-solid return-item-select" data-name="item_id" required>
                        <option value=""></option>
                        ${itemOptionsHtml}
                    </select>
                    <div class="invalid-feedback" data-error-for="item_id"></div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="required form-label">Sumber Stok</label>
                    <select class="form-select form-select-solid" data-name="stock_source">
                        <option value="regular">Stok Normal</option>
                        <option value="damaged">Stok Rusak</option>
                    </select>
                    <div class="invalid-feedback" data-error-for="stock_source"></div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="required form-label">Qty</label>
                    <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required>
                    <div class="form-text fs-8 return-unit-info">PCS/SET</div>
                    <div class="invalid-feedback" data-error-for="qty"></div>
                </div>
                <div class="col-lg-2 col-md-6">
                    <label class="form-label">Catatan Item</label>
                    <input type="text" class="form-control form-control-solid" data-name="note">
                </div>
                <div class="col-lg-1 col-md-2 text-md-end">
                    <button type="button" class="btn btn-light btn-sm btn-remove-return-item">Hapus</button>
                </div>
            `;
            container.appendChild(row);

            const itemEl = row.querySelector('[data-name="item_id"]');
            const sourceEl = row.querySelector('[data-name="stock_source"]');
            const qtyEl = row.querySelector('[data-name="qty"]');
            const noteItemEl = row.querySelector('[data-name="note"]');
            const unitInfo = row.querySelector('.return-unit-info');

            if (data.item_id) itemEl.value = String(data.item_id);
            sourceEl.value = data.stock_source || 'regular';
            qtyEl.value = data.qty || '';
            noteItemEl.value = data.note || '';

            const updateUnitInfo = () => {
                const units = itemUnits[String(itemEl.value)] || [];
                const baseUnit = units.find(unit => unit.is_base);
                unitInfo.textContent = baseUnit ? `Satuan dasar: ${baseUnit.name}` : 'PCS/SET';
            };

            initSelect2(itemEl);
            $(itemEl).on('change', () => {
                updateUnitInfo();
                validateUniqueRows();
            });
            sourceEl.addEventListener('change', validateUniqueRows);
            updateUnitInfo();
            renumberRows();
            validateUniqueRows();
        };

        addBtn?.addEventListener('click', () => createRow());

        container.addEventListener('click', (event) => {
            const btn = event.target.closest('.btn-remove-return-item');
            if (!btn) return;
            btn.closest('.return-item-row')?.remove();
            if (!container.querySelector('.return-item-row')) createRow();
            renumberRows();
            validateUniqueRows();
        });

        (Array.isArray(initialItems) && initialItems.length ? initialItems : [{}]).forEach(row => createRow(row));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            if (!validateUniqueRows()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Item dengan sumber stok yang sama tidak boleh duplikat', 'error');
                return;
            }

            const formData = new FormData(form);
            if (isEdit) formData.append('_method', 'PUT');

            try {
                const res = await fetch(submitUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const json = await res.json().catch(() => ({}));

                if (!res.ok) {
                    if (json?.errors) {
                        const messages = [];
                        Object.entries(json.errors).forEach(([key, value]) => {
                            const msg = Array.isArray(value) ? value.join(', ') : String(value);
                            if (key.startsWith('items.')) {
                                const parts = key.split('.');
                                const idx = Number(parts[1]);
                                const field = parts[2];
                                const row = container.querySelectorAll('.return-item-row')[idx];
                                const errEl = row?.querySelector(`[data-error-for="${field}"]`);
                                const fieldEl = row?.querySelector(`[data-name="${field}"]`);
                                if (errEl) errEl.textContent = msg;
                                if (fieldEl) fieldEl.classList.add('is-invalid');
                                if (!errEl) messages.push(msg);
                            } else {
                                const errEl = form.querySelector(`[data-error="${key}"]`);
                                const fieldEl = form.querySelector(`[name="${key}"]`);
                                if (errEl) errEl.textContent = msg;
                                if (fieldEl) fieldEl.classList.add('is-invalid');
                                if (!errEl) messages.push(msg);
                            }
                        });
                        if (messages.length && typeof Swal !== 'undefined') Swal.fire('Error', messages.join(', '), 'error');
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json?.message || 'Gagal menyimpan retur customer', 'error');
                    }
                    return;
                }

                if (typeof Swal !== 'undefined') {
                    await Swal.fire('Berhasil', json?.message || 'Retur customer berhasil disimpan', 'success');
                }
                window.location.href = indexUrl;
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan retur customer', 'error');
            }
        });
    });
</script>
@endpush
