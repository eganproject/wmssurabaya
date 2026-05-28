@extends('layouts.admin')

@section('title', 'QC Transit')
@section('page_title', 'QC Transit')

@push('styles')
<style>
    .modal-picker-transit-detail {
        --bs-modal-width: 1500px;
        width: min(96vw, 1500px);
        max-width: calc(100vw - 1.5rem);
    }

    @media (max-width: 575.98px) {
        .modal-picker-transit-detail {
            width: calc(100% - 1rem);
            max-width: calc(100% - 1rem);
        }
    }
</style>
@endpush

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="fw-bold">QC Transit</div>
        </div>
    </div>

    <div class="card-body py-6">
        <div class="row g-4 mb-6">
            <div class="col-md-6">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-hourglass-split fs-2 text-warning me-3"></i>
                                <div>
                                    <div class="text-muted">QC Transit - Belum Scan Out</div>
                                    <div class="fs-2 fw-bold" id="picker_summary_ongoing">0</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light btn-picker-status" data-status="ongoing" data-title="QC Transit - Belum Scan Out">
                                Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <i class="bi bi-check-circle-fill fs-2 text-success me-3"></i>
                                <div>
                                    <div class="text-muted">QC Transit - Selesai</div>
                                    <div class="fs-2 fw-bold" id="picker_summary_done">0</div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light btn-picker-status" data-status="done" data-title="QC Transit - Selesai">
                                Detail
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" id="picker_filter_search" placeholder="Search Resi / Pesanan / SKU" />
            </div>

            <input type="text" class="form-control form-control-solid w-150px" id="picker_filter_date" placeholder="Tanggal" value="{{ $today ?? '' }}" />

            <select class="form-select form-select-solid w-175px" id="picker_filter_status">
                <option value="">Semua Status</option>
                <option value="ongoing">Belum Scan Out</option>
                <option value="done">Selesai</option>
            </select>

            <button type="button" class="btn btn-light" id="picker_filter_apply">Filter</button>
            <button type="button" class="btn btn-light" id="picker_filter_reset">Reset</button>

            @if((auth()->user()->email ?? '') === 'superadmin@gmail.com')
                <button type="button" class="btn btn-light-primary" id="picker_recalculate_today" title="Hitung ulang remaining QC transit untuk hari ini.">Recalculate Hari Ini</button>
                <button type="button" class="btn btn-light" id="picker_audit_remaining" title="Audit SKU yang masih remaining dan indikasi penyebabnya.">Audit Remaining</button>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="picker_transit_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>No</th>
                        <th>Tanggal</th>
                        <th>ID Pesanan</th>
                        <th>No Resi</th>
                        <th>SKU Dalam Resi</th>
                        <th>Progres QC Resi</th>
                        <th class="text-end">Scan / Wajib</th>
                        <th>Scan Out</th>
                        <th>Petugas QC</th>
                        <th>Detail</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_picker_status" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-0" id="picker_status_title">QC Transit</h2>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <input type="text" class="form-control form-control-solid w-250px" id="picker_status_search" placeholder="Search Resi / Pesanan / SKU" />
                    <button type="button" class="btn btn-light-primary" id="picker_status_export">Export Excel</button>
                </div>
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="picker_status_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>ID Pesanan</th>
                                <th>No Resi</th>
                                <th>SKU</th>
                                <th>Progres QC</th>
                                <th class="text-end">Scan / Wajib</th>
                                <th>Scan Out</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_picker_transit_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable modal-picker-transit-detail">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1" id="picker_transit_detail_title">Detail Resi</h5>
                    <div class="text-muted fs-7" id="picker_transit_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-5">
                    <div class="d-flex align-items-center gap-1 px-3 py-2 rounded bg-light-primary">
                        <i class="bi bi-receipt text-primary fs-5"></i>
                        <span class="text-muted fs-7 me-1">Total SKU</span>
                        <span class="fw-bold text-primary fs-6" id="picker_transit_detail_total_resi">0</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 px-3 py-2 rounded bg-light-success">
                        <i class="bi bi-check-circle text-success fs-5"></i>
                        <span class="text-muted fs-7 me-1">SKU Selesai</span>
                        <span class="fw-bold text-success fs-6" id="picker_transit_detail_qc_scanned">0</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 px-3 py-2 rounded bg-light-warning">
                        <i class="bi bi-hourglass-split text-warning fs-5"></i>
                        <span class="text-muted fs-7 me-1">Belum Selesai</span>
                        <span class="fw-bold text-warning fs-6" id="picker_transit_detail_qc_pending">0</span>
                    </div>
                    <div class="d-flex align-items-center gap-1 px-3 py-2 rounded bg-light-info">
                        <i class="bi bi-box text-info fs-5"></i>
                        <span class="text-muted fs-7 me-1">Scan / Wajib</span>
                        <span class="fw-bold text-info fs-6" id="picker_transit_detail_total_qty">0</span>
                    </div>
                </div>
                <div class="d-flex align-items-center position-relative mb-4">
                    <span class="svg-icon svg-icon-1 position-absolute ms-6">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                            <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                        </svg>
                    </span>
                    <input type="text" class="form-control form-control-solid w-300px ps-14" id="picker_transit_detail_search" placeholder="Search SKU / Nama" />
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="5%">No</th>
                                <th width="20%">SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end">Scan / Wajib</th>
                                <th class="text-center">Progress</th>
                                <th>Petugas</th>
                                <th>Status QC</th>
                            </tr>
                        </thead>
                        <tbody id="picker_transit_detail_body">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_picker_transit_audit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Audit Remaining QC Transit</h5>
                    <div class="text-muted fs-7" id="picker_transit_audit_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th>SKU</th>
                                <th>Nama</th>
                                <th class="text-end">Qty QC</th>
                                <th class="text-end">Remaining</th>
                                <th>Exception</th>
                                <th>Indikasi</th>
                            </tr>
                        </thead>
                        <tbody id="picker_transit_audit_body">
                            <tr>
                                <td colspan="6" class="text-center text-muted py-6">Belum ada data.</td>
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
    const dataUrl = '{{ $dataUrl }}';
    const pickerTransitDetailUrl = '{{ route('admin.inventory.picker-transit.detail') }}';
    const pickerTransitAuditUrl = '{{ route('admin.inventory.picker-transit.audit') }}';
    const pickerTransitRecalcTodayUrl = '{{ route('admin.inventory.picker-transit.recalculate-today') }}';
    const csrfToken = '{{ csrf_token() }}';
    const exportPickerUrl = '{{ route('admin.inventory.picker-transit.export-picker') }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picker_transit_table');
        const statusTableEl = $('#picker_status_table');

        const searchInput = document.getElementById('picker_filter_search');
        const dateEl = document.getElementById('picker_filter_date');
        const applyBtn = document.getElementById('picker_filter_apply');
        const resetBtn = document.getElementById('picker_filter_reset');
        const statusEl = document.getElementById('picker_filter_status');
        const recalcBtn = document.getElementById('picker_recalculate_today');
        const auditBtn = document.getElementById('picker_audit_remaining');

        const statusModalEl = document.getElementById('modal_picker_status');
        const statusModal = statusModalEl ? new bootstrap.Modal(statusModalEl) : null;
        const statusTitleEl = document.getElementById('picker_status_title');
        const statusSearchEl = document.getElementById('picker_status_search');
        const statusExportBtn = document.getElementById('picker_status_export');

        const pickerTransitDetailModalEl = document.getElementById('modal_picker_transit_detail');
        const pickerTransitDetailModal = pickerTransitDetailModalEl ? new bootstrap.Modal(pickerTransitDetailModalEl) : null;
        const pickerTransitDetailSubtitleEl = document.getElementById('picker_transit_detail_subtitle');
        const pickerTransitDetailTotalResiEl = document.getElementById('picker_transit_detail_total_resi');
        const pickerTransitDetailTotalQtyEl = document.getElementById('picker_transit_detail_total_qty');
        const pickerTransitDetailQcScannedEl = document.getElementById('picker_transit_detail_qc_scanned');
        const pickerTransitDetailQcPendingEl = document.getElementById('picker_transit_detail_qc_pending');
        const pickerTransitDetailBodyEl = document.getElementById('picker_transit_detail_body');
        const pickerTransitDetailSearchEl = document.getElementById('picker_transit_detail_search');

        const pickerTransitAuditModalEl = document.getElementById('modal_picker_transit_audit');
        const pickerTransitAuditModal = pickerTransitAuditModalEl ? new bootstrap.Modal(pickerTransitAuditModalEl) : null;
        const pickerTransitAuditSubtitleEl = document.getElementById('picker_transit_audit_subtitle');
        const pickerTransitAuditBodyEl = document.getElementById('picker_transit_audit_body');

        let fpDate = null;
        let dtMain = null;
        let dtStatus = null;
        let statusFilter = '';
        let activeDetailQcResiId = '';

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof flatpickr !== 'undefined' && dateEl) {
            fpDate = flatpickr(dateEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        dtMain = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[1, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: function(json) {
                    const summary = json?.summary || {};
                    const ongoing = summary.ongoing ?? 0;
                    const done = summary.done ?? 0;
                    const elOngoing = document.getElementById('picker_summary_ongoing');
                    const elDone = document.getElementById('picker_summary_done');
                    if (elOngoing) elOngoing.textContent = ongoing;
                    if (elDone) elDone.textContent = done;
                    return json.data || [];
                },
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (dateEl?.value) params.date = dateEl.value;
                    if (statusEl?.value) params.status = statusEl.value;
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                { data: 'date' },
                { data: 'id_pesanan', render: (data) => `<span class="fw-semibold text-gray-700">${data || '-'}</span>` },
                { data: 'no_resi', render: (data) => `<span class="fw-bold font-monospace text-primary">${data || '-'}</span>` },
                { data: 'sku_summary', orderable: false, render: (data, type, row) => {
                    return `<div class="fw-semibold">${Number(row.sku_count || 0)} SKU</div><div class="text-muted fs-9 text-truncate" style="max-width:260px">${data || '-'}</div>`;
                }},
                { data: 'qc_progress', orderable: false, render: (data, type, row) => {
                    const pct = Number(data || 0);
                    const barClass = pct >= 100 ? 'bg-success' : (pct > 0 ? 'bg-warning' : 'bg-secondary');
                    return `<div class="d-flex align-items-center gap-2" style="min-width:165px">
                                <div class="progress flex-grow-1 h-6px bg-light">
                                    <div class="progress-bar ${barClass}" style="width:${pct}%"></div>
                                </div>
                                <span class="fw-semibold fs-8">${pct}%</span>
                            </div>
                            <div class="text-muted fs-9">${row.qc_status === 'completed' ? 'QC selesai' : 'Belum selesai QC'}</div>`;
                }},
                { data: 'qc_scanned_qty', className: 'text-end', orderable: false, render: (data, type, row) => {
                    const scanned = Number(data || 0).toLocaleString('id-ID');
                    const required = Number(row.qc_required_qty || 0).toLocaleString('id-ID');
                    return `<span class="fw-semibold">${scanned}</span><span class="text-muted fs-8"> / ${required}</span>`;
                }},
                { data: 'scan_out_status', render: (data, type, row) => {
                    if (data === 'done') {
                        return `<span class="badge badge-light-success">Selesai</span><div class="text-muted fs-9">${row.scan_out_at || '-'}</div>`;
                    }
                    return '<span class="badge badge-light-warning">Belum Scan Out</span>';
                }},
                { data: 'scanner_name', render: data => `<div class="fw-semibold">${data || '-'}</div>` },
                { data: null, orderable: false, searchable: false, render: (data, type, row) => {
                    const id = row?.id || '';
                    const disabled = !id ? 'disabled' : '';
                    return `<button type="button" class="btn btn-sm btn-light-primary btn-picker-transit-detail" data-id="${id}" data-label="${row?.no_resi || row?.id_pesanan || '-'}" ${disabled}><i class="bi bi-list-ul me-1"></i>SKU</button>`;
                }},
            ],
            createdRow: (row, data) => {
                if (data.scan_out_status === 'done') {
                    $(row).addClass('bg-light-success');
                }
            }
        });

        const reloadMain = () => dtMain?.ajax?.reload();

        let mainSearchTimer = null;
        searchInput?.addEventListener('keyup', () => {
            clearTimeout(mainSearchTimer);
            mainSearchTimer = setTimeout(reloadMain, 300);
        });
        applyBtn?.addEventListener('click', reloadMain);
        resetBtn?.addEventListener('click', () => {
            if (fpDate && todayStr) {
                fpDate.setDate(todayStr, true);
            } else if (dateEl) {
                dateEl.value = todayStr || '';
            }
            if (searchInput) searchInput.value = '';
            if (statusEl) statusEl.value = '';
            reloadMain();
        });
        statusEl?.addEventListener('change', reloadMain);

        const initStatusTable = () => {
            if (!statusTableEl.length || dtStatus) return;
            dtStatus = statusTableEl.DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip',
                order: [[1, 'desc']],
                responsive: true,
                scrollX: true,
                ajax: {
                    url: dataUrl,
                    dataSrc: 'data',
                    data: function(params) {
                        params.q = statusSearchEl?.value || '';
                        if (dateEl?.value) params.date = dateEl.value;
                        if (statusFilter) params.status = statusFilter;
                    }
                },
                columns: [
                    { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1 },
                    { data: 'date' },
                    { data: 'id_pesanan' },
                    { data: 'no_resi' },
                    { data: 'sku_summary', orderable: false },
                    { data: 'qc_progress', orderable: false, render: (data, type, row) => `${Number(data || 0)}% (${row.qc_status === 'completed' ? 'QC selesai' : 'Belum selesai QC'})` },
                    { data: 'qc_scanned_qty', className: 'text-end', orderable: false, render: (data, type, row) => `${Number(data || 0)} / ${Number(row.qc_required_qty || 0)}` },
                    { data: 'scan_out_status', render: (data) => data === 'done' ? 'Selesai' : 'Belum Scan Out' },
                ]
            });
        };

        const openStatus = (status, title) => {
            statusFilter = status || '';
            if (statusTitleEl) statusTitleEl.textContent = title || 'QC Transit';
            if (statusSearchEl) statusSearchEl.value = '';
            initStatusTable();
            dtStatus?.ajax?.reload();
            statusModal?.show();
        };

        document.querySelectorAll('.btn-picker-status').forEach((btn) => {
            btn.addEventListener('click', () => {
                openStatus(btn.getAttribute('data-status'), btn.getAttribute('data-title'));
            });
        });

        let statusTimer = null;
        statusSearchEl?.addEventListener('input', () => {
            if (statusTimer) clearTimeout(statusTimer);
            statusTimer = setTimeout(() => {
                dtStatus?.ajax?.reload();
            }, 300);
        });

        statusExportBtn?.addEventListener('click', () => {
            const params = new URLSearchParams();
            const q = (statusSearchEl?.value || '').trim();
            if (q) params.set('q', q);
            if (dateEl?.value) params.set('date', dateEl.value);
            if (statusFilter) params.set('status', statusFilter);
            const url = params.toString() ? `${exportPickerUrl}?${params.toString()}` : exportPickerUrl;
            window.location.href = url;
        });

        statusModalEl?.addEventListener('shown.bs.modal', () => {
            dtStatus?.columns?.adjust();
        });

        // Recalculate handler (admin-only button is optional)
        recalcBtn?.addEventListener('click', async () => {
            recalcBtn.disabled = true;
            const oldText = recalcBtn.textContent;
            recalcBtn.textContent = 'Memproses...';

            try {
                const response = await fetch(pickerTransitRecalcTodayUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({}),
                });
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal recalculate.');
                }

                reloadMain();
                dtStatus?.ajax?.reload();
            } catch (e) {
                alert(e.message || 'Gagal recalculate.');
            } finally {
                recalcBtn.disabled = false;
                recalcBtn.textContent = oldText || 'Recalculate Hari Ini';
            }
        });

        // Audit handler (admin-only button is optional)
        const setAuditLoading = (date) => {
            if (pickerTransitAuditSubtitleEl) pickerTransitAuditSubtitleEl.textContent = `Tanggal ${date || '-'}`;
            if (pickerTransitAuditBodyEl) {
                pickerTransitAuditBodyEl.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
        };

        const renderAuditRows = (rows) => {
            if (!pickerTransitAuditBodyEl) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                pickerTransitAuditBodyEl.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted py-6">Tidak ada remaining untuk tanggal ini.</td>
                    </tr>
                `;
                return;
            }

            pickerTransitAuditBodyEl.innerHTML = rows.map((r) => {
                const isExc = Number(r.is_exception || 0) > 0;
                const excBadge = isExc ? '<span class="badge badge-light-danger">Ya</span>' : '<span class="badge badge-light-success">Tidak</span>';
                return `
                    <tr>
                        <td>${r.sku || '-'}</td>
                        <td>${r.name || '-'}</td>
                        <td class="text-end">${Number(r.picked_qty || 0)}</td>
                        <td class="text-end"><span class="badge badge-light-warning">${Number(r.remaining_qty || 0)}</span></td>
                        <td>${excBadge}</td>
                        <td style="min-width: 360px;">${r.suspect || '-'}</td>
                    </tr>
                `;
            }).join('');
        };

        auditBtn?.addEventListener('click', async () => {
            const date = dateEl?.value || todayStr || '';
            if (!pickerTransitAuditModal || !pickerTransitAuditUrl) return;

            setAuditLoading(date);
            pickerTransitAuditModal.show();

            try {
                const params = new URLSearchParams({ date });
                const response = await fetch(`${pickerTransitAuditUrl}?${params.toString()}`);
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal memuat audit.');
                }
                renderAuditRows(payload?.data || []);
            } catch (e) {
                if (pickerTransitAuditBodyEl) {
                    pickerTransitAuditBodyEl.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-danger py-6">${e.message || 'Gagal memuat audit.'}</td>
                        </tr>
                    `;
                }
            }
        });

        // Detail resi handler
        const setPickerTransitDetailLoading = (label) => {
            if (pickerTransitDetailSubtitleEl) pickerTransitDetailSubtitleEl.textContent = label || '-';
            if (pickerTransitDetailTotalResiEl) pickerTransitDetailTotalResiEl.textContent = '0';
            if (pickerTransitDetailTotalQtyEl) pickerTransitDetailTotalQtyEl.textContent = '0';
            if (pickerTransitDetailQcScannedEl) pickerTransitDetailQcScannedEl.textContent = '0';
            if (pickerTransitDetailQcPendingEl) pickerTransitDetailQcPendingEl.textContent = '0';
            if (pickerTransitDetailSearchEl) pickerTransitDetailSearchEl.value = '';
            if (pickerTransitDetailBodyEl) {
                pickerTransitDetailBodyEl.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
        };

        const renderPickerTransitDetailRows = (rows) => {
            if (!pickerTransitDetailBodyEl) return;
            if (!Array.isArray(rows) || rows.length === 0) {
                pickerTransitDetailBodyEl.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted py-6">Tidak ada SKU yang cocok.</td>
                    </tr>
                `;
                return;
            }
            pickerTransitDetailBodyEl.innerHTML = rows.map((r, idx) => {
                const qcBadge = qcStatusBadge(r.qc_status);
                const pct = Number(r.progress || 0);
                const barClass = r.qc_status === 'completed' ? 'bg-success' : (pct > 0 ? 'bg-warning' : 'bg-secondary');
                return `
                <tr class="${r.qc_status === 'not_started' ? 'opacity-75' : ''}">
                    <td class="text-muted">${idx + 1}</td>
                    <td><span class="font-monospace fw-bold text-primary fs-7">${r.sku || '-'}</span></td>
                    <td><span class="fw-semibold text-gray-800">${r.name || '-'}</span></td>
                    <td class="text-end fw-semibold">${Number(r.scanned_qty || 0).toLocaleString('id-ID')} / ${Number(r.required_qty || 0).toLocaleString('id-ID')}</td>
                    <td class="text-center">
                        <div class="d-inline-flex align-items-center gap-2">
                            <div class="progress w-75px h-6px bg-light">
                                <div class="progress-bar ${barClass}" style="width:${pct}%"></div>
                            </div>
                            <span class="fs-9 fw-semibold">${pct}%</span>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold">${r.scanner_name || '-'}</div>
                    </td>
                    <td>${qcBadge}<div class="text-muted fs-9 mt-1">${r.completed_at && r.completed_at !== '-' ? r.completed_at : r.scanned_at}</div></td>
                </tr>`;
            }).join('');
        };

        const qcStatusBadge = (status) => {
            if (status === 'completed') {
                return `<span class="badge badge-light-success"><i class="bi bi-check-circle-fill me-1"></i>Selesai</span>`;
            }
            if (status === 'in_progress') {
                return `<span class="badge badge-light-warning"><i class="bi bi-hourglass-split me-1"></i>Proses QC</span>`;
            }
            return `<span class="badge badge-light"><i class="bi bi-dash-circle me-1"></i>Belum QC</span>`;
        };

        const loadPickerTransitDetail = async () => {
            if (!activeDetailQcResiId) return;
            const q = (pickerTransitDetailSearchEl?.value || '').trim();

            try {
                const params = new URLSearchParams({ qc_resi_id: activeDetailQcResiId });
                if (q) params.set('q', q);
                const response = await fetch(`${pickerTransitDetailUrl}?${params.toString()}`);
                const payload = await response.json();
                if (!response.ok) {
                    throw new Error(payload?.message || 'Gagal memuat detail SKU.');
                }

                const meta = payload?.meta || {};
                if (pickerTransitDetailSubtitleEl) {
                    pickerTransitDetailSubtitleEl.textContent = `${meta.no_resi || '-'} | ${meta.id_pesanan || '-'} | ${meta.date || '-'}`;
                }
                if (pickerTransitDetailTotalResiEl) pickerTransitDetailTotalResiEl.textContent = Number(meta.total_sku || 0).toLocaleString('id-ID');
                if (pickerTransitDetailTotalQtyEl) {
                    pickerTransitDetailTotalQtyEl.textContent = `${Number(meta.qc_scanned_qty || 0).toLocaleString('id-ID')} / ${Number(meta.qc_required_qty || 0).toLocaleString('id-ID')}`;
                }
                if (pickerTransitDetailQcScannedEl) pickerTransitDetailQcScannedEl.textContent = Number(meta.qc_scanned || 0).toLocaleString('id-ID');
                if (pickerTransitDetailQcPendingEl) pickerTransitDetailQcPendingEl.textContent = Number(meta.qc_in_progress || 0).toLocaleString('id-ID');
                renderPickerTransitDetailRows(payload?.data || []);
            } catch (e) {
                if (pickerTransitDetailBodyEl) {
                    pickerTransitDetailBodyEl.innerHTML = `
                        <tr>
                            <td colspan="7" class="text-center text-danger py-6">${e.message || 'Gagal memuat detail SKU.'}</td>
                        </tr>
                    `;
                }
            }
        };

        tableEl.on('click', '.btn-picker-transit-detail', async function() {
            const id = this.getAttribute('data-id') || '';
            const label = this.getAttribute('data-label') || '';
            if (!pickerTransitDetailModal || !id) return;

            activeDetailQcResiId = id;
            setPickerTransitDetailLoading(label);
            pickerTransitDetailModal.show();
            await loadPickerTransitDetail();
        });

        let detailSearchTimer = null;
        pickerTransitDetailSearchEl?.addEventListener('input', () => {
            if (detailSearchTimer) clearTimeout(detailSearchTimer);
            detailSearchTimer = setTimeout(loadPickerTransitDetail, 250);
        });
    });
</script>
@endpush
