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
        $menus = [
            [
                'name' => 'Analitik Transfer Gudang',
                'slug' => 'report-transfer-analytics',
                'route' => 'admin.reports.transfer-analytics.index',
                'icon' => 'fa-solid fa-arrow-right-arrow-left',
                'sort_order' => 1.3,
            ],
            [
                'name' => 'Perencanaan Pengadaan Stok',
                'slug' => 'report-stock-planning',
                'route' => 'admin.reports.stock-planning.index',
                'icon' => 'fa-solid fa-chart-line',
                'sort_order' => 1.35,
            ],
        ];

        foreach ($menus as $menu) {
            DB::table('menus')->updateOrInsert(
                ['slug' => $menu['slug']],
                array_merge($menu, [
                    'parent_id' => $parentId,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }

        $roleIds = DB::table('roles')
            ->whereIn('slug', ['superadmin', 'captain', 'admin-gudang', 'kepala-gudang'])
            ->pluck('id');
        $menuIds = DB::table('menus')
            ->whereIn('slug', ['report-transfer-analytics', 'report-stock-planning'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($menuIds as $menuId) {
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
    }

    public function down(): void
    {
        $menuIds = DB::table('menus')
            ->whereIn('slug', ['report-transfer-analytics', 'report-stock-planning'])
            ->pluck('id');

        DB::table('permission_menu')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menus')->whereIn('id', $menuIds)->delete();
    }
};
