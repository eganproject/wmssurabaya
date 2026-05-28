@extends('layouts.admin')

@section('title', 'Stock Mutations')
@section('page_title', 'Stock Mutations')

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
            <div class="d-flex align-items-center gap-2">
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Dari" />
                <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Sampai" />
                <button type="button" class="btn btn-light" id="filter_apply">Filter</button>
                <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stock_mutations_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Tanggal</th>
                        <th>Item</th>
                        <th>Submit By</th>
                        <th>Arah</th>
                        <th>Qty</th>
                        <th>Sumber</th>
                        <th>Kode</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_mutation_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Detail Mutasi Stok</h2>
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
                        <div id="mutation_id">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Tanggal</div>
                        <div id="mutation_date">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">User</div>
                        <div id="mutation_user">-</div>
                    </div>
                </div>
                <div class="row mb-6">
                    <div class="col-md-6">
                        <div class="fw-bold text-gray-600">Item</div>
                        <div id="mutation_item">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="fw-bold text-gray-600">Arah</div>
                        <div id="mutation_direction">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="fw-bold text-gray-600">Qty</div>
                        <div id="mutation_qty">-</div>
                    </div>
                    <div class="col-md-2">
                        <div class="fw-bold text-gray-600">Sumber</div>
                        <div id="mutation_source">-</div>
                    </div>
                </div>
                <div class="row mb-6">
                    <div class="col-md-4">
                        <div class="fw-bold text-gray-600">Kode Sumber</div>
                        <div id="mutation_source_code">-</div>
                    </div>
                    <div class="col-md-8">
                        <div class="fw-bold text-gray-600">Catatan</div>
                        <div id="mutation_note">-</div>
                    </div>
                </div>

                <hr class="my-6" />

                <div class="fw-bolder fs-5 mb-4">Sumber Data</div>
                <div id="source_empty" class="text-muted">Data sumber tidak ditemukan.</div>
                <div id="source_section" style="display:none;">
                    <div class="row mb-6">
                        <div class="col-md-4">
                            <div class="fw-bold text-gray-600">Jenis</div>
                            <div id="source_label">-</div>
                        </div>
                        <div class="col-md-4">
                            <div class="fw-bold text-gray-600">Kode</div>
                            <div id="source_code">-</div>
                        </div>
                        <div class="col-md-4">
                            <div class="fw-bold text-gray-600">Ref</div>
                            <div id="source_ref">-</div>
                        </div>
                    </div>
                    <div class="row mb-6">
                        <div class="col-md-4">
                            <div class="fw-bold text-gray-600">Tanggal</div>
                            <div id="source_date">-</div>
                        </div>
                        <div class="col-md-8">
                            <div class="fw-bold text-gray-600">Catatan</div>
                            <div id="source_note">-</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-5">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Catatan</th>
                                </tr>
                            </thead>
                            <tbody id="source_items"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const dataUrl = '{{ route('admin.inventory.stock-mutations.data') }}';
    const detailUrlTpl = '{{ route('admin.inventory.stock-mutations.show', ':id') }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_mutations_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        let fpFrom = null;
        let fpTo = null;

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
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                { data: 'id' },
                { data: 'occurred_at' },
                { data: 'item' },
                { data: 'user' },
                { data: 'direction' },
                { data: 'qty' },
                { data: 'source' },
                { data: 'source_code' },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data) => {
                    const detailItem = `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-detail" data-id="${data}">Detail</a></div>`;
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
                                ${detailItem}
                            </div>
                        </div>
                    `;
                } },
            ]
        });

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };
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

        const modalEl = document.getElementById('modal_mutation_detail');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const setText = (id, value) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? '-';
        };

        tableEl.on('click', '.btn-detail', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            try {
                const res = await fetch(detailUrlTpl.replace(':id', id), { headers: { 'Accept': 'application/json' }});
                const json = await res.json();
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal memuat data', 'error');
                    return;
                }
                const m = json.mutation || {};
                setText('mutation_id', m.id);
                setText('mutation_date', m.occurred_at);
                setText('mutation_item', m.item);
                setText('mutation_direction', m.direction);
                setText('mutation_qty', m.qty);
                setText('mutation_source', m.source);
                setText('mutation_source_code', m.source_code);
                setText('mutation_note', m.note);
                setText('mutation_user', m.user);

                const source = json.source || null;
                const sourceEmpty = document.getElementById('source_empty');
                const sourceSection = document.getElementById('source_section');
                const sourceItemsBody = document.getElementById('source_items');
                if (source && sourceSection && sourceEmpty) {
                    sourceSection.style.display = '';
                    sourceEmpty.style.display = 'none';
                    setText('source_label', source.label);
                    setText('source_code', source.code);
                    setText('source_ref', source.ref);
                    setText('source_date', source.date);
                    setText('source_note', source.note);
                    const rows = (source.items || []).map(row => {
                        const meta = row.meta ? `<div class="text-muted fs-8">${row.meta}</div>` : '';
                        return `
                            <tr>
                                <td>${row.label || '-'}${meta}</td>
                                <td>${row.qty ?? '-'}</td>
                                <td>${row.note ?? '-'}</td>
                            </tr>
                        `;
                    }).join('');
                    if (sourceItemsBody) {
                        sourceItemsBody.innerHTML = rows || '<tr><td colspan="3" class="text-muted">Tidak ada item.</td></tr>';
                    }
                } else if (sourceEmpty && sourceSection) {
                    sourceSection.style.display = 'none';
                    sourceEmpty.style.display = '';
                }

                modal?.show();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal memuat data', 'error');
            }
        });
    });
</script>
@endpush
