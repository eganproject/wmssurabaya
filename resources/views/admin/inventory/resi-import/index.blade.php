@extends('layouts.admin')

@section('title', 'Import Resi')
@section('page_title', 'Import Resi')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.inventory.resi-import.index', 'create');
@endphp

@section('content')
<style>
    .import-loading-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2050;
        padding: 24px;
    }
    .import-loading-card {
        background: #fff;
        border-radius: 16px;
        padding: 24px 28px;
        text-align: center;
        box-shadow: 0 20px 50px rgba(15, 23, 42, 0.2);
        min-width: 260px;
    }
    .buyer-note-text {
        white-space: pre-wrap;
        word-break: break-word;
    }
</style>

<div class="import-loading-overlay" id="import_loading_overlay">
    <div class="import-loading-card">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="fw-bold mt-3">Memproses import...</div>
        <div class="text-muted fs-7 mt-1">Mohon tunggu, jangan tutup halaman.</div>
    </div>
</div>

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="fw-bold">Import Resi</div>
        </div>
        <div class="card-toolbar">
            @if($canCreate)
                <button type="button" class="btn btn-light-primary" id="btn_import_resi" data-bs-toggle="modal" data-bs-target="#modal_import_resi">Import Excel</button>
            @endif
        </div>
    </div>
    <div class="card-body py-6">
        <div class="text-muted fs-7">
            Header wajib: <strong>ID Pesanan</strong>, <strong>SKU</strong>, <strong>Jumlah</strong>, <strong>Tanggal Pembuatan</strong>.
            <strong>AWB/No. Tracking</strong>, <strong>Kurir</strong>, dan <strong>Catatan Pembeli</strong> opsional.
        </div>
        <div class="text-muted fs-7 mt-2">
            Format tanggal akan dibaca otomatis (string atau tanggal Excel).
        </div>
    </div>
</div>

<div class="card mt-8">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="fw-bold">Batch Import Resi</div>
        </div>
        <div class="card-toolbar">
            <button type="button" class="btn btn-light" id="btn_refresh_batches">Refresh Batch</button>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Batch</th>
                        <th>File</th>
                        <th>Upload</th>
                        <th>Jumlah Resi</th>
                        <th>Detail SKU</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody id="batch_body">
                    <tr>
                        <td colspan="7" class="text-center text-muted py-6">Memuat batch...</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-3" id="batch_pagination" style="display:none;">
            <div class="text-muted" id="batch_page_summary"></div>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-light" id="batch_prev">&larr; Sebelumnya</button>
                <button type="button" class="btn btn-sm btn-light" id="batch_next">Berikutnya &rarr;</button>
            </div>
        </div>
    </div>
</div>

