<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inbound_items', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_items', 'qty_missing')) {
                $table->integer('qty_missing')->default(0)->after('qty_damaged');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inbound_items', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_items', 'qty_missing')) {
                $table->dropColumn('qty_missing');
            }
        });
    }
};
