<?php

namespace App\Imports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class InboundReturnsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<string,array{ref_no:?string,note:?string,transacted_at:?string,items:array<int,array{item_id:int,qty:int,qty_received:int,qty_good:int,qty_damaged:int,qty_missing:int,note:?string}>}> */
    public array $groups = [];

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
                'file' => 'Header wajib: sku, qty_diterima, qty_bagus, qty_rusak, qty_hilang (opsional: ref_no, note, item_note, transacted_at)',
            ]);
        }
        $receivedKey = $this->detectFirstKey($headers, ['qty_diterima', 'qty_received', 'diterima', 'received', 'qty']);
        if ($receivedKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header qty_diterima wajib (bisa gunakan: qty_diterima/qty_received/diterima/received/qty)',
            ]);
        }
        $goodKey = $this->detectFirstKey($headers, ['qty_bagus', 'qty_good', 'bagus', 'good']);
        $damagedKey = $this->detectFirstKey($headers, ['qty_rusak', 'qty_damaged', 'rusak', 'damaged']);
        $missingKey = $this->detectFirstKey($headers, ['qty_hilang', 'qty_missing', 'hilang', 'missing', 'lost']);
        if ($goodKey === null || $damagedKey === null || $missingKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header qty_bagus, qty_rusak, dan qty_hilang wajib agar qty diterima bisa divalidasi balance.',
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
            $receivedQty = $this->parseQty($row, $receivedKey);
            $goodQty = $this->parseQty($row, $goodKey);
            $damagedQty = $this->parseQty($row, $damagedKey);
            $missingQty = $this->parseQty($row, $missingKey);

            if ($receivedQty <= 0) {
                $errors[] = "Baris {$rowIndex}: qty diterima tidak valid untuk SKU {$sku}";
                continue;
            }
            if ($goodQty + $damagedQty + $missingQty !== $receivedQty) {
                $errors[] = "Baris {$rowIndex}: qty bagus + qty rusak + qty hilang harus sama dengan qty diterima untuk SKU {$sku}";
                continue;
            }

            $ref = trim((string) ($row['ref_no'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));
            $itemNote = trim((string) ($row['item_note'] ?? $row['note_item'] ?? ''));
            $transactedAt = trim((string) ($row['transacted_at'] ?? $row['tanggal'] ?? ''));

            $groupKey = $ref !== '' ? $ref : '__default__';
            if (!isset($this->groups[$groupKey])) {
                $this->groups[$groupKey] = [
                    'ref_no' => $ref !== '' ? $ref : null,
                    'note' => $note !== '' ? $note : null,
                    'transacted_at' => $transactedAt !== '' ? $transactedAt : null,
                    'items' => [],
                ];
            } else {
                if ($this->groups[$groupKey]['note'] === null && $note !== '') {
                    $this->groups[$groupKey]['note'] = $note;
                }
                if ($this->groups[$groupKey]['transacted_at'] === null && $transactedAt !== '') {
                    $this->groups[$groupKey]['transacted_at'] = $transactedAt;
                }
            }

            $itemId = (int) $skuMap[$sku];
            if (!isset($this->groups[$groupKey]['items'][$itemId])) {
                $this->groups[$groupKey]['items'][$itemId] = [
                    'item_id' => $itemId,
                    'qty' => $receivedQty,
                    'qty_received' => $receivedQty,
                    'qty_good' => $goodQty,
                    'qty_damaged' => $damagedQty,
                    'qty_missing' => $missingQty,
                    'note' => $itemNote !== '' ? $itemNote : null,
                ];
            } else {
                $this->groups[$groupKey]['items'][$itemId]['qty'] += $receivedQty;
                $this->groups[$groupKey]['items'][$itemId]['qty_received'] += $receivedQty;
                $this->groups[$groupKey]['items'][$itemId]['qty_good'] += $goodQty;
                $this->groups[$groupKey]['items'][$itemId]['qty_damaged'] += $damagedQty;
                $this->groups[$groupKey]['items'][$itemId]['qty_missing'] += $missingQty;
                if ($itemNote !== '' && empty($this->groups[$groupKey]['items'][$itemId]['note'])) {
                    $this->groups[$groupKey]['items'][$itemId]['note'] = $itemNote;
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

        foreach ($this->groups as $key => $group) {
            $items = array_values($group['items'] ?? []);
            if (empty($items)) {
                unset($this->groups[$key]);
                continue;
            }
            $this->groups[$key]['items'] = $items;
        }

        if (empty($this->groups)) {
            throw ValidationException::withMessages([
                'file' => 'Tidak ada data valid untuk diimport',
            ]);
        }
    }

    private function detectFirstKey(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $key) {
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
}
