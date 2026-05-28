<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('picker_transit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->date('picked_date');
            $table->integer('qty');
            $table->integer('remaining_qty')->default(0);
            $table->timestamp('picked_at')->useCurrent();
            $table->timestamps();

            $table->unique(['item_id', 'picked_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picker_transit_items');
    }
};
