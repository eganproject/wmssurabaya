<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('damaged_goods', function (Blueprint $table) {
            if (!Schema::hasColumn('damaged_goods', 'inbound_transaction_id')) {
                $table->foreignId('inbound_transaction_id')
                    ->nullable()
                    ->after('source_ref')
                    ->constrained('inbound_transactions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('damaged_goods', function (Blueprint $table) {
            if (Schema::hasColumn('damaged_goods', 'inbound_transaction_id')) {
                $table->dropForeign(['inbound_transaction_id']);
                $table->dropColumn('inbound_transaction_id');
            }
        });
    }
};
