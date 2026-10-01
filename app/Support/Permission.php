<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class Permission
{
    private static ?bool $hasApproveColumn = null;

    public static function resolveBaseRoute(string $routeName): string
    {
        $base = preg_replace('/\.scan(\.(store|finish))?$/', '.index', $routeName);
        if ($base !== $routeName) {
            return $base;
        }

        $base = preg_replace('/\.(create|store|edit|update|status|destroy|show|data|forecast-data|forecast-export|stocks|import|detail|approve|finalize|ship|receive|cancel)$/', '.index', $routeName);
        return $base;
    }

    public static function actionFromRoute(string $routeName): string
    {
        if (preg_match('/\.scan\.(store|finish)$/', $routeName)) return 'update';
        if (preg_match('/\.(approve|finalize)$/', $routeName)) return 'approve';
        if (preg_match('/\.(create|store|import)$/', $routeName)) return 'create';
        if (preg_match('/\.(edit|update|status|ship|receive|scan|finish)$/', $routeName)) return 'update';
        if (preg_match('/\.(destroy|cancel)$/', $routeName)) return 'delete';
        // index, show, data, others default to view
        return 'view';
    }

    public static function can(User $user, string $routeName, ?string $action = null): bool
    {
        $action = $action ?: self::actionFromRoute($routeName);
        $baseRoute = self::resolveBaseRoute($routeName);

        $menu = Menu::where('route', $baseRoute)->first();
        if (!$menu) {
            // If no mapped menu, allow by default to avoid blocking non-menu routes
            return true;
        }

        $roleIds = $user->roles()->pluck('roles.id');
        if ($roleIds->isEmpty()) {
            if (Schema::hasTable('role_user') && DB::table('role_user')->count() === 0) {
                return true;
            }
            return false;
        }

        // If permissions table is empty (not seeded yet), allow access
        if (!Schema::hasTable('permission_menu') || DB::table('permission_menu')->count() === 0) {
            return true;
        }

        $col = match ($action) {
            'create' => 'can_create',
            'update' => 'can_update',
            'delete' => 'can_delete',
            'approve' => self::approveColumn(),
            default => 'can_view',
        };

        return DB::table('permission_menu')
            ->where('menu_id', $menu->id)
            ->whereIn('role_id', $roleIds)
            ->where($col, true)
            ->exists();
    }

    /**
     * Menu dianggap memiliki aksi approve jika punya route pasangan
     * ".approve" atau ".finalize" (mis. admin.inbound.receipts.approve).
     */
    public static function isApprovable(?string $menuRoute): bool
    {
        if (!$menuRoute || !str_ends_with($menuRoute, '.index')) {
            return false;
        }

        $prefix = substr($menuRoute, 0, -strlen('.index'));

        return Route::has($prefix.'.approve') || Route::has($prefix.'.finalize');
    }

    public static function hasApproveColumn(): bool
    {
        return self::$hasApproveColumn ??= Schema::hasColumn('permission_menu', 'can_approve');
    }

    // Fallback ke can_update selama migration can_approve belum dijalankan.
    private static function approveColumn(): string
    {
        return self::hasApproveColumn() ? 'can_approve' : 'can_update';
    }

    public static function viewableMenuIds(User $user)
    {
        $roleIds = $user->roles()->pluck('roles.id');
        if ($roleIds->isEmpty()) {
            if (Schema::hasTable('role_user') && DB::table('role_user')->count() === 0) {
                return Menu::pluck('id');
            }
            return collect();
        }
        if (!Schema::hasTable('permission_menu') || DB::table('permission_menu')->count() === 0) {
            return Menu::pluck('id');
        }
        return DB::table('permission_menu')
            ->whereIn('role_id', $roleIds)
            ->where('can_view', true)
            ->pluck('menu_id')
            ->unique();
    }
}
