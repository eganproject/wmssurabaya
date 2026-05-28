@extends('layouts.mobile')

@section('title', 'Stock Opname Mobile')

@section('content')
<style>
    .section-title {
        font-weight: 700;
        margin-bottom: 8px;
    }
    .batch-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 10px;
        align-items: center;
        margin-top: 10px;
    }
    .small-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
        border-radius: 12px;
        font-weight: 700;
        border: 1px solid var(--border);
        background: #fff;
    }
    .chip {
        display: inline-flex;
        align-items: center;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 11px;
        background: rgba(15, 118, 110, 0.12);
        color: var(--brand);
        font-weight: 600;
    }
    .batch-info {
        margin-top: 12px;
        padding: 12px;
        border-radius: 14px;
        background: #f8fafc;
        border: 1px dashed var(--border);
        font-size: 12px;
        color: var(--muted);
    }
    .result-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .result-qty {
        width: 70px;
        padding: 8px;
        border-radius: 10px;
        border: 1px solid var(--border);
        font-size: 12px;
        text-align: center;
    }
    .tiny-btn {
        padding: 6px 10px;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: #fff;
        font-size: 12px;
        font-weight: 700;
    }
    .item-actions {
        display: grid;
        gap: 6px;
        justify-items: end;
    }
    .item-meta small {
        color: var(--muted);
    }
    .item-sub {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 6px;
        font-size: 11px;
        color: var(--muted);
    }
    .sync-btn {
        border: 1px solid var(--border);
        background: #fff;
        padding: 6px 10px;
        border-radius: 10px;
        font-size: 12px;
        font-weight: 700;
    }
    .summary-line {
        font-size: 12px;
        color: var(--muted);
    }
    .summary-line strong {
        color: var(--text);
    }
    .bottom-actions {
        display: grid;
        gap: 8px;
        justify-items: end;
    }
    .bottom-actions .primary-btn {
        width: auto;
        padding: 10px 12px;
        font-size: 12px;
        border-radius: 12px;
    }
    .topbar-actions {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .topbar-actions form {
        margin: 0;
    }
</style>

<div class="screen">
    <div class="topbar">
        <div>
            <div class="brand">{{ config('app.name') }}</div>
            <div class="subtitle">Input Stock Opname (Mobile)</div>
        </div>
        <div class="topbar-actions">
            <a href="{{ $routes['dashboard'] }}" class="logout">Dashboard</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="section-title">Batch Stock Opname</div>
        <div class="muted">Semua input akan masuk ke satu batch yang sama.</div>
        <div class="batch-row">
            <input type="text" class="input" id="batch_code" placeholder="Masukkan kode batch">
            <button type="button" class="small-btn" id="btn_join_batch">Gabung</button>
        </div>
        <div style="margin-top:10px; display:grid; gap:8px;">
            <button type="button" class="sync-btn" id="btn_sync_batch">Sinkronkan Batch</button>
        </div>
        <div class="batch-info" id="batch_info" style="display:none;"></div>
    </div>

    <div class="card disabled" id="search_card">
        <div style="font-weight: 700; margin-bottom: 8px;">Tambah Item Opname</div>
        <input type="text" class="input" id="item_search" placeholder="Cari SKU atau nama barang" autocomplete="off" />
        <div class="results" id="search_results"></div>
    </div>

    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <div style="font-weight:700;">Daftar Item Batch</div>
            <div class="muted" id="total_items">0 item</div>
        </div>
        <div class="muted" id="items_empty">Belum ada item pada batch ini.</div>
        <div class="items-list" id="items_list"></div>
    </div>
</div>

<div class="bottom-bar">
    <div class="bottom-inner">
        <div>
            <div class="summary-line">
                Total qty dihitung: <strong id="total_counted">0</strong>
            </div>
        </div>
        <div class="bottom-actions">
            <div class="chip" id="batch_code_chip">Belum ada batch</div>
        </div>
    </div>
</div>

<script>
    const routes = @json($routes);
    const csrfToken = '{{ csrf_token() }}';

    const state = {
        batch: null,
        items: [],
        searching: false,
    };

    const el = {
        batchCode: document.getElementById('batch_code'),
        btnJoin: document.getElementById('btn_join_batch'),
        btnSync: document.getElementById('btn_sync_batch'),
        batchInfo: document.getElementById('batch_info'),
        batchChip: document.getElementById('batch_code_chip'),
        searchCard: document.getElementById('search_card'),
        itemSearch: document.getElementById('item_search'),
        searchResults: document.getElementById('search_results'),
        itemsList: document.getElementById('items_list'),
        itemsEmpty: document.getElementById('items_empty'),
        totalItems: document.getElementById('total_items'),
        totalCounted: document.getElementById('total_counted'),
    };

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers || {}),
            },
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
            throw error;
        }
        return json;
    };

    const setBatch = (payload) => {
        state.batch = payload.batch || null;
        state.items = payload.items || [];
        if (state.batch?.code) {
            localStorage.setItem('opname_batch_code', state.batch.code);
        }
        renderBatch();
        renderItems();
    };

    const renderBatch = () => {
        if (!state.batch) {
            el.batchInfo.style.display = 'none';
            el.batchChip.textContent = 'Belum ada batch';
            el.searchCard.classList.add('disabled');
            return;
        }
        el.batchCode.value = state.batch.code || '';
        el.batchChip.textContent = state.batch.code || '-';
        el.batchInfo.style.display = 'block';
        const note = state.batch.note ? `<br>Catatan: ${state.batch.note}` : '';
        el.batchInfo.innerHTML = `
            <div><strong>Kode:</strong> ${state.batch.code}</div>
            <div><strong>Tanggal:</strong> ${state.batch.transacted_at || '-'}</div>
            <div><strong>Dibuat oleh:</strong> ${state.batch.creator || '-'}</div>
            ${note}
        `;
        const isCompleted = state.batch.status === 'completed';
        el.searchCard.classList.toggle('disabled', isCompleted);
    };

    const renderItems = () => {
        const items = state.items || [];
        const isCompleted = state.batch?.status === 'completed';
        const totalCounted = items.reduce((sum, row) => sum + (row.counted_qty || 0), 0);
        el.totalItems.textContent = `${items.length} item`;
        el.totalCounted.textContent = `${totalCounted}`;

        if (!items.length) {
            el.itemsEmpty.style.display = 'block';
            el.itemsList.innerHTML = '';
            return;
        }

        el.itemsEmpty.style.display = 'none';
        el.itemsList.innerHTML = items.map((row) => {
            return `
                <div class="item-row" data-id="${row.id}">
                    <div class="item-meta">
                        <strong>${row.sku} • ${row.name}</strong>
                        <div class="item-sub">
                            <span>Input: ${row.created_by || '-'}</span>
                        </div>
                    </div>
                    <div class="item-actions">
                        <input type="number" min="0" class="qty-input" value="${row.counted_qty}" ${isCompleted ? 'disabled' : ''} />
                        <button class="tiny-btn btn-save" ${isCompleted ? 'disabled' : ''}>Simpan</button>
                        <button class="remove-btn" data-action="remove" ${isCompleted ? 'disabled' : ''}>×</button>
                    </div>
                </div>
            `;
        }).join('');
    };

    const loadBatch = async (code) => {
        const url = routes.batchShow.replace('__CODE__', encodeURIComponent(code));
        const json = await fetchJson(url);
        setBatch(json);
    };

    const searchItems = async (q) => {
        if (!q || q.length < 2) {
            el.searchResults.innerHTML = '';
            return;
        }
        const url = `${routes.itemsSearch}?q=${encodeURIComponent(q)}&batch_code=${encodeURIComponent(state.batch?.code || '')}`;
        const json = await fetchJson(url);
        const items = json.items || [];
        if (!items.length) {
            el.searchResults.innerHTML = '';
            return;
        }
        el.searchResults.innerHTML = items.map((item) => {
            return `
                <div class="result-item">
                    <div class="result-info">
                        <strong>${item.sku}</strong>
                        <span>${item.name}</span>
                    </div>
                    <div class="result-actions">
                        <input type="number" min="0" class="result-qty" value="0" />
                        <button class="add-btn" data-id="${item.id}">Tambah</button>
                    </div>
                </div>
            `;
        }).join('');
    };

    const addItem = async (itemId, qty) => {
        if (!state.batch) return;
        const url = routes.itemsStore.replace('__CODE__', encodeURIComponent(state.batch.code));
        const payload = new FormData();
        payload.append('item_id', itemId);
        payload.append('counted_qty', qty);
        const json = await fetchJson(url, { method: 'POST', body: payload });
        setBatch(json);
        el.itemSearch.value = '';
        el.searchResults.innerHTML = '';
    };

    const updateItem = async (rowId, qty, silent = false) => {
        if (!state.batch) return;
        const url = routes.itemsUpdate
            .replace('__CODE__', encodeURIComponent(state.batch.code))
            .replace('__ID__', rowId);
        const payload = new FormData();
        payload.append('_method', 'PUT');
        payload.append('counted_qty', qty);
        const json = await fetchJson(url, { method: 'POST', body: payload });
        setBatch(json);
        if (!silent && typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Tersimpan',
                text: 'Perubahan item berhasil disimpan.',
                confirmButtonText: 'OK',
            });
        }
    };

    const removeItem = async (rowId) => {
        if (!state.batch) return;
        const url = routes.itemsDestroy
            .replace('__CODE__', encodeURIComponent(state.batch.code))
            .replace('__ID__', rowId);
        const confirmed = typeof Swal !== 'undefined'
            ? await Swal.fire({
                icon: 'warning',
                title: 'Hapus item?',
                showCancelButton: true,
                confirmButtonText: 'Hapus',
                cancelButtonText: 'Batal',
            }).then(res => res.isConfirmed)
            : confirm('Hapus item?');
        if (!confirmed) return;
        const payload = new FormData();
        payload.append('_method', 'DELETE');
        const json = await fetchJson(url, { method: 'POST', body: payload });
        setBatch(json);
    };

    const debounce = (fn, delay = 300) => {
        let timer;
        return (...args) => {
            clearTimeout(timer);
            timer = setTimeout(() => fn(...args), delay);
        };
    };

    const runSearch = debounce(async () => {
        const q = el.itemSearch.value.trim();
        await searchItems(q);
    }, 300);

    el.btnJoin.addEventListener('click', async () => {
        const code = el.batchCode.value.trim();
        if (!code) return;
        try {
            await loadBatch(code);
        } catch (err) {
            Swal?.fire('Error', err.message || 'Batch tidak ditemukan', 'error');
        }
    });

    el.btnSync.addEventListener('click', async () => {
        if (!state.batch?.code) return;
        try {
            await loadBatch(state.batch.code);
        } catch (err) {
            Swal?.fire('Error', err.message || 'Gagal sinkron', 'error');
        }
    });

    el.itemSearch.addEventListener('input', runSearch);

    el.searchResults.addEventListener('click', async (e) => {
        if (state.batch?.status === 'completed') return;
        const btn = e.target.closest('.add-btn');
        if (!btn) return;
        const itemId = btn.getAttribute('data-id');
        const qtyInput = btn.parentElement?.querySelector('.result-qty');
        const qty = parseInt(qtyInput?.value || '0', 10);
        if (Number.isNaN(qty) || qty < 0) {
            Swal?.fire('Error', 'Qty tidak valid', 'error');
            return;
        }
        try {
            await addItem(itemId, qty);
        } catch (err) {
            Swal?.fire('Error', err.message || 'Gagal menambah item', 'error');
        }
    });

    el.itemsList.addEventListener('click', async (e) => {
        if (state.batch?.status === 'completed') return;
        if (e.target.classList.contains('btn-save')) {
            const row = e.target.closest('.item-row');
            const rowId = row?.getAttribute('data-id');
            const qtyInput = row?.querySelector('.qty-input');
            const qty = parseInt(qtyInput?.value || '0', 10);
            if (Number.isNaN(qty) || qty < 0) {
                Swal?.fire('Error', 'Qty tidak valid', 'error');
                return;
            }
            try {
                await updateItem(rowId, qty);
            } catch (err) {
                Swal?.fire('Error', err.message || 'Gagal memperbarui', 'error');
            }
        }

        if (e.target.getAttribute('data-action') === 'remove') {
            const row = e.target.closest('.item-row');
            const rowId = row?.getAttribute('data-id');
            if (rowId) {
                await removeItem(rowId);
            }
        }
    });

    const storedBatch = localStorage.getItem('opname_batch_code');
    if (storedBatch) {
        loadBatch(storedBatch).catch(() => {
            localStorage.removeItem('opname_batch_code');
        });
    }
</script>
@endsection
