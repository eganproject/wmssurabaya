@extends('layouts.admin')

@section('title', $pageTitle)
@section('page_title', $pageTitle)

@section('content')
@php($isInboundReturn = ($transaction->type ?? '') === 'return' && str_contains($backUrl ?? '', '/inbound/'))
@php($isManualOutbound = ($transaction->type ?? '') === 'manual' && str_contains($backUrl ?? '', '/outbound/'))
@php($isOutboundReturn = ($transaction->type ?? '') === 'return' && str_contains($backUrl ?? '', '/outbound/'))
@php($isApproved = ($transaction->status ?? '') === 'approved')
@php($isFinalized = ($transaction->status ?? '') === 'finalized')
@php($suratJalan = $isManualOutbound ? ($transaction->suratJalan ?? null) : null)
@php($scanPlannedQty = $isManualOutbound ? (int) $transaction->items->sum('qty') : 0)
@php($scanScannedQty = $isManualOutbound ? (int) ($transaction->manualScanLogs ?? collect())->sum('qty') : 0)
@php($scanRemainingQty = max(0, $scanPlannedQty - $scanScannedQty))
@php($canScanManual = $isManualOutbound && \App\Support\Permission::can(auth()->user(), 'admin.outbound.manuals.scan'))

@if($isManualOutbound)
<style>
    .doc-paper { background: #fff; border: 1px solid #e4e6ef; border-radius: 8px; padding: 40px 48px; box-shadow: 0 2px 6px rgba(0,0,0,.04); font-family: 'Inter', Arial, sans-serif; color: #2e2e2e; }
    .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f2937; padding-bottom: 18px; margin-bottom: 24px; }
    .doc-company { font-size: 22px; font-weight: 800; letter-spacing: 1.5px; color: #111827; }
    .doc-company-sub { font-size: 12px; color: #6b7280; margin-top: 2px; }
    .doc-title { text-align: right; }
    .doc-title h2 { font-size: 18px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; color: #1a56db; margin: 0; }
    .doc-title .code { font-size: 14px; font-weight: 600; margin-top: 4px; color: #111827; }
    .doc-meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 32px; margin-bottom: 28px; padding: 18px 22px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; }
    .doc-meta .label { font-size: 11px; color: #6b7280; text-transform: uppercase; font-weight: 700; letter-spacing: .5px; }
    .doc-meta .value { font-size: 13.5px; color: #111827; margin-top: 3px; font-weight: 600; }
    .doc-section-title { font-size: 13px; font-weight: 800; color: #1f2937; text-transform: uppercase; letter-spacing: 1px; margin: 22px 0 10px; padding-bottom: 6px; border-bottom: 1px solid #e5e7eb; }
    .doc-table { width: 100%; border-collapse: collapse; }
    .doc-table thead tr { background: #1f2937; color: #fff; }
    .doc-table thead th { padding: 10px 12px; text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; }
    .doc-table thead th.text-center { text-align: center; }
    .doc-table thead th.text-end { text-align: right; }
    .doc-table tbody tr { border-bottom: 1px solid #e5e7eb; }
    .doc-table tbody tr:nth-child(even) { background: #fafafa; }
    .doc-table tbody td { padding: 10px 12px; font-size: 13px; vertical-align: top; }
    .doc-table tfoot tr { background: #f3f4f6; border-top: 2px solid #1f2937; }
    .doc-table tfoot td { padding: 10px 12px; font-weight: 700; font-size: 13px; }
    .doc-note { background: #fefce8; border: 1px solid #facc15; border-radius: 6px; padding: 12px 16px; margin: 18px 0; font-size: 12.5px; }
    .doc-note .label { font-weight: 700; margin-bottom: 4px; color: #854d0e; }
    .doc-signature { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 36px; margin-top: 48px; }
    .doc-sign-box { text-align: center; }
    .doc-sign-box .role { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #4b5563; }
    .doc-sign-box .space { height: 64px; }
    .doc-sign-box .name { border-top: 1px solid #1f2937; padding-top: 6px; font-size: 12.5px; color: #1f2937; font-weight: 600; }
    .doc-status-pill { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
    .doc-status-approved { background: #d1fae5; color: #065f46; }
    .doc-status-pending { background: #fef3c7; color: #92400e; }
    .doc-source-tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
    .doc-source-regular { background: #e0f2fe; color: #075985; }
    .doc-source-damaged { background: #fee2e2; color: #991b1b; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bolder mb-0 fs-3">{{ $pageTitle }}</h2>
        <div class="text-muted fs-7">Detail dokumen outbound manual</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if(!$isApproved && $canScanManual)
            <a href="{{ route('admin.outbound.manuals.scan', $transaction->id) }}" class="btn btn-primary">
                <i class="fas fa-barcode me-1"></i>
                {{ ($transaction->scan_status ?? 'not_started') === 'in_progress' ? 'Lanjut Scan' : 'Mulai Scan' }}
            </a>
        @endif
        @if($isApproved)
            @if($suratJalan)
                <a href="{{ route('admin.outbound.manuals.surat-jalan', $transaction->id) }}" target="_blank" class="btn btn-light-success">
                    <i class="fas fa-file-alt me-1"></i> Lihat Surat Jalan
                </a>
            @else
                <button type="button" class="btn btn-light-primary" id="btn_generate_sj">
                    <i class="fas fa-file-alt me-1"></i> Buat Surat Jalan
                </button>
            @endif
        @endif
        <button type="button" class="btn btn-light-info" onclick="window.print()">
            <i class="fas fa-print me-1"></i> Cetak
        </button>
        <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
    </div>
</div>

<div class="doc-paper">
    <div class="doc-header">
        <div>
            <div class="doc-company">WAREHOUSE 29</div>
            <div class="doc-company-sub">Sistem Manajemen Gudang</div>
        </div>
        <div class="doc-title">
            <h2>Bukti Pengeluaran Barang</h2>
            <div class="code">{{ $transaction->code }}</div>
            <div style="font-size:11.5px;color:#6b7280;margin-top:3px;">
                {{ $transaction->transacted_at?->format('d F Y, H:i') }}
            </div>
        </div>
    </div>

    <div class="doc-meta">
        <div>
            <div class="label">No. Transaksi</div>
            <div class="value">{{ $transaction->code }}</div>
        </div>
        <div>
            <div class="label">Ref. No</div>
            <div class="value">{{ $transaction->ref_no ?: '-' }}</div>
        </div>
        <div>
            <div class="label">Status</div>
            <div class="value">
                @if($isApproved)
                    <span class="doc-status-pill doc-status-approved">Disetujui</span>
                @else
                    <span class="doc-status-pill doc-status-pending">Menunggu Persetujuan</span>
                @endif
            </div>
        </div>
        <div>
            <div class="label">Tanggal Transaksi</div>
            <div class="value">{{ $transaction->transacted_at?->format('d/m/Y H:i') ?: '-' }}</div>
        </div>
        <div>
            <div class="label">Dibuat Oleh</div>
            <div class="value">{{ $transaction->creator?->name ?? '-' }}</div>
        </div>
        <div>
            <div class="label">Disetujui Oleh</div>
            <div class="value">
                {{ $transaction->approver?->name ?? '-' }}
                @if($transaction->approved_at)
                    <div style="font-size:11px;color:#6b7280;font-weight:500;margin-top:2px;">
                        {{ $transaction->approved_at->format('d/m/Y H:i') }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if($transaction->note)
    <div class="doc-note">
        <div class="label">Catatan Transaksi</div>
        <div>{{ $transaction->note }}</div>
    </div>
    @endif

    @if($suratJalan)
    <div class="doc-note" style="background:#dcfce7;border-color:#22c55e;">
        <div class="label" style="color:#14532d;">Surat Jalan: {{ $suratJalan->code }}</div>
        <div>
            Dibuat {{ $suratJalan->created_at?->format('d/m/Y H:i') }} oleh {{ $suratJalan->creator?->name ?? '-' }}
            <a href="{{ route('admin.outbound.manuals.surat-jalan', $transaction->id) }}" target="_blank" class="ms-2 fw-bold">Lihat / Cetak &raquo;</a>
        </div>
    </div>
    @endif

    <div class="doc-note" style="background:#eff6ff;border-color:#93c5fd;">
        <div class="label" style="color:#1d4ed8;">Status Scan</div>
        <div>
            @if(($transaction->scan_status ?? 'not_started') === 'complete')
                <strong>Complete</strong>
                @if($transaction->scan_completed_at)
                    - {{ $transaction->scan_completed_at->format('d/m/Y H:i') }}
                    oleh {{ $transaction->scanCompleter?->name ?? '-' }}
                @endif
            @elseif(($transaction->scan_status ?? 'not_started') === 'in_progress')
                <strong>In Progress</strong>
            @else
                <strong>Belum mulai</strong>
            @endif
            <div style="margin-top:4px;">
                Scan {{ number_format($scanScannedQty) }} dari {{ number_format($scanPlannedQty) }} qty.
                Sisa {{ number_format($scanRemainingQty) }} qty.
            </div>
        </div>
    </div>

    <div class="doc-section-title">Rincian Item</div>
    <table class="doc-table">
        <thead>
            <tr>
                <th style="width:42px">No</th>
                <th style="width:130px">SKU</th>
                <th>Nama Item</th>
                <th class="text-center" style="width:90px">Qty</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $i => $row)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $row->item?->sku ?? '-' }}</td>
                <td>{{ $row->item?->name ?? '-' }}</td>
                <td class="text-center fw-bold">{{ $row->qty }}</td>
                <td>{{ $row->note ?: '-' }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right">Total Qty</td>
                <td style="text-align:center">{{ $totalQty }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="doc-signature">
        <div class="doc-sign-box">
            <div class="role">Dibuat Oleh</div>
            <div class="space"></div>
            <div class="name">{{ $transaction->creator?->name ?? '..............................' }}</div>
        </div>
        <div class="doc-sign-box">
            <div class="role">Disetujui Oleh</div>
            <div class="space"></div>
            <div class="name">{{ $transaction->approver?->name ?? '..............................' }}</div>
        </div>
        <div class="doc-sign-box">
            <div class="role">Diterima Oleh</div>
            <div class="space"></div>
            <div class="name">..............................</div>
        </div>
    </div>
</div>
@else

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title">
            <div class="d-flex flex-column">
                <div class="fw-bolder fs-5">{{ $transaction->code }}</div>
                <div class="text-muted fs-7">{{ $transaction->transacted_at?->format('Y-m-d H:i') }}</div>
            </div>
        </div>
        <div class="card-toolbar d-flex gap-2">
            <a href="{{ $backUrl }}" class="btn btn-light">Kembali</a>
        </div>
    </div>
    <div class="card-body py-6">
        <div class="row mb-6">
            <div class="col-md-3">
                <div class="fw-bold text-gray-600">Ref No</div>
                <div>{{ $transaction->ref_no ?? '-' }}</div>
            </div>
            <div class="col-md-3">
                <div class="fw-bold text-gray-600">Status</div>
                <div>
                    @if($isFinalized)
                        <span class="badge badge-light-success">Finalisasi</span>
                    @elseif($isApproved && $isInboundReturn)
                        <span class="badge badge-light-primary">Gudang Retur</span>
                    @elseif($isApproved)
                        <span class="badge badge-light-success">Disetujui</span>
                    @else
                        <span class="badge badge-light-warning">Menunggu</span>
                    @endif
                </div>
            </div>
            <div class="col-md-3">
                <div class="fw-bold text-gray-600">Catatan</div>
                <div>{{ $transaction->note ?? '-' }}</div>
            </div>
            <div class="col-md-3">
                <div class="fw-bold text-gray-600">Total Qty</div>
                <div>{{ $totalQty }}</div>
            </div>
        </div>

        @if($isInboundReturn && $isApproved)
            <div class="alert alert-primary d-flex align-items-center p-5 mb-6">
                <i class="fas fa-warehouse fs-2 me-4"></i>
                <div>
                    <div class="fw-bold">Stok Gudang Retur: {{ number_format($totalQty) }} qty</div>
                    <div class="text-muted">Barang retur ini belum finalisasi, sehingga belum masuk stok reguler maupun stok barang rusak.</div>
                </div>
            </div>
        @endif

        @if($isInboundReturn && $isFinalized)
            <div class="alert alert-success d-flex align-items-center p-5 mb-6">
                <i class="fas fa-check-circle fs-2 me-4"></i>
                <div>
                    <div class="fw-bold">Retur sudah finalisasi</div>
                    <div class="text-muted">
                        Finalisasi: {{ $transaction->finalized_at?->format('Y-m-d H:i') ?? '-' }}
                        oleh {{ $transaction->finalizer?->name ?? '-' }}.
                    </div>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table align-middle table-row-dashed fs-6 gy-5">
                <thead>
                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                        <th>Item</th>
                        @if($isInboundReturn)
                            <th>Qty Diterima</th>
                            <th>Qty Bagus</th>
                            <th>Qty Rusak</th>
                        @else
                            <th>Qty</th>
                        @endif
                        @if($isOutboundReturn)
                            <th>Sumber Stok</th>
                        @endif
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transaction->items as $row)
                        <tr>
                            <td>{{ $row->item?->sku }} - {{ $row->item?->name }}</td>
                            @if($isInboundReturn)
                                <td>{{ $row->qty_received ?: $row->qty }}</td>
                                <td>{{ $row->qty_good ?? 0 }}</td>
                                <td>{{ $row->qty_damaged ?? 0 }}</td>
                            @else
                                <td>{{ $row->qty }}</td>
                            @endif
                            @if($isOutboundReturn)
                                <td>
                                    @if(($row->stock_source ?? 'regular') === 'damaged')
                                        <span class="badge badge-light-danger">Gudang Rusak</span>
                                    @else
                                        <span class="badge badge-light-info">Gudang Reguler</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $row->note ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection

@push('scripts')
@if($isManualOutbound && $isApproved && !$suratJalan)
<script>
    document.getElementById('btn_generate_sj')?.addEventListener('click', async function() {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Membuat...';

        try {
            const res = await fetch('{{ route('admin.outbound.manuals.surat-jalan.generate', $transaction->id) }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            });
            const json = await res.json();
            if (!res.ok) {
                if (typeof Swal !== 'undefined') Swal.fire('Error', json.message || 'Gagal membuat surat jalan', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-alt me-1"></i> Buat Surat Jalan';
                return;
            }
            if (typeof Swal !== 'undefined') {
                await Swal.fire('Berhasil', 'Surat jalan <strong>' + json.code + '</strong> berhasil dibuat', 'success');
            }
            window.location.reload();
        } catch (err) {
            if (typeof Swal !== 'undefined') Swal.fire('Error', 'Gagal membuat surat jalan', 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-file-alt me-1"></i> Buat Surat Jalan';
        }
    });
</script>
@endif
@endpush
