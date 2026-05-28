@extends('layouts.admin')

@section('title', 'Detail Stock Opname - ' . $opname->code)
@section('page_title', 'Detail Stock Opname')

@php
    $statusCompleted = ($opname->status ?? 'open') === 'completed';
    $accuracy = $totalSku > 0 ? round((($totalSku - $diffSkuCount) / $totalSku) * 100, 2) : 100;
@endphp

@push('styles')
<style>
    .so-doc {
        max-width: 1000px;
        margin: 0 auto;
    }
    .so-doc-title {
        letter-spacing: .12em;
    }
    .so-doc-table th {
        background: #f9f9f9;
    }
    .so-doc-table th,
    .so-doc-table td {
        border: 1px solid #dfe1e6 !important;
        vertical-align: middle;
    }
    .so-meta-table td {
        padding: .35rem .25rem;
    }
    .so-sign-box {
        height: 90px;
    }

    @media print {
        #kt_header,
        #kt_toolbar,
        #kt_footer,
        .app-footer,
        .no-print {
            display: none !important;
        }
        body {
            background: #fff !important;
        }
        #kt_content_container,
        #kt_wrapper,
        .content {
            padding: 0 !important;
            margin: 0 !important;
        }
        .so-doc {
            max-width: 100% !important;
        }
        .card {
            box-shadow: none !important;
            border: none !important;
        }
        .so-doc-table th,
        .so-doc-table td {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
</style>
@endpush

@section('content')
<div class="so-doc">
    {{-- Toolbar (tidak ikut tercetak) --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-5 gap-2 no-print">
        <a href="{{ route('admin.inventory.stock-opname.index') }}" class="btn btn-light">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.inventory.stock-opname.export', $opname->id) }}" class="btn btn-light-success">
                <i class="fas fa-file-excel me-1"></i> Export Excel
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i> Cetak
            </button>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-8 p-lg-10">
            {{-- Kop dokumen --}}
            <div class="text-center border-bottom border-gray-300 pb-5 mb-6">
                <h1 class="fw-bolder fs-2x text-gray-900 mb-1 so-doc-title text-uppercase">Berita Acara Stock Opname</h1>
                <div class="text-muted fs-6">{{ config('app.name') }}</div>
            </div>

            {{-- Informasi batch --}}
            <div class="row mb-8">
                <div class="col-md-7">
                    <table class="w-100 fs-6 so-meta-table">
                        <tr>
                            <td class="text-gray-600 fw-semibold w-130px">Kode Dokumen</td>
                            <td class="fw-bold text-gray-900">: {{ $opname->code }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-600 fw-semibold">Tanggal</td>
                            <td class="text-gray-900">: {{ $opname->transacted_at?->format('d M Y H:i') ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-600 fw-semibold">Diinput oleh</td>
                            <td class="text-gray-900">: {{ $opname->creator?->name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="text-gray-600 fw-semibold">Disetujui oleh</td>
                            <td class="text-gray-900">: {{ $opname->completer?->name ?? '-' }}</td>
                        </tr>
                        @if($statusCompleted && $opname->completed_at)
                        <tr>
                            <td class="text-gray-600 fw-semibold">Tgl. Disetujui</td>
                            <td class="text-gray-900">: {{ $opname->completed_at?->format('d M Y H:i') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
                <div class="col-md-5 text-md-end mt-4 mt-md-0">
                    <div class="text-gray-600 fw-semibold fs-7 mb-1">STATUS</div>
                    @if($statusCompleted)
                        <span class="badge badge-success fs-6 px-4 py-2">Selesai</span>
                    @else
                        <span class="badge badge-warning fs-6 px-4 py-2">Berjalan</span>
                    @endif
                </div>
                @if($opname->note)
                <div class="col-12 mt-3">
                    <table class="w-100 fs-6 so-meta-table">
                        <tr>
                            <td class="text-gray-600 fw-semibold w-130px align-top">Catatan</td>
                            <td class="text-gray-900">: {{ $opname->note }}</td>
                        </tr>
                    </table>
                </div>
                @endif
            </div>

            {{-- Ringkasan --}}
            <div class="row g-4 mb-8">
                <div class="col-6 col-md-3">
                    <div class="border border-gray-300 rounded p-4 text-center h-100">
                        <div class="fs-7 fw-bold text-gray-500 mb-1 text-uppercase">Total SKU</div>
                        <div class="fs-2x fw-bolder text-gray-900">{{ number_format($totalSku) }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border border-danger rounded p-4 text-center h-100">
                        <div class="fs-7 fw-bold text-danger mb-1 text-uppercase">SKU Selisih</div>
                        <div class="fs-2x fw-bolder text-danger">{{ number_format($diffSkuCount) }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border border-success rounded p-4 text-center h-100">
                        <div class="fs-7 fw-bold text-success mb-1 text-uppercase">Selisih Lebih</div>
                        <div class="fs-2x fw-bolder text-success">{{ $plusSkuCount }} <span class="fs-6">SKU</span></div>
                        <div class="fs-7 text-success fw-semibold">+{{ number_format($plusQty) }} qty</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border border-warning rounded p-4 text-center h-100">
                        <div class="fs-7 fw-bold text-warning mb-1 text-uppercase">Selisih Kurang</div>
                        <div class="fs-2x fw-bolder text-warning">{{ $minusSkuCount }} <span class="fs-6">SKU</span></div>
                        <div class="fs-7 text-warning fw-semibold">{{ number_format($minusQty) }} qty</div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-6 mb-8 fs-6">
                <div>
                    <span class="text-gray-600 fw-semibold">Akurasi Stock Opname: </span>
                    <span class="fw-bolder {{ $accuracy >= 100 ? 'text-success' : ($accuracy >= 95 ? 'text-warning' : 'text-danger') }}">{{ $accuracy }}%</span>
                </div>
                <div>
                    <span class="text-gray-600 fw-semibold">Total Adjustment: </span>
                    <span class="fw-bolder {{ $totalAdjustment > 0 ? 'text-success' : ($totalAdjustment < 0 ? 'text-danger' : 'text-gray-900') }}">{{ $totalAdjustment > 0 ? '+' : '' }}{{ number_format($totalAdjustment) }}</span>
                </div>
            </div>

            {{-- Daftar SKU Selisih --}}
            <div class="mb-8">
                <h3 class="fw-bolder fs-4 text-gray-900 mb-3">
                    Daftar SKU Selisih
                    <span class="badge badge-light-danger ms-1">{{ $diffSkuCount }}</span>
                </h3>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle fs-6 mb-0 so-doc-table">
                        <thead>
                            <tr class="fw-bolder text-gray-700 text-uppercase fs-7">
                                <th class="w-40px text-center">No</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end w-90px">System</th>
                                <th class="text-end w-90px">Fisik</th>
                                <th class="text-end w-100px">Selisih</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($diffItems as $i => $row)
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td class="fw-semibold">{{ $row->item?->sku ?? '-' }}</td>
                                <td>{{ $row->item?->name ?? '-' }}</td>
                                <td class="text-end">{{ number_format((int) $row->system_qty) }}</td>
                                <td class="text-end">{{ number_format((int) $row->counted_qty) }}</td>
                                <td class="text-end fw-bolder {{ (int) $row->adjustment > 0 ? 'text-success' : 'text-danger' }}">
                                    {{ (int) $row->adjustment > 0 ? '+' : '' }}{{ number_format((int) $row->adjustment) }}
                                </td>
                                <td class="text-gray-600">{{ $row->note ?: '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-6">Tidak ada SKU yang selisih. Semua stok sesuai.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Rincian seluruh item --}}
            <div class="mb-8">
                <h3 class="fw-bolder fs-4 text-gray-900 mb-3">
                    Rincian Seluruh Item
                    <span class="badge badge-light ms-1">{{ $totalSku }}</span>
                </h3>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle fs-6 mb-0 so-doc-table">
                        <thead>
                            <tr class="fw-bolder text-gray-700 text-uppercase fs-7">
                                <th class="w-40px text-center">No</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end w-90px">System</th>
                                <th class="text-end w-90px">Fisik</th>
                                <th class="text-end w-100px">Selisih</th>
                                <th>Catatan</th>
                                <th>Diinput</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $i => $row)
                            <tr>
                                <td class="text-center">{{ $i + 1 }}</td>
                                <td class="fw-semibold">{{ $row->item?->sku ?? '-' }}</td>
                                <td>{{ $row->item?->name ?? '-' }}</td>
                                <td class="text-end">{{ number_format((int) $row->system_qty) }}</td>
                                <td class="text-end">{{ number_format((int) $row->counted_qty) }}</td>
                                <td class="text-end fw-bold {{ (int) $row->adjustment > 0 ? 'text-success' : ((int) $row->adjustment < 0 ? 'text-danger' : 'text-gray-500') }}">
                                    {{ (int) $row->adjustment > 0 ? '+' : '' }}{{ number_format((int) $row->adjustment) }}
                                </td>
                                <td class="text-gray-600">{{ $row->note ?: '-' }}</td>
                                <td class="text-gray-600">{{ $row->creator?->name ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-6">Tidak ada item.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Tanda tangan --}}
            <div class="row text-center mt-10 fs-6">
                <div class="col-4">
                    <div class="text-gray-700 mb-1">Dihitung oleh</div>
                    <div class="so-sign-box"></div>
                    <div class="border-top border-gray-400 pt-1 fw-semibold text-gray-900">
                        ( {{ $opname->creator?->name ?? '..........................' }} )
                    </div>
                </div>
                <div class="col-4">
                    <div class="text-gray-700 mb-1">Diperiksa oleh</div>
                    <div class="so-sign-box"></div>
                    <div class="border-top border-gray-400 pt-1 fw-semibold text-gray-900">
                        ( .......................... )
                    </div>
                </div>
                <div class="col-4">
                    <div class="text-gray-700 mb-1">Disetujui oleh</div>
                    <div class="so-sign-box"></div>
                    <div class="border-top border-gray-400 pt-1 fw-semibold text-gray-900">
                        ( {{ $opname->completer?->name ?? '..........................' }} )
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
