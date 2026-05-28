@extends('layouts.admin')

@section('title', 'Items')
@section('page_title', 'Items')

@php
    use App\Support\Permission as Perm;
    $canCreate = Perm::can(auth()->user(), 'admin.masterdata.items.index', 'create');
    $canUpdate = Perm::can(auth()->user(), 'admin.masterdata.items.index', 'update');
    $canDelete = Perm::can(auth()->user(), 'admin.masterdata.items.index', 'delete');
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
                <input type="text" class="form-control form-control-solid w-250px ps-14" placeholder="Search items" data-kt-filter="search" />
            </div>
        </div>
        <div class="card-toolbar">
            <div class="d-flex justify-content-end align-items-center gap-2" data-kt-user-table-toolbar="base">
                <select class="form-select form-select-solid w-100px" id="filter_items_limit" aria-label="Limit">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
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
                            <label class="form-label fs-6 fw-bold">Category:</label>
                            <select id="filter_item_category" class="form-select form-select-solid fw-bolder" data-placeholder="Select option" data-allow-clear="true">
                                <option value="">Semua</option>
                                <option value="0">Tanpa Kategori</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="button" class="btn btn-light btn-active-light-primary me-2" id="filter_items_reset">Reset</button>
                            <button type="button" class="btn btn-primary" id="filter_items_apply">Apply</button>
                        </div>
                    </div>
                </div>
                @if($canCreate)
                    <button type="button" class="btn btn-light-primary me-3" id="btn_import_items" data-bs-toggle="modal" data-bs-target="#modal_import_items">Import Excel</button>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_item_form" id="btn_open_create_item">
                        Add Item
                    </button>
                @endif
            </div>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5" id="items_table">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>No</th>
                        <th>SKU</th>
                        <th>Nama</th>
                        <th>Tipe</th>
                        <th>Kategori</th>
                        <th>Alamat</th>
                        <th>Deskripsi</th>
                        <th class="text-end">Stok Pengaman</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<!--begin::Modal Item Form-->
<div class="modal fade" id="modal_item_form" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-750px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder" id="modal_item_title">Add Item</h2>
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
                <form class="form" id="item_form">
                    @csrf
                    <input type="hidden" name="item_id" id="item_id" />
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">SKU</label>
                        <input type="text" class="form-control form-control-solid" name="sku" id="item_sku" required />
                        <div class="invalid-feedback" id="error_sku"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Nama</label>
                        <input type="text" class="form-control form-control-solid" name="name" id="item_name" required />
                        <div class="invalid-feedback" id="error_name"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Kategori</label>
                        <select name="category_id" id="item_category_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Pilih kategori">
                            <option value="0">Tanpa Kategori</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" id="error_category_id"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Alamat</label>
                        <textarea class="form-control form-control-solid" name="address" id="item_address" rows="2"></textarea>
                        <div class="invalid-feedback" id="error_address"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Deskripsi</label>
                        <textarea class="form-control form-control-solid" name="description" id="item_description" rows="3"></textarea>
                        <div class="invalid-feedback" id="error_description"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Jumlah Stok Pengaman</label>
                        <input type="number" min="0" class="form-control form-control-solid" name="safety_stock" id="item_safety_stock" value="0" />
                        <div class="invalid-feedback" id="error_safety_stock"></div>
                    </div>

                    {{-- Bundle toggle --}}
                    <div class="fv-row mb-5">
                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" id="item_is_bundle" name="is_bundle" value="1" />
                            <label class="form-check-label fw-bold" for="item_is_bundle">Ini adalah item Bundle</label>
                        </div>
                        <div class="text-muted fs-7 mt-1">Bundle tidak memiliki stok fisik sendiri. Stoknya dihitung dari stok terendah komponen.</div>
                        <div class="invalid-feedback d-block" id="error_is_bundle"></div>
                    </div>

                    {{-- Bundle components section --}}
                    <div id="bundle_components_section" class="d-none">
                        <div class="separator mb-5"></div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0">Komponen Bundle</h6>
                            <button type="button" class="btn btn-sm btn-light-primary" id="btn_add_component">+ Tambah Komponen</button>
                        </div>
                        <div class="invalid-feedback d-block mb-2" id="error_components"></div>
                        <div id="bundle_components_container"></div>
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
<!--end::Modal Item Form-->

