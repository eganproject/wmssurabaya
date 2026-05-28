<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\PickerTransitItem;
use App\Models\PickingList;
use App\Models\PickingListException;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MovePickingDate extends Command
{
    protected $signature = 'picking:move-date
        {from : Tanggal asal (YYYY-MM-DD)}
        {to : Tanggal tujuan (YYYY-MM-DD)}
        {--sku= : Batasi SKU tertentu, pisahkan dengan koma}
        {--apply : Eksekusi perubahan (default hanya preview)}';

    protected $description = 'Memindahkan data picking (transit) dari tanggal asal ke tanggal tujuan dan merapikan picking list/exception.';

    public function handle(): int
    {
        $from = $this->parseDate($this->argument('from'));
        $to = $this->parseDate($this->argument('to'));

        if (!$from || !$to) {
            $this->error('Format tanggal tidak valid. Gunakan YYYY-MM-DD.');
            return Command::FAILURE;
        }
        if ($from === $to) {
            $this->error('Tanggal asal dan tujuan tidak boleh sama.');
            return Command::FAILURE;
        }

        $skuFilter = $this->parseSkuOption($this->option('sku'));
        $itemIdsBySku = $this->resolveItemIdsBySku($skuFilter);
        if ($skuFilter && $itemIdsBySku->isEmpty()) {
            $this->error('SKU filter tidak ditemukan di master item.');
            return Command::FAILURE;
        }

        $sourceQuery = PickerTransitItem::query()
            ->whereDate('picked_date', $from);

        if ($itemIdsBySku->isNotEmpty()) {
            $sourceQuery->whereIn('item_id', $itemIdsBySku->values());
        }

        $sourceRows = $sourceQuery->get();
        $totalRows = $sourceRows->count();
        $totalQty = (int) $sourceRows->sum('qty');
        $totalRemaining = (int) $sourceRows->sum('remaining_qty');

        $this->info("Transit ditemukan: {$totalRows} baris, qty {$totalQty}, remaining {$totalRemaining}.");
        $this->info("Dari {$from} -> {$to}".($skuFilter ? ' | SKU: '.implode(', ', $skuFilter) : ' | Semua SKU'));
        $this->renderPreviewDetail($sourceRows);

        if (!$this->option('apply')) {
            $this->warn('Preview saja. Jalankan ulang dengan --apply untuk eksekusi.');
            return Command::SUCCESS;
        }

        DB::transaction(function () use ($from, $to, $sourceRows, $itemIdsBySku) {
            foreach ($sourceRows as $row) {
                $target = PickerTransitItem::query()
                    ->where('item_id', $row->item_id)
                    ->whereDate('picked_date', $to)
                    ->lockForUpdate()
                    ->first();

                if ($target) {
                    $target->qty = (int) $target->qty + (int) $row->qty;
                    $target->remaining_qty = (int) $target->remaining_qty + (int) $row->remaining_qty;
                    if ($row->picked_at && (!$target->picked_at || $row->picked_at->gt($target->picked_at))) {
                        $target->picked_at = $row->picked_at;
                    }
                    $target->save();
                    $row->delete();
                } else {
                    $row->picked_date = $to;
                    $row->save();
                }
            }

            $this->rebuildBalancesForDate($from, $itemIdsBySku);
            $this->rebuildBalancesForDate($to, $itemIdsBySku);
        });

        $this->info('Selesai memperbaiki data.');
        return Command::SUCCESS;
    }

    private function parseDate(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseSkuOption(?string $value): array
    {
        if (!$value) {
            return [];
        }
        return collect(explode(',', $value))
            ->map(fn ($sku) => trim((string) $sku))
            ->filter()
            ->values()
            ->all();
    }

    private function resolveItemIdsBySku(array $skus): Collection
    {
        if (!$skus) {
            return collect();
        }

        $items = Item::query()
            ->whereIn('sku', $skus)
            ->pluck('id', 'sku');

        $missing = array_diff($skus, $items->keys()->all());
        if (!empty($missing)) {
            $this->warn('SKU tidak ditemukan: '.implode(', ', $missing));
        }

        return $items->values();
    }

    private function rebuildBalancesForDate(string $date, Collection $itemIdsFilter): void
    {
        $listQuery = PickingList::query()->where('list_date', $date)->lockForUpdate();
        $exceptionQuery = PickingListException::query()->where('list_date', $date)->lockForUpdate();

        if ($itemIdsFilter->isNotEmpty()) {
            $skus = Item::whereIn('id', $itemIdsFilter)->pluck('sku')->all();
            $listQuery->whereIn('sku', $skus);
            $exceptionQuery->whereIn('sku', $skus);
        }

        $listRows = $listQuery->get()->keyBy('sku');
        $exceptionRows = $exceptionQuery->get()->keyBy('sku');

        $transitQuery = PickerTransitItem::query()->whereDate('picked_date', $date);
        if ($itemIdsFilter->isNotEmpty()) {
            $transitQuery->whereIn('item_id', $itemIdsFilter);
        }
        $transitRows = $transitQuery->get();

        $itemIds = $transitRows->pluck('item_id')->unique()->values();
        $skuMap = $itemIds->isEmpty()
            ? collect()
            : Item::whereIn('id', $itemIds)->pluck('sku', 'id');

        $pickedBySku = [];
        foreach ($transitRows as $row) {
            $sku = $skuMap[$row->item_id] ?? null;
            if (!$sku) {
                continue;
            }
            $pickedBySku[$sku] = ($pickedBySku[$sku] ?? 0) + (int) $row->qty;
        }

        $allSkus = array_values(array_unique(array_merge(
            array_keys($pickedBySku),
            $listRows->keys()->all(),
            $exceptionRows->keys()->all()
        )));

        foreach ($allSkus as $sku) {
            $listRow = $listRows->get($sku);
            $listQty = $listRow ? (int) $listRow->qty : 0;
            $pickedQty = (int) ($pickedBySku[$sku] ?? 0);

            $remaining = max(0, $listQty - $pickedQty);
            $exceptionQty = max(0, $pickedQty - $listQty);

            if ($listRow) {
                $listRow->remaining_qty = $remaining;
                if ($listRow->qty <= 0 && $listRow->remaining_qty <= 0) {
                    $listRow->delete();
                } else {
                    $listRow->save();
                }
            }

            $exceptionRow = $exceptionRows->get($sku);
            if ($exceptionQty > 0) {
                if ($exceptionRow) {
                    $exceptionRow->qty = $exceptionQty;
                    $exceptionRow->save();
                } else {
                    PickingListException::create([
                        'list_date' => $date,
                        'sku' => $sku,
                        'qty' => $exceptionQty,
                    ]);
                }
            } elseif ($exceptionRow) {
                $exceptionRow->delete();
            }
        }
    }

    private function renderPreviewDetail(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $skuMap = Item::whereIn('id', $rows->pluck('item_id')->unique())
            ->pluck('sku', 'id');

        $grouped = [];
        foreach ($rows as $row) {
            $sku = $skuMap[$row->item_id] ?? 'UNKNOWN';
            if (!isset($grouped[$sku])) {
                $grouped[$sku] = [
                    'sku' => $sku,
                    'rows' => 0,
                    'qty' => 0,
                    'remaining' => 0,
                ];
            }
            $grouped[$sku]['rows']++;
            $grouped[$sku]['qty'] += (int) $row->qty;
            $grouped[$sku]['remaining'] += (int) $row->remaining_qty;
        }

        $items = collect($grouped)
            ->sortByDesc('qty')
            ->values();

        $limit = 50;
        $preview = $items->take($limit);
        $this->newLine();
        $this->info('Detail Preview (per SKU):');
        $this->table(
            ['SKU', 'Rows', 'Qty', 'Remaining'],
            $preview->map(fn ($row) => [
                $row['sku'],
                $row['rows'],
                $row['qty'],
                $row['remaining'],
            ])->all()
        );

        if ($items->count() > $limit) {
            $this->warn('Preview dipotong. Total SKU: '.$items->count());
        }
    }
}
