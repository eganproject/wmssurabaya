# API Stok Gudang

> Panduan pemakaian langkah demi langkah (setup, contoh request, langkah deploy) ada di [PANDUAN_API.md](PANDUAN_API.md).

API ini memiliki format respons yang sama dengan API `warehouse29`. `qty` dan `min_qty` adalah total dari seluruh gudang aktif WMS Surabaya.

## Konfigurasi

```env
STOCK_API_ENABLED=true
STOCK_API_TOKEN=<token-rahasia-panjang-dan-acak>
STOCK_API_WAREHOUSE_CODE=WSSBY
STOCK_API_RATE_LIMIT_PER_MINUTE=60
```

## Deploy pertama kali

```bash
php artisan migrate
php artisan db:seed --class=MenuSeeder
php artisan stock-api:backfill
php artisan optimize:clear
```

Backfill hanya membentuk data proyeksi API; tidak mengubah `item_stocks` atau `stock_mutations`.

Sebelum memanggil API, tambahkan IP publik server pusat melalui **Master Data → Akses API Stok**. Hanya IP aktif dalam daftar tersebut yang dapat memakai token. Jika daftar masih kosong, seluruh akses API ditolak.

## Endpoint

Semua endpoint memerlukan header berikut:

```http
Authorization: Bearer <API_TOKEN>
Accept: application/json
```

- `GET /api/v1/health`
- `GET /api/v1/stocks?updated_since=<ISO-8601>&updated_until=<ISO-8601>&page=1&per_page=100`
- `GET /api/v1/stocks?as_of=YYYY-MM-DD&page=1&per_page=100`

Respons `GET /api/v1/health`:

```json
{"success": true, "warehouse_code": "WSSBY", "server_time": "2026-07-27T12:00:00+07:00"}
```

`meta.warehouse_code` diambil dari `STOCK_API_WAREHOUSE_CODE` dan berfungsi sebagai identitas WMS Surabaya, bukan filter gudang.

Format respons tetap:

```json
{
  "success": true,
  "meta": {"warehouse_code": "WH-SMALL", "server_time": "...", "page": 1, "per_page": 100, "total": 0, "total_pages": 0},
  "data": [{"sku": "SKU-001", "name": "Produk", "category": "Kategori", "uom": "PCS", "qty": 0, "min_qty": null, "status": "active", "updated_at": "..."}]
}
```

`per_page` maksimum 500. Tanpa `as_of`, data dapat diambil inkremental dengan `updated_since`/`updated_until` dan diurutkan `updated_at ASC, sku ASC`. Gunakan `meta.server_time` dari halaman pertama sebagai `updated_until` di halaman berikutnya.

`as_of` memakai WIB dan mengembalikan saldo penutup tanggal tersebut. Parameter ini tidak dapat digabung dengan `updated_since` atau `updated_until`. Stok rusak tidak termasuk; bundle dihitung virtual per gudang lalu dijumlahkan.

Item yang dinonaktifkan dari Master Item dikirim sebagai `status: deleted` dengan `qty: 0`. Jika item diaktifkan kembali, status API kembali menjadi `active` dengan saldo berjalan terbaru.

## API Analisis Pergerakan Stok

Data sama persis dengan **Laporan Stok → Analisis Pergerakan** (tabel web dan export Excel), karena memakai builder yang sama (`App\Support\StockMovementReport`). Stok = akumulasi Gudang Besar + Gudang Kecil; qty keluar = outbound manual + resi yang QC-nya selesai. Klasifikasi Pareto: kontribusi kumulatif < 70% fast, < 90% medium, sisanya slow, qty keluar 0 = non-moving. Laporan ini tidak punya filter gudang.

Header dan proteksi (token + whitelist IP + rate limit) sama dengan endpoint lain di atas.

- `GET /api/v1/stock-movements` — detail per SKU
- `GET /api/v1/stock-movements/stores` — ringkasan per toko (hanya resi, karena toko hanya ada di resi)

### Parameter filter (keduanya)

