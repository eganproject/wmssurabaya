# Rekomendasi Laporan Analitik Transfer Gudang

## 1. Ringkasan Eksekutif

Modul transfer gudang pada aplikasi ini sudah memiliki fondasi data yang cukup baik untuk
membangun laporan operasional dan pengendalian stok. Sistem menyimpan:

- gudang asal dan tujuan;
- status transfer dari draft sampai diterima atau dibatalkan;
- waktu dokumen, pengiriman, penerimaan, dan pembatalan;
- pengguna pembuat, pengirim, penerima, dan pembatal;
- kuantitas kirim dalam satuan input dan satuan dasar;
- kuantitas aktual yang diterima;
- selisih penerimaan beserta alasannya;
- mutasi stok gudang asal dan tujuan;
- stok aktual dan safety stock per item per gudang.

Laporan pertama yang paling berguna adalah **Dashboard Kinerja Transfer Gudang**. Dashboard
ini harus menjawab empat pertanyaan utama:

1. Berapa transfer yang masih tertahan atau sedang dalam perjalanan?
2. Berapa lama proses transfer berlangsung?
3. Berapa tingkat penerimaan lengkap dan berapa besar selisihnya?
4. Item dan rute gudang mana yang paling sering bermasalah?

## 2. Alur Bisnis yang Terekam

Alur transfer pada aplikasi:

1. **Draft**: dokumen dibuat dan masih dapat diubah atau dibatalkan.
2. **Shipped**: stok gudang asal langsung berkurang dan tercatat sebagai mutasi `out`.
3. **Received**: stok gudang tujuan bertambah sesuai jumlah aktual yang diterima.
4. **Received with discrepancy**: penerimaan selesai, tetapi jumlah diterima lebih kecil
   daripada jumlah dikirim.
5. **Cancelled**: hanya draft yang dapat dibatalkan.

Implikasi analitik:

- stok in-transit adalah jumlah barang pada transfer berstatus `shipped`;
- selisih baru final setelah transfer diterima;
- durasi proses dapat dipecah menjadi waktu tunggu draft dan waktu perjalanan;
- `transacted_at` adalah tanggal dokumen yang dapat diinput pengguna, sedangkan
  `created_at` adalah waktu pencatatan aktual oleh sistem;
- semua perbandingan lintas satuan sebaiknya menggunakan kuantitas satuan dasar.

## 3. Peta Sumber Data

| Tabel | Kegunaan Analitik |
|---|---|
| `stock_transfers` | Header transfer, rute, status, timestamp proses, petugas, catatan |
| `stock_transfer_items` | SKU, qty kirim, qty diterima, selisih, satuan, alasan selisih |
| `warehouses` | Nama, kode, tipe gudang, status aktif |
| `items` | SKU, nama item, kategori, status bundle |
| `categories` | Analisis transfer dan selisih per kategori |
| `item_units` | Nama satuan dan konversi ke satuan dasar |
| `stock_mutations` | Audit pergerakan stok saat ship dan receive |
| `item_stocks` | Posisi stok aktual per item per gudang |
| `item_warehouse_settings` | Safety stock dan lokasi item per gudang |
| `users` | Produktivitas dan akuntabilitas pembuat/pengirim/penerima |
| `activity_logs` | Audit aktivitas aplikasi jika diperlukan |

Relasi utama:

```text
stock_transfers
  -> stock_transfer_items
  -> source warehouse
  -> destination warehouse
  -> creator / shipper / receiver / canceller

stock_transfer_items
  -> item
  -> sending unit
  -> receiving unit

stock_mutations
  -> source_type = transfer
  -> source_id = stock_transfers.id
```

## 4. Dashboard Utama yang Direkomendasikan

### A. Dashboard Kinerja Transfer Gudang

**Tujuan:** memberikan gambaran harian dan periodik kepada kepala gudang dan manajemen.

Kartu KPI:

| KPI | Definisi |
|---|---|
| Total transfer | Jumlah dokumen selain `cancelled` pada periode |
| Transfer dikirim | Jumlah transfer dengan `shipped_at` pada periode |
| Transfer diterima | Jumlah status `received` + `received_with_discrepancy` |
| Transfer in-transit | Jumlah status `shipped` sampai saat laporan dibuka |
| Unit in-transit | Total `qty_base` dari transfer berstatus `shipped` |
| Penerimaan lengkap | Persentase transfer berstatus `received` dari semua transfer selesai |
| Discrepancy rate dokumen | Transfer berselisih / seluruh transfer selesai |
| Discrepancy rate unit | Total selisih unit dasar / total unit dasar yang dikirim dan sudah diterima |
| Rata-rata lead time | Rata-rata `received_at - shipped_at` |
| Draft tertunda | Draft yang belum dikirim melebihi batas umur operasional |

