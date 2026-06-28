@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@section('page_breadcrumbs')
    <span class="text-muted">Home</span>
    <span class="mx-2">-</span>
    <span class="text-muted">Inbound</span>
    <span class="mx-2">-</span>
    <a href="{{ $indexUrl }}" class="text-muted text-hover-primary">Retur Inbound</a>
    <span class="mx-2">-</span>
    <span class="text-dark">{{ $transaction ? 'Edit' : 'Tambah' }}</span>
@endsection

@php
    $initialItems = $transaction
        ? $transaction->items->map(fn ($row) => [
            'item_id' => $row->item_id,
            'qty' => $row->qty_input ?: $row->qty,
            'qty_received' => $row->qty_received ?: $row->qty,
            'qty_good' => $row->qty_good ?? 0,
            'qty_damaged' => $row->qty_damaged ?? 0,
            'qty_missing' => $row->qty_missing ?? 0,
            'note' => $row->note ?? '',
        ])->values()
        : collect();
    $isFinalized = $transaction && ($transaction->status ?? 'pending') === 'finalized';
    $transactionPayload = $transaction ? [
        'id' => $transaction->id,
        'ref_no' => $transaction->ref_no,
        'note' => $transaction->note,
        'status' => $transaction->status ?? 'pending',
        'transacted_at' => $transaction->transacted_at?->format('Y-m-d H:i'),
    ] : null;
@endphp

