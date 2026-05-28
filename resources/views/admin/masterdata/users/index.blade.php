@extends('layouts.admin')

@section('title', 'Masterdata - User')

@section('content')
@php use App\Support\Permission as Perm; @endphp
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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search users" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end" data-kt-user-table-toolbar="base">
                <button type="button" class="btn btn-light-primary me-3" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                    <span class="svg-icon svg-icon-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M19.0759 3H4.72777C3.95892 3 3.47768 3.83148 3.86067 4.49814L8.56967 12.6949C9.17923 13.7559 9.5 14.9582 9.5 16.1819V19.5072C9.5 20.2189 10.2223 20.7028 10.8805 20.432L13.8805 19.1977C14.2553 19.0435 14.5 18.6783 14.5 18.273V13.8372C14.5 12.8089 14.8171 11.8056 15.408 10.964L19.8943 4.57465C20.3596 3.912 19.8856 3 19.0759 3Z" fill="black" />
                        </svg>
                    </span>
                    Filter
                </button>
                <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true">
                    <div class="px-7 py-5">
                        <div class="fs-5 text-dark fw-bolder">Filter Options</div>
                    </div>
                    <div class="separator border-gray-200"></div>
                    <div class="px-7 py-5">
                        <div class="mb-10">
                            <label class="form-label fs-6 fw-bold">Role:</label>
                            <select id="filter_user_role" class="form-select form-select-solid fw-bolder" data-placeholder="Select option" data-allow-clear="true">
                                <option value="">Semua</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light btn-active-light-primary me-2" id="filter_users_reset">Reset</button>
                            <button type="button" class="btn btn-primary" id="filter_users_apply">Apply</button>
                        </div>
                    </div>
                </div>
                @if(Perm::can(auth()->user(), 'admin.masterdata.users.index', 'create'))
                    <button type="button" class="btn btn-light-primary me-3" id="btn_import_users" data-bs-toggle="modal" data-bs-target="#modal_import_users">
                        Import Excel
                    </button>
                    <a href="{{ route('admin.masterdata.users.create') }}" class="btn btn-primary">
                        Add User
                    </a>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="users_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Avatar</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Divisi</th>
                        <th>Roles</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_import_users" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Import Users (Excel)</h2>
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
                        <li><strong>Nama</strong> (wajib)</li>
                        <li><strong>Email</strong> (wajib)</li>
                        <li><strong>Password</strong> (wajib)</li>
                        <li><strong>Roles</strong> (opsional, pisahkan dengan koma)</li>
                        <li><strong>Divisi</strong> atau <strong>Divisi ID</strong> (opsional)</li>
                    </ul>
                    <p class="text-muted small mb-0">
                        Roles dapat diisi dengan <em>nama</em>, <em>slug</em>, atau <em>ID</em> role.
                    </p>
                </div>
                <div class="mb-10">
                    <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                    <input type="file" class="form-control form-control-solid" id="import_users_file" accept=".xlsx,.xls" />
                    <div class="invalid-feedback d-block" id="error_import_users_file"></div>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btn_import_users_submit">Import</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const csrfToken = '{{ csrf_token() }}';
    const dataUrl   = '{{ route('admin.masterdata.users.data') }}';
    const importUrl = '{{ route('admin.masterdata.users.import') }}';
    const editTpl   = '{{ route('admin.masterdata.users.edit', ':id') }}';
    const delTpl    = '{{ route('admin.masterdata.users.destroy', ':id') }}';
    const renderActionsDropdown = (items) => {
        if (!items.length) return '-';
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
                    ${items.join('')}
                </div>
            </div>
        `.trim();
    };

    document.addEventListener('DOMContentLoaded', function() {
        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };
        const tableEl = $('#users_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const applyBtn = document.getElementById('filter_users_apply');
        const resetBtn = document.getElementById('filter_users_reset');
        const roleSelect = document.getElementById('filter_user_role');
        const importBtn = document.getElementById('btn_import_users');
        const importInput = document.getElementById('import_users_file');
        const importError = document.getElementById('error_import_users_file');
        const importSubmit = document.getElementById('btn_import_users_submit');
        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables is not available or #users_table missing');
            return;
        }
        const dt = tableEl.DataTable({
            processing: true, serverSide: false, dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.role_id = roleSelect?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'avatar_url', orderable:false, searchable:false, render: (data)=>`<img src="${data}" alt="avatar" class="w-40px h-40px rounded-circle object-cover">` },
                { data: 'name' },
                { data: 'email' },
                { data: 'divisi' },
                { data: 'roles' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data)=>{
                    const editUrl = editTpl.replace(':id', data);
                    const delUrl  = delTpl.replace(':id', data);
                    const canUpdate = {{ \App\Support\Permission::can(auth()->user(), 'admin.masterdata.users.index', 'update') ? 'true' : 'false' }};
                    const canDelete = {{ \App\Support\Permission::can(auth()->user(), 'admin.masterdata.users.index', 'delete') ? 'true' : 'false' }};
                    const menuItems = [];
                    if (canUpdate) menuItems.push(`<div class="menu-item px-3"><a href="${editUrl}" class="menu-link px-3">Edit</a></div>`);
                    if (canDelete) menuItems.push(`<div class="menu-item px-3"><a href="#" data-url="${delUrl}" data-id="${data}" class="menu-link px-3 text-danger btn-delete">Hapus</a></div>`);
                    return renderActionsDropdown(menuItems);
                }}
            ]
        });
        refreshMenus();
        dt.on('draw', refreshMenus);

        if (searchInput) {
            searchInput.addEventListener('keyup', function () {
                dt.ajax.reload();
            });
        }

        applyBtn?.addEventListener('click', () => dt.ajax.reload());
        resetBtn?.addEventListener('click', () => {
            if (roleSelect) roleSelect.value = '';
            dt.ajax.reload();
        });

        $('#users_table').on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            const confirmed = await AppSwal.confirm('Yakin ingin menghapus User ini?', {
                confirmButtonText: 'Hapus'
            });
            if (!confirmed) return;
            fetch(url, { method:'POST', headers:{ 'X-CSRF-TOKEN': csrfToken }, body: new URLSearchParams({ _method:'DELETE' }) })
                .then(res => { if (res.ok) dt.ajax.reload(null, false); else AppSwal.error('Gagal menghapus user'); })
                .catch(()=> AppSwal.error('Gagal menghapus user'));
        });

        importBtn?.addEventListener('click', () => {
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
        });

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
                const res = await fetch(importUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const text = await res.text();
                let json = null;
                try { json = JSON.parse(text); } catch (err) { json = null; }

                if (!res.ok) {
                    const msg = json?.errors?.file?.[0] || json?.message || 'Gagal import';
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', msg, 'error');
                    } else if (importError) {
                        importError.textContent = msg;
                    }
                    return;
                }

                const successMsg = json?.message || 'Import user berhasil';
                if (typeof Swal !== 'undefined') {
                    const count = json?.created ? ` (berhasil ${json.created})` : '';
                    Swal.fire('Berhasil', successMsg + count, 'success');
                }

                if (importInput) importInput.value = '';
                const modalEl = document.getElementById('modal_import_users');
                if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();
                dt.ajax.reload();
            } catch (err) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Gagal import', 'error');
                } else if (importError) {
                    importError.textContent = 'Gagal import';
                }
            }
        });
    });
</script>
@endpush
