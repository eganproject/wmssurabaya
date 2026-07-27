# API Stok Gudang

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
