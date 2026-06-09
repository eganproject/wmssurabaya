@extends('layouts.admin')

@section('title', 'Bukti Mutasi #' . $mutation->id)
@section('page_title', 'Bukti Mutasi Stok')

@section('content')
@php
    $isIncoming = $mutation->direction === 'in';
    $directionLabel = $isIncoming ? 'Stok Masuk' : 'Stok Keluar';
    $directionClass = $isIncoming ? 'success' : 'danger';
    $unitName = $mutation->unit?->name ?: 'satuan dasar';
    $sourceName = $sourceSummary['label'] ?? strtoupper($mutation->source_type ?: '-');
@endphp

<style>
    .document-shell { max-width: 1040px; margin: 0 auto; }
    .mutation-document { background: #fff; border: 1px solid #d9dce3; box-shadow: 0 4px 18px rgba(31, 38, 135, .06); }
    .document-header { border-bottom: 3px double #181c32; }
    .document-title { letter-spacing: .08em; }
    .info-table td { padding: .35rem .5rem; vertical-align: top; }
    .info-table td:first-child { width: 145px; color: #7e8299; }
    .stock-flow-box { border: 1px solid #e4e6ef; background: #f9fafb; }
    .stock-flow-value { font-size: 1.65rem; line-height: 1.2; }
    .source-table th { background: #f5f8fa; color: #3f4254; border-color: #cfd3da !important; }
    .source-table td { border-color: #e4e6ef !important; }
    .signature-box { min-height: 115px; }
    .signature-line { width: 170px; border-top: 1px solid #181c32; margin: 60px auto 0; }
    @media (max-width: 575.98px) {
        .stock-arrow { transform: rotate(90deg); margin: .5rem 0; }
        .document-toolbar .btn { flex: 1 1 auto; }
    }
    @media print {
        body { background: #fff !important; }
        .document-toolbar, .header, .aside, .footer, .scrolltop { display: none !important; }
        .content, .post, .container, .container-fluid { padding: 0 !important; margin: 0 !important; max-width: none !important; }
        .mutation-document { border: 0; box-shadow: none; }
        .mutation-document .p-6, .mutation-document .p-lg-10 { padding: 12px !important; }
        .page-break-inside-avoid { break-inside: avoid; }
        @page { size: A4 portrait; margin: 12mm; }
    }
</style>

<div class="document-shell">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5 document-toolbar">
        <div>
            <div class="fw-bolder fs-3 text-gray-900">Bukti Mutasi Stok</div>
            <div class="text-muted fs-7">Rincian perubahan saldo dan transaksi sumber.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ $backUrl }}" class="btn btn-light">
                <i class="fas fa-arrow-left me-1"></i>Kembali
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>Cetak Dokumen
            </button>
        </div>
    </div>

    <article class="mutation-document rounded p-6 p-lg-10">
        <header class="document-header pb-6 mb-7">
            <div class="row align-items-center g-5">
                <div class="col-md-7">
                    <div class="fw-bolder fs-2 text-gray-900">WAREHOUSE MANAGEMENT SYSTEM</div>
                    <div class="text-muted fs-7 mt-1">{{ $mutation->warehouse?->name ?? '-' }}</div>
                </div>
                <div class="col-md-5 text-md-end">
                    <h1 class="document-title fw-bolder fs-2 text-gray-900 mb-1">BUKTI MUTASI</h1>
                    <div class="text-muted">No. MUT-{{ str_pad($mutation->id, 7, '0', STR_PAD_LEFT) }}</div>
                </div>
            </div>
        </header>

        <section class="row g-6 mb-7 page-break-inside-avoid">
            <div class="col-md-7">
                <h2 class="fw-bolder fs-4 text-gray-900 mb-4">
                    {{ $mutation->item?->name ?? 'Item tidak ditemukan' }}
                </h2>
                <table class="info-table w-100">
                    <tr><td>SKU Item</td><td class="fw-semibold">: {{ $mutation->item?->sku ?? '-' }}</td></tr>
                    <tr><td>Tanggal Mutasi</td><td>: {{ $mutation->occurred_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
                    <tr><td>Gudang</td><td>: {{ $mutation->warehouse?->name ?? '-' }}</td></tr>
                    <tr><td>Jenis Mutasi</td><td>: <span class="badge badge-light-{{ $directionClass }}">{{ $directionLabel }}</span></td></tr>
                    <tr><td>Petugas</td><td>: {{ $mutation->creator?->name ?? '-' }}</td></tr>
                </table>
            </div>
            <div class="col-md-5">
                <div class="border rounded p-5 h-100">
                    <div class="text-muted fs-7 fw-bold mb-2">DOKUMEN SUMBER</div>
                    <div class="fw-bolder fs-5 text-gray-900">{{ $sourceName }}</div>
                    <div class="badge badge-light mt-3">{{ $mutation->source_code ?: ($sourceSummary['code'] ?? 'Tanpa kode') }}</div>
                    @if($mutation->source_subtype)
                        <div class="text-muted fs-7 mt-3">Subjenis: {{ ucfirst($mutation->source_subtype) }}</div>
                    @endif
                </div>
            </div>
        </section>

        <section class="stock-flow-box rounded p-5 p-md-7 mb-7 page-break-inside-avoid">
            <div class="text-center fw-bolder text-gray-700 mb-5">PERUBAHAN SALDO STOK</div>
            <div class="d-flex flex-column flex-sm-row align-items-center justify-content-center gap-4 gap-md-7">
                <div class="text-center">
                    <div class="text-muted fs-7 mb-2">STOK SEBELUM</div>
                    <div class="stock-flow-value fw-bolder text-gray-900">
                        {{ is_null($mutation->stock_before) ? '-' : number_format($mutation->stock_before) }}
                    </div>
                </div>
                <i class="stock-arrow fas fa-long-arrow-alt-right fs-2 text-gray-400"></i>
                <div class="text-center px-5 py-3 rounded bg-light-{{ $directionClass }}">
                    <div class="text-{{ $directionClass }} fs-7 fw-bold">{{ strtoupper($directionLabel) }}</div>
                    <div class="stock-flow-value fw-bolder text-{{ $directionClass }} mt-1">
                        {{ $isIncoming ? '+' : '-' }}{{ number_format($mutation->qty) }}
                    </div>
                    <div class="text-muted fs-8">satuan dasar</div>
                </div>
                <i class="stock-arrow fas fa-long-arrow-alt-right fs-2 text-gray-400"></i>
                <div class="text-center">
                    <div class="text-muted fs-7 mb-2">STOK SESUDAH</div>
                    <div class="stock-flow-value fw-bolder text-primary">
                        {{ is_null($mutation->stock_after) ? '-' : number_format($mutation->stock_after) }}
                    </div>
                </div>
            </div>

            <div class="separator my-5"></div>
            <div class="row g-4 text-center">
                <div class="col-sm-4">
                    <div class="text-muted fs-8">JUMLAH INPUT</div>
                    <div class="fw-bold mt-1">{{ number_format($mutation->qty_input ?? $mutation->qty) }} {{ $unitName }}</div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted fs-8">KONVERSI</div>
                    <div class="fw-bold mt-1">1 {{ $unitName }} = {{ number_format($mutation->conversion_qty ?: 1) }} dasar</div>
                </div>
                <div class="col-sm-4">
                    <div class="text-muted fs-8">TOTAL DASAR</div>
                    <div class="fw-bold mt-1">{{ number_format($mutation->qty) }}</div>
                </div>
            </div>
        </section>

        <section class="mb-7">
            <h3 class="fw-bolder fs-4 text-gray-900 mb-4">Rincian Transaksi Sumber</h3>
            @if($sourceSummary)
                <div class="row g-4 mb-5 page-break-inside-avoid">
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted fs-8 fw-bold">JENIS</div>
                        <div class="fw-semibold mt-1">{{ $sourceSummary['label'] ?? '-' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted fs-8 fw-bold">KODE</div>
                        <div class="fw-semibold mt-1">{{ $sourceSummary['code'] ?? '-' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted fs-8 fw-bold">REFERENSI</div>
                        <div class="fw-semibold mt-1">{{ $sourceSummary['ref'] ?? '-' }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="text-muted fs-8 fw-bold">TANGGAL</div>
                        <div class="fw-semibold mt-1">
                            {{ !empty($sourceSummary['date']) ? \Illuminate\Support\Carbon::parse($sourceSummary['date'])->format('d/m/Y H:i') : '-' }}
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered source-table fs-7">
                        <thead>
                            <tr>
                                <th class="text-center">No.</th>
                                <th>Item</th>
                                <th class="text-end">Qty</th>
                                <th>Informasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sourceItems as $row)
                                <tr>
                                    <td class="text-center">{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $row['label'] ?? '-' }}</div>
                                        @if(!empty($row['meta']))<div class="text-muted">{{ $row['meta'] }}</div>@endif
                                    </td>
                                    <td class="text-end fw-bold">{{ number_format($row['qty'] ?? 0) }}</td>
                                    <td>{{ $row['note'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-6">Tidak ada rincian item sumber.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="alert alert-secondary">
                    Dokumen sumber tidak ditemukan atau sudah tidak tersedia. Data mutasi utama tetap tersimpan.
                </div>
            @endif
        </section>

        <section class="border rounded p-5 page-break-inside-avoid">
            <div class="text-muted fs-8 fw-bold mb-2">CATATAN MUTASI</div>
            <div>{{ $mutation->note ?: ($sourceSummary['note'] ?? 'Tidak ada catatan.') }}</div>
        </section>

        <footer class="row g-6 mt-8 page-break-inside-avoid">
            <div class="col-6 text-center signature-box">
                <div class="fw-semibold">Dibuat Oleh</div>
                <div class="signature-line"></div>
                <div class="fw-semibold mt-2">{{ $mutation->creator?->name ?? 'Petugas Gudang' }}</div>
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
