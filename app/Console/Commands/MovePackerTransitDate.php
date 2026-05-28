<?php

namespace App\Console\Commands;

use App\Models\PackerTransitHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MovePackerTransitDate extends Command
{
    protected $signature = 'packer-transit:move-date
        {from : Tanggal asal (YYYY-MM-DD)}
        {to : Tanggal tujuan (YYYY-MM-DD)}
        {--status=selesai : Status yang dipindahkan (selesai|menunggu scan out|all)}
        {--apply : Eksekusi perubahan (default hanya preview)}';

    protected $description = 'Memindahkan tanggal (created_at/updated_at) data packer transit berdasarkan status.';

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

        $status = $this->normalizeStatus($this->option('status'));
        if ($status === null) {
            $this->error('Status tidak valid. Gunakan: selesai, menunggu scan out, atau all.');
            return Command::FAILURE;
        }

        $query = PackerTransitHistory::query()->whereDate('created_at', $from);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $rows = $query->get(['id', 'id_pesanan', 'no_resi', 'status', 'created_at', 'updated_at']);
        $total = $rows->count();
        $this->info("Packer transit ditemukan: {$total} baris.");
        $this->info("Dari {$from} -> {$to} | Status: {$status}");
        $this->renderPreviewDetail($rows);

        if (!$this->option('apply')) {
            $this->warn('Preview saja. Jalankan ulang dengan --apply untuk eksekusi.');
            return Command::SUCCESS;
        }

        $fromDate = Carbon::parse($from);
        $toDate = Carbon::parse($to);
        $dayDiff = $fromDate->diffInDays($toDate, false);

        DB::transaction(function () use ($rows, $dayDiff) {
            foreach ($rows as $row) {
                $createdAt = $row->created_at ? Carbon::parse($row->created_at) : null;
                $updatedAt = $row->updated_at ? Carbon::parse($row->updated_at) : null;
                if ($createdAt) {
                    $createdAt = $createdAt->copy()->addDays($dayDiff);
                    $row->created_at = $createdAt;
                }
                if ($updatedAt) {
                    $updatedAt = $updatedAt->copy()->addDays($dayDiff);
                    $row->updated_at = $updatedAt;
                }
                $row->save();
            }
        });

        $this->info('Selesai memperbaiki data packer transit.');
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

    private function normalizeStatus(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 'selesai';
        }
        if (in_array($value, ['selesai', 'menunggu scan out', 'all'], true)) {
            return $value;
        }
        return null;
    }

    private function renderPreviewDetail($rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $limit = 50;
        $preview = $rows->take($limit);
        $this->newLine();
        $this->info('Detail Preview (maks 50 baris):');
        $this->table(
            ['ID Pesanan', 'No Resi', 'Status', 'Created At'],
            $preview->map(fn ($row) => [
                $row->id_pesanan ?? '-',
                $row->no_resi ?? '-',
                $row->status ?? '-',
                $row->created_at?->format('Y-m-d H:i') ?? '-',
            ])->all()
        );

        if ($rows->count() > $limit) {
            $this->warn('Preview dipotong. Total baris: '.$rows->count());
        }
    }
}
