<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (!Schema::hasColumn('resis', 'uncanceled_at')) {
                $table->timestamp('uncanceled_at')->nullable()->after('cancel_reason');
            }

            if (!Schema::hasColumn('resis', 'uncanceled_by')) {
                $table->foreignId('uncanceled_by')->nullable()->constrained('users')->nullOnDelete()->after('uncanceled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('resis', function (Blueprint $table) {
            if (Schema::hasColumn('resis', 'uncanceled_by')) {
                $table->dropConstrainedForeignId('uncanceled_by');
            }

            if (Schema::hasColumn('resis', 'uncanceled_at')) {
                $table->dropColumn('uncanceled_at');
            }
        });
    }
};
