@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<style>
    .dash-wrap {
        --dash-blue: #2563eb;
        --dash-green: #059669;
        --dash-amber: #d97706;
        --dash-red: #dc2626;
        --dash-slate: #475569;
    }

    /* ---------- Section heading ---------- */
    .dash-section-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
    }
    .dash-section-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 17px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.01em;
    }
    .dash-section-title i {
        color: var(--dash-blue);
        font-size: 16px;
    }
    .dash-section-sub {
        font-size: 12.5px;
        color: #64748b;
        margin-top: 2px;
    }
    .dash-legend {
        font-size: 11.5px;
        color: #94a3b8;
        margin-top: 6px;
        line-height: 1.6;
    }

    /* ---------- Date filter ---------- */
    .dash-filter {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 6px 8px 6px 12px;
    }
    .dash-filter-label {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }
    .dash-filter input {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        height: 34px;
        max-width: 140px;
        font-size: 13px;
    }

    /* ---------- Stat cards ---------- */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 16px;
    }
    .stat-card {
        position: relative;
        display: flex;
        align-items: center;
        gap: 16px;
        border: 1px solid #e9edf3;
        border-radius: 16px;
        padding: 18px;
        background: #fff;
        overflow: hidden;
        transition: transform 0.18s ease, box-shadow 0.18s ease;
    }
    .stat-card::before {
        content: "";
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: var(--accent, var(--dash-blue));
    }
    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.09);
    }
    .stat-icon {
        flex-shrink: 0;
        width: 52px;
        height: 52px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        color: var(--accent, var(--dash-blue));
        background: var(--accent-soft, rgba(37, 99, 235, 0.1));
    }
    .stat-body { min-width: 0; }
    .stat-label {
        display: flex;
        align-items: center;
        font-size: 11.5px;
        color: #64748b;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .stat-value {
        font-size: 30px;
        font-weight: 800;
        line-height: 1.1;
        color: #0f172a;
        letter-spacing: -0.02em;
    }
    .stat-meta {
        font-size: 11.5px;
        color: #94a3b8;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .stat-card--blue  { --accent: var(--dash-blue);  --accent-soft: rgba(37, 99, 235, 0.1); }
    .stat-card--green { --accent: var(--dash-green); --accent-soft: rgba(5, 150, 105, 0.1); }
    .stat-card--amber { --accent: var(--dash-amber); --accent-soft: rgba(217, 119, 6, 0.12); }
    .stat-card--red   { --accent: var(--dash-red);   --accent-soft: rgba(220, 38, 38, 0.1); }

    .stat-value-row {
        display: flex;
        align-items: baseline;
        gap: 9px;
        margin-top: 4px;
    }
    .stat-percent {
        font-size: 12px;
        font-weight: 800;
        padding: 2px 9px;
        border-radius: 999px;
        color: var(--accent, var(--dash-blue));
        background: var(--accent-soft, rgba(37, 99, 235, 0.1));
        white-space: nowrap;
    }
    .stat-progress {
        height: 6px;
        border-radius: 999px;
        background: #eef2f7;
        overflow: hidden;
        margin-top: 11px;
    }
    .stat-progress-bar {
        height: 100%;
        border-radius: 999px;
        background: var(--accent, var(--dash-blue));
        transition: width 0.4s ease;
    }
    .stat-progress-text {
        font-size: 11.5px;
        color: #64748b;
        font-weight: 600;
        margin-top: 6px;
    }

    /* ---------- Kurir cards ---------- */
    .kurir-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 16px;
    }
    @media (max-width: 991px) { .kurir-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 575px) { .kurir-grid { grid-template-columns: 1fr; } }

    .kurir-card {
        display: flex;
        flex-direction: column;
        border: 1px solid #e9edf3;
        border-radius: 16px;
        padding: 18px;
        background: #fff;
        transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
    }
    .kurir-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 28px rgba(15, 23, 42, 0.09);
        border-color: #dbe3ee;
    }
    .kurir-card-top {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .kurir-avatar {
        flex-shrink: 0;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        color: var(--dash-blue);
        background: linear-gradient(135deg, rgba(37, 99, 235, 0.12), rgba(37, 99, 235, 0.05));
    }
    .kurir-info { min-width: 0; flex: 1; }
    .kurir-name {
        font-weight: 800;
        font-size: 15px;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .kurir-updated {
        font-size: 11px;
        color: #94a3b8;
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 2px;
    }
    .kurir-ratio {
        display: flex;
        align-items: baseline;
        gap: 4px;
        margin-top: 14px;
    }
    .ratio-resi { font-size: 30px; font-weight: 800; color: var(--dash-blue); letter-spacing: -0.02em; }
    .ratio-scan { font-size: 30px; font-weight: 800; color: var(--dash-green); letter-spacing: -0.02em; }
    .ratio-sep  { font-size: 22px; font-weight: 600; color: #cbd5e1; }
    .ratio-caption {
        font-size: 11px;
        color: #94a3b8;
        font-weight: 600;
        margin-left: 4px;
    }
    .kurir-progress {
        height: 7px;
        border-radius: 999px;
        background: #eef2f7;
        overflow: hidden;
        margin-top: 12px;
    }
    .kurir-progress-bar {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #10b981, #059669);
        transition: width 0.4s ease;
    }
    .kurir-progress-text {
        font-size: 11px;
        color: #64748b;
        font-weight: 600;
        margin-top: 6px;
    }
    .kurir-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }
    .chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
    }
    .chip-amber  { background: rgba(217, 119, 6, 0.12);  color: #b45309; }
    .chip-red    { background: rgba(220, 38, 38, 0.1);   color: #b91c1c; }
    .chip-green  { background: rgba(5, 150, 105, 0.12);  color: #047857; }
    .kurir-detail-btn {
        margin-top: 16px;
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 9px 14px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        color: var(--dash-blue);
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    }
    .kurir-detail-btn:hover {
        background: var(--dash-blue);
        border-color: var(--dash-blue);
        color: #fff;
    }
    .kurir-detail-btn i { transition: transform 0.15s ease; }
    .kurir-detail-btn:hover i { transform: translateX(3px); }

    /* ---------- Modal filter cards ---------- */
    .filter-card-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
    }
    @media (max-width: 575px) { .filter-card-grid { grid-template-columns: repeat(2, 1fr); } }
    .filter-card {
        display: flex;
        align-items: center;
        gap: 11px;
        text-align: left;
        padding: 12px;
        border-radius: 13px;
        border: 1.5px solid #e9edf3;
        background: #fff;
        cursor: pointer;
        transition: border-color 0.15s ease, background 0.15s ease, transform 0.12s ease;
    }
    .filter-card:hover { transform: translateY(-2px); border-color: #cbd5e1; }
    .filter-card-icon {
        flex-shrink: 0;
        width: 38px;
        height: 38px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        color: var(--fc-color, var(--dash-slate));
        background: var(--fc-soft, rgba(71, 85, 105, 0.1));
    }
    .filter-card-body { min-width: 0; }
    .filter-card-label {
        font-size: 10.5px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .filter-card-value {
        font-size: 22px;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.15;
    }
    .filter-card.active {
        border-color: var(--fc-color, var(--dash-slate));
        background: var(--fc-soft, rgba(71, 85, 105, 0.08));
    }
    .filter-card[data-status="all"]      { --fc-color: var(--dash-blue);  --fc-soft: rgba(37, 99, 235, 0.1); }
    .filter-card[data-status="scanned"]  { --fc-color: var(--dash-green); --fc-soft: rgba(5, 150, 105, 0.1); }
    .filter-card[data-status="pending"]  { --fc-color: var(--dash-amber); --fc-soft: rgba(217, 119, 6, 0.12); }
    .filter-card[data-status="canceled"] { --fc-color: var(--dash-red);   --fc-soft: rgba(220, 38, 38, 0.1); }

    /* ---------- Modal search ---------- */
    .modal-search {
        position: relative;
        max-width: 340px;
    }
    .modal-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        font-size: 13px;
    }
    .modal-search input {
        width: 100%;
        height: 38px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0 12px 0 34px;
        font-size: 13px;
    }
    .modal-search input:focus {
        outline: none;
        border-color: var(--dash-blue);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    /* ---------- Modal table ---------- */
    #kurir_detail_table thead th {
        background: #f8fafc;
        font-size: 10.5px;
    }
    #kurir_detail_table tbody td { vertical-align: middle; }
    .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        white-space: nowrap;
    }
    .status-pill--scanned  { background: rgba(5, 150, 105, 0.12); color: #047857; }
    .status-pill--pending  { background: rgba(217, 119, 6, 0.12); color: #b45309; }
    .status-pill--canceled { background: rgba(220, 38, 38, 0.1);  color: #b91c1c; }
    .sku-list {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }
    .sku-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 3px 8px;
        border-radius: 7px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        font-size: 11.5px;
        font-weight: 600;
        color: #334155;
    }
    .sku-chip i { color: #94a3b8; font-size: 10px; }
    .sku-chip .sku-qty {
        font-weight: 800;
        color: var(--dash-blue);
    }
    .sku-total {
        font-size: 10.5px;
        color: #94a3b8;
        font-weight: 600;
        margin-top: 4px;
    }
    .mono { font-variant-numeric: tabular-nums; }

    .info-tip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 15px;
        height: 15px;
        border-radius: 999px;
        border: 1px solid #cbd5e1;
        color: #94a3b8;
        cursor: help;
        margin-left: 6px;
    }
    .info-tip i { font-size: 8px; }
</style>

<div class="dash-wrap">
    {{-- ============ Ringkasan Resi ============ --}}
    <div class="card mb-6">
        <div class="card-body">
            <div class="dash-section-head mb-5">
                <div>
                    <div class="dash-section-title"><i class="fa-solid fa-chart-pie"></i> Ringkasan Resi</div>
                    <div class="dash-section-sub">Performa scan out untuk tanggal {{ $today ?? '-' }}</div>
                    <div class="dash-legend">
                        Resi aktif = status selain cancel &nbsp;&middot;&nbsp; Rasio = resi aktif / scan out &nbsp;&middot;&nbsp; Resi cancel tidak dihitung sebagai resi aktif.
                    </div>
                </div>
                <form class="dash-filter" method="GET" action="{{ url()->current() }}">
                    <span class="dash-filter-label"><i class="fa-regular fa-calendar"></i> Tanggal</span>
                    <input type="text" name="date" id="filter_date" value="{{ $today ?? '' }}" autocomplete="off" />
                    <button type="submit" class="btn btn-sm btn-primary">Terapkan</button>
                    @if(request()->has('date'))
                        <a href="{{ url()->current() }}" class="btn btn-sm btn-light">Reset</a>
                    @endif
                </form>
            </div>

            @php
                $totalResiVal = (int) ($totalResi ?? 0);
                $totalQcScanVal = (int) ($totalQcScan ?? 0);
                $totalScanVal = (int) ($totalScanOut ?? 0);
                $totalCancelVal = (int) ($totalResiCanceled ?? 0);
                $grandTotal = $totalResiVal + $totalCancelVal;
                $activePercent = $grandTotal > 0 ? round($totalResiVal / $grandTotal * 100) : 0;
                $qcScanPercent = $totalResiVal > 0 ? min(100, round($totalQcScanVal / $totalResiVal * 100)) : 0;
                $scanPercent = $totalResiVal > 0 ? min(100, round($totalScanVal / $totalResiVal * 100)) : 0;
                $cancelPercent = $grandTotal > 0 ? round($totalCancelVal / $grandTotal * 100) : 0;
            @endphp
            <div class="stats-grid">
                <div class="stat-card stat-card--blue">
                    <div class="stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">
                            Total Resi Aktif
                            <span class="info-tip" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah resi dengan status selain cancel pada tanggal ini.">
                                <i class="fa-solid fa-info"></i>
                            </span>
                        </div>
                        <div class="stat-value-row">
                            <span class="stat-value">{{ number_format($totalResiVal) }}</span>
                            <span class="stat-percent">{{ $activePercent }}%</span>
                        </div>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: {{ $activePercent }}%"></div>
                        </div>
                        <div class="stat-progress-text">{{ $activePercent }}% dari {{ number_format($grandTotal) }} total resi</div>
                        <div class="stat-meta"><i class="fa-regular fa-clock"></i> Update {{ $totalResiUpdated ?? '-' }}</div>
                    </div>
                </div>
                <div class="stat-card stat-card--amber">
                    <div class="stat-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">
                            Total QC Scan
                            <span class="info-tip" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah resi pada tanggal upload ini yang sudah dilakukan QC scan.">
                                <i class="fa-solid fa-info"></i>
                            </span>
                        </div>
                        <div class="stat-value-row">
                            <span class="stat-value">{{ number_format($totalQcScanVal) }}</span>
                            <span class="stat-percent">{{ $qcScanPercent }}%</span>
                        </div>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: {{ $qcScanPercent }}%"></div>
                        </div>
                        <div class="stat-progress-text">{{ $qcScanPercent }}% resi aktif sudah QC scan</div>
                        <div class="stat-meta"><i class="fa-regular fa-clock"></i> Update {{ $totalQcScanUpdated ?? '-' }}</div>
                    </div>
                </div>
                <div class="stat-card stat-card--green">
                    <div class="stat-icon"><i class="fa-solid fa-barcode"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">
                            Total Scan Out
                            <span class="info-tip" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah resi (berdasarkan tanggal upload) yang sudah scan out.">
                                <i class="fa-solid fa-info"></i>
                            </span>
                        </div>
                        <div class="stat-value-row">
                            <span class="stat-value">{{ number_format($totalScanVal) }}</span>
                            <span class="stat-percent">{{ $scanPercent }}%</span>
                        </div>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: {{ $scanPercent }}%"></div>
                        </div>
                        <div class="stat-progress-text">{{ $scanPercent }}% resi aktif sudah scan out</div>
                        <div class="stat-meta"><i class="fa-regular fa-clock"></i> Update {{ $totalScanUpdated ?? '-' }}</div>
                    </div>
                </div>
                <div class="stat-card stat-card--red">
                    <div class="stat-icon"><i class="fa-solid fa-ban"></i></div>
                    <div class="stat-body">
                        <div class="stat-label">
                            Total Resi Cancel
                            <span class="info-tip" data-bs-toggle="tooltip" data-bs-placement="top" title="Jumlah resi yang dibatalkan pada tanggal ini.">
                                <i class="fa-solid fa-info"></i>
                            </span>
                        </div>
                        <div class="stat-value-row">
                            <span class="stat-value text-danger">{{ number_format($totalCancelVal) }}</span>
                            <span class="stat-percent">{{ $cancelPercent }}%</span>
                        </div>
                        <div class="stat-progress">
                            <div class="stat-progress-bar" style="width: {{ $cancelPercent }}%"></div>
                        </div>
                        <div class="stat-progress-text">{{ $cancelPercent }}% dari {{ number_format($grandTotal) }} total resi</div>
                        <div class="stat-meta">Tidak termasuk resi aktif</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Per Kurir ============ --}}
    <div class="card">
        <div class="card-body">
            <div class="dash-section-head mb-5">
                <div>
                    <div class="dash-section-title"><i class="fa-solid fa-truck-fast"></i> Per Kurir</div>
                    <div class="dash-section-sub">Jumlah resi &amp; hasil scan out per kurir &mdash; klik kartu untuk rincian resi tiap status</div>
                </div>
            </div>

            @if(isset($kurirs) && $kurirs->count())
                <div class="kurir-grid">
                    @foreach($kurirs as $kurir)
                        @php
                            $resiTotal = (int) $kurir['resi_total'];
                            $scanTotal = (int) $kurir['scan_total'];
                            $progress = $resiTotal > 0 ? min(100, round($scanTotal / $resiTotal * 100)) : 0;
                            $remaining = (int) $kurir['remaining'];
                            $canceled = (int) ($kurir['canceled_total'] ?? 0);
                        @endphp
                        <div class="kurir-card">
                            <div class="kurir-card-top">
                                <div class="kurir-avatar"><i class="fa-solid fa-truck-fast"></i></div>
                                <div class="kurir-info">
                                    <div class="kurir-name" title="{{ $kurir['name'] }}">{{ $kurir['name'] }}</div>
                                    <div class="kurir-updated"><i class="fa-regular fa-clock"></i> Update {{ $kurir['last_update'] }}</div>
                                </div>
                            </div>

                            <div class="kurir-ratio">
                                <span class="ratio-resi">{{ number_format($resiTotal) }}</span>
                                <span class="ratio-sep">/</span>
                                <span class="ratio-scan">{{ number_format($scanTotal) }}</span>
                                <span class="ratio-caption">resi&nbsp;/&nbsp;scan</span>
                            </div>

                            <div class="kurir-progress">
                                <div class="kurir-progress-bar" style="width: {{ $progress }}%"></div>
                            </div>
                            <div class="kurir-progress-text">{{ $progress }}% resi aktif sudah scan out</div>

                            <div class="kurir-chips">
                                @if($remaining > 0)
                                    <span class="chip chip-amber"><i class="fa-solid fa-clock"></i> Sisa {{ number_format($remaining) }}</span>
                                @else
                                    <span class="chip chip-green"><i class="fa-solid fa-circle-check"></i> Selesai</span>
                                @endif
                                <span class="chip chip-red"><i class="fa-solid fa-ban"></i> Cancel {{ number_format($canceled) }}</span>
                            </div>

                            <button
                                type="button"
                                class="kurir-detail-btn btn-kurir-detail"
                                data-kurir-id="{{ $kurir['id'] }}"
                                data-kurir-name="{{ $kurir['name'] }}"
                                data-date="{{ $today ?? '' }}"
                            >
                                <i class="fa-solid fa-list-ul"></i> Lihat Detail Resi
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center text-muted py-10">
                    <i class="fa-solid fa-truck-fast fs-2x mb-3 d-block text-gray-300"></i>
                    Belum ada data kurir.
                </div>
            @endif
        </div>
    </div>
</div>

{{-- ============ Modal Detail Kurir ============ --}}
<div class="modal fade" id="modal_kurir_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">
                        <i class="fa-solid fa-truck-fast text-primary me-2"></i>Detail Resi Kurir
                    </h5>
                    <div class="text-muted fs-7" id="kurir_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                {{-- Filter cards: clickable per status --}}
                <div class="filter-card-grid mb-4">
                    <div class="filter-card active" role="button" tabindex="0" data-status="all">
                        <div class="filter-card-icon"><i class="fa-solid fa-layer-group"></i></div>
                        <div class="filter-card-body">
                            <div class="filter-card-label">Total Resi Aktif</div>
                            <div class="filter-card-value" id="kurir_detail_total">0</div>
                        </div>
                    </div>
                    <div class="filter-card" role="button" tabindex="0" data-status="scanned">
                        <div class="filter-card-icon"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="filter-card-body">
                            <div class="filter-card-label">Sudah Scan Out</div>
                            <div class="filter-card-value" id="kurir_detail_scanned">0</div>
                        </div>
                    </div>
                    <div class="filter-card" role="button" tabindex="0" data-status="pending">
                        <div class="filter-card-icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="filter-card-body">
                            <div class="filter-card-label">Belum Scan Out</div>
                            <div class="filter-card-value" id="kurir_detail_remaining">0</div>
                        </div>
                    </div>
                    <div class="filter-card" role="button" tabindex="0" data-status="canceled">
                        <div class="filter-card-icon"><i class="fa-solid fa-ban"></i></div>
                        <div class="filter-card-body">
                            <div class="filter-card-label">Dibatalkan</div>
                            <div class="filter-card-value" id="kurir_detail_canceled">0</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <div class="text-muted fs-7">
                        <i class="fa-solid fa-circle-info me-1"></i>
                        Menampilkan: <span class="fw-bold text-gray-800" id="kurir_detail_filter_label">Total Resi Aktif</span>
                    </div>
                    <div class="modal-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="kurir_detail_search" placeholder="Cari ID pesanan, no resi, atau SKU..." autocomplete="off" />
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle" id="kurir_detail_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                <th width="18%">ID Pesanan</th>
                                <th width="18%">No Resi</th>
                                <th width="15%">Status</th>
                                <th width="34%">SKU &amp; Jumlah</th>
                                <th width="15%">Tanggal Upload</th>
                            </tr>
                        </thead>
                        <tbody id="kurir_detail_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-6">Belum ada data.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const kurirDetailUrl = '{{ route('admin.dashboard.kurir-detail') }}';

    document.addEventListener('DOMContentLoaded', () => {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new bootstrap.Tooltip(el));
        }

        const dateInput = document.getElementById('filter_date');
        if (typeof flatpickr !== 'undefined' && dateInput) {
            flatpickr(dateInput, { dateFormat: 'Y-m-d', allowInput: true });
        }

        const detailModalEl = document.getElementById('modal_kurir_detail');
        const detailModal = detailModalEl ? new bootstrap.Modal(detailModalEl) : null;
        const detailSubtitle = document.getElementById('kurir_detail_subtitle');
        const detailTotal = document.getElementById('kurir_detail_total');
        const detailScanned = document.getElementById('kurir_detail_scanned');
        const detailRemaining = document.getElementById('kurir_detail_remaining');
        const detailCanceled = document.getElementById('kurir_detail_canceled');
        const detailBody = document.getElementById('kurir_detail_body');
        const detailSearch = document.getElementById('kurir_detail_search');
        const detailFilterLabel = document.getElementById('kurir_detail_filter_label');
        const filterCards = Array.from(document.querySelectorAll('.filter-card'));

        const STATUS_LABEL = {
            all: 'Total Resi Aktif',
            scanned: 'Sudah Scan Out',
            pending: 'Belum Scan Out',
            canceled: 'Dibatalkan',
        };

        let currentRows = [];
        let activeStatus = 'all';

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');

        const formatNumber = (value) => Number(value || 0).toLocaleString('id-ID');

        const statusPill = (row) => {
            const map = {
                scanned: 'status-pill--scanned',
                pending: 'status-pill--pending',
                canceled: 'status-pill--canceled',
            };
            const icon = {
                scanned: 'fa-circle-check',
                pending: 'fa-clock',
                canceled: 'fa-ban',
            };
            const cls = map[row.status_key] || 'status-pill--pending';
            const ic = icon[row.status_key] || 'fa-clock';
            return `<span class="status-pill ${cls}"><i class="fa-solid ${ic}"></i> ${escapeHtml(row.status_label || '-')}</span>`;
        };

        const skuCell = (row) => {
            const items = Array.isArray(row.items) ? row.items : [];
            if (!items.length) {
                return '<span class="text-muted">-</span>';
            }
            const chips = items.map((item) => `
                <span class="sku-chip">
                    <i class="fa-solid fa-cube"></i>
                    ${escapeHtml(item.sku || '-')}
                    <span class="sku-qty">&times;${formatNumber(item.qty)}</span>
                </span>
            `).join('');
            const totalLine = items.length > 1
                ? `<div class="sku-total">${items.length} SKU &middot; total ${formatNumber(row.total_qty)} pcs</div>`
                : '';
            return `<div class="sku-list">${chips}</div>${totalLine}`;
        };

        const setLoadingState = (kurirName, date) => {
            if (detailSubtitle) detailSubtitle.textContent = `${kurirName || '-'} | Tanggal ${date || '-'}`;
            [detailTotal, detailScanned, detailRemaining, detailCanceled].forEach((el) => {
                if (el) el.textContent = '0';
            });
            if (detailSearch) detailSearch.value = '';
            if (detailBody) {
                detailBody.innerHTML = `
                    <tr><td colspan="5" class="text-center text-muted py-6">
                        <span class="spinner-border spinner-border-sm me-2"></span>Memuat data...
                    </td></tr>`;
            }
        };

        const renderTable = () => {
            if (!detailBody) return;

            const keyword = (detailSearch?.value || '').trim().toLowerCase();
            const rows = currentRows.filter((row) => {
                const matchStatus = activeStatus === 'all'
                    ? (row.status_key === 'scanned' || row.status_key === 'pending')
                    : row.status_key === activeStatus;
                if (!matchStatus) return false;
                if (!keyword) return true;
                const haystack = [
                    row.id_pesanan,
                    row.no_resi,
                    ...(Array.isArray(row.items) ? row.items.map((i) => i.sku) : []),
                ].join(' ').toLowerCase();
                return haystack.includes(keyword);
            });

            if (!rows.length) {
                const msg = keyword
                    ? `Tidak ada resi yang cocok dengan pencarian "${escapeHtml(keyword)}".`
                    : `Tidak ada resi untuk status ${escapeHtml(STATUS_LABEL[activeStatus] || '-')}.`;
                detailBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-6">${msg}</td></tr>`;
                return;
            }

            detailBody.innerHTML = rows.map((row) => `
                <tr>
                    <td class="fw-semibold text-gray-800">${escapeHtml(row.id_pesanan || '-')}</td>
                    <td class="mono">${escapeHtml(row.no_resi || '-')}</td>
                    <td>${statusPill(row)}</td>
                    <td>${skuCell(row)}</td>
                    <td class="text-muted mono">${escapeHtml(row.tanggal_upload || '-')}</td>
                </tr>
            `).join('');
        };

        const setActiveStatus = (status) => {
            activeStatus = status;
            filterCards.forEach((card) => {
                card.classList.toggle('active', card.getAttribute('data-status') === status);
            });
            if (detailFilterLabel) detailFilterLabel.textContent = STATUS_LABEL[status] || '-';
            renderTable();
        };

        filterCards.forEach((card) => {
            const status = card.getAttribute('data-status');
            card.addEventListener('click', () => setActiveStatus(status));
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    setActiveStatus(status);
                }
            });
        });

        if (detailSearch) {
            detailSearch.addEventListener('input', renderTable);
        }

        document.querySelectorAll('.btn-kurir-detail').forEach((button) => {
            button.addEventListener('click', async () => {
                const kurirId = button.getAttribute('data-kurir-id');
                const kurirName = button.getAttribute('data-kurir-name') || '-';
                const date = button.getAttribute('data-date') || '';

                if (!kurirId || !detailModal) return;

                currentRows = [];
                setActiveStatus('all');
                setLoadingState(kurirName, date);
                detailModal.show();

                try {
                    const params = new URLSearchParams({ kurir_id: kurirId, date });
                    const response = await fetch(`${kurirDetailUrl}?${params.toString()}`);
                    const payload = await response.json();

                    if (!response.ok) {
                        throw new Error(payload?.message || 'Gagal memuat detail kurir.');
                    }

                    const meta = payload?.meta || {};
                    if (detailSubtitle) {
                        detailSubtitle.textContent = `${meta.kurir_name || kurirName} | Tanggal ${meta.date || date || '-'}`;
                    }
                    if (detailTotal) detailTotal.textContent = formatNumber(meta.total_resi);
                    if (detailScanned) detailScanned.textContent = formatNumber(meta.scanned_total);
                    if (detailRemaining) detailRemaining.textContent = formatNumber(meta.remaining_total);
                    if (detailCanceled) detailCanceled.textContent = formatNumber(meta.canceled_total);

                    currentRows = Array.isArray(payload?.data) ? payload.data : [];
                    renderTable();
                } catch (error) {
                    if (detailBody) {
                        detailBody.innerHTML = `
                            <tr><td colspan="5" class="text-center text-danger py-6">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                ${escapeHtml(error.message || 'Gagal memuat detail kurir.')}
                            </td></tr>`;
                    }
                }
            });
        });
    });
</script>
@endpush
