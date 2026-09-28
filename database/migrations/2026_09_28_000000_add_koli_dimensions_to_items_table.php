<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dimensi per koli (cm) untuk informasi CBM. Nullable tanpa default
     * agar data item yang sudah ada tidak berubah.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('koli_length_cm', 8, 2)->nullable()->after('procurement_source');
            $table->decimal('koli_width_cm', 8, 2)->nullable()->after('koli_length_cm');
            $table->decimal('koli_height_cm', 8, 2)->nullable()->after('koli_width_cm');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['koli_length_cm', 'koli_width_cm', 'koli_height_cm']);
        });
    }
};
