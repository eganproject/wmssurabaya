@extends('layouts.admin')

@section('title', 'Scan Out Input')
@section('page_title', 'Scan Out Input')

@section('content')
<style>
    .scanout-shell {
        max-width: 1680px;
        margin: 0 auto;
    }
    .scanout-card {
        border: 2px solid #dbeafe;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06);
    }
    .scanout-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    }
    .scanout-input {
        height: 64px;
        font-size: 22px;
        letter-spacing: 0.4px;
    }
    .scanout-status {
        min-height: 76px;
        border-radius: 12px;
        padding: 14px 18px;
        background: #f8fafc;
        border: 2px solid #e2e8f0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .scanout-status .dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #94a3b8;
        flex: 0 0 auto;
    }
    .scanout-status .msg {
        color: #0f172a;
        font-size: 16px;
        font-weight: 800;
        line-height: 1.35;
    }
    .scanout-status.ok {
        background: #f0fdf4;
        border-color: #86efac;
    }
    .scanout-status.ok .dot {
        background: #16a34a;
    }
    .scanout-status.warn {
        background: #fffbeb;
        border-color: #fbbf24;
    }
    .scanout-status.warn .dot {
        background: #f59e0b;
    }
    .scanout-status.err {
        background: #fef2f2;
        border-color: #fca5a5;
    }
    .scanout-status.err .dot {
        background: #dc2626;
    }
    .scanout-result {
        display: none;
        border: 1px solid #bbf7d0;
        background: #f0fdf4;
        border-radius: 14px;
        padding: 16px;
    }
    .scanout-result.show {
        display: block;
    }
    .scanout-result-title {
        color: #166534;
        font-size: 16px;
        font-weight: 800;
    }
    .scanout-result-meta {
        color: #475569;
        font-size: 12px;
        margin-top: 4px;
    }
    .scanout-item-list {
        display: grid;
        gap: 8px;
        margin-top: 14px;
    }
    .scanout-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        border: 1px solid #dcfce7;
        background: #fff;
        border-radius: 10px;
        padding: 10px 12px;
    }
    .scanout-issue-list {
        display: grid;
        gap: 8px;
        margin-top: 10px;
    }
    .scanout-issue {
        border: 1px solid #fecaca;
        background: #fff;
        border-radius: 10px;
        padding: 9px 11px;
    }
    .scanout-kpi {
        border: 1px solid #e2e8f0;
        background: #fff;
        border-radius: 12px;
        padding: 12px 14px;
    }
    .scanout-kpi .label {
        color: #64748b;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .scanout-kpi .value {
        color: #0f172a;
        font-size: 20px;
        font-weight: 800;
        margin-top: 2px;
    }
    .scanout-toast.swal2-popup {
        width: 420px !important;
        padding: 14px 16px !important;
        border-radius: 12px !important;
        box-shadow: 0 14px 36px rgba(15, 23, 42, 0.18) !important;
    }
    .scanout-toast .swal2-title {
        font-size: 15px !important;
        line-height: 1.35 !important;
        text-align: left !important;
    }
    .scanout-toast .swal2-html-container {
        margin: 6px 0 0 !important;
        color: #475569 !important;
        font-size: 12px !important;
        line-height: 1.45 !important;
        text-align: left !important;
    }
    .scanout-toast-code {
        display: inline-block;
        margin-bottom: 4px;
        padding: 2px 6px;
        border-radius: 6px;
        background: #f1f5f9;
        color: #0f172a;
        font-weight: 800;
    }
    .scanout-toast-detail {
        font-weight: 800;
    }
    .scanout-toast-action {
        display: block;
        margin-top: 4px;
        color: #0f172a;
        font-weight: 400;
    }
    @media (min-width: 1400px) {
        .scanout-left {
            width: 42%;
        }
        .scanout-right {
            width: 58%;
        }
    }
</style>

