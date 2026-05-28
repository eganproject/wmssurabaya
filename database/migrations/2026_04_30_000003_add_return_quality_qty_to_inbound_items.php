<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbound_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_items', 'qty_received')) {
                $table->integer('qty_received')->default(0)->after('qty');
            }
            if (!Schema::hasColumn('inbound_items', 'qty_good')) {
                $table->integer('qty_good')->default(0)->after('qty_received');
            }
            if (!Schema::hasColumn('inbound_items', 'qty_damaged')) {
                $table->integer('qty_damaged')->default(0)->after('qty_good');
            }
        });

        DB::table('inbound_items')
            ->where('qty_received', 0)
            ->update([
                'qty_received' => DB::raw('qty'),
                'qty_good' => DB::raw('qty'),
                'qty_damaged' => 0,
            ]);

        $returnItemIds = DB::table('inbound_items')
            ->join('inbound_transactions', 'inbound_transactions.id', '=', 'inbound_items.inbound_transaction_id')
            ->where('inbound_transactions.type', 'return')
            ->where('inbound_items.qty_damaged', 0)
            ->pluck('inbound_items.id');

        if ($returnItemIds->isNotEmpty()) {
            DB::table('inbound_items')
                ->whereIn('id', $returnItemIds)
                ->update([
                    'qty_good' => 0,
                    'qty_damaged' => DB::raw('qty_received'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('inbound_items', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_items', 'qty_damaged')) {
                $table->dropColumn('qty_damaged');
            }
            if (Schema::hasColumn('inbound_items', 'qty_good')) {
                $table->dropColumn('qty_good');
            }
            if (Schema::hasColumn('inbound_items', 'qty_received')) {
                $table->dropColumn('qty_received');
            }
        });
    }
};
