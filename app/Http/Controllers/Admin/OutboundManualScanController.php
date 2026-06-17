<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OutboundItem;
use App\Models\OutboundManualScanLog;
use App\Models\OutboundTransaction;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OutboundManualScanController extends Controller
{
    public function index(int $id)
    {
        $tx = $this->manualTransactionQuery()
            ->with(['items.item', 'items.unit', 'warehouse', 'creator', 'scanCompleter'])
            ->findOrFail($id);

        return view('admin.outbound.manual-scan.index', [
            'transaction' => $tx,
            'rows' => $this->scanRows($tx),
            'scanUrl' => route('admin.outbound.manuals.scan.store', $tx->id),
            'finishUrl' => route('admin.outbound.manuals.scan.finish', $tx->id),
            'detailUrl' => route('admin.outbound.manuals.detail', $tx->id),
        ]);
    }

    public function scan(Request $request, int $id)
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
        ]);

        $sku = trim($validated['sku']);

        return DB::transaction(function () use ($id, $sku) {
            $tx = $this->manualTransactionQuery()
                ->with(['items.item', 'items.unit', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertCanScan($tx);

            $item = Item::where('sku', $sku)->first();
            if (!$item) {
                throw ValidationException::withMessages([
                    'sku' => "SKU {$sku} tidak ditemukan.",
                ]);
            }

            $row = $tx->items->firstWhere('item_id', $item->id);
            if (!$row) {
                throw ValidationException::withMessages([
                    'sku' => "SKU {$sku} tidak ada di outbound manual ini.",
                ]);
            }

            $scanQty = $this->scanQty($tx, $row);
            $scannedQty = (int) OutboundManualScanLog::where('outbound_item_id', $row->id)->sum('qty');
            $plannedQty = (int) $row->qty;

            if ($scannedQty + $scanQty > $plannedQty) {
                $remaining = max(0, $plannedQty - $scannedQty);
                throw ValidationException::withMessages([
                    'sku' => "Scan {$sku} ditolak. Sisa qty {$remaining}, qty per scan {$scanQty}.",
                ]);
            }

            OutboundManualScanLog::create([
                'outbound_transaction_id' => $tx->id,
                'outbound_item_id' => $row->id,
                'item_id' => $row->item_id,
                'scanned_sku' => $sku,
                'qty' => $scanQty,
                'scanned_at' => now(),
                'scanned_by' => auth()->id(),
            ]);

            if (($tx->scan_status ?? 'not_started') === 'not_started') {
                $tx->scan_status = 'in_progress';
                $tx->scan_completed_at = null;
                $tx->scan_completed_by = null;
                $tx->save();
            }

            $tx->load(['items.item', 'items.unit', 'warehouse']);
            $rows = $this->scanRows($tx);

            return response()->json([
                'message' => "SKU {$sku} berhasil discan (+{$scanQty}).",
                'scan_qty' => $scanQty,
                'all_complete' => collect($rows)->every(fn ($scanRow) => $scanRow['remaining_qty'] === 0),
                'summary' => $this->summary($rows),
                'rows' => $rows,
            ]);
        });
    }

    public function finish(int $id)
    {
        return DB::transaction(function () use ($id) {
            $tx = $this->manualTransactionQuery()
                ->with(['items.item', 'items.unit', 'warehouse'])
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertCanScan($tx);

            $rows = $this->scanRows($tx);
            $incomplete = collect($rows)->first(fn ($row) => $row['remaining_qty'] > 0);
            if ($incomplete) {
                throw ValidationException::withMessages([
                    'scan' => "Scan belum lengkap. SKU {$incomplete['sku']} masih kurang {$incomplete['remaining_qty']}.",
                ]);
            }

            $tx->scan_status = 'complete';
            $tx->scan_completed_at = now();
            $tx->scan_completed_by = auth()->id();
            $tx->save();

            return response()->json([
                'message' => 'Scan outbound manual selesai. Transaksi siap di-approve.',
                'summary' => $this->summary($rows),
                'rows' => $rows,
            ]);
        });
    }

    private function manualTransactionQuery()
    {
        return OutboundTransaction::where('type', 'manual');
    }

    private function assertCanScan(OutboundTransaction $tx): void
    {
        if (($tx->status ?? 'pending') === 'approved') {
            throw ValidationException::withMessages([
                'scan' => 'Outbound manual sudah disetujui dan tidak bisa discan.',
            ]);
        }

        if (($tx->scan_status ?? 'not_started') === 'complete') {
            throw ValidationException::withMessages([
                'scan' => 'Scan outbound manual sudah complete.',
            ]);
        }
    }

    private function scanRows(OutboundTransaction $tx): array
    {
        $tx->loadMissing(['items.item', 'items.unit', 'warehouse']);

        $scannedByOutboundItem = OutboundManualScanLog::query()
            ->where('outbound_transaction_id', $tx->id)
            ->select('outbound_item_id', DB::raw('SUM(qty) as scanned_qty'))
            ->groupBy('outbound_item_id')
            ->pluck('scanned_qty', 'outbound_item_id');

        return $tx->items->map(function (OutboundItem $row) use ($tx, $scannedByOutboundItem) {
            $plannedQty = (int) $row->qty;
            $scannedQty = (int) ($scannedByOutboundItem[$row->id] ?? 0);
            $scanQty = $this->scanQty($tx, $row);

            return [
                'outbound_item_id' => $row->id,
                'item_id' => $row->item_id,
                'sku' => $row->item?->sku ?? '-',
                'name' => $row->item?->name ?? '-',
                'unit' => $row->unit?->name ?? 'PCS/SET',
                'planned_qty' => $plannedQty,
                'scanned_qty' => $scannedQty,
                'remaining_qty' => max(0, $plannedQty - $scannedQty),
                'scan_qty' => $scanQty,
                'complete' => $plannedQty > 0 && $scannedQty >= $plannedQty,
            ];
        })->values()->all();
    }

    private function scanQty(OutboundTransaction $tx, OutboundItem $row): int
    {
        $isBulkWarehouse = ($tx->warehouse?->type ?? null) === Warehouse::TYPE_BULK;
        if (!$isBulkWarehouse) {
            return 1;
        }

        return max(1, (int) ($row->conversion_qty ?: 1));
    }

    private function summary(array $rows): array
    {
        $planned = collect($rows)->sum('planned_qty');
        $scanned = collect($rows)->sum('scanned_qty');

        return [
            'planned_qty' => (int) $planned,
            'scanned_qty' => (int) $scanned,
            'remaining_qty' => max(0, (int) $planned - (int) $scanned),
        ];
    }
}
