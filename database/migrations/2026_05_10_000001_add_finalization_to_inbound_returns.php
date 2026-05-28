<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inbound_transactions', 'finalized_at')) {
                $table->timestamp('finalized_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('inbound_transactions', 'finalized_by')) {
                $table->foreignId('finalized_by')
                    ->nullable()
                    ->after('approved_by')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        DB::table('inbound_transactions')
            ->where('type', 'return')
            ->where('status', 'approved')
            ->update([
                'status' => 'finalized',
                'finalized_at' => DB::raw('approved_at'),
                'finalized_by' => DB::raw('approved_by'),
            ]);
    }

    public function down(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('inbound_transactions', 'finalized_by')) {
                $table->dropForeign(['finalized_by']);
                $table->dropColumn('finalized_by');
            }
            if (Schema::hasColumn('inbound_transactions', 'finalized_at')) {
                $table->dropColumn('finalized_at');
            }
        });
    }
};
