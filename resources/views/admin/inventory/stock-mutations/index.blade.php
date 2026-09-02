@extends('layouts.admin')

@section('title', 'Mutasi Stok')
@section('page_title', 'Mutasi Stok')

@section('content')
<style>
    .mutation-filters { gap: .75rem; }
    .mutation-filters .search-box { min-width: 260px; flex: 1 1 320px; }
    .mutation-filters .warehouse-filter { min-width: 210px; }
    .mutation-table tbody tr { transition: background-color .15s ease; }
    .mutation-table tbody tr:hover { background: #f8fafc; }
    .mutation-table td { padding-top: 1.15rem !important; padding-bottom: 1.15rem !important; }
    .mutation-item { min-width: 260px; }
    .mutation-item-name { color: #181c32; font-size: .98rem; line-height: 1.4; }
    .movement-value { font-size: 1.05rem; line-height: 1.3; }
    .stock-balance { min-width: 180px; }
    .stock-balance-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
    .stock-balance-row + .stock-balance-row { margin-top: .45rem; padding-top: .45rem; border-top: 1px dashed #e4e6ef; }
    .source-info { min-width: 210px; max-width: 300px; }
    #export_excel .export-loading { display: none; }
    #export_excel.is-loading .export-default { display: none; }
    #export_excel.is-loading .export-loading { display: inline-flex; align-items: center; }
    @media (max-width: 991.98px) {
        .mutation-filters > * { flex: 1 1 210px; }
    }
    @media (max-width: 575.98px) {
        .mutation-filters > * { width: 100% !important; max-width: none !important; flex-basis: 100%; }
    }
</style>

<div class="card card-flush">
    <div class="card-header border-0 pt-6 pb-3">
        <div class="card-title d-block">
            <h2 class="fw-bolder text-gray-900 mb-1">Riwayat Pergerakan Stok</h2>
            <div class="text-muted fs-7">Setiap perubahan stok dilengkapi saldo sebelum, saldo sesudah, dan dokumen sumber.</div>
        </div>
    </div>

    <div class="card-body pt-2">
        <div class="d-flex flex-wrap align-items-center mutation-filters mb-6">
            <div class="position-relative search-box">
                <i class="fas fa-search position-absolute top-50 translate-middle-y ms-5 text-gray-500"></i>
                <input type="text" class="form-control form-control-solid ps-12" placeholder="Cari item, kode, atau petugas..." data-kt-filter="search" />
            </div>

            <select class="form-select form-select-solid warehouse-filter" id="filter_warehouse">
                <option value="">Semua Gudang</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                @endforeach
            </select>

            <input type="text" class="form-control form-control-solid w-150px" id="filter_date_from" placeholder="Tanggal mulai" />
            <input type="text" class="form-control form-control-solid w-150px" id="filter_date_to" placeholder="Tanggal akhir" />

            <button type="button" class="btn btn-primary" id="filter_apply">
                <i class="fas fa-filter me-1"></i>Terapkan
            </button>
            <button type="button" class="btn btn-light" id="filter_reset">Reset</button>
            <button type="button" class="btn btn-success" id="export_excel">
                <span class="export-default">
                    <i class="fas fa-file-excel me-1"></i>Export Excel
                </span>
                <span class="export-loading">
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Menyiapkan Excel...
                </span>
            </button>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed mutation-table fs-6 gy-4" id="stock_mutations_table">
                <thead>
                    <tr class="text-start text-gray-600 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Waktu & Petugas</th>
                        <th>Item & Gudang</th>
                        <th>Mutasi</th>
                        <th>Saldo</th>
                        <th>Referensi</th>
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
    const dataUrl = '{{ route('admin.inventory.stock-mutations.data') }}';
    const exportUrl = '{{ route('admin.inventory.stock-mutations.export') }}';
    const detailUrlTpl = '{{ route('admin.inventory.stock-mutations.show', ':id') }}';

    document.addEventListener('DOMContentLoaded', () => {
        const tableEl = $('#stock_mutations_table');
        const searchInput = document.querySelector('[data-kt-filter="search"]');
        const dateFromEl = document.getElementById('filter_date_from');
        const dateToEl = document.getElementById('filter_date_to');
        const filterApplyBtn = document.getElementById('filter_apply');
        const filterResetBtn = document.getElementById('filter_reset');
        const exportExcelBtn = document.getElementById('export_excel');
        const warehouseEl = document.getElementById('filter_warehouse');
        let fpFrom = null;
        let fpTo = null;

        if (!tableEl.length || !$.fn.DataTable) {
            console.error('DataTables unavailable');
            return;
        }

        const escapeHtml = value => $('<div>').text(value ?? '').html();
        const formatNumber = value => Number(value || 0).toLocaleString('id-ID');
        const formatStock = value => value === null || value === undefined
            ? '-'
            : Number(value).toLocaleString('id-ID');

        if (typeof flatpickr !== 'undefined') {
            fpFrom = dateFromEl ? flatpickr(dateFromEl, { dateFormat: 'Y-m-d', allowInput: true }) : null;
            fpTo = dateToEl ? flatpickr(dateToEl, { dateFormat: 'Y-m-d', allowInput: true }) : null;
        }

        const dt = tableEl.DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            order: [],
            pageLength: 10,
            ajax: {
                url: dataUrl,
                dataSrc: 'data',
                data: params => {
                    params.q = searchInput?.value || '';
                    if (warehouseEl?.value) params.warehouse_id = warehouseEl.value;
                    if (dateFromEl?.value) params.date_from = dateFromEl.value;
                    if (dateToEl?.value) params.date_to = dateToEl.value;
                }
            },
            columns: [
                {
                    data: 'occurred_at',
                    render: (value, type, row) => `
                        <div class="text-nowrap fw-semibold text-gray-900">${escapeHtml(value || '-')}</div>
                        <div class="text-gray-600 mt-2">
                            <i class="fas fa-user-circle me-1 text-gray-400"></i>${escapeHtml(row.user || '-')}
                        </div>`
                },
                {
                    data: 'item',
                    render: (value, type, row) => `
                        <div class="mutation-item">
                            <div class="mutation-item-name fw-bolder">${escapeHtml(value)}</div>
                            <div class="text-gray-600 mt-2">
                                <i class="fas fa-warehouse me-1 text-gray-400"></i>${escapeHtml(row.warehouse)}
                            </div>
                        </div>`
                },
                {
                    data: 'qty',
                    render: (value, type, row) => {
                        const incoming = row.direction === 'IN';
                        const unitText = row.unit
                            ? `${formatNumber(row.qty_input)} ${escapeHtml(row.unit)}`
                            : `${formatNumber(value)} unit`;
                        return `<span class="badge ${incoming ? 'badge-light-success' : 'badge-light-danger'}">
                                <i class="fas ${incoming ? 'fa-arrow-down' : 'fa-arrow-up'} me-1"></i>
                                ${incoming ? 'Masuk' : 'Keluar'}
                            </span>
                            <div class="movement-value fw-bolder ${incoming ? 'text-success' : 'text-danger'} mt-2">
                                ${incoming ? '+' : '-'}${unitText}
                            </div>
                            ${row.unit && Number(row.qty_input) !== Number(value)
                                ? `<div class="text-gray-600 mt-1">Setara ${formatNumber(value)} satuan dasar</div>`
                                : ''}`;
                    }
                },
                {
                    data: 'stock_before',
                    render: (value, type, row) => `
                        <div class="stock-balance">
                            <div class="stock-balance-row">
                                <span class="text-gray-600">Sebelum</span>
                                <span class="fw-semibold text-gray-800">${formatStock(value)}</span>
                            </div>
                            <div class="stock-balance-row">
                                <span class="text-gray-600">Sesudah</span>
                                <span class="fw-bolder text-primary">${formatStock(row.stock_after)}</span>
                            </div>
                        </div>`
                },
                {
                    data: 'source',
                    render: (value, type, row) => `
                        <div class="source-info">
                            <div class="fw-semibold text-gray-800">${escapeHtml(value || '-')}</div>
                            <div class="mt-2">
                                <span class="badge badge-light-dark">${escapeHtml(row.source_code || 'Tanpa kode')}</span>
                            </div>
                            ${row.note ? `<div class="text-gray-600 mt-2">${escapeHtml(row.note)}</div>` : ''}
                        </div>`
                },
                {
                    data: 'id',
                    orderable: false,
                    searchable: false,
                    className: 'text-end',
                    render: id => `<a href="${detailUrlTpl.replace(':id', id)}" class="btn btn-sm btn-light-primary">
                        <i class="fas fa-file-alt me-1"></i>Lihat Bukti
                    </a>`
                },
            ],
            language: {
                emptyTable: 'Belum ada mutasi stok.',
                zeroRecords: 'Mutasi stok tidak ditemukan.',
                processing: 'Memuat data...',
            }
        });

        const reloadTable = () => dt.ajax.reload();
        let searchTimer;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(reloadTable, 300);
        });
        filterApplyBtn?.addEventListener('click', reloadTable);
        warehouseEl?.addEventListener('change', reloadTable);
        const exportErrorMessage = async response => {
            if (response.status === 401 || response.status === 419 || response.redirected) {
                return 'Sesi Anda telah berakhir. Silakan muat ulang halaman dan login kembali.';
            }

            try {
                const payload = await response.clone().json();
                if (payload?.message) return payload.message;
            } catch (error) {
                // Respons bukan JSON; gunakan pesan berdasarkan status HTTP.
            }

            if (response.status === 403) return 'Anda tidak memiliki akses untuk export laporan ini.';
            if (response.status >= 500) return 'Server gagal menyiapkan file Excel. Silakan coba kembali.';

            return `Export gagal diproses (HTTP ${response.status}).`;
        };

        const showExportError = message => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Export Excel gagal',
                    text: message,
                    confirmButtonText: 'Tutup',
                });
                return;
            }

            window.alert(message);
        };

        const downloadBlob = (blob, filename) => {
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = objectUrl;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
        };

        const responseFilename = response => {
            const disposition = response.headers.get('Content-Disposition') || '';
            const utf8Match = disposition.match(/filename\*=UTF-8''([^;]+)/i);
            const regularMatch = disposition.match(/filename="?([^";]+)"?/i);
            const encodedName = utf8Match?.[1] || regularMatch?.[1];

            if (!encodedName) return 'laporan-mutasi-stok.xlsx';

            try {
                return decodeURIComponent(encodedName.trim());
            } catch (error) {
                return encodedName.trim();
            }
        };

        exportExcelBtn?.addEventListener('click', async () => {
            if (exportExcelBtn.classList.contains('is-loading')) return;

            const params = new URLSearchParams();
            if (searchInput?.value) params.set('q', searchInput.value);
            if (warehouseEl?.value) params.set('warehouse_id', warehouseEl.value);
            if (dateFromEl?.value) params.set('date_from', dateFromEl.value);
            if (dateToEl?.value) params.set('date_to', dateToEl.value);

            exportExcelBtn.classList.add('is-loading');
            exportExcelBtn.disabled = true;
            exportExcelBtn.setAttribute('aria-busy', 'true');

            try {
                const query = params.toString();
                const response = await fetch(`${exportUrl}${query ? `?${query}` : ''}`, {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const contentType = response.headers.get('Content-Type') || '';
                const isExcel = contentType.includes('spreadsheetml') || contentType.includes('application/octet-stream');
                if (!response.ok || !isExcel) {
                    throw new Error(await exportErrorMessage(response));
                }

                const blob = await response.blob();
                if (!blob.size) throw new Error('File Excel yang diterima kosong.');

                downloadBlob(blob, responseFilename(response));
            } catch (error) {
                showExportError(error?.message || 'Export Excel gagal. Silakan coba kembali.');
            } finally {
                exportExcelBtn.classList.remove('is-loading');
                exportExcelBtn.disabled = false;
                exportExcelBtn.removeAttribute('aria-busy');
            }
        });
        filterResetBtn?.addEventListener('click', () => {
            if (fpFrom) fpFrom.clear(); else if (dateFromEl) dateFromEl.value = '';
            if (fpTo) fpTo.clear(); else if (dateToEl) dateToEl.value = '';
            if (warehouseEl) warehouseEl.value = '';
            if (searchInput) searchInput.value = '';
            reloadTable();
        });
    });
</script>
@endpush
