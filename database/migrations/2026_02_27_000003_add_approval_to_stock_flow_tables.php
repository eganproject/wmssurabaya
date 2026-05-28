<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inbound_transactions', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('note');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('outbound_transactions', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('note');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::table('damaged_goods', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('note');
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        DB::table('inbound_transactions')->update([
            'status' => 'approved',
            'approved_at' => DB::raw('transacted_at'),
            'approved_by' => DB::raw('created_by'),
        ]);
        DB::table('outbound_transactions')->update([
            'status' => 'approved',
            'approved_at' => DB::raw('transacted_at'),
            'approved_by' => DB::raw('created_by'),
        ]);
        DB::table('damaged_goods')->update([
            'status' => 'approved',
            'approved_at' => DB::raw('transacted_at'),
            'approved_by' => DB::raw('created_by'),
        ]);
    }

    public function down(): void
    {
        Schema::table('damaged_goods', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'approved_at', 'approved_by']);
        });

        Schema::table('outbound_transactions', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'approved_at', 'approved_by']);
        });

        Schema::table('inbound_transactions', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'approved_at', 'approved_by']);
        });
    }
};
