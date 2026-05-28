<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('qc_scan_resis')) {
            return;
        }

        $removedSessionColumn = Schema::hasColumn('qc_scan_resis', 'qc_scan_session_id');

        if ($removedSessionColumn) {
            $this->mergeDuplicateResiScans();

            Schema::table('qc_scan_resis', function (Blueprint $table) {
                try {
                    $table->dropForeign(['qc_scan_session_id']);
                } catch (Throwable) {
                    // Foreign key may already be absent.
                }

                try {
                    $table->dropUnique(['qc_scan_session_id', 'resi_id']);
                } catch (Throwable) {
                    // Index may not exist on fresh or manually adjusted databases.
                }

                $table->dropColumn('qc_scan_session_id');
            });
        }

        if ($removedSessionColumn) {
            Schema::table('qc_scan_resis', function (Blueprint $table) {
                $table->unique('resi_id');
                $table->index(['scanned_by', 'scanned_at']);
            });
        }

        Schema::dropIfExists('qc_scan_sessions');
    }

    public function down(): void
    {
        if (!Schema::hasTable('qc_scan_resis')) {
            return;
        }

        Schema::create('qc_scan_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('last_scan_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('started_at');
        });

        Schema::table('qc_scan_resis', function (Blueprint $table) {
            $table->foreignId('qc_scan_session_id')->nullable()->after('id')->constrained('qc_scan_sessions')->nullOnDelete();
        });
    }

    private function mergeDuplicateResiScans(): void
    {
        $duplicateGroups = DB::table('qc_scan_resis')
            ->select('resi_id', DB::raw('COUNT(*) as total'))
            ->groupBy('resi_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('qc_scan_resis')
                ->where('resi_id', $group->resi_id)
                ->orderByRaw("CASE WHEN status = 'completed' THEN 0 ELSE 1 END")
                ->orderByDesc('completed_at')
                ->orderByDesc('scanned_at')
                ->orderByDesc('id')
                ->get();

            $keep = $rows->first();
            if (!$keep) {
                continue;
            }

            $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
            $itemRows = DB::table('qc_scan_resi_items')
                ->whereIn('qc_scan_resi_id', $ids)
                ->get()
                ->groupBy('sku');

            DB::table('qc_scan_resi_items')->whereIn('qc_scan_resi_id', $ids)->delete();

            $allItemsComplete = true;
            foreach ($itemRows as $sku => $items) {
                $requiredQty = (int) $items->max('required_qty');
                $scannedQty = min($requiredQty, (int) $items->sum('scanned_qty'));
                $allItemsComplete = $allItemsComplete && $requiredQty > 0 && $scannedQty >= $requiredQty;

                DB::table('qc_scan_resi_items')->insert([
                    'qc_scan_resi_id' => $keep->id,
                    'item_id' => $items->firstWhere('item_id', '!=', null)?->item_id,
                    'sku' => $sku,
                    'required_qty' => $requiredQty,
                    'scanned_qty' => $scannedQty,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('qc_scan_resis')
                ->whereIn('id', array_values(array_diff($ids, [(int) $keep->id])))
                ->delete();

            DB::table('qc_scan_resis')
                ->where('id', $keep->id)
                ->update([
                    'status' => $allItemsComplete && $itemRows->isNotEmpty() ? 'completed' : 'in_progress',
                    'completed_at' => $allItemsComplete && $itemRows->isNotEmpty()
                        ? ($keep->completed_at ?: now())
                        : null,
                    'completed_by' => $allItemsComplete && $itemRows->isNotEmpty()
                        ? ($keep->completed_by ?: $keep->scanned_by)
                        : null,
                    'updated_at' => now(),
                ]);
        }
    }
};