<!--begin::Import Modal-->
<div class="modal fade" id="modal_import_items" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Import Items (Excel)</h2>
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
                    <p class="fw-semibold mb-3">Pastikan file Excel memiliki header dan kolom berikut:</p>
                    <ul class="ms-5 mb-4">
                        <li><strong>sku</strong> (wajib, unik)</li>
                        <li><strong>name</strong> (wajib)</li>
                        <li><strong>parent_category</strong> (opsional, parent kategori; akan dibuat jika belum ada)</li>
                        <li><strong>category</strong> (opsional, anak kategori; jika kosong akan dimasukkan ke kategori default "Tanpa Kategori")</li>
                        <li><strong>stock</strong> / <strong>stok</strong> / <strong>qty</strong> (opsional, stok awal; akan dicatat sebagai inbound saldo awal)</li>
                        <li><strong>safety_stock</strong> / <strong>stok_pengaman</strong> (opsional, jumlah stok pengaman)</li>
                        <li><strong>address</strong> (opsional)</li>
                        <li><strong>description</strong> (opsional)</li>
                    </ul>
                    <p class="text-muted small mb-1">Contoh header: <code>sku,name,parent_category,category,stock,safety_stock,address,description</code></p>
                    <p class="text-muted small mb-1">Gunakan format Excel (.xlsx/.xls) dengan header di baris pertama.</p>
                    <p class="text-muted small mb-0">Jika kolom category dikosongkan, item otomatis dimasukkan ke kategori "Tanpa Kategori".</p>
                </div>
                <div class="mb-10">
                    <label class="required fs-6 fw-bold form-label mb-2">File Excel</label>
                    <input type="file" class="form-control form-control-solid" id="import_items_file" accept=".xlsx,.xls" />
                    <div class="invalid-feedback d-block" id="error_import_file"></div>
                </div>
                <div class="text-end">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="btn_import_items_submit">Import</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!--end::Import Modal-->
@endsection

