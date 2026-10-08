<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Hanya menambah menu baru + izin lihat. Tidak mengubah tabel/data lain.
     * Role yang diberi akses mengikuti role yang sudah bisa melihat
     * "Perencanaan Pengadaan Stok", ditambah superadmin.
     */
    public function up(): void
    {
        $parentId = DB::table('menus')->where('slug', 'reports')->value('id');
        if (! $parentId) {
            return;
        }

        $now = now();
        DB::table('menus')->updateOrInsert(
            ['slug' => 'report-cbm-forecast'],
            [
                'name' => 'Forecast CBM',
                'route' => 'admin.reports.cbm-forecast.index',
                'icon' => 'fa-solid fa-cube',
                'parent_id' => $parentId,
                'sort_order' => 1.36,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $menuId = DB::table('menus')->where('slug', 'report-cbm-forecast')->value('id');
        $planningMenuId = DB::table('menus')->where('slug', 'report-stock-planning')->value('id');

        $roleIds = DB::table('roles')->where('slug', 'superadmin')->pluck('id');
        if ($planningMenuId) {
            $roleIds = $roleIds->merge(
                DB::table('permission_menu')->where('menu_id', $planningMenuId)->where('can_view', true)->pluck('role_id')
            );
        }

        foreach ($roleIds->unique() as $roleId) {
            DB::table('permission_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                ['can_view' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menus')->where('slug', 'report-cbm-forecast')->value('id');
        if ($menuId) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        }
    }
};
