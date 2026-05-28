<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_allocations', function (Blueprint $table) {
            if (!Schema::hasColumn('damaged_allocations', 'outbound_transaction_id')) {
                $table->foreignId('outbound_transaction_id')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('outbound_transactions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('damaged_allocations', function (Blueprint $table) {
            if (Schema::hasColumn('damaged_allocations', 'outbound_transaction_id')) {
                $table->dropForeign(['outbound_transaction_id']);
                $table->dropColumn('outbound_transaction_id');
            }
        });
    }
};
