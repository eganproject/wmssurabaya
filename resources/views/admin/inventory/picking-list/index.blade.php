@extends('layouts.admin')

@section('title', 'Picking List')
@section('page_title', 'Picking List')

@section('content')
<div class="card mb-6">
    <div class="card-body py-3">
        <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-transparent fw-bold" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#picking_tab_all" type="button" role="tab">
                    <i class="fa-solid fa-list-check me-2"></i>Semua Picking List
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#picking_tab_batch" type="button" role="tab" id="picking_tab_batch_btn">
                    <i class="fa-solid fa-layer-group me-2"></i>Per Batch Import
                </button>
            </li>
        </ul>
    </div>
</div>

<div class="tab-content">
<div class="tab-pane fade show active" id="picking_tab_all" role="tabpanel">
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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search SKU / Nama" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date" placeholder="Tanggal" value="{{ $today ?? '' }}" />
                <select class="form-select form-select-solid w-175px" id="filter_status">
                    <option value="">Semua Status</option>
                    <option value="ongoing">Dalam Proses</option>
                    <option value="done">Selesai</option>
                </select>
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
                <button type="button" class="btn btn-light-warning" id="btn_recalculate_picking">Recalculate</button>
                <button type="button" class="btn btn-light-primary" id="btn_export_picking_list">Export Excel</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row g-4 mb-6">
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">SKU Dalam Proses</div>
                        <div class="fs-2 fw-bold" id="summary_ongoing">0</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">SKU Selesai</div>
                        <div class="fs-2 fw-bold" id="summary_done">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="picking_list_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Tanggal</th>
                        <th>SKU</th>
                        <th>Nama</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Remaining</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
</div>

<div class="tab-pane fade" id="picking_tab_batch" role="tabpanel">
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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search SKU / Nama" id="batch_filter_search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="text" class="form-control form-control-solid w-150px" id="batch_filter_date" placeholder="Tanggal" value="{{ $today ?? '' }}" />
                <select class="form-select form-select-solid w-175px" id="batch_filter_status">
                    <option value="">Semua Status</option>
                    <option value="ongoing">Dalam Proses</option>
                    <option value="done">Selesai</option>
                </select>
                <button type="button" class="btn btn-light" id="batch_filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="batch_filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4 mb-6">
            <i class="fa-solid fa-circle-info fs-2 text-primary me-3 mt-1"></i>
            <div class="fs-7 text-gray-700">
                Kebutuhan picking dipecah per batch import resi pada tanggal yang dipilih. Qty <b>Sudah Diambil</b> dibagikan ke batch
                sesuai urutan upload (batch paling awal dipenuhi lebih dulu), karena proses picking dicatat per SKU per tanggal.
                Total semua batch sama dengan tab <b>Semua Picking List</b>.
            </div>
        </div>
        <div class="row g-4 mb-6">
            <div class="col-4 col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Jumlah Batch</div>
                        <div class="fs-2 fw-bold" id="batch_summary_count">0</div>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Batch Dalam Proses</div>
                        <div class="fs-2 fw-bold text-warning" id="batch_summary_ongoing">0</div>
                    </div>
                </div>
            </div>
            <div class="col-4 col-md-3">
                <div class="card card-flush h-100">
                    <div class="card-body">
                        <div class="text-muted">Batch Selesai</div>
                        <div class="fs-2 fw-bold text-success" id="batch_summary_done">0</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end gap-3 mb-3" id="batch_toggle_all_wrap">
            <button type="button" class="btn btn-sm btn-light" id="batch_expand_all">Buka Semua</button>
            <button type="button" class="btn btn-sm btn-light" id="batch_collapse_all">Tutup Semua</button>
        </div>
        <div id="batch_list"></div>
    </div>
</div>
</div>
</div>

