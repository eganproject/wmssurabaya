<?php

namespace App\Http\Middleware;

use App\Support\Permission as PermissionSupport;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizeMenuPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $route = $request->route();
        $routeName = $route?->getName() ?? '';

        // Only enforce for named admin routes.
        if (str_starts_with($routeName, 'admin.')) {
            if (!PermissionSupport::can($user, $routeName)) {
                abort(403, 'Anda tidak memiliki akses ke halaman ini');
            }
        }

        return $next($request);
    }
}