<div class="card mt-8">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" id="filter_search" placeholder="Search no resi / SKU / ID Pesanan / Kurir" value="{{ $filterSearch ?? '' }}" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-solid w-175px" id="filter_status">
                    <option value="">Semua Status</option>
                    <option value="active" {{ ($filterStatus ?? '') === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="canceled" {{ ($filterStatus ?? '') === 'canceled' ? 'selected' : '' }}>Cancel</option>
                </select>
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date" placeholder="Tanggal" value="{{ $filterDate ?? '' }}" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                <button type="button" class="btn btn-light-warning" id="btn_catatan_pembeli">
                    Catatan Pembeli (<span id="summary_buyer_notes">{{ $summaryBuyerNotes ?? 0 }}</span>)
                </button>
                <button type="button" class="btn btn-light-primary" id="btn_rekap_sku">Rekap SKU</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="d-flex flex-wrap align-items-center gap-6 mb-4">
            <div class="fw-bold">Jumlah Pesanan: <span id="summary_orders">{{ $summaryOrders ?? 0 }}</span></div>
            <div class="fw-bold">Jumlah SKU: <span id="summary_skus">{{ $summarySkus ?? 0 }}</span></div>
        </div>
        <div class="fw-bold mb-3">Daftar Resi (Tanggal <span id="label_date">{{ $filterDate ?? $today }}</span>)</div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="resi_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>No</th>
                        <th>No Resi</th>
                        <th>Kurir</th>
                        <th>ID Pesanan</th>
                        <th>SKU</th>
                        <th>Tanggal Order</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

@if($canCreate)
    <div class="modal fade" id="modal_import_resi" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-650px">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="fw-bolder">Import Resi (Excel)</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="modal-body scroll-y px-10 py-10">
                    <div class="mb-7">
                        <p class="fw-semibold mb-3">Pastikan file Excel memiliki header berikut:</p>
                        <ul class="ms-5 mb-4">
                            <li><strong>ID Pesanan</strong> (wajib)</li>
                            <li><strong>SKU</strong> (wajib)</li>
                            <li><strong>Jumlah</strong> (wajib)</li>
                            <li><strong>Tanggal Pembuatan</strong> (wajib)</li>
                            <li><strong>AWB/No. Tracking</strong> (opsional)</li>
                            <li><strong>Kurir</strong> (opsional)</li>
                            <li><strong>Catatan Pembeli</strong> (opsional)</li>
                        </ul>
                        <p class="text-muted small mb-0">Header akan dibaca otomatis menjadi: <code>id_pesanan, awb_no_tracking, kurir, sku, jumlah, tanggal_pembuatan, catatan_pembeli</code></p>
                    </div>
                    <div class="mb-10">
                        <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                        <input type="file" class="form-control form-control-solid" id="import_resi_file" accept=".xlsx,.xls" />
                        <div class="invalid-feedback d-block" id="error_import_resi_file"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="button" class="btn btn-primary" id="btn_import_resi_submit">Import</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="modal fade" id="modal_cancel_resi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Batalkan Resi</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body mx-5 mx-xl-15 my-7">
                <form id="form_cancel_resi">
                    @csrf
                    <div class="fv-row mb-5">
                        <label class="fs-6 fw-bold form-label mb-2">ID Pesanan</label>
                        <input type="text" class="form-control form-control-solid" id="cancel_id_pesanan" name="id_pesanan" readonly />
                    </div>
                    <div class="fv-row mb-5">
                        <label class="fs-6 fw-bold form-label mb-2">No Resi</label>
                        <input type="text" class="form-control form-control-solid" id="cancel_no_resi" name="no_resi" readonly />
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Alasan Cancel</label>
                        <textarea class="form-control form-control-solid" id="cancel_reason" name="reason" rows="3" placeholder="Tulis alasan cancel"></textarea>
                        <div class="text-danger fs-7 mt-2" data-error="reason"></div>
                    </div>
                    <div class="text-end">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger" id="btn_submit_cancel">Cancel Resi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_catatan_pembeli" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Resi dengan Catatan Pembeli</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body mx-5 mx-xl-10 my-7">
                <div class="d-flex flex-wrap align-items-center gap-6 mb-4">
                    <div class="fw-bold">Tanggal: <span id="buyer_notes_date">-</span></div>
                    <div class="fw-bold">Jumlah Resi: <span id="buyer_notes_count">0</span></div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="6%">No</th>
                                <th>No Resi</th>
                                <th>ID Pesanan</th>
                                <th>Kurir</th>
                                <th>Catatan Pembeli</th>
                            </tr>
                        </thead>
                        <tbody id="buyer_notes_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-6">Memuat data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_rekap_sku" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-700px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Rekap SKU Import Resi</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body mx-5 mx-xl-15 my-7">
                <div class="d-flex flex-wrap align-items-center gap-6 mb-4">
                    <div class="fw-bold">Tanggal: <span id="rekap_date">-</span></div>
                    <div class="fw-bold">Total SKU: <span id="rekap_total_sku">0</span></div>
                    <div class="fw-bold">Total Qty: <span id="rekap_total_qty">0</span></div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-4">
                    <input type="text" class="form-control form-control-solid w-250px" id="rekap_search" placeholder="Cari SKU" />
                    <button type="button" class="btn btn-light" id="rekap_search_btn">Search</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="10%">No</th>
                                <th>SKU</th>
                                <th class="text-end">Qty</th>
                            </tr>
                        </thead>
                        <tbody id="rekap_body">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-6">Memuat data...</td>
                            </tr>
                        </tbody>
                    </table>
                    <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-3" id="rekap_pagination" style="display:none;">
                        <div class="text-muted" id="rekap_page_summary"></div>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-light" id="rekap_prev">&larr; Sebelumnya</button>
                            <button type="button" class="btn btn-sm btn-light" id="rekap_next">Berikutnya &rarr;</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_batch_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Detail Batch <span id="batch_detail_code">-</span></h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body mx-5 mx-xl-10 my-7">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>ID Pesanan</th>
                                <th>No Resi</th>
                                <th>Kurir</th>
                                <th>SKU</th>
                                <th>Status DB</th>
                            </tr>
                        </thead>
                        <tbody id="batch_detail_body">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-6">Memuat data...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const importUrl = '{{ $importUrl ?? '' }}';
    const dataUrl = '{{ $dataUrl ?? '' }}';
    const summaryUrl = '{{ route('admin.inventory.resi-import.summary') }}';
    const buyerNotesUrl = '{{ $buyerNotesUrl ?? '' }}';
    const batchesUrl = '{{ $batchesUrl ?? '' }}';
    const batchDetailUrl = '{{ $batchDetailUrl ?? '' }}';
    const batchDeleteUrl = '{{ $batchDeleteUrl ?? '' }}';
    const resiDeleteUrl = '{{ $resiDeleteUrl ?? '' }}';
    const cancelUrl = '{{ route('admin.inventory.resi-import.cancel') }}';
    const uncancelUrl = '{{ route('admin.inventory.resi-import.uncancel') }}';
    const csrfToken = '{{ csrf_token() }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const importBtn = document.getElementById('btn_import_resi');
        const importModalEl = document.getElementById('modal_import_resi');
        const importModal = importModalEl ? new bootstrap.Modal(importModalEl) : null;
        const importInput = document.getElementById('import_resi_file');
        const importError = document.getElementById('error_import_resi_file');
        const importSubmit = document.getElementById('btn_import_resi_submit');
        const cancelModalEl = document.getElementById('modal_cancel_resi');
        const cancelForm = document.getElementById('form_cancel_resi');
        const cancelModal = cancelModalEl ? new bootstrap.Modal(cancelModalEl) : null;
        const cancelIdInput = document.getElementById('cancel_id_pesanan');
        const cancelNoResiInput = document.getElementById('cancel_no_resi');
        const cancelReasonInput = document.getElementById('cancel_reason');
        const loadingOverlay = document.getElementById('import_loading_overlay');
        const filterDateEl = document.getElementById('filter_date');
        const filterSearchEl = document.getElementById('filter_search');
        const filterStatusEl = document.getElementById('filter_status');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const rekapBtn = document.getElementById('btn_rekap_sku');
        const buyerNotesBtn = document.getElementById('btn_catatan_pembeli');
        const buyerNotesModalEl = document.getElementById('modal_catatan_pembeli');
        const buyerNotesModal = buyerNotesModalEl ? new bootstrap.Modal(buyerNotesModalEl) : null;
        const buyerNotesDateEl = document.getElementById('buyer_notes_date');
        const buyerNotesCountEl = document.getElementById('buyer_notes_count');
        const buyerNotesBodyEl = document.getElementById('buyer_notes_body');
        const rekapModalEl = document.getElementById('modal_rekap_sku');
        const rekapModal = rekapModalEl ? new bootstrap.Modal(rekapModalEl) : null;
        const rekapDateEl = document.getElementById('rekap_date');
        const rekapTotalSkuEl = document.getElementById('rekap_total_sku');
        const rekapTotalQtyEl = document.getElementById('rekap_total_qty');
        const rekapBodyEl = document.getElementById('rekap_body');
        const rekapSearchInput = document.getElementById('rekap_search');
        const rekapSearchBtn = document.getElementById('rekap_search_btn');
        const rekapPaginationEl = document.getElementById('rekap_pagination');
        const rekapPageSummaryEl = document.getElementById('rekap_page_summary');
        const rekapPrevBtn = document.getElementById('rekap_prev');
        const rekapNextBtn = document.getElementById('rekap_next');
        const rekapState = {
            rows: [],
            page: 1,
            perPage: 10,
            keyword: '',
        };
        const summaryOrdersEl = document.getElementById('summary_orders');
        const summarySkusEl = document.getElementById('summary_skus');
        const summaryBuyerNotesEl = document.getElementById('summary_buyer_notes');
        const labelDateEl = document.getElementById('label_date');
        const batchBodyEl = document.getElementById('batch_body');
        const batchRefreshBtn = document.getElementById('btn_refresh_batches');
        const batchPaginationEl = document.getElementById('batch_pagination');
        const batchPageSummaryEl = document.getElementById('batch_page_summary');
        const batchPrevBtn = document.getElementById('batch_prev');
        const batchNextBtn = document.getElementById('batch_next');
        const batchDetailModalEl = document.getElementById('modal_batch_detail');
        const batchDetailModal = batchDetailModalEl ? new bootstrap.Modal(batchDetailModalEl) : null;
        const batchDetailCodeEl = document.getElementById('batch_detail_code');
        const batchDetailBodyEl = document.getElementById('batch_detail_body');
        const tableEl = $('#resi_table');
        const batchState = {
            page: 1,
            perPage: 10,
            lastPage: 1,
        };
        let fpDate = null;
        let dt = null;

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const renderBatchStatus = (status) => {
            if (status === 'deleted') {
                return '<span class="badge badge-light-danger">Deleted</span>';
            }
            return '<span class="badge badge-light-success">Active</span>';
        };

        const loadBatches = async () => {
            if (!batchesUrl || !batchBodyEl) return;
            batchBodyEl.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-6">Memuat batch...</td></tr>';
            try {
                const params = new URLSearchParams();
                params.set('page', batchState.page);
                params.set('per_page', batchState.perPage);
                const res = await fetch(`${batchesUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) throw new Error(json?.message || 'Gagal memuat batch.');
                const rows = Array.isArray(json?.data) ? json.data : [];
                const meta = json?.meta || {};
                batchState.page = Number(meta.current_page || batchState.page || 1);
                batchState.lastPage = Number(meta.last_page || 1);
                if (!rows.length) {
                    if (Number(meta.total || 0) > 0 && batchState.page > 1) {
                        batchState.page = Math.max(1, batchState.page - 1);
                        loadBatches();
                        return;
                    }
                    batchBodyEl.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-6">Belum ada batch import.</td></tr>';
                    if (batchPaginationEl) batchPaginationEl.style.display = 'none';
                    return;
                }
                batchBodyEl.innerHTML = rows.map((row) => {
                    const canDelete = row.can_delete === true;
                    const deleteBtn = canDelete
                        ? `<button type="button" class="btn btn-sm btn-light-danger btn-delete-batch" data-id="${row.id}" data-code="${escapeHtml(row.batch_code)}">Hapus Batch</button>`
                        : '<span class="text-muted">-</span>';
                    return `
                        <tr>
                            <td class="fw-bold">${escapeHtml(row.batch_code || '-')}</td>
                            <td>${escapeHtml(row.file_name || '-')}</td>
                            <td>${escapeHtml(row.uploaded_at || '-')}<br><span class="text-muted">${escapeHtml(row.uploaded_by || '-')}</span></td>
                            <td>${row.total_resis ?? 0}</td>
                            <td>${row.total_details ?? 0}</td>
                            <td>${renderBatchStatus(row.status)}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-light-primary btn-detail-batch" data-id="${row.id}">Detail</button>
                                ${deleteBtn}
                            </td>
                        </tr>
                    `;
                }).join('');
                if (batchPaginationEl) {
                    batchPaginationEl.style.display = Number(meta.total || 0) > batchState.perPage ? 'flex' : 'none';
                }
                if (batchPageSummaryEl) {
                    const from = meta.from || 0;
                    const to = meta.to || 0;
                    const total = meta.total || 0;
                    batchPageSummaryEl.textContent = `Menampilkan ${from}-${to} dari ${total} batch hari ini`;
                }
                if (batchPrevBtn) batchPrevBtn.disabled = batchState.page <= 1;
                if (batchNextBtn) batchNextBtn.disabled = batchState.page >= batchState.lastPage;
            } catch (err) {
                batchBodyEl.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-6">Gagal memuat batch.</td></tr>';
                if (batchPaginationEl) batchPaginationEl.style.display = 'none';
            }
        };

        const showBatchDetail = async (id) => {
            if (!batchDetailUrl || !id) return;
            if (batchDetailCodeEl) batchDetailCodeEl.textContent = '-';
            if (batchDetailBodyEl) {
                batchDetailBodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-6">Memuat data...</td></tr>';
            }
            batchDetailModal?.show();
            try {
                const url = batchDetailUrl.replace('__BATCH__', encodeURIComponent(id));
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (!res.ok) throw new Error(json?.message || 'Gagal memuat detail batch.');
                const rows = Array.isArray(json?.data) ? json.data : [];
                if (batchDetailCodeEl) batchDetailCodeEl.textContent = json?.batch?.batch_code || '-';
                if (!rows.length) {
                    if (batchDetailBodyEl) {
                        batchDetailBodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-6">Batch tidak memiliki item.</td></tr>';
                    }
                    return;
                }
                if (batchDetailBodyEl) {
                    batchDetailBodyEl.innerHTML = rows.map((row, idx) => {
                        const skuText = Array.isArray(row.items)
                            ? row.items.map((item) => `${escapeHtml(item.sku || '-')} (${item.qty || 0})`).join(', ')
                            : '-';
                        return `
                            <tr>
                                <td>${idx + 1}</td>
                                <td>${escapeHtml(row.id_pesanan || '-')}</td>
                                <td>${escapeHtml(row.no_resi || '-')}</td>
                                <td>${escapeHtml(row.kurir || '-')}</td>
                                <td>${skuText || '-'}</td>
                                <td>${row.deleted ? '<span class="badge badge-light-danger">Terhapus</span>' : '<span class="badge badge-light-success">Ada</span>'}</td>
                            </tr>
                        `;
                    }).join('');
                }
            } catch (err) {
                if (batchDetailBodyEl) {
                    batchDetailBodyEl.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-6">Gagal memuat detail batch.</td></tr>';
                }
            }
        };

        const deleteBatch = async (id, code) => {
            if (!batchDeleteUrl || !id) return;
            const runDelete = async (reason = '') => {
                const formData = new FormData();
                formData.append('_method', 'DELETE');
                if (reason) formData.append('reason', reason);
                const url = batchDeleteUrl.replace('__BATCH__', encodeURIComponent(id));
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    const error = new Error(json?.message || 'Gagal menghapus batch.');
                    error.payload = json;
                    throw error;
                }
                return json;
            };

            try {
                let reason = '';
                if (typeof Swal !== 'undefined') {
                    const result = await Swal.fire({
                        title: 'Hapus batch import?',
                        text: `Batch ${code || ''} hanya akan dihapus jika semua resinya belum pernah diproses QC, scan packer, atau scan out.`,
                        input: 'text',
                        inputPlaceholder: 'Alasan hapus batch',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, hapus',
                        cancelButtonText: 'Batal',
                    });
                    if (!result.isConfirmed) return;
                    reason = result.value || '';
                } else if (!confirm(`Hapus batch ${code || ''}?`)) {
                    return;
                }
                const json = await runDelete(reason);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Batch Berhasil Dihapus',
                        icon: 'success',
                        html: `
                            <div class="text-start">
                                <div class="fw-bold mb-2">${escapeHtml(json?.message || 'Batch berhasil dihapus')}</div>
                                <div class="text-muted mb-3">${escapeHtml(json?.detail || '')}</div>
                                <div>Batch: <strong>${escapeHtml(json?.batch_code || code || '-')}</strong></div>
                                <div>Resi terhapus: <strong>${json?.deleted_resis ?? 0}</strong></div>
                                <div>Detail SKU terhapus: <strong>${json?.deleted_details ?? 0}</strong></div>
                            </div>
                        `,
                    });
                }
                loadBatches();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') {
                    const payload = err.payload || {};
                    const blockers = Array.isArray(payload.blockers) ? payload.blockers : [];
                    if (blockers.length) {
                        const rows = blockers.slice(0, 10).map((row, idx) => `
                            <tr>
                                <td>${idx + 1}</td>
                                <td>${escapeHtml(row.no_resi || '-')}</td>
                                <td>${escapeHtml(row.id_pesanan || '-')}</td>
                                <td>${escapeHtml((row.reasons || []).join(', '))}</td>
                            </tr>
                        `).join('');
                        const more = blockers.length > 10
                            ? `<div class="text-muted mt-2">Dan ${blockers.length - 10} resi lain.</div>`
                            : '';
                        Swal.fire({
                            title: 'Batch Tidak Bisa Dihapus',
                            icon: 'warning',
                            width: 760,
                            html: `
                                <div class="text-start">
                                    <div class="fw-bold mb-2">${escapeHtml(payload.message || err.message || 'Batch tidak bisa dihapus.')}</div>
                                    <div class="text-muted mb-3">${escapeHtml(payload.detail || '')}</div>
                                    <div class="table-responsive">
                                        <table class="table table-row-dashed align-middle fs-7">
                                            <thead>
                                                <tr class="text-start text-gray-400 fw-bolder text-uppercase">
                                                    <th>No</th>
                                                    <th>No Resi</th>
                                                    <th>ID Pesanan</th>
                                                    <th>Alasan</th>
                                                </tr>
                                            </thead>
                                            <tbody>${rows}</tbody>
                                        </table>
                                    </div>
                                    ${more}
                                </div>
                            `,
                        });
                    } else {
                        Swal.fire('Error', err.message || 'Gagal menghapus batch', 'error');
                    }
                }
            }
        };

        const setBuyerNotesCount = (count) => {
            const normalized = Number(count || 0);
            if (summaryBuyerNotesEl) summaryBuyerNotesEl.textContent = normalized;
            if (buyerNotesBtn) buyerNotesBtn.disabled = normalized <= 0;
        };
        setBuyerNotesCount(summaryBuyerNotesEl?.textContent || 0);
        loadBatches();

        batchRefreshBtn?.addEventListener('click', () => {
            batchState.page = 1;
            loadBatches();
        });
        batchPrevBtn?.addEventListener('click', () => {
            if (batchState.page <= 1) return;
            batchState.page -= 1;
            loadBatches();
        });
        batchNextBtn?.addEventListener('click', () => {
            if (batchState.page >= batchState.lastPage) return;
            batchState.page += 1;
            loadBatches();
        });
        batchBodyEl?.addEventListener('click', (e) => {
            const detailBtn = e.target.closest('.btn-detail-batch');
            if (detailBtn) {
                showBatchDetail(detailBtn.getAttribute('data-id'));
                return;
            }
            const deleteBtn = e.target.closest('.btn-delete-batch');
            if (deleteBtn) {
                deleteBatch(deleteBtn.getAttribute('data-id'), deleteBtn.getAttribute('data-code'));
            }
        });

        if (typeof flatpickr !== 'undefined' && filterDateEl) {
            fpDate = flatpickr(filterDateEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        if (tableEl.length && $.fn.DataTable) {
            dt = tableEl.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                ordering: false,
                ajax: {
                    url: dataUrl,
                    dataSrc: 'data',
                    data: function(params) {
                        params.q = filterSearchEl?.value || '';
                        params.date = filterDateEl?.value || '';
                        params.status = filterStatusEl?.value || '';
                    }
                },
                columns: [
                    { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }},
                    { data: 'no_resi' },
                    { data: 'kurir' },
                        { data: 'id_pesanan' },
                        { data: 'sku' },
                        { data: 'tanggal_pesanan' },
                        { data: 'status', render: (data) => {
                            if (data === 'canceled') {
                                return '<span class="badge badge-light-danger">Cancel</span>';
                            }
                            return '<span class="badge badge-light-success">Aktif</span>';
                        }},
                        { data: null, orderable: false, searchable: false, className: 'text-end', render: (data, type, row) => {
                            const idPesanan = row.id_pesanan || '';
                            const noResi = row.no_resi || '';
                            const status = row.status || 'active';
                            const canDelete = row.can_delete === true;
                            const deleteBtn = canDelete
                                ? `<button type="button" class="btn btn-sm btn-light-danger btn-delete-resi ms-2" data-resi-id="${row.id}" data-id="${escapeHtml(idPesanan)}" data-resi="${escapeHtml(noResi)}">Hapus</button>`
                                : '';
                            if (status === 'canceled') {
                                return `<button type="button" class="btn btn-sm btn-light-warning btn-uncancel" data-id="${escapeHtml(idPesanan)}" data-resi="${escapeHtml(noResi)}">Batal Cancel</button>${deleteBtn}`;
                            }
                            if (!canDelete) {
                                return '<span class="text-muted">-</span>';
                            }
                            return `<button type="button" class="btn btn-sm btn-light-warning btn-cancel" data-id="${escapeHtml(idPesanan)}" data-resi="${escapeHtml(noResi)}">Cancel</button>${deleteBtn}`;
                        }},
                    ],
                    language: {
                        emptyTable: 'Belum ada data',
                        processing: 'Memuat...',
                },
            });

            tableEl.on('xhr.dt', function () {
                const json = dt?.ajax?.json?.();
                if (json?.summary) {
                    if (summaryOrdersEl) summaryOrdersEl.textContent = json.summary.orders ?? '0';
                    if (summarySkusEl) summarySkusEl.textContent = json.summary.skus ?? '0';
                    setBuyerNotesCount(json.summary.buyer_notes ?? 0);
                }
            });
        }

        const reloadTable = () => {
            if (labelDateEl) labelDateEl.textContent = filterDateEl?.value || todayStr || '';
            dt?.ajax?.reload();
        };

        filterApplyBtn?.addEventListener('click', reloadTable);
        filterSearchEl?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadTable();
        });
        filterStatusEl?.addEventListener('change', reloadTable);
        filterResetBtn?.addEventListener('click', () => {
            if (fpDate && todayStr) {
                fpDate.setDate(todayStr, true);
            } else if (filterDateEl && todayStr) {
                filterDateEl.value = todayStr;
            }
            if (filterSearchEl) filterSearchEl.value = '';
            if (filterStatusEl) filterStatusEl.value = '';
            reloadTable();
        });

        buyerNotesBtn?.addEventListener('click', async () => {
            const dateValue = (filterDateEl?.value || todayStr || '').trim();
            const statusValue = (filterStatusEl?.value || '').trim();
            const searchValue = (filterSearchEl?.value || '').trim();

            if (buyerNotesDateEl) buyerNotesDateEl.textContent = dateValue || '-';
            if (buyerNotesCountEl) buyerNotesCountEl.textContent = '0';
            if (buyerNotesBodyEl) {
                buyerNotesBodyEl.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
            buyerNotesModal?.show();

            try {
                const params = new URLSearchParams();
                if (dateValue) params.set('date', dateValue);
                if (statusValue) params.set('status', statusValue);
                if (searchValue) params.set('q', searchValue);
                const res = await fetch(`${buyerNotesUrl}?${params.toString()}`);
                const json = await res.json();
                if (!res.ok) {
                    throw new Error(json?.message || 'Gagal memuat catatan pembeli.');
                }

                const rows = Array.isArray(json?.data) ? json.data : [];
                if (buyerNotesDateEl) buyerNotesDateEl.textContent = json?.date || dateValue || '-';
                if (buyerNotesCountEl) buyerNotesCountEl.textContent = json?.count ?? rows.length;

                if (!rows.length) {
                    if (buyerNotesBodyEl) {
                        buyerNotesBodyEl.innerHTML = `
                            <tr>
                                <td colspan="5" class="text-center text-muted py-6">Tidak ada resi dengan catatan pembeli.</td>
                            </tr>
                        `;
                    }
                    return;
                }

                if (buyerNotesBodyEl) {
                    buyerNotesBodyEl.innerHTML = rows.map((row, idx) => `
                        <tr>
                            <td>${idx + 1}</td>
                            <td>${escapeHtml(row.no_resi || '-')}</td>
                            <td>${escapeHtml(row.id_pesanan || '-')}</td>
                            <td>${escapeHtml(row.kurir || '-')}</td>
                            <td><div class="buyer-note-text">${escapeHtml(row.catatan_pembeli || '-')}</div></td>
                        </tr>
                    `).join('');
                }
            } catch (err) {
                if (buyerNotesBodyEl) {
                    buyerNotesBodyEl.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-danger py-6">Gagal memuat catatan pembeli.</td>
                        </tr>
                    `;
                }
            }
        });

        rekapBtn?.addEventListener('click', async () => {
            const dateValue = (filterDateEl?.value || todayStr || '').trim();
            const statusValue = (filterStatusEl?.value || '').trim();
            if (rekapDateEl) rekapDateEl.textContent = dateValue || '-';
            if (rekapTotalSkuEl) rekapTotalSkuEl.textContent = '0';
            if (rekapTotalQtyEl) rekapTotalQtyEl.textContent = '0';
            if (rekapBodyEl) {
                rekapBodyEl.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
            if (rekapSearchInput) rekapSearchInput.value = '';
            rekapState.keyword = '';
            rekapState.page = 1;
            rekapState.rows = [];
            rekapModal?.show();
            try {
                const params = new URLSearchParams();
                if (dateValue) params.set('date', dateValue);
                if (statusValue) params.set('status', statusValue);
                const res = await fetch(`${summaryUrl}?${params.toString()}`);
                const json = await res.json();
                if (!res.ok) {
                    throw new Error(json?.message || 'Gagal memuat rekap.');
                }
                const rows = Array.isArray(json?.data) ? json.data : [];
                if (rekapDateEl) rekapDateEl.textContent = json?.date || dateValue || '-';
                if (rekapTotalSkuEl) rekapTotalSkuEl.textContent = json?.summary?.total_sku ?? 0;
                if (rekapTotalQtyEl) rekapTotalQtyEl.textContent = json?.summary?.total_qty ?? 0;
                rekapState.rows = rows;
                rekapState.page = 1;
                renderRekapRows();
            } catch (err) {
                if (rekapBodyEl) {
                    rekapBodyEl.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center text-danger py-6">Gagal memuat data rekap.</td>
                        </tr>
                    `;
                }
                if (rekapPaginationEl) rekapPaginationEl.style.display = 'none';
            }
        });

        const getRekapFilteredRows = () => {
            const keyword = (rekapState.keyword || '').toLowerCase();
            if (!keyword) {
                return rekapState.rows;
            }
            return rekapState.rows.filter((row) => (row.sku || '').toLowerCase().includes(keyword));
        };

        const renderRekapRows = () => {
            const rows = getRekapFilteredRows();
            const total = rows.length;
            const perPage = rekapState.perPage;
            const maxPage = Math.max(1, Math.ceil(total / perPage));
            rekapState.page = Math.min(Math.max(1, rekapState.page), maxPage);
            const start = (rekapState.page - 1) * perPage;
            const paginated = rows.slice(start, start + perPage);

            if (!paginated.length) {
                if (rekapBodyEl) {
                    rekapBodyEl.innerHTML = `
                        <tr>
                            <td colspan="3" class="text-center text-muted py-6">Tidak ada data.</td>
                        </tr>
                    `;
                }
            } else if (rekapBodyEl) {
                rekapBodyEl.innerHTML = paginated.map((row, idx) => `
                    <tr>
                        <td>${start + idx + 1}</td>
                        <td>${row.sku || '-'}</td>
                        <td class="text-end">${row.qty ?? 0}</td>
                    </tr>
                `).join('');
            }

            if (rekapPaginationEl) {
                rekapPaginationEl.style.display = total > perPage ? 'flex' : 'none';
            }
            if (rekapPageSummaryEl) {
                rekapPageSummaryEl.textContent = `Halaman ${rekapState.page} dari ${maxPage}`;
            }
            if (rekapPrevBtn) rekapPrevBtn.disabled = rekapState.page <= 1;
            if (rekapNextBtn) rekapNextBtn.disabled = rekapState.page >= maxPage;
        };

        const applyRekapSearch = () => {
            rekapState.keyword = (rekapSearchInput?.value || '').trim();
            rekapState.page = 1;
            renderRekapRows();
        };

        rekapSearchBtn?.addEventListener('click', applyRekapSearch);
        rekapSearchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                applyRekapSearch();
            }
        });
        rekapPrevBtn?.addEventListener('click', () => {
            rekapState.page = Math.max(1, rekapState.page - 1);
            renderRekapRows();
        });
        rekapNextBtn?.addEventListener('click', () => {
            rekapState.page += 1;
            renderRekapRows();
        });

        importBtn?.addEventListener('click', () => {
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
        });

        const clearCancelErrors = () => {
            cancelForm?.querySelectorAll('[data-error]').forEach((el) => { el.textContent = ''; });
        };

        tableEl.on('click', '.btn-cancel', function (e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const resi = this.getAttribute('data-resi');
            const openModal = () => {
                if (cancelIdInput) cancelIdInput.value = id || '';
                if (cancelNoResiInput) cancelNoResiInput.value = resi || '';
                if (cancelReasonInput) cancelReasonInput.value = '';
                clearCancelErrors();
                cancelModal?.show();
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Batalkan resi ini?',
                    text: 'Resi yang dibatalkan tidak bisa diproses QC scan maupun scan out.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, cancel',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (result.isConfirmed) openModal();
                });
            } else {
                if (confirm('Batalkan resi ini?')) openModal();
            }
        });

        tableEl.on('click', '.btn-delete-resi', async function (e) {
            e.preventDefault();
            if (!resiDeleteUrl) return;

            const resiId = this.getAttribute('data-resi-id');
            const idPesanan = this.getAttribute('data-id') || '-';
            const noResi = this.getAttribute('data-resi') || '-';
            if (!resiId) return;

            const runDelete = async () => {
                const formData = new FormData();
                formData.append('_method', 'DELETE');
                const url = resiDeleteUrl.replace('__RESI__', encodeURIComponent(resiId));
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(json?.message || 'Gagal menghapus resi.');
                }
                return json;
            };

            try {
                if (typeof Swal !== 'undefined') {
                    const result = await Swal.fire({
                        title: 'Hapus resi ini?',
                        html: `
                            <div class="text-start">
                                <div>Resi: <strong>${escapeHtml(noResi)}</strong></div>
                                <div>ID Pesanan: <strong>${escapeHtml(idPesanan)}</strong></div>
                                <div class="text-muted mt-2">Resi akan dihapus permanen jika belum masuk QC scan, scan packer, atau scan out.</div>
                            </div>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Hapus',
                        cancelButtonText: 'Batal',
                    });
                    if (!result.isConfirmed) return;
                } else if (!confirm(`Hapus resi ${noResi || idPesanan}?`)) {
                    return;
                }

                const json = await runDelete();
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json?.message || 'Resi berhasil dihapus.', 'success');
                }
                loadBatches();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', err.message || 'Gagal menghapus resi.', 'error');
                }
            }
        });

        tableEl.on('click', '.btn-uncancel', async function (e) {
            e.preventDefault();
            const id = this.getAttribute('data-id') || '';
            const resi = this.getAttribute('data-resi') || '';

            const payload = new FormData();
            if (id) payload.append('id_pesanan', id);
            if (resi) payload.append('no_resi', resi);

            const submitUncancel = async () => {
                try {
                    const res = await fetch(uncancelUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: payload,
                    });
                    const text = await res.text();
                    let json = null;
                    try { json = JSON.parse(text); } catch (err) { /* ignore */ }

                    if (!res.ok) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', json?.message || 'Gagal membatalkan status cancel', 'error');
                        }
                        return;
                    }

                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Berhasil', json?.message || 'Status cancel dibatalkan', 'success');
                    }
                    reloadTable();
                } catch (err) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', 'Gagal membatalkan status cancel', 'error');
                    }
                }
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Batalkan status cancel?',
                    text: 'Resi akan aktif kembali dan masuk lagi ke picking list.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, aktifkan lagi',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (result.isConfirmed) submitUncancel();
                });
            } else if (confirm('Batalkan status cancel resi ini?')) {
                submitUncancel();
            }
        });

        cancelForm?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearCancelErrors();
            const formData = new FormData(cancelForm);
            try {
                const res = await fetch(cancelUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); } catch (err) { /* ignore */ }
                if (!res.ok) {
                    if (res.status === 422 && json?.errors) {
                        Object.entries(json.errors).forEach(([field, messages]) => {
                            const errEl = cancelForm.querySelector(`[data-error="${field}"]`);
                            if (errEl) errEl.textContent = messages.join(', ');
                        });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json?.message || 'Gagal cancel resi', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json?.message || 'Resi dibatalkan', 'success');
                }
                cancelModal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Gagal cancel resi', 'error');
                }
            }
        });

        const setLoading = (state) => {
            if (!loadingOverlay) return;
            loadingOverlay.style.display = state ? 'flex' : 'none';
            if (importSubmit) importSubmit.disabled = state;
            if (importInput) importInput.disabled = state;
            if (importBtn) importBtn.disabled = state;
            document.body.style.cursor = state ? 'progress' : '';
        };

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

            try {
                setLoading(true);
                const res = await fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const isJson = res.headers.get('content-type')?.includes('application/json');
                const json = isJson ? await res.json() : {};

                if (!res.ok) {
                    const msg = json?.errors?.file?.[0] || json?.message || 'Gagal import';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg, 'error');
                    } else if (importError) {
                        importError.textContent = msg;
                    }
                    return;
                }

                const successMsg = json?.message || 'Import resi berhasil';
                if (typeof Swal !== 'undefined') {
                    const count = json?.details ? ` (detail: ${json.details})` : '';
                    Swal.fire('Berhasil', successMsg + count, 'success');
                }

                if (importInput) importInput.value = '';
                importModal?.hide();
                batchState.page = 1;
                loadBatches();
                reloadTable();
            } catch (e) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Gagal import', 'error');
                } else if (importError) {
                    importError.textContent = 'Gagal import';
                }
            } finally {
                setLoading(false);
            }
        });
    });
</script>
@endpush
