<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class PackerScanOutInputController extends Controller
{
    public function csrfToken()
    {
        return response()->json([
            'csrf_token' => csrf_token(),
        ]);
    }

    public function index()
    {
        $roles = auth()->user()?->roles()->pluck('slug') ?? collect();
        $isAdminScanOnly = $roles->contains('admin-scan')
            && !$roles->contains('picker')
            && !$roles->contains('packer')
            && $roles->diff(['picker', 'packer', 'admin-scan'])->isEmpty();
        $isLimitedScanOutUser = ($roles->contains('packer') || $roles->contains('admin-scan'))
            && !$roles->contains('picker')
            && $roles->diff(['picker', 'packer', 'admin-scan'])->isEmpty();

        return view('admin.outbound.scan-out.index', [
            'today' => now()->toDateString(),
            'isAdminScanOnly' => $isAdminScanOnly,
            'isLimitedScanOutUser' => $isLimitedScanOutUser,
            'routes' => [
                'dashboard' => route('admin.dashboard'),
                'scan' => route('admin.outbound.scan-out.scan'),
                'csrfToken' => route('admin.outbound.scan-out.csrf-token'),
                'history' => route('admin.outbound.packer-scan-outs.index'),
                'historyData' => route('admin.outbound.packer-scan-outs.data'),
                'qcTransit' => route('admin.inventory.picker-transit.index'),
                'qcScan' => route('admin.outbound.qc-scan.index'),
            ],
        ]);
    }
}
