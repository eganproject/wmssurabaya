<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menus')
            ->where('slug', 'outbound-picker-history')
            ->orWhere('route', 'admin.outbound.picker-sessions.index')
            ->update([
                'slug'  => 'outbound-qc-scan-history',
                'route' => 'admin.outbound.qc-scan-history.index',
            ]);
    }

    public function down(): void
    {
        DB::table('menus')
            ->where('slug', 'outbound-qc-scan-history')
            ->orWhere('route', 'admin.outbound.qc-scan-history.index')
            ->update([
                'slug'  => 'outbound-picker-history',
                'route' => 'admin.outbound.picker-sessions.index',
            ]);
    }
};
