@extends('layouts.admin')

@section('title', 'Detail Penyesuaian Stok')
@section('page_title', 'Detail Penyesuaian Stok')

@section('content')
@php
    $isApproved = ($adjustment->status ?? 'pending') === 'approved';
    $statusClass = $isApproved ? 'doc-status-approved' : 'doc-status-pending';
    $statusText = $isApproved ? 'Disetujui' : 'Menunggu Persetujuan';
@endphp

<style>
    .doc-paper { background: #fff; border: 1px solid #e4e6ef; border-radius: 8px; padding: 40px 48px; box-shadow: 0 2px 6px rgba(0,0,0,.04); font-family: 'Inter', Arial, sans-serif; color: #2e2e2e; }
    .doc-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1f2937; padding-bottom: 18px; margin-bottom: 24px; gap: 24px; }
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
    .doc-direction { display: inline-block; min-width: 68px; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-align: center; text-transform: uppercase; }
    .doc-direction-in { background: #dcfce7; color: #166534; }
    .doc-direction-out { background: #fee2e2; color: #991b1b; }
    @media (max-width: 767.98px) {
        .doc-paper { padding: 24px 18px; }
        .doc-header { flex-direction: column; }
        .doc-title { text-align: left; }
        .doc-meta { grid-template-columns: 1fr; }
        .doc-signature { grid-template-columns: 1fr; gap: 24px; }
    }
    @media print {
        .header, .toolbar, .aside, .card-toolbar, .btn, .scrolltop { display: none !important; }
        .content, .container, .container-fluid { padding: 0 !important; margin: 0 !important; max-width: none !important; }
        .doc-paper { border: 0; box-shadow: none; border-radius: 0; padding: 0; }
        body { background: #fff !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bolder mb-0 fs-3">{{ $adjustment->code }}</h2>
        <div class="text-muted fs-7">Dokumen penyesuaian stok</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
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
            <h2>Bukti Penyesuaian Stok</h2>
            <div class="code">{{ $adjustment->code }}</div>
            <div style="font-size:11.5px;color:#6b7280;margin-top:3px;">
                {{ $adjustment->transacted_at?->format('d F Y, H:i') ?: '-' }}
            </div>
        </div>
    </div>

    <div class="doc-meta">
        <div>
            <div class="label">No. Dokumen</div>
            <div class="value">{{ $adjustment->code }}</div>
        </div>
        <div>
            <div class="label">Status</div>
            <div class="value"><span class="doc-status-pill {{ $statusClass }}">{{ $statusText }}</span></div>
        </div>
        <div>
            <div class="label">Tanggal Penyesuaian</div>
            <div class="value">{{ $adjustment->transacted_at?->format('d/m/Y H:i') ?: '-' }}</div>
        </div>
        <div>
            <div class="label">Gudang</div>
            <div class="value">{{ $adjustment->warehouse?->name ?? '-' }}</div>
        </div>
        <div>
            <div class="label">Dibuat Oleh</div>
            <div class="value">{{ $adjustment->creator?->name ?? '-' }}</div>
        </div>
        <div>
            <div class="label">Disetujui Oleh</div>
            <div class="value">
                {{ $adjustment->approver?->name ?? '-' }}
                @if($adjustment->approved_at)
                    <div style="font-size:11px;color:#6b7280;font-weight:500;margin-top:2px;">
                        {{ $adjustment->approved_at->format('d/m/Y H:i') }}
                    </div>
                @endif
            </div>
        </div>
        <div>
            <div class="label">Total Selisih</div>
            <div class="value">{{ $netQty >= 0 ? '+' : '' }}{{ number_format($netQty) }} qty</div>
        </div>
    </div>

    @if($adjustment->note)
        <div class="doc-note">
            <div class="label">Catatan Dokumen</div>
            <div>{{ $adjustment->note }}</div>
        </div>
    @endif

    <div class="doc-section-title">Rincian Item</div>
    <table class="doc-table">
        <thead>
            <tr>
                <th style="width:42px">No</th>
                <th style="width:130px">SKU</th>
                <th>Nama Item</th>
                <th class="text-center" style="width:95px">Arah</th>
                <th class="text-end" style="width:130px">Qty Input</th>
                <th class="text-end" style="width:110px">Qty Dasar</th>
                <th>Catatan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($adjustment->items as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->item?->sku ?? '-' }}</td>
                    <td>{{ $row->item?->name ?? '-' }}</td>
                    <td class="text-center">
                        @if($row->direction === 'in')
                            <span class="doc-direction doc-direction-in">Tambah</span>
                        @else
                            <span class="doc-direction doc-direction-out">Kurangi</span>
                        @endif
                    </td>
                    <td class="text-end fw-bold">
                        {{ $row->direction === 'in' ? '+' : '-' }}{{ number_format((int) ($row->qty_input ?: $row->qty)) }}
                        {{ $row->unit?->name ?: 'satuan dasar' }}
                    </td>
                    <td class="text-end">{{ number_format((int) $row->qty) }}</td>
                    <td>{{ $row->note ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-muted">Tidak ada item.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5" style="text-align:right">Total Tambah (satuan dasar)</td>
                <td style="text-align:right">+{{ number_format($totalIn) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="5" style="text-align:right">Total Kurangi (satuan dasar)</td>
                <td style="text-align:right">-{{ number_format($totalOut) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="5" style="text-align:right">Selisih Bersih (satuan dasar)</td>
                <td style="text-align:right">{{ $netQty >= 0 ? '+' : '' }}{{ number_format($netQty) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="doc-signature">
        <div class="doc-sign-box">
            <div class="role">Dibuat Oleh</div>
            <div class="space"></div>
            <div class="name">{{ $adjustment->creator?->name ?? '..............................' }}</div>
        </div>
        <div class="doc-sign-box">
            <div class="role">Diperiksa Oleh</div>
            <div class="space"></div>
            <div class="name">..............................</div>
        </div>
        <div class="doc-sign-box">
            <div class="role">Disetujui Oleh</div>
            <div class="space"></div>
            <div class="name">{{ $adjustment->approver?->name ?? '..............................' }}</div>
        </div>
    </div>
</div>
@endsection
