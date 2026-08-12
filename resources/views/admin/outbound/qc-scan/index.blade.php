@extends('layouts.admin')

@section('title', 'QC Scan Input')
@section('page_title', 'QC Scan Input')

@section('content')
<style>
    /* ── Typography & Base ── */
    .qc-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }

    /* ── Status bar ── */
    .qc-status {
        border-radius: 12px;
        padding: 10px 14px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        min-height: 44px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: background 0.15s, border-color 0.15s;
    }
    .qc-status .dot {
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: #94a3b8;
        flex: 0 0 auto;
    }
    .qc-status .msg { font-size: 13px; line-height: 1.4; }
    .qc-status.ok  { background: #f0fdf4; border-color: #bbf7d0; }
    .qc-status.ok  .dot { background: #16a34a; }
    .qc-status.err { background: #fef2f2; border-color: #fecaca; }
    .qc-status.err .dot { background: #ef4444; }
    .qc-status.warn { background: #fffbeb; border-color: #fde68a; }
    .qc-status.warn .dot { background: #f59e0b; }

    .qc-live-status {
        min-height: 72px;
        padding: 14px 18px;
        border-width: 2px;
    }
    .qc-live-status .dot {
        width: 13px;
        height: 13px;
    }
    .qc-live-status .msg {
        font-size: 16px;
        font-weight: 700;
    }

    .qc-scan-workspace {
        max-width: 1680px;
        margin: 0 auto;
    }

    .qc-scan-card {
        border: 2px solid #dbeafe;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }

    .qc-scan-card .card-header {
        min-height: auto;
    }

    /* ── Step badge ── */
    .step-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        font-size: 12px;
        font-weight: 700;
        flex: 0 0 auto;
    }
    .step-badge.active   { background: #3b82f6; color: #fff; }
    .step-badge.done     { background: #16a34a; color: #fff; }
    .step-badge.inactive { background: #e2e8f0; color: #94a3b8; }

    /* ── Resi info bar ── */
    .resi-bar {
        background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
        border: 1px solid #bfdbfe;
        border-radius: 14px;
        padding: 14px 18px;
    }
    .resi-bar .resi-no  { font-size: 16px; font-weight: 700; letter-spacing: 0.3px; }
    .resi-bar .resi-id  { font-size: 12px; color: #64748b; margin-top: 2px; }
    .resi-bar .resi-meta { font-size: 12px; color: #475569; margin-top: 6px; }

    /* ── Progress bar ── */
    .qc-progress-wrap {
        background: #f1f5f9;
        border-radius: 999px;
        height: 8px;
        overflow: hidden;
    }
    .qc-progress-fill {
        height: 100%;
        border-radius: 999px;
        background: linear-gradient(90deg, #3b82f6, #06b6d4);
        transition: width 0.4s ease;
    }
    .qc-progress-fill.done { background: linear-gradient(90deg, #16a34a, #22c55e); }

    /* ── Checklist ── */
    .qc-checklist {
        list-style: none;
        padding: 0;
        margin: 0;
        max-height: 360px;
        overflow: auto;
        padding-right: 4px;
    }
    .qc-checklist-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 10px;
        border-radius: 10px;
        transition: background 0.1s;
        cursor: default;
    }
    .qc-checklist-item.pending { background: #fff; border: 1px solid #e5e7eb; margin-bottom: 4px; }
    .qc-checklist-item.scanned { background: #f0fdf4; border: 1px solid #bbf7d0; margin-bottom: 4px; opacity: 0.85; }
    .qc-checklist-item.active  { background: #eff6ff; border: 1.5px solid #93c5fd; margin-bottom: 4px; }
    .qc-checklist-item.next { background: #ecfeff; border: 1.5px solid #67e8f9; margin-bottom: 4px; }
    .qc-check-icon { font-size: 16px; flex: 0 0 auto; }
    .qc-sku-label { font-weight: 700; font-size: 13px; min-width: 90px; }
    .qc-sku-name  { font-size: 12px; color: #64748b; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .qc-qty-badge {
        margin-left: auto;
        font-size: 11px;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 999px;
        white-space: nowrap;
        flex: 0 0 auto;
    }
    .qc-qty-badge.pending { background: #f1f5f9; color: #64748b; }
    .qc-qty-badge.done    { background: #dcfce7; color: #15803d; }
    .qc-qty-badge.active  { background: #dbeafe; color: #1d4ed8; }

    /* ── Input large ── */
    .qc-input-lg {
        font-size: 22px;
        letter-spacing: 0.5px;
        height: 62px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .qc-input-md { height: 50px; font-size: 16px; }

    /* ── Session pills ── */
    .qc-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 1px solid #e5e7eb;
        padding: 6px 10px;
        border-radius: 999px;
        background: #fff;
        font-size: 12px;
    }
    .qc-pill .label { color: #94a3b8; }
    .qc-pill .value { font-weight: 700; }

    /* ── Items table ── */
    .qc-items-table td { vertical-align: middle; }
    .qc-qty-controls { display: inline-flex; align-items: center; gap: 5px; }
    .qc-qty-controls .btn { padding: 0.3rem 0.55rem; }
    .qc-qty-controls input { width: 80px; text-align: center; }

    /* ── All-done banner ── */
    .qc-alldone-banner {
        background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
        border: 2px solid #86efac;
        border-radius: 14px;
        padding: 16px 20px;
        text-align: center;
    }

    .qc-next-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
        background: #0f172a;
        color: #fff;
        border-radius: 12px;
        padding: 14px 16px;
        margin-bottom: 16px;
    }

    .qc-next-box .label {
        color: #94a3b8;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .qc-next-box .sku {
        font-size: 20px;
        font-weight: 800;
        line-height: 1.1;
    }

    .qc-next-box .name {
        color: #cbd5e1;
        font-size: 12px;
        margin-top: 3px;
        max-width: 420px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .qc-next-box .qty {
        text-align: right;
        font-size: 24px;
        font-weight: 800;
        white-space: nowrap;
    }

    .qc-operator-hint {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        padding: 10px 12px;
        color: #64748b;
        font-size: 12px;
    }

    .swal2-popup.qc-scan-toast {
        width: 420px !important;
        padding: 14px 16px !important;
        border-radius: 12px !important;
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.18) !important;
    }

    .swal2-popup.qc-scan-toast .swal2-title {
        font-size: 15px !important;
        line-height: 1.35 !important;
        text-align: left !important;
    }

    .swal2-popup.qc-scan-toast .swal2-html-container {
        margin: 6px 0 0 !important;
        color: #475569 !important;
        font-size: 12px !important;
        line-height: 1.45 !important;
        text-align: left !important;
    }

    .qc-toast-code {
        display: inline-block;
        margin-bottom: 4px;
        padding: 2px 6px;
        border-radius: 6px;
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 800;
    }

    .qc-toast-action {
        display: block;
        margin-top: 4px;
        color: #0f172a;
        font-weight: 700;
    }

    @media (min-width: 1400px) {
        .qc-left-col { width: 42%; }
        .qc-right-col { width: 58%; }
    }

    @media (max-width: 991.98px) {
        .qc-next-box {
            align-items: flex-start;
            flex-direction: column;
        }

        .qc-next-box .qty {
            text-align: left;
            font-size: 20px;
        }
    }

    /* ── Phase visibility ── */
    #phase-scan-resi { display: block; }
    #phase-scan-sku  { display: none; }
</style>

<div class="row g-6 qc-scan-workspace">
    {{-- ═══════════════ LEFT COLUMN ═══════════════ --}}
    <div class="col-xl-5 qc-left-col">

        {{-- ─── PHASE 1: Scan Resi ─── --}}
        <div id="phase-scan-resi">
            <div class="card qc-scan-card">
                <div class="card-header border-0 pt-6 pb-0">
                    <div class="card-title">
                        <div class="d-flex align-items-center gap-3">
                            <span class="step-badge active">1</span>
                            <div>
                                <div class="fw-bold fs-5">Scan Resi</div>
                                <div class="text-muted fs-8">Scan resi terlebih dahulu untuk melihat daftar SKU</div>
                            </div>
                        </div>
                    </div>
                    @if(empty($isLimitedQcScanUser))
                        <div class="card-toolbar">
                            <a href="{{ $routes['pickingList'] }}" class="btn btn-sm btn-light me-2">Picking List</a>
                            <a href="{{ $routes['pickerTransit'] }}" class="btn btn-sm btn-light">QC Transit</a>
                        </div>
                    @endif
                </div>
                <div class="card-body pt-5 pb-7">
                    <div class="alert alert-light-primary d-flex align-items-start gap-3 py-3 px-4 mb-6">
                        <i class="fa-solid fa-circle-info mt-1 text-primary"></i>
                        <div class="fs-8 text-gray-700">
                            Scan <strong>No Resi</strong> atau <strong>ID Pesanan</strong> terlebih dahulu.
                            Sistem akan menampilkan SKU yang harus di-QC sesuai isi resi tersebut.
                            <span class="d-block mt-1">Tanggal kerja: <strong>{{ $today }}</strong></span>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">Tipe Scan</label>
                        <select class="form-select form-select-solid qc-input-md" id="resi_type">
                            <option value="no_resi">No Resi</option>
                            <option value="id_pesanan">ID Pesanan</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">Kode Resi / ID Pesanan</label>
                        <input
                            type="text"
                            class="form-control form-control-solid qc-input-lg"
                            id="resi_code"
                            autocomplete="off"
                            placeholder="Arahkan scanner ke barcode resi, lalu Enter"
                        />
                        <div class="form-text">Scanner biasanya mengirim Enter otomatis. Halaman akan menjaga fokus ke kolom aktif.</div>
                    </div>

                    <button type="button" class="btn btn-primary w-100 fs-6 py-3 mb-4" id="resi_scan_btn">
                        <i class="fa-solid fa-barcode me-2"></i>Cari &amp; Muat Resi
                    </button>

                    <div class="qc-status qc-live-status" id="resi_scan_status">
                        <span class="dot"></span>
                        <div class="msg">Siap scan resi.</div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header border-0 pt-5 pb-0">
                    <div class="card-title">
                        <div>
                            <div class="fw-bold fs-6">Riwayat Scan Resi</div>
                            <div class="text-muted fs-8">Daftar resi yang kamu scan hari ini</div>
                        </div>
                    </div>
                    <div class="card-toolbar gap-2">
                        <select class="form-select form-select-solid form-select-sm w-100px" id="qc_resi_history_limit" aria-label="Jumlah data riwayat">
                            <option value="10" selected>10</option>
                            <option value="20">20</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="500">500</option>
                        </select>
                        <div class="d-flex align-items-center position-relative">
                            <i class="fa-solid fa-magnifying-glass position-absolute ms-3 text-gray-500 fs-8"></i>
                            <input type="text" class="form-control form-control-solid form-control-sm w-175px ps-9" id="qc_resi_history_search" placeholder="Cari resi..." />
                        </div>
                        <button type="button" class="btn btn-sm btn-light" id="btn_refresh_resi_history" title="Muat ulang riwayat">
                            <i class="fa-solid fa-rotate-right"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body pt-4 pb-5">
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-8 mb-0">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder text-uppercase gs-0">
                                    <th>Resi</th>
                                    <th class="text-end">Progress</th>
                                    <th class="text-end">Detail</th>
                                </tr>
                            </thead>
                            <tbody id="qc_resi_history_body">
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-6">Belum ada riwayat scan resi hari ini.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ─── PHASE 2: Resi Loaded → Scan SKU ─── --}}
        <div id="phase-scan-sku">

            {{-- Resi Info Bar --}}
            <div class="resi-bar mb-4">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div class="flex-grow-1 min-w-0">
                        <div class="resi-no qc-mono" id="disp_no_resi">-</div>
                        <div class="resi-id" id="disp_id_pesanan">-</div>
                        <div class="resi-meta">
                            <i class="fa-solid fa-calendar-days me-1"></i><span id="disp_tanggal">-</span>
                            <span class="mx-2">·</span>
                            <i class="fa-solid fa-truck me-1"></i><span id="disp_kurir">-</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-light flex-shrink-0" id="btn_change_resi" title="Ganti resi">
                        <i class="fa-solid fa-arrows-rotate me-1"></i>Ganti Resi
                    </button>
                </div>
            </div>

            {{-- Progress --}}
            <div class="card mb-4">
                <div class="card-body py-5 px-6">
                    <div class="qc-next-box" id="qc_next_box">
                        <div class="min-w-0">
                            <div class="label">SKU Berikutnya</div>
                            <div class="sku qc-mono" id="qc_next_sku">-</div>
                            <div class="name" id="qc_next_name">Scan SKU yang masih belum lengkap.</div>
                        </div>
                        <div class="qty" id="qc_next_qty">0 / 0</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="fw-bold fs-7" id="progress_text">0 / 0 SKU selesai</div>
                        <div class="fw-bold fs-7 text-primary" id="progress_pct">0%</div>
                    </div>
                    <div class="qc-progress-wrap mb-5">
                        <div class="qc-progress-fill" id="progress_bar" style="width: 0%"></div>
                    </div>

                    {{-- All-done banner (hidden by default) --}}
                    <div class="qc-alldone-banner d-none" id="alldone_banner">
                        <div class="fs-3 mb-1"><i class="fa-solid fa-circle-check text-success"></i></div>
                        <div class="fw-bold text-success fs-6">Semua SKU pada resi ini sudah selesai di-scan!</div>
                        <div class="text-muted fs-8 mt-1">Halaman akan kembali ke scan resi berikutnya otomatis.</div>
                    </div>

                    {{-- Checklist --}}
                    <div id="checklist_wrap">
                        <div class="fw-bold fs-8 text-uppercase text-muted ls-1 mb-3">Daftar SKU Resi</div>
                        <ul class="qc-checklist" id="sku_checklist"></ul>
                    </div>
                </div>
            </div>

            {{-- SKU Scan Form --}}
            <div class="card qc-scan-card" id="sku_scan_card">
                <div class="card-header border-0 pt-5 pb-0">
                    <div class="card-title">
                        <div class="d-flex align-items-center gap-3">
                            <span class="step-badge active" id="step2_badge">2</span>
                            <div>
                                <div class="fw-bold">Scan SKU</div>
                                <div class="text-muted fs-8">Scan SKU untuk validasi kesesuaian isi paket</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-5 pb-6">
                    <div class="mb-4">
                        <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">Scan SKU</label>
                        <input
                            type="text"
                            class="form-control form-control-solid qc-input-lg"
                            id="sku_code"
                            autocomplete="off"
                            placeholder="Scan barcode SKU lalu Enter…"
                        />
                        <div class="form-text" id="sku_hint">Scan 1 item fisik per kali scan. Qty default: 1.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-4">
                            <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">Qty</label>
                            <input
                                type="number"
                                min="1"
                                class="form-control form-control-solid"
                                style="height:50px;font-size:17px;"
                                id="sku_qty"
                                value="1"
                            />
                        </div>
                        <div class="col-8">
                            <label class="form-label fw-bold fs-7 text-uppercase text-muted ls-1">&nbsp;</label>
                            <button type="button" class="btn btn-primary w-100 h-100 fs-6" id="sku_scan_btn">
                                <i class="fa-solid fa-barcode me-2"></i>Scan SKU
                            </button>
                        </div>
                    </div>

                    <div class="qc-status qc-live-status mb-4" id="sku_scan_status">
                        <span class="dot"></span>
                        <div class="msg">Siap scan SKU.</div>
                    </div>

                    <div class="qc-operator-hint mb-4">
                        <i class="fa-solid fa-keyboard me-1"></i>
                        Tips cepat: scan resi, scan SKU satu per satu, lalu langsung lanjut ke resi berikutnya saat indikator selesai.
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-light" id="qc_btn_focus" title="Fokus ke input scanner">
                            <i class="fa-solid fa-crosshairs"></i>
                        </button>
                        <button type="button" class="btn btn-light-primary flex-grow-1" id="qc_btn_start">
                            <i class="fa-solid fa-arrows-rotate me-2"></i>Muat Scan QC Hari Ini
                        </button>
                    </div>
                    <div class="text-muted fs-8 mt-2">
                        Tidak ada submit manual. Resi otomatis selesai saat seluruh SKU wajib sudah discan.
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- ═══════════════ RIGHT COLUMN ═══════════════ --}}
    <div class="col-xl-7 qc-right-col">
        <div class="card h-100">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div class="fw-bold fs-5">Scan QC Hari Ini</div>
                </div>
                <div class="card-toolbar gap-2">
                    <a href="{{ $routes['dashboard'] }}" class="btn btn-sm btn-light">Dashboard</a>
                </div>
            </div>
            <div class="card-body py-5">
                {{-- Session pills --}}
                <div class="d-flex flex-wrap gap-2 mb-6">
                    <div class="qc-pill">
                        <span class="label">Status</span>
                        <span class="badge badge-light value" id="qc_session_status">Belum mulai</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Scan Pertama</span>
                        <span class="value" id="qc_session_started_at">-</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Total SKU</span>
                        <span class="value" id="qc_total_items">0</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Total Qty</span>
                        <span class="value" id="qc_total_qty">0</span>
                    </div>
                    <div class="qc-pill">
                        <span class="label">Akun</span>
                        <span class="value">{{ auth()->user()->name ?? '-' }}</span>
                    </div>
                </div>

                {{-- Items table --}}
                <div class="fw-bold fs-7 text-uppercase text-muted mb-3">Resi Belum Selesai QC</div>
                <div class="table-responsive mb-8">
                    <table class="table table-row-dashed align-middle qc-items-table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-8 text-uppercase gs-0">
                                <th>No Resi</th>
                                <th class="text-end">Scan / Wajib</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="qc_pending_resis_body">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-8">Tidak ada resi pending.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="fw-bold fs-7 text-uppercase text-muted mb-3">Akumulasi SKU Hari Ini</div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle qc-items-table" id="qc_items_table">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder fs-8 text-uppercase gs-0">
                                <th width="20%">SKU</th>
                                <th>Nama</th>
                                <th width="20%" class="text-end">Scan / Wajib</th>
                            </tr>
                        </thead>
                        <tbody id="qc_items_body">
                            <tr>
                                <td colspan="3" class="text-center text-muted py-10">
                                    <i class="fa-regular fa-clipboard fs-2 d-block mb-2 text-gray-300"></i>
                                    Belum ada item yang di-scan hari ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_qc_resi_history_detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-1" id="qc_history_detail_title">Detail Resi</h5>
                    <div class="text-muted fs-7" id="qc_history_detail_subtitle">-</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 mb-5" id="qc_history_detail_pills"></div>
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-7 mb-0">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder text-uppercase gs-0">
                                <th width="8%">No</th>
                                <th>SKU</th>
                                <th>Nama Item</th>
                                <th class="text-end">Scan / Wajib</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="qc_history_detail_items_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-6">Tidak ada detail SKU.</td>
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
    const routes   = @json($routes);
    const todayStr = '{{ $today }}';
    const csrf     = '{{ csrf_token() }}';

    /* ─────────────── STATE ─────────────── */
    const state = {
        phase: 'scan-resi',   // 'scan-resi' | 'scan-sku'
        session: null,
        busy: false,
        resi: null,           // {id, id_pesanan, no_resi, tanggal_pesanan, kurir_name}
        checklist: [],        // [{sku, qty, scanned_qty}]
        itemNames: {},        // sku → name (fetched from session items)
        completionTimer: null,
    };

    /* ─────────────── ELEMENT REFS ─────────────── */
    const el = {
        // Phase wrappers
        phaseScanResi: document.getElementById('phase-scan-resi'),
        phaseScanSku:  document.getElementById('phase-scan-sku'),

        // Step 1
        resiType:   document.getElementById('resi_type'),
        resiCode:   document.getElementById('resi_code'),
        resiScanBtn: document.getElementById('resi_scan_btn'),
        resiStatus:  document.getElementById('resi_scan_status'),

        // Resi bar
        dispNoResi:    document.getElementById('disp_no_resi'),
        dispIdPesanan: document.getElementById('disp_id_pesanan'),
        dispTanggal:   document.getElementById('disp_tanggal'),
        dispKurir:     document.getElementById('disp_kurir'),
        btnChangeResi: document.getElementById('btn_change_resi'),

        // Progress
        progressText: document.getElementById('progress_text'),
        progressPct:  document.getElementById('progress_pct'),
        progressBar:  document.getElementById('progress_bar'),
        alldoneBanner: document.getElementById('alldone_banner'),
        checklistWrap: document.getElementById('checklist_wrap'),
        skuChecklist:  document.getElementById('sku_checklist'),
        nextBox:       document.getElementById('qc_next_box'),
        nextSku:       document.getElementById('qc_next_sku'),
        nextName:      document.getElementById('qc_next_name'),
        nextQty:       document.getElementById('qc_next_qty'),

        // Step 2
        skuCode:    document.getElementById('sku_code'),
        skuQty:     document.getElementById('sku_qty'),
        skuScanBtn: document.getElementById('sku_scan_btn'),
        skuStatus:  document.getElementById('sku_scan_status'),
        skuHint:    document.getElementById('sku_hint'),
        btnSubmit:  null,
        btnStart:   document.getElementById('qc_btn_start'),
        btnFocus:   document.getElementById('qc_btn_focus'),

        // Session panel
        sessionStatus:    document.getElementById('qc_session_status'),
        sessionStartedAt: document.getElementById('qc_session_started_at'),
        totalItems:       document.getElementById('qc_total_items'),
        totalQty:         document.getElementById('qc_total_qty'),
        itemsBody:        document.getElementById('qc_items_body'),
        pendingResisBody: document.getElementById('qc_pending_resis_body'),
        resiHistorySearch: document.getElementById('qc_resi_history_search'),
        resiHistoryLimit: document.getElementById('qc_resi_history_limit'),
        resiHistoryBody:  document.getElementById('qc_resi_history_body'),
        btnRefreshHistory: document.getElementById('btn_refresh_resi_history'),
        historyDetailModalEl: document.getElementById('modal_qc_resi_history_detail'),
        historyDetailTitle: document.getElementById('qc_history_detail_title'),
        historyDetailSubtitle: document.getElementById('qc_history_detail_subtitle'),
        historyDetailPills: document.getElementById('qc_history_detail_pills'),
        historyDetailItemsBody: document.getElementById('qc_history_detail_items_body'),
    };
    const historyDetailModal = el.historyDetailModalEl ? new bootstrap.Modal(el.historyDetailModalEl) : null;

    /* ─────────────── AUDIO ─────────────── */
    const audio = { ctx: null };

    async function ensureAudio() {
        const Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        if (!audio.ctx) audio.ctx = new Ctx();
        if (audio.ctx.state === 'suspended') await audio.ctx.resume();
        return audio.ctx;
    }

    async function beep(kind) {
        try {
            const ctx = await ensureAudio();
            if (!ctx) return;
            const tones = kind === 'ok'
                ? [{ f: 880, t: 0, d: 0.07 }, { f: 1174, t: 0.09, d: 0.09 }]
                : [{ f: 320, t: 0, d: 0.12 }, { f: 240, t: 0.12, d: 0.16 }];
            const now = ctx.currentTime + 0.01;
            tones.forEach(({ f, t, d }) => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = kind === 'ok' ? 'sine' : 'triangle';
                osc.frequency.setValueAtTime(f, now + t);
                gain.gain.setValueAtTime(0.0001, now + t);
                gain.gain.exponentialRampToValueAtTime(1, now + t + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + t + d);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + t);
                osc.stop(now + t + d);
            });
        } catch {}
    }

    /* ─────────────── HELPERS ─────────────── */
    const esc = v => String(v ?? '').replace(/[&<>"']/g, m =>
        ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));

    function setStatus(elRef, msg, type = '') {
        if (!elRef) return;
        elRef.classList.remove('ok', 'err', 'warn');
        if (type) elRef.classList.add(type);
        const d = elRef.querySelector('.msg');
        if (d) d.textContent = msg || '';
    }

    function setBusy(busy) {
        state.busy = !!busy;
        const btns = [el.resiScanBtn, el.skuScanBtn, el.btnStart];
        btns.forEach(b => { if (b) b.disabled = busy; });
        if (el.btnSubmit) el.btnSubmit.disabled = true;
    }

    function focusSku() {
        if (el.skuCode) { el.skuCode.focus(); el.skuCode.select?.(); }
    }
    function focusResi() {
        if (el.resiCode) { el.resiCode.focus(); el.resiCode.select?.(); }
    }

    function activeScanInput() {
        return state.phase === 'scan-sku' ? el.skuCode : el.resiCode;
    }

    function refocusActiveScanner() {
        if (state.busy) return;
        const input = activeScanInput();
        if (!input) return;
        const active = document.activeElement;
        if (active !== input && !active?.closest?.('.modal')) {
            input.focus();
        }
    }

    function makeRequestId() {
        if (window.crypto?.randomUUID) {
            return window.crypto.randomUUID();
        }
        return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    async function fetchJson(url, opts = {}) {
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf, ...(opts.headers || {}) },
            credentials: 'same-origin',
            ...opts,
        });
        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch {}
        if (!res.ok) {
            let msg = json?.message || 'Terjadi kesalahan';
            if (json?.errors) {
                const first = Object.values(json.errors)[0];
                if (Array.isArray(first) && first.length) msg = first[0];
            }
            throw new Error(msg);
        }
        return json;
    }

    /* ─────────────── PHASE SWITCHING ─────────────── */
    function goToPhase(phase) {
        if (state.completionTimer) {
            clearTimeout(state.completionTimer);
            state.completionTimer = null;
        }
        state.phase = phase;
        el.phaseScanResi.style.display = phase === 'scan-resi' ? 'block' : 'none';
        el.phaseScanSku.style.display  = phase === 'scan-sku'  ? 'block' : 'none';
        if (phase === 'scan-resi') {
            setStatus(el.resiStatus, 'Siap scan resi.');
            if (el.resiCode) el.resiCode.value = '';
            if (el.skuCode) el.skuCode.value = '';
            if (el.skuQty) el.skuQty.value = '1';
            setTimeout(focusResi, 50);
        } else {
            if (el.skuHint) el.skuHint.textContent = 'Scan 1 item fisik per kali scan. Qty default: 1.';
            setTimeout(focusSku, 50);
        }
    }

    /* ─────────────── CHECKLIST ─────────────── */
    function checklistProgress() {
        const total = state.checklist.length;
        const done  = state.checklist.filter(i => i.scanned_qty >= i.qty).length;
        const pct   = total > 0 ? Math.round(done / total * 100) : 0;
        return { total, done, pct };
    }

    function nextChecklistItem() {
        return state.checklist.find(i => Number(i.scanned_qty || 0) < Number(i.qty || 0)) || null;
    }

    function updateNextBox() {
        const next = nextChecklistItem();
        if (!el.nextBox) return;

        if (!next) {
            el.nextSku.textContent = 'SELESAI';
            el.nextName.textContent = 'Semua SKU pada resi ini sudah lengkap.';
            el.nextQty.textContent = '';
            return;
        }

        const scanned = Number(next.scanned_qty || 0);
        const required = Number(next.qty || 0);
        const remaining = Math.max(0, required - scanned);
        el.nextSku.textContent = next.sku || '-';
        el.nextName.textContent = state.itemNames[next.sku?.toLowerCase?.() || ''] || 'Scan SKU ini atau SKU lain yang ada dalam daftar.';
        el.nextQty.textContent = `${scanned} / ${required} | sisa ${remaining}`;
    }

    function renderChecklist() {
        if (!el.skuChecklist) return;
        const items = state.checklist;
        if (!items.length) { el.skuChecklist.innerHTML = ''; return; }

        el.skuChecklist.innerHTML = items.map(item => {
            const isDone    = item.scanned_qty >= item.qty;
            const isPartial = item.scanned_qty > 0 && !isDone;
            const next      = nextChecklistItem();
            const cls       = isDone ? 'scanned' : (next && next.sku === item.sku ? 'next' : 'pending');
            const icon      = isDone ? '<i class="fa-solid fa-circle-check text-success"></i>' : (next && next.sku === item.sku ? '<i class="fa-solid fa-location-dot text-info"></i>' : '<i class="fa-regular fa-circle text-gray-400"></i>');
            const badgeCls  = isDone ? 'done' : 'pending';
            const qtyLabel  = isDone
                ? `${item.qty} / ${item.qty}`
                : (isPartial ? `${item.scanned_qty} / ${item.qty}` : `0 / ${item.qty}`);
            const name = state.itemNames[item.sku.toLowerCase()] || '';

            return `<li class="qc-checklist-item ${cls}" data-sku="${esc(item.sku)}">
                <span class="qc-check-icon">${icon}</span>
                <span class="qc-sku-label qc-mono">${esc(item.sku)}</span>
                <span class="qc-sku-name">${esc(name)}</span>
                <span class="qc-qty-badge ${badgeCls}">${qtyLabel}</span>
            </li>`;
        }).join('');
    }

    function updateProgress() {
        const { total, done, pct } = checklistProgress();
        if (el.progressText) el.progressText.textContent = `${done} / ${total} SKU selesai`;
        if (el.progressPct)  el.progressPct.textContent  = `${pct}%`;
        if (el.progressBar) {
            el.progressBar.style.width = `${pct}%`;
            el.progressBar.classList.toggle('done', pct === 100);
        }
        const allDone = total > 0 && done === total;
        if (el.alldoneBanner) el.alldoneBanner.classList.toggle('d-none', !allDone);
        if (el.checklistWrap) el.checklistWrap.classList.toggle('d-none', allDone);
        updateNextBox();
        renderChecklist();
    }

    /* ─────────────── SESSION RENDER ─────────────── */
    function renderSession() {
        const s = state.session;
        const isDraft = !!s && s.status === 'active';

        if (!s) {
            el.sessionStatus.textContent = 'Belum mulai';
            el.sessionStatus.className = 'badge badge-light value';
            el.sessionStartedAt.textContent = '-';
            el.totalItems.textContent = '0';
            el.totalQty.textContent   = '0';
            renderItems([]);
            renderPendingResis([]);
            renderResiHistory([]);
            return;
        }

        el.sessionStatus.textContent = isDraft ? 'Aktif' : 'Tidak aktif';
        el.sessionStatus.className   = isDraft
            ? 'badge badge-light-success value'
            : 'badge badge-light-warning value';
        el.sessionStartedAt.textContent = s.started_at || '-';

        const items = Array.isArray(s.items) ? s.items : [];
        const total = items.reduce((sum, r) => sum + (Number(r.qty) || 0), 0);
        el.totalItems.textContent = String(items.length);
        el.totalQty.textContent   = String(total);

        // Update item names cache from session
        items.forEach(r => {
            if (r.sku && r.name) state.itemNames[r.sku.toLowerCase()] = r.name;
        });

        renderItems(items);
        const resis = Array.isArray(s.resis) ? s.resis : [];
        renderPendingResis(resis);
        renderResiHistory(resis);
    }

    function resiStatusBadge(status) {
        if (status === 'completed') {
            return '<span class="badge badge-light-success"><i class="fa-solid fa-circle-check me-1"></i>Selesai</span>';
        }
        return '<span class="badge badge-light-warning"><i class="fa-solid fa-hourglass-half me-1"></i>Belum Lengkap</span>';
    }

    function renderResiHistory(resis) {
        if (!el.resiHistoryBody) return;
        const rows = (Array.isArray(resis) ? [...resis] : [])
            .filter(row => matchesResiHistorySearch(row))
            .sort((a, b) => String(b.scanned_at || '').localeCompare(String(a.scanned_at || '')))
            .slice(0, resiHistoryLimit());

        if (!rows.length) {
            const hasKeyword = (el.resiHistorySearch?.value || '').trim() !== '';
            el.resiHistoryBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-6">${hasKeyword ? 'Tidak ada riwayat yang cocok.' : 'Belum ada riwayat scan resi hari ini.'}</td>
                </tr>`;
            return;
        }

        el.resiHistoryBody.innerHTML = rows.map(row => {
            const pct = Number(row.progress || 0);
            const label = row.no_resi || row.id_pesanan || '-';
            const scanned = Number(row.scanned_qty || 0).toLocaleString('id-ID');
            const required = Number(row.required_qty || 0).toLocaleString('id-ID');
            const barClass = row.status === 'completed' ? 'bg-success' : 'bg-warning';
            return `<tr>
                <td>
                    <div class="fw-bold qc-mono text-primary fs-8">${esc(label)}</div>
                    <div class="text-muted fs-9">${esc(row.id_pesanan || '-')} · ${esc(row.scanned_at || '-')}</div>
                </td>
                <td class="text-end">
                    <div class="d-flex align-items-center justify-content-end gap-2">
                        <div class="progress w-75px h-6px bg-light">
                            <div class="progress-bar ${barClass}" style="width:${pct}%"></div>
                        </div>
                        <span class="fw-semibold fs-9">${pct}%</span>
                    </div>
                    <div class="text-muted fs-9">${scanned}/${required} qty</div>
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-icon btn-light-primary btn-qc-resi-history-detail" data-id="${esc(String(row.id))}" title="Detail resi">
                        <i class="fa-solid fa-circle-info"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    }

    function resiHistoryLimit() {
        const allowed = [10, 20, 50, 100, 500];
        const value = Number(el.resiHistoryLimit?.value || 10);
        return allowed.includes(value) ? value : 10;
    }

    function matchesResiHistorySearch(row) {
        const keyword = (el.resiHistorySearch?.value || '').trim().toLowerCase();
        if (!keyword) return true;

        const itemText = (row.items || []).map(item => `${item.sku || ''} ${item.name || ''}`).join(' ');
        return [
            row.no_resi,
            row.id_pesanan,
            row.kurir_name,
            row.status,
            row.scanned_at,
            row.completed_at,
            itemText,
        ].some(value => String(value || '').toLowerCase().includes(keyword));
    }

    function showResiHistoryDetail(qcResiId) {
        const row = (state.session?.resis || []).find(r => String(r.id) === String(qcResiId));
        if (!row || !historyDetailModal) return;

        const scanned = Number(row.scanned_qty || 0).toLocaleString('id-ID');
        const required = Number(row.required_qty || 0).toLocaleString('id-ID');
        if (el.historyDetailTitle) el.historyDetailTitle.textContent = row.no_resi || '-';
        if (el.historyDetailSubtitle) {
            el.historyDetailSubtitle.textContent = `${row.id_pesanan || '-'} · ${row.kurir_name || '-'} · ${row.scanned_at || '-'}`;
        }
        if (el.historyDetailPills) {
            el.historyDetailPills.innerHTML = [
                resiStatusBadge(row.status),
                historyPill('fa-boxes-stacked', 'text-primary', 'Total SKU', Number(row.items?.length || 0).toLocaleString('id-ID')),
                historyPill('fa-barcode', 'text-info', 'Scan / Wajib', `${scanned}/${required}`),
                historyPill('fa-chart-simple', 'text-success', 'Progress', `${Number(row.progress || 0)}%`),
                row.completed_at ? historyPill('fa-circle-check', 'text-success', 'Selesai', esc(row.completed_at)) : '',
            ].filter(Boolean).join('');
        }
        if (el.historyDetailItemsBody) {
            const items = Array.isArray(row.items) ? row.items : [];
            if (!items.length) {
                el.historyDetailItemsBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-6">Tidak ada detail SKU.</td></tr>`;
            } else {
                el.historyDetailItemsBody.innerHTML = items.map((item, idx) => {
                    const itemScanned = Number(item.scanned_qty || 0);
                    const itemRequired = Number(item.required_qty || 0);
                    const done = itemRequired > 0 && itemScanned >= itemRequired;
                    return `<tr>
                        <td class="text-muted">${idx + 1}</td>
                        <td><span class="fw-bold qc-mono">${esc(item.sku || '-')}</span></td>
                        <td>${esc(item.name || '-')}</td>
                        <td class="text-end fw-semibold">${itemScanned.toLocaleString('id-ID')} / ${itemRequired.toLocaleString('id-ID')}</td>
                        <td>${resiStatusBadge(done ? 'completed' : 'in_progress')}</td>
                    </tr>`;
                }).join('');
            }
        }

        historyDetailModal.show();
    }

    function historyPill(icon, colorClass, label, value) {
        return `<span class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded bg-light">
                    <i class="fa-solid ${esc(icon)} ${esc(colorClass)} fs-8"></i>
                    <span class="text-muted fs-8">${esc(label)}:</span>
                    <span class="fw-semibold fs-8">${value}</span>
                </span>`;
    }

    function renderPendingResis(resis) {
        if (!el.pendingResisBody) return;
        const pending = (Array.isArray(resis) ? resis : []).filter(row => row.status !== 'completed');
        if (!pending.length) {
            el.pendingResisBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-8">Tidak ada resi pending.</td>
                </tr>`;
            return;
        }

        el.pendingResisBody.innerHTML = pending.map((row, idx) => {
            const pct = Number(row.progress || 0);
            const label = row.no_resi || row.id_pesanan || '-';
            return `<tr>
                <td>
                    <div class="fw-bold qc-mono text-primary fs-8">${esc(label)}</div>
                    <div class="text-muted fs-9">${esc(row.id_pesanan || '-')} · ${Number(row.items?.length || 0)} SKU · ${pct}%</div>
                </td>
                <td class="text-end fw-semibold">${Number(row.scanned_qty || 0).toLocaleString('id-ID')} / ${Number(row.required_qty || 0).toLocaleString('id-ID')}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-light-primary btn-resume-resi" data-id="${row.id}">Lanjutkan</button>
                </td>
            </tr>`;
        }).join('');
    }

    function renderItems(items) {
        if (!el.itemsBody) return;
        if (!Array.isArray(items) || !items.length) {
            el.itemsBody.innerHTML = `
                <tr>
                    <td colspan="3" class="text-center text-muted py-10">
                        <i class="fa-regular fa-clipboard fs-2 d-block mb-2 text-gray-300"></i>
                        Belum ada item yang di-scan hari ini.
                    </td>
                </tr>`;
            return;
        }
        el.itemsBody.innerHTML = items.map(row => {
            const addr = row.address && String(row.address).trim() ? row.address : '-';
            return `<tr>
                <td class="qc-mono fw-bold fs-8">${esc(row.sku || '-')}</td>
                <td>
                    <div class="fw-bold fs-8">${esc(row.name || '-')}</div>
                </td>
                <td class="text-end fw-semibold">${Number(row.qty || 0).toLocaleString('id-ID')} / ${Number(row.required_qty || 0).toLocaleString('id-ID')}</td>
            </tr>`;
        }).join('');
    }

    /* ─────────────── API CALLS ─────────────── */
    async function refreshSession() {
        const json = await fetchJson(routes.qcCurrent);
        state.session = json.session || null;
        renderSession();
    }

    async function startSession() {
        if (state.session?.status === 'active') return true;
        setBusy(true);
        try {
            const json = await fetchJson(routes.qcStart, { method: 'POST' });
            state.session = json.session || null;
            renderSession();
            return !!(state.session?.status === 'active');
        } catch (e) {
            notifyScanIssue({
                target: el.skuStatus,
                title: 'Gagal Memuat QC',
                detail: e.message || 'Gagal memuat scan QC hari ini.',
                action: 'Coba muat ulang, lalu scan lagi.',
                timer: 4500,
            });
            return false;
        } finally {
            setBusy(false);
        }
    }

    /* ─────────────── STEP 1: LOOKUP RESI ─────────────── */
    async function lookupResi() {
        if (state.busy) return;
        const type = el.resiType?.value || 'no_resi';
        const code = (el.resiCode?.value || '').trim();

        if (!code) {
            notifyScanIssue({
                target: el.resiStatus,
                icon: 'warning',
                title: 'Resi Kosong',
                detail: 'Scanner belum mengirim kode resi atau ID pesanan.',
                action: 'Arahkan scanner ke barcode resi, lalu scan ulang.',
                timer: 3000,
            });
            focusResi();
            return;
        }

        setBusy(true);
        setStatus(el.resiStatus, `Mencari resi "${code}"…`, 'warn');
        try {
            await ensureAudio();
            const params = new URLSearchParams({ type, code });
            const json = await fetchJson(`${routes.qcResiLookup}?${params}`);


            state.resi      = json.resi;
            state.checklist = (json.items || []).map(i => ({
                sku: i.sku,
                qty: i.qty,
                scanned_qty: i.scanned_qty || 0,
            }));

            // Load item names from current session
            if (state.session?.items) {
                state.session.items.forEach(r => {
                    if (r.sku && r.name) state.itemNames[r.sku.toLowerCase()] = r.name;
                });
            }

            // Fill display
            el.dispNoResi.textContent    = state.resi.no_resi    || '-';
            el.dispIdPesanan.textContent = `ID Pesanan: ${state.resi.id_pesanan || '-'}`;
            el.dispTanggal.textContent   = state.resi.tanggal_pesanan || '-';
            el.dispKurir.textContent     = state.resi.kurir_name || '-';

            // Handle resi yang sudah pernah di-scan
            if (json.already_scanned) {
                if (json.is_complete) {
                    beep('err');
                    state.resi      = null;
                    state.checklist = [];
                    if (el.resiCode) el.resiCode.value = '';
                    notifyScanIssue({
                        target: el.resiStatus,
                        icon: 'warning',
                        title: 'Resi Sudah Selesai',
                        code: state.resi?.no_resi || code,
                        detail: 'Semua SKU pada resi ini sudah lengkap.',
                        action: 'Tidak perlu scan ulang. Lanjut scan resi lain.',
                        timer: 4200,
                    });
                    focusResi();
                    return;
                }
                // Resi BELUM SELESAI - lanjutkan dari history
                const doneSKUs  = state.checklist.filter(i => i.scanned_qty >= i.qty).length;
                const totalSKUs = state.checklist.length;
                beep('ok');
                swalToast('info', 'Melanjutkan Scan Resi',
                    `Resi ini pernah di-scan (${doneSKUs}/${totalSKUs} SKU selesai). Progress dipulihkan.`,
                    4000);
            } else {
                beep('ok');
            }

            // Catat resi ke ledger QC. Ini wajib supaya scan SKU bisa diaudit per resi.
            const form = new FormData();
            form.append('resi_id', String(state.resi.id));
            const recordJson = await fetchJson(routes.qcResiRecord, { method: 'POST', body: form });
            await refreshSession();

            updateProgress();
            if (!state.checklist.length && recordJson.status === 'completed') {
                const finishedResi = state.resi?.no_resi || state.resi?.id_pesanan || code;
                setStatus(el.resiStatus, `Resi ${finishedResi} selesai QC otomatis.`, 'ok');
                swalToast('success', 'Resi Selesai', 'Semua SKU pada resi ini masuk exception.', 2400);
                state.resi = null;
                state.checklist = [];
                if (el.resiCode) el.resiCode.value = '';
                renderChecklist();
                focusResi();
                return;
            }

            goToPhase('scan-sku');
            setStatus(el.skuStatus,
                json.already_scanned
                    ? 'Melanjutkan scan. Lihat progress checklist di atas.'
                    : 'Resi dimuat. Scan SKU sesuai daftar di atas.',
                'ok');
        } catch (e) {
            beep('err');
            notifyScanIssue({
                target: el.resiStatus,
                title: 'Resi Gagal Dimuat',
                code,
                detail: e.message || 'Resi tidak ditemukan.',
                action: 'Periksa barcode atau ganti tipe scan, lalu scan ulang.',
                timer: 4800,
            });
            focusResi();
        } finally {
            setBusy(false);
        }
    }

    /* ─────────────── SWEET ALERT HELPERS ─────────────── */
    function swalToast(icon, title, text, timer = 2500) {
        if (!window.Swal) return;
        window.Swal.fire({
            icon,
            title,
            text,
            toast: true,
            position: 'top-end',
            timer,
            timerProgressBar: true,
            showConfirmButton: false,
            returnFocus: false,
        });
    }

    function notifyScanIssue({ target, icon = 'error', title, code = '', detail, action, timer = 3800 }) {
        const statusType = icon === 'warning' ? 'warn' : 'err';
        const shortCode = code ? ` [${code}]` : '';
        setStatus(target, `${title}${shortCode}: ${detail}`, statusType);

        if (!window.Swal) return;
        window.Swal.fire({
            icon,
            title,
            html: `
                ${code ? `<span class="qc-toast-code qc-mono">${esc(code)}</span><br>` : ''}
                <span>${esc(detail)}</span>
                ${action ? `<span class="qc-toast-action">${esc(action)}</span>` : ''}
            `,
            toast: true,
            position: 'top-end',
            timer,
            timerProgressBar: true,
            showConfirmButton: false,
            returnFocus: false,
            customClass: { popup: 'qc-scan-toast' },
            didOpen: () => setTimeout(refocusActiveScanner, 20),
            didClose: () => setTimeout(refocusActiveScanner, 20),
        });
    }

    /* ─────────────── STEP 2: SCAN SKU ─────────────── */
    async function scanSku() {
        if (state.busy) return;
        const code = (el.skuCode?.value || '').trim();
        let qty    = Math.max(1, parseInt(el.skuQty?.value || '1', 10) || 1);

        if (!code) {
            notifyScanIssue({
                target: el.skuStatus,
                icon: 'warning',
                title: 'SKU Kosong',
                detail: 'Scanner belum mengirim kode SKU.',
                action: 'Scan ulang item fisik yang sedang dipegang.',
                timer: 2600,
            });
            focusSku();
            return;
        }

        // ── Validasi 1: SKU harus ada di checklist resi ──
        const checkItem = state.checklist.find(i => i.sku.toLowerCase() === code.toLowerCase());
        if (!checkItem) {
            beep('err');
            if (el.skuCode) el.skuCode.value = '';
            notifyScanIssue({
                target: el.skuStatus,
                title: 'SKU Tidak Sesuai Resi',
                code,
                detail: `SKU ini tidak ada dalam resi ${state.resi?.no_resi || '-'}.`,
                action: 'Pisahkan barang ini, lalu scan SKU yang sesuai daftar.',
                timer: 5200,
            });
            focusSku();
            return;
        }

        // ── Validasi 2: SKU sudah selesai di-scan ──
        if (checkItem.scanned_qty >= checkItem.qty) {
            beep('err');
            if (el.skuCode) el.skuCode.value = '';
            notifyScanIssue({
                target: el.skuStatus,
                icon: 'warning',
                title: 'SKU Sudah Lengkap',
                code,
                detail: `Qty SKU ini sudah terpenuhi (${checkItem.qty}/${checkItem.qty}).`,
                action: 'Jangan scan ulang. Lanjut ke SKU lain yang belum lengkap.',
                timer: 4200,
            });
            focusSku();
            return;
        }

        // ── Pastikan ringkasan scan hari ini termuat ──
        if (!state.session || state.session.status !== 'active') {
            const ok = await startSession();
            if (!ok) { focusSku(); return; }
        }

        setBusy(true);
        setStatus(el.skuStatus, `Memproses scan ${code} (qty ${qty})…`, 'warn');
        try {
            const form = new FormData();
            form.append('code', code);
            form.append('qty', String(qty));
            form.append('request_id', makeRequestId());
            // Kirim resi_id agar backend bisa validasi SKU memang ada di resi ini
            if (state.resi?.id) form.append('resi_id', String(state.resi.id));

            const json = await fetchJson(routes.qcScanItem, { method: 'POST', body: form });
            state.session = json.session || null;
            renderSession();

            // Update checklist scanned qty
            checkItem.scanned_qty = Math.min(checkItem.qty, checkItem.scanned_qty + qty);
            const sessionItem = state.session?.items?.find(i => i.sku?.toLowerCase() === code.toLowerCase());
            if (sessionItem?.name) state.itemNames[code.toLowerCase()] = sessionItem.name;

            updateProgress();

            const sisaSetelahScan = checkItem.qty - checkItem.scanned_qty;
            const skuSelesai      = sisaSetelahScan <= 0;

            if (skuSelesai) {
                // ── Notifikasi: SKU selesai ──
                const doneMsg = `SKU "${code}" sudah selesai di-scan (${checkItem.qty} dari ${checkItem.qty} qty).`;
                setStatus(el.skuStatus, doneMsg, 'ok');
                beep('ok');
                swalToast('success', 'SKU Selesai', doneMsg, 2000);

                // ── Notifikasi: Semua SKU di resi selesai ──
                const { total, done } = checklistProgress();
                if (total > 0 && done === total) {
                    setStatus(el.skuStatus, `Resi ${state.resi?.no_resi || '-'} selesai. Siapkan resi berikutnya.`, 'ok');
                    swalToast('success', 'Resi Selesai', 'Halaman kembali ke scan resi berikutnya.', 2200);
                    state.completionTimer = setTimeout(() => {
                        goToPhase('scan-resi');
                    }, 900);
                }
            } else {
                const msg = `OK: ${code} +${qty} ✓  (sisa perlu: ${sisaSetelahScan})`;
                setStatus(el.skuStatus, msg, 'ok');
                beep('ok');
            }

            if (el.skuCode) el.skuCode.value = '';
            if (el.skuQty)  el.skuQty.value  = '1';
        } catch (e) {
            beep('err');
            if (el.skuCode) el.skuCode.value = '';
            notifyScanIssue({
                target: el.skuStatus,
                title: 'Scan Gagal',
                code,
                detail: e.message || 'Gagal memproses scan.',
                action: 'Data belum tercatat. Perbaiki penyebabnya, lalu scan ulang.',
                timer: 5200,
            });
        } finally {
            setBusy(false);
            focusSku();
        }
    }


    /* ─────────────── SKU INPUT HANDLER (info saja, qty tidak diubah) ─────────────── */
    let skuInputTimer = null;
    function onSkuInput() {
        clearTimeout(skuInputTimer);
        skuInputTimer = setTimeout(() => {
            const code = (el.skuCode?.value || '').trim();
            if (!code || !state.checklist.length) {
                if (el.skuHint) el.skuHint.textContent = 'Scan 1 item fisik per kali scan. Qty default: 1.';
                return;
            }
            const item = state.checklist.find(i => i.sku.toLowerCase() === code.toLowerCase());
            if (item) {
                const remaining = item.qty - item.scanned_qty;
                if (item.scanned_qty >= item.qty) {
                    if (el.skuHint) el.skuHint.textContent = `✅ SKU ini sudah selesai (${item.qty}/${item.qty} qty).`;
                } else {
                    if (el.skuHint) el.skuHint.textContent = `SKU ada di resi — terscan: ${item.scanned_qty}/${item.qty}, sisa: ${remaining}. Max qty per scan: ${remaining}.`;
                }
            } else {
                if (el.skuHint) el.skuHint.textContent = '⚠ SKU ini tidak ada dalam resi yang dimuat — scan akan ditolak.';
            }
        }, 120);
    }

    function resumePendingResi(qcResiId) {
        const row = (state.session?.resis || []).find(r => String(r.id) === String(qcResiId));
        if (!row) return;

        state.resi = {
            id: row.resi_id,
            no_resi: row.no_resi,
            id_pesanan: row.id_pesanan,
            tanggal_pesanan: row.tanggal_pesanan,
            kurir_name: row.kurir_name,
        };
        state.checklist = (row.items || []).map(item => ({
            sku: item.sku,
            qty: Number(item.required_qty || 0),
            scanned_qty: Number(item.scanned_qty || 0),
        }));
        (row.items || []).forEach(item => {
            if (item.sku && item.name) state.itemNames[item.sku.toLowerCase()] = item.name;
        });

        el.dispNoResi.textContent = state.resi.no_resi || '-';
        el.dispIdPesanan.textContent = `ID Pesanan: ${state.resi.id_pesanan || '-'}`;
        el.dispTanggal.textContent = state.resi.tanggal_pesanan || '-';
        el.dispKurir.textContent = state.resi.kurir_name || '-';
        updateProgress();
        goToPhase('scan-sku');
        setStatus(el.skuStatus, 'Melanjutkan resi pending. Scan SKU yang belum lengkap.', 'ok');
    }

    /* ─────────────── INIT ─────────────── */
    document.addEventListener('DOMContentLoaded', async () => {
        // Load existing session
        try {
            await refreshSession();
        } catch {}

        // Start at phase 1
        goToPhase('scan-resi');
        focusResi();

        // ── Step 1 listeners ──
        el.resiScanBtn?.addEventListener('click', async () => {
            await ensureAudio();
            await lookupResi();
        });
        el.resiCode?.addEventListener('keydown', async e => {
            if (e.key === 'Enter') { e.preventDefault(); await ensureAudio(); await lookupResi(); }
        });

        // ── Resi bar listeners ──
        el.btnChangeResi?.addEventListener('click', () => goToPhase('scan-resi'));

        // ── Step 2 listeners ──
        el.skuCode?.addEventListener('input', onSkuInput);
        el.skuScanBtn?.addEventListener('click', async () => {
            await ensureAudio();
            await scanSku();
        });
        el.skuCode?.addEventListener('keydown', async e => {
            if (e.key === 'Enter') { e.preventDefault(); await ensureAudio(); await scanSku(); }
        });

        el.btnStart?.addEventListener('click', async () => {
            await ensureAudio();
            await startSession();
            setStatus(el.skuStatus,
                state.session?.status === 'active'
                    ? 'Scan QC hari ini aktif. Silakan scan SKU.'
                    : 'Gagal memuat scan QC hari ini.',
                state.session?.status === 'active' ? 'ok' : 'err');
            focusSku();
        });
        el.btnFocus?.addEventListener('click', async () => {
            await ensureAudio();
            focusSku();
        });

        el.pendingResisBody?.addEventListener('click', async (e) => {
            const btn = e.target.closest('.btn-resume-resi');
            if (!btn) return;
            await ensureAudio();
            resumePendingResi(btn.getAttribute('data-id'));
        });

        el.btnRefreshHistory?.addEventListener('click', async () => {
            await refreshSession();
            setStatus(el.resiStatus, 'Riwayat scan resi diperbarui.', 'ok');
            focusResi();
        });

        let historySearchTimer = null;
        el.resiHistorySearch?.addEventListener('input', () => {
            clearTimeout(historySearchTimer);
            historySearchTimer = setTimeout(() => {
                renderResiHistory(state.session?.resis || []);
            }, 150);
        });
        el.resiHistoryLimit?.addEventListener('change', () => {
            renderResiHistory(state.session?.resis || []);
        });

        el.resiHistoryBody?.addEventListener('click', (e) => {
            const btn = e.target.closest('.btn-qc-resi-history-detail');
            if (!btn) return;
            showResiHistoryDetail(btn.getAttribute('data-id'));
        });

        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.altKey || e.metaKey || e.key.length !== 1) return;
            const target = e.target;
            if (target?.closest?.('input, textarea, select, button, a, [contenteditable="true"], .modal')) return;

            const input = activeScanInput();
            if (!input || state.busy) return;
            input.focus();
            input.value += e.key;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            e.preventDefault();
        });

        document.addEventListener('click', (e) => {
            if (e.target?.closest?.('input, textarea, select, button, a, .modal')) return;
            setTimeout(refocusActiveScanner, 20);
        });
    });
</script>
@endpush
