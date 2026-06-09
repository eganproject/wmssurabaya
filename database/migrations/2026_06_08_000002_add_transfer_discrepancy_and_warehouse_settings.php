<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('received_at');
            $table->foreignId('cancelled_by')->nullable()->after('received_by')->constrained('users')->nullOnDelete();
            $table->text('discrepancy_note')->nullable()->after('note');
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->integer('qty_received_input')->default(0)->after('qty_received_base');
            $table->text('discrepancy_note')->nullable()->after('note');
        });

        Schema::create('item_warehouse_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->unsignedInteger('safety_stock')->default(0);
            $table->string('location', 255)->nullable();
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_id'], 'item_warehouse_settings_unique');
        });

        $warehouses = DB::table('warehouses')->pluck('id');
        $items = DB::table('items')->get(['id', 'safety_stock', 'address']);
        $now = now();
        foreach ($warehouses as $warehouseId) {
            foreach ($items as $item) {
                DB::table('item_warehouse_settings')->insert([
                    'warehouse_id' => $warehouseId,
                    'item_id' => $item->id,
                    'safety_stock' => max(0, (int) ($item->safety_stock ?? 0)),
                    'location' => $item->address,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('item_warehouse_settings');

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn(['qty_received_input', 'discrepancy_note']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['cancelled_at', 'discrepancy_note']);
        });
    }
};