| Parameter | Keterangan |
|---|---|
| `date_from`, `date_to` | Tanggal `YYYY-MM-DD`. Default 30 hari terakhir s/d hari ini. Jika terbalik, ditukar otomatis. |
| `category_id` | ID kategori; `0` = Tanpa Kategori. |
| `is_active` | `1` (default) produk aktif, `0` nonaktif. |
| `store_id` | ID toko, atau `none` = resi tanpa toko. Jika diisi, qty keluar hanya dari resi toko itu. |
| `q` / `sku` | SKU persis (exact match), sama seperti kotak cari di web. |
| `movement` | `fast`, `medium`, `slow`, `non_moving` (hanya `/stock-movements`). |
| `cover_min`, `cover_max` | Rentang days cover (inklusif, ≥ 0, `cover_max` ≥ `cover_min`). SKU tanpa qty keluar tidak ikut jika diisi. |

Klasifikasi selalu dihitung dari seluruh SKU hasil filter tanggal/kategori/status/toko/SKU; `movement` dan `cover_*` hanya menyaring baris (sama seperti web).

### Parameter tambahan `/stock-movements`

| Parameter | Keterangan |
|---|---|
| `sort` | `sku`, `location`, `movement`, `stock`, `outbound_qty`, `average_daily_outbound`, `contribution_percent`, `outbound_transactions`, `days_cover`, `last_outbound_at`. Default: urutan kelas lalu qty keluar terbesar. |
| `dir` | `asc` (default) / `desc`. |
| `page`, `per_page` | Default 1 dan 100; `per_page` maksimum 500. |
| `include_trend` | `1`/`true` untuk menyertakan `trend` qty keluar harian. |

`/stock-movements/stores` menerima `include_items=1` untuk menyertakan klasifikasi SKU per toko.

### Contoh

```http
GET /api/v1/stock-movements?date_from=2026-09-22&date_to=2026-10-21&movement=fast&per_page=50
```

```json
{
  "success": true,
  "meta": {"warehouse_code": "WSSBY", "server_time": "2026-10-21T15:33:15+07:00", "date_from": "2026-09-22", "date_to": "2026-10-21", "period_days": 30, "page": 1, "per_page": 50, "total": 1, "total_unfiltered": 4, "total_pages": 1},
  "summary": {"total_sku": 1, "fast_sku": 1, "medium_sku": 0, "slow_sku": 0, "non_moving_sku": 0, "total_outbound_qty": 70, "total_stock": 100, "non_moving_stock": 0, "below_safety_sku": 0, "critical_cover_sku": 0, "out_of_stock_sku": 0, "total_transactions": 1, "period_days": 30, "date_from": "2026-09-22", "date_to": "2026-10-21", "warehouse": "Gudang Besar + Gudang Kecil", "outbound_source": "Outbound manual + import resi selesai", "store": "Semua toko"},
  "data": [{
    "id": 1, "sku": "MOVE-FAST", "name": "Item MOVE-FAST", "category": "Tanpa Kategori",
    "warehouse": "Gudang Besar + Gudang Kecil", "warehouse_type": "Akumulasi", "location": "-",
    "stock": 100, "safety_stock": 0, "gap_to_safety": 0, "base_unit": "PCS",
    "outbound_qty": 70, "outbound_transactions": 1, "active_days": 1, "average_daily_outbound": 2.33,
    "days_cover": 42.9, "last_outbound_at": "2026-10-20 15:33:15", "is_active": true,
    "movement_key": "fast", "movement_label": "Fast moving", "contribution_percent": 70,
    "recommended_action": "Pertahankan dan monitor berkala"
  }]
}
```

`meta.total` = jumlah baris setelah semua filter; `meta.total_unfiltered` = jumlah SKU sebelum filter `movement`/`cover_*`. `summary` adalah ringkasan baris yang tersaring (sama dengan kartu ringkasan di web).

Contoh `/stock-movements/stores`:

```json
{
  "success": true,
  "meta": {"warehouse_code": "WSSBY", "server_time": "...", "date_from": "2026-09-22", "date_to": "2026-10-21", "period_days": 30},
  "data": [{"store_id": 3, "store_name": "Toko A", "total_outbound_qty": 120, "moving_sku": 8, "fast_sku": 2, "medium_sku": 3, "slow_sku": 3, "top_fast": [{"sku": "SKU-1", "name": "...", "outbound_qty": 60, "average_daily_outbound": 2, "contribution_percent": 50, "movement_key": "fast", "movement_label": "Fast moving"}]}]
}
```

Parameter tidak valid menghasilkan HTTP 400:

```json
{"success": false, "error": {"code": "INVALID_PARAMETER", "message": "The selected movement is invalid.", "fields": {"movement": ["The selected movement is invalid."]}}}
```
