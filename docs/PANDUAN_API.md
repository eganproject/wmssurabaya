# Panduan Penggunaan API WMS Surabaya

Panduan ini untuk tim yang memakai API dari sistem lain (misalnya server pusat). Ada dua kelompok API:

1. **Penarikan stok**: `GET /api/v1/stocks` untuk saldo stok per SKU, baik saldo berjalan maupun saldo per tanggal.
2. **Pergerakan stok**: `GET /api/v1/stock-movements` dan `GET /api/v1/stock-movements/stores`, isinya sama dengan **Laporan Stok → Analisis Pergerakan** di web.

Detail teknis tiap field ada di [API_STOK_GUDANG.md](API_STOK_GUDANG.md).

---

## 1. Persiapan (sekali saja, oleh admin WMS)

### a. Atur token di `.env` server

```env
STOCK_API_ENABLED=true
STOCK_API_TOKEN=<token-rahasia>
STOCK_API_WAREHOUSE_CODE=WSSBY
STOCK_API_RATE_LIMIT_PER_MINUTE=60
```

Buat token acak yang panjang, contohnya dengan perintah ini:

```bash
openssl rand -hex 32
```

Setelah `.env` diubah, jalankan `php artisan config:clear` (atau `php artisan config:cache` kalau server memakai config cache).

Kirim token ke tim pemakai API lewat jalur yang aman, jangan lewat chat grup.

### b. Daftarkan IP pemanggil

Buka **Master Data → Akses API Stok**, tambahkan IP publik server pemanggil, lalu pastikan statusnya aktif. Request dari IP yang tidak ada di daftar ini ditolak walaupun tokennya benar. Kalau daftarnya masih kosong, semua akses ditolak.

> Jika WMS berada di belakang reverse proxy, load balancer, atau Cloudflare, aplikasi bisa membaca IP proxy, bukan IP pemanggil asli. Kalau request dari IP yang sudah terdaftar tetap mendapat `IP_NOT_ALLOWED`, cek IP yang terbaca dengan `tail storage/logs/laravel.log` atau tanyakan ke admin server, lalu atur trusted proxies.

### c. Uji koneksi

```bash
curl -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  https://<domain-wms>/api/v1/health
```

Hasilnya harus seperti ini:

```json
{"success": true, "warehouse_code": "WSSBY", "server_time": "2026-10-21T15:00:00+07:00"}
```

---

## 2. Aturan umum semua endpoint

- Semua request memakai `GET` dan wajib mengirim header `Authorization: Bearer <token>` dan `Accept: application/json`.
- Batas request default adalah 60 per menit untuk setiap kombinasi token + IP (`STOCK_API_RATE_LIMIT_PER_MINUTE`). Kalau terlewati, server membalas HTTP 429, jadi tunggu sebentar lalu ulangi.
- Waktu memakai WIB (`+07:00`).

| HTTP | `error.code` | Arti / tindakan |
|---|---|---|
| 401 | `UNAUTHORIZED` | Token salah atau tidak dikirim |
| 403 | `IP_NOT_ALLOWED` | IP belum didaftarkan atau tidak aktif di Akses API Stok |
| 503 | `API_DISABLED` | `STOCK_API_ENABLED=false` di server |
| 400 | `INVALID_PARAMETER` | Parameter salah; lihat `error.message` |
| 429 | — | Terlalu banyak request |

---

## 3. API Penarikan Stok — `GET /api/v1/stocks`

`qty` adalah total stok semua gudang aktif per SKU. Item nonaktif dikirim dengan `status: "deleted"` dan `qty: 0`.

### Mode A: sinkron inkremental (yang berubah saja)

| Parameter | Keterangan |
|---|---|
| `updated_since` | ISO-8601 dengan zona waktu, misal `2026-10-21T00:00:00+07:00` |
| `updated_until` | ISO-8601; isi dengan `meta.server_time` dari halaman pertama |
| `page`, `per_page` | default 1 / 100, maks 500 |

Alur yang disarankan:
1. Untuk penarikan pertama, panggil tanpa `updated_since` supaya semua data terambil.
2. Simpan `meta.server_time` dari halaman 1, lalu pakai sebagai `updated_until` di halaman berikutnya agar datanya konsisten.
3. Ulangi `page=2,3,...` sampai `page > meta.total_pages`.
4. Di sinkron berikutnya, pakai `server_time` tersebut sebagai `updated_since`.

```bash
curl -G -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  --data-urlencode "updated_since=2026-10-20T00:00:00+07:00" \
  -d page=1 -d per_page=500 \
  https://<domain-wms>/api/v1/stocks
```

### Mode B: saldo penutup per tanggal

```bash
curl -G -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  -d as_of=2026-10-20 -d page=1 -d per_page=500 \
  https://<domain-wms>/api/v1/stocks
```

`as_of` tidak bisa digabung dengan `updated_since` atau `updated_until`.

Contoh respons:

```json
{
  "success": true,
  "meta": {"warehouse_code": "WSSBY", "server_time": "2026-10-21T15:00:00+07:00", "page": 1, "per_page": 500, "total": 1, "total_pages": 1},
  "data": [{"sku": "SKU-001", "name": "Produk", "category": "Kategori", "uom": "PCS", "qty": 120, "min_qty": 10, "status": "active", "updated_at": "2026-10-21T14:10:00+07:00"}]
}
```

---

## 4. API Pergerakan Stok — `GET /api/v1/stock-movements`

