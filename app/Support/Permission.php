<?php

namespace App\Support;

use App\Models\Menu;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Permission
{
    public static function resolveBaseRoute(string $routeName): string
    {
        $base = preg_replace('/\.(create|store|edit|update|destroy|show|data|import|detail|approve)$/', '.index', $routeName);
        return $base;
    }

    public static function actionFromRoute(string $routeName): string
    {
        if (preg_match('/\.(create|store|import)$/', $routeName)) return 'create';
        if (preg_match('/\.(edit|update|approve)$/', $routeName)) return 'update';
        if (preg_match('/\.(destroy)$/', $routeName)) return 'delete';
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
            default => 'can_view',
        };

        return DB::table('permission_menu')
            ->where('menu_id', $menu->id)
            ->whereIn('role_id', $roleIds)
            ->where($col, true)
            ->exists();
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