Visual:

- tren jumlah transfer per hari/minggu;
- tren unit dikirim, diterima, dan selisih;
- distribusi status transfer;
- rata-rata lead time per rute;
- 10 SKU dengan selisih terbesar;
- 10 transfer in-transit tertua;
- matriks rute gudang asal ke gudang tujuan.

Filter:

- tanggal dokumen;
- tanggal kirim;
- tanggal terima;
- gudang asal;
- gudang tujuan;
- tipe gudang;
- status;
- kategori;
- SKU;
- pembuat, pengirim, atau penerima.

### B. Laporan Transfer Belum Selesai

**Tujuan:** menjadi daftar kerja harian, bukan hanya laporan historis.

Isi:

- kode transfer;
- gudang asal dan tujuan;
- status;
- umur draft atau umur in-transit;
- jumlah SKU;
- total unit dasar;
- waktu transaksi dan waktu kirim;
- pembuat dan pengirim;
- indikator prioritas.

Definisi umur:

```text
draft_age_hours = sekarang - created_at
transit_age_hours = sekarang - shipped_at
```

Prioritas dapat ditandai:

- merah: lebih dari 48 jam;
- kuning: 24 sampai 48 jam;
- hijau: kurang dari 24 jam.

Angka 24/48 jam masih merupakan aturan tampilan awal, bukan SLA resmi, karena schema belum
menyimpan target SLA.

### C. Laporan Akurasi Penerimaan dan Selisih

**Tujuan:** mengidentifikasi kehilangan, kekurangan isi, salah hitung, atau masalah proses.

KPI:

```text
qty_discrepancy_base = qty_base - qty_received_base

document_discrepancy_rate =
  transfer_selesai_berselisih / seluruh_transfer_selesai * 100

unit_discrepancy_rate =
  total_qty_discrepancy_base / total_qty_base_transfer_selesai * 100

fill_rate =
  total_qty_received_base / total_qty_base_transfer_selesai * 100
```

Breakdown:

- per gudang asal;
- per gudang tujuan;
- per pasangan rute;
- per SKU dan kategori;
- per pengirim;
- per penerima;
- per minggu atau bulan;
- berdasarkan teks alasan selisih.

Catatan: alasan selisih saat ini berupa teks bebas. Klasifikasi penyebab masih perlu
dilakukan manual atau dengan pencocokan kata sampai tersedia master alasan.

### D. Laporan Lead Time Transfer

**Tujuan:** mengukur kecepatan eksekusi proses internal.

Metrik:

```text
draft_to_ship_hours = shipped_at - created_at
ship_to_receive_hours = received_at - shipped_at
end_to_end_hours = received_at - created_at
document_to_receive_hours = received_at - transacted_at
```

Statistik yang ditampilkan:

- rata-rata;
- median;
- persentil ke-90;
- minimum dan maksimum;
- jumlah transfer selesai;
- jumlah transfer yang melewati ambang operasional.

Median dan persentil ke-90 lebih berguna daripada rata-rata saja karena beberapa transfer
yang sangat terlambat dapat mengubah rata-rata secara signifikan.

### E. Laporan Arus Transfer per Gudang

**Tujuan:** melihat gudang yang menjadi pemasok, penerima, atau bottleneck.

KPI per gudang:

- transfer keluar;
- transfer masuk;
- unit dasar keluar;
- unit dasar diterima;
- unit masih dalam perjalanan keluar;
- unit masih dalam perjalanan masuk;
- selisih pada transfer keluar;
- selisih pada transfer masuk;
- rata-rata waktu kirim dan terima.

Gunakan `qty_base` untuk membandingkan item yang dikirim sebagai PCS, SET, DUS, BOX, atau
KOLI. Angka unit dari SKU berbeda tidak boleh disebut nilai persediaan karena belum ada
harga atau biaya.

### F. Laporan Transfer untuk Rebalancing Stok

