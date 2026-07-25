<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('item_units', function (Blueprint $table) {
            $table->foreignId('uom_id')->nullable()->after('item_id')->constrained('uoms')->nullOnDelete();
        });

        // Preserve existing item_units as the transaction source of truth.  This only
        // creates matching master records and links them; it never renames or deletes
        // an existing unit or historical transaction.
        $now = now();
        DB::table('item_units')->select('name')->distinct()->orderBy('name')->each(function ($unit) use ($now) {
            $name = trim((string) $unit->name);
            if ($name === '') return;
            $code = strtoupper(mb_substr($name, 0, 30));
            $id = DB::table('uoms')->where('code', $code)->value('id');
            if (!$id) {
                $id = DB::table('uoms')->insertGetId([
                    'code' => $code, 'name' => $name, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('item_units')->whereNull('uom_id')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->update(['uom_id' => $id]);
        });

        // Menus normally come from MenuSeeder, but production deployments run
        // migrations independently. Create the entry here too and copy Item
        // permissions so current master-data operators can access it immediately.
        $parentId = DB::table('menus')->where('slug', 'master-data')->value('id');
        if ($parentId) {
            DB::table('menus')->updateOrInsert(['slug' => 'uoms'], [
                'name' => 'UOM', 'route' => 'admin.masterdata.uoms.index',
                'icon' => 'fa-solid fa-ruler-combined', 'parent_id' => $parentId,
                'sort_order' => 21.55, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $uomMenuId = DB::table('menus')->where('slug', 'uoms')->value('id');
            $itemMenuId = DB::table('menus')->where('slug', 'items')->value('id');
            if ($uomMenuId && $itemMenuId) {
                DB::table('permission_menu')->where('menu_id', $itemMenuId)->orderBy('role_id')->each(function ($permission) use ($uomMenuId, $now) {
                    DB::table('permission_menu')->updateOrInsert(
                        ['role_id' => $permission->role_id, 'menu_id' => $uomMenuId],
                        ['can_view' => $permission->can_view, 'can_create' => $permission->can_create,
                         'can_update' => $permission->can_update, 'can_delete' => $permission->can_delete,
                         'created_at' => $now, 'updated_at' => $now]
                    );
                });
            }
        }
    }

    public function down(): void
    {
        $menuId = DB::table('menus')->where('slug', 'uoms')->value('id');
        if ($menuId) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        }
        Schema::table('item_units', function (Blueprint $table) {
            $table->dropForeign(['uom_id']);
            $table->dropColumn('uom_id');
        });
        Schema::dropIfExists('uoms');
    }
};
