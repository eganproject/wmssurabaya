@extends('layouts.admin')

@section('title', 'Masterdata - Roles')
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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search roles" data-kt-filter="search" />
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
                            <label class="form-label fs-6 fw-bold">User Count:</label>
                            <select id="filter_role_user_count" class="form-select form-select-solid fw-bolder" data-placeholder="Select option" data-allow-clear="true">
                                <option value="">Semua</option>
                                <option value="0">No Users</option>
                                <option value="1-5">1 - 5 Users</option>
                                <option value="6-10">6 - 10 Users</option>
                                <option value="11-plus">11+ Users</option>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light btn-active-light-primary me-2" id="filter_roles_reset">Reset</button>
                            <button type="button" class="btn btn-primary" id="filter_roles_apply">Apply</button>
                        </div>
                    </div>
                </div>
                @if(Perm::can(auth()->user(), 'admin.masterdata.roles.index', 'create'))
                    <a href="{{ route('admin.masterdata.roles.create') }}" class="btn btn-primary">
                        Add Role
                    </a>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="roles_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Slug</th>
                        <th>Jumlah User</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const csrfToken = '{{ csrf_token() }}';
    const dataUrl   = '{{ route('admin.masterdata.roles.data') }}';
    const editTpl   = '{{ route('admin.masterdata.roles.edit', ':id') }}';
    const delTpl    = '{{ route('admin.masterdata.roles.destroy', ':id') }}';
    const canUpdate = {{ \App\Support\Permission::can(auth()->user(), 'admin.masterdata.roles.index', 'update') ? 'true' : 'false' }};
    const canDelete = {{ \App\Support\Permission::can(auth()->user(), 'admin.masterdata.roles.index', 'delete') ? 'true' : 'false' }};
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
        const tableEl = $('#roles_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const applyBtn = document.getElementById('filter_roles_apply');
        const resetBtn = document.getElementById('filter_roles_reset');
        const userCount = document.getElementById('filter_role_user_count');
        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables is not available or #roles_table missing');
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
                    params.user_count = userCount?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'name' },
                { data: 'slug' },
                { data: 'users_count' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data)=>{
                    const editUrl = editTpl.replace(':id', data);
                    const delUrl  = delTpl.replace(':id', data);
                    const permUrl = `{{ route('admin.masterdata.permissions.edit', ':id') }}`.replace(':id', data);
                    const menuItems = [
                        `<div class="menu-item px-3"><a href="${permUrl}" class="menu-link px-3">Permission</a></div>`
                    ];
                    if (canUpdate) menuItems.unshift(`<div class="menu-item px-3"><a href="${editUrl}" class="menu-link px-3">Edit</a></div>`);
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
            if (userCount) userCount.value = '';
            dt.ajax.reload();
        });

        $('#roles_table').on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const url = this.getAttribute('data-url');
            const confirmed = await AppSwal.confirm('Yakin ingin menghapus Role ini?', {
                confirmButtonText: 'Hapus',
                confirmButtonType: 'danger'
            });
            if (!confirmed) return;
            fetch(url, { method:'POST', headers:{ 'X-CSRF-TOKEN': csrfToken }, body: new URLSearchParams({ _method:'DELETE' }) })
                .then(res => { if (res.ok) dt.ajax.reload(null, false); else AppSwal.error('Gagal menghapus role'); })
                .catch(()=> AppSwal.error('Gagal menghapus role'));
        });
    });
</script>
@endpush
