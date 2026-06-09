<?php

namespace App\Imports;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class DamagedGoodsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private const ALLOWED_SOURCES = ['display', 'inbound_return'];

    /** @var array<string,array{source_type:string,source_ref:?string,note:?string,transacted_at:?string,items:array<int,array{item_id:int,qty:int,note:?string}>}> */
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
                'file' => 'Header wajib: sku, qty_input (opsional: source_type, source_ref, note, item_note, transacted_at)',
            ]);
        }
        $qtyKey = $this->detectFirstKey($headers, ['qty_input', 'qty', 'quantity', 'jumlah', 'qty_rusak', 'rusak']);
        if ($qtyKey === null) {
            throw ValidationException::withMessages([
                'file' => 'Header qty_input wajib (qty lama tetap didukung)',
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
            $qty = $this->parseQty($row, $qtyKey);
            if ($qty <= 0) {
                $errors[] = "Baris {$rowIndex}: qty tidak valid untuk SKU {$sku}";
                continue;
            }

            $sourceType = $this->normalizeSource((string) ($row['source_type'] ?? $row['sumber'] ?? ''));
            if ($sourceType === null) {
                $errors[] = "Baris {$rowIndex}: source_type tidak valid untuk SKU {$sku} (gunakan: display / inbound_return)";
                continue;
            }
            $sourceRef = trim((string) ($row['source_ref'] ?? $row['ref'] ?? $row['ref_no'] ?? ''));
            $note = trim((string) ($row['note'] ?? ''));
            $itemNote = trim((string) ($row['item_note'] ?? $row['note_item'] ?? ''));
            $transactedAt = trim((string) ($row['transacted_at'] ?? $row['tanggal'] ?? ''));

            $groupKey = $sourceType.'|'.($sourceRef !== '' ? $sourceRef : '__default__');
            if (!isset($this->groups[$groupKey])) {
                $this->groups[$groupKey] = [
                    'source_type' => $sourceType,
                    'source_ref' => $sourceRef !== '' ? $sourceRef : null,
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
                    'qty' => $qty,
                    'note' => $itemNote !== '' ? $itemNote : null,
                ];
            } else {
                $this->groups[$groupKey]['items'][$itemId]['qty'] += $qty;
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

    private function normalizeSource(string $raw): ?string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return 'display';
        }
        $value = str_replace([' ', '-'], '_', $value);
        $map = [
            'display' => 'display',
            'inbound_return' => 'inbound_return',
            'retur_inbound' => 'inbound_return',
            'inbound' => 'inbound_return',
            'retur' => 'inbound_return',
        ];

        return $map[$value] ?? (in_array($value, self::ALLOWED_SOURCES, true) ? $value : null);
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
