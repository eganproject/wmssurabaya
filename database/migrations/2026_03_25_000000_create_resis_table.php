<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resis', function (Blueprint $table) {
            $table->id();
            $table->string('id_pesanan', 100);
            $table->date('tanggal_pesanan');
            $table->date('tanggal_upload');
            $table->string('no_resi', 100)->nullable();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resis');
    }
};
