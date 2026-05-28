@extends('layouts.admin')

@section('title', 'SKU Exception Scan Out')
@section('page_title', 'SKU Exception Scan Out')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.outbound.packer-scan-exceptions.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.outbound.packer-scan-exceptions.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.outbound.packer-scan-exceptions.index', 'delete');
@endphp

@section('content')
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex align-items-center position-relative my-1">
                <span class="svg-icon svg-icon-1 position-absolute ms-6">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                        <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17.53333 14.4667 17.53333 11C17.53333 7.53333 14.4667 5 11 5Z" fill="black" />
                    </svg>
                </span>
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search SKU / Catatan" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end">
                @if($canCreate)
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_exception_form" id="btn_open_create">
                        Tambah SKU Exception
                    </button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="exception_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>SKU</th>
                        <th>Catatan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_exception_form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="modal_exception_title">Tambah SKU Exception</h2>
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
                <form class="form" id="exception_form">
                    @csrf
                    <input type="hidden" name="exception_id" id="exception_id" />
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">SKU</label>
                        <input type="text" class="form-control form-control-solid" name="sku" id="exception_sku" required />
                        <div class="invalid-feedback" id="error_sku"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Catatan</label>
                        <input type="text" class="form-control form-control-solid" name="note" id="exception_note" />
                        <div class="invalid-feedback" id="error_note"></div>
                    </div>
                    <div class="text-end pt-3">
                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">
                            <span class="indicator-label">Simpan</span>
                            <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const csrfToken = '{{ csrf_token() }}';
    const dataUrl   = '{{ route('admin.outbound.packer-scan-exceptions.data') }}';
    const storeUrl  = '{{ route('admin.outbound.packer-scan-exceptions.store') }}';
    const updateTpl = '{{ route('admin.outbound.packer-scan-exceptions.update', ':id') }}';
    const deleteTpl = '{{ route('admin.outbound.packer-scan-exceptions.destroy', ':id') }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#exception_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const form = document.getElementById('exception_form');
        const modalEl = document.getElementById('modal_exception_form');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const formSku = document.getElementById('exception_sku');
        const formNote = document.getElementById('exception_note');
        const formId = document.getElementById('exception_id');
        const titleEl = document.getElementById('modal_exception_title');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const refreshMenus = () => {
            if (window.KTMenu) {
                KTMenu.createInstances();
            }
        };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[1, 'asc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'sku' },
                { data: 'note' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row)=>{
                    const editItem = canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}" data-sku="${row.sku}" data-note="${row.note}">Edit</a></div>` : '';
                    const delItem = canDelete ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${data}">Hapus</a></div>` : '';
                    const actions = `${editItem}${delItem}`;
                    if (!actions) return '';
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
                                ${actions}
                            </div>
                        </div>
                    `;
                }}
            ]
        });
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = () => dt.ajax.reload();
        searchInput?.addEventListener('keyup', reloadTable);

        document.getElementById('btn_open_create')?.addEventListener('click', () => {
            if (!form) return;
            form.reset();
            formId.value = '';
            clearErrors();
            if (titleEl) titleEl.textContent = 'Tambah SKU Exception';
        });

        const clearErrors = () => {
            const errSku = document.getElementById('error_sku');
            const errNote = document.getElementById('error_note');
            if (errSku) errSku.textContent = '';
            if (errNote) errNote.textContent = '';
        };

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            const id = formId.value;
            const url = id ? updateTpl.replace(':id', id) : storeUrl;
            const method = id ? 'PUT' : 'POST';
            const formData = new FormData(form);
            if (id) formData.append('_method', 'PUT');
            try {
                const res = await fetch(url, {
                    method: method === 'PUT' ? 'POST' : method,
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (parseErr) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (json?.errors) {
                        Object.entries(json.errors).forEach(([key, msgs])=>{
                            const errEl = document.getElementById(`error_${key}`);
                            if (errEl) errEl.textContent = msgs.join(', ');
                        });
                    } else if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', json.message || 'Gagal menyimpan data', 'error');
                    }
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable();
            } catch (err) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan data', 'error');
            }
        });

        tableEl.on('click', '.btn-edit', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const sku = this.getAttribute('data-sku');
            const note = this.getAttribute('data-note');
            if (!form) return;
            formId.value = id;
            formSku.value = sku || '';
            formNote.value = note && note !== '-' ? note : '';
            clearErrors();
            if (titleEl) titleEl.textContent = 'Edit SKU Exception';
            modal?.show();
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            if (!id) return;
            if (typeof Swal === 'undefined') return;
            const confirm = await Swal.fire({
                title: 'Hapus SKU Exception?',
                text: 'Data yang dihapus tidak bisa dikembalikan.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal',
            });
            if (!confirm.isConfirmed) return;
            try {
                const res = await fetch(deleteTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    Swal.fire('Error', json.message || 'Gagal menghapus data', 'error');
                    return;
                }
                Swal.fire('Berhasil', json.message || 'Data dihapus', 'success');
                reloadTable();
            } catch (err) {
                Swal.fire('Error', 'Gagal menghapus data', 'error');
            }
        });
    });
</script>
@endpush