<div class="row g-6 scanout-shell">
    <div class="col-xl-5 scanout-left">
        <div class="card scanout-card">
            <div class="card-header border-0 pt-6 pb-0">
                <div class="card-title">
                    <div>
                        <div class="fw-bold fs-4">Scan Out Desktop</div>
                        <div class="text-muted fs-8">Scan resi atau ID pesanan untuk keluar dari QC transit.</div>
                    </div>
                </div>
                <div class="card-toolbar gap-2">
                    <a href="{{ $routes['history'] }}" class="btn btn-sm btn-light">History</a>
                    @if(empty($isLimitedScanOutUser))
                        <a href="{{ $routes['qcTransit'] }}" class="btn btn-sm btn-light">QC Transit</a>
                    @endif
                </div>
            </div>
            <div class="card-body pt-5 pb-7">
                <div class="alert alert-light-primary d-flex align-items-start gap-3 py-3 px-4 mb-6">
                    <i class="fa-solid fa-circle-info mt-1 text-primary"></i>
                    <div class="fs-8 text-gray-700">
                        Gunakan scanner hardware. Setelah barcode mengirim Enter, sistem langsung memproses scan out.
                        <span class="d-block mt-1">Tanggal scan: <strong>{{ $today }}</strong></span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold fs-7 text-uppercase text-muted">Tipe Scan</label>
                    <select class="form-select form-select-solid" id="scan_type" style="height:50px;font-size:16px;">
                        <option value="no_resi">No Resi</option>
                        <option value="id_pesanan">ID Pesanan</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold fs-7 text-uppercase text-muted">Kode Resi / ID Pesanan</label>
                    <input
                        type="text"
                        class="form-control form-control-solid scanout-input scanout-mono"
                        id="scan_code"
                        autocomplete="off"
                        placeholder="Scan barcode lalu Enter"
                    />
                    <div class="form-text">Halaman akan menjaga fokus tetap di input scan.</div>
                </div>

                <button type="button" class="btn btn-primary w-100 fs-6 py-3 mb-4" id="btn_scan_out">
                    <i class="fa-solid fa-truck-ramp-box me-2"></i>Proses Scan Out
                </button>

                <div class="scanout-status" id="scan_status">
                    <span class="dot"></span>
                    <div class="msg">Siap scan out.</div>
                </div>
            </div>
        </div>

        <div class="scanout-result mt-4" id="result_card">
            <div class="d-flex align-items-start justify-content-between gap-3">
                <div>
                    <div class="scanout-result-title" id="result_title">Scan out berhasil</div>
                    <div class="scanout-result-meta" id="result_meta">-</div>
                </div>
                <span class="badge badge-light-success">Sukses</span>
            </div>
            <div class="scanout-item-list" id="result_items"></div>
        </div>
    </div>

    <div class="col-xl-7 scanout-right">
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="scanout-kpi">
                    <div class="label">Scan Berhasil</div>
                    <div class="value" id="kpi_success">0</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="scanout-kpi">
                    <div class="label">Scan Gagal</div>
                    <div class="value" id="kpi_failed">0</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="scanout-kpi">
                    <div class="label">Scan Terakhir</div>
                    <div class="value fs-7" id="kpi_last">-</div>
                </div>
            </div>
        </div>

        <div class="card h-100">
            <div class="card-header border-0 pt-6">
                <div class="card-title">
                    <div>
                        <div class="fw-bold fs-5">Riwayat Scan Out Hari Ini</div>
                        <div class="text-muted fs-8">Data terbaru dari history scan out.</div>
                    </div>
                </div>
                <div class="card-toolbar">
                    <button type="button" class="btn btn-sm btn-light" id="btn_refresh_history">
                        <i class="fa-solid fa-rotate-right"></i>
                    </button>
                </div>
            </div>
            <div class="card-body py-5">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle fs-8 mb-0">
                        <thead>
                            <tr class="text-start text-gray-400 fw-bolder text-uppercase gs-0">
                                <th>Waktu</th>
                                <th>Kode Scan</th>
                                <th>ID Pesanan</th>
                                <th>No Resi</th>
                                <th>Petugas</th>
                            </tr>
                        </thead>
                        <tbody id="history_body">
                            <tr>
                                <td colspan="5" class="text-center text-muted py-8">Memuat riwayat scan out...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="separator my-6"></div>

                <div>
                    <div class="fw-bold fs-7 text-uppercase text-muted mb-3">Catatan Validasi Terakhir</div>
                    <div id="issue_body" class="scanout-issue-list">
                        <div class="text-muted fs-8">Belum ada validasi gagal.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const routes = @json($routes);
    let csrf = '{{ csrf_token() }}';
    const todayStr = '{{ $today }}';

    const state = {
        busy: false,
        successCount: 0,
        failedCount: 0,
        lastValidationErrorAt: 0,
    };

    const el = {
        scanType: document.getElementById('scan_type'),
        scanCode: document.getElementById('scan_code'),
        scanBtn: document.getElementById('btn_scan_out'),
        scanStatus: document.getElementById('scan_status'),
        resultCard: document.getElementById('result_card'),
        resultTitle: document.getElementById('result_title'),
        resultMeta: document.getElementById('result_meta'),
        resultItems: document.getElementById('result_items'),
        historyBody: document.getElementById('history_body'),
        issueBody: document.getElementById('issue_body'),
        refreshHistory: document.getElementById('btn_refresh_history'),
        kpiSuccess: document.getElementById('kpi_success'),
        kpiFailed: document.getElementById('kpi_failed'),
        kpiLast: document.getElementById('kpi_last'),
    };

    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    function setStatus(message, type = '') {
        el.scanStatus.classList.remove('ok', 'warn', 'err');
        if (type) el.scanStatus.classList.add(type);
        const msg = el.scanStatus.querySelector('.msg');
        if (msg) msg.textContent = message || '';
    }

    function setBusy(busy) {
        state.busy = !!busy;
        if (el.scanBtn) el.scanBtn.disabled = state.busy;
        if (el.scanCode) el.scanCode.disabled = state.busy;
    }

    function isScanOutRedirectUrl(url) {
        try {
            const parsed = new URL(url, window.location.origin);
            return parsed.pathname === '/dashboard'
                || parsed.pathname === '/admin'
                || parsed.pathname === '/admin/dashboard'
                || parsed.pathname === '/mobile/dashboard';
        } catch {
            return false;
        }
    }

    function focusScan() {
        if (!state.busy && el.scanCode) {
            el.scanCode.focus();
            el.scanCode.select?.();
        }
    }

    function updateKpis() {
        el.kpiSuccess.textContent = String(state.successCount);
        el.kpiFailed.textContent = String(state.failedCount);
    }

    async function refreshCsrfToken() {
        if (!routes.csrfToken) return false;

        const response = await fetch(routes.csrfToken, {
            credentials: 'same-origin',
            redirect: 'manual',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const text = await response.text();
        let json = null;
        try { json = JSON.parse(text); } catch {}
        if (!response.ok || !json?.csrf_token) return false;

        csrf = json.csrf_token;
        return true;
    }

    function withCurrentCsrfBody(options = {}) {
        const headers = {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf,
            ...(options.headers || {}),
        };

        let body = options.body;
        const contentType = String(headers['Content-Type'] || headers['content-type'] || '');
        if (body && contentType.includes('application/json')) {
            try {
                const payload = JSON.parse(body);
                body = JSON.stringify({ ...payload, _token: csrf });
            } catch {}
        }

        return { headers, body };
    }

    async function fetchJson(url, options = {}, retryOnCsrf = true) {
        const csrfAware = withCurrentCsrfBody(options);
        const response = await fetch(url, {
            credentials: 'same-origin',
            redirect: 'manual',
            ...options,
            headers: csrfAware.headers,
            body: csrfAware.body,
        });
        if (response.type === 'opaqueredirect' || (response.status >= 300 && response.status < 400)) {
            const error = new Error('Request scan out dialihkan. Tetap di halaman scan out dan silakan scan ulang.');
            error.status = response.status;
            throw error;
        }
        const text = await response.text();
        let json = null;
        try { json = JSON.parse(text); } catch {}
        if (response.status === 419 && retryOnCsrf && String(options.method || 'GET').toUpperCase() !== 'GET') {
            const refreshed = await refreshCsrfToken();
            if (refreshed) {
                return fetchJson(url, options, false);
            }
        }
        if (response.redirected || (!json && response.ok)) {
            const error = new Error('Request scan out dialihkan. Tetap di halaman scan out dan silakan scan ulang.');
            error.status = response.status;
            throw error;
        }
        if (!response.ok) {
            let message = json?.message || 'Terjadi kesalahan.';
            if (json?.errors) {
                const first = Object.values(json.errors)[0];
                if (Array.isArray(first) && first.length) message = first[0];
            }
            const error = new Error(message);
            error.details = json?.details || [];
            error.status = response.status;
            throw error;
        }
        return json;
    }

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
                gain.gain.exponentialRampToValueAtTime(0.035, now + t + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + t + d);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(now + t);
                osc.stop(now + t + d);
            });
        } catch {}
    }

    function toast(icon, title, detail, code = '', action = '', timer = 3200) {
        if (!window.Swal) return;
        window.Swal.fire({
            icon,
            title,
            html: `
                ${code ? `<span class="scanout-toast-code scanout-mono">${esc(code)}</span><br>` : ''}
                <span class="scanout-toast-detail">${esc(detail)}</span>
                ${action ? `<span class="scanout-toast-action">${esc(action)}</span>` : ''}
            `,
            toast: true,
            position: 'top-end',
            timer,
            timerProgressBar: true,
            showConfirmButton: false,
            returnFocus: false,
            customClass: { popup: 'scanout-toast' },
            didOpen: () => setTimeout(focusScan, 20),
            didClose: () => setTimeout(focusScan, 20),
        });
    }

    function renderIssues(message, details = []) {
        const rows = Array.isArray(details) ? details : [];
        if (!rows.length) {
            el.issueBody.innerHTML = `<div class="scanout-issue">
                <div class="fw-bold text-danger">Validasi gagal</div>
                <div class="text-muted fs-8">${esc(message)}</div>
            </div>`;
            return;
        }

        el.issueBody.innerHTML = rows.map(row => {
            const stockLine = row.available !== undefined
                ? `Butuh ${Number(row.required || 0).toLocaleString('id-ID')}, tersedia ${Number(row.available || 0).toLocaleString('id-ID')}`
                : `Butuh ${Number(row.required || 0).toLocaleString('id-ID')}`;
            return `<div class="scanout-issue">
                <div class="d-flex align-items-center justify-content-between gap-2">
                    <span class="fw-bold scanout-mono text-danger">${esc(row.sku || '-')}</span>
                    <span class="badge badge-light-danger">${esc(row.reason || 'Validasi gagal')}</span>
                </div>
                <div class="text-muted fs-8 mt-1">${esc(stockLine)}</div>
            </div>`;
        }).join('');
    }

    function clearIssues() {
        el.issueBody.innerHTML = `<div class="text-muted fs-8">Belum ada validasi gagal.</div>`;
    }

    function renderResult(data, code) {
        const resi = data?.resi || {};
        const items = Array.isArray(data?.items) ? data.items : [];
        const meta = [
            resi.id_pesanan ? `ID Pesanan: ${resi.id_pesanan}` : null,
            resi.no_resi ? `No Resi: ${resi.no_resi}` : null,
            resi.tanggal_pesanan ? `Tanggal Order: ${resi.tanggal_pesanan}` : null,
        ].filter(Boolean).join(' | ');

        el.resultTitle.textContent = data?.message || 'Scan out berhasil';
        el.resultMeta.textContent = meta || code || '-';
        el.resultItems.innerHTML = items.length ? items.map(item => {
            const excluded = !!item.excluded;
            return `<div class="scanout-item">
                <div>
                    <div class="fw-bold scanout-mono">${esc(item.sku || '-')}</div>
                    ${excluded ? '<div class="text-muted fs-9">SKU exception scan out</div>' : ''}
                </div>
                <div class="text-end">
                    <div class="fw-bold">${Number(item.qty || 0).toLocaleString('id-ID')} qty</div>
                    ${excluded ? '<span class="badge badge-light-warning">Exception</span>' : ''}
                </div>
            </div>`;
        }).join('') : `<div class="text-muted fs-8">Tidak ada detail SKU.</div>`;
        el.resultCard.classList.add('show');
    }

    async function submitScan() {
        if (state.busy) return;
        const type = el.scanType.value || 'no_resi';
        const code = (el.scanCode.value || '').trim();

        if (!code) {
            setStatus('Kode scan kosong. Scan ulang resi atau ID pesanan.', 'warn');
            toast('warning', 'Kode Kosong', 'Scanner belum mengirim kode.', '', 'Arahkan scanner ke barcode, lalu scan ulang.', 2600);
            focusScan();
            return;
        }

        setBusy(true);
        setStatus(`Memproses ${code}...`, 'warn');
        try {
            await ensureAudio();
            const data = await fetchJson(routes.scan, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, code }),
            });

            state.successCount += 1;
            updateKpis();
            clearIssues();
            renderResult(data, code);
            setStatus(data?.message || 'Scan out berhasil.', 'ok');
            toast('success', 'Scan Out Berhasil', data?.message || 'Resi berhasil keluar dari QC transit.', code, 'Siap scan resi berikutnya.', 2200);
            await beep('ok');
            el.scanCode.value = '';
            el.kpiLast.textContent = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            refreshHistory();
        } catch (error) {
            state.failedCount += 1;
            updateKpis();
            renderIssues(error.message || 'Gagal memproses scan out.', error.details || []);
            setStatus(`${error.message || 'Gagal memproses scan out.'} [${code}]`, 'err');
            toast('error', 'Scan Out Gagal', error.message || 'Gagal memproses scan out.', code, 'Data belum tercatat. Perbaiki penyebabnya, lalu scan ulang.', 5200);
            state.lastValidationErrorAt = Date.now();
            await beep('err');
            el.scanCode.value = '';
        } finally {
            setBusy(false);
            focusScan();
        }
    }

    async function refreshHistory() {
        if (!el.historyBody) return;
        const params = new URLSearchParams({
            draw: '1',
            start: '0',
            length: '8',
            date_from: todayStr,
            date_to: todayStr,
        });
        try {
            const json = await fetchJson(`${routes.historyData}?${params}`);
            const rows = Array.isArray(json.data) ? json.data : [];
            if (!rows.length) {
                el.historyBody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-8">Belum ada scan out hari ini.</td></tr>`;
                return;
            }
            el.historyBody.innerHTML = rows.map(row => `<tr>
                <td class="text-muted">${esc(row.scanned_at || '-')}</td>
                <td><span class="fw-bold scanout-mono text-primary">${esc(row.scan_code || '-')}</span><div class="text-muted fs-9">${esc(row.scan_type || '-')}</div></td>
                <td>${esc(row.id_pesanan || '-')}</td>
                <td>${esc(row.no_resi || '-')}</td>
                <td>${esc(row.scanner || '-')}</td>
            </tr>`).join('');
        } catch (error) {
            el.historyBody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-8">${esc(error.message || 'Gagal memuat riwayat.')}</td></tr>`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        focusScan();
        refreshHistory();
        updateKpis();

        el.scanBtn?.addEventListener('click', submitScan);
        el.scanCode?.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitScan();
            }
        });
        el.refreshHistory?.addEventListener('click', () => {
            refreshHistory();
            focusScan();
        });

        document.addEventListener('keydown', event => {
            if (event.ctrlKey || event.altKey || event.metaKey || event.key.length !== 1) return;
            const target = event.target;
            if (target?.closest?.('input, textarea, select, button, a, [contenteditable="true"], .modal')) return;
            if (state.busy || !el.scanCode) return;
            el.scanCode.focus();
            el.scanCode.value += event.key;
            event.preventDefault();
        });

        document.addEventListener('click', event => {
            if (event.target?.closest?.('input, textarea, select, button, a, .modal')) return;
            setTimeout(focusScan, 20);
        });

        document.addEventListener('submit', event => {
            if (event.target?.closest?.('.modal')) return;
            event.preventDefault();
            focusScan();
        }, true);

        document.addEventListener('click', event => {
            const link = event.target?.closest?.('a[href]');
            if (!link) return;
            if (Date.now() - state.lastValidationErrorAt > 6000) return;
            if (!isScanOutRedirectUrl(link.href)) return;
            event.preventDefault();
            focusScan();
        }, true);
    });
</script>
@endpush
