<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Guarded so it is a no-op when the import-resi store change already added the column.
        if (Schema::hasColumn('resis', 'store_id')) {
            return;
        }

        Schema::table('resis', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('kurir_id')->constrained('stores')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('resis', 'store_id')) {
            return;
        }

        Schema::table('resis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
        });
    }
};
