<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (!$parentId) {
            return;
        }

        $now = now();
        DB::table('menus')->updateOrInsert(
            ['slug' => 'report-stock'],
            [
                'name' => 'Laporan Stok',
                'route' => 'admin.reports.stock.index',
                'icon' => 'fa-solid fa-boxes-stacked',
                'parent_id' => $parentId,
                'sort_order' => 1.27,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $menuId = DB::table('menus')->where('slug', 'report-stock')->value('id');
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['superadmin', 'captain', 'admin-retur', 'admin-gudang', 'kepala-gudang'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                [
                    'can_view' => true,
                    'can_create' => false,
                    'can_update' => false,
                    'can_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menus')->where('slug', 'report-stock')->value('id');
        if (!$menuId) {
            return;
        }

        DB::table('permission_menu')->where('menu_id', $menuId)->delete();
        DB::table('menus')->where('id', $menuId)->delete();
    }
};
