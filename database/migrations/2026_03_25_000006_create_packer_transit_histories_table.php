<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packer_transit_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resi_id')->constrained('resis')->cascadeOnDelete();
            $table->string('id_pesanan', 100);
            $table->string('no_resi', 100)->nullable();
            $table->string('status', 50)->default('menunggu scan out');
            $table->timestamps();

            $table->unique('resi_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packer_transit_histories');
    }
};
