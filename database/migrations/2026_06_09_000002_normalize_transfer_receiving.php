<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->foreignId('received_unit_id')
                ->nullable()
                ->after('qty_received_base')
                ->constrained('item_units')
                ->nullOnDelete();
            $table->integer('qty_received_unit')->default(0)->after('received_unit_id');
            $table->integer('qty_discrepancy_base')->default(0)->after('qty_received_unit');
        });

        DB::table('stock_transfer_items')->orderBy('id')->get()->each(function ($row) {
            DB::table('stock_transfer_items')->where('id', $row->id)->update([
                'received_unit_id' => $row->qty_received_base > 0 ? $row->unit_id : null,
                'qty_received_unit' => (int) $row->qty_received_input,
                'qty_discrepancy_base' => (int) $row->qty_base - (int) $row->qty_received_base,
            ]);
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn('qty_received_input');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->integer('qty_received_input')->default(0)->after('qty_received_base');
        });

        DB::table('stock_transfer_items')->update([
            'qty_received_input' => DB::raw('qty_received_unit'),
        ]);

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_unit_id');
            $table->dropColumn(['qty_received_unit', 'qty_discrepancy_base']);
        });
    }
};
