<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kurirs', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->timestamps();
        });

        DB::table('kurirs')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Tidak ditemukan kurir',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('kurirs');
    }
};