@push('scripts')
<script>
    const csrfToken  = '{{ csrf_token() }}';
    const dataUrl    = '{{ route('admin.masterdata.items.data') }}';
    const storeUrl   = '{{ route('admin.masterdata.items.store') }}';
    const updateTpl  = '{{ route('admin.masterdata.items.update', ':id') }}';
    const deleteTpl  = '{{ route('admin.masterdata.items.destroy', ':id') }}';
    const showTpl    = '{{ route('admin.masterdata.items.show', ':id') }}';
    const importUrl  = '{{ route('admin.masterdata.items.import') }}';
    const itemSearchUrl = '{{ route('admin.masterdata.items.data') }}';
    const canUpdate  = {{ $canUpdate ? 'true' : 'false' }};
    const canDelete  = {{ $canDelete ? 'true' : 'false' }};

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl        = $('#items_table');
        const searchInput    = document.querySelector('[data-kt-filter="search"]');
        const applyBtn       = document.getElementById('filter_items_apply');
        const resetBtn       = document.getElementById('filter_items_reset');
        const limitSelect    = document.getElementById('filter_items_limit');
        const categoryFilter = document.getElementById('filter_item_category');
        const form           = document.getElementById('item_form');
        const modalEl        = document.getElementById('modal_item_form');
        const modal          = modalEl ? new bootstrap.Modal(modalEl) : null;
        const formSku        = document.getElementById('item_sku');
        const formName       = document.getElementById('item_name');
        const formCategory   = document.getElementById('item_category_id');
        const formId         = document.getElementById('item_id');
        const formAddress    = document.getElementById('item_address');
        const formDescription = document.getElementById('item_description');
        const formSafetyStock = document.getElementById('item_safety_stock');
        const formIsBundle   = document.getElementById('item_is_bundle');
        const bundleSection  = document.getElementById('bundle_components_section');
        const bundleContainer = document.getElementById('bundle_components_container');
        const titleEl        = document.getElementById('modal_item_title');
        const importModalEl  = document.getElementById('modal_import_items');
        const importModal    = importModalEl ? new bootstrap.Modal(importModalEl) : null;
        const importInput    = document.getElementById('import_items_file');
        const importError    = document.getElementById('error_import_file');
        const importSubmit   = document.getElementById('btn_import_items_submit');
        const qrLibraryUrl   = 'https://cdn.jsdelivr.net/npm/qr-code-styling@1.6.0/lib/qr-code-styling.js';
        let qrLibraryPromise = null;

        // ── Bundle component rows ──────────────────────────────────────────────

        const createComponentRow = (data = {}) => {
            const idx = bundleContainer.querySelectorAll('.component-row').length;
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-end mb-3 component-row';
            row.innerHTML = `
                <div class="col-md-8">
                    <label class="required fs-7 fw-bold form-label mb-1">Item Komponen</label>
                    <select class="form-select form-select-solid component-item-select" data-name="component_item_id" required>
                        <option value=""></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="required fs-7 fw-bold form-label mb-1">Qty</label>
                    <input type="number" min="1" value="${data.qty ?? 1}" class="form-control form-control-solid" data-name="qty" required />
                </div>
                <div class="col-md-2 text-end">
                    <button type="button" class="btn btn-sm btn-light btn-remove-component">Hapus</button>
                </div>`;
            bundleContainer.appendChild(row);

            const selectEl = row.querySelector('.component-item-select');
            const qtyEl    = row.querySelector('[data-name="qty"]');

            // Prefill with existing data if provided
            if (data.component_item_id && data.sku) {
                const opt = new Option(`${data.sku} - ${data.name || ''}`, data.component_item_id, true, true);
                selectEl.appendChild(opt);
            }
            if (data.qty) qtyEl.value = data.qty;

            // Init select2 with AJAX search
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $(selectEl).select2({
                    placeholder: 'Cari SKU komponen...',
                    allowClear: true,
                    width: '100%',
                    ajax: {
                        url: itemSearchUrl,
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term || '', length: 20, start: 0 }),
                        processResults: resp => ({
                            results: (resp.data || [])
                                .filter(i => !i.is_bundle)
                                .map(i => ({ id: i.id, text: `${i.sku} – ${i.name}` })),
                        }),
                        cache: true,
                    },
                    minimumInputLength: 0,
                });
            }

            renumberComponents();
        };

        const renumberComponents = () => {
            bundleContainer.querySelectorAll('.component-row').forEach((row, idx) => {
                row.querySelectorAll('[data-name]').forEach(el => {
                    el.name = `components[${idx}][${el.getAttribute('data-name')}]`;
                });
            });
        };

        bundleContainer.addEventListener('click', e => {
            if (!e.target.closest('.btn-remove-component')) return;
            e.target.closest('.component-row')?.remove();
            renumberComponents();
        });

        document.getElementById('btn_add_component')?.addEventListener('click', () => createComponentRow());

        formIsBundle?.addEventListener('change', () => {
            if (formIsBundle.checked) {
                bundleSection.classList.remove('d-none');
                if (!bundleContainer.querySelector('.component-row')) createComponentRow();
            } else {
                bundleSection.classList.add('d-none');
            }
        });

        // ── Helpers ────────────────────────────────────────────────────────────

        const setCategoryValue = (val) => {
            if (!formCategory) return;
            const normalized = (val === null || val === undefined || val === '' || val === 'null') ? '0' : String(val);
            formCategory.value = normalized;
            if (typeof $ !== 'undefined' && $(formCategory).data('select2')) {
                $(formCategory).val(normalized).trigger('change');
            }
        };

        const errorIds = ['error_sku','error_name','error_category_id','error_address','error_description','error_safety_stock','error_is_bundle','error_components'];
        const clearErrors = () => {
            errorIds.forEach(id => {
                const el = document.getElementById(id);
                if (el) el.textContent = '';
            });
        };

        const confirmAction = async () => {
            if (typeof Swal === 'undefined') return true;
            const result = await Swal.fire({
                title: 'Apakah Anda yakin?', icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6', cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, lanjutkan', cancelButtonText: 'Batal',
                allowOutsideClick: false, allowEscapeKey: false, focusConfirm: false,
            });
            if (!result.isConfirmed) return false;
            Swal.fire({ title: 'Memproses...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
            return true;
        };
        const closeSwal = () => typeof Swal !== 'undefined' && Swal.close();
        const safeFileName = (value) => String(value || 'sku')
            .replace(/[^a-z0-9\-_]+/gi, '-')
            .replace(/^-+|-+$/g, '')
            .slice(0, 80) || 'sku';
        const escapeAttr = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
        const loadQrLibrary = () => {
            if (window.QRCodeStyling) return Promise.resolve();
            if (qrLibraryPromise) return qrLibraryPromise;
            qrLibraryPromise = new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = qrLibraryUrl;
                script.async = true;
                script.onload = () => window.QRCodeStyling ? resolve() : reject(new Error('Library QR tidak tersedia.'));
                script.onerror = () => reject(new Error('Gagal memuat library QR dari CDN.'));
                document.head.appendChild(script);
            });
            return qrLibraryPromise;
        };

        const drawWrappedCenteredText = (ctx, text, x, y, maxWidth, lineHeight) => {
            const words = String(text || '').split(/\s+/).filter(Boolean);
            const lines = [];
            let line = '';
            words.forEach((word) => {
                const test = line ? `${line} ${word}` : word;
                if (ctx.measureText(test).width <= maxWidth) {
                    line = test;
                    return;
                }
                if (line) lines.push(line);
                line = word;
            });
            if (line) lines.push(line);
            const output = lines.length ? lines : [String(text || '')];
            output.slice(0, 2).forEach((row, idx) => ctx.fillText(row, x, y + (idx * lineHeight)));
        };
        const downloadSkuQr = async (sku) => {
            sku = String(sku || '').trim();
            if (!sku) {
                Swal?.fire('Error', 'SKU kosong, QR Code tidak bisa dibuat.', 'error');
                return;
            }

            try {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Membuat QR Code...',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: () => Swal.showLoading(),
                    });
                }

                await loadQrLibrary();
                const holder = document.createElement('div');
                holder.style.position = 'fixed';
                holder.style.left = '-9999px';
                holder.style.top = '-9999px';
                document.body.appendChild(holder);

                const qr = new QRCodeStyling({
                    width: 360,
                    height: 360,
                    type: 'canvas',
                    data: sku,
                    margin: 22,
                    qrOptions: { errorCorrectionLevel: 'M' },
                    dotsOptions: { color: '#000000', type: 'square' },
                    cornersSquareOptions: { color: '#000000', type: 'square' },
                    cornersDotOptions: { color: '#000000', type: 'square' },
                    backgroundOptions: { color: '#ffffff' },
                });
                qr.append(holder);
                await new Promise(resolve => setTimeout(resolve, 80));
                const qrCanvas = holder.querySelector('canvas');
                if (!qrCanvas) throw new Error('Canvas QR tidak berhasil dibuat.');

                const canvas = document.createElement('canvas');
                canvas.width = 420;
                canvas.height = 470;
                const ctx = canvas.getContext('2d');

                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);

                ctx.drawImage(qrCanvas, 30, 30, 360, 360);
                holder.remove();

                ctx.fillStyle = '#0f172a';
                ctx.font = '800 32px Arial, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                drawWrappedCenteredText(ctx, sku, 210, 408, 380, 38);
                canvas.toBlob((blob) => {
                    if (!blob) {
                        Swal?.fire('Error', 'Gagal membuat file QR Code.', 'error');
                        return;
                    }
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `qr-${safeFileName(sku)}.png`;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    URL.revokeObjectURL(url);
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Berhasil', `QR Code SKU ${sku} berhasil didownload.`, 'success');
                    }
                }, 'image/png');
            } catch (err) {
                console.error(err);
                Swal?.fire('Error', err.message || 'Gagal membuat QR Code.', 'error');
            }
        };

        // ── DataTable ──────────────────────────────────────────────────────────

        if (typeof $ !== 'undefined' && $.fn.select2) {
            $(categoryFilter).select2({ placeholder: 'Semua', allowClear: true, width: '100%' })
                .on('select2:opening select2:closing select2:close', e => e.stopPropagation());
            $(formCategory).select2({ placeholder: 'Pilih kategori', allowClear: true, width: '100%' });
        }

        const refreshMenus = () => window.KTMenu?.createInstances();

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [[0, 'desc']],
            pageLength: Number(limitSelect?.value || 10),
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: params => {
                    params.q = searchInput?.value || '';
                    params.category_id = categoryFilter?.value || '';
                },
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
                { data: 'sku' },
                { data: 'name' },
                { data: 'is_bundle', render: v => v
                    ? '<span class="badge badge-light-primary">Bundle</span>'
                    : '<span class="badge badge-light-secondary">Regular</span>' },
                { data: 'category' },
                { data: 'address' },
                { data: 'description' },
                { data: 'safety_stock', className: 'text-end', render: d => d ?? 0 },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (id, t, row) => {
                    const qrItem = `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-download-qr" data-sku="${escapeAttr(row.sku)}">Download QR</a></div>`;
                    const editItem = canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${id}">Edit</a></div>` : '';
                    const delItem  = canDelete ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${id}">Hapus</a></div>` : '';
                    const actions = `${qrItem}${editItem}${delItem}`;
                    if (!actions) return '';
                    return `<div class="text-end">
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
                    </div>`;
                }},
            ],
        });
        refreshMenus();
        dt.on('draw', refreshMenus);

        const reloadTable = (keepPage = false) => dt.ajax.reload(null, keepPage ? false : true);

        searchInput?.addEventListener('keyup', reloadTable);
        applyBtn?.addEventListener('click', reloadTable);
        categoryFilter?.addEventListener('change', reloadTable);
        limitSelect?.addEventListener('change', () => {
            dt.page.len(Number(limitSelect.value || 10)).draw();
        });
        resetBtn?.addEventListener('click', () => {
            if (categoryFilter) {
                categoryFilter.value = '';
                typeof $ !== 'undefined' && $(categoryFilter).data('select2') && $(categoryFilter).val('').trigger('change.select2');
            }
            if (limitSelect) { limitSelect.value = '10'; dt.page.len(10).draw(); }
            reloadTable();
        });

        // ── Create item ────────────────────────────────────────────────────────

        document.getElementById('btn_open_create_item')?.addEventListener('click', () => {
            if (!form) return;
            form.reset();
            formId.value = '';
            formSafetyStock && (formSafetyStock.value = 0);
            formIsBundle.checked = false;
            bundleSection.classList.add('d-none');
            bundleContainer.innerHTML = '';
            setCategoryValue('0');
            clearErrors();
            if (titleEl) titleEl.textContent = 'Add Item';
        });

        // ── Edit item ──────────────────────────────────────────────────────────

        tableEl.on('click', '.btn-download-qr', async function(e) {
            e.preventDefault();
            await downloadSkuQr(this.getAttribute('data-sku'));
        });

        tableEl.on('click', '.btn-edit', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            try {
                const res  = await fetch(showTpl.replace(':id', id), { headers: { Accept: 'application/json' } });
                const json = await res.json();
                if (!res.ok) { Swal?.fire('Error', json.message || 'Gagal memuat data', 'error'); return; }

                form.reset();
                formId.value = id;
                formSku && (formSku.value = json.sku || '');
                formName && (formName.value = json.name || '');
                formAddress && (formAddress.value = json.address || '');
                formDescription && (formDescription.value = json.description || '');
                formSafetyStock && (formSafetyStock.value = json.safety_stock ?? 0);
                setCategoryValue(json.category_id || '0');

                // Bundle state
                const isBundle = !!json.is_bundle;
                formIsBundle.checked = isBundle;
                bundleContainer.innerHTML = '';
                if (isBundle) {
                    bundleSection.classList.remove('d-none');
                    (json.components || []).forEach(c => createComponentRow(c));
                    if (!(json.components || []).length) createComponentRow();
                } else {
                    bundleSection.classList.add('d-none');
                }

                clearErrors();
                if (titleEl) titleEl.textContent = 'Edit Item';
                modal?.show();
            } catch (err) {
                console.error(err);
                Swal?.fire('Error', 'Gagal memuat data item', 'error');
            }
        });

        // ── Delete item ────────────────────────────────────────────────────────

        tableEl.on('click', '.btn-delete', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const res = await Swal.fire({
                    title: 'Apakah Anda yakin?', text: 'Item akan dihapus', icon: 'warning',
                    showCancelButton: true, confirmButtonText: 'Hapus', cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: { confirmButton: 'btn btn-danger', cancelButton: 'btn btn-light' },
                });
                confirmed = res.isConfirmed;
            }
            if (!confirmed) return;
            try {
                const res  = await fetch(deleteTpl.replace(':id', id), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
                    body: new URLSearchParams({ _method: 'DELETE' }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) { Swal?.fire('Error', json.message || 'Gagal menghapus item', 'error'); return; }
                Swal?.fire('Berhasil', json.message || 'Berhasil', 'success');
                reloadTable();
            } catch (err) {
                console.error(err);
                Swal?.fire('Error', 'Gagal menghapus item', 'error');
            }
        });

        // ── Form submit ────────────────────────────────────────────────────────

        form?.addEventListener('submit', async (e) => {
            e.preventDefault();
            clearErrors();
            const id  = formId.value;
            const url = id ? updateTpl.replace(':id', id) : storeUrl;
            const fd  = new FormData(form);
            if (id) fd.append('_method', 'PUT');

            // Explicitly send is_bundle as 0/1 (checkbox not submitted when unchecked)
            fd.set('is_bundle', formIsBundle.checked ? '1' : '0');

            try {
                const res  = await fetch(id ? url : url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                    body: fd,
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    if (json?.errors) {
                        Object.entries(json.errors).forEach(([key, msgs]) => {
                            const errEl = document.getElementById(`error_${key}`);
                            if (errEl) errEl.textContent = msgs.join(', ');
                        });
                    } else {
                        Swal?.fire('Error', json.message || 'Gagal menyimpan item', 'error');
                    }
                    return;
                }
                Swal?.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable(true);
            } catch (err) {
                console.error(err);
                Swal?.fire('Error', 'Gagal menyimpan item', 'error');
            }
        });

        // ── Import ─────────────────────────────────────────────────────────────

        document.getElementById('btn_import_items')?.addEventListener('click', () => {
            if (importInput) importInput.value = '';
            if (importError) importError.textContent = '';
        });

        importSubmit?.addEventListener('click', async () => {
            if (importError) importError.textContent = '';
            const file = importInput?.files?.[0];
            if (!file) { if (importError) importError.textContent = 'Pilih file Excel terlebih dahulu.'; return; }
            const confirmed = await confirmAction();
            if (!confirmed) return;
            const fd = new FormData();
            fd.append('file', file);
            try {
                const res  = await fetch(importUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                    body: fd,
                });
                const text = await res.text();
                let json;
                try { json = JSON.parse(text); } catch { closeSwal(); Swal?.fire('Error', 'Respons server tidak valid', 'error'); return; }
                closeSwal();
                if (!res.ok) {
                    if (json?.errors) { Swal?.fire('Error', Object.values(json.errors).flat().join(', ') || 'Gagal import', 'error'); }
                    else { Swal?.fire('Error', json.message || 'Gagal import', 'error'); }
                    return;
                }
                Swal?.fire('Berhasil', `${json.message || 'Import selesai'} (created: ${json.created}, updated: ${json.updated})`, 'success');
                if (importInput) importInput.value = '';
                importModal?.hide();
                reloadTable();
            } catch (err) {
                console.error(err);
                closeSwal();
                Swal?.fire('Error', 'Gagal import', 'error');
            }
        });
    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
