<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $menuId = DB::table('menus')->where('slug', 'inbound-manual')->value('id');
        if ($menuId) {
            DB::table('permission_menu')->where('menu_id', $menuId)->delete();
            DB::table('menus')->where('id', $menuId)->delete();
        }
    }

    public function down(): void
    {
        // Intentionally left blank. The inbound manual route/controller surface was removed.
    }
};
