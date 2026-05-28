<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (!Schema::hasColumn('resis', 'status')) {
                $table->string('status', 20)->default('active')->after('kurir_id');
            }
            if (!Schema::hasColumn('resis', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('resis', 'canceled_by')) {
                $table->foreignId('canceled_by')->nullable()->constrained('users')->nullOnDelete()->after('canceled_at');
            }
            if (!Schema::hasColumn('resis', 'cancel_reason')) {
                $table->string('cancel_reason', 255)->nullable()->after('canceled_by');
            }
        });

        if (Schema::hasColumn('resis', 'status')) {
            DB::table('resis')->whereNull('status')->update(['status' => 'active']);
        }
    }

    public function down(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (Schema::hasColumn('resis', 'cancel_reason')) {
                $table->dropColumn('cancel_reason');
            }
            if (Schema::hasColumn('resis', 'canceled_by')) {
                $table->dropConstrainedForeignId('canceled_by');
            }
            if (Schema::hasColumn('resis', 'canceled_at')) {
                $table->dropColumn('canceled_at');
            }
            if (Schema::hasColumn('resis', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
