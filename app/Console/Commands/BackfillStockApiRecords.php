<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Warehouse;
use App\Support\StockApiSyncService;
use Illuminate\Console\Command;

class BackfillStockApiRecords extends Command
{
    protected $signature = 'stock-api:backfill {--warehouse= : Kode gudang tertentu (opsional)}';
    protected $description = 'Mengisi ulang data sinkronisasi API stok tanpa mengubah saldo stok';

    public function handle(): int
    {
        $warehouses = Warehouse::where('is_active', true);
        if ($code = $this->option('warehouse')) {
            $warehouses->where('code', $code);
        }
        $warehouses = $warehouses->get();
        if ($warehouses->isEmpty()) {
            $this->error('Gudang aktif tidak ditemukan.');
            return self::FAILURE;
        }

        foreach ($warehouses as $warehouse) {
            $this->info("Sinkronisasi {$warehouse->code}...");
            Item::query()->orderBy('id')->chunkById(200, function ($items) use ($warehouse) {
                foreach ($items as $item) {
                    StockApiSyncService::syncItem($warehouse->id, $item->id);
                }
            });
        }
        $this->info('Backfill API stok selesai.');
        return self::SUCCESS;
    }
}
