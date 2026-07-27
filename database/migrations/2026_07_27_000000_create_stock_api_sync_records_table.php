<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_api_sync_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->string('sku', 100);
            $table->string('name', 255);
            $table->string('category', 255)->nullable();
            $table->string('uom', 30)->default('PCS');
            $table->decimal('qty', 18, 3)->default(0);
            $table->decimal('min_qty', 18, 3)->nullable();
            $table->string('status', 10)->index();
            $table->timestamp('source_updated_at')->index();
            $table->timestamps();
            $table->unique(['warehouse_id', 'sku']);
            $table->index(['warehouse_id', 'source_updated_at', 'sku']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_api_sync_records');
    }
};
