<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->string('type', 30)->default('fulfillment');
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $now = now();
        $smallWarehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'WH-SMALL',
            'name' => 'Gudang Kecil',
            'type' => 'fulfillment',
            'is_active' => true,
            'is_default' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('warehouses')->insert([
            'code' => 'WH-BULK',
            'name' => 'Gudang Besar',
            'type' => 'bulk',
            'is_active' => true,
            'is_default' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::create('item_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('name', 30);
            $table->unsignedInteger('conversion_qty')->default(1);
            $table->boolean('is_base')->default(false);
            $table->string('barcode', 100)->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'name']);
        });

        $items = DB::table('items')->get(['id']);
        foreach ($items as $item) {
            DB::table('item_units')->insert([
                'item_id' => $item->id,
                'name' => 'PCS',
                'conversion_qty' => 1,
                'is_base' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses');
        });
        DB::table('item_stocks')->update(['warehouse_id' => $smallWarehouseId]);
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->index('item_id', 'item_stocks_item_id_index');
            $table->dropUnique(['item_id']);
            $table->unique(['warehouse_id', 'item_id'], 'item_stocks_warehouse_item_unique');
        });

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses');
            $table->foreignId('unit_id')->nullable()->after('item_id')->constrained('item_units')->nullOnDelete();
            $table->integer('qty_input')->nullable()->after('qty');
            $table->unsignedInteger('conversion_qty')->default(1)->after('qty_input');
            $table->integer('stock_before')->nullable()->after('conversion_qty');
            $table->integer('stock_after')->nullable()->after('stock_before');
            $table->index(['warehouse_id', 'item_id', 'occurred_at'], 'stock_mutations_warehouse_item_date_idx');
        });
        DB::table('stock_mutations')->update([
            'warehouse_id' => $smallWarehouseId,
            'qty_input' => DB::raw('qty'),
            'conversion_qty' => 1,
        ]);

        $currentStocks = DB::table('item_stocks')->get(['warehouse_id', 'item_id', 'stock']);
        foreach ($currentStocks as $currentStock) {
            $running = (int) $currentStock->stock;
            $mutations = DB::table('stock_mutations')
                ->where('warehouse_id', $currentStock->warehouse_id)
                ->where('item_id', $currentStock->item_id)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get(['id', 'direction', 'qty']);
            foreach ($mutations as $mutation) {
                $after = $running;
                $before = $mutation->direction === 'in'
                    ? $after - (int) $mutation->qty
                    : $after + (int) $mutation->qty;
                DB::table('stock_mutations')->where('id', $mutation->id)->update([
                    'stock_before' => $before,
                    'stock_after' => $after,
                ]);
                $running = $before;
            }
        }

        foreach (['inbound_transactions', 'outbound_transactions', 'stock_opnames', 'stock_adjustments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses');
            });
            DB::table($tableName)->update(['warehouse_id' => $smallWarehouseId]);
        }

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses');
            $table->foreignId('destination_warehouse_id')->constrained('warehouses');
            $table->string('status', 20)->default('draft');
            $table->timestamp('transacted_at')->useCurrent();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shipped_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('item_units')->nullOnDelete();
            $table->integer('qty_input');
            $table->unsignedInteger('conversion_qty')->default(1);
            $table->integer('qty_base');
            $table->integer('qty_received_base')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['stock_transfer_id', 'item_id'], 'stock_transfer_items_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');

        foreach (['stock_adjustments', 'stock_opnames', 'outbound_transactions', 'inbound_transactions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('warehouse_id');
            });
        }

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->dropIndex('stock_mutations_warehouse_item_date_idx');
            $table->dropConstrainedForeignId('unit_id');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn(['qty_input', 'conversion_qty', 'stock_before', 'stock_after']);
        });

        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropUnique('item_stocks_warehouse_item_unique');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropIndex('item_stocks_item_id_index');
            $table->unique('item_id');
        });

        Schema::dropIfExists('item_units');
        Schema::dropIfExists('warehouses');
    }
};
