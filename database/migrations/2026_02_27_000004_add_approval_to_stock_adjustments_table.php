<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_adjustments', 'status')) {
                $table->string('status', 20)->default('pending')->after('note');
            }
            if (!Schema::hasColumn('stock_adjustments', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('transacted_at');
            }
            if (!Schema::hasColumn('stock_adjustments', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        if (Schema::hasColumn('stock_adjustments', 'status')) {
            DB::table('stock_adjustments')->update([
                'status' => 'approved',
                'approved_at' => DB::raw('transacted_at'),
                'approved_by' => DB::raw('created_by'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('stock_adjustments', 'approved_by')) {
                $table->dropForeign(['approved_by']);
            }
            if (Schema::hasColumn('stock_adjustments', 'status')) {
                $table->dropColumn(['status']);
            }
            if (Schema::hasColumn('stock_adjustments', 'approved_at')) {
                $table->dropColumn(['approved_at']);
            }
            if (Schema::hasColumn('stock_adjustments', 'approved_by')) {
                $table->dropColumn(['approved_by']);
            }
        });
    }
};