@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const exportUrl = '{{ route('admin.inventory.picking-list.export') }}';
    const recalcUrl = '{{ route('admin.inventory.picking-list.recalculate') }}';
    const todayStr = '{{ $today ?? '' }}';
    const csrfToken = '{{ csrf_token() }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#picking_list_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateEl = document.getElementById('filter_date');
        const statusEl = document.getElementById('filter_status');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const recalcBtn = document.getElementById('btn_recalculate_picking');
        const exportBtn = document.getElementById('btn_export_picking_list');
        const isSameDay = (value, compare) => {
            const a = (value || '').trim();
            const b = (compare || '').trim();
            return a !== '' && b !== '' && a === b;
        };
        const updateRecalcState = () => {
            if (!recalcBtn) return;
            const selected = (dateEl?.value || '').trim();
            recalcBtn.disabled = !isSameDay(selected, todayStr);
        };
        const summaryOngoingEl = document.getElementById('summary_ongoing');
        const summaryDoneEl = document.getElementById('summary_done');
        let fpDate = null;
        let dtList = null;

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof flatpickr !== 'undefined') {
            if (dateEl) {
                fpDate = flatpickr(dateEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
        }
        updateRecalcState();

        dtList = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: function(json) {
                    const summary = json?.summary || {};
                    if (summaryOngoingEl) summaryOngoingEl.textContent = summary.ongoing ?? 0;
                    if (summaryDoneEl) summaryDoneEl.textContent = summary.done ?? 0;
                    return json.data || [];
                },
                data: function(params) {
                    params.q = searchInput?.value || '';
                    if (dateEl?.value) params.date = dateEl.value;
                    if (statusEl?.value) params.status = statusEl.value;
                }
            },
            columns: [
                { data: 'date' },
                { data: 'sku' },
                { data: 'name' },
                { data: 'qty', className: 'text-end' },
                { data: 'remaining_qty', className: 'text-end' },
            ]
        });

        const reloadAll = () => {
            dtList?.ajax?.reload();
        };

        searchInput?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadAll();
        });
        filterApplyBtn?.addEventListener('click', reloadAll);
        dateEl?.addEventListener('change', updateRecalcState);
        statusEl?.addEventListener('change', reloadAll);
        filterResetBtn?.addEventListener('click', () => {
            if (fpDate && todayStr) {
                fpDate.setDate(todayStr, true);
            } else if (dateEl) {
                dateEl.value = todayStr || '';
            }
            if (searchInput) searchInput.value = '';
            if (statusEl) statusEl.value = '';
            updateRecalcState();
            reloadAll();
        });

        exportBtn?.addEventListener('click', () => {
            const params = new URLSearchParams();
            const q = (searchInput?.value || '').trim();
            if (q) params.set('q', q);
            if (dateEl?.value) params.set('date', dateEl.value);
            if (statusEl?.value) params.set('status', statusEl.value);
            const url = params.toString() ? `${exportUrl}?${params.toString()}` : exportUrl;
            window.location.href = url;
        });

        recalcBtn?.addEventListener('click', async () => {
            const listDate = (dateEl?.value || todayStr || '').trim();
            if (!listDate) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Tanggal picking belum dipilih.', 'error');
                }
                return;
            }
            if (!isSameDay(listDate, todayStr)) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Tidak diizinkan', 'Recalculate hanya bisa dilakukan untuk tanggal hari ini.', 'warning');
                }
                return;
            }

            const runRecalc = async () => {
                try {
                    recalcBtn.disabled = true;
                    const formData = new FormData();
                    formData.append('list_date', listDate);
                    const res = await fetch(recalcUrl, {
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
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Error', json?.message || 'Gagal melakukan rekalkulasi', 'error');
                        }
                        return;
                    }
                    if (typeof Swal !== 'undefined') {
                        const summary = json?.summary;
                        const info = summary
                            ? `Updated: ${summary.updated ?? 0}, Deleted: ${summary.deleted ?? 0}, Exceptions: ${summary.exceptions ?? 0}`
                            : '';
                        Swal.fire('Berhasil', `${json?.message || 'Rekalkulasi selesai.'} ${info}`.trim(), 'success');
                    }
                    reloadAll();
                } catch (err) {
                    console.error(err);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', 'Gagal melakukan rekalkulasi', 'error');
                    }
                } finally {
                    if (recalcBtn) recalcBtn.disabled = false;
                }
            };

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Recalculate Picking List?',
                    text: `Tanggal ${listDate} akan dihitung ulang berdasarkan resi dan transit.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, recalculation',
                    cancelButtonText: 'Batal',
                }).then((result) => {
                    if (result.isConfirmed) {
                        runRecalc();
                    }
                });
            } else {
                runRecalc();
            }
        });

        // ===== Per Batch Import =====
        const batchesUrl = '{{ $batchesUrl }}';
        const batchListEl = document.getElementById('batch_list');
        const batchSearchEl = document.getElementById('batch_filter_search');
        const batchDateEl = document.getElementById('batch_filter_date');
        const batchStatusEl = document.getElementById('batch_filter_status');
        const batchCountEl = document.getElementById('batch_summary_count');
        const batchOngoingEl = document.getElementById('batch_summary_ongoing');
        const batchDoneEl = document.getElementById('batch_summary_done');
        const batchToggleWrap = document.getElementById('batch_toggle_all_wrap');
        const openBatches = new Set();
        let batchLoaded = false;
        let batchRequest = 0;
        let fpBatchDate = null;

        if (typeof flatpickr !== 'undefined' && batchDateEl) {
            fpBatchDate = flatpickr(batchDateEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
        const fmt = (value) => Number(value || 0).toLocaleString('id-ID');
        const batchKey = (batch) => batch.id ? `b${batch.id}` : 'none';

        const renderBatchRows = (rows) => {
            if (!rows.length) {
                return '<div class="text-muted fs-7 py-4 text-center">Tidak ada SKU yang cocok dengan filter.</div>';
            }
            const body = rows.map((row) => {
                const done = row.remaining_qty <= 0;
                return `<tr>
                    <td class="fw-bold text-nowrap">${esc(row.sku)}</td>
                    <td>${esc(row.name)}</td>
                    <td class="text-end">${fmt(row.qty)}</td>
                    <td class="text-end">${fmt(row.picked_qty)}</td>
                    <td class="text-end fw-bold ${done ? 'text-success' : 'text-warning'}">${fmt(row.remaining_qty)}</td>
                    <td class="text-end">${done
                        ? '<span class="badge badge-light-success">Selesai</span>'
                        : '<span class="badge badge-light-warning">Dalam Proses</span>'}</td>
                </tr>`;
            }).join('');
            return `<div class="table-responsive">
                <table class="table align-middle table-row-dashed fs-6 gy-3 mb-0">
                    <thead>
                        <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                            <th>SKU</th>
                            <th>Nama</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Sudah Diambil</th>
                            <th class="text-end">Remaining</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>${body}</tbody>
                </table>
            </div>`;
        };

        const renderBatches = (batches) => {
            const filterActive = (batchSearchEl?.value || '').trim() !== '' || (batchStatusEl?.value || '') !== '';
            const visible = filterActive ? batches.filter((b) => b.rows.length > 0) : batches;
            const doneCount = batches.filter((b) => b.remaining_qty <= 0).length;
            if (batchCountEl) batchCountEl.textContent = batches.length;
            if (batchDoneEl) batchDoneEl.textContent = doneCount;
            if (batchOngoingEl) batchOngoingEl.textContent = batches.length - doneCount;
            if (batchToggleWrap) batchToggleWrap.classList.toggle('d-none', visible.length < 2);

            if (!visible.length) {
                batchListEl.innerHTML = `<div class="text-center text-muted py-10">
                    <i class="fa-solid fa-box-open fs-2x mb-3 d-block"></i>
                    ${batches.length ? 'Tidak ada batch yang cocok dengan filter.' : 'Belum ada resi yang diimport pada tanggal ini.'}
                </div>`;
                return;
            }

            // Open the first unfinished batch the first time, so there is always something to read.
            if (!openBatches.size) {
                const first = visible.find((b) => b.remaining_qty > 0) || visible[0];
                openBatches.add(batchKey(first));
            }
            if (filterActive && (batchSearchEl?.value || '').trim() !== '') {
                visible.forEach((b) => openBatches.add(batchKey(b)));
            }

            batchListEl.innerHTML = visible.map((batch) => {
                const key = batchKey(batch);
                const open = openBatches.has(key);
                const done = batch.remaining_qty <= 0;
                const percent = batch.total_qty > 0 ? Math.round((batch.picked_qty / batch.total_qty) * 100) : 0;
                const title = batch.id
                    ? `Batch #${batch.number} <span class="text-gray-500 fw-semibold fs-7 ms-2">${esc(batch.batch_code)}</span>`
                    : 'Tanpa Batch <span class="text-gray-500 fw-semibold fs-7 ms-2">resi lama sebelum fitur batch</span>';
                const meta = [
                    batch.uploaded_at ? `<span><i class="fa-regular fa-clock me-1"></i>${esc(batch.uploaded_at)}</span>` : '',
                    batch.file_name ? `<span class="text-truncate mw-250px" title="${esc(batch.file_name)}"><i class="fa-regular fa-file-excel me-1"></i>${esc(batch.file_name)}</span>` : '',
                    batch.uploaded_by ? `<span><i class="fa-regular fa-user me-1"></i>${esc(batch.uploaded_by)}</span>` : '',
                    `<span>${fmt(batch.total_resis)} resi</span>`,
                    `<span>${fmt(batch.sku_done)}/${fmt(batch.total_sku)} SKU selesai</span>`,
                ].filter(Boolean).join('<span class="text-gray-300">&bull;</span>');

                return `<div class="border border-gray-300 rounded mb-4" style="border-left: 4px solid ${done ? 'var(--bs-success, #50cd89)' : 'var(--bs-warning, #ffc700)'} !important;">
                    <div class="d-flex flex-wrap align-items-center gap-4 p-5 cursor-pointer" data-batch-toggle="${key}">
                        <i class="fa-solid ${open ? 'fa-chevron-down' : 'fa-chevron-right'} text-gray-500"></i>
                        <div class="flex-grow-1 min-w-200px">
                            <div class="fs-5 fw-bold text-gray-800">${title}</div>
                            <div class="d-flex flex-wrap gap-2 text-muted fs-7 mt-1">${meta}</div>
                        </div>
                        <div class="w-200px">
                            <div class="d-flex justify-content-between fs-7 mb-1">
                                <span class="text-muted">Diambil ${fmt(batch.picked_qty)} / ${fmt(batch.total_qty)}</span>
                                <span class="fw-bold">${percent}%</span>
                            </div>
                            <div class="progress h-6px bg-light">
                                <div class="progress-bar ${done ? 'bg-success' : 'bg-warning'}" style="width: ${percent}%"></div>
                            </div>
                        </div>
                        <div class="text-end min-w-100px">
                            ${done
                                ? '<span class="badge badge-light-success">Selesai</span>'
                                : `<span class="badge badge-light-warning">Sisa ${fmt(batch.remaining_qty)} pcs</span>`}
                        </div>
                    </div>
                    <div class="px-5 pb-4 ${open ? '' : 'd-none'}" data-batch-body="${key}">
                        ${renderBatchRows(batch.rows)}
                    </div>
                </div>`;
            }).join('');
        };

        const loadBatches = async () => {
            if (!batchListEl) return;
            const requestId = ++batchRequest;
            batchListEl.innerHTML = '<div class="text-center text-muted py-10"><span class="spinner-border spinner-border-sm me-2"></span>Memuat batch...</div>';
            const params = new URLSearchParams();
            const q = (batchSearchEl?.value || '').trim();
            if (q) params.set('q', q);
            if (batchDateEl?.value) params.set('date', batchDateEl.value);
            if (batchStatusEl?.value) params.set('status', batchStatusEl.value);
            try {
                const res = await fetch(`${batchesUrl}?${params.toString()}`, { headers: { 'Accept': 'application/json' } });
                const json = await res.json();
                if (requestId !== batchRequest) return;
                if (!res.ok) throw new Error(json?.message || 'Gagal memuat data');
                renderBatches(json.data || []);
                batchLoaded = true;
            } catch (err) {
                console.error(err);
                if (requestId !== batchRequest) return;
                batchListEl.innerHTML = '<div class="text-center text-danger py-10">Gagal memuat picking list per batch.</div>';
            }
        };

        batchListEl?.addEventListener('click', (e) => {
            const header = e.target.closest('[data-batch-toggle]');
            if (!header) return;
            const key = header.dataset.batchToggle;
            const body = batchListEl.querySelector(`[data-batch-body="${key}"]`);
            const icon = header.querySelector('i');
            const willOpen = body?.classList.contains('d-none');
            body?.classList.toggle('d-none', !willOpen);
            icon?.classList.toggle('fa-chevron-down', willOpen);
            icon?.classList.toggle('fa-chevron-right', !willOpen);
            if (willOpen) openBatches.add(key); else openBatches.delete(key);
        });
        const setAllBatches = (open) => {
            batchListEl?.querySelectorAll('[data-batch-toggle]').forEach((header) => {
                const key = header.dataset.batchToggle;
                batchListEl.querySelector(`[data-batch-body="${key}"]`)?.classList.toggle('d-none', !open);
                const icon = header.querySelector('i');
                icon?.classList.toggle('fa-chevron-down', open);
                icon?.classList.toggle('fa-chevron-right', !open);
                if (open) openBatches.add(key); else openBatches.delete(key);
            });
        };
        document.getElementById('batch_expand_all')?.addEventListener('click', () => setAllBatches(true));
        document.getElementById('batch_collapse_all')?.addEventListener('click', () => setAllBatches(false));

        const reloadBatches = () => {
            openBatches.clear();
            loadBatches();
        };
        document.getElementById('picking_tab_batch_btn')?.addEventListener('shown.bs.tab', () => {
            if (batchLoaded) return;
            // First visit: start from the date already chosen on the main tab.
            const mainDate = (dateEl?.value || '').trim();
            if (mainDate && batchDateEl) {
                if (fpBatchDate) fpBatchDate.setDate(mainDate, false); else batchDateEl.value = mainDate;
            }
            loadBatches();
        });
        document.getElementById('batch_filter_apply')?.addEventListener('click', reloadBatches);
        batchSearchEl?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') reloadBatches();
        });
        batchStatusEl?.addEventListener('change', reloadBatches);
        batchDateEl?.addEventListener('change', reloadBatches);
        document.getElementById('batch_filter_reset')?.addEventListener('click', () => {
            if (fpBatchDate && todayStr) {
                fpBatchDate.setDate(todayStr, false);
            } else if (batchDateEl) {
                batchDateEl.value = todayStr || '';
            }
            if (batchSearchEl) batchSearchEl.value = '';
            if (batchStatusEl) batchStatusEl.value = '';
            reloadBatches();
        });

    });
</script>
@endpush
