<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['stock_adjustment_items', 'damaged_good_items', 'damaged_allocation_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('unit_id')->nullable()->after('item_id')->constrained('item_units')->nullOnDelete();
                $table->integer('qty_input')->nullable()->after('unit_id');
                $table->unsignedInteger('conversion_qty')->default(1)->after('qty_input');
            });
            DB::table($tableName)->update([
                'qty_input' => DB::raw('qty'),
                'conversion_qty' => 1,
            ]);
        }

        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('item_id')->constrained('item_units')->nullOnDelete();
            $table->integer('counted_qty_input')->nullable()->after('unit_id');
            $table->unsignedInteger('conversion_qty')->default(1)->after('counted_qty_input');
        });
        DB::table('stock_opname_items')->update([
            'counted_qty_input' => DB::raw('counted_qty'),
            'conversion_qty' => 1,
        ]);
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['counted_qty_input', 'conversion_qty']);
        });

        foreach (['damaged_allocation_items', 'damaged_good_items', 'stock_adjustment_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('unit_id');
                $table->dropColumn(['qty_input', 'conversion_qty']);
            });
        }
    }
};
