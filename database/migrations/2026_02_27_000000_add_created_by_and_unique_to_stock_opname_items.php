<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('note')->constrained('users')->nullOnDelete();
            $table->unique(['stock_opname_id', 'item_id'], 'stock_opname_items_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opname_items', function (Blueprint $table) {
            $table->dropUnique('stock_opname_items_unique');
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
