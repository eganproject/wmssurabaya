@extends('layouts.admin')

@section('title', 'Scan Outbound Manual')
@section('page_title', 'Scan Outbound Manual')

@php
    $summary = [
        'planned_qty' => collect($rows)->sum('planned_qty'),
        'scanned_qty' => collect($rows)->sum('scanned_qty'),
        'remaining_qty' => collect($rows)->sum('remaining_qty'),
    ];
    $isComplete = ($transaction->scan_status ?? 'not_started') === 'complete';
    $isApproved = ($transaction->status ?? 'pending') === 'approved';
@endphp

@section('content')
<style>
    .scan-shell { max-width: 1280px; margin: 0 auto; }
    .scan-input { height: 62px; font-size: 24px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; letter-spacing: .5px; }
    .scan-status { border-radius: 8px; padding: 12px 14px; border: 1px solid #e5e7eb; background: #f8fafc; min-height: 48px; }
    .scan-status.ok { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .scan-status.err { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .scan-status.warn { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .scan-progress { height: 10px; border-radius: 999px; background: #e5e7eb; overflow: hidden; }
    .scan-progress > div { height: 100%; background: #1a56db; transition: width .2s ease; }
    .scan-progress > div.complete { background: #16a34a; }
    .scan-row-complete { background: #f0fdf4; }
</style>

<div class="scan-shell">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-5">
        <div>
            <h2 class="fw-bolder fs-3 mb-1">{{ $transaction->code }}</h2>
            <div class="text-muted fs-7">
                {{ $transaction->warehouse?->name ?? '-' }} &middot;
                {{ $transaction->transacted_at?->format('Y-m-d H:i') ?? '-' }} &middot;
                {{ $transaction->creator?->name ?? '-' }}
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ $detailUrl }}" class="btn btn-light">Kembali Detail</a>
        </div>
    </div>

    @if($isApproved)
        <div class="alert alert-success d-flex align-items-center p-5">
            <i class="fas fa-check-circle fs-2 me-4"></i>
            <div>
                <div class="fw-bold">Outbound manual sudah disetujui</div>
                <div class="text-muted">Scan tidak bisa diubah setelah transaksi approved.</div>
            </div>
        </div>
    @endif

    <div class="row g-5">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div class="fw-bolder">Input Scan SKU</div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <form id="scan_form">
                        @csrf
                        <label class="required fs-6 fw-bold form-label mb-2">SKU</label>
                        <input
                            type="text"
                            id="scan_sku"
                            class="form-control form-control-solid scan-input"
                            autocomplete="off"
                            autofocus
                            placeholder="SCAN SKU"
                            @disabled($isComplete || $isApproved)
                        >
                        <div class="form-text mt-2">
                            {{ ($transaction->warehouse?->type ?? '') === \App\Models\Warehouse::TYPE_BULK
                                ? 'Gudang besar: 1 scan mengikuti conversion qty/unit item.'
                                : 'Gudang kecil/display: 1 scan = 1 PCS/SET.' }}
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-4" @disabled($isComplete || $isApproved)>
                            Scan
                        </button>
                    </form>

                    <div id="scan_status" class="scan-status mt-5 {{ $isComplete ? 'ok' : '' }}">
                        @if($isComplete)
                            Scan complete. Transaksi siap di-approve.
                        @else
                            Siap scan SKU.
                        @endif
                    </div>

                    <button type="button" id="btn_finish_scan" class="btn btn-success w-100 mt-4" @disabled($isComplete || $isApproved || $summary['remaining_qty'] > 0)>
                        Selesai Scan
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title">
                        <div>
                            <div class="fw-bolder">Progress Scan</div>
                            <div class="text-muted fs-7">
                                Status:
                                @if($isComplete)
                                    <span class="badge badge-light-success">Complete</span>
                                @elseif(($transaction->scan_status ?? 'not_started') === 'in_progress')
                                    <span class="badge badge-light-primary">In Progress</span>
                                @else
                                    <span class="badge badge-light-warning">Belum Mulai</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="row g-3 mb-5">
                        <div class="col-md-4">
                            <div class="border rounded p-4">
                                <div class="text-muted fs-8 text-uppercase">Rencana</div>
                                <div class="fw-bolder fs-2" id="summary_planned">{{ number_format($summary['planned_qty']) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-4">
                                <div class="text-muted fs-8 text-uppercase">Sudah Scan</div>
                                <div class="fw-bolder fs-2 text-primary" id="summary_scanned">{{ number_format($summary['scanned_qty']) }}</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-4">
                                <div class="text-muted fs-8 text-uppercase">Sisa</div>
                                <div class="fw-bolder fs-2 text-danger" id="summary_remaining">{{ number_format($summary['remaining_qty']) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="scan-progress mb-5">
                        <div id="summary_bar" class="{{ $summary['remaining_qty'] === 0 ? 'complete' : '' }}" style="width: {{ $summary['planned_qty'] > 0 ? min(100, ($summary['scanned_qty'] / $summary['planned_qty']) * 100) : 0 }}%"></div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-4">
                            <thead>
                                <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                    <th>SKU</th>
                                    <th>Item</th>
                                    <th>Satuan</th>
                                    <th class="text-end">Per Scan</th>
                                    <th class="text-end">Rencana</th>
                                    <th class="text-end">Scan</th>
                                    <th class="text-end">Sisa</th>
                                </tr>
                            </thead>
                            <tbody id="scan_rows">
                                @foreach($rows as $row)
                                    <tr data-row-id="{{ $row['outbound_item_id'] }}" @class(['scan-row-complete' => $row['complete']])>
                                        <td class="fw-bold">{{ $row['sku'] }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td>{{ $row['unit'] }}</td>
                                        <td class="text-end" data-cell="scan_qty">{{ number_format($row['scan_qty']) }}</td>
                                        <td class="text-end" data-cell="planned_qty">{{ number_format($row['planned_qty']) }}</td>
                                        <td class="text-end fw-bold text-primary" data-cell="scanned_qty">{{ number_format($row['scanned_qty']) }}</td>
                                        <td class="text-end fw-bold text-danger" data-cell="remaining_qty">{{ number_format($row['remaining_qty']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const scanUrl = @json($scanUrl);
    const finishUrl = @json($finishUrl);
    const csrfToken = @json(csrf_token());

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('scan_form');
        const input = document.getElementById('scan_sku');
        const status = document.getElementById('scan_status');
        const finishBtn = document.getElementById('btn_finish_scan');
        const plannedEl = document.getElementById('summary_planned');
        const scannedEl = document.getElementById('summary_scanned');
        const remainingEl = document.getElementById('summary_remaining');
        const barEl = document.getElementById('summary_bar');

        const fmt = (value) => Number(value || 0).toLocaleString('id-ID');
        const setStatus = (message, type = '') => {
            status.textContent = message;
            status.className = `scan-status mt-5 ${type}`.trim();
        };

        const applyPayload = (payload) => {
            const rows = payload?.rows || [];
            rows.forEach((row) => {
                const tr = document.querySelector(`[data-row-id="${row.outbound_item_id}"]`);
                if (!tr) return;
                tr.classList.toggle('scan-row-complete', !!row.complete);
                ['scan_qty', 'planned_qty', 'scanned_qty', 'remaining_qty'].forEach((key) => {
                    const cell = tr.querySelector(`[data-cell="${key}"]`);
                    if (cell) cell.textContent = fmt(row[key]);
                });
            });

            const summary = payload?.summary || {};
            const planned = Number(summary.planned_qty || 0);
            const scanned = Number(summary.scanned_qty || 0);
            const remaining = Number(summary.remaining_qty || 0);
            if (plannedEl) plannedEl.textContent = fmt(planned);
            if (scannedEl) scannedEl.textContent = fmt(scanned);
            if (remainingEl) remainingEl.textContent = fmt(remaining);
            if (barEl) {
                const pct = planned > 0 ? Math.min(100, (scanned / planned) * 100) : 0;
                barEl.style.width = `${pct}%`;
                barEl.classList.toggle('complete', remaining === 0);
            }
            if (finishBtn) finishBtn.disabled = remaining > 0;
        };

        const readJson = async (res) => {
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                return { message: 'Respons server tidak valid' };
            }
        };

        form?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const sku = (input?.value || '').trim();
            if (!sku) {
                setStatus('SKU wajib diisi.', 'warn');
                input?.focus();
                return;
            }

            input.disabled = true;
            try {
                const res = await fetch(scanUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ sku }),
                });
                const json = await readJson(res);
                if (!res.ok) {
                    const message = json?.errors?.sku?.[0] || json?.errors?.scan?.[0] || json?.message || 'Scan gagal';
                    setStatus(message, 'err');
                    return;
                }
                applyPayload(json);
                setStatus(json.message || 'Scan berhasil.', json.all_complete ? 'ok' : 'ok');
            } catch (err) {
                setStatus('Gagal scan SKU.', 'err');
            } finally {
                input.value = '';
                input.disabled = false;
                input.focus();
            }
        });

        finishBtn?.addEventListener('click', async () => {
            finishBtn.disabled = true;
            try {
                const res = await fetch(finishUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });
                const json = await readJson(res);
                if (!res.ok) {
                    const message = json?.errors?.scan?.[0] || json?.message || 'Gagal menyelesaikan scan';
                    setStatus(message, 'err');
                    finishBtn.disabled = false;
                    return;
                }
                applyPayload(json);
                setStatus(json.message || 'Scan complete.', 'ok');
                input.disabled = true;
                finishBtn.disabled = true;
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Berhasil', json.message || 'Scan complete.', 'success');
                }
            } catch (err) {
                setStatus('Gagal menyelesaikan scan.', 'err');
                finishBtn.disabled = false;
            }
        });

        input?.focus();
    });
</script>
@endpush
