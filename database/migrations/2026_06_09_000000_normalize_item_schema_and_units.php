<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->default(null)->change();
        });
        DB::table('categories')->where('parent_id', 0)->update(['parent_id' => null]);
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->default(null)->change();
        });
        DB::table('items')->where('category_id', 0)->update(['category_id' => null]);
        Schema::table('items', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });

        $defaultWarehouseId = (int) DB::table('warehouses')->where('is_default', true)->value('id');
        if ($defaultWarehouseId > 0) {
            $items = DB::table('items')->get(['id', 'address', 'safety_stock']);
            $now = now();
            foreach ($items as $item) {
                DB::table('item_warehouse_settings')->updateOrInsert(
                    [
                        'warehouse_id' => $defaultWarehouseId,
                        'item_id' => $item->id,
                    ],
                    [
                        'location' => $item->address,
                        'safety_stock' => max(0, (int) $item->safety_stock),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['address', 'safety_stock']);
        });

        Schema::table('item_units', function (Blueprint $table) {
            $table->unique(['item_id', 'is_base'], 'item_units_item_role_unique');
        });
    }

    public function down(): void
    {
        Schema::table('item_units', function (Blueprint $table) {
            $table->dropUnique('item_units_item_role_unique');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->text('address')->nullable()->after('category_id');
            $table->unsignedInteger('safety_stock')->default(0)->after('description');
        });

        $defaultWarehouseId = (int) DB::table('warehouses')->where('is_default', true)->value('id');
        if ($defaultWarehouseId > 0) {
            DB::table('items')
                ->leftJoin('item_warehouse_settings as settings', function ($join) use ($defaultWarehouseId) {
                    $join->on('settings.item_id', '=', 'items.id')
                        ->where('settings.warehouse_id', '=', $defaultWarehouseId);
                })
                ->update([
                    'items.address' => DB::raw('settings.location'),
                    'items.safety_stock' => DB::raw('COALESCE(settings.safety_stock, 0)'),
                ]);
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });
        DB::table('items')->whereNull('category_id')->update(['category_id' => 0]);
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable(false)->default(0)->change();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        DB::table('categories')->whereNull('parent_id')->update(['parent_id' => 0]);
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable(false)->default(0)->change();
        });
    }
};
