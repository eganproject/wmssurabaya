<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->string('status', 20)->default('open')->after('note');
            $table->timestamp('completed_at')->nullable()->after('transacted_at');
            $table->foreignId('completed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropForeign(['completed_by']);
            $table->dropColumn(['status', 'completed_at', 'completed_by']);
        });
    }
};
