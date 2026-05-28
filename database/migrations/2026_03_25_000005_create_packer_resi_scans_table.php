<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('packer_resi_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resi_id')->constrained('resis')->cascadeOnDelete();
            $table->string('scan_type', 20);
            $table->string('scan_code', 120);
            $table->date('scan_date');
            $table->timestamp('scanned_at')->useCurrent();
            $table->foreignId('scanned_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('resi_id');
            $table->index('scan_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packer_resi_scans');
    }
};
