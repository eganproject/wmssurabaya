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
<style>
    .items-toolbar { gap: .75rem; }
    .items-toolbar .items-search { min-width: 260px; flex: 1 1 340px; }
    .items-table tbody tr { transition: background-color .15s ease; }
    .items-table tbody tr:hover { background: #f8fafc; }
    .items-table td { padding-top: 1.15rem !important; padding-bottom: 1.15rem !important; }
    .item-primary { min-width: 260px; }
    .item-name { font-size: 1rem; line-height: 1.35; color: #181c32; }
    .item-secondary { color: #5e6278; font-size: .84rem; line-height: 1.45; }
    .item-description { max-width: 360px; color: #5e6278; line-height: 1.5; }
    .warehouse-info { min-width: 210px; }
    .table-action-button { white-space: nowrap; }
    .bulk-step-number { width: 30px; height: 30px; flex: 0 0 30px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-right: .85rem; background: #eef6ff; color: #1b84ff; font-weight: 700; font-size: .9rem; }
    .bulk-field-option { padding: .85rem 1rem; border: 1px dashed #dbdfe9; border-radius: .65rem; cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
    .bulk-field-option:hover { border-color: #1b84ff; }
    .bulk-field-option.is-selected { border-style: solid; border-color: #1b84ff; background: #f1f8ff; }
    .bulk-field-key { font-size: .7rem; padding: .1rem .35rem; margin-left: .25rem; background: #f9f9f9; color: #7e8299; }
    .bulk-error-list { max-height: 220px; overflow-y: auto; padding-left: 1.1rem; }
    .bulk-error-list li + li { margin-top: .25rem; }
    @media (max-width: 991.98px) {
        .items-toolbar > * { flex: 1 1 200px; }
    }
    @media (max-width: 575.98px) {
        .items-toolbar > * { width: 100% !important; max-width: none !important; flex-basis: 100%; }
        .items-toolbar .btn { justify-content: center; }
    }
</style>

<div class="card card-flush">
    <div class="card-header border-0 pt-6 pb-3">
        <div class="card-title d-block">
            <h2 class="fw-bolder text-gray-900 mb-1">Master Item</h2>
            <div class="text-muted fs-7">Kelola identitas item, kategori, dan pengaturan penyimpanan gudang.</div>
        </div>
    </div>
    <div class="card-body pt-2">
        <div class="d-flex flex-wrap align-items-center items-toolbar mb-6" data-kt-user-table-toolbar="base">
            <div class="position-relative items-search">
                <i class="fas fa-search position-absolute top-50 translate-middle-y ms-5 text-gray-500"></i>
                <input type="text" class="form-control form-control-solid ps-12" placeholder="Cari SKU, nama, lokasi, atau deskripsi..." data-kt-filter="search" />
            </div>
            <select class="form-select form-select-solid w-125px" id="filter_items_limit" aria-label="Jumlah data">
                <option value="10" selected>10 baris</option>
                <option value="25">25 baris</option>
                <option value="50">50 baris</option>
                <option value="100">100 baris</option>
            </select>
            <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                <i class="fas fa-filter me-2"></i>Filter
            </button>
            <div class="menu menu-sub menu-sub-dropdown w-300px w-md-325px" data-kt-menu="true" data-kt-menu-dismiss="false">
                <div class="px-7 py-5"><div class="fs-5 text-dark fw-bolder">Filter Item</div></div>
                <div class="separator border-gray-200"></div>
                <div class="px-7 py-5">
                    <div class="mb-7">
                        <label class="form-label fs-6 fw-bold">Kategori</label>
                        <select id="filter_item_category" class="form-select form-select-solid fw-bolder" data-placeholder="Semua kategori" data-allow-clear="true">
                            <option value="">Semua</option>
                            <option value="0">Tanpa Kategori</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-7">
                        <label class="form-label fs-6 fw-bold">Status Produk</label>
                        <select id="filter_item_status" class="form-select form-select-solid fw-bolder">
                            <option value="">Semua Status</option>
                            <option value="1">Aktif</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    <div class="mb-7">
                        <label class="form-label fs-6 fw-bold">Sumber Pengadaan</label>
                        <select id="filter_item_procurement_source" class="form-select form-select-solid fw-bolder">
                            <option value="">Semua Sumber</option>
                            @foreach($procurementSources as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-light btn-active-light-primary me-2" id="filter_items_reset">Reset</button>
                        <button type="button" class="btn btn-primary" id="filter_items_apply">Terapkan</button>
                    </div>
                </div>
            </div>
            @if($canUpdate)
                <button type="button" class="btn btn-light-primary" id="btn_bulk_update_items" data-bs-toggle="modal" data-bs-target="#modal_bulk_update_items">
                    <i class="fas fa-edit me-2"></i>Update Massal
                </button>
            @endif
            @if($canCreate)
                <button type="button" class="btn btn-light-primary" id="btn_import_items" data-bs-toggle="modal" data-bs-target="#modal_import_items">
                    <i class="fas fa-file-import me-2"></i>Import Excel
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal_item_form" id="btn_open_create_item">
                    <i class="fas fa-plus me-2"></i>Tambah Item
                </button>
            @endif
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed items-table fs-6 gy-4" id="items_table">
                <thead>
                    <tr class="text-start text-gray-600 fw-bolder fs-7 text-uppercase gs-0">
                        <th>No</th>
                        <th>Identitas Item</th>
                        <th>Kategori, UOM & Deskripsi</th>
                        <th>Pengaturan Gudang Kecil</th>
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
                        <label class="required fs-6 fw-bold form-label mb-2">Sumber Pengadaan</label>
                        <select name="procurement_source" id="item_procurement_source" class="form-select form-select-solid" required>
                            @foreach($procurementSources as $value => $label)
                                <option value="{{ $value }}" @selected($value === \App\Models\Item::PROCUREMENT_NANGGEWER)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Menentukan lead time yang digunakan pada Analisa Forecast.</div>
                        <div class="invalid-feedback" id="error_procurement_source"></div>
                    </div>
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Deskripsi</label>
                        <textarea class="form-control form-control-solid" name="description" id="item_description" rows="3"></textarea>
                        <div class="invalid-feedback" id="error_description"></div>
                    </div>
                    <div class="mb-7">
                        <label class="fs-6 fw-bold form-label mb-3">Pengaturan Per Gudang</label>
                        <div class="row g-4">
                            @foreach($warehouses as $index => $warehouse)
                                <div class="col-md-6 warehouse-setting-row" data-warehouse-id="{{ $warehouse->id }}">
                                    <div class="border rounded p-4">
                                        <div class="fw-bold mb-3">{{ $warehouse->name }}</div>
                                        <input type="hidden" name="warehouse_settings[{{ $index }}][warehouse_id]" value="{{ $warehouse->id }}">
                                        <label class="form-label">Lokasi/Rak</label>
                                        <input class="form-control form-control-solid warehouse-location mb-3" name="warehouse_settings[{{ $index }}][location]" placeholder="Contoh: Rak A-01">
                                        <label class="form-label">Safety Stock</label>
                                        <input type="number" min="0" value="0" class="form-control form-control-solid warehouse-safety" name="warehouse_settings[{{ $index }}][safety_stock]">
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="row g-4 mb-7">
                        <div class="col-md-4">
                            <label class="required fs-6 fw-bold form-label mb-2">UOM Dasar</label>
                            <select class="form-select form-select-solid" name="base_uom_id" id="item_base_uom_id" required>
                                @foreach($uoms as $uom)<option value="{{ $uom->id }}" data-code="{{ $uom->code }}" @selected($uom->code === 'PCS')>{{ $uom->code }} — {{ $uom->name }}</option>@endforeach
                            </select><input type="hidden" name="base_unit_name" id="item_base_unit_name" value="PCS" />
                        </div>
                        <div class="col-md-4">
                            <label class="fs-6 fw-bold form-label mb-2">UOM Kemasan</label>
                            <select class="form-select form-select-solid" name="package_uom_id" id="item_package_uom_id"><option value="">Tanpa kemasan</option>@foreach($uoms as $uom)<option value="{{ $uom->id }}" data-code="{{ $uom->code }}">{{ $uom->code }} — {{ $uom->name }}</option>@endforeach</select><input type="hidden" name="package_unit_name" id="item_package_unit_name" />
                        </div>
                        <div class="col-md-4">
                            <label class="fs-6 fw-bold form-label mb-2">Isi per Kemasan</label>
                            <input type="number" min="1" class="form-control form-control-solid" name="package_conversion_qty" id="item_package_conversion_qty" value="1" />
                            <div class="form-text">Minimal 1. Contoh: 1 DUS = 24 PCS.</div>
                        </div>
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
                        <li><strong>procurement_source</strong> (opsional; <code>nanggewer</code>/<code>produksi</code> atau <code>import</code>; default <code>nanggewer</code>)</li>
                        <li><strong>base_unit</strong> (opsional, default <code>PCS</code>; dapat diisi <code>SET</code>)</li>
                        <li><strong>package_unit</strong> (opsional, contoh <code>KOLI</code>, <code>DUS</code>, atau <code>BOX</code>)</li>
                        <li><strong>package_conversion_qty</strong> (wajib jika package_unit atau stok Gudang Besar diisi; minimal <code>1</code>, contoh <code>24</code> berarti 1 KOLI = 24 PCS)</li>
                        <li><strong>small_warehouse_stock</strong> (opsional, stok awal Gudang Kecil dalam satuan dasar)</li>
                        <li><strong>large_warehouse_stock</strong> (opsional, stok awal Gudang Besar dalam satuan kemasan; package_unit default <code>KOLI</code>)</li>
                        <li><strong>small_warehouse_safety_stock</strong> dan <strong>small_warehouse_location</strong> (opsional)</li>
                        <li><strong>large_warehouse_safety_stock</strong> dan <strong>large_warehouse_location</strong> (opsional)</li>
                        <li><strong>description</strong> (opsional)</li>
                    </ul>
                    <p class="text-muted small mb-1">Contoh header baru:</p>
                    <code class="d-block text-wrap">sku,name,parent_category,category,procurement_source,base_unit,package_unit,package_conversion_qty,small_warehouse_stock,large_warehouse_stock,small_warehouse_safety_stock,small_warehouse_location,large_warehouse_safety_stock,large_warehouse_location,description</code>
                    <p class="text-muted small mt-3 mb-1">Contoh nilai: <code>SKU-001 | Produk A | PCS | KOLI | 24 | 100 | 10</code> berarti Gudang Kecil 100 PCS dan Gudang Besar 10 KOLI = 240 PCS.</p>
                    <p class="text-muted small mb-1">Gunakan format Excel (.xlsx/.xls) dengan header di baris pertama.</p>
                    <p class="text-muted small mb-0">Jika kolom category dikosongkan, item otomatis dimasukkan ke kategori "Tanpa Kategori".</p>
                    <a href="{{ route('admin.masterdata.items.template') }}" class="btn btn-sm btn-light-success mt-4">Download Template Excel</a>
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

@if($canUpdate)
<!--begin::Bulk Update Modal-->
<div class="modal fade" id="modal_bulk_update_items" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 class="fw-bolder mb-1">Update Massal Item</h2>
                    <div class="text-muted fs-7">Perbarui field tertentu untuk banyak item sekaligus berdasarkan SKU.</div>
                </div>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body px-8 px-lg-10 py-8">
                <div class="notice d-flex align-items-center bg-light-warning rounded border-warning border border-dashed p-4 mb-8">
                    <i class="fas fa-lock text-warning fs-2 me-4"></i>
                    <div class="fs-7 text-gray-700">
                        <span class="fw-bolder text-gray-900">Tidak dapat diubah:</span>
                        SKU (dipakai sebagai kunci pencarian), satuan kemasan (koli), dan isi per koli.
                        Field yang tidak dipilih tetap seperti semula.
                    </div>
                </div>

                <!-- Langkah 1: pilih field -->
                <div class="bulk-step mb-8">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
                        <div class="d-flex align-items-center">
                            <span class="bulk-step-number">1</span>
                            <div>
                                <div class="fw-bolder fs-6 text-gray-900">Pilih field yang akan diupdate</div>
                                <div class="text-muted fs-8">Kolom template mengikuti pilihan ini.</div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-light-primary fs-8" id="bulk_field_counter">0 field dipilih</span>
                            <button type="button" class="btn btn-sm btn-light" id="bulk_field_select_all">Pilih semua</button>
                            <button type="button" class="btn btn-sm btn-light" id="bulk_field_clear">Kosongkan</button>
                        </div>
                    </div>
                    @foreach($bulkUpdateFieldGroups as $groupName => $groupFields)
                        <div class="text-gray-500 fw-bold fs-8 text-uppercase mb-2 {{ $loop->first ? '' : 'mt-4' }}">{{ $groupName }}</div>
                        <div class="row g-3">
                            @foreach($groupFields as $fieldKey => $field)
                                <div class="col-md-6">
                                    <label class="bulk-field-option d-flex align-items-start h-100" for="bulk_field_{{ $fieldKey }}">
                                        <span class="form-check form-check-custom form-check-solid form-check-sm me-3 mt-1">
                                            <input class="form-check-input bulk-field-checkbox" type="checkbox" value="{{ $fieldKey }}" id="bulk_field_{{ $fieldKey }}" data-label="{{ $field['label'] }}" />
                                        </span>
                                        <span class="d-flex flex-column">
                                            <span class="fw-bold text-gray-900 fs-7">{{ $field['label'] }} <code class="bulk-field-key">{{ $fieldKey }}</code></span>
                                            <span class="text-muted fs-8 lh-sm mt-1">{{ $field['hint'] }}</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <!-- Langkah 2: download template -->
                <div class="bulk-step mb-8">
                    <div class="d-flex align-items-center mb-4">
                        <span class="bulk-step-number">2</span>
                        <div>
                            <div class="fw-bolder fs-6 text-gray-900">Download template</div>
                            <div class="text-muted fs-8">Template berisi kolom SKU + field terpilih, lengkap dengan dropdown dan sheet panduan.</div>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-3">
                        <div class="btn-group bulk-prefill-group flex-wrap" role="group" aria-label="Isi template">
                            <input type="radio" class="btn-check" name="bulk_prefill" id="bulk_prefill_all" value="all" checked />
                            <label class="btn btn-sm btn-light btn-active-light-primary" for="bulk_prefill_all">Isi semua item</label>
                            <input type="radio" class="btn-check" name="bulk_prefill" id="bulk_prefill_filtered" value="filtered" />
                            <label class="btn btn-sm btn-light btn-active-light-primary" for="bulk_prefill_filtered">Sesuai filter tabel</label>
                            <input type="radio" class="btn-check" name="bulk_prefill" id="bulk_prefill_none" value="none" />
                            <label class="btn btn-sm btn-light btn-active-light-primary" for="bulk_prefill_none">Template kosong</label>
                        </div>
                        <button type="button" class="btn btn-sm btn-light-success ms-md-auto" id="btn_bulk_update_template" disabled>
                            <i class="fas fa-file-excel me-2"></i>Download Template
                        </button>
                    </div>
                    <div class="text-muted fs-8 mt-2" id="bulk_prefill_hint">Template diisi data item saat ini sehingga Anda cukup mengubah nilai yang perlu diganti.</div>
                </div>

                <!-- Langkah 3: upload -->
                <div class="bulk-step">
                    <div class="d-flex align-items-center mb-4">
                        <span class="bulk-step-number">3</span>
                        <div>
                            <div class="fw-bolder fs-6 text-gray-900">Upload file yang sudah diisi</div>
                            <div class="text-muted fs-8">Semua baris divalidasi dulu. Jika ada yang salah, tidak ada data yang diubah.</div>
                        </div>
                    </div>
                    <input type="file" class="form-control form-control-solid" id="bulk_update_file" accept=".xlsx,.xls" />
                    <div class="invalid-feedback d-block" id="bulk_update_file_error"></div>
                    <div class="alert alert-dismissible bg-light-danger border border-danger border-dashed d-none mt-4 mb-0 p-4" id="bulk_update_errors">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fas fa-exclamation-circle text-danger me-2"></i>
                            <span class="fw-bolder text-danger fs-7">Import dibatalkan, perbaiki baris berikut lalu upload ulang:</span>
                        </div>
                        <ul class="bulk-error-list mb-0 fs-7 text-gray-800" id="bulk_update_error_list"></ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer flex-center">
                <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btn_bulk_update_submit" disabled>
                    <i class="fas fa-upload me-2"></i>Proses Update
                </button>
            </div>
        </div>
    </div>
</div>
<!--end::Bulk Update Modal-->
@endif
@endsection

@push('scripts')
<script>
    const csrfToken  = '{{ csrf_token() }}';
    const dataUrl    = '{{ route('admin.masterdata.items.data') }}';
    const storeUrl   = '{{ route('admin.masterdata.items.store') }}';
    const updateTpl  = '{{ route('admin.masterdata.items.update', ':id') }}';
    const deleteTpl  = '{{ route('admin.masterdata.items.destroy', ':id') }}';
    const statusTpl  = '{{ route('admin.masterdata.items.status', ':id') }}';
    const showTpl    = '{{ route('admin.masterdata.items.show', ':id') }}';
    const importUrl  = '{{ route('admin.masterdata.items.import') }}';
    const bulkTemplateUrl = '{{ route('admin.masterdata.items.bulk-update.template') }}';
    const bulkImportUrl   = '{{ route('admin.masterdata.items.bulk-update.import') }}';
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
        const statusFilter   = document.getElementById('filter_item_status');
        const procurementFilter = document.getElementById('filter_item_procurement_source');
        const form           = document.getElementById('item_form');
        const modalEl        = document.getElementById('modal_item_form');
        const modal          = modalEl ? new bootstrap.Modal(modalEl) : null;
        const formSku        = document.getElementById('item_sku');
        const formName       = document.getElementById('item_name');
        const formCategory   = document.getElementById('item_category_id');
        const formProcurementSource = document.getElementById('item_procurement_source');
        const formId         = document.getElementById('item_id');
        const formDescription = document.getElementById('item_description');
        const formBaseUnit    = document.getElementById('item_base_unit_name');
        const formPackageUnit = document.getElementById('item_package_unit_name');
        const formBaseUom     = document.getElementById('item_base_uom_id');
        const formPackageUom  = document.getElementById('item_package_uom_id');
        const formPackageConversion = document.getElementById('item_package_conversion_qty');
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
                    {{-- tanpa atribut required: select2 menyembunyikan select asli sehingga
                         validasi bawaan browser memblokir submit tanpa pesan yang terlihat --}}
                    <select class="form-select form-select-solid component-item-select" data-name="component_item_id">
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
                        data: params => ({ q: params.term || '', is_active: '1', length: 20, start: 0 }),
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

        const syncUomNames = () => {
            if (formBaseUom && formBaseUnit) formBaseUnit.value = formBaseUom.selectedOptions[0]?.dataset.code || 'PCS';
            if (formPackageUom && formPackageUnit) formPackageUnit.value = formPackageUom.selectedOptions[0]?.dataset.code || '';
        };
        formBaseUom?.addEventListener('change', syncUomNames);
        formPackageUom?.addEventListener('change', syncUomNames);

        const errorIds = ['error_sku','error_name','error_category_id','error_procurement_source','error_address','error_description','error_safety_stock','error_is_bundle','error_components'];
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
        const escapeHtml = (value) => $('<div>').text(value ?? '').html();
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
            $(procurementFilter).select2({ placeholder: 'Semua Sumber', allowClear: true, width: '100%' })
                .on('select2:opening select2:closing select2:close', e => e.stopPropagation());
            $(statusFilter).select2({ placeholder: 'Semua Status', allowClear: true, width: '100%' })
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
                    params.is_active = statusFilter?.value ?? '';
                    params.procurement_source = procurementFilter?.value || '';
                },
            },
            columns: [
                { data: null, orderable: false, searchable: false, render: (d, t, r, m) => m.row + m.settings._iDisplayStart + 1 },
                {
                    data: 'name',
                    render: (value, type, row) => `
                        <div class="item-primary">
                            <div class="item-name fw-bolder">${escapeHtml(value)}</div>
                            <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                                <span class="badge badge-light-dark">${escapeHtml(row.sku)}</span>
                                <span class="badge ${row.procurement_source === 'import' ? 'badge-light-info' : 'badge-light-success'}">
                                    ${escapeHtml(row.procurement_source_label || 'Nanggewer (Produksi)')}
                                </span>
                                <span class="badge ${row.is_bundle ? 'badge-light-primary' : 'badge-light-secondary'}">
                                    ${row.is_bundle ? 'Bundle / Set' : 'Item Reguler'}
                                </span>
                                <span class="badge ${row.is_active ? 'badge-light-success' : 'badge-light-danger'}">
                                    ${row.is_active ? 'Aktif' : 'Nonaktif'}
                                </span>
                            </div>
                        </div>`
                },
                {
                    data: 'category',
                    render: (value, type, row) => `
                        <div>
                            <div class="fw-semibold text-gray-800 mb-1">${escapeHtml(value || 'Tanpa kategori')}</div>
                            <div class="mb-1"><span class="badge badge-light-info">Dasar: ${escapeHtml(row.base_unit || 'PCS')}</span>${row.package_unit ? ` <span class="badge badge-light-warning">Kemasan: ${escapeHtml(row.package_unit)}</span>` : ''}</div>
                            <div class="item-description">
                                ${row.description ? escapeHtml(row.description) : '<span class="text-muted">Tidak ada deskripsi</span>'}
                            </div>
                        </div>`
                },
                {
                    data: 'default_location',
                    render: (value, type, row) => `
                        <div class="warehouse-info">
                            <div class="mb-2">
                                <span class="text-muted me-2">Lokasi:</span>
                                <span class="fw-semibold text-gray-800">${value ? escapeHtml(value) : 'Belum diatur'}</span>
                            </div>
                            <div>
                                <span class="text-muted me-2">Safety stock:</span>
                                <span class="fw-bolder text-gray-900">${Number(row.default_safety_stock || 0).toLocaleString('id-ID')}</span>
                            </div>
                        </div>`
                },
                { data: 'id', orderable: false, searchable: false, className: 'text-end', render: (id, t, row) => {
                    const qrItem = `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-download-qr" data-sku="${escapeAttr(row.sku)}"><i class="fas fa-qrcode me-2"></i>Download QR</a></div>`;
                    const editItem = canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 btn-edit" data-id="${id}"><i class="fas fa-edit me-2"></i>Edit Item</a></div>` : '';
                    const statusItem = canUpdate ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 ${row.is_active ? 'text-danger' : 'text-success'} btn-toggle-status" data-id="${id}" data-active="${row.is_active ? '1' : '0'}"><i class="fas ${row.is_active ? 'fa-ban' : 'fa-check-circle'} me-2"></i>${row.is_active ? 'Nonaktifkan' : 'Aktifkan'}</a></div>` : '';
                    const delItem  = canDelete ? `<div class="menu-item px-3"><a href="#" class="menu-link px-3 text-danger btn-delete" data-id="${id}">Hapus</a></div>` : '';
                    const actions = `${qrItem}${editItem}${statusItem}${delItem}`;
                    if (!actions) return '';
                    return `<div class="text-end">
                        <a href="#" class="btn btn-sm btn-light-primary table-action-button" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            Kelola <i class="fas fa-chevron-down ms-2 fs-8"></i>
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

        let searchTimer;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadTable, 300);
        });
        applyBtn?.addEventListener('click', reloadTable);
        categoryFilter?.addEventListener('change', reloadTable);
        statusFilter?.addEventListener('change', reloadTable);
        procurementFilter?.addEventListener('change', reloadTable);
        limitSelect?.addEventListener('change', () => {
            dt.page.len(Number(limitSelect.value || 10)).draw();
        });
        resetBtn?.addEventListener('click', () => {
            if (categoryFilter) {
                categoryFilter.value = '';
                typeof $ !== 'undefined' && $(categoryFilter).data('select2') && $(categoryFilter).val('').trigger('change.select2');
            }
            if (statusFilter) {
                statusFilter.value = '';
                typeof $ !== 'undefined' && $(statusFilter).data('select2') && $(statusFilter).val('').trigger('change.select2');
            }
            if (procurementFilter) {
                procurementFilter.value = '';
                typeof $ !== 'undefined' && $(procurementFilter).data('select2') && $(procurementFilter).val('').trigger('change.select2');
            }
            if (limitSelect) { limitSelect.value = '10'; dt.page.len(10).draw(); }
            reloadTable();
        });

        // ── Create item ────────────────────────────────────────────────────────

        document.getElementById('btn_open_create_item')?.addEventListener('click', () => {
            if (!form) return;
            form.reset();
            formId.value = '';
            if (formProcurementSource) formProcurementSource.value = 'nanggewer';
            document.querySelectorAll('.warehouse-location').forEach(el => el.value = '');
            document.querySelectorAll('.warehouse-safety').forEach(el => el.value = 0);
            formBaseUnit && (formBaseUnit.value = 'PCS');
            formPackageUnit && (formPackageUnit.value = '');
            if (formBaseUom) formBaseUom.value = Array.from(formBaseUom.options).find(o => o.dataset.code === 'PCS')?.value || formBaseUom.options[0]?.value || '';
            if (formPackageUom) formPackageUom.value = '';
            syncUomNames();
            formPackageConversion && (formPackageConversion.value = 1);
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
                formProcurementSource && (formProcurementSource.value = json.procurement_source || 'nanggewer');
                formSku && (formSku.value = json.sku || '');
                formName && (formName.value = json.name || '');
                formDescription && (formDescription.value = json.description || '');
                document.querySelectorAll('.warehouse-setting-row').forEach(row => {
                    const setting = (json.warehouse_settings || []).find(value => String(value.warehouse_id) === String(row.dataset.warehouseId));
                    row.querySelector('.warehouse-location').value = setting?.location || '';
                    row.querySelector('.warehouse-safety').value = setting?.safety_stock ?? 0;
                });
                if (formBaseUom) formBaseUom.value = json.base_uom_id || Array.from(formBaseUom.options).find(o => o.dataset.code === (json.base_unit_name || 'PCS'))?.value || '';
                if (formPackageUom) formPackageUom.value = json.package_uom_id || Array.from(formPackageUom.options).find(o => o.dataset.code === (json.package_unit_name || ''))?.value || '';
                formBaseUnit && (formBaseUnit.value = json.base_unit_name || 'PCS');
                formPackageUnit && (formPackageUnit.value = json.package_unit_name || '');
                formPackageConversion && (formPackageConversion.value = json.package_conversion_qty || 1);
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

        tableEl.on('click', '.btn-toggle-status', async function(e) {
            e.preventDefault();
            const id = this.getAttribute('data-id');
            const currentlyActive = this.getAttribute('data-active') === '1';
            const actionLabel = currentlyActive ? 'menonaktifkan' : 'mengaktifkan';
            let confirmed = true;
            if (typeof Swal !== 'undefined') {
                const result = await Swal.fire({
                    title: currentlyActive ? 'Nonaktifkan item?' : 'Aktifkan item?',
                    text: currentlyActive
                        ? 'Item disembunyikan dari daftar stok dan laporan aktif secara default, tetapi saldo serta histori tetap tersimpan.'
                        : 'Item akan kembali tampil pada daftar stok dan laporan aktif.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: currentlyActive ? 'Ya, nonaktifkan' : 'Ya, aktifkan',
                    cancelButtonText: 'Batal',
                    buttonsStyling: false,
                    customClass: { confirmButton: `btn ${currentlyActive ? 'btn-danger' : 'btn-success'}`, cancelButton: 'btn btn-light' },
                });
                confirmed = result.isConfirmed;
            }
            if (!confirmed) return;

            try {
                const res = await fetch(statusTpl.replace(':id', id), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
                    body: new URLSearchParams({ _method: 'PATCH', is_active: currentlyActive ? '0' : '1' }),
                });
                const json = await res.json().catch(() => ({}));
                if (!res.ok) {
                    Swal?.fire('Error', Object.values(json.errors || {}).flat().join('\n') || json.message || `Gagal ${actionLabel} item.`, 'error');
                    return;
                }
                Swal?.fire('Berhasil', json.message || 'Status item berhasil diperbarui.', 'success');
                reloadTable(true);
            } catch (err) {
                console.error(err);
                Swal?.fire('Error', `Gagal ${actionLabel} item.`, 'error');
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
                closeSwal();
                if (!res.ok) {
                    const messages = Object.entries(json?.errors || {}).map(([key, msgs]) => {
                        const text = Array.isArray(msgs) ? msgs.join(', ') : String(msgs);
                        const errEl = document.getElementById(`error_${key}`);
                        if (errEl) errEl.textContent = text;
                        return text;
                    });
                    Swal?.fire('Error', messages.join('\n') || json.message || 'Gagal menyimpan item', 'error');
                    return;
                }
                Swal?.fire('Berhasil', json.message || 'Berhasil', 'success');
                modal?.hide();
                reloadTable(true);
            } catch (err) {
                console.error(err);
                closeSwal();
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

        // ── Update Massal ──────────────────────────────────────────────────────

        const bulkModalEl     = document.getElementById('modal_bulk_update_items');
        const bulkModal       = bulkModalEl ? bootstrap.Modal.getOrCreateInstance(bulkModalEl) : null;
        const bulkCheckboxes  = Array.from(document.querySelectorAll('.bulk-field-checkbox'));
        const bulkCounter     = document.getElementById('bulk_field_counter');
        const bulkTemplateBtn = document.getElementById('btn_bulk_update_template');
        const bulkSubmitBtn   = document.getElementById('btn_bulk_update_submit');
        const bulkFileInput   = document.getElementById('bulk_update_file');
        const bulkFileError   = document.getElementById('bulk_update_file_error');
        const bulkErrorBox    = document.getElementById('bulk_update_errors');
        const bulkErrorList   = document.getElementById('bulk_update_error_list');
        const bulkPrefillHint = document.getElementById('bulk_prefill_hint');
        const bulkStorageKey  = 'items.bulkUpdate.fields';
        const bulkPrefillHints = {
            all: 'Template diisi data item saat ini sehingga Anda cukup mengubah nilai yang perlu diganti.',
            filtered: 'Hanya item yang tampil sesuai pencarian & filter tabel saat ini yang dimasukkan ke template.',
            none: 'Template hanya berisi header. Isi SKU dan nilai baru secara manual.',
        };

        const selectedBulkFields = () => bulkCheckboxes.filter(cb => cb.checked);

        const refreshBulkState = () => {
            const selected = selectedBulkFields();
            bulkCheckboxes.forEach(cb => cb.closest('.bulk-field-option')?.classList.toggle('is-selected', cb.checked));
            if (bulkCounter) bulkCounter.textContent = `${selected.length} field dipilih`;
            if (bulkTemplateBtn) bulkTemplateBtn.disabled = selected.length === 0;
            if (bulkSubmitBtn) bulkSubmitBtn.disabled = selected.length === 0;
            try { localStorage.setItem(bulkStorageKey, JSON.stringify(selected.map(cb => cb.value))); } catch {}
        };

        const clearBulkErrors = () => {
            if (bulkFileError) bulkFileError.textContent = '';
            bulkErrorBox?.classList.add('d-none');
            if (bulkErrorList) bulkErrorList.innerHTML = '';
        };

        const showBulkErrors = (messages) => {
            if (!bulkErrorBox || !bulkErrorList) return;
            bulkErrorList.innerHTML = messages.map(msg => `<li>${escapeHtml(msg)}</li>`).join('');
            bulkErrorBox.classList.remove('d-none');
            bulkErrorBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        };

        try {
            const saved = JSON.parse(localStorage.getItem(bulkStorageKey) || '[]');
            if (Array.isArray(saved)) bulkCheckboxes.forEach(cb => { cb.checked = saved.includes(cb.value); });
        } catch {}
        refreshBulkState();

        bulkCheckboxes.forEach(cb => cb.addEventListener('change', refreshBulkState));
        document.getElementById('bulk_field_select_all')?.addEventListener('click', () => {
            bulkCheckboxes.forEach(cb => { cb.checked = true; });
            refreshBulkState();
        });
        document.getElementById('bulk_field_clear')?.addEventListener('click', () => {
            bulkCheckboxes.forEach(cb => { cb.checked = false; });
            refreshBulkState();
        });
        document.querySelectorAll('input[name="bulk_prefill"]').forEach(radio => radio.addEventListener('change', () => {
            if (bulkPrefillHint) bulkPrefillHint.textContent = bulkPrefillHints[radio.value] || '';
        }));

        bulkModalEl?.addEventListener('show.bs.modal', () => {
            if (bulkFileInput) bulkFileInput.value = '';
            clearBulkErrors();
        });
        bulkFileInput?.addEventListener('change', clearBulkErrors);

        bulkTemplateBtn?.addEventListener('click', () => {
            const fields = selectedBulkFields();
            if (!fields.length) return;
            const prefill = document.querySelector('input[name="bulk_prefill"]:checked')?.value || 'all';
            const params = new URLSearchParams();
            fields.forEach(cb => params.append('fields[]', cb.value));
            params.append('prefill', prefill);
            if (prefill === 'filtered') {
                params.append('q', searchInput?.value || '');
                params.append('category_id', categoryFilter?.value || '');
                params.append('is_active', statusFilter?.value ?? '');
                params.append('procurement_source', procurementFilter?.value || '');
            }
            window.location.href = `${bulkTemplateUrl}?${params.toString()}`;
        });

        bulkSubmitBtn?.addEventListener('click', async () => {
            clearBulkErrors();
            const fields = selectedBulkFields();
            if (!fields.length) return;
            const file = bulkFileInput?.files?.[0];
            if (!file) {
                if (bulkFileError) bulkFileError.textContent = 'Pilih file Excel terlebih dahulu.';
                return;
            }

            if (typeof Swal !== 'undefined') {
                const labels = fields.map(cb => `<span class="badge badge-light-primary m-1">${escapeHtml(cb.dataset.label)}</span>`).join('');
                const result = await Swal.fire({
                    title: 'Proses update massal?',
                    html: `<div class="text-gray-700 fs-6 mb-3">Field berikut akan diperbarui berdasarkan SKU di file:</div><div>${labels}</div>`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, proses',
                    cancelButtonText: 'Batal',
                    customClass: { confirmButton: 'btn btn-primary', cancelButton: 'btn btn-light' },
                    buttonsStyling: false,
                });
                if (!result.isConfirmed) return;
                Swal.fire({ title: 'Memproses...', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
            }

            const fd = new FormData();
            fd.append('file', file);
            fields.forEach(cb => fd.append('fields[]', cb.value));
            bulkSubmitBtn.disabled = true;
            try {
                const res = await fetch(bulkImportUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                    body: fd,
                });
                const json = await res.json().catch(() => null);
                closeSwal();
                if (!res.ok) {
                    const messages = json?.errors ? Object.values(json.errors).flat() : [json?.message || 'Gagal memproses update massal.'];
                    showBulkErrors(messages);
                    Swal?.fire('Update dibatalkan', 'Tidak ada data yang diubah. Periksa daftar kesalahan pada form update massal.', 'error');
                    return;
                }
                Swal?.fire({
                    title: 'Update massal selesai',
                    html: `<div class="fs-6"><span class="fw-bolder text-success">${json.updated}</span> item diperbarui, <span class="fw-bolder text-gray-700">${json.unchanged}</span> item tanpa perubahan.</div>`,
                    icon: 'success',
                });
                bulkModal?.hide();
                reloadTable(true);
            } catch (err) {
                console.error(err);
                closeSwal();
                Swal?.fire('Error', 'Gagal memproses update massal.', 'error');
            } finally {
                refreshBulkState();
            }
        });
    });
</script>
@endpush

@include('layouts.partials.form-submit-confirmation')
