<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $defaultWarehouseId = (int) DB::table('warehouses')
            ->where('is_default', true)
            ->value('id');

        Schema::table('inbound_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('item_id')->constrained('item_units')->nullOnDelete();
            $table->integer('qty_input')->nullable()->after('unit_id');
            $table->unsignedInteger('conversion_qty')->default(1)->after('qty_input');
        });
        DB::table('inbound_items')->update([
            'qty_input' => DB::raw('qty'),
            'conversion_qty' => 1,
        ]);

        Schema::table('outbound_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('item_id')->constrained('item_units')->nullOnDelete();
            $table->integer('qty_input')->nullable()->after('unit_id');
            $table->unsignedInteger('conversion_qty')->default(1)->after('qty_input');
        });
        DB::table('outbound_items')->update([
            'qty_input' => DB::raw('qty'),
            'conversion_qty' => 1,
        ]);

        Schema::table('damaged_item_stocks', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses');
        });
        DB::table('damaged_item_stocks')->update(['warehouse_id' => $defaultWarehouseId]);
        Schema::table('damaged_item_stocks', function (Blueprint $table) {
            $table->index('item_id', 'damaged_item_stocks_item_id_index');
            $table->dropUnique(['item_id']);
            $table->unique(['warehouse_id', 'item_id'], 'damaged_item_stocks_warehouse_item_unique');
        });

        Schema::table('damaged_stock_mutations', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses');
            $table->index(['warehouse_id', 'item_id', 'occurred_at'], 'damaged_stock_warehouse_item_date_idx');
        });
        DB::table('damaged_stock_mutations')->update(['warehouse_id' => $defaultWarehouseId]);

        foreach (['damaged_goods', 'damaged_allocations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses');
            });
            DB::table($tableName)->update(['warehouse_id' => $defaultWarehouseId]);
        }
    }

    public function down(): void
    {
        foreach (['damaged_allocations', 'damaged_goods'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }

        Schema::table('damaged_stock_mutations', function (Blueprint $table) {
            $table->dropIndex('damaged_stock_warehouse_item_date_idx');
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('damaged_item_stocks', function (Blueprint $table) {
            $table->dropUnique('damaged_item_stocks_warehouse_item_unique');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropIndex('damaged_item_stocks_item_id_index');
            $table->unique('item_id');
        });

        Schema::table('outbound_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['qty_input', 'conversion_qty']);
        });

        Schema::table('inbound_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
            $table->dropColumn(['qty_input', 'conversion_qty']);
        });
    }
};
