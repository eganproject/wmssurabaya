@extends('layouts.admin')

@section('title', 'Stores')
@section('page_title', 'Stores')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.stores.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.stores.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.stores.index', 'delete');
@endphp

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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search stores" data-kt-filter="search" />
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
                <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" data-kt-menu-dismiss="false">
                    <div class="px-7 py-5">
                        <div class="fs-5 text-dark fw-bolder">Filter Options</div>
                    </div>
                    <div class="separator border-gray-200"></div>
                    <div class="px-7 py-5">
                        <div class="mb-10">
                            <label class="form-label fs-6 fw-bold">PIC:</label>
                            <select id="filter_store_pic" class="form-select form-select-solid fw-bolder" data-placeholder="Select option" data-allow-clear="true">
                                <option value="">Semua</option>
                                @foreach($pics as $pic)
                                    <option value="{{ $pic->id }}">{{ $pic->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light btn-active-light-primary me-2" id="filter_stores_reset">Reset</button>
                            <button type="button" class="btn btn-primary" id="filter_stores_apply">Apply</button>
                        </div>
                    </div>
                </div>
            @if($canCreate)
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_store_form" id="btn_open_create_store">
                    Add Store
                </button>
            @endif
        </div>
    </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="stores_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>ID</th>
                        <th>Logo</th>
                        <th>Nama</th>
                        <th>PIC</th>
                        <th>Alamat</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!--begin::Modal-->
<div class="modal fade" id="modal_store_form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="modal_store_title">Add Store</h2>
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
                <form class="form" id="store_form">
                    @csrf
                    <input type="hidden" name="store_id" id="store_id" />
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Nama</label>
                        <input type="text" class="form-control form-control-solid" name="name" id="store_name" required />
                        <div class="invalid-feedback" id="error_name"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">PIC</label>
                        <select name="pic_id" id="store_pic_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih PIC">
                            <option value="">Pilih PIC</option>
                            @foreach($pics as $pic)
                                <option value="{{ $pic->id }}">{{ $pic->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="error_pic_id"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Alamat</label>
                        <textarea class="form-control form-control-solid" name="address" id="store_address" rows="3"></textarea>
                        <div class="invalid-feedback" id="error_address"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Logo</label>
                        <div class="d-flex align-items-center gap-4 mb-3">
                            <img src="{{ asset('metronic/media/logos/logo-demo11.svg') }}" alt="Logo" id="store_logo_preview" class="w-60px h-60px rounded object-cover">
                        </div>
                        <input type="file" name="logo" id="store_logo" class="form-control form-control-solid" accept=".jpg,.jpeg,.png" />
                        <div class="invalid-feedback" id="error_logo"></div>
                        <div class="form-text">Boleh dikosongkan.</div>
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
<!--end::Modal-->
@endsection

@push('scripts')
<script>
    const csrfToken = '{{ csrf_token() }}';
    const dataUrl   = '{{ route('admin.masterdata.stores.data') }}';
    const storeUrl  = '{{ route('admin.masterdata.stores.store') }}';
    const updateTpl = '{{ route('admin.masterdata.stores.update', ':id') }}';
    const deleteTpl = '{{ route('admin.masterdata.stores.destroy', ':id') }}';
    const canUpdate = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete = {{ $canDelete ? 'true' : 'false' }};
    const defaultLogoUrl = "{{ asset('metronic/media/logos/logo-demo11.svg') }}";

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stores_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const applyBtn = document.getElementById('filter_stores_apply');
        const resetBtn = document.getElementById('filter_stores_reset');
        const picFilter = document.getElementById('filter_store_pic');
        const form = document.getElementById('store_form');
        const modalEl = document.getElementById('modal_store_form');
        const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
        const formName = document.getElementById('store_name');
        const formPic = document.getElementById('store_pic_id');
        const formAddress = document.getElementById('store_address');
        const formId = document.getElementById('store_id');
        const formLogo = document.getElementById('store_logo');
        const logoPreview = document.getElementById('store_logo_preview');
        const titleEl = document.getElementById('modal_store_title');

        const select2Safe = (el, placeholder) => {
            if (el && typeof $ !== 'undefined' && $.fn.select2) {
                $(el).select2({ placeholder, allowClear: true, width: '100%' })
                    .on('select2:opening select2:closing select2:close', function(e){ e.stopPropagation(); });
            }
        };

        select2Safe(picFilter, 'Semua');
        select2Safe(formPic, 'Pilih PIC');

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const refreshMenus = () => { if (window.KTMenu) KTMenu.createInstances(); };

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: function(params) {
                    params.q = searchInput?.value || '';
                    params.pic_id = picFilter?.value || '';
                }
            },
            columns: [
                { data: 'id' },
                { data: 'logo_url', orderable:false, searchable:false, render: (data)=>`<img src="${data}" alt="logo" class="w-40px h-40px rounded object-cover">` },
                { data: 'name' },
                { data: 'pic' },
                { data: 'address' },
                { data: 'id', orderable:false, searchable:false, className:'text-end', render: (data, type, row)=>{
                    const editItem = canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${data}" data-name="${row.name}" data-pic="${row.pic_id}" data-address="${row.address}" data-logo="${row.logo_url}">Edit</a></div>` : '';
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
        applyBtn?.addEventListener('click', reloadTable);
        picFilter?.addEventListener('change', reloadTable);
        resetBtn?.addEventListener('click', () => {
            if (picFilter) {
                picFilter.value = '';
                if (typeof $ !== 'undefined' && $(picFilter).data('select2')) {
                    $(picFilter).val('').trigger('change.select2');
                }
            }
            reloadTable();
        });

        const clearErrors = () => {
            ['error_name','error_pic_id','error_address','error_logo'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
        };

        const setLogoPreview = (url = null) => {
            if (logoPreview) {
                logoPreview.src = url || defaultLogoUrl;
            }
        };

        document.getElementById('btn_open_create_store')?.addEventListener('click', () => {
            if (!form) return;
            form.reset();
            formId.value = '';
            if (formPic) {
                formPic.value = '';
                if (typeof $ !== 'undefined' && $(formPic).data('select2')) {
                    $(formPic).val('').trigger('change.select2');
                }
            }
            if (formLogo) {
                formLogo.value = '';
            }
            clearErrors();
            setLogoPreview();
            if (titleEl) titleEl.textContent = 'Add Store';
        });

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
                    console.error('Invalid JSON', text);
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
                        Swal.fire('Error', json.message || 'Gagal menyimpan toko', 'error');
                    }
                    return;
                }
                if (json?.store?.logo_url) {
                    setLogoPreview(json.store.logo_url);
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menyimpan toko', 'error');
            }
        });

        tableEl.on('click', '.btn-edit', function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const name = this.getAttribute('data-name');
            const picId = this.getAttribute('data-pic');
            const address = this.getAttribute('data-address') || '';
            const logo = this.getAttribute('data-logo');
            if (!form) return;
            clearErrors();
            formId.value = id;
            formName.value = name;
            formAddress.value = address !== '-' ? address : '';
            if (formPic) {
                formPic.value = picId || '';
                if (typeof $ !== 'undefined' && $(formPic).data('select2')) {
                    $(formPic).val(formPic.value).trigger('change.select2');
                }
            }
            if (formLogo) {
                formLogo.value = '';
            }
            setLogoPreview(logo || null);
            if (titleEl) titleEl.textContent = 'Edit Store';
            modal?.show();
        });

        modalEl?.addEventListener('hidden.bs.modal', () => {
            if (formLogo) formLogo.value = '';
            setLogoPreview();
        });

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?',
                    text: 'Toko akan dihapus',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Hapus',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;
            try {
                const res = await fetch(deleteTpl.replace(':id', id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch (parseErr) {
                    console.error('Invalid JSON', text);
                    if (typeof Swal !== 'undefined') Swal.fire('Error', 'Respons server tidak valid', 'error');
                    return;
                }
                if (!res.ok) {
                    if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal menghapus toko', 'error');
                    return;
                }
                if (typeof Swal !== 'undefined') Swal.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                console.error(err);
                if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal menghapus toko', 'error');
            }
        });

    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
