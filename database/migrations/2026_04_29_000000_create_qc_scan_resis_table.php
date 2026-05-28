<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('qc_scan_resis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resi_id')->constrained('resis')->cascadeOnDelete();
            $table->string('status', 20)->default('in_progress');
            $table->timestamp('scanned_at')->useCurrent();
            $table->foreignId('scanned_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('resi_id');
            $table->index(['resi_id', 'status']);
            $table->index(['scanned_by', 'scanned_at']);
        });

        Schema::create('qc_scan_resi_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qc_scan_resi_id')->constrained('qc_scan_resis')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('sku', 100);
            $table->integer('required_qty');
            $table->integer('scanned_qty')->default(0);
            $table->timestamps();

            $table->unique(['qc_scan_resi_id', 'sku']);
            $table->index('sku');
        });

        Schema::create('qc_transit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->date('transit_date');
            $table->integer('qty');
            $table->integer('remaining_qty')->default(0);
            $table->timestamp('last_qc_at')->useCurrent();
            $table->timestamps();

            $table->unique(['item_id', 'transit_date']);
            $table->index('transit_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_transit_items');
        Schema::dropIfExists('qc_scan_resi_items');
        Schema::dropIfExists('qc_scan_resis');
    }
};
