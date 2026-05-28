<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_bundles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_item_id');
            $table->unsignedBigInteger('component_item_id');
            $table->integer('qty')->default(1);
            $table->timestamps();

            $table->unique(['bundle_item_id', 'component_item_id']);
            $table->foreign('bundle_item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->foreign('component_item_id')->references('id')->on('items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_bundles');
    }
};
