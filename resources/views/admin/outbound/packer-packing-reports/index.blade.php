@extends('layouts.admin')

@section('title', 'Laporan Packer (Packing)')
@section('page_title', 'Laporan Packer (Packing)')

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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Cari Packer / Tanggal" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" value="{{ $today ?? '' }}">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" value="{{ $today ?? '' }}">
                <select class="form-select form-select-solid w-200px" id="filter_packer">
                    <option value="">Semua Packer</option>
                    @foreach($packers as $packer)
                        <option value="{{ $packer->id }}">{{ $packer->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-light" id="filter_apply">Terapkan</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="card card-flush mb-6">
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="flex-grow-1">
                        <input type="text" class="form-control form-control-solid" id="resi_search_input" placeholder="Cari No Resi / ID Pesanan">
                    </div>
                    <button type="button" class="btn btn-primary" id="resi_search_btn">Cari Resi</button>
                </div>
                <div class="mt-4" id="resi_search_result" style="display:none;"></div>
            </div>
        </div>
        <div class="row g-4 mb-6">
            <div class="col-md-4">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Total Packing</div>
                        <div class="fs-2 fw-bold" id="summary_total_scan">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Total Packer</div>
                        <div class="fs-2 fw-bold" id="summary_total_packer">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Rata-rata Packing / Jam</div>
                        <div class="fs-2 fw-bold" id="summary_avg_hour">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="border border-dashed rounded-3 p-5 mb-8" id="comparison_card">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-4 mb-4">
                <div>
                    <div class="fs-4 fw-bold">Komparasi Import Resi vs Packing</div>
                    <div class="text-muted">Memastikan seluruh ID Pesanan / No Resi hasil import telah terpacking.</div>
                </div>
                <div class="text-muted">
                    Menampilkan maksimal 50 data resi yang belum terpacking.
                </div>
            </div>
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="bg-light-primary rounded-3 px-4 py-3 h-100">
                        <div class="text-muted">Total Import</div>
                        <div class="fs-2 fw-bold" id="comparison_import_total">0</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-light-success rounded-3 px-4 py-3 h-100">
                        <div class="text-muted">Sudah Packing</div>
                        <div class="fs-2 fw-bold" id="comparison_scanned_total">0</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="bg-light-warning rounded-3 px-4 py-3 h-100">
                        <div class="text-muted">Sisa Belum Packing</div>
                        <div class="fs-2 fw-bold" id="comparison_missing_total">0</div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead>
                        <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th width="35%">ID Pesanan</th>
                            <th width="35%">No Resi</th>
                            <th width="30%">Tanggal Upload</th>
                        </tr>
                    </thead>
                    <tbody id="missing_transit_body">
                        <tr>
                            <td colspan="3" class="text-center text-muted py-6" id="missing_transit_empty">
                                Tidak ada data tertinggal.
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-3" id="missing_pagination" style="display:none;">
                    <div class="text-muted" id="missing_page_summary"></div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-light" id="missing_prev">&larr; Sebelumnya</button>
                        <button type="button" class="btn btn-sm btn-light" id="missing_next">Berikutnya &rarr;</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="packer_report_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th width="5%">No</th>
                        <th>Tanggal</th>
                        <th>Packer</th>
                        <th class="text-end">Total Packing</th>
                        <th class="text-end">Unik Resi</th>
                        <th class="text-end">Avg / Jam</th>
                        <th>Scan Pertama</th>
                        <th>Scan Terakhir</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_packer_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Packing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-4">
                    <div class="fw-bold" id="detail_title">-</div>
                    <div class="text-muted" id="detail_subtitle">-</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="25%">ID Pesanan</th>
                                <th width="25%">No Resi</th>
                                <th width="15%">Scan Type</th>
                                <th width="20%">Scan Code</th>
                                <th width="15%">Waktu Scan</th>
                            </tr>
                        </thead>
                        <tbody id="detail_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-6">Belum ada data.</td>
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
    const detailUrl = '{{ route('admin.reports.packer-packing-reports.detail') }}';
    const searchResiUrl = '{{ route('admin.reports.packer-packing-reports.search-resi') }}';
    const todayStr = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#packer_report_table');
        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTable unavailable');
            return;
        }

        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const packerSelect = document.getElementById('filter_packer');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const summaryTotalScan = document.getElementById('summary_total_scan');
        const summaryTotalPacker = document.getElementById('summary_total_packer');
        const summaryAvgHour = document.getElementById('summary_avg_hour');
        const comparisonImportEl = document.getElementById('comparison_import_total');
        const comparisonScannedEl = document.getElementById('comparison_scanned_total');
        const comparisonMissingEl = document.getElementById('comparison_missing_total');
        const missingBody = document.getElementById('missing_transit_body');
        const missingPagination = document.getElementById('missing_pagination');
        const missingPrevBtn = document.getElementById('missing_prev');
        const missingNextBtn = document.getElementById('missing_next');
        const missingSummary = document.getElementById('missing_page_summary');
        const resiSearchInput = document.getElementById('resi_search_input');
        const resiSearchBtn = document.getElementById('resi_search_btn');
        const resiSearchResult = document.getElementById('resi_search_result');
        const detailModalEl = document.getElementById('modal_packer_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const detailTitle = document.getElementById('detail_title');
        const detailSubtitle = document.getElementById('detail_subtitle');
        const detailBody = document.getElementById('detail_body');
        const comparisonState = {
            samples: [],
            page: 1,
            perPage: 10,
        };
        let fpFrom = null;
        let fpTo = null;

        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
        }

        const updateSummary = (data) => {
            const rows = Array.isArray(data) ? data : [];
            const totalScan = rows.reduce((sum, row) => sum + (Number(row.total_scan) || 0), 0);
            const avgHour = rows.length
                ? (rows.reduce((sum, row) => sum + (Number(row.avg_per_hour) || 0), 0) / rows.length)
                : 0;
            const totalPacker = new Set(rows.map((row) => row.packer)).size;
            if (summaryTotalScan) summaryTotalScan.textContent = totalScan.toLocaleString('id-ID');
            if (summaryTotalPacker) summaryTotalPacker.textContent = totalPacker.toString();
            if (summaryAvgHour) summaryAvgHour.textContent = avgHour.toFixed(2);
        };

        const renderMissingRows = () => {
            const samples = comparisonState.samples || [];
            const total = samples.length;
            const perPage = comparisonState.perPage;
            const maxPage = Math.max(1, Math.ceil(total / perPage));
            comparisonState.page = Math.min(Math.max(1, comparisonState.page), maxPage);
            const start = (comparisonState.page - 1) * perPage;
            const paginated = samples.slice(start, start + perPage);

            if (!paginated.length) {
                missingBody.innerHTML = `
                    <tr>
                        <td colspan="3" class="text-center text-muted py-6">Tidak ada data tertinggal.</td>
                    </tr>
                `;
            } else {
                missingBody.innerHTML = paginated.map((sample) => `
                    <tr>
                        <td>${sample.id_pesanan || '-'}</td>
                        <td>${sample.no_resi || '-'}</td>
                        <td>${sample.tanggal_upload || '-'}</td>
                    </tr>
                `).join('');
            }

            if (missingPagination) {
                if (total <= perPage) {
                    missingPagination.style.display = 'none';
                } else {
                    missingPagination.style.display = 'flex';
                }
            }

            if (missingSummary) {
                missingSummary.textContent = `Halaman ${comparisonState.page} dari ${maxPage}`;
            }

            if (missingPrevBtn) {
                missingPrevBtn.disabled = comparisonState.page <= 1;
            }
            if (missingNextBtn) {
                missingNextBtn.disabled = comparisonState.page >= maxPage;
            }
        };

        const updateComparison = (comparison) => {
            if (!comparison) {
                return;
            }
            if (comparisonImportEl) comparisonImportEl.textContent = (comparison.import_total || 0).toLocaleString('id-ID');
            if (comparisonScannedEl) comparisonScannedEl.textContent = (comparison.scanned_total || 0).toLocaleString('id-ID');
            if (comparisonMissingEl) comparisonMissingEl.textContent = (comparison.missing_total || 0).toLocaleString('id-ID');
            comparisonState.samples = Array.isArray(comparison.missing_samples) ? comparison.missing_samples : [];
            comparisonState.page = 1;
            renderMissingRows();
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: false,
            searching: false,
            ordering: false,
            dom: 'rtip',
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function (params) {
                    params.q = searchInput?.value || '';
                    params.date_from = dateFromEl?.value || '';
                    params.date_to = dateToEl?.value || '';
                    params.packer_id = packerSelect?.value || '';
                }
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                { data: 'date' },
                { data: 'packer' },
                { data: 'total_scan', className: 'text-end' },
                { data: 'unique_scan', className: 'text-end' },
                { data: 'avg_per_hour', className: 'text-end' },
                { data: 'first_scan' },
                { data: 'last_scan' },
                { data: null, className: 'text-end', render: (data, type, row) => {
                    const packerId = row.packer_id || '';
                    const date = row.date || '';
                    return `<button type="button" class="btn btn-sm btn-light-primary btn-detail" data-id="${packerId}" data-date="${date}" data-name="${row.packer || ''}">Detail</button>`;
                }},
            ],
            language: {
                emptyTable: 'Belum ada data',
                processing: 'Memuat...',
            },
        });

        tableEl.on('xhr.dt', function () {
            const json = dt?.ajax?.json?.();
            if (json?.data) {
                updateSummary(json.data);
            }
            if (json?.comparison) {
                updateComparison(json.comparison);
            }
        });

        const reloadTable = () => dt.ajax.reload();

        applyBtn?.addEventListener('click', reloadTable);
        resetBtn?.addEventListener('click', () => {
            if (dateFromEl && todayStr) dateFromEl.value = todayStr;
            if (dateToEl && todayStr) dateToEl.value = todayStr;
            if (fpFrom) fpFrom.setDate(todayStr);
            if (fpTo) fpTo.setDate(todayStr);
            if (packerSelect) packerSelect.value = '';
            if (searchInput) searchInput.value = '';
            reloadTable();
        });

        searchInput?.addEventListener('keyup', () => {
            reloadTable();
        });

        tableEl.on('click', '.btn-detail', async function () {
            const packerId = this.getAttribute('data-id');
            const date = this.getAttribute('data-date');
            const packerName = this.getAttribute('data-name') || '-';
            if (!packerId || !date) {
                return;
            }
            if (detailTitle) detailTitle.textContent = `Packer: ${packerName}`;
            if (detailSubtitle) detailSubtitle.textContent = `Tanggal: ${date}`;
            if (detailBody) {
                detailBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-muted py-6">Memuat data...</td>
                    </tr>
                `;
            }
            detailModal?.show();
            try {
                const params = new URLSearchParams({ packer_id: packerId, date });
                const res = await fetch(`${detailUrl}?${params.toString()}`);
                const json = await res.json();
                const rows = Array.isArray(json?.data) ? json.data : [];
                if (!rows.length) {
                    detailBody.innerHTML = `
                        <tr>
                            <td colspan="5" class="text-center text-muted py-6">Tidak ada data.</td>
                        </tr>
                    `;
                    return;
                }
                detailBody.innerHTML = rows.map((row) => `
                    <tr>
                        <td>${row.id_pesanan || '-'}</td>
                        <td>${row.no_resi || '-'}</td>
                        <td>${row.scan_type || '-'}</td>
                        <td>${row.scan_code || '-'}</td>
                        <td>${row.scanned_at || '-'}</td>
                    </tr>
                `).join('');
            } catch (err) {
                detailBody.innerHTML = `
                    <tr>
                        <td colspan="5" class="text-center text-danger py-6">Gagal memuat detail.</td>
                    </tr>
                `;
            }
        });

        const renderResiResult = (payload, isError = false) => {
            if (!resiSearchResult) return;
            resiSearchResult.style.display = 'block';
            if (isError) {
                resiSearchResult.innerHTML = `<div class="alert alert-danger mb-0">${payload}</div>`;
                return;
            }
            if (!payload || typeof payload === 'string') {
                resiSearchResult.innerHTML = `<div class="alert alert-info mb-0">${payload || 'Memuat data...'}</div>`;
                return;
            }
            resiSearchResult.innerHTML = `
                <div class="alert alert-success mb-0">
                    <div><strong>Resi:</strong> ${payload.no_resi || '-'}</div>
                    <div><strong>ID Pesanan:</strong> ${payload.id_pesanan || '-'}</div>
                    <div><strong>Packer:</strong> ${payload.packer || '-'}</div>
                    <div><strong>Tanggal Scan:</strong> ${payload.scan_date || '-'}</div>
                    <div><strong>Waktu Scan:</strong> ${payload.scanned_at || '-'}</div>
                </div>
            `;
        };

        const handleResiSearch = async () => {
            const keyword = resiSearchInput?.value?.trim() || '';
            if (!keyword) {
                renderResiResult('Nomor resi atau ID pesanan wajib diisi.', true);
                return;
            }
            renderResiResult('Memuat data...', false);
            try {
                const params = new URLSearchParams({ q: keyword });
                const res = await fetch(`${searchResiUrl}?${params.toString()}`);
                const json = await res.json();
                if (!res.ok) {
                    renderResiResult(json?.message || 'Resi tidak ditemukan.', true);
                    return;
                }
                renderResiResult(json?.data || {}, false);
            } catch (err) {
                renderResiResult('Gagal memuat data resi.', true);
            }
        };

        resiSearchBtn?.addEventListener('click', handleResiSearch);
        resiSearchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                handleResiSearch();
            }
        });

        missingPrevBtn?.addEventListener('click', () => {
            comparisonState.page = Math.max(1, comparisonState.page - 1);
            renderMissingRows();
        });
        missingNextBtn?.addEventListener('click', () => {
            comparisonState.page += 1;
            renderMissingRows();
        });
    });
</script>
@endpush
