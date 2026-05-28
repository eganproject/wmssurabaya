<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;
use App\Models\PackerScanException;
use App\Models\PickingList;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PickingListMobileController extends Controller
{
    public function index()
    {
        return view('picker.picking-list', [
            'routes' => [
                'dashboard' => route('picker.dashboard'),
                'data' => route('picker.picking-list.data'),
                'logout' => route('logout'),
            ],
            'today' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $date = $request->input('date') ?: now()->toDateString();
        try {
            $date = Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            $date = now()->toDateString();
        }

        $query = PickingList::query()
            ->with('item')
            ->where('list_date', $date)
            ->orderBy('sku');
        $this->applyPackerExceptionFilter($query);

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQ) use ($search) {
                        $itemQ->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $items = $query->get()->map(function ($row) {
            return [
                'sku' => $row->sku ?? '-',
                'name' => $row->item?->name ?? '-',
                'qty' => (int) $row->qty,
                'remaining_qty' => (int) $row->remaining_qty,
            ];
        })->values();

        return response()->json([
            'date' => $date,
            'items' => $items,
        ]);
    }

    private function applyPackerExceptionFilter($query): void
    {
        $query->whereNotIn('sku', PackerScanException::query()->select('sku'));
    }
}
