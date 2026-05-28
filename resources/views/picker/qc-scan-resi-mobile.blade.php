<style>
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar-actions form {
        margin: 0;
    }
    .qc-phase {
        display: none;
    }
    .qc-phase.active {
        display: grid;
        gap: 12px;
    }
    .section-title {
        font-weight: 800;
        margin-bottom: 6px;
    }
    .scan-actions {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .scan-row {
        display: grid;
        gap: 8px;
        grid-template-columns: 1fr auto;
        align-items: center;
    }
    .scan-btn,
    .photo-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
        border-radius: 12px;
        font-weight: 800;
        border: 1px solid var(--border);
        background: #fff;
    }
    .photo-scan {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: var(--muted);
    }
    .photo-btn {
        border-style: dashed;
    }
    .status-line {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 12px;
        margin-top: 10px;
        font-size: 13px;
        font-weight: 800;
        line-height: 1.35;
        color: #334155;
        background: #f8fafc;
    }
    .status-line.ok {
        color: #047857;
        background: #ecfdf5;
        border-color: #a7f3d0;
    }
    .status-line.warn {
        color: #b45309;
        background: #fffbeb;
        border-color: #fde68a;
    }
    .status-line.err {
        color: #b91c1c;
        background: #fef2f2;
        border-color: #fecaca;
    }
    .resi-box {
        border: 1px solid #bfdbfe;
        background: linear-gradient(135deg, #eff6ff, #f0fdf4);
        border-radius: 16px;
        padding: 12px;
    }
    .resi-code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 15px;
        font-weight: 800;
    }
    .resi-meta {
        margin-top: 4px;
        color: var(--muted);
        font-size: 12px;
    }
    .progress-wrap {
        height: 8px;
        border-radius: 999px;
        overflow: hidden;
        background: #e2e8f0;
        margin: 10px 0 12px;
    }
    .progress-fill {
        height: 100%;
        width: 0%;
        background: linear-gradient(90deg, #0ea5e9, #10b981);
        transition: width 0.25s ease;
    }
    .next-box {
        border-radius: 16px;
        padding: 12px;
        color: #fff;
        background: #0f172a;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }
    .next-label {
        color: #94a3b8;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .next-sku {
        font-size: 18px;
        font-weight: 900;
        line-height: 1.1;
        word-break: break-word;
    }
    .next-name {
        margin-top: 3px;
        color: #cbd5e1;
        font-size: 11px;
    }
    .next-qty {
        white-space: nowrap;
        font-weight: 900;
        font-size: 16px;
    }
    .checklist {
        display: grid;
        gap: 8px;
        max-height: 280px;
        overflow: auto;
    }
    .check-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 10px 12px;
        background: #fff;
    }
    .check-row.next {
        background: #ecfeff;
        border-color: #67e8f9;
    }
    .check-row.done {
        background: #f0fdf4;
        border-color: #bbf7d0;
    }
    .check-sku {
        font-weight: 900;
        word-break: break-word;
    }
    .check-name {
        margin-top: 2px;
        color: var(--muted);
        font-size: 11px;
    }
    .qty-pill {
        border-radius: 999px;
        padding: 4px 8px;
        background: #f1f5f9;
        font-size: 11px;
        font-weight: 800;
        white-space: nowrap;
    }
    .qty-pill.done {
        background: #dcfce7;
        color: #15803d;
    }
    .history-list {
        display: grid;
        gap: 8px;
    }
    .history-row {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 10px 12px;
        background: #fff;
    }
    .scanner-modal {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.72);
        display: none;
        align-items: center;
        justify-content: center;
        padding: 16px;
        z-index: 50;
    }
    .scanner-card {
        width: 100%;
        max-width: 520px;
        background: #fff;
        border-radius: 18px;
        padding: 14px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow);
        display: grid;
        gap: 10px;
    }
    .scanner-video,
    .scanner-qr {
        width: 100%;
        border-radius: 14px;
        background: #111827;
    }
    .scanner-qr {
        overflow: hidden;
        display: none;
    }
    .scanner-actions {
        display: flex;
        justify-content: space-between;
        gap: 8px;
    }
    .scanner-actions .primary-btn,
    .scanner-actions .ghost-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">{{ config('app.name') }}</div>
            <div class="subtitle">QC Scan Input</div>
        </div>
        <div class="topbar-actions">
            <a href="{{ $routes['dashboard'] }}" class="logout">Dashboard</a>
            <form method="POST" action="{{ $routes['logout'] }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="qc-phase active" id="phase_resi">
        <div class="card">
            <div class="section-title">Scan Resi</div>
            <div class="muted">Scan No Resi atau ID Pesanan terlebih dahulu. Daftar SKU akan muncul setelah resi valid.</div>
            <div class="scan-actions">
                <select class="input" id="resi_type">
                    <option value="no_resi">No Resi</option>
                    <option value="id_pesanan">ID Pesanan</option>
                </select>
                <div class="scan-row">
                    <input type="text" class="input" id="resi_code" placeholder="Scan resi lalu Enter" autocomplete="off" />
                    <button type="button" class="scan-btn" id="btn_open_scanner_resi">Scan</button>
                </div>
                <button type="button" class="primary-btn" id="btn_load_resi">Muat Resi</button>
                <div class="photo-scan" id="photo_scan_wrap_resi">
                    <button type="button" class="photo-btn" id="btn_scan_photo_resi">Scan via Foto</button>
                    <span>Alternatif untuk iPhone.</span>
                </div>
            </div>
            <div class="status-line" id="resi_status">Siap scan resi.</div>
        </div>

        <div class="card">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">
                <div style="font-weight:800;">Riwayat Resi Hari Ini</div>
                <button type="button" class="scan-btn" id="btn_refresh_history">Refresh</button>
            </div>
            <div class="history-list" id="history_list">
                <div class="muted">Belum ada riwayat scan resi hari ini.</div>
            </div>
        </div>
    </div>

    <div class="qc-phase" id="phase_sku">
        <div class="resi-box">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                <div>
                    <div class="resi-code" id="disp_no_resi">-</div>
                    <div class="resi-meta" id="disp_id_pesanan">-</div>
                    <div class="resi-meta" id="disp_meta">-</div>
                </div>
                <button type="button" class="scan-btn" id="btn_change_resi">Ganti</button>
            </div>
        </div>

        <div class="card">
            <div class="next-box">
                <div>
                    <div class="next-label">SKU Berikutnya</div>
                    <div class="next-sku" id="next_sku">-</div>
                    <div class="next-name" id="next_name">Scan SKU yang masih belum lengkap.</div>
                </div>
                <div class="next-qty" id="next_qty">0/0</div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;">
                <strong id="progress_text">0 / 0 SKU selesai</strong>
                <strong id="progress_pct">0%</strong>
            </div>
            <div class="progress-wrap"><div class="progress-fill" id="progress_bar"></div></div>
            <div class="checklist" id="sku_checklist"></div>
        </div>

        <div class="card">
            <div class="section-title">Scan SKU</div>
            <div class="scan-actions">
                <div class="scan-row">
                    <input type="text" class="input" id="sku_code" placeholder="Scan SKU lalu Enter" autocomplete="off" />
                    <button type="button" class="scan-btn" id="btn_open_scanner_sku">Scan</button>
                </div>
                <div class="scan-row">
                    <input type="number" class="input" id="sku_qty" min="1" value="1" />
                    <button type="button" class="primary-btn" id="btn_scan_sku">Scan SKU</button>
                </div>
                <div class="photo-scan" id="photo_scan_wrap_sku">
                    <button type="button" class="photo-btn" id="btn_scan_photo_sku">Scan via Foto</button>
                    <span>Alternatif untuk iPhone.</span>
                </div>
            </div>
            <div class="status-line" id="sku_status">Siap scan SKU.</div>
        </div>
    </div>
