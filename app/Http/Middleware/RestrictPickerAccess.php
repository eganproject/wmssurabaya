<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictPickerAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $roles = $user->roles()->pluck('slug');
        $hasPicker = $roles->contains('picker');
        $hasPacker = $roles->contains('packer');
        $hasAdminScan = $roles->contains('admin-scan');
        if (!$hasPicker && !$hasPacker && !$hasAdminScan) {
            return $next($request);
        }

        $hasOtherRoles = $roles->diff(['picker', 'packer', 'admin-scan'])->isNotEmpty();
        if ($hasOtherRoles) {
            return $next($request);
        }

        $routeName = $request->route()?->getName() ?? '';
        $path = trim($request->path(), '/');

        $isDashboardRoute = $routeName === 'picker.dashboard' || $path === 'mobile/dashboard';
        $isScanOutRoute = str_starts_with($routeName, 'picker.scan-out')
            || str_starts_with($routeName, 'picker.scan-out-v2')
            || str_starts_with($path, 'mobile/scan-out')
            || str_starts_with($path, 'mobile/scan-out-v2');
        $isPickerRoute = (str_starts_with($routeName, 'picker.') || str_starts_with($path, 'mobile'))
            && !$isScanOutRoute
            && !$isDashboardRoute;
        $isOpnameRoute = str_starts_with($routeName, 'opname.') || str_starts_with($path, 'opname');
        $isQcRoute = str_starts_with($routeName, 'qc.') || str_starts_with($path, 'qc');
        $isLogoutRoute = $routeName === 'logout';
        $isAdminQcScanRoute = $routeName === 'admin.outbound.qc-scan.index' || $path === 'admin/outbound/qc-scan';
        $isAdminScanOutRoute = str_starts_with($routeName, 'admin.outbound.scan-out')
            || str_starts_with($path, 'admin/outbound/scan-out');
        $isAdminScanOutHistoryRoute = str_starts_with($routeName, 'admin.outbound.packer-scan-outs')
            || str_starts_with($path, 'admin/outbound/packer-scan-outs');

        if ($hasPicker || $hasPacker) {
            if (
                ($hasPicker && $isAdminQcScanRoute)
                || $isAdminScanOutRoute
                || ($hasPacker && $isAdminScanOutHistoryRoute)
                || $isPickerRoute
                || $isScanOutRoute
                || $isQcRoute
                || $isOpnameRoute
                || $isLogoutRoute
                || $isDashboardRoute
            ) {
                return $next($request);
            }
        }

        if ($hasAdminScan && !$hasPicker && !$hasPacker) {
            if ($isScanOutRoute || $isAdminScanOutRoute || $isAdminScanOutHistoryRoute || $isLogoutRoute) {
                return $next($request);
            }
        }

        if ($isAdminScanOutRoute || $isAdminScanOutHistoryRoute) {
            return response()->json([
                'message' => 'Akses scan out desktop tidak valid. Tetap di halaman scan out.',
            ], 403);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Akses dibatasi untuk role picker',
            ], 403);
        }

        if ($hasAdminScan && !$hasPicker && !$hasPacker && !$hasOtherRoles) {
            return redirect()->route('admin.outbound.scan-out.index');
        }

        return redirect()->route('picker.dashboard');
    }
}
