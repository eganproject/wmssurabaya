<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('resi_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 40)->unique();
            $table->string('file_name')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->unsignedInteger('total_resis')->default(0);
            $table->unsignedInteger('total_details')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delete_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'uploaded_at']);
        });

        Schema::create('resi_import_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resi_import_batch_id')->constrained('resi_import_batches')->cascadeOnDelete();
            $table->unsignedBigInteger('resi_id')->nullable();
            $table->string('id_pesanan', 100);
            $table->string('no_resi', 100)->nullable();
            $table->string('action', 20)->default('created');
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index('resi_id');
            $table->index('id_pesanan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resi_import_batch_items');
        Schema::dropIfExists('resi_import_batches');
    }
};