**Tujuan:** membantu menentukan item yang sebaiknya dipindahkan antar gudang.

Data yang tersedia:

```text
available_stock = item_stocks.stock
safety_stock = item_warehouse_settings.safety_stock
surplus = max(available_stock - safety_stock, 0)
shortage = max(safety_stock - available_stock, 0)
```

Rekomendasi kandidat:

- gudang tujuan memiliki `shortage > 0`;
- gudang lain memiliki `surplus > 0` untuk SKU yang sama;
- kuantitas rekomendasi awal adalah nilai minimum antara surplus dan shortage;
- jika gudang bulk terlibat, jumlah harus dibulatkan sesuai konversi satuan koli.

Keterbatasan: ini baru **rebalancing terhadap safety stock**, belum demand forecasting.
Schema belum menyimpan target hari persediaan atau proyeksi permintaan per gudang.

### G. Laporan Rekonsiliasi Transfer dengan Mutasi Stok

**Tujuan:** mendeteksi ketidaksesuaian antara dokumen transfer dan ledger stok.

Kontrol yang perlu diperiksa:

- setiap item transfer yang sudah dikirim memiliki satu mutasi `transfer/ship`;
- qty mutasi keluar sama dengan `stock_transfer_items.qty_base`;
- setiap item dengan qty diterima lebih dari nol memiliki mutasi `transfer/receive`;
- qty mutasi masuk sama dengan `qty_received_base`;
- warehouse mutasi ship sama dengan gudang asal;
- warehouse mutasi receive sama dengan gudang tujuan;
- transfer draft/cancelled tidak memiliki mutasi transfer;
- `stock_after - stock_before` sesuai arah dan qty mutasi.

Laporan ini sebaiknya menampilkan hanya exception agar mudah ditindaklanjuti.

## 5. Prioritas Implementasi

### Prioritas 1: Operasional Harian

1. Laporan Transfer Belum Selesai.
2. Dashboard Kinerja Transfer Gudang.
3. Laporan Akurasi Penerimaan dan Selisih.

Alasan: seluruh data sudah tersedia dan ketiga laporan langsung membantu pengendalian
barang yang tertahan, barang dalam perjalanan, serta selisih penerimaan.

### Prioritas 2: Evaluasi Kinerja

1. Laporan Lead Time Transfer.
2. Laporan Arus Transfer per Gudang.
3. Laporan Rekonsiliasi Transfer dengan Mutasi Stok.

Alasan: laporan ini membantu evaluasi proses, kapasitas gudang, dan integritas stok.

### Prioritas 3: Pengambilan Keputusan

1. Laporan Rebalancing Stok.
2. Forecast kebutuhan transfer.
3. Analisis biaya dan nilai selisih.

Prioritas 3 membutuhkan penguatan master data dan aturan bisnis.

## 6. Contoh Dataset Detail Laporan

Satu baris sebaiknya mewakili satu item dalam satu transfer.

Kolom yang disarankan:

| Kolom | Sumber/Formula |
|---|---|
| Transfer code | `stock_transfers.code` |
| Document date | `stock_transfers.transacted_at` |
| Created time | `stock_transfers.created_at` |
| Shipped time | `stock_transfers.shipped_at` |
| Received time | `stock_transfers.received_at` |
| Status | `stock_transfers.status` |
| Source warehouse | join `warehouses` |
| Destination warehouse | join `warehouses` |
| SKU | `items.sku` |
| Item name | `items.name` |
| Category | join `categories` |
| Sent qty input | `stock_transfer_items.qty_input` |
| Sent unit | join `item_units` |
| Sent qty base | `stock_transfer_items.qty_base` |
| Received qty input | `qty_received_unit` |
| Received unit | join `received_unit_id` |
| Received qty base | `qty_received_base` |
| Discrepancy qty base | `qty_discrepancy_base` |
| Fill rate | received base / sent base |
| Discrepancy reason | item/header discrepancy note |
| Creator | join `created_by` |
| Shipper | join `shipped_by` |
| Receiver | join `received_by` |
| Transit hours | received/shipped timestamp calculation |

## 7. Aturan Definisi Angka

Untuk mencegah perbedaan angka antar halaman:

