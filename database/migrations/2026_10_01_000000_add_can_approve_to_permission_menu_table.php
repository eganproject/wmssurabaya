<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu yang memiliki aksi approve/finalisasi pada saat migration ini dibuat.
     * Sebelumnya aksi tersebut ikut hak "Ubah" (can_update), jadi hak approve
     * diisi dari can_update agar akses role yang sudah ada tidak berubah.
     */
    private array $approvableRoutes = [
        'admin.inventory.stock-opname.index',
        'admin.inventory.stock-adjustments.index',
        'admin.inventory.damaged-goods.index',
        'admin.inventory.damaged-allocations.index',
        'admin.inbound.receipts.index',
        'admin.inbound.returns.index',
        'admin.outbound.pickers.index',
        'admin.outbound.manuals.index',
        'admin.outbound.returns.index',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('permission_menu', 'can_approve')) {
            Schema::table('permission_menu', function (Blueprint $table) {
                $table->boolean('can_approve')->default(false)->after('can_delete');
            });
        }

        $menuIds = DB::table('menus')->whereIn('route', $this->approvableRoutes)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            DB::table('permission_menu')
                ->whereIn('menu_id', $menuIds)
                ->where('can_update', true)
                ->update(['can_approve' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permission_menu', 'can_approve')) {
            Schema::table('permission_menu', function (Blueprint $table) {
                $table->dropColumn('can_approve');
            });
        }
    }
};
