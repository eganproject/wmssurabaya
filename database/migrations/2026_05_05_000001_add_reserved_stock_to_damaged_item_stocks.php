<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_item_stocks', function (Blueprint $table) {
            $table->integer('reserved_stock')->default(0)->after('stock');
        });

        // Backfill reserved_stock dari alokasi pending yang sudah ada.
        DB::table('damaged_allocation_items as dai')
            ->join('damaged_allocations as da', 'da.id', '=', 'dai.damaged_allocation_id')
            ->where('da.status', 'pending')
            ->groupBy('dai.item_id')
            ->select('dai.item_id', DB::raw('SUM(dai.qty) as total'))
            ->orderBy('dai.item_id')
            ->chunk(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('damaged_item_stocks')
                        ->where('item_id', $row->item_id)
                        ->update(['reserved_stock' => (int) $row->total]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('damaged_item_stocks', function (Blueprint $table) {
            $table->dropColumn('reserved_stock');
        });
    }
};