</div>

<input type="file" id="scan_photo" accept="image/*" capture="environment" style="display:none;" />

<div class="scanner-modal" id="scanner_modal">
    <div class="scanner-card">
        <div style="font-weight:700;">Kamera Scanner</div>
        <video class="scanner-video" id="scanner_video" playsinline></video>
        <div class="scanner-qr" id="scanner_qr"></div>
        <div class="scanner-actions">
            <button type="button" class="ghost-btn" id="btn_close_scanner">Tutup</button>
            <button type="button" class="primary-btn" id="btn_start_scan">Mulai Scan</button>
        </div>
        <div class="muted" id="scanner_hint">Arahkan kamera ke barcode.</div>
    </div>
</div>

<script>
    const routes = @json($routes);
    const csrfToken = '{{ csrf_token() }}';

    const state = {
        phase: 'resi',
        session: null,
        busy: false,
        resi: null,
        checklist: [],
        itemNames: {},
        scanTarget: 'resi',
        completionTimer: null,
    };

    const el = {
        phaseResi: document.getElementById('phase_resi'),
        phaseSku: document.getElementById('phase_sku'),
        resiType: document.getElementById('resi_type'),
        resiCode: document.getElementById('resi_code'),
        resiStatus: document.getElementById('resi_status'),
        btnLoadResi: document.getElementById('btn_load_resi'),
        btnChangeResi: document.getElementById('btn_change_resi'),
        historyList: document.getElementById('history_list'),
        btnRefreshHistory: document.getElementById('btn_refresh_history'),
        dispNoResi: document.getElementById('disp_no_resi'),
        dispIdPesanan: document.getElementById('disp_id_pesanan'),
        dispMeta: document.getElementById('disp_meta'),
        nextSku: document.getElementById('next_sku'),
        nextName: document.getElementById('next_name'),
        nextQty: document.getElementById('next_qty'),
        progressText: document.getElementById('progress_text'),
        progressPct: document.getElementById('progress_pct'),
        progressBar: document.getElementById('progress_bar'),
        skuChecklist: document.getElementById('sku_checklist'),
        skuCode: document.getElementById('sku_code'),
        skuQty: document.getElementById('sku_qty'),
        skuStatus: document.getElementById('sku_status'),
        btnScanSku: document.getElementById('btn_scan_sku'),
        btnOpenScannerResi: document.getElementById('btn_open_scanner_resi'),
        btnOpenScannerSku: document.getElementById('btn_open_scanner_sku'),
        btnScanPhotoResi: document.getElementById('btn_scan_photo_resi'),
        btnScanPhotoSku: document.getElementById('btn_scan_photo_sku'),
        photoInput: document.getElementById('scan_photo'),
        photoWrapResi: document.getElementById('photo_scan_wrap_resi'),
        photoWrapSku: document.getElementById('photo_scan_wrap_sku'),
        scannerModal: document.getElementById('scanner_modal'),
        scannerVideo: document.getElementById('scanner_video'),
        scannerQr: document.getElementById('scanner_qr'),
        btnCloseScanner: document.getElementById('btn_close_scanner'),
        btnStartScan: document.getElementById('btn_start_scan'),
        scannerHint: document.getElementById('scanner_hint'),
    };

    const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
    }[char]));

    function setStatus(node, message, type = '') {
        if (!node) return;
        node.classList.remove('ok', 'warn', 'err');
        if (type) node.classList.add(type);
        node.textContent = message || '';
    }

    function activeInput() {
        return state.phase === 'sku' ? el.skuCode : el.resiCode;
    }

    function focusActive() {
        if (state.busy) return;
        const input = activeInput();
        if (input) {
            input.focus();
            input.select?.();
        }
    }

    function makeRequestId() {
        if (window.crypto?.randomUUID) return window.crypto.randomUUID();
        return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    }

    async function fetchJson(url, options = {}) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken, ...(options.headers || {}) },
            credentials: 'same-origin',
            ...options,
        });
        const text = await response.text();
        let json = null;
        try { json = JSON.parse(text); } catch {}
        if (!response.ok) {
            let message = json?.message || 'Terjadi kesalahan.';
            if (json?.errors) {
                const first = Object.values(json.errors)[0];
                if (Array.isArray(first) && first.length) message = first[0];
            }
            throw new Error(message);
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

    function toast(icon, title, text, timer = 2600) {
        if (!window.Swal) return;
        window.Swal.fire({
            icon, title, text,
            toast: true,
            position: 'top-end',
            timer,
            timerProgressBar: true,
            showConfirmButton: false,
            returnFocus: false,
            didOpen: () => setTimeout(focusActive, 20),
            didClose: () => setTimeout(focusActive, 20),
        });
    }

    function setBusy(busy) {
        state.busy = !!busy;
        [el.btnLoadResi, el.btnScanSku, el.resiCode, el.skuCode].forEach(node => {
            if (node) node.disabled = state.busy;
        });
    }

    function goPhase(phase) {
        if (state.completionTimer) clearTimeout(state.completionTimer);
        state.phase = phase;
        el.phaseResi.classList.toggle('active', phase === 'resi');
        el.phaseSku.classList.toggle('active', phase === 'sku');
        if (phase === 'resi') {
            el.resiCode.value = '';
            el.skuCode.value = '';
            el.skuQty.value = '1';
            setStatus(el.resiStatus, 'Siap scan resi.');
        } else {
            setStatus(el.skuStatus, 'Siap scan SKU.');
        }
        setTimeout(focusActive, 50);
    }

    function progress() {
        const total = state.checklist.length;
        const done = state.checklist.filter(item => Number(item.scanned_qty || 0) >= Number(item.qty || 0)).length;
        const pct = total ? Math.round(done / total * 100) : 0;
        return { total, done, pct };
    }

    function nextItem() {
        return state.checklist.find(item => Number(item.scanned_qty || 0) < Number(item.qty || 0)) || null;
    }

    function renderProgress() {
        const { total, done, pct } = progress();
        el.progressText.textContent = `${done} / ${total} SKU selesai`;
        el.progressPct.textContent = `${pct}%`;
        el.progressBar.style.width = `${pct}%`;
        const next = nextItem();
        if (next) {
            const scanned = Number(next.scanned_qty || 0);
            const qty = Number(next.qty || 0);
            el.nextSku.textContent = next.sku || '-';
            el.nextName.textContent = state.itemNames[String(next.sku || '').toLowerCase()] || 'Scan SKU ini atau SKU lain yang ada di daftar.';
            el.nextQty.textContent = `${scanned}/${qty}`;
        } else {
            el.nextSku.textContent = 'SELESAI';
            el.nextName.textContent = 'Semua SKU pada resi ini sudah lengkap.';
            el.nextQty.textContent = '';
        }

        el.skuChecklist.innerHTML = state.checklist.map(item => {
            const qty = Number(item.qty || 0);
            const scanned = Number(item.scanned_qty || 0);
            const doneItem = scanned >= qty;
            const isNext = next && next.sku === item.sku;
            const name = state.itemNames[String(item.sku || '').toLowerCase()] || '';
            return `<div class="check-row ${doneItem ? 'done' : ''} ${isNext ? 'next' : ''}">
                <div>
                    <div class="check-sku">${esc(item.sku)}</div>
                    <div class="check-name">${esc(name)}</div>
                </div>
                <span class="qty-pill ${doneItem ? 'done' : ''}">${scanned}/${qty}</span>
            </div>`;
        }).join('');
    }

    function renderSession() {
        const rows = Array.isArray(state.session?.resis) ? state.session.resis : [];
        (state.session?.items || []).forEach(item => {
            if (item.sku && item.name) state.itemNames[String(item.sku).toLowerCase()] = item.name;
        });
        if (!rows.length) {
            el.historyList.innerHTML = '<div class="muted">Belum ada riwayat scan resi hari ini.</div>';
            return;
        }
        el.historyList.innerHTML = rows.slice(0, 8).map(row => {
            const label = row.no_resi || row.id_pesanan || '-';
            return `<div class="history-row">
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <strong class="resi-code">${esc(label)}</strong>
                    <span class="qty-pill">${Number(row.progress || 0)}%</span>
                </div>
                <div class="muted">${esc(row.id_pesanan || '-')} • ${Number(row.scanned_qty || 0)}/${Number(row.required_qty || 0)} qty</div>
            </div>`;
        }).join('');
    }

    async function refreshSession() {
        const json = await fetchJson(routes.qcCurrent);
        state.session = json.session || null;
        renderSession();
    }

    async function lookupResi() {
        if (state.busy) return;
        const type = el.resiType.value || 'no_resi';
        const code = (el.resiCode.value || '').trim();
        if (!code) {
            setStatus(el.resiStatus, 'Kode resi kosong. Scan ulang.', 'warn');
            toast('warning', 'Resi Kosong', 'Scanner belum mengirim kode resi.');
            focusActive();
            return;
        }
        setBusy(true);
        setStatus(el.resiStatus, `Mencari ${code}...`, 'warn');
        try {
            await ensureAudio();
            const params = new URLSearchParams({ type, code });
            const json = await fetchJson(`${routes.qcResiLookup}?${params}`);
            state.resi = json.resi;
            state.checklist = (json.items || []).map(item => ({
                sku: item.sku,
                qty: Number(item.qty || 0),
                scanned_qty: Number(item.scanned_qty || 0),
            }));
            el.dispNoResi.textContent = state.resi.no_resi || '-';
            el.dispIdPesanan.textContent = `ID Pesanan: ${state.resi.id_pesanan || '-'}`;
            el.dispMeta.textContent = `${state.resi.tanggal_pesanan || '-'} • ${state.resi.kurir_name || '-'}`;

            if (json.already_scanned && json.is_complete) {
                await beep('err');
                setStatus(el.resiStatus, 'Resi sudah selesai di-QC. Scan resi lain.', 'warn');
                toast('warning', 'Resi Sudah Selesai', 'Semua SKU pada resi ini sudah lengkap.');
                state.resi = null;
                state.checklist = [];
                el.resiCode.value = '';
                focusActive();
                return;
            }

            const form = new FormData();
            form.append('resi_id', String(state.resi.id));
            await fetchJson(routes.qcResiRecord, { method: 'POST', body: form });
            await refreshSession();
            renderProgress();
            goPhase('sku');
            setStatus(el.skuStatus, json.already_scanned ? 'Melanjutkan resi pending.' : 'Resi dimuat. Scan SKU sesuai daftar.', 'ok');
            await beep('ok');
        } catch (error) {
            setStatus(el.resiStatus, error.message || 'Resi tidak ditemukan.', 'err');
            toast('error', 'Resi Gagal Dimuat', error.message || 'Resi tidak ditemukan.', 4200);
            await beep('err');
            focusActive();
        } finally {
            setBusy(false);
        }
    }

    async function scanSku() {
        if (state.busy) return;
        const code = (el.skuCode.value || '').trim();
        let qty = Math.max(1, parseInt(el.skuQty.value || '1', 10) || 1);
        if (!code) {
            setStatus(el.skuStatus, 'Kode SKU kosong. Scan ulang.', 'warn');
            toast('warning', 'SKU Kosong', 'Scanner belum mengirim kode SKU.');
            focusActive();
            return;
        }
        const item = state.checklist.find(row => String(row.sku).toLowerCase() === code.toLowerCase());
        if (!item) {
            setStatus(el.skuStatus, `SKU ${code} tidak ada dalam resi ini.`, 'err');
            toast('error', 'SKU Tidak Sesuai Resi', `SKU ${code} tidak ada dalam resi ini.`, 4800);
            await beep('err');
            el.skuCode.value = '';
            focusActive();
            return;
        }
        if (Number(item.scanned_qty || 0) >= Number(item.qty || 0)) {
            setStatus(el.skuStatus, `SKU ${code} sudah lengkap.`, 'warn');
            toast('warning', 'SKU Sudah Lengkap', 'Jangan scan ulang. Lanjut ke SKU lain.');
            await beep('err');
            el.skuCode.value = '';
            focusActive();
            return;
        }
        setBusy(true);
        setStatus(el.skuStatus, `Memproses ${code} qty ${qty}...`, 'warn');
        try {
            const form = new FormData();
            form.append('code', code);
            form.append('qty', String(qty));
            form.append('request_id', makeRequestId());
            form.append('resi_id', String(state.resi.id));
            const json = await fetchJson(routes.qcScanItem, { method: 'POST', body: form });
            state.session = json.session || null;
            item.scanned_qty = Math.min(Number(item.qty || 0), Number(item.scanned_qty || 0) + qty);
            const sessionItem = state.session?.items?.find(row => String(row.sku || '').toLowerCase() === code.toLowerCase());
            if (sessionItem?.name) state.itemNames[code.toLowerCase()] = sessionItem.name;
            renderSession();
            renderProgress();
            el.skuCode.value = '';
            el.skuQty.value = '1';
            const { total, done } = progress();
            if (total > 0 && done === total) {
                setStatus(el.skuStatus, `Resi ${state.resi?.no_resi || '-'} selesai. Siapkan resi berikutnya.`, 'ok');
                toast('success', 'Resi Selesai', 'Halaman kembali ke scan resi.', 1800);
                await beep('ok');
                state.completionTimer = setTimeout(() => goPhase('resi'), 900);
                return;
            }
            setStatus(el.skuStatus, `OK: ${code} +${qty}.`, 'ok');
            await beep('ok');
        } catch (error) {
            setStatus(el.skuStatus, error.message || 'Gagal memproses scan.', 'err');
            toast('error', 'Scan Gagal', error.message || 'Gagal memproses scan.', 4800);
            await beep('err');
            el.skuCode.value = '';
        } finally {
            setBusy(false);
            focusActive();
        }
    }

    let scannerStream = null;
    let scannerActive = false;
    let barcodeDetector = null;
    let scanLoopId = null;
    let html5Qr = null;
    let scanMode = 'native';
    let html5LoadPromise = null;
    const isIOS = (() => {
        const ua = navigator.userAgent || '';
        const platform = navigator.platform || '';
        return /iPad|iPhone|iPod/.test(ua) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    })();

    function loadHtml5Qr() {
        if (typeof Html5Qrcode !== 'undefined') return Promise.resolve(true);
        if (html5LoadPromise) return html5LoadPromise;
        const sources = ["{{ asset('vendor/html5-qrcode.min.js') }}", 'https://unpkg.com/html5-qrcode@2.3.10/minified/html5-qrcode.min.js'];
        html5LoadPromise = new Promise(resolve => {
            const tryLoad = index => {
                if (index >= sources.length) return resolve(false);
                const script = document.createElement('script');
                script.src = sources[index];
                script.async = true;
                script.onload = () => resolve(true);
                script.onerror = () => tryLoad(index + 1);
                document.head.appendChild(script);
            };
            tryLoad(0);
        });
        return html5LoadPromise;
    }

    function acceptScannedCode(rawCode) {
        const code = (rawCode || '').trim();
        if (!code) return;
        if (state.scanTarget === 'sku') {
            el.skuCode.value = code;
            scanSku();
        } else {
            el.resiCode.value = code;
            lookupResi();
        }
    }

    function stopScanner() {
        scannerActive = false;
        if (scanLoopId) cancelAnimationFrame(scanLoopId);
        scanLoopId = null;
        if (scannerStream) {
            scannerStream.getTracks().forEach(track => track.stop());
            scannerStream = null;
        }
        if (html5Qr) {
            html5Qr.stop().then(() => html5Qr.clear()).catch(() => {}).finally(() => { html5Qr = null; });
        }
        el.scannerVideo.srcObject = null;
    }

    function closeScanner() {
        stopScanner();
        el.scannerModal.style.display = 'none';
        el.btnStartScan.disabled = false;
        setTimeout(focusActive, 20);
    }

    async function openScanner(target) {
        state.scanTarget = target;
        await ensureAudio();
        if (!window.isSecureContext) {
            toast('error', 'Kamera Tidak Tersedia', 'Akses kamera membutuhkan HTTPS atau localhost.');
            return;
        }
        const hasNative = 'BarcodeDetector' in window && !isIOS;
        const html5Ready = await loadHtml5Qr();
        const hasHtml5 = html5Ready && typeof Html5Qrcode !== 'undefined';
        if (!hasNative && !hasHtml5) {
            toast('error', 'Scanner Tidak Didukung', 'Gunakan input manual atau scan via foto.');
            return;
        }
        scanMode = hasNative ? 'native' : 'html5';
        el.scannerVideo.style.display = scanMode === 'native' ? 'block' : 'none';
        el.scannerQr.style.display = scanMode === 'html5' ? 'block' : 'none';
        if (scanMode === 'native') {
            try {
                barcodeDetector = new BarcodeDetector({ formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'upc_a', 'upc_e'] });
            } catch {
                if (!hasHtml5) return;
                scanMode = 'html5';
                el.scannerVideo.style.display = 'none';
                el.scannerQr.style.display = 'block';
            }
        }
        el.scannerHint.textContent = target === 'sku' ? 'Arahkan kamera ke barcode SKU.' : 'Arahkan kamera ke barcode resi.';
        el.scannerModal.style.display = 'flex';
    }

    async function startScanner() {
        if (scanMode === 'html5') {
            try {
                el.btnStartScan.disabled = true;
                html5Qr = new Html5Qrcode('scanner_qr');
                await html5Qr.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: { width: 250, height: 250 } },
                    decodedText => {
                        if (!decodedText) return;
                        closeScanner();
                        acceptScannedCode(decodedText);
                    },
                    () => {}
                );
                scannerActive = true;
                return;
            } catch {
                closeScanner();
                toast('error', 'Kamera Gagal', 'Tidak bisa membuka kamera. Cek izin browser.');
                return;
            }
        }
        try {
            el.btnStartScan.disabled = true;
            scannerStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            el.scannerVideo.srcObject = scannerStream;
            await el.scannerVideo.play();
            scannerActive = true;
            scanLoop();
        } catch {
            closeScanner();
            toast('error', 'Kamera Gagal', 'Tidak bisa membuka kamera. Cek izin browser.');
        }
    }

    async function scanLoop() {
        if (!scannerActive || !barcodeDetector) return;
        try {
            const barcodes = await barcodeDetector.detect(el.scannerVideo);
            if (Array.isArray(barcodes) && barcodes.length && barcodes[0].rawValue) {
                closeScanner();
                acceptScannedCode(barcodes[0].rawValue);
                return;
            }
        } catch {}
        scanLoopId = requestAnimationFrame(scanLoop);
    }

    async function scanPhoto(target) {
        state.scanTarget = target;
        el.photoInput.click();
    }

    el.btnLoadResi.addEventListener('click', lookupResi);
    el.resiCode.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            lookupResi();
        }
    });
    el.btnScanSku.addEventListener('click', scanSku);
    el.skuCode.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            scanSku();
        }
    });
    el.btnChangeResi.addEventListener('click', () => goPhase('resi'));
    el.btnRefreshHistory.addEventListener('click', async () => {
        await refreshSession();
        focusActive();
    });
    el.btnOpenScannerResi.addEventListener('click', () => openScanner('resi'));
    el.btnOpenScannerSku.addEventListener('click', () => openScanner('sku'));
    el.btnScanPhotoResi.addEventListener('click', () => scanPhoto('resi'));
    el.btnScanPhotoSku.addEventListener('click', () => scanPhoto('sku'));
    el.btnCloseScanner.addEventListener('click', closeScanner);
    el.btnStartScan.addEventListener('click', startScanner);
    el.scannerModal.addEventListener('click', event => {
        if (event.target === el.scannerModal) closeScanner();
    });
    el.photoInput.addEventListener('change', async event => {
        const file = event.target.files && event.target.files[0];
        if (!file) return;
        const ready = await loadHtml5Qr();
        if (!ready || typeof Html5Qrcode === 'undefined') {
            toast('error', 'Foto Gagal', 'Library scan belum siap.');
            return;
        }
        try {
            const scanner = new Html5Qrcode('scanner_qr');
            const decodedText = await scanner.scanFile(file, true);
            await scanner.clear();
            acceptScannedCode(decodedText || '');
        } catch {
            toast('error', 'Foto Gagal', 'Barcode tidak terbaca dari foto.');
        } finally {
            el.photoInput.value = '';
        }
    });

    if (el.photoWrapResi) el.photoWrapResi.style.display = isIOS ? 'flex' : 'none';
    if (el.photoWrapSku) el.photoWrapSku.style.display = isIOS ? 'flex' : 'none';

    document.addEventListener('click', event => {
        if (event.target?.closest?.('input, textarea, select, button, a, .scanner-modal')) return;
        setTimeout(focusActive, 20);
    });

    refreshSession().catch(() => {});
    goPhase('resi');
</script>
