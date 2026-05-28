<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('picker_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('submitted_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('picker_session_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picker_session_id')->constrained('picker_sessions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['picker_session_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picker_session_items');
        Schema::dropIfExists('picker_sessions');
    }
};
