<?php

namespace Tests\Feature;

use App\Models\PickingList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PickingListBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    private function importFile(array $rows, string $name)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(array_merge([
            ['ID Pesanan', 'AWB/No. Tracking', 'Kurir', 'SKU', 'Jumlah', 'Tanggal Pembuatan'],
        ], $rows));
        $path = tempnam(sys_get_temp_dir(), 'resi-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $this->actingAs($this->user)->postJson(route('admin.inventory.resi-import.import'), [
            'file' => new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true),
        ]);
    }

    public function test_picking_list_is_split_per_import_batch_without_changing_main_list(): void
    {
        $date = now()->toDateString();

        $this->importFile([
            ['ORD-1', 'RESI-1', 'JNE', 'SKU-A', 3, $date],
            ['ORD-2', 'RESI-2', 'JNE', 'SKU-B', 1, $date],
        ], 'pagi.xlsx')->assertOk();
        // SQLite stores the date cast with a time part; keep it a plain date like MySQL does.
        DB::table('picking_lists')->update(['list_date' => $date]);

        $this->travel(1)->hours();
        $this->importFile([
            ['ORD-3', 'RESI-3', 'JNE', 'SKU-A', 2, $date],
            // ORD-2 re-imported: now belongs to the second batch.
            ['ORD-2', 'RESI-2', 'JNE', 'SKU-B', 4, $date],
        ], 'siang.xlsx')->assertOk();

        // Main list stays aggregated per SKU.
        $this->assertSame(5, (int) PickingList::where('sku', 'SKU-A')->value('qty'));
        $this->assertSame(4, (int) PickingList::where('sku', 'SKU-B')->value('qty'));

        // 4 of SKU-A picked: batch 1 (3) is filled first, batch 2 gets the remaining 1.
        PickingList::where('sku', 'SKU-A')->update(['remaining_qty' => 1]);

        $response = $this->actingAs($this->user)
            ->getJson(route('admin.inventory.picking-list.batches', ['date' => $date]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $response
            ->assertJsonPath('data.0.number', 1)
            ->assertJsonPath('data.0.file_name', 'pagi.xlsx')
            ->assertJsonPath('data.0.total_resis', 1)
            ->assertJsonPath('data.0.rows.0.sku', 'SKU-A')
            ->assertJsonPath('data.0.rows.0.qty', 3)
            ->assertJsonPath('data.0.rows.0.picked_qty', 3)
            ->assertJsonPath('data.0.remaining_qty', 0)
            ->assertJsonPath('data.1.number', 2)
            ->assertJsonPath('data.1.total_resis', 2)
            ->assertJsonPath('data.1.total_qty', 6)
            ->assertJsonPath('data.1.rows.0.sku', 'SKU-A')
            ->assertJsonPath('data.1.rows.0.picked_qty', 1)
            ->assertJsonPath('data.1.rows.0.remaining_qty', 1)
            ->assertJsonPath('data.1.rows.1.sku', 'SKU-B')
            ->assertJsonPath('data.1.rows.1.remaining_qty', 4);

        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.picking-list.batches', ['date' => $date, 'status' => 'ongoing']))
            ->assertOk()
            ->assertJsonCount(0, 'data.0.rows')
            ->assertJsonCount(2, 'data.1.rows');

        $this->actingAs($this->user)
            ->getJson(route('admin.inventory.picking-list.batches', ['date' => $date, 'q' => 'sku-b']))
            ->assertOk()
            ->assertJsonCount(1, 'data.1.rows')
            ->assertJsonPath('data.1.rows.0.sku', 'SKU-B');

        $this->actingAs($this->user)
            ->get(route('admin.inventory.picking-list.index'))
            ->assertOk()
            ->assertSee('Per Batch Import');
    }
}
