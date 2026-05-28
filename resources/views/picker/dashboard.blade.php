@extends('layouts.mobile')

@section('title', 'Menu Operasional')

@section('content')
<style>
    .menu-card {
        display: flex;
        gap: 14px;
        align-items: center;
        padding: 16px;
        border-radius: 18px;
        border: 1px solid var(--border);
        background: #fff;
        text-decoration: none;
        color: inherit;
        box-shadow: var(--shadow);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .menu-card:active {
        transform: scale(0.99);
    }
    .menu-icon {
        width: 52px;
        height: 52px;
        border-radius: 16px;
        display: grid;
        place-items: center;
        font-weight: 700;
        color: #0f172a;
        background: linear-gradient(135deg, rgba(15,118,110,0.18), rgba(16,185,129,0.15));
        border: 1px solid rgba(15,118,110,0.2);
    }
    .menu-icon.opname {
        background: linear-gradient(135deg, rgba(14,165,233,0.2), rgba(56,189,248,0.2));
        border-color: rgba(14,165,233,0.25);
    }
    .menu-icon.scan-out {
        background: linear-gradient(135deg, rgba(14,116,144,0.2), rgba(56,189,248,0.2));
        border-color: rgba(14,116,144,0.25);
    }
    .menu-icon.scan-out-v2 {
        background: linear-gradient(135deg, rgba(37,99,235,0.2), rgba(14,165,233,0.2));
        border-color: rgba(37,99,235,0.25);
    }
    .menu-icon.picking-list {
        background: linear-gradient(135deg, rgba(30,64,175,0.18), rgba(59,130,246,0.2));
        border-color: rgba(59,130,246,0.25);
    }
    .menu-title {
        font-weight: 700;
        font-size: 15px;
        margin-bottom: 4px;
    }
    .menu-desc {
        font-size: 12px;
        color: var(--muted);
    }
    .menu-list {
        display: grid;
        gap: 14px;
        margin-top: 10px;
    }
    .welcome-card {
        background: #ffffff;
        border-radius: var(--radius);
        border: 1px solid rgba(226, 232, 240, 0.7);
        box-shadow: var(--shadow);
        padding: 16px;
        margin-bottom: 16px;
    }
    .welcome-title {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 6px;
    }
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .switch-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid var(--brand);
        background: rgba(15, 118, 110, 0.1);
        color: var(--brand);
        padding: 8px 12px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        text-decoration: none;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">{{ config('app.name') }}</div>
            <div class="subtitle">Dashboard Operasional</div>
        </div>
        <div class="topbar-actions">
            @if(!empty($showDesktop))
                <a href="{{ $routes['desktop'] }}" class="switch-btn" title="Pindah ke tampilan desktop">
                    &#x1F5A5; Desktop
                </a>
            @endif
            <form method="POST" action="{{ $routes['logout'] }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="welcome-card">
        <div class="welcome-title">Pilih Menu Kerja</div>
        <div class="muted">Pilih proses yang ingin Anda lakukan hari ini.</div>
    </div>

    <div class="menu-list">
        <a class="menu-card" href="{{ $routes['opname'] }}">
            <div class="menu-icon opname">SO</div>
            <div>
                <div class="menu-title">Stock Opname</div>
                <div class="menu-desc">Input hasil stock opname dengan cepat.</div>
            </div>
        </a>

        @if(!empty($showPicking))
            <a class="menu-card" href="{{ $routes['picker'] }}">
                <div class="menu-icon">QC</div>
                <div>
                    <div class="menu-title">QC Scan</div>
                    <div class="menu-desc">Scan SKU barang keluar rak untuk masuk QC transit.</div>
                </div>
            </a>
        @endif

        @if(!empty($showScanOut))
            <a class="menu-card" href="{{ $routes['scanOut'] }}">
                <div class="menu-icon scan-out">SO</div>
                <div>
                    <div class="menu-title">Scan Out</div>
                    <div class="menu-desc">Scan resi keluar gudang.</div>
                </div>
            </a>
        @endif

        @if(!empty($showScanOutV2))
            <a class="menu-card" href="{{ $routes['scanOutV2'] }}">
                <div class="menu-icon scan-out-v2">S2</div>
                <div>
                    <div class="menu-title">Scan Out V2</div>
                    <div class="menu-desc">Auto proses setelah scan.</div>
                </div>
            </a>
        @endif

        @if(!empty($showPickingList))
            <a class="menu-card" href="{{ $routes['pickingList'] }}">
                <div class="menu-icon picking-list">PL</div>
                <div>
                    <div class="menu-title">Picking List</div>
                    <div class="menu-desc">Lihat picking list & sisa qty.</div>
                </div>
            </a>
        @endif
    </div>
</div>
@endsection
