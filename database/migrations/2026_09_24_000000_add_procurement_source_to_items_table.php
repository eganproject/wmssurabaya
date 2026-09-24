<?php

use App\Models\Item;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('procurement_source', 20)
                ->default(Item::PROCUREMENT_NANGGEWER)
                ->after('category_id')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['procurement_source']);
            $table->dropColumn('procurement_source');
        });
    }
};
