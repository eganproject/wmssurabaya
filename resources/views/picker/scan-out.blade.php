@extends('layouts.mobile')

@section('title', 'Scan Out')

@section('content')
<style>
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
    .scan-btn {
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
    }
    .photo-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
        border-radius: 12px;
        font-weight: 700;
        border: 1px dashed var(--border);
        background: #fff;
    }
    .status-line {
        font-size: 12px;
        color: var(--muted);
        margin-top: 6px;
    }
    .result-card {
        display: none;
    }
    .result-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }
    .result-badge {
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(16, 185, 129, 0.15);
        color: #047857;
        font-weight: 700;
        font-size: 11px;
    }
    .result-items {
        display: grid;
        gap: 10px;
        margin-top: 10px;
    }
    .result-item {
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 10px 12px;
        background: #fff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        font-size: 13px;
    }
    .result-meta {
        font-size: 12px;
        color: var(--muted);
    }
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar-actions form {
        margin: 0;
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
    .scanner-actions .primary-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">{{ config('app.name') }}</div>
            <div class="subtitle">Scan Out Gudang</div>
        </div>
        <div class="topbar-actions">
            <a href="{{ $routes['history'] }}" class="logout">History</a>
            <a href="{{ $routes['dashboard'] }}" class="logout">Dashboard</a>
            <form method="POST" action="{{ $routes['logout'] }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Scan Out</div>
        <div class="muted">Scan resi atau ID pesanan untuk mengeluarkan barang dari gudang.</div>
        <div class="scan-actions">
            <select class="input" id="scan_type">
                <option value="no_resi">No Resi</option>
                <option value="id_pesanan">ID Pesanan</option>
            </select>
            <div class="scan-row">
                <input type="text" class="input" id="scan_code" placeholder="Scan No. Resi" autocomplete="off" />
                <button type="button" class="scan-btn" id="btn_open_scanner">Scan</button>
            </div>
            <div class="photo-scan" id="photo_scan_wrap">
                <button type="button" class="photo-btn" id="btn_scan_photo">Scan via Foto</button>
                <span class="muted">Alternatif untuk iPhone.</span>
            </div>
            <input type="file" id="scan_photo" accept="image/*" capture="environment" style="display:none;" />
            <button type="button" class="primary-btn" id="btn_scan">Proses Resi</button>
        </div>
        <div class="status-line" id="scan_status">Siap memproses scan out.</div>
    </div>

    <div class="card result-card" id="result_card">
        <div class="result-header">
            <div>
                <div style="font-weight:700;" id="result_title">Scan Out Berhasil</div>
                <div class="result-meta" id="result_meta">-</div>
            </div>
            <div class="result-badge">Sukses</div>
        </div>
        <div class="result-items" id="result_items"></div>
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
        <div class="muted" id="scanner_hint">Arahkan kamera ke barcode resi.</div>
    </div>
</div>

<script>
    const routes = @json($routes);
    const csrfToken = '{{ csrf_token() }}';

    let audioCtx = null;
    const getAudioCtx = () => {
        if (!audioCtx) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return null;
            audioCtx = new Ctx();
        }
        if (audioCtx.state === 'suspended') {
            audioCtx.resume().catch(() => {});
        }
        return audioCtx;
    };
    const playBeep = (frequency = 880, duration = 120, volume = 0.35) => {
        const ctx = getAudioCtx();
        if (!ctx) return;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = frequency;
        gain.gain.value = volume;
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        setTimeout(() => {
            try { osc.stop(); } catch (e) {}
            osc.disconnect();
            gain.disconnect();
        }, duration);
    };
    const playScanSound = () => playBeep(760, 120, 0.35);
    const playSuccessSound = () => playBeep(1200, 140, 0.45);

    const el = {
        scanType: document.getElementById('scan_type'),
        scanCode: document.getElementById('scan_code'),
        btnScan: document.getElementById('btn_scan'),
        btnOpenScanner: document.getElementById('btn_open_scanner'),
        btnScanPhoto: document.getElementById('btn_scan_photo'),
        scanPhotoInput: document.getElementById('scan_photo'),
        photoScanWrap: document.getElementById('photo_scan_wrap'),
        scanStatus: document.getElementById('scan_status'),
        resultCard: document.getElementById('result_card'),
        resultMeta: document.getElementById('result_meta'),
        resultItems: document.getElementById('result_items'),
        scannerModal: document.getElementById('scanner_modal'),
        scannerVideo: document.getElementById('scanner_video'),
        scannerQr: document.getElementById('scanner_qr'),
        btnCloseScanner: document.getElementById('btn_close_scanner'),
        btnStartScan: document.getElementById('btn_start_scan'),
        scannerHint: document.getElementById('scanner_hint'),
    };

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
            '{{ asset('vendor/html5-qrcode.min.js') }}',
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

    const setStatus = (text, type = 'muted') => {
        el.scanStatus.textContent = text;
        if (type === 'error') {
            el.scanStatus.style.color = '#b91c1c';
        } else if (type === 'success') {
            el.scanStatus.style.color = '#047857';
        } else if (type === 'pending') {
            el.scanStatus.style.color = '#f97316';
        } else {
            el.scanStatus.style.color = '#6b7280';
        }
    };

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const buildErrorMessage = (res, json) => {
        if (json?.message) {
            return json.message;
        }
        if (res.status === 419) {
            return 'Sesi habis. Silakan refresh halaman dan coba lagi.';
        }
        if (res.status === 403) {
            return 'Akses ditolak.';
        }
        if (res.status === 404) {
            return 'Endpoint tidak ditemukan.';
        }
        if (res.status >= 500) {
            return 'Terjadi kesalahan server. Coba lagi.';
        }
        return 'Terjadi kesalahan.';
    };

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
            ...options,
        });

        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (err) { json = null; }
        if (res.redirected || (!json && res.ok)) {
            const error = new Error('Request scan out dialihkan. Tetap di halaman scan out dan silakan scan ulang.');
            error.status = res.status;
            throw error;
        }

        if (!res.ok) {
            const error = new Error(buildErrorMessage(res, json));
            if (json?.details) {
                error.details = json.details;
            }
            error.status = res.status;
            throw error;
        }

        return json;
    };

    const showError = (message, details = []) => {
        if (typeof Swal !== 'undefined') {
            let html = `<div style="text-align:left; font-size:13px;">${esc(message)}</div>`;
            if (Array.isArray(details) && details.length) {
                const list = details.map((row) => {
                    const sku = esc(row.sku || '-');
                    const required = esc(row.required ?? '-');
                    const available = esc(row.available ?? '-');
                    const reason = row.reason ? `<div style="color:#64748b; font-size:12px;">${esc(row.reason)}</div>` : '';
                    const stock = row.available !== undefined
                        ? `<div style="color:#64748b; font-size:12px;">Butuh ${required}, tersedia ${available}</div>`
                        : `<div style="color:#64748b; font-size:12px;">Butuh ${required}</div>`;
                    return `<li style="margin-bottom:8px;"><strong>${sku}</strong>${reason}${stock}</li>`;
                }).join('');
                html += `<ul style="text-align:left; padding-left:18px; margin-top:8px;">${list}</ul>`;
            }
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                html,
            });
            return;
        }

        setStatus(message, 'error');
    };

    const renderResult = (data) => {
        const resi = data?.resi || {};
        const items = Array.isArray(data?.items) ? data.items : [];
        const resiLine = [
            resi.id_pesanan ? `ID Pesanan: ${resi.id_pesanan}` : null,
            resi.no_resi ? `No Resi: ${resi.no_resi}` : null,
            resi.tanggal_pesanan ? `Tanggal Order: ${resi.tanggal_pesanan}` : null,
        ].filter(Boolean).join(' • ');

        el.resultMeta.textContent = resiLine || '-';
        el.resultItems.innerHTML = items.map((row) => {
            const qty = row.qty ?? 0;
            return `<div class="result-item"><strong>${row.sku || '-'}</strong><span>${qty} qty</span></div>`;
        }).join('');

        el.resultCard.style.display = 'block';
    };

    const submitScan = async () => {
        getAudioCtx();
        const type = el.scanType.value;
        const code = el.scanCode.value.trim();
        if (!code) {
            setStatus('Masukkan nomor resi atau ID pesanan.', 'error');
            el.scanCode.focus();
            return;
        }

        el.btnScan.disabled = true;
        setStatus('Memproses resi...', 'pending');

        try {
            const data = await fetchJson(routes.scan, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ code, type, _token: csrfToken }),
            });

            setStatus(data?.message || 'Resi berhasil diproses.', 'success');
            playSuccessSound();
            renderResult(data);
            el.scanCode.value = '';
            el.scanCode.focus();
        } catch (error) {
            showError(error.message || 'Gagal memproses resi.', error.details || []);
            setStatus(error.message || 'Gagal memproses resi.', 'error');
        } finally {
            el.btnScan.disabled = false;
        }
    };

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
        el.scannerVideo.srcObject = null;
    };

    const closeScanner = () => {
        stopScanner();
        el.scannerModal.style.display = 'none';
        el.btnStartScan.disabled = false;
        el.scannerHint.textContent = 'Arahkan kamera ke barcode resi.';
    };

    const openScanner = async () => {
        getAudioCtx();
        if (!window.isSecureContext) {
            showError('Akses kamera membutuhkan HTTPS. Gunakan domain HTTPS atau localhost.');
            return;
        }

        const hasNative = 'BarcodeDetector' in window && !isIOS;
        const html5Ready = await loadHtml5Qr();
        const hasHtml5 = html5Ready && typeof Html5Qrcode !== 'undefined';

        if (!hasNative && !hasHtml5) {
            showError('Browser belum mendukung scan kamera. Gunakan input manual atau pastikan file html5-qrcode tersedia.');
            return;
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError('Akses kamera tidak tersedia di browser ini. Gunakan input manual.');
            return;
        }

        scanMode = hasNative ? 'native' : 'html5';
        el.scannerVideo.style.display = scanMode === 'native' ? 'block' : 'none';
        el.scannerQr.style.display = scanMode === 'html5' ? 'block' : 'none';

        if (scanMode === 'native') {
            try {
                barcodeDetector = new BarcodeDetector({
                    formats: ['code_128', 'code_39', 'ean_13', 'ean_8', 'qr_code', 'upc_a', 'upc_e'],
                });
            } catch (error) {
                if (hasHtml5) {
                    scanMode = 'html5';
                    el.scannerVideo.style.display = 'none';
                    el.scannerQr.style.display = 'block';
                } else {
                    showError('Fitur scan tidak tersedia. Gunakan input manual.');
                    return;
                }
            }
        }

        el.scannerModal.style.display = 'flex';
    };

    const startScanner = async () => {
        if (scanMode === 'html5') {
            try {
                el.btnStartScan.disabled = true;
                el.scannerHint.textContent = 'Mengaktifkan kamera...';
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
                            el.scanCode.value = decodedText;
                            el.scanCode.focus();
                            closeScanner();
                        }
                    },
                    () => {}
                );
                scannerActive = true;
                el.scannerHint.textContent = 'Scan berjalan. Arahkan ke barcode.';
                return;
            } catch (error) {
                el.btnStartScan.disabled = false;
                showError('Tidak bisa membuka kamera. Pastikan izin kamera aktif.');
                closeScanner();
                return;
            }
        }

        try {
            el.btnStartScan.disabled = true;
            el.scannerHint.textContent = 'Mengaktifkan kamera...';
            scannerStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    facingMode: { ideal: 'environment' },
                },
                audio: false,
            });
            el.scannerVideo.srcObject = scannerStream;
            await el.scannerVideo.play();
            scannerActive = true;
            el.scannerHint.textContent = 'Scan berjalan. Arahkan ke barcode.';
            scanLoop();
        } catch (error) {
            el.btnStartScan.disabled = false;
            showError('Tidak bisa membuka kamera. Pastikan izin kamera aktif.');
            closeScanner();
        }
    };

    const scanLoop = async () => {
        if (!scannerActive || !barcodeDetector) return;
        try {
            const barcodes = await barcodeDetector.detect(el.scannerVideo);
            if (Array.isArray(barcodes) && barcodes.length) {
                const code = barcodes[0].rawValue || '';
                if (code) {
                    playScanSound();
                    el.scanCode.value = code;
                    el.scanCode.focus();
                    closeScanner();
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

        setStatus('Memproses foto...', 'pending');
        const ready = await loadHtml5Qr();
        if (!ready || typeof Html5Qrcode === 'undefined') {
            showError('Library scan belum tersedia. Gunakan input manual.');
            setStatus('Scan foto gagal.', 'error');
            return;
        }

        try {
            closeScanner();
            const photoScanner = new Html5Qrcode('scanner_qr');
            const decodedText = await photoScanner.scanFile(file, true);
            await photoScanner.clear();
            playScanSound();
            el.scanCode.value = decodedText || '';
            el.scanCode.focus();
            setStatus('Hasil scan foto siap. Tekan Proses Resi.', 'success');
        } catch (error) {
            showError('Gagal membaca barcode dari foto. Pastikan barcode jelas dan tidak blur.');
            setStatus('Scan foto gagal.', 'error');
        } finally {
            el.scanPhotoInput.value = '';
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
            setStatus('Scan kamera tidak tersedia. Gunakan input manual.', 'error');
            return;
        }
    };

    el.btnScan.addEventListener('click', submitScan);
    el.btnOpenScanner.addEventListener('click', openScanner);
    el.btnCloseScanner.addEventListener('click', closeScanner);
    el.btnStartScan.addEventListener('click', startScanner);
    if (el.photoScanWrap) {
        el.photoScanWrap.style.display = isIOS ? 'flex' : 'none';
    }
    el.btnScanPhoto.addEventListener('click', () => {
        el.scanPhotoInput.click();
    });
    el.scanPhotoInput.addEventListener('change', (event) => {
        const file = event.target.files && event.target.files[0];
        scanFromPhoto(file);
    });
    el.scannerModal.addEventListener('click', (event) => {
        if (event.target === el.scannerModal) {
            closeScanner();
        }
    });
    el.scanType.addEventListener('change', () => {
        const type = el.scanType.value;
        el.scanCode.placeholder = type === 'id_pesanan' ? 'Scan ID Pesanan' : 'Scan No. Resi';
        el.scanCode.focus();
    });
    el.scanCode.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            submitScan();
        }
    });

    updateScanAvailability();
</script>
@endsection
