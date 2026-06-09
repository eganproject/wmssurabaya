<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('status', 40)->default('draft')->change();
        });
    }

    public function down(): void
    {
        DB::table('stock_transfers')
            ->where('status', 'received_with_discrepancy')
            ->update(['status' => 'received']);

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->change();
        });
    }
};
