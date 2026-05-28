<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (!Schema::hasColumn('resis', 'kurir_id')) {
                $table->foreignId('kurir_id')
                    ->default(1)
                    ->nullable()
                    ->constrained('kurirs')
                    ->nullOnDelete()
                    ->after('no_resi');
            }
        });

        if (Schema::hasColumn('resis', 'kurir_id')) {
            DB::table('resis')->whereNull('kurir_id')->update(['kurir_id' => 1]);
        }
    }

    public function down(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (Schema::hasColumn('resis', 'kurir_id')) {
                $table->dropConstrainedForeignId('kurir_id');
            }
        });
    }
};
