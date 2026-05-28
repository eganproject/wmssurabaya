<?php

namespace App\Imports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StockAdjustmentsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<int,array{item_id:int,direction:string,qty:int,note:?string}> */
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
                'file' => 'Header wajib: sku, qty, direction (opsional: note, item_note, transacted_at)',
            ]);
        }
        $qtyKey = $this->detectQtyKey($headers);
        if ($qtyKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header qty wajib (gunakan: qty/quantity/jumlah/stok/stock)',
            ]);
        }
        $directionKey = null;
        if (in_array('direction', $headers, true)) {
            $directionKey = 'direction';
        } elseif (in_array('arah', $headers, true)) {
            $directionKey = 'arah';
        } else {
            throw ValidationException::withMessages([
                'file' => 'Header direction/arah wajib (isi: in/out atau tambah/kurangi)',
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
            if ($qty <= 0) {
                $errors[] = "Baris {$rowIndex}: qty tidak valid untuk SKU {$sku}";
                continue;
            }

            $directionRaw = trim((string) ($row[$directionKey] ?? ''));
            $direction = $this->normalizeDirection($directionRaw);
            if ($direction === null) {
                $errors[] = "Baris {$rowIndex}: direction tidak valid untuk SKU {$sku}";
                continue;
            }

            $note = trim((string) ($row['note'] ?? ''));
            $itemNote = trim((string) ($row['item_note'] ?? $row['note_item'] ?? ''));
            $transactedAt = trim((string) ($row['transacted_at'] ?? $row['tanggal'] ?? ''));

            if ($this->note === null && $note !== '') {
                $this->note = $note;
            }
            if ($this->transacted_at === null && $transactedAt !== '') {
                $this->transacted_at = $transactedAt;
            }

            $itemId = (int) $skuMap[$sku];
            if (!isset($itemsById[$itemId])) {
                $itemsById[$itemId] = [
                    'item_id' => $itemId,
                    'direction' => $direction,
                    'qty' => $qty,
                    'note' => $itemNote !== '' ? $itemNote : null,
                ];
            } else {
                if ($itemsById[$itemId]['direction'] !== $direction) {
                    $errors[] = "Baris {$rowIndex}: direction berbeda untuk SKU {$sku}";
                    continue;
                }
                $itemsById[$itemId]['qty'] += $qty;
                if ($itemNote !== '' && empty($itemsById[$itemId]['note'])) {
                    $itemsById[$itemId]['note'] = $itemNote;
                }
            }
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
        foreach (['qty', 'quantity', 'jumlah', 'stok', 'stock'] as $key) {
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
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
    }

    private function normalizeDirection(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return null;
        }
        $map = [
            'in' => 'in',
            'out' => 'out',
            'tambah' => 'in',
            'masuk' => 'in',
            'plus' => 'in',
            '+' => 'in',
            'kurangi' => 'out',
            'keluar' => 'out',
            'minus' => 'out',
            '-' => 'out',
        ];
        return $map[$value] ?? null;
    }
}
