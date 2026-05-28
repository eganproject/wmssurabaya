<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('picking_list_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('list_date');
            $table->string('sku', 100);
            $table->integer('qty');
            $table->timestamps();

            $table->unique(['list_date', 'sku']);
            $table->index('list_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_list_exceptions');
    }
};
