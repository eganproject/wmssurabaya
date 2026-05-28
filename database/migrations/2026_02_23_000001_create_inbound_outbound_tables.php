<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inbound_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('type', 20);
            $table->string('ref_no', 100)->nullable();
            $table->timestamp('transacted_at')->useCurrent();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inbound_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_transaction_id')->constrained('inbound_transactions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('outbound_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('type', 20);
            $table->string('ref_no', 100)->nullable();
            $table->timestamp('transacted_at')->useCurrent();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('outbound_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_transaction_id')->constrained('outbound_transactions')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->integer('qty');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_items');
        Schema::dropIfExists('outbound_transactions');
        Schema::dropIfExists('inbound_items');
        Schema::dropIfExists('inbound_transactions');
    }
};
