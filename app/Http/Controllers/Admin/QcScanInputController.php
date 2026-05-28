<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class QcScanInputController extends Controller
{
    public function index()
    {
        $roles = auth()->user()?->roles()->pluck('slug') ?? collect();
        $isLimitedQcScanUser = $roles->contains('picker')
            && !$roles->contains('packer')
            && !$roles->contains('admin-scan')
            && $roles->diff(['picker', 'packer', 'admin-scan'])->isEmpty();

        return view('admin.outbound.qc-scan.index', [
            'today' => now()->toDateString(),
            'isLimitedQcScanUser' => $isLimitedQcScanUser,
            'routes' => [
                'dashboard' => route('admin.dashboard'),
                'pickerTransit' => route('admin.inventory.picker-transit.index'),
                'pickingList' => route('admin.inventory.picking-list.index'),

                'qcCurrent' => route('qc.current'),
                'qcStart' => route('qc.start'),
                'qcScanItem' => route('qc.scan-item'),
                'qcSearchItems' => route('qc.items.search'),

                // Picking list reference (mobile JSON endpoint)
                'pickingListData' => route('picker.picking-list.data'),

                // Resi lookup & record for QC scan flow
                'qcResiLookup'  => route('qc.resi-lookup'),
                'qcResiRecord'  => route('qc.resi-record'),
            ],
        ]);
    }
}
