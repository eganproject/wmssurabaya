<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resi_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resi_id')->constrained('resis')->cascadeOnDelete();
            $table->string('sku', 100);
            $table->integer('qty');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resi_details');
    }
};
