<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->integer('qty');
            $table->string('source_type', 20);
            $table->string('source_subtype', 20)->nullable();
            $table->unsignedBigInteger('source_id');
            $table->string('source_code', 50)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_mutations');
    }
};
