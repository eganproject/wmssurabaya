<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('damaged_goods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('source_type', 30);
            $table->string('source_ref', 100)->nullable();
            $table->timestamp('transacted_at')->useCurrent();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('damaged_good_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('damaged_good_id')->constrained('damaged_goods')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_good_items');
        Schema::dropIfExists('damaged_goods');
    }
};