1. Semua volume lintas satuan menggunakan `qty_base`.
2. Transfer selesai adalah status `received` atau `received_with_discrepancy`.
3. Transfer aktif adalah `draft` atau `shipped`.
4. Transfer in-transit hanya status `shipped`.
5. Transfer cancelled dikeluarkan dari volume operasional, tetapi tetap masuk laporan
   pembatalan.
6. Discrepancy rate dokumen dan discrepancy rate unit harus ditampilkan sebagai dua KPI
   berbeda.
7. Durasi proses memakai timestamp sistem, bukan hanya tanggal dokumen.
8. Agregasi default menggunakan tanggal kirim untuk volume keluar dan tanggal terima
   untuk volume masuk.
9. Zona waktu laporan harus mengikuti konfigurasi aplikasi/gudang dan ditampilkan jelas.

## 8. Gap Schema dan Rekomendasi Penambahan Data

### Data penting yang belum tersedia

| Kebutuhan | Dampak |
|---|---|
| Target SLA per rute | Tidak dapat mengukur on-time delivery secara resmi |
| Kode alasan selisih | Root cause hanya dapat dianalisis dari teks bebas |
| Harga pokok atau nilai item | Tidak dapat menghitung nilai stok in-transit dan kerugian |
| Nomor kendaraan/pengemudi | Tidak dapat menganalisis performa transportasi |
| Nomor surat jalan transfer | Audit dokumen fisik belum lengkap |
| Waktu approval | Tidak dapat memisahkan waktu persetujuan dari proses gudang |
| Status parsial/close shortage | Penerimaan berselisih langsung dianggap selesai |
| Bukti foto atau lampiran | Investigasi selisih tidak memiliki bukti terstruktur |

### Kolom/master yang disarankan

```text
transfer_reason_codes
- id
- code
- name
- category
- is_active

warehouse_transfer_slas
- source_warehouse_id
- destination_warehouse_id
- target_hours

stock_transfers
- approved_at
- approved_by
- expected_arrival_at
- vehicle_no
- driver_name
- delivery_document_no

stock_transfer_items
- discrepancy_reason_code_id
- discrepancy_resolution
- resolved_at
- resolved_by
```

Untuk analisis nilai, lebih aman menyimpan snapshot biaya pada item transaksi atau ledger
biaya, bukan hanya membaca harga master terbaru.

## 9. Pemeriksaan Kualitas Data

Jalankan kontrol berikut secara berkala:

- `source_warehouse_id` tidak sama dengan `destination_warehouse_id`;
- `qty_base = qty_input * conversion_qty`;
- `qty_received_base <= qty_base`;
- `qty_discrepancy_base = qty_base - qty_received_base`;
- transfer berselisih memiliki alasan per item;
- timestamp berurutan: created, shipped, received;
- unit pengiriman memang milik item yang sama;
- satu transfer tidak memiliki item duplikat;
- status dan timestamp konsisten;
- mutasi transfer tidak ganda berdasarkan idempotency key;
- stok pada gudang bulk tetap kelipatan satuan koli yang valid.

Catatan teknis: schema saat ini tidak memiliki check constraint untuk memastikan gudang
asal dan tujuan berbeda atau memastikan urutan timestamp. Validasi tersebut berada pada
aplikasi, sehingga laporan exception tetap penting.

## 10. Rancangan Halaman Laporan

Halaman yang disarankan:

```text
Laporan
  -> Dashboard Transfer Gudang
  -> Transfer Belum Selesai
  -> Akurasi & Selisih Transfer
  -> Lead Time Transfer
  -> Rekonsiliasi Transfer
```

Fitur umum:

- filter tanggal dan gudang;
- pencarian kode transfer/SKU;
- kartu KPI;
- grafik tren;
- tabel detail dengan drill-down;
- export Excel;
- penanda transfer terlambat;
- link langsung ke detail transfer dan mutasi stok.

## 11. Kesimpulan

Dengan schema saat ini, aplikasi sudah mampu menghasilkan laporan yang kuat untuk:

- monitoring transfer aktif;
- pengukuran lead time;
- pengukuran fill rate dan discrepancy;
- analisis arus antar gudang;
- rekomendasi rebalancing berbasis safety stock;
- rekonsiliasi dokumen transfer dengan mutasi stok.

Implementasi terbaik dimulai dari laporan transfer belum selesai dan discrepancy karena
keduanya paling langsung mengurangi risiko stok hilang, transfer terlambat, dan kekurangan
stok pada gudang tujuan.
