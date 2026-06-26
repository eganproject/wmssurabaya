<?php

namespace App\Imports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockOpnamesImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,array{item_id:int,sku:string,counted_qty_input:int,unit:?string,note:?string}> */
    public array $items = [];

    public ?string $note = null;
    public ?string $transacted_at = null;

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headers = array_keys($first?->toArray() ?? []);
        if (!in_array('sku', $headers, true)) {
            throw ValidationException::withMessages([
                'file' => 'Header wajib: sku, counted_qty_input (opsional: unit, note, item_note, transacted_at)',
            ]);
        }

        $qtyKey = $this->detectQtyKey($headers);
        if ($qtyKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header counted_qty_input wajib (alias yang didukung: counted_qty, qty_input, qty, jumlah, stok_fisik)',
            ]);
        }

        $skus = $rows->map(fn ($row) => trim((string) ($row['sku'] ?? '')))
            ->filter()
            ->unique()
            ->values();

        $items = Item::whereIn('sku', $skus)->get(['id', 'sku']);
        $skuMap = $items->pluck('id', 'sku')->all();

        $missing = [];
        $errors = [];
        $rowIndex = 1;
        $itemsById = [];

        foreach ($rows as $row) {
            $rowIndex++;
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }

            if (!isset($skuMap[$sku])) {
                $missing[$sku] = true;
                continue;
            }

            $qty = $this->parseQty($row, $qtyKey);
            if ($qty < 0) {
                $errors[] = "Baris {$rowIndex}: counted_qty_input tidak valid untuk SKU {$sku}";
                continue;
            }

            $note = trim((string) ($row['note'] ?? ''));
            $itemNote = trim((string) ($row['item_note'] ?? $row['note_item'] ?? ''));
            $transactedAt = trim((string) ($row['transacted_at'] ?? $row['tanggal'] ?? ''));
            $unit = trim((string) ($row['unit'] ?? $row['satuan'] ?? ''));

            if ($this->note === null && $note !== '') {
                $this->note = $note;
            }
            if ($this->transacted_at === null && $transactedAt !== '') {
                $this->transacted_at = $transactedAt;
            }

            $itemId = (int) $skuMap[$sku];
            if (isset($itemsById[$itemId])) {
                $errors[] = "Baris {$rowIndex}: SKU {$sku} duplikat dalam file import";
                continue;
            }

            $itemsById[$itemId] = [
                'item_id' => $itemId,
                'sku' => $sku,
                'counted_qty_input' => $qty,
                'unit' => $unit !== '' ? $unit : null,
                'note' => $itemNote !== '' ? $itemNote : null,
            ];
        }

        if (!empty($missing)) {
            $list = implode(', ', array_keys($missing));
            throw ValidationException::withMessages([
                'file' => 'SKU tidak ditemukan: '.$list,
            ]);
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages([
                'file' => implode(' | ', array_slice($errors, 0, 5)),
            ]);
        }

        $this->items = array_values($itemsById);
        if (empty($this->items)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }
    }

    private function detectQtyKey(array $headers): ?string
    {
        foreach (['counted_qty_input', 'counted_qty', 'qty_input', 'qty', 'quantity', 'jumlah', 'stok_fisik', 'stock_opname'] as $key) {
            if (in_array($key, $headers, true)) {
                return $key;
            }
        }

        return null;
    }

    private function parseQty($row, string $key): int
    {
        $raw = null;
        if (is_array($row) && array_key_exists($key, $row)) {
            $raw = $row[$key];
        } elseif ($row instanceof Collection && $row->has($key)) {
            $raw = $row->get($key);
        } elseif (isset($row[$key])) {
            $raw = $row[$key];
        }

        if ($raw === null || $raw === '') {
            return -1;
        }

        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);

        return $value >= 0 ? $value : -1;
    }
}
