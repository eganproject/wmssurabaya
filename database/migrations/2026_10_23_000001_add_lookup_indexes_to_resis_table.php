<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk pencarian resi (scan QC, scan out, dll.) berdasarkan no_resi / id_pesanan.
 * Index biasa (bukan unique), jadi data lama yang duplikat tetap aman.
 * Hanya menambah index, tidak mengubah isi data.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (! Schema::hasIndex('resis', 'resis_no_resi_index')) {
                $table->index('no_resi', 'resis_no_resi_index');
            }
            if (! Schema::hasIndex('resis', 'resis_id_pesanan_index')) {
                $table->index('id_pesanan', 'resis_id_pesanan_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (Schema::hasIndex('resis', 'resis_no_resi_index')) {
                $table->dropIndex('resis_no_resi_index');
            }
            if (Schema::hasIndex('resis', 'resis_id_pesanan_index')) {
                $table->dropIndex('resis_id_pesanan_index');
            }
        });
    }
};
