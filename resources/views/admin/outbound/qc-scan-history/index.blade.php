@extends('layouts.admin')

@section('title', 'History QC Scan')
@section('page_title', 'History QC Scan')

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6 pb-4 flex-wrap gap-3">
        <div class="card-title flex-wrap gap-2">
            <div class="d-flex align-items-center position-relative my-1">
                <i class="bi bi-search position-absolute ms-3 text-gray-500 fs-5"></i>
                <input type="text" class="form-control form-control-solid w-225px ps-10" placeholder="Cari resi / SKU / petugas" id="qc_search" />
            </div>
            <input type="text" class="form-control form-control-solid w-135px" id="filter_date_from" placeholder="Dari" value="{{ $today ?? '' }}" />
            <input type="text" class="form-control form-control-solid w-135px" id="filter_date_to" placeholder="Sampai" value="{{ $today ?? '' }}" />
            <select id="filter_picker_user" class="form-select form-select-solid w-175px">
                <option value="">Semua Petugas</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <select id="filter_picker_status" class="form-select form-select-solid w-170px">
                <option value="">Semua Status QC</option>
                <option value="in_progress">Belum Lengkap</option>
                <option value="completed">Selesai</option>
            </select>
            <select id="filter_limit" class="form-select form-select-solid w-110px" aria-label="Jumlah data">
                <option value="10" selected>10 / hal</option>
                <option value="20">20 / hal</option>
                <option value="50">50 / hal</option>
                <option value="100">100 / hal</option>
                <option value="500">500 / hal</option>
            </select>
            <button type="button" class="btn btn-primary px-4" id="filter_apply">
                <i class="bi bi-funnel me-1"></i>Terapkan
            </button>
            <button type="button" class="btn btn-light px-4" id="filter_reset">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
            </button>
        </div>
    </div>

    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-4" id="qc_scan_history_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>No Resi</th>
                        <th>ID Pesanan</th>
                        <th>Petugas QC</th>
                        <th>Waktu Scan</th>
                        <th>SKU</th>
                        <th>Progress Qty</th>
                        <th>Status QC</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_qc_resi_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="qc_detail_title">Detail QC Resi</h5>
                    <div class="text-muted fs-7" id="qc_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 mb-5" id="qc_detail_pills"></div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h6 class="fw-bold text-gray-700 mb-0">
                        <i class="bi bi-box-seam me-1 text-primary"></i>Daftar SKU Resi
                    </h6>
                    <div class="d-flex align-items-center position-relative">
                        <i class="bi bi-search position-absolute ms-3 text-gray-500 fs-5"></i>
                        <input type="text" class="form-control form-control-solid w-250px ps-10" id="qc_detail_items_search" placeholder="Cari SKU / nama" />
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="8%">No</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end">Scan / Wajib</th>
                                <th>Status QC</th>
                            </tr>
                        </thead>
                        <tbody id="qc_detail_items_body">
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data.</td></tr>
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
    const dataUrl      = '{{ $dataUrl }}';
    const deleteUrlTpl = '{{ route('admin.outbound.qc-scan-history.destroy', ':id') }}';
    const csrfToken    = '{{ csrf_token() }}';
    const todayStr     = '{{ $today ?? '' }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#qc_scan_history_table');
        const searchInput = document.getElementById('qc_search');
        const userSelect = document.getElementById('filter_picker_user');
        const statusSelect = document.getElementById('filter_picker_status');
        const limitSelect = document.getElementById('filter_limit');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const detailModalEl = document.getElementById('modal_qc_resi_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const detailTitleEl = document.getElementById('qc_detail_title');
        const detailSubtitleEl = document.getElementById('qc_detail_subtitle');
        const detailPillsEl = document.getElementById('qc_detail_pills');
        const detailItemsBodyEl = document.getElementById('qc_detail_items_body');
        const detailItemsSearchEl = document.getElementById('qc_detail_items_search');
        const rowDataStore = {};
        let activeDetailRow = null;
        let searchTimer = null;
        let detailSearchTimer = null;

        const esc = v => String(v ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));

        const select2Safe = (el, placeholder) => {
            if (el && typeof $ !== 'undefined' && $.fn.select2) {
                $(el).select2({ placeholder, allowClear: true, width: '100%' })
                    .on('select2:opening select2:closing select2:close', e => e.stopPropagation());
            }
        };
        select2Safe(userSelect, 'Semua Petugas');
        select2Safe(statusSelect, 'Semua Status QC');

        let fpFrom = null;
        let fpTo = null;
        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            if (dateToEl) fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[3, 'desc']],
            pageLength: Number(limitSelect?.value || 10),
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data(params) {
                    params.q = searchInput?.value || '';
                    params.user_id = userSelect?.value || '';
                    params.status = statusSelect?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                },
            },
            columns: [
                {
                    data: 'no_resi',
                    render(data, type, row) {
                        rowDataStore[String(row.id)] = row;
                        return `<span class="fw-bold font-monospace text-primary fs-7">${esc(data) || '-'}</span>
                                <div class="text-muted fs-9">#${esc(String(row.id))}</div>`;
                    },
                },
                { data: 'id_pesanan', render: data => esc(data) || '-' },
                { data: 'picker', render: data => esc(data) || '-' },
                {
                    data: 'scanned_at',
                    render(data, type, row) {
                        const completed = row.completed_at ? `<div class="text-muted fs-9">Selesai: ${esc(row.completed_at)}</div>` : '';
                        return `<span class="fs-7">${esc(data) || '-'}</span>${completed}`;
                    },
                },
                {
                    data: 'items',
                    orderable: false,
                    searchable: false,
                    render(data, type, row) {
                        const count = Array.isArray(data) ? data.length : 0;
                        return `<button class="btn btn-sm btn-light-primary btn-detail-qc-resi px-3" data-id="${esc(String(row.id))}">
                                    <i class="bi bi-box-seam me-1"></i>${count} SKU
                                </button>
                                <div class="text-muted fs-9 mt-1 text-truncate" style="max-width:260px">${esc(row.item || '-')}</div>`;
                    },
                },
                {
                    data: 'qc_progress',
                    orderable: false,
                    render(data, type, row) {
                        const pct = Number(data || 0);
                        const scanned = Number(row.scanned_qty || 0).toLocaleString('id-ID');
                        const required = Number(row.required_qty || 0).toLocaleString('id-ID');
                        const barClass = pct >= 100 ? 'bg-success' : 'bg-warning';
                        return `<div class="d-flex align-items-center gap-2" style="min-width:180px">
                                    <div class="progress flex-grow-1 h-6px bg-light">
                                        <div class="progress-bar ${barClass}" style="width:${pct}%"></div>
                                    </div>
                                    <span class="fw-semibold fs-8">${pct}%</span>
                                </div>
                                <div class="text-muted fs-9">${scanned}/${required} qty</div>`;
                    },
                },
                { data: 'status', render: status => qcStatusBadge(status) },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render(data, type, row) {
                        if (row?.status !== 'in_progress' || Number(row?.scanned_qty || 0) > 0) return '-';
                        return `<button class="btn btn-sm btn-light-danger btn-delete" data-id="${esc(String(data))}">
                                    <i class="bi bi-trash me-1"></i>Hapus
                                </button>`;
                    },
                },
            ],
        });

        const reloadTable = () => dt.ajax.reload(null, false);
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadTable, 400);
        });
        applyBtn?.addEventListener('click', reloadTable);
        limitSelect?.addEventListener('change', () => {
            dt.page.len(Number(limitSelect.value || 10)).draw();
        });
        resetBtn?.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            setSelectValue(userSelect, '');
            setSelectValue(statusSelect, '');
            if (limitSelect) {
                limitSelect.value = '10';
                dt.page.len(10);
            }
            if (fpFrom && todayStr) fpFrom.setDate(todayStr, true);
            else if (dateFromEl) dateFromEl.value = todayStr || '';
            if (fpTo && todayStr) fpTo.setDate(todayStr, true);
            else if (dateToEl) dateToEl.value = todayStr || '';
            reloadTable();
        });

        tableEl.on('click', '.btn-detail-qc-resi', function() {
            const row = rowDataStore[this.getAttribute('data-id')];
            if (!row || !detailModal) return;
            activeDetailRow = row;
            if (detailItemsSearchEl) detailItemsSearchEl.value = '';
            renderDetail(row);
            detailModal.show();
        });

        detailItemsSearchEl?.addEventListener('input', () => {
            clearTimeout(detailSearchTimer);
            detailSearchTimer = setTimeout(() => renderDetailItems(activeDetailRow), 200);
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            const confirmed = await swalConfirm('Hapus resi QC?', 'Resi QC yang belum memiliki scan SKU akan dihapus.', 'Hapus');
            if (!confirmed) return;
            try {
                const { ok, json } = await apiAction({
                    url: deleteUrlTpl.replace(':id', id),
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                swalResult(ok, json?.message || (ok ? 'Resi QC berhasil dihapus' : 'Gagal menghapus resi QC'));
                if (ok) reloadTable();
            } catch {
                swalResult(false, 'Gagal menghapus resi QC');
            }
        });

        function renderDetail(row) {
            if (detailTitleEl) detailTitleEl.textContent = row.no_resi || '-';
            if (detailSubtitleEl) detailSubtitleEl.textContent = `${row.id_pesanan || '-'} - ${row.picker || '-'}`;
            if (detailPillsEl) {
                detailPillsEl.innerHTML = [
                    qcStatusBadge(row.status),
                    pill('bi-calendar', 'text-gray-600', 'Scan', esc(row.scanned_at || '-')),
                    pill('bi-person', 'text-info', 'Petugas', esc(row.picker || '-')),
                    pill('bi-box-seam', 'text-warning', 'Qty QC', `${row.scanned_qty ?? 0}/${row.required_qty ?? 0}`),
                    pill('bi-graph-up', 'text-success', 'Progress', `${row.qc_progress ?? 0}%`),
                ].join('');
            }
            renderDetailItems(row);
        }

        function renderDetailItems(row) {
            if (!detailItemsBodyEl) return;
            const keyword = (detailItemsSearchEl?.value || '').trim().toLowerCase();
            let items = Array.isArray(row?.items) ? row.items : [];
            if (keyword !== '') {
                items = items.filter(it => String(it.sku || '').toLowerCase().includes(keyword)
                    || String(it.name || '').toLowerCase().includes(keyword)
                    || String(it.status || '').toLowerCase().includes(keyword));
            }
            if (!items.length) {
                detailItemsBodyEl.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada SKU yang cocok.</td></tr>`;
                return;
            }
            detailItemsBodyEl.innerHTML = items.map((it, idx) => `
                <tr>
                    <td class="text-muted">${idx + 1}</td>
                    <td><span class="fw-bold font-monospace">${esc(it.sku)}</span></td>
                    <td>${esc(it.name)}</td>
                    <td class="text-end fw-semibold">${Number(it.scanned_qty || 0).toLocaleString('id-ID')} / ${Number(it.required_qty || 0).toLocaleString('id-ID')}</td>
                    <td>${qcStatusBadge(it.status)}</td>
                </tr>`).join('');
        }

        function setSelectValue(el, value) {
            if (!el) return;
            if (typeof $ !== 'undefined' && $(el).data('select2')) $(el).val(value).trigger('change.select2');
            else el.value = value;
        }

        function qcStatusBadge(status) {
            if (status === 'completed') {
                return '<span class="badge badge-light-success"><i class="bi bi-check-circle me-1"></i>Selesai</span>';
            }
            return '<span class="badge badge-light-warning"><i class="bi bi-hourglass-split me-1"></i>Belum Lengkap</span>';
        }

        function pill(icon, colorClass, label, value) {
            return `<span class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded bg-light">
                        <i class="bi ${esc(icon)} ${esc(colorClass)} fs-7"></i>
                        <span class="text-muted fs-8">${esc(label)}:</span>
                        <span class="fw-semibold fs-7">${value}</span>
                    </span>`;
        }

        async function apiAction({ url, method = 'POST', body = null }) {
            const opts = {
                method,
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            };
            if (body) {
                opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                opts.body = body;
            }
            const res = await fetch(url, opts);
            const text = await res.text();
            let json = null;
            try { json = JSON.parse(text); } catch {}
            return { ok: res.ok, status: res.status, json };
        }

        async function swalConfirm(title, text, confirmText) {
            if (typeof Swal === 'undefined') return window.confirm(`${title}\n${text}`);
            const res = await Swal.fire({
                title,
                text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmText,
                cancelButtonText: 'Batal',
                buttonsStyling: false,
                customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
            });
            return res.isConfirmed;
        }

        function swalResult(ok, msg) {
            if (typeof Swal !== 'undefined') Swal.fire(ok ? 'Berhasil' : 'Error', msg, ok ? 'success' : 'error');
            else alert(msg);
        }
    });
</script>
@endpush
