@extends('layouts.mobile')

@section('title', 'QC Scan')

@section('content')
    @include('picker.qc-scan-resi-mobile')
@endsection

{{-- Legacy draft-session QC mobile view disabled; active flow is included above.

@section('title', 'QC Scan')

@section('content')
<style>
    .address-line {
        display: flex;
        align-items: flex-start;
        gap: 6px;
        margin-top: 4px;
        font-size: 11px;
        color: var(--muted);
        line-height: 1.4;
    }
    .address-tag {
        display: inline-flex;
        align-items: center;
        padding: 2px 6px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 600;
        background: rgba(15, 118, 110, 0.12);
        color: var(--brand);
        white-space: nowrap;
    }
    .address-text {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .result-info .address-line {
        margin-top: 6px;
    }
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar-actions form {
        margin: 0;
    }
    .section-title {
        font-weight: 700;
        margin-bottom: 8px;
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
        font-weight: 700;
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
        font-size: 12px;
        color: var(--muted);
        margin-top: 6px;
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
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="card" id="session_card">
        <div class="session-meta">
            <div>
                <span class="badge" id="session_status">Belum Mulai</span>
            </div>
            <div class="code" id="session_code">-</div>
        </div>
        <div class="muted" id="session_started">Mulai QC scan untuk membuat sesi baru.</div>
        <div style="margin-top: 12px;">
            <button type="button" class="primary-btn" id="btn_start">Mulai Input</button>
        </div>
    </div>

    <div class="card" id="scan_card">
        <div class="section-title">Scan Barang</div>
        <div class="muted">Gunakan kamera untuk scan SKU (barang keluar rak) dan masuk ke QC Transit.</div>
        <div class="scan-actions">
            <div class="scan-row">
                <input type="text" class="input" id="scan_code" placeholder="Scan SKU" autocomplete="off" />
                <button type="button" class="scan-btn" id="btn_open_scanner">Scan</button>
            </div>
            <div class="photo-scan" id="photo_scan_wrap">
                <button type="button" class="photo-btn" id="btn_scan_photo">Scan via Foto</button>
                <span>Alternatif untuk iPhone.</span>
            </div>
            <input type="file" id="scan_photo" accept="image/*" capture="environment" style="display:none;" />
        </div>
        <div class="status-line" id="scan_status">Siap scan barcode SKU.</div>
    </div>

    <div class="card" id="search_card">
        <div style="font-weight: 700; margin-bottom: 8px;">Tambah Barang</div>
        <input type="text" class="input" id="item_search" placeholder="Cari SKU atau nama barang" autocomplete="off" />
        <div class="results" id="search_results"></div>
    </div>

    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <div style="font-weight:700;">Daftar QC Transit</div>
            <div class="muted" id="total_items">0 item</div>
        </div>
        <div class="muted" id="items_empty">Belum ada barang ditambahkan.</div>
        <div class="items-list" id="items_list"></div>
    </div>
</div>

<div class="scanner-modal" id="scanner_modal">
    <div class="scanner-card">
        <div style="font-weight:700;">Kamera Scanner</div>
        <video class="scanner-video" id="scanner_video" playsinline></video>
        <div class="scanner-qr" id="scanner_qr"></div>
        <div class="scanner-actions">
            <button type="button" class="ghost-btn" id="btn_close_scanner">Tutup</button>
            <button type="button" class="primary-btn" id="btn_start_scan">Mulai Scan</button>
        </div>
        <div class="muted" id="scanner_hint">Arahkan kamera ke barcode SKU.</div>
    </div>
</div>

<div class="bottom-bar">
    <div class="bottom-inner">
        <div>
            <div class="summary">
                <strong id="total_qty">0 qty</strong>
                Total akumulasi barang
            </div>
            <div class="save-status" id="save_status">Siap diinput</div>
        </div>
        <button class="primary-btn" id="btn_submit" style="width:auto; padding:12px 18px;">Selesaikan</button>
    </div>
</div>

<script>
    const routes = @json($routes);
    const initialSession = @json($session);
    const csrfToken = '{{ csrf_token() }}';

    const state = {
        session: initialSession,
        searching: false,
    };

    const el = {
        sessionStatus: document.getElementById('session_status'),
        sessionCode: document.getElementById('session_code'),
        sessionStarted: document.getElementById('session_started'),
        btnStart: document.getElementById('btn_start'),
        itemSearch: document.getElementById('item_search'),
        searchResults: document.getElementById('search_results'),
        itemsList: document.getElementById('items_list'),
        itemsEmpty: document.getElementById('items_empty'),
        totalItems: document.getElementById('total_items'),
        totalQty: document.getElementById('total_qty'),
        saveStatus: document.getElementById('save_status'),
        btnSubmit: document.getElementById('btn_submit'),
        searchCard: document.getElementById('search_card'),
        scanCard: document.getElementById('scan_card'),
        scanCode: document.getElementById('scan_code'),
        btnOpenScanner: document.getElementById('btn_open_scanner'),
        btnScanPhoto: document.getElementById('btn_scan_photo'),
        scanPhotoInput: document.getElementById('scan_photo'),
        photoScanWrap: document.getElementById('photo_scan_wrap'),
        scanStatus: document.getElementById('scan_status'),
        scannerModal: document.getElementById('scanner_modal'),
        scannerVideo: document.getElementById('scanner_video'),
        scannerQr: document.getElementById('scanner_qr'),
        btnCloseScanner: document.getElementById('btn_close_scanner'),
        btnStartScan: document.getElementById('btn_start_scan'),
        scannerHint: document.getElementById('scanner_hint'),
    };

    let updateScanStatusFn = null;
    let syncScanControlsFn = null;
    let lastScanSessionState = null;

    const canUseScan = () => state.session && state.session.status === 'draft';

    const makeRequestId = () => {
        if (window.crypto?.randomUUID) {
            return window.crypto.randomUUID();
        }
        return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    };

    const setSaveStatus = (text, pending = false) => {
        el.saveStatus.textContent = text;
        el.saveStatus.style.color = pending ? '#f97316' : '#6b7280';
    };

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
            credentials: 'same-origin',
            ...options,
        });
        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (err) { json = null; }
        if (!res.ok) {
            let message = json?.message || 'Terjadi kesalahan';
            if (json?.errors) {
                const first = Object.values(json.errors)[0];
                if (Array.isArray(first) && first.length) {
                    message = first[0];
                }
            }
            const error = new Error(message);
            if (json?.insufficient) {
                error.insufficient = json.insufficient;
            }
            throw error;
        }
        return json;
    };

    const showInsufficientStock = (details) => {
        if (!Array.isArray(details) || !details.length) return;
        if (typeof Swal === 'undefined') return;
        const list = details.map((row) => {
            const sku = row.sku || '-';
            const name = row.name ? ` • ${row.name}` : '';
            const available = typeof row.available !== 'undefined' ? row.available : '-';
            const required = typeof row.required !== 'undefined' ? row.required : '-';
            return `<li style="margin-bottom:6px;"><strong>${sku}</strong>${name}<br><span style="color:#64748b;">Tersedia ${available}, butuh ${required}</span></li>`;
        }).join('');
        Swal.fire({
            icon: 'error',
            title: 'Stok tidak mencukupi',
            html: `<div style="text-align:left; font-size:14px;">Item berikut stoknya kurang:</div><ul style="text-align:left; padding-left:18px; margin-top:8px;">${list}</ul>`,
        });
    };

    const renderSession = () => {
        const session = state.session;
        if (!session) {
            el.sessionStatus.textContent = 'Belum Mulai';
            el.sessionStatus.style.background = 'rgba(148,163,184,0.15)';
            el.sessionStatus.style.color = '#64748b';
            el.sessionCode.textContent = '-';
            el.sessionStarted.textContent = 'Mulai input untuk membuat sesi baru.';
            el.btnStart.textContent = 'Mulai Input';
            el.btnSubmit.classList.add('disabled');
            el.searchCard.classList.add('disabled');
            el.scanCard?.classList.add('disabled');
            syncScanControlsFn?.(false);
            if (lastScanSessionState !== false) {
                updateScanStatusFn?.('Mulai sesi terlebih dahulu sebelum scan.', 'error');
                lastScanSessionState = false;
            }
            renderItems([]);
            return;
        }

        const isDraft = session.status === 'draft';
        el.sessionStatus.textContent = isDraft ? 'Draft Aktif' : 'Sesi Selesai';
        el.sessionStatus.style.background = isDraft ? 'rgba(15,118,110,0.12)' : 'rgba(249,115,22,0.15)';
        el.sessionStatus.style.color = isDraft ? '#0f766e' : '#c2410c';
        el.sessionCode.textContent = session.code || '-';
        el.sessionStarted.textContent = `Mulai: ${session.started_at || '-'}`;
        el.btnStart.textContent = isDraft ? 'Lanjutkan Input' : 'Mulai Sesi Baru';
        el.btnSubmit.classList.toggle('disabled', !isDraft);
        el.searchCard.classList.toggle('disabled', !isDraft);
        el.scanCard?.classList.toggle('disabled', !isDraft);
        syncScanControlsFn?.(isDraft);
        if (lastScanSessionState !== isDraft) {
            lastScanSessionState = isDraft;
            const message = isDraft ? 'Siap scan barcode SKU.' : 'Mulai sesi terlebih dahulu sebelum scan.';
            const type = isDraft ? 'muted' : 'error';
            updateScanStatusFn?.(message, type);
        }
        renderItems(session.items || []);
    };

    const renderItems = (items) => {
        const totalQty = items.reduce((sum, row) => sum + (row.qty || 0), 0);
        el.totalQty.textContent = `${totalQty} qty`;
        el.totalItems.textContent = `${items.length} item`;

        if (!items.length) {
            el.itemsEmpty.style.display = 'block';
            el.itemsList.innerHTML = '';
            return;
        }

        el.itemsEmpty.style.display = 'none';
        el.itemsList.innerHTML = items.map((row) => {
            const address = row.address && row.address.trim() ? row.address : 'Belum diisi';
            return `
                <div class="item-row" data-id="${row.id}" data-qty="${row.qty}">
                    <div class="item-meta">
                        <strong>${row.sku || '-'} • ${row.name || '-'}</strong>
                        <div class="address-line">
                            <span class="address-tag">Lokasi</span>
                            <span class="address-text">${address}</span>
                        </div>
                        <span>Qty tercatat: ${row.qty}</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:8px;">
                        <div class="qty-controls">
                            <button class="qty-btn" data-action="dec">-</button>
                            <input type="number" min="1" class="qty-input" value="${row.qty}" />
                            <button class="qty-btn" data-action="inc">+</button>
                        </div>
                        <button class="remove-btn" data-action="remove">×</button>
                    </div>
                </div>
            `;
        }).join('');
    };

    const renderSearchResults = (items) => {
        if (!items.length) {
            el.searchResults.innerHTML = '';
            return;
        }
        el.searchResults.innerHTML = items.map(item => {
            const address = item.address && item.address.trim() ? item.address : 'Belum diisi';
            return `
                <div class="result-item">
                    <div class="result-info">
                        <strong>${item.sku}</strong>
                        <span>${item.name}</span>
                        <div class="address-line">
                            <span class="address-tag">Lokasi</span>
                            <span class="address-text">${address}</span>
                        </div>
                    </div>
                    <button class="add-btn" data-id="${item.id}">Tambah</button>
                </div>
            `;
        }).join('');
    };

    const refreshSession = async () => {
        const json = await fetchJson(routes.current);
        state.session = json.session;
        renderSession();
    };

    const startSession = async () => {
        if (state.session && state.session.status === 'draft') {
            renderSession();
            setSaveStatus('Batch aktif digunakan');
            return;
        }
        try {
            setSaveStatus('Membuat sesi...', true);
            const json = await fetchJson(routes.start, { method: 'POST' });
            state.session = json.session;
            renderSession();
            setSaveStatus('Sesi siap digunakan');
        } catch (err) {
            setSaveStatus(err.message || 'Gagal membuat sesi');
        }
    };

    const addItem = async (itemId) => {
        try {
            setSaveStatus('Menyimpan item...', true);
            const payload = new FormData();
            payload.append('item_id', itemId);
            payload.append('qty', 1);
            payload.append('request_id', makeRequestId());
            const json = await fetchJson(routes.itemsStore, {
                method: 'POST',
                body: payload,
            });
            state.session = json.session;
            renderSession();
            setSaveStatus('Item tersimpan');
        } catch (err) {
            setSaveStatus(err.message || 'Gagal menyimpan item');
        }
    };

    const updateItem = async (rowId, qty) => {
        if (!rowId) return;
        if (qty < 1) {
            return removeItem(rowId);
        }
        try {
            setSaveStatus('Memperbarui qty...', true);
            const payload = new FormData();
            payload.append('_method', 'PUT');
            payload.append('qty', qty);
            const json = await fetchJson(routes.itemsUpdate.replace(':id', rowId), {
                method: 'POST',
                body: payload,
            });
            state.session = json.session;
            renderSession();
            setSaveStatus('Perubahan tersimpan');
        } catch (err) {
            setSaveStatus(err.message || 'Gagal memperbarui qty');
        }
    };

    const removeItem = async (rowId) => {
        try {
            setSaveStatus('Menghapus item...', true);
            const payload = new FormData();
            payload.append('_method', 'DELETE');
            const json = await fetchJson(routes.itemsDestroy.replace(':id', rowId), {
                method: 'POST',
                body: payload,
            });
            state.session = json.session;
            renderSession();
            setSaveStatus('Item dihapus');
        } catch (err) {
            setSaveStatus(err.message || 'Gagal menghapus item');
        }
    };

    const submitSession = async () => {
        try {
            setSaveStatus('Mengunci sesi...', true);
            const json = await fetchJson(routes.submit, { method: 'POST' });
            state.session = json.session;
            renderSession();
            setSaveStatus('Sesi selesai dikirim');
            if (typeof Swal !== 'undefined') {
                const items = state.session?.items || [];
                const totalQty = items.reduce((sum, row) => sum + (row.qty || 0), 0);
                Swal.fire({
                    icon: 'success',
                    title: 'Sesi selesai',
                    html: `
                        <div style="text-align:left; font-size:14px;">
                            Kode sesi: <strong>${state.session?.code || '-'}</strong><br>
                            Total item: <strong>${items.length}</strong><br>
                            Total qty: <strong>${totalQty}</strong>
                        </div>
                    `,
                    confirmButtonText: 'OK',
                    allowOutsideClick: false,
                });
            }
        } catch (err) {
            if (err?.insufficient) {
                showInsufficientStock(err.insufficient);
                setSaveStatus('Stok tidak mencukupi');
                return;
            }
            setSaveStatus(err.message || 'Gagal mengunci sesi');
        }
    };

    const debounce = (fn, delay = 300) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    };

    el.btnStart.addEventListener('click', async () => {
        await startSession();
    });

    el.searchResults.addEventListener('click', async (e) => {
        const btn = e.target.closest('.add-btn');
        if (!btn) return;
        const itemId = btn.getAttribute('data-id');
        if (!itemId) return;
        await addItem(itemId);
        el.itemSearch.value = '';
        renderSearchResults([]);
    });

    el.itemsList.addEventListener('click', async (e) => {
        const row = e.target.closest('.item-row');
        if (!row) return;
        const rowId = row.getAttribute('data-id');
        const currentQty = parseInt(row.getAttribute('data-qty') || '1', 10);

        const action = e.target.getAttribute('data-action');
        if (action === 'inc') {
            await updateItem(rowId, currentQty + 1);
            return;
        }
        if (action === 'dec') {
            await updateItem(rowId, currentQty - 1);
            return;
        }
        if (action === 'remove') {
            await removeItem(rowId);
        }
    });

    el.itemsList.addEventListener('change', async (e) => {
        const input = e.target.closest('.qty-input');
        if (!input) return;
        const row = e.target.closest('.item-row');
        if (!row) return;
        const rowId = row.getAttribute('data-id');
        const qty = parseInt(input.value || '1', 10);
        await updateItem(rowId, qty);
    });

    el.btnSubmit.addEventListener('click', async () => {
        if (!state.session || state.session.status !== 'draft') return;
        await submitSession();
    });

    const runSearch = debounce(async () => {
        const q = el.itemSearch.value.trim();
        if (q.length < 2) {
            renderSearchResults([]);
            return;
        }
        try {
            setSaveStatus('Mencari item...', true);
            const url = `${routes.searchItems}?q=${encodeURIComponent(q)}`;
            const json = await fetchJson(url);
            renderSearchResults(json.items || []);
            setSaveStatus('Siap diinput');
        } catch (err) {
            renderSearchResults([]);
            setSaveStatus('Gagal mencari item');
        }
    }, 300);

    el.itemSearch.addEventListener('input', runSearch);

    const initScanFeature = () => {
        if (!routes?.scanItem || !el.scanCard) return;

        let audioCtx = null;
        const getAudioCtx = () => {
            if (!audioCtx) {
                const Ctx = window.AudioContext || window.webkitAudioContext;
                if (!Ctx) return null;
                audioCtx = new Ctx();
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume().catch(() => {});
            }
            return audioCtx;
        };
        const playBeep = (frequency = 880, duration = 120, volume = 1) => {
            const ctx = getAudioCtx();
            if (!ctx) return;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            const now = ctx.currentTime;
            const seconds = duration / 1000;
            const peak = Math.max(0.0001, volume);
            osc.type = 'sine';
            osc.frequency.value = frequency;
            // attack/release singkat supaya volume penuh tidak berbunyi "klik"
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(peak, now + 0.005);
            gain.gain.setValueAtTime(peak, now + Math.max(0.006, seconds - 0.005));
            gain.gain.exponentialRampToValueAtTime(0.0001, now + seconds);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            setTimeout(() => {
                try { osc.stop(); } catch (e) {}
                osc.disconnect();
                gain.disconnect();
            }, duration);
        };
        const playScanSound = () => playBeep(760, 120, 1);
        const playSuccessSound = () => playBeep(1200, 140, 1);

        let scannerStream = null;
        let scannerActive = false;
        let barcodeDetector = null;
        let scanLoopId = null;
        let html5Qr = null;
        let scanMode = 'native';
        let html5LoadPromise = null;
        let scanInputTimer = null;
        const isIOS = (() => {
            const ua = navigator.userAgent || '';
            const platform = navigator.platform || '';
            const isAppleMobile = /iPad|iPhone|iPod/.test(ua);
            const isIpadOs = platform === 'MacIntel' && navigator.maxTouchPoints > 1;
            return isAppleMobile || isIpadOs;
        })();

        const loadHtml5Qr = () => {
            if (typeof Html5Qrcode !== 'undefined') {
                return Promise.resolve(true);
            }
            if (html5LoadPromise) {
                return html5LoadPromise;
            }

            const sources = [
                "{{ asset('vendor/html5-qrcode.min.js') }}",
                'https://unpkg.com/html5-qrcode@2.3.10/minified/html5-qrcode.min.js',
            ];

            html5LoadPromise = new Promise((resolve) => {
                const tryLoad = (index) => {
                    if (index >= sources.length) {
                        resolve(false);
                        return;
                    }
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
        };

        const setScanStatus = (text, type = 'muted') => {
            if (!el.scanStatus) return;
            el.scanStatus.textContent = text;
            const colors = {
                error: '#b91c1c',
                success: '#047857',
                pending: '#f97316',
                muted: '#6b7280',
            };
            el.scanStatus.style.color = colors[type] || colors.muted;
        };
        updateScanStatusFn = setScanStatus;

        const syncScanControls = (enabled) => {
            const controls = [el.scanCode, el.btnOpenScanner, el.btnScanPhoto];
            controls.forEach((control) => {
                if (!control) return;
                control.disabled = !enabled;
            });
            if (el.scanPhotoInput) el.scanPhotoInput.disabled = !enabled;
        };
        syncScanControlsFn = syncScanControls;
        syncScanControls(canUseScan());

        const stopScanner = () => {
            scannerActive = false;
            if (scanLoopId) {
                cancelAnimationFrame(scanLoopId);
                scanLoopId = null;
            }
            if (scannerStream) {
                scannerStream.getTracks().forEach((track) => track.stop());
                scannerStream = null;
            }
            if (html5Qr) {
                html5Qr.stop()
                    .then(() => html5Qr.clear())
                    .catch(() => {})
                    .finally(() => {
                        html5Qr = null;
                    });
            }
            if (el.scannerVideo) {
                el.scannerVideo.srcObject = null;
            }
        };

        const closeScanner = () => {
            stopScanner();
            if (el.scannerModal) {
                el.scannerModal.style.display = 'none';
            }
            if (el.btnStartScan) el.btnStartScan.disabled = false;
            if (el.scannerHint) el.scannerHint.textContent = 'Arahkan kamera ke barcode SKU.';
        };

        const requireActiveSession = () => {
            if (!canUseScan()) {
                setScanStatus('Mulai sesi terlebih dahulu sebelum scan.', 'error');
                return false;
            }
            return true;
        };

        const handleScannedCode = (rawCode) => {
            const clean = (rawCode || '').trim();
            if (!clean || !el.scanCode) return;
            if (scanInputTimer) {
                clearTimeout(scanInputTimer);
                scanInputTimer = null;
            }
            el.scanCode.value = clean;
            el.scanCode.focus();
            if (requireActiveSession()) {
                submitScan();
            }
        };

        let scanSubmitting = false;
        const submitScan = async () => {
            if (!el.scanCode || !routes.scanItem) return;
            if (!requireActiveSession()) return;
            if (scanSubmitting) return;
            const code = el.scanCode.value.trim();
            if (!code) {
                setScanStatus('Masukkan atau scan SKU terlebih dahulu.', 'error');
                el.scanCode.focus();
                return;
            }
            scanSubmitting = true;
            setScanStatus('Menambahkan barang hasil scan...', 'pending');
            try {
                const payload = new FormData();
                payload.append('code', code);
                payload.append('request_id', makeRequestId());
                const json = await fetchJson(routes.scanItem, {
                    method: 'POST',
                    body: payload,
                });
                state.session = json.session;
                renderSession();
                setSaveStatus('Barang ditambahkan melalui scan');
                setScanStatus(json?.message || 'Item berhasil ditambahkan.', 'success');
                playSuccessSound();
                el.scanCode.value = '';
                el.scanCode.focus();
            } catch (err) {
                setScanStatus(err.message || 'Gagal menambahkan hasil scan.', 'error');
            } finally {
                scanSubmitting = false;
            }
        };

        const openScanner = async () => {
            getAudioCtx();
            if (!requireActiveSession()) return;
            if (!window.isSecureContext) {
                setScanStatus('Akses kamera perlu HTTPS atau localhost.', 'error');
                return;
            }
            const hasNative = 'BarcodeDetector' in window && !isIOS;
            const html5Ready = await loadHtml5Qr();
            const hasHtml5 = html5Ready && typeof Html5Qrcode !== 'undefined';

            if (!hasNative && !hasHtml5) {
                setScanStatus('Browser belum mendukung scan kamera.', 'error');
                return;
            }
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                setScanStatus('Akses kamera tidak tersedia di browser ini.', 'error');
                return;
            }

            scanMode = hasNative ? 'native' : 'html5';
            if (el.scannerVideo) el.scannerVideo.style.display = scanMode === 'native' ? 'block' : 'none';
            if (el.scannerQr) el.scannerQr.style.display = scanMode === 'html5' ? 'block' : 'none';

            if (scanMode === 'native') {
                try {
                    barcodeDetector = new BarcodeDetector({
                        formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'upc_a', 'upc_e'],
                    });
                } catch (error) {
                    if (hasHtml5) {
                        scanMode = 'html5';
                        if (el.scannerVideo) el.scannerVideo.style.display = 'none';
                        if (el.scannerQr) el.scannerQr.style.display = 'block';
                    } else {
                        setScanStatus('Fitur scan tidak tersedia. Gunakan input manual.', 'error');
                        return;
                    }
                }
            }

            if (el.scannerModal) {
                el.scannerModal.style.display = 'flex';
            }
        };

        const startScanner = async () => {
            if (!el.btnStartScan) return;
            if (scanMode === 'html5') {
                try {
                    el.btnStartScan.disabled = true;
                    if (el.scannerHint) el.scannerHint.textContent = 'Mengaktifkan kamera...';
                    const config = {
                        fps: 10,
                        qrbox: { width: 250, height: 250 },
                    };
                    if (typeof Html5QrcodeSupportedFormats !== 'undefined') {
                        config.formatsToSupport = [
                            Html5QrcodeSupportedFormats.CODE_128,
                            Html5QrcodeSupportedFormats.CODE_39,
                            Html5QrcodeSupportedFormats.EAN_13,
                            Html5QrcodeSupportedFormats.EAN_8,
                            Html5QrcodeSupportedFormats.QR_CODE,
                            Html5QrcodeSupportedFormats.UPC_A,
                            Html5QrcodeSupportedFormats.UPC_E,
                        ];
                    }

                    html5Qr = new Html5Qrcode('scanner_qr');
                    await html5Qr.start(
                        { facingMode: 'environment' },
                        config,
                        (decodedText) => {
                            if (decodedText) {
                                playScanSound();
                                closeScanner();
                                handleScannedCode(decodedText);
                            }
                        },
                        () => {}
                    );
                    scannerActive = true;
                    if (el.scannerHint) el.scannerHint.textContent = 'Scan berjalan. Arahkan ke barcode.';
                    return;
                } catch (error) {
                    el.btnStartScan.disabled = false;
                    setScanStatus('Tidak bisa membuka kamera. Cek perizinan.', 'error');
                    closeScanner();
                    return;
                }
            }

            try {
                el.btnStartScan.disabled = true;
                if (el.scannerHint) el.scannerHint.textContent = 'Mengaktifkan kamera...';
                scannerStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: { ideal: 'environment' },
                    },
                    audio: false,
                });
                if (el.scannerVideo) {
                    el.scannerVideo.srcObject = scannerStream;
                    await el.scannerVideo.play();
                }
                scannerActive = true;
                if (el.scannerHint) el.scannerHint.textContent = 'Scan berjalan. Arahkan ke barcode.';
                scanLoop();
            } catch (error) {
                el.btnStartScan.disabled = false;
                setScanStatus('Tidak bisa membuka kamera. Cek perizinan.', 'error');
                closeScanner();
            }
        };

        const scanLoop = async () => {
            if (!scannerActive || !barcodeDetector || !el.scannerVideo) return;
            try {
                const barcodes = await barcodeDetector.detect(el.scannerVideo);
                if (Array.isArray(barcodes) && barcodes.length) {
                    const code = barcodes[0].rawValue || '';
                    if (code) {
                        playScanSound();
                        closeScanner();
                        handleScannedCode(code);
                        return;
                    }
                }
            } catch (error) {
                // ignore frame errors
            }
            scanLoopId = requestAnimationFrame(scanLoop);
        };

        const scanFromPhoto = async (file) => {
            if (!file) return;

            setScanStatus('Memproses foto barcode...', 'pending');
            const ready = await loadHtml5Qr();
            if (!ready || typeof Html5Qrcode === 'undefined') {
                setScanStatus('Library scan belum siap. Gunakan input manual.', 'error');
                return;
            }

            try {
                closeScanner();
                const photoScanner = new Html5Qrcode('scanner_qr');
                const decodedText = await photoScanner.scanFile(file, true);
                await photoScanner.clear();
                playScanSound();
                handleScannedCode(decodedText || '');
            } catch (error) {
                setScanStatus('Gagal membaca barcode dari foto.', 'error');
            } finally {
                if (el.scanPhotoInput) el.scanPhotoInput.value = '';
            }
        };

        const updateScanAvailability = async () => {
            const hasNative = 'BarcodeDetector' in window && !isIOS;
            const html5Ready = await loadHtml5Qr();
            const hasHtml5 = html5Ready && typeof Html5Qrcode !== 'undefined';
            const canUseCamera = window.isSecureContext && navigator.mediaDevices && navigator.mediaDevices.getUserMedia;
            const supported = canUseCamera && (hasNative || hasHtml5);

            if (!supported) {
                if (el.btnOpenScanner) el.btnOpenScanner.style.display = 'none';
                if (el.photoScanWrap) el.photoScanWrap.style.display = 'none';
                setScanStatus('Scan kamera tidak tersedia. Gunakan input manual.', 'error');
            }
        };

        if (el.photoScanWrap) {
            el.photoScanWrap.style.display = isIOS ? 'flex' : 'none';
        }

        el.scanCode?.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitScan();
            }
        });
        el.scanCode?.addEventListener('input', () => {
            if (!canUseScan()) return;
            if (scanInputTimer) clearTimeout(scanInputTimer);
            const value = el.scanCode.value.trim();
            if (!value) return;
            scanInputTimer = setTimeout(() => {
                if (value === el.scanCode.value.trim()) {
                    submitScan();
                }
            }, 600);
        });
        el.btnOpenScanner?.addEventListener('click', openScanner);
        el.btnStartScan?.addEventListener('click', startScanner);
        el.btnCloseScanner?.addEventListener('click', closeScanner);
        el.scannerModal?.addEventListener('click', (event) => {
            if (event.target === el.scannerModal) {
                closeScanner();
            }
        });
        el.btnScanPhoto?.addEventListener('click', () => {
            el.scanPhotoInput?.click();
        });
        el.scanPhotoInput?.addEventListener('change', (event) => {
            const file = event.target.files && event.target.files[0];
            scanFromPhoto(file);
        });

        setScanStatus('Siap scan barcode SKU.');
        updateScanAvailability();
    };

    initScanFeature();

    renderSession();
</script>
@endsection
--}}
