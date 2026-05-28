<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MovePackerScanDates extends Command
{
    protected $signature = 'packer-scan:move-date
        {from : Tanggal asal (YYYY-MM-DD)}
        {to : Tanggal tujuan (YYYY-MM-DD)}
        {--only=both : Pilih tabel (packer|scanout|both)}
        {--apply : Eksekusi perubahan (default hanya preview)}';

    protected $description = 'Memindahkan tanggal scan packer (packer_resi_scans) dan scan out (packer_scan_outs).';

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

        $only = $this->normalizeOnly($this->option('only'));
        if ($only === null) {
            $this->error('Nilai --only tidak valid. Gunakan: packer, scanout, atau both.');
            return Command::FAILURE;
        }

        $dayDiff = Carbon::parse($from)->diffInDays(Carbon::parse($to), false);

        if ($only === 'packer' || $only === 'both') {
            $this->processTable(
                table: 'packer_resi_scans',
                label: 'Packer Scan (packer_resi_scans)',
                from: $from,
                to: $to,
                dayDiff: $dayDiff,
                apply: (bool) $this->option('apply')
            );
        }

        if ($only === 'scanout' || $only === 'both') {
            $this->processTable(
                table: 'packer_scan_outs',
                label: 'Scan Out (packer_scan_outs)',
                from: $from,
                to: $to,
                dayDiff: $dayDiff,
                apply: (bool) $this->option('apply')
            );
        }

        return Command::SUCCESS;
    }

    private function processTable(string $table, string $label, string $from, string $to, int $dayDiff, bool $apply): void
    {
        $base = DB::table("{$table} as s")
            ->leftJoin('resis as r', 'r.id', '=', 's.resi_id')
            ->whereDate('s.scan_date', $from);

        $total = (clone $base)->count();
        $firstScan = (clone $base)->min('s.scanned_at');
        $lastScan = (clone $base)->max('s.scanned_at');

        $this->newLine();
        $this->info("{$label}: {$total} baris | {$from} -> {$to}");
        if ($firstScan || $lastScan) {
            $this->info('Range scan: '.($firstScan ? Carbon::parse($firstScan)->format('Y-m-d H:i') : '-')
                .' s/d '.($lastScan ? Carbon::parse($lastScan)->format('Y-m-d H:i') : '-'));
        }

        $preview = (clone $base)
            ->select([
                's.id',
                'r.id_pesanan',
                'r.no_resi',
                's.scan_code',
                's.scan_date',
                's.scanned_at',
            ])
            ->orderBy('s.scanned_at')
            ->limit(50)
            ->get();

        if ($preview->isNotEmpty()) {
            $this->table(
                ['ID Pesanan', 'No Resi', 'Scan Code', 'Scan Date', 'Scanned At'],
                $preview->map(fn ($row) => [
                    $row->id_pesanan ?? '-',
                    $row->no_resi ?? '-',
                    $row->scan_code ?? '-',
                    $row->scan_date ? Carbon::parse($row->scan_date)->format('Y-m-d') : '-',
                    $row->scanned_at ? Carbon::parse($row->scanned_at)->format('Y-m-d H:i') : '-',
                ])->all()
            );
            if ($total > 50) {
                $this->warn('Preview dipotong. Total baris: '.$total);
            }
        }

        if (!$apply || $total === 0) {
            if (!$apply) {
                $this->warn('Preview saja. Gunakan --apply untuk eksekusi.');
            }
            return;
        }

        DB::transaction(function () use ($table, $from, $to, $dayDiff) {
            DB::table($table)
                ->whereDate('scan_date', $from)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table, $to, $dayDiff) {
                    foreach ($rows as $row) {
                        $scannedAt = $row->scanned_at ? Carbon::parse($row->scanned_at)->addDays($dayDiff) : null;
                        $createdAt = $row->created_at ? Carbon::parse($row->created_at)->addDays($dayDiff) : null;
                        $updatedAt = $row->updated_at ? Carbon::parse($row->updated_at)->addDays($dayDiff) : null;

                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([
                                'scan_date' => $to,
                                'scanned_at' => $scannedAt,
                                'created_at' => $createdAt,
                                'updated_at' => $updatedAt,
                            ]);
                    }
                });
        });

        $this->info("{$label}: selesai dipindahkan.");
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

    private function normalizeOnly(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));
        if ($value === '' || $value === 'both') {
            return 'both';
        }
        if (in_array($value, ['packer', 'scanout'], true)) {
            return $value;
        }
        return null;
    }
}
