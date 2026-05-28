<?php

namespace App\Http\Controllers\Picker;

use App\Http\Controllers\Controller;

class PickerDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $roles = $user ? $user->roles()->pluck('slug') : collect();
        $hasPicker = $roles->contains('picker');
        $hasPacker = $roles->contains('packer');
        $hasAdminScan = $roles->contains('admin-scan');
        $hasOtherRoles = $roles->diff(['picker', 'packer', 'admin-scan'])->isNotEmpty();
        $isAdminScanOnly = $hasAdminScan && !$hasPicker && !$hasPacker && !$hasOtherRoles;

        return view('picker.dashboard', [
            'routes' => [
                'opname' => route('opname.index'),
                'picker' => route('admin.outbound.qc-scan.index'),
                'scanOut' => route('admin.outbound.scan-out.index'),
                'scanOutV2' => route('admin.outbound.scan-out.index'),
                'pickingList' => route('picker.picking-list.index'),
                'logout' => route('logout'),
                'desktop' => route('admin.dashboard'),
            ],
            'showPicking' => ($hasPicker || $hasPacker || $hasOtherRoles) && !$isAdminScanOnly,
            'showScanOut' => ($hasAdminScan || $hasOtherRoles) && !$hasPicker && !$hasPacker,
            'showScanOutV2' => ($hasAdminScan || $hasOtherRoles) && !$hasPicker && !$hasPacker,
            'showPickingList' => ($hasPicker || $hasPacker || $hasOtherRoles) && !$isAdminScanOnly,
            'showDesktop' => $hasOtherRoles,
        ]);
    }
}
