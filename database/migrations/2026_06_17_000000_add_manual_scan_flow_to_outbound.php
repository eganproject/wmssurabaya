<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('outbound_transactions', function (Blueprint $table) {
            $table->string('scan_status', 20)->default('not_started')->after('status');
            $table->timestamp('scan_completed_at')->nullable()->after('scan_status');
            $table->foreignId('scan_completed_by')->nullable()->after('scan_completed_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('outbound_manual_scan_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_transaction_id')->constrained('outbound_transactions')->cascadeOnDelete();
            $table->foreignId('outbound_item_id')->constrained('outbound_items')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->string('scanned_sku', 100);
            $table->unsignedInteger('qty');
            $table->timestamp('scanned_at')->useCurrent();
            $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['outbound_transaction_id', 'item_id']);
            $table->index(['scanned_by', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_manual_scan_logs');

        Schema::table('outbound_transactions', function (Blueprint $table) {
            $table->dropForeign(['scan_completed_by']);
            $table->dropColumn(['scan_status', 'scan_completed_at', 'scan_completed_by']);
        });
    }
};
