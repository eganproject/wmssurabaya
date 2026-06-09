@extends('layouts.admin')

@section('title', 'Kartu Stok: ' . $item->sku)
@section('page_title', 'Kartu Stok Item')

@section('content')
@php
    $baseUnitName = $baseUnit?->name ?? 'PCS';
    $isBulkWarehouse = $warehouse->type === \App\Models\Warehouse::TYPE_BULK;
    $statusLabel = match($stockStatus) {
        'empty' => 'Stok Habis',
        'low' => 'Stok Menipis',
        default => 'Stok Aman',
    };
    $statusClass = match($stockStatus) {
        'empty' => 'danger',
        'low' => 'warning',
        default => 'success',
    };
    $sourceLabel = fn ($type) => match($type) {
        'inbound' => 'Penerimaan',
        'outbound' => 'Pengeluaran',
        'adjustment' => 'Penyesuaian Stok',
        'opname' => 'Stock Opname',
        'transfer' => 'Transfer Gudang',
        'damaged' => 'Barang Rusak',
        'picker', 'qc' => 'QC Scan',
        'qc_resi' => 'QC Resi',
        default => ucfirst($type ?: '-'),
    };
@endphp

<style>
    .document-shell { max-width: 1240px; margin: 0 auto; }
    .stock-document { background: #fff; border: 1px solid #d9dce3; box-shadow: 0 4px 18px rgba(31, 38, 135, .06); }
    .document-header { border-bottom: 3px double #181c32; }
    .document-title { letter-spacing: .08em; }
    .document-meta td { padding: .25rem .5rem; vertical-align: top; }
    .document-meta td:first-child { width: 130px; color: #7e8299; }
    .summary-cell { border: 1px solid #e4e6ef; min-height: 92px; }
    .ledger-table th { background: #f5f8fa; color: #3f4254; border-color: #cfd3da !important; white-space: nowrap; }
    .ledger-table td { border-color: #e4e6ef !important; vertical-align: top; }
    .signature-box { min-height: 115px; }
    .signature-line { width: 170px; border-top: 1px solid #181c32; margin: 60px auto 0; }
    @media (max-width: 767.98px) {
        .stock-document { border-radius: .75rem; }
        .document-meta td:first-child { width: 105px; }
        .document-toolbar .btn { flex: 1 1 auto; }
    }
    @media print {
        body { background: #fff !important; }
        .document-toolbar, .header, .aside, .footer, .scrolltop { display: none !important; }
        .content, .post, .container, .container-fluid { padding: 0 !important; margin: 0 !important; max-width: none !important; }
        .stock-document { border: 0; box-shadow: none; }
        .stock-document .p-6, .stock-document .p-lg-10 { padding: 12px !important; }
        .ledger-table { font-size: 10px !important; }
        .page-break-inside-avoid { break-inside: avoid; }
        @page { size: A4 landscape; margin: 10mm; }
    }
</style>

<div class="document-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5 document-toolbar">
        <div>
            <div class="fw-bolder fs-3 text-gray-900">Kartu Stok Item</div>
            <div class="text-muted fs-7">Dokumen riwayat saldo untuk {{ $warehouse->name }}</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('admin.inventory.item-stocks.index', ['warehouse_id' => $warehouse->id]) }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-1"></i>Kembali
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Cetak Dokumen
            </button>
        </div>
    </div>

    <article class="stock-document rounded p-6 p-lg-10">
        <header class="document-header pb-6 mb-7">
            <div class="row align-items-center g-5">
                <div class="col-md-8">
                    <div class="fw-bolder fs-2 text-gray-900">WAREHOUSE MANAGEMENT SYSTEM</div>
                    <div class="text-muted fs-7 mt-1">{{ $warehouse->name }} · {{ $warehouse->code }}</div>
                </div>
                <div class="col-md-4 text-md-end">
                    <h1 class="document-title fw-bolder fs-2 text-gray-900 mb-1">KARTU STOK</h1>
                    <div class="text-muted">No. KS-{{ str_pad($item->id, 6, '0', STR_PAD_LEFT) }}</div>
                </div>
            </div>
        </header>

        <section class="row g-6 mb-7 page-break-inside-avoid">
            <div class="col-lg-7">
                <h2 class="fw-bolder fs-4 text-gray-900 mb-4">{{ $item->name }}</h2>
                <table class="document-meta w-100 fs-6">
                    <tr><td>SKU</td><td class="fw-semibold">: {{ $item->sku }}</td></tr>
                    <tr><td>Kategori</td><td>: {{ $item->category?->name ?? '-' }}</td></tr>
                    <tr><td>Jenis Item</td><td>: {{ $isBundle ? 'Bundle / Set' : 'Item Reguler' }}</td></tr>
                    <tr><td>Lokasi Rak</td><td>: {{ $warehouseLocation ?: 'Belum ditentukan' }}</td></tr>
                    <tr><td>Satuan Dasar</td><td>: {{ $baseUnitName }}</td></tr>
                    @if($packageUnit)
                        <tr><td>Satuan Koli</td><td>: 1 {{ $packageUnit->name }} = {{ number_format($packageConversion) }} {{ $baseUnitName }}</td></tr>
                    @endif
                </table>
            </div>
            <div class="col-lg-5">
                <div class="border rounded p-5 h-100 bg-light-{{ $statusClass }}">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="text-gray-700 fw-bold">SALDO SAAT INI</span>
                        <span class="badge badge-{{ $statusClass }}">{{ $statusLabel }}</span>
                    </div>
                    @if($isBulkWarehouse && $packageUnit)
                        <div class="fw-bolder fs-2x text-gray-900">
                            {{ number_format(intdiv((int) $currentStock, $packageConversion)) }} {{ $packageUnit->name }}
                        </div>
                        @if($currentStock % $packageConversion)
                            <div class="fw-semibold text-gray-700 mt-1">
                                + {{ number_format($currentStock % $packageConversion) }} {{ $baseUnitName }} sisa
                            </div>
                        @endif
                        <div class="text-muted fs-7 mt-2">Total {{ number_format($currentStock) }} {{ $baseUnitName }}</div>
                    @else
                        <div class="fw-bolder fs-2x text-gray-900">{{ number_format($currentStock) }} {{ $baseUnitName }}</div>
                        @if($isBundle)<div class="text-muted fs-7 mt-2">Stok virtual berdasarkan komponen bundle</div>@endif
                    @endif
                    <div class="separator my-4"></div>
                    <div class="d-flex justify-content-between fs-7">
                        <span class="text-muted">Batas minimum</span>
                        <span class="fw-bold">{{ number_format($warehouseSafetyStock) }} {{ $baseUnitName }}</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="row g-4 mb-8 page-break-inside-avoid">
            <div class="col-6 col-lg-3">
                <div class="summary-cell rounded p-4">
                    <div class="text-muted fs-7 fw-bold">TOTAL MUTASI</div>
                    <div class="fw-bolder fs-3 mt-2">{{ number_format($mutationCount) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="summary-cell rounded p-4">
                    <div class="text-success fs-7 fw-bold">TOTAL MASUK</div>
                    <div class="fw-bolder fs-3 text-success mt-2">+{{ number_format($totalIn) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="summary-cell rounded p-4">
                    <div class="text-danger fs-7 fw-bold">TOTAL KELUAR</div>
                    <div class="fw-bolder fs-3 text-danger mt-2">-{{ number_format($totalOut) }}</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="summary-cell rounded p-4">
                    <div class="text-muted fs-7 fw-bold">MUTASI TERAKHIR</div>
                    <div class="fw-bold fs-6 mt-2">{{ $lastMutation?->occurred_at?->format('d/m/Y H:i') ?? '-' }}</div>
                </div>
            </div>
        </section>

        <section>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h3 class="fw-bolder fs-4 text-gray-900 mb-1">Jurnal Mutasi Stok</h3>
                    <div class="text-muted fs-8">Nilai saldo dicatat dalam satuan dasar item.</div>
                </div>
                <form method="GET" class="document-toolbar">
                    <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">
                    <select name="per_page" class="form-select form-select-sm form-select-solid w-auto" onchange="this.form.submit()">
                        @foreach([10, 20, 50, 100] as $n)
                            <option value="{{ $n }}" @selected(request('per_page', 20) == $n)>{{ $n }} baris</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered ledger-table fs-7">
                    <thead>
                        <tr>
                            <th class="text-center">No.</th>
                            <th>Tanggal</th>
                            <th>Dokumen / Referensi</th>
                            <th class="text-end">Masuk</th>
                            <th class="text-end">Keluar</th>
                            <th class="text-end">Stok Sebelum</th>
                            <th class="text-end">Stok Sesudah</th>
                            <th>Petugas / Catatan</th>
                            <th class="document-toolbar text-center">Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($mutations as $mutation)
                            <tr>
                                <td class="text-center">{{ $mutations->firstItem() + $loop->index }}</td>
                                <td class="text-nowrap">{{ $mutation->occurred_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $sourceLabel($mutation->source_type) }}</div>
                                    <div class="text-muted">{{ $mutation->source_code ?: '-' }}</div>
                                </td>
                                <td class="text-end fw-bold text-success">
                                    {{ $mutation->direction === 'in' ? number_format($mutation->qty) : '-' }}
                                </td>
                                <td class="text-end fw-bold text-danger">
                                    {{ $mutation->direction === 'out' ? number_format($mutation->qty) : '-' }}
                                </td>
                                <td class="text-end">{{ is_null($mutation->stock_before) ? '-' : number_format($mutation->stock_before) }}</td>
                                <td class="text-end fw-bold">{{ is_null($mutation->stock_after) ? '-' : number_format($mutation->stock_after) }}</td>
                                <td>
                                    <div>{{ $mutation->creator?->name ?? '-' }}</div>
                                    @if($mutation->unit && $mutation->qty_input)
                                        <div class="text-muted">{{ number_format($mutation->qty_input) }} {{ $mutation->unit->name }}</div>
                                    @endif
                                    @if($mutation->note)<div class="text-muted">{{ $mutation->note }}</div>@endif
                                </td>
                                <td class="document-toolbar text-center">
                                    <a href="{{ route('admin.inventory.stock-mutations.show', $mutation->id) }}" class="btn btn-sm btn-light-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-8">Belum ada riwayat mutasi stok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($mutations->hasPages())
                <div class="document-toolbar d-flex justify-content-between align-items-center flex-wrap gap-3 mt-5">
                    <div class="text-muted fs-7">
                        Menampilkan {{ $mutations->firstItem() }}-{{ $mutations->lastItem() }} dari {{ $mutations->total() }} mutasi
                    </div>
                    {{ $mutations->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </section>

        <footer class="row g-6 mt-8 page-break-inside-avoid">
            <div class="col-6 text-center signature-box">
                <div class="fw-semibold">Petugas Gudang</div>
                <div class="signature-line"></div>
                <div class="text-muted fs-8 mt-2">Nama dan tanda tangan</div>
            </div>
            <div class="col-6 text-center signature-box">
                <div class="fw-semibold">Diperiksa Oleh</div>
                <div class="signature-line"></div>
                <div class="text-muted fs-8 mt-2">Nama dan tanda tangan</div>
            </div>
            <div class="col-12 text-center text-muted fs-8">
                Dicetak pada {{ now()->format('d/m/Y H:i') }} · Dokumen ini dihasilkan oleh sistem.
            </div>
        </footer>
    </article>
</div>
@endsection
