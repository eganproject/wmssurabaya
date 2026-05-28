<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('damaged_item_stocks')) {
            Schema::create('damaged_item_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->integer('stock')->default(0);
                $table->timestamps();

                $table->unique('item_id');
            });
        }

        if (!Schema::hasTable('damaged_stock_mutations')) {
            Schema::create('damaged_stock_mutations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->enum('direction', ['in', 'out']);
                $table->integer('qty');
                $table->string('source_type', 40);
                $table->string('source_subtype', 40)->nullable();
                $table->unsignedBigInteger('source_id');
                $table->string('source_code', 80)->nullable();
                $table->text('note')->nullable();
                $table->timestamp('occurred_at')->useCurrent();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('idempotency_key', 120)->nullable();
                $table->timestamps();

                $table->index(['source_type', 'source_id']);
                $table->unique('idempotency_key');
            });
        }

        if (!Schema::hasTable('damaged_allocations')) {
            Schema::create('damaged_allocations', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('allocation_type', 40);
                $table->string('ref_no', 100)->nullable();
                $table->timestamp('transacted_at')->useCurrent();
                $table->text('note')->nullable();
                $table->string('status', 20)->default('pending');
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('damaged_allocation_items')) {
            Schema::create('damaged_allocation_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('damaged_allocation_id')->constrained('damaged_allocations')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->integer('qty');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['damaged_allocation_id', 'item_id'], 'damaged_allocation_items_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('damaged_allocation_items');
        Schema::dropIfExists('damaged_allocations');
        Schema::dropIfExists('damaged_stock_mutations');
        Schema::dropIfExists('damaged_item_stocks');
    }
};
