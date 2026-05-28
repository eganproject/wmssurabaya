@extends('layouts.mobile')

@section('title', 'History Scan Out')

@section('content')
<style>
    .filter-grid {
        display: grid;
        gap: 10px;
    }
    .list-row {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px;
        align-items: center;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 12px;
        background: #fff;
    }
    .list-row strong {
        display: block;
        font-size: 13px;
    }
    .list-row small {
        color: var(--muted);
        font-size: 11px;
    }
    .type-badge {
        background: rgba(14, 116, 144, 0.12);
        color: #0e7490;
        font-weight: 700;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        white-space: nowrap;
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
            <div class="subtitle">History Scan Out</div>
        </div>
        <div class="topbar-actions">
            <a href="{{ $routes['scanOut'] }}" class="logout">Scan Out</a>
            <a href="{{ $routes['dashboard'] }}" class="logout">Dashboard</a>
            <form method="POST" action="{{ $routes['logout'] }}">
                @csrf
                <button type="submit" class="logout">Logout</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div style="font-weight: 700; margin-bottom: 8px;">Filter</div>
        <div class="filter-grid">
            <input type="date" class="input" id="filter_date" value="{{ $today ?? '' }}" />
            <input type="text" class="input" id="filter_search" placeholder="Cari ID Pesanan / No Resi" autocomplete="off" />
        </div>
    </div>

    <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
            <div style="font-weight:700;">Daftar Scan Out</div>
            <div class="muted" id="total_items">0 data</div>
        </div>
        <div class="muted" id="list_empty">Belum ada data.</div>
        <div class="items-list" id="list_items"></div>
    </div>
</div>

<script>
    const routes = @json($routes);
    const todayStr = '{{ $today ?? '' }}';
    const csrfToken = '{{ csrf_token() }}';

    const el = {
        date: document.getElementById('filter_date'),
        search: document.getElementById('filter_search'),
        list: document.getElementById('list_items'),
        empty: document.getElementById('list_empty'),
        total: document.getElementById('total_items'),
    };

    const fetchJson = async (url) => {
        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            }
        });
        const text = await res.text();
        let json = null;
        try { json = JSON.parse(text); } catch (err) { json = null; }
        if (!res.ok) {
            throw new Error(json?.message || 'Gagal mengambil data');
        }
        return json;
    };

    const renderList = (items) => {
        el.total.textContent = `${items.length} data`;
        if (!items.length) {
            el.empty.style.display = 'block';
            el.list.innerHTML = '';
            return;
        }
        el.empty.style.display = 'none';
        el.list.innerHTML = items.map((row) => {
            const idPesanan = row.id_pesanan || '-';
            const noResi = row.no_resi || '-';
            const scanType = row.scan_type === 'id_pesanan' ? 'ID Pesanan' : 'No Resi';
            const scannedAt = row.scanned_at || '-';
            return `
                <div class="list-row">
                    <div>
                        <strong>ID Pesanan: ${idPesanan}</strong>
                        <small>No Resi: ${noResi} • ${scannedAt}</small>
                    </div>
                    <div class="type-badge">${scanType}</div>
                </div>
            `;
        }).join('');
    };

    let searchTimer = null;
    const loadData = async () => {
        const params = new URLSearchParams();
        const dateVal = el.date?.value || todayStr;
        if (dateVal) params.set('date', dateVal);
        const q = (el.search?.value || '').trim();
        if (q) params.set('q', q);

        const url = `${routes.data}?${params.toString()}`;
        const data = await fetchJson(url);
        renderList(Array.isArray(data.items) ? data.items : []);
    };

    el.date?.addEventListener('change', loadData);
    el.search?.addEventListener('input', () => {
        if (searchTimer) clearTimeout(searchTimer);
        searchTimer = setTimeout(loadData, 300);
    });

    if (el.date && !el.date.value && todayStr) {
        el.date.value = todayStr;
    }
    loadData();
</script>
@endsection
