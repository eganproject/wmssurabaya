<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resi_import_batches', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('file_name');
        });
    }

    public function down(): void
    {
        Schema::table('resi_import_batches', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
