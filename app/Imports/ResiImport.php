<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ResiImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /** @var array<string,array{id_pesanan:string,no_resi:?string,kurir:?string,tanggal_pesanan:string,catatan_pembeli:?string,items:array<string,array{sku:string,qty:int}>}> */
    public array $groups = [];
    public bool $hasBuyerNotesHeader = false;
    /** @var array<int,string> */
    private array $requiredHeaders = [
        'id_pesanan',
        'sku',
        'jumlah',
        'tanggal_pembuatan',
    ];

    public function collection(Collection $rows)
    {
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'file' => 'File kosong',
            ]);
        }

        $first = $rows->first();
        $headersRaw = array_keys($first?->toArray() ?? []);
        $headers = array_map(fn ($h) => $this->normalizeKey((string) $h), $headersRaw);
        $this->hasBuyerNotesHeader = in_array('catatan_pembeli', $headers, true);
        $missing = array_diff($this->requiredHeaders, $headers);
        if (!empty($missing)) {
            $detected = implode(', ', array_filter($headers));
            throw ValidationException::withMessages([
                'file' => 'Header wajib: ID Pesanan, SKU, Jumlah, Tanggal Pembuatan. '
                    .'AWB/No. Tracking dan Kurir opsional. Pastikan header berada di baris pertama. '
                    .($detected !== '' ? 'Header terdeteksi: '.$detected : ''),
            ]);
        }

        $errors = [];
        $rowIndex = 1;
        foreach ($rows as $row) {
            $rowIndex++;
            $rowData = $this->normalizeRow($row);
            $idPesanan = trim((string) ($rowData['id_pesanan'] ?? ''));
            $noResi = trim((string) ($rowData['awb_no_tracking'] ?? ''));
            $kurir = trim((string) ($rowData['kurir'] ?? ''));
            $catatanPembeli = $this->hasBuyerNotesHeader
                ? trim((string) ($rowData['catatan_pembeli'] ?? ''))
                : '';
            $sku = trim((string) ($rowData['sku'] ?? ''));
            $qty = $this->parseQty($rowData['jumlah'] ?? null);
            $tanggalPesanan = trim((string) ($rowData['tanggal_pembuatan'] ?? ''));

            if ($idPesanan === '' || $sku === '' || $tanggalPesanan === '') {
                $errors[] = "Baris {$rowIndex}: ID Pesanan, SKU, dan Tanggal Pembuatan wajib diisi";
                continue;
            }

            if ($qty <= 0) {
                $errors[] = "Baris {$rowIndex}: jumlah tidak valid untuk SKU {$sku}";
                continue;
            }

            $groupKey = $idPesanan;
            if (!isset($this->groups[$groupKey])) {
                $group = [
                    'id_pesanan' => $idPesanan,
                    'no_resi' => $noResi !== '' ? $noResi : null,
                    'kurir' => $kurir !== '' ? $kurir : null,
                    'tanggal_pesanan' => $tanggalPesanan,
                    'items' => [],
                ];
                if ($this->hasBuyerNotesHeader) {
                    $group['catatan_pembeli'] = $catatanPembeli !== '' ? $catatanPembeli : null;
                }
                $this->groups[$groupKey] = $group;
            } elseif ($this->groups[$groupKey]['no_resi'] === null && $noResi !== '') {
                $this->groups[$groupKey]['no_resi'] = $noResi;
            }
            if ($this->groups[$groupKey]['kurir'] === null && $kurir !== '') {
                $this->groups[$groupKey]['kurir'] = $kurir;
            }
            if ($this->hasBuyerNotesHeader && ($this->groups[$groupKey]['catatan_pembeli'] ?? null) === null && $catatanPembeli !== '') {
                $this->groups[$groupKey]['catatan_pembeli'] = $catatanPembeli;
            }

            if (!isset($this->groups[$groupKey]['items'][$sku])) {
                $this->groups[$groupKey]['items'][$sku] = [
                    'sku' => $sku,
                    'qty' => $qty,
                ];
            } else {
                $this->groups[$groupKey]['items'][$sku]['qty'] += $qty;
            }
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

    private function parseQty($raw): int
    {
        if ($raw === null || $raw === '') {
            return 0;
        }
        $value = is_numeric($raw) ? (int) $raw : (int) preg_replace('/[^0-9\-]/', '', (string) $raw);
        return $value > 0 ? $value : 0;
    }

    private function normalizeRow($row): array
    {
        $data = [];
        foreach (($row instanceof Collection ? $row->toArray() : (array) $row) as $key => $value) {
            $normKey = $this->normalizeKey((string) $key);
            if ($normKey === '') {
                continue;
            }
            $data[$normKey] = $value;
        }
        return $data;
    }

    private function normalizeKey(string $key): string
    {
        $key = ltrim($key, "\xEF\xBB\xBF");
        $key = trim($key);
        if ($key === '') {
            return '';
        }
        $key = mb_strtolower($key);
        $key = preg_replace('/[^\p{L}\p{N}]+/u', '_', $key);
        $key = trim($key, '_');
        if ($key === 'awbno_tracking') {
            return 'awb_no_tracking';
        }
        if (in_array($key, ['kurir', 'courier', 'ekspedisi', 'expedisi', 'jasa_kurir'], true)) {
            return 'kurir';
        }
        if (in_array($key, ['catatan_pembeli', 'buyer_note', 'buyer_notes', 'buyer_remark', 'buyer_remarks', 'customer_note', 'customer_notes'], true)) {
            return 'catatan_pembeli';
        }
        return $key;
    }
}
