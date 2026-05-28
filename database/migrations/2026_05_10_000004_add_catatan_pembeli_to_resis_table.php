<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (!Schema::hasColumn('resis', 'catatan_pembeli')) {
                $table->text('catatan_pembeli')->nullable()->after('kurir_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (Schema::hasColumn('resis', 'catatan_pembeli')) {
                $table->dropColumn('catatan_pembeli');
            }
        });
    }
};
