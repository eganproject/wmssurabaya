<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'item_stocks',
            'stock_mutations',
            'inbound_transactions',
            'outbound_transactions',
            'stock_opnames',
            'stock_adjustments',
            'damaged_item_stocks',
            'damaged_stock_mutations',
            'damaged_goods',
            'damaged_allocations',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable(false)->change();
            });
        }

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->integer('qty_input')->nullable(false)->change();
            $table->integer('stock_before')->nullable(false)->change();
            $table->integer('stock_after')->nullable(false)->change();
        });

        foreach (['inbound_items', 'outbound_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->integer('qty_input')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['inbound_items', 'outbound_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->integer('qty_input')->nullable()->change();
            });
        }

        Schema::table('stock_mutations', function (Blueprint $table) {
            $table->integer('qty_input')->nullable()->change();
            $table->integer('stock_before')->nullable()->change();
            $table->integer('stock_after')->nullable()->change();
        });

        foreach ([
            'item_stocks',
            'stock_mutations',
            'inbound_transactions',
            'outbound_transactions',
            'stock_opnames',
            'stock_adjustments',
            'damaged_item_stocks',
            'damaged_stock_mutations',
            'damaged_goods',
            'damaged_allocations',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->change();
            });
        }
    }
};