@section('content')
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-fluid" id="kt_content_container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-6">
            <div>
                <div class="text-muted fs-7">Scan atau isi nomor resi. Jika data import ditemukan, SKU dan qty otomatis terisi; jika tidak, item bisa diisi manual.</div>
            </div>
            <a href="{{ $indexUrl }}" class="btn btn-light">Kembali</a>
        </div>

        @if($isFinalized)
            <div class="alert alert-warning d-flex align-items-center p-5 mb-6">
                <i class="fa-solid fa-circle-info fs-2 text-warning me-4"></i>
                <div>
                    <div class="fw-bold">Data sudah finalisasi</div>
                    <div class="text-muted">Transaksi yang sudah finalisasi tidak bisa diubah.</div>
                </div>
            </div>
        @endif

        <form id="inbound_return_form" class="form">
            @csrf
            <input type="hidden" name="warehouse_id" value="{{ $smallWarehouse->id }}">

            <div class="row g-6">
                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-header border-0 pt-6">
                            <div class="card-title">
                                <h2 class="fw-bolder mb-0">Informasi Resi</h2>
                            </div>
                        </div>
                        <div class="card-body py-6">
                            <div class="fv-row mb-6">
                                <label class="fs-6 fw-bold form-label mb-2">Nomor Resi / ID Pesanan</label>
                                <div class="position-relative">
                                    <input type="text" class="form-control form-control-solid pe-12" id="return_resi_lookup" placeholder="Scan atau ketik nomor resi" autocomplete="off" @disabled($isFinalized)>
                                    <span class="position-absolute top-50 end-0 translate-middle-y me-4 d-none" id="return_resi_spinner">
                                        <span class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                                    </span>
                                </div>
                                <div class="form-text">Enter langsung memproses hasil scan.</div>
                            </div>
                            <div class="alert bg-light-primary border border-primary border-dashed p-4 mb-6">
                                <div class="fw-bold text-gray-800" id="return_resi_status">Belum ada resi dipilih.</div>
                                <div class="text-muted fs-8 mt-1">Nomor resi akan otomatis masuk ke Ref No.</div>
                            </div>
                            <div class="fv-row mb-6">
                                <label class="required fs-6 fw-bold form-label mb-2">Tanggal</label>
                                <input type="text" class="form-control form-control-solid" name="transacted_at" id="return_transacted_at" placeholder="YYYY-MM-DD HH:mm" required @disabled($isFinalized)>
                                <div class="invalid-feedback" data-error="transacted_at"></div>
                            </div>
                            <div class="fv-row mb-6">
                                <label class="fs-6 fw-bold form-label mb-2">Ref No</label>
                                <input type="text" class="form-control form-control-solid" name="ref_no" id="return_ref_no" @disabled($isFinalized)>
                                <div class="invalid-feedback" data-error="ref_no"></div>
                            </div>
                            <div class="fv-row mb-6">
                                <label class="fs-6 fw-bold form-label mb-2">Gudang</label>
                                <input type="text" class="form-control form-control-solid" value="{{ $smallWarehouse->name }}" readonly>
                                <div class="form-text">Retur inbound selalu memakai Gudang Kecil.</div>
                            </div>
                            <div class="fv-row">
                                <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                                <textarea class="form-control form-control-solid" name="note" id="return_note" rows="3" @disabled($isFinalized)></textarea>
                                <div class="invalid-feedback" data-error="note"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-8">
                    <div class="card">
                        <div class="card-header border-0 pt-6">
                            <div class="card-title">
                                <div>
                                    <h2 class="fw-bolder mb-1">Item Diterima</h2>
                                    <div class="text-muted fs-7">Qty bagus + rusak + hilang harus sama dengan qty diterima.</div>
                                </div>
                            </div>
                            <div class="card-toolbar">
                                <button type="button" class="btn btn-light-primary" id="btn_add_return_item" @disabled($isFinalized)>Tambah Item</button>
                            </div>
                        </div>
                        <div class="card-body py-6">
                            <div id="return_items_container"></div>
                            <div class="invalid-feedback d-block" data-error="items"></div>
                            <div class="d-flex flex-column flex-sm-row justify-content-end gap-3 pt-6">
                                <a href="{{ $indexUrl }}" class="btn btn-light">Batal</a>
                                <button type="submit" class="btn btn-primary" @disabled($isFinalized)>
                                    {{ $transaction ? 'Simpan Perubahan' : 'Simpan Retur' }}
                                </button>
                            </div>
                        </div>
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
    const initialItems = @json($initialItems);
    const transaction = @json($transactionPayload);
    const lookupUrl = '{{ $lookupUrl }}';
    const submitUrl = '{{ $transaction ? $updateUrl : $storeUrl }}';
    const indexUrl = '{{ $indexUrl }}';
    const csrfToken = '{{ csrf_token() }}';
    const isEdit = {{ $transaction ? 'true' : 'false' }};
    const isFinalized = {{ $isFinalized ? 'true' : 'false' }};

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('inbound_return_form');
        const container = document.getElementById('return_items_container');
        const addBtn = document.getElementById('btn_add_return_item');
        const lookupInput = document.getElementById('return_resi_lookup');
        const lookupSpinner = document.getElementById('return_resi_spinner');
        const lookupStatus = document.getElementById('return_resi_status');
        const transactedAtEl = document.getElementById('return_transacted_at');
        const refNoEl = document.getElementById('return_ref_no');
        const noteEl = document.getElementById('return_note');
        let fpTransacted = null;
        let lookupTimer = null;
        let lookupController = null;
        let lastLookupCode = '';
        let activeLookupCode = '';

        const pad = (n) => String(n).padStart(2, '0');
        const formatDateTime = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
        const nowJakarta = () => formatDateTime(new Date(new Date().toLocaleString('en-US', { timeZone: 'Asia/Jakarta' })));
        const toQty = (value) => Math.max(0, parseInt(value || '0', 10) || 0);

        if (typeof flatpickr !== 'undefined') {
            fpTransacted = flatpickr(transactedAtEl, { enableTime: true, dateFormat: 'Y-m-d H:i', allowInput: true });
        }

        if (transaction) {
            refNoEl.value = transaction.ref_no || '';
            lookupInput.value = transaction.ref_no || '';
            noteEl.value = transaction.note || '';
            if (fpTransacted) fpTransacted.setDate(transaction.transacted_at || null, true, 'Y-m-d H:i');
            else transactedAtEl.value = transaction.transacted_at || '';
        } else if (fpTransacted) {
            fpTransacted.setDate(nowJakarta(), true, 'Y-m-d H:i');
        } else {
            transactedAtEl.value = nowJakarta();
        }

        const initSelect2 = (selectEl) => {
            if (selectEl && typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({ placeholder: 'Pilih item', allowClear: true, width: '100%' });
            }
        };

        const setLoading = (loading) => {
            lookupSpinner?.classList.toggle('d-none', !loading);
        };

        const clearErrors = () => {
            form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
            form.querySelectorAll('[data-error], [data-error-for]').forEach(el => { el.textContent = ''; });
        };

        const renumberRows = () => {
            container.querySelectorAll('.return-item-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach(el => {
                    el.name = `items[${idx}][${el.getAttribute('data-name')}]`;
                    el.disabled = isFinalized;
                });
            });
        };

        const syncBalance = (row, changedField = null) => {
            const receivedEl = row.querySelector('[data-name="qty_received"]');
            const goodEl = row.querySelector('[data-name="qty_good"]');
            const damagedEl = row.querySelector('[data-name="qty_damaged"]');
            const missingEl = row.querySelector('[data-name="qty_missing"]');
            const infoEl = row.querySelector('.return-balance-info');
            const received = toQty(receivedEl.value);

            if (changedField === 'qty_received') {
                goodEl.value = received;
                damagedEl.value = 0;
                missingEl.value = 0;
            } else if (changedField === 'qty_good' || changedField === 'qty_damaged') {
                const good = Math.min(toQty(goodEl.value), received);
                const damaged = Math.min(toQty(damagedEl.value), Math.max(0, received - good));
                goodEl.value = good;
                damagedEl.value = damaged;
                missingEl.value = Math.max(0, received - good - damaged);
            }

            const total = toQty(goodEl.value) + toQty(damagedEl.value) + toQty(missingEl.value);
            const balanced = received > 0 && total === received;
            [receivedEl, goodEl, damagedEl, missingEl].forEach(el => el.classList.toggle('is-invalid', !balanced));
            infoEl.textContent = balanced
                ? `Balance: ${total.toLocaleString('id-ID')} / ${received.toLocaleString('id-ID')}`
                : `Belum balance: ${total.toLocaleString('id-ID')} / ${received.toLocaleString('id-ID')}`;
            infoEl.className = `form-text fs-8 return-balance-info ${balanced ? 'text-success' : 'text-danger'}`;
            return balanced;
        };

        const validateBalances = () => {
            const rows = Array.from(container.querySelectorAll('.return-item-row'));
            return rows.length > 0 && rows.every(row => syncBalance(row));
        };

        const createRow = (data = {}) => {
            const row = document.createElement('div');
            row.className = 'border border-dashed rounded p-4 mb-4 return-item-row';
            row.innerHTML = `
                <div class="row g-4 align-items-end">
                    <div class="col-lg-4 col-md-6">
                        <label class="required fs-6 fw-bold form-label mb-2">Item</label>
                        <select class="form-select form-select-solid return-item-select" data-name="item_id" required>
                            <option value=""></option>
                            ${itemOptionsHtml}
                        </select>
                        <div class="invalid-feedback" data-error-for="item_id"></div>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="required fs-6 fw-bold form-label mb-2">Qty Resi</label>
                        <input type="number" min="1" class="form-control form-control-solid" data-name="qty" required>
                        <div class="invalid-feedback" data-error-for="qty"></div>
                    </div>
                    <div class="col-lg-2 col-md-6">
                        <label class="required fs-6 fw-bold form-label mb-2">Diterima</label>
                        <input type="number" min="1" class="form-control form-control-solid" data-name="qty_received" required>
                        <div class="form-text fs-8 return-balance-info text-muted">Isi qty diterima.</div>
                        <div class="invalid-feedback" data-error-for="qty_received"></div>
                    </div>
                    <div class="col-lg-1 col-md-4">
                        <label class="required fs-6 fw-bold form-label mb-2">Bagus</label>
                        <input type="number" min="0" class="form-control form-control-solid" data-name="qty_good" required>
                        <div class="invalid-feedback" data-error-for="qty_good"></div>
                    </div>
                    <div class="col-lg-1 col-md-4">
                        <label class="required fs-6 fw-bold form-label mb-2">Rusak</label>
                        <input type="number" min="0" class="form-control form-control-solid" data-name="qty_damaged" required>
                        <div class="invalid-feedback" data-error-for="qty_damaged"></div>
                    </div>
                    <div class="col-lg-1 col-md-4">
                        <label class="required fs-6 fw-bold form-label mb-2">Hilang</label>
                        <input type="number" min="0" class="form-control form-control-solid" data-name="qty_missing" required>
                        <div class="invalid-feedback" data-error-for="qty_missing"></div>
                    </div>
                    <div class="col-lg-1 text-end">
                        <button type="button" class="btn btn-light btn-sm btn-remove-return-item" ${isFinalized ? 'disabled' : ''}>Hapus</button>
                    </div>
                    <div class="col-12">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan Item</label>
                        <input type="text" class="form-control form-control-solid" data-name="note">
                    </div>
                </div>
            `;
            container.appendChild(row);

            row.querySelector('[data-name="item_id"]').value = data.item_id ? String(data.item_id) : '';
            row.querySelector('[data-name="qty"]').value = data.qty ?? '';
            row.querySelector('[data-name="qty_received"]').value = data.qty_received ?? data.qty ?? '';
            row.querySelector('[data-name="qty_good"]').value = data.qty_good ?? data.qty_received ?? data.qty ?? '';
            row.querySelector('[data-name="qty_damaged"]').value = data.qty_damaged ?? 0;
            row.querySelector('[data-name="qty_missing"]').value = data.qty_missing ?? 0;
            row.querySelector('[data-name="note"]').value = data.note || '';

            initSelect2(row.querySelector('.return-item-select'));
            row.querySelectorAll('[data-name="qty_received"], [data-name="qty_good"], [data-name="qty_damaged"], [data-name="qty_missing"]').forEach(el => {
                el.addEventListener('input', () => syncBalance(row, el.getAttribute('data-name')));
            });
            syncBalance(row);
            renumberRows();
        };

        const applyResi = (payload) => {
            const rows = Array.isArray(payload?.items) ? payload.items : [];
            const noResi = payload?.resi?.no_resi || payload?.resi?.id_pesanan || lookupInput.value.trim();
            refNoEl.value = noResi;
            lookupStatus.textContent = rows.length ? `${rows.length} SKU ditemukan dari database import.` : 'Resi ditemukan, tetapi belum ada detail SKU.';
            const matched = rows.filter(row => row.found_item && row.item_id);
            const unmatched = rows.filter(row => !row.found_item);
            if (unmatched.length && typeof Swal !== 'undefined') {
                Swal.fire('Perlu input manual', `SKU belum ada di master item: ${unmatched.map(row => row.sku).join(', ')}`, 'warning');
            }
            if (!matched.length) return;
            container.innerHTML = '';
            matched.forEach(row => createRow({
                item_id: row.item_id,
                qty: row.qty,
                qty_received: row.qty,
                qty_good: row.qty,
                qty_damaged: 0,
                qty_missing: 0,
                note: row.sku ? `Dari resi ${noResi}` : '',
            }));
            clearErrors();
        };

        const runLookup = async (force = false) => {
            const code = (lookupInput.value || '').trim();
            if (!code) {
                lookupStatus.textContent = 'Belum ada resi dipilih.';
                lastLookupCode = '';
                return;
            }
            if (!force && code === lastLookupCode) return;
            lastLookupCode = code;
            lookupController?.abort();
            lookupController = new AbortController();
            activeLookupCode = code;
            lookupStatus.textContent = `Mencari ${code}...`;
            setLoading(true);
            try {
                const res = await fetch(`${lookupUrl}?no_resi=${encodeURIComponent(code)}`, {
                    headers: { 'Accept': 'application/json' },
                    signal: lookupController.signal,
                });
                const json = await res.json();
                if (!res.ok) {
                    refNoEl.value = code;
                    lookupStatus.textContent = json.message || 'Resi tidak ditemukan. Input manual tetap bisa dilakukan.';
                    if (!container.querySelector('.return-item-row')) createRow();
                    return;
                }
                applyResi(json);
            } catch (err) {
                if (err.name !== 'AbortError') lookupStatus.textContent = 'Gagal lookup resi. Input manual tetap bisa dilakukan.';
            } finally {
                if (activeLookupCode === code) {
                    setLoading(false);
                    activeLookupCode = '';
                }
            }
        };

        lookupInput?.addEventListener('input', () => {
            const code = lookupInput.value.trim();
            window.clearTimeout(lookupTimer);
            if (!code) {
                lastLookupCode = '';
                activeLookupCode = '';
                refNoEl.value = '';
                lookupStatus.textContent = 'Belum ada resi dipilih.';
                setLoading(false);
                return;
            }
            refNoEl.value = code;
            lookupStatus.textContent = 'Menunggu input selesai...';
            lookupTimer = window.setTimeout(() => runLookup(false), 650);
        });

        lookupInput?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                window.clearTimeout(lookupTimer);
                runLookup(true);
            }
        });

        addBtn?.addEventListener('click', () => createRow());
        container.addEventListener('click', (event) => {
            const btn = event.target.closest('.btn-remove-return-item');
            if (!btn || isFinalized) return;
            btn.closest('.return-item-row')?.remove();
            if (!container.querySelector('.return-item-row')) createRow();
            renumberRows();
        });

        (Array.isArray(initialItems) && initialItems.length ? initialItems : [{}]).forEach(row => createRow(row));

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearErrors();
            if (!validateBalances()) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Qty bagus + rusak + hilang harus sama dengan qty diterima.', 'error');
                return;
            }

            const formData = new FormData(form);
            if (isEdit) formData.append('_method', 'PUT');

            try {
                const res = await fetch(submitUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: formData,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    if (json?.errors) {
                        Object.entries(json.errors).forEach(([key, value]) => {
                            const msg = Array.isArray(value) ? value.join(', ') : String(value);
                            if (key.startsWith('items.')) {
                                const parts = key.split('.');
                                const row = container.querySelectorAll('.return-item-row')[Number(parts[1])];
                                const field = parts[2];
                                row?.querySelector(`[data-name="${field}"]`)?.classList.add('is-invalid');
                                const errEl = row?.querySelector(`[data-error-for="${field}"]`);
                                if (errEl) errEl.textContent = msg;
                            } else {
                                form.querySelector(`[name="${key}"]`)?.classList.add('is-invalid');
                                const errEl = form.querySelector(`[data-error="${key}"]`);
                                if (errEl) errEl.textContent = msg;
                            }
                        });
                    }
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json?.message || 'Gagal menyimpan retur inbound', 'error');
                    return;
                }

                if (typeof Swal !== 'undefined') {
                    await Swal.fire('Berhasil', json?.message || 'Retur inbound berhasil disimpan', 'success');
                }
                window.location.href = indexUrl;
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan retur inbound', 'error');
            }
        });
    });
</script>
@endpush