Isinya sama dengan tab **Analisis Pergerakan** di Laporan Stok: klasifikasi fast/medium/slow/non-moving, qty keluar, rata-rata harian, days cover, dan rekomendasi. Stok yang dipakai adalah gabungan Gudang Besar dan Gudang Kecil. Qty keluar dihitung dari outbound manual dan resi yang QC-nya sudah selesai.

| Parameter | Keterangan | Sama dengan filter web |
|---|---|---|
| `date_from`, `date_to` | `YYYY-MM-DD`, default 30 hari terakhir | Periode |
| `category_id` | ID kategori, `0` = Tanpa Kategori | Kategori |
| `is_active` | `1` (default) / `0` | Status produk |
| `store_id` | ID toko, atau `none` | Toko |
| `sku` (atau `q`) | SKU persis | Cari SKU |
| `movement` | `fast`, `medium`, `slow`, `non_moving` | Kelas pergerakan |
| `cover_min`, `cover_max` | Rentang days cover | Days cover |
| `sort`, `dir` | `sku`, `location`, `movement`, `stock`, `outbound_qty`, `average_daily_outbound`, `contribution_percent`, `outbound_transactions`, `days_cover`, `last_outbound_at` · `asc`/`desc` | Klik kolom tabel |
| `page`, `per_page` | default 1 / 100, maks 500 | — |
| `include_trend` | `1` untuk menyertakan grafik qty keluar harian | Grafik tren |

Contoh 1: semua SKU fast moving bulan ini

```bash
curl -G -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  -d date_from=2026-10-01 -d date_to=2026-10-21 -d movement=fast -d per_page=500 \
  https://<domain-wms>/api/v1/stock-movements
```

Contoh 2: SKU yang stoknya hanya cukup 0–7 hari, urut dari cover terkecil

```bash
curl -G -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  -d cover_min=0 -d cover_max=7 -d sort=days_cover -d dir=asc \
  https://<domain-wms>/api/v1/stock-movements
```

Contoh respons (dipersingkat):

```json
{
  "success": true,
  "meta": {"warehouse_code": "WSSBY", "server_time": "2026-10-21T15:33:15+07:00", "date_from": "2026-10-01", "date_to": "2026-10-21", "period_days": 21, "page": 1, "per_page": 500, "total": 1, "total_unfiltered": 4, "total_pages": 1},
  "summary": {"total_sku": 1, "fast_sku": 1, "medium_sku": 0, "slow_sku": 0, "non_moving_sku": 0, "total_outbound_qty": 70, "total_stock": 100, "critical_cover_sku": 0, "warehouse": "Gudang Besar + Gudang Kecil", "store": "Semua toko"},
  "data": [{"sku": "MOVE-FAST", "name": "Item MOVE-FAST", "category": "Tanpa Kategori", "location": "-", "stock": 100, "safety_stock": 0, "outbound_qty": 70, "average_daily_outbound": 3.33, "days_cover": 30, "contribution_percent": 70, "movement_key": "fast", "movement_label": "Fast moving", "last_outbound_at": "2026-10-20 15:33:15", "recommended_action": "Pertahankan dan monitor berkala"}]
}
```

- `meta.total` adalah jumlah baris setelah semua filter, dan `meta.total_unfiltered` adalah jumlah SKU sebelum filter `movement`/`cover_*`.
- `summary` berisi angka yang sama dengan kartu ringkasan di web untuk filter yang sama.
- Halaman diambil dengan `page=1,2,...` sampai `meta.total_pages`.

### Ringkasan per toko: `GET /api/v1/stock-movements/stores`

Parameter filternya sama (tanpa `movement`, `cover_*`, `sort`, dan `page`). Tambahkan `include_items=1` untuk menyertakan daftar SKU per toko. Hasilnya hanya mencakup resi, karena toko hanya tercatat di resi.

```bash
curl -G -H "Authorization: Bearer <token>" -H "Accept: application/json" \
  -d date_from=2026-10-01 -d date_to=2026-10-21 \
  https://<domain-wms>/api/v1/stock-movements/stores
```

---

## 5. Langkah setelah `git pull` di server

Untuk update yang menambahkan API pergerakan stok (PR #4):

```bash
cd /path/ke/wmssurabaya
git pull origin main
composer install --no-dev --optimize-autoloader   # aman dijalankan walau tidak ada paket baru
php artisan migrate --force                        # update ini tidak punya migrasi baru, tapi aman
php artisan optimize:clear                         # wajib: route API baru harus terbaca
php artisan optimize                               # opsional, kalau server memakai cache config/route
```

Setelah itu:
- Restart PHP-FPM (misalnya `sudo systemctl restart php8.3-fpm`) kalau OPcache aktif dengan `validate_timestamps=0`. Kalau Anda tidak yakin, restart saja.
- Jika ada queue worker, jalankan `php artisan queue:restart`.
- Uji `GET /api/v1/stock-movements` dengan token. Kalau hasilnya 404, berarti cache route belum dibersihkan, jadi ulangi `php artisan optimize:clear`.

Update ini tidak membutuhkan variabel `.env` baru, migrasi, atau seeder. Token dan whitelist IP yang sudah dipakai API stok langsung berlaku juga untuk API pergerakan.

Jika server **belum pernah** memasang API stok sama sekali, jalankan juga langkah deploy pertama yang ada di [API_STOK_GUDANG.md](API_STOK_GUDANG.md#deploy-pertama-kali), yaitu `migrate`, `db:seed --class=MenuSeeder`, dan `stock-api:backfill`, lalu atur token dan whitelist IP seperti di bagian 1.
