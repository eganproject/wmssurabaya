@extends('layouts.admin')

@section('title', 'Aktivitas User')
@section('page_title', 'Aktivitas User')

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
        <div class="card-toolbar flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" />
                <button type="button" class="btn btn-light" id="filter_date_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_date_reset">Reset</button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select id="filter_user" class="form-select form-select-solid fw-bolder" data-control="select2" data-placeholder="User" data-allow-clear="true">
                    <option value="">Semua User</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                <select id="filter_method" class="form-select form-select-solid fw-bolder" data-control="select2" data-placeholder="Method" data-allow-clear="true">
                    <option value="">Semua Method</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="PATCH">PATCH</option>
                    <option value="DELETE">DELETE</option>
                </select>
                <button type="button" class="btn btn-light" id="filter_apply">Apply</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="activity_logs_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aktivitas</th>
                        <th>Method</th>
                        <th>IP</th>
                        <th>URL</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_activity_detail" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Detail Aktivitas</h2>
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
                <div class="row mb-6">
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">ID</div>
                        <div id="activity_id">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Waktu</div>
                        <div id="activity_time">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">User</div>
                        <div id="activity_user">-</div>
                    </div>
                </div>
                <div class="row mb-6">
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Email</div>
                        <div id="activity_email">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Method</div>
                        <div id="activity_method">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">IP</div>
                        <div id="activity_ip">-</div>
                    </div>
                </div>
                <div class="row mb-6">
                    <div class="col-md-6">
                        <div class="fw-bold text-gray-600">Route</div>
                        <div id="activity_route">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="fw-bold text-gray-600">URL</div>
                        <div id="activity_url">-</div>
                    </div>
                </div>
                <div class="row mb-6">
                    <div class="col-md-12">
                        <div class="fw-bold text-gray-600">Aktivitas</div>
                        <div id="activity_action">-</div>
                    </div>
                </div>
                <div class="row mb-6" id="activity_context_section" style="display:none">
                    <div class="col-md-12">
                        <div class="fw-bold text-gray-600 mb-2">Rincian Item</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle fs-7 mb-0">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bold text-uppercase gs-0 bg-light">
                                        <th class="ps-3">SKU</th>
                                        <th>Nama</th>
                                        <th class="text-end pe-3">Qty</th>
                                    </tr>
                                </thead>
                                <tbody id="activity_context_tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="row mb-6">
                    <div class="col-md-12">
                        <div class="fw-bold text-gray-600">User Agent</div>
                        <div id="activity_agent" class="text-gray-700">-</div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="fw-bold text-gray-600">Payload</div>
                        <pre class="bg-light p-4 rounded" id="activity_payload" style="white-space: pre-wrap;">-</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ $dataUrl }}';
    const detailUrlTpl = '{{ $detailUrl }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#activity_logs_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const userSelect = document.getElementById('filter_user');
        const methodSelect = document.getElementById('filter_method');
        const applyBtn = document.getElementById('filter_apply');
        const resetBtn = document.getElementById('filter_reset');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const dateApplyBtn = document.getElementById('filter_date_apply');
        const dateResetBtn = document.getElementById('filter_date_reset');
        let fpFrom = null;
        let fpTo = null;

        const select2Safe = (el, placeholder) => {
            if (el && typeof $ !== 'undefined' && $.fn.select2) {
                $(el).select2({ placeholder, allowClear: true, width: '200px' })
                    .on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
            }
        };

        select2Safe(userSelect, 'Semua User');
        select2Safe(methodSelect, 'Semua Method');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        if (typeof flatpickr !== 'undefined') {
            if (dateFromEl) {
                fpFrom = flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
            if (dateToEl) {
                fpTo = flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true });
            }
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[1, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.user_id = userSelect?.value || '';
                    params.method = methodSelect?.value || '';
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'created_at' },
                { data: 'user' },
                { data: 'action' },
                { data: 'method' },
                { data: 'ip' },
                { data: 'url' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data) => {
                    return `<button type="button" class="btn btn-sm btn-light btn-detail" data-id="${data}">Detail</button>`;
                }},
            ]
        });

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);
        applyBtn?.addEventListener('click', reloadTable);
        resetBtn?.addEventListener('click', () => {
            if (userSelect) userSelect.value = '';
            if (methodSelect) methodSelect.value = '';
            if (typeof $ !== 'undefined' && $(userSelect).data('select2')) {
                $(userSelect).val('').trigger('change.select2');
            }
            if (typeof $ !== 'undefined' && $(methodSelect).data('select2')) {
                $(methodSelect).val('').trigger('change.select2');
            }
            reloadTable();
        });
        dateApplyBtn?.addEventListener('click', reloadTable);
        dateResetBtn?.addEventListener('click', () => {
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            reloadTable();
        });

        tableEl.on('click', '.btn-detail', async function() {
            const id = this.getAttribute('data-id');
            if (!id) return;
            const url = detailUrlTpl.replace(':id', id);
            try {
                const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) {
                    throw new Error('Gagal memuat detail aktivitas');
                }
                const data = await response.json();
                document.getElementById('activity_id').textContent = data.id ?? '-';
                document.getElementById('activity_time').textContent = data.created_at ?? '-';
                document.getElementById('activity_user').textContent = data.user ?? '-';
                document.getElementById('activity_email').textContent = data.email ?? '-';
                document.getElementById('activity_method').textContent = data.method ?? '-';
                document.getElementById('activity_ip').textContent = data.ip ?? '-';
                document.getElementById('activity_route').textContent = data.route_name ?? '-';
                document.getElementById('activity_url').textContent = data.url ?? '-';
                document.getElementById('activity_action').textContent = data.action ?? '-';
                document.getElementById('activity_agent').textContent = data.user_agent ?? '-';
                const payload = data.payload ? JSON.stringify(data.payload, null, 2) : '-';
                document.getElementById('activity_payload').textContent = payload;

                const contextSection = document.getElementById('activity_context_section');
                const contextTbody = document.getElementById('activity_context_tbody');
                const contextItems = data.context?.items;
                if (contextItems && contextItems.length > 0) {
                    contextTbody.innerHTML = '';
                    contextItems.forEach(item => {
                        const tr = document.createElement('tr');
                        const tdSku = document.createElement('td');
                        tdSku.className = 'ps-3 fw-bold';
                        tdSku.textContent = item.sku ?? '-';
                        const tdName = document.createElement('td');
                        tdName.textContent = item.name ?? '-';
                        const tdQty = document.createElement('td');
                        tdQty.className = 'text-end pe-3';
                        tdQty.textContent = item.qty ?? 0;
                        tr.append(tdSku, tdName, tdQty);
                        contextTbody.appendChild(tr);
                    });
                    contextSection.style.display = '';
                } else {
                    contextSection.style.display = 'none';
                    contextTbody.innerHTML = '';
                }

                const modalEl = document.getElementById('modal_activity_detail');
                if (modalEl) {
                    const modal = new bootstrap.Modal(modalEl);
                    modal.show();
                }
            } catch (error) {
                console.error(error);
            }
        });
    });
</script>
@endpush
