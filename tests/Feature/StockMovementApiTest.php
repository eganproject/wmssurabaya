<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemStock;
use App\Models\ItemUnit;
use App\Models\StockApiAllowedIp;
use App\Models\StockMutation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-stock-api-token';

    protected function setUp(): void
    {
        parent::setUp();
        config(['stock_api.enabled' => true, 'stock_api.token' => self::TOKEN]);
        StockApiAllowedIp::create(['ip_address' => '127.0.0.1', 'label' => 'test', 'is_active' => true]);
    }

    public function test_requires_api_token(): void
    {
        $this->getJson('/api/v1/stock-movements')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_returns_same_classification_as_web_report_with_filters_and_pagination(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->seedMovement($user);

        $params = [
            'date_from' => now()->subDays(29)->toDateString(),
            'date_to' => now()->toDateString(),
        ];

        $web = $this->actingAs($user)
            ->getJson(route('admin.reports.stock.movement-data', $params + ['draw' => 1, 'start' => 0, 'length' => -1]))
            ->assertOk();

        $api = $this->withToken(self::TOKEN)
            ->getJson('/api/v1/stock-movements?'.http_build_query($params + ['include_trend' => 'true']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.period_days', 30)
            ->assertJsonPath('summary.fast_sku', 1)
            ->assertJsonPath('summary.non_moving_sku', 1)
            ->assertJsonCount(30, 'trend');

        fwrite(STDERR, json_encode($api->json(), JSON_PRETTY_PRINT));
        $this->assertSame($web->json('summary'), $api->json('summary'));
        $this->assertSame(
            collect($web->json('data'))->pluck('movement_key', 'sku')->all(),
            collect($api->json('data'))->pluck('movement_key', 'sku')->all(),
        );
        $this->assertSame(['MOVE-FAST', 'MOVE-MEDIUM', 'MOVE-SLOW', 'MOVE-NONE'], collect($api->json('data'))->pluck('sku')->all());

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/stock-movements?'.http_build_query($params + ['movement' => 'fast']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.total_unfiltered', 4)
            ->assertJsonPath('data.0.sku', 'MOVE-FAST');

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/stock-movements?'.http_build_query($params + ['sku' => 'MOVE-SLOW']))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.sku', 'MOVE-SLOW');

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/stock-movements?'.http_build_query($params + ['sort' => 'outbound_qty', 'dir' => 'asc', 'page' => 2, 'per_page' => 3]))
            ->assertOk()
            ->assertJsonPath('meta.total_pages', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'MOVE-FAST');
    }

    public function test_store_breakdown_endpoint(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/stock-movements/stores')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.period_days', 30)
            ->assertJsonPath('data', []);
    }

    public function test_invalid_parameters_return_400(): void
    {
        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/stock-movements?movement=super')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_PARAMETER');

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/stock-movements?cover_min=30&cover_max=10')
            ->assertStatus(400);

        $this->withToken(self::TOKEN)
            ->getJson('/api/v1/stock-movements?per_page=501')
            ->assertStatus(400);
    }

    private function seedMovement(User $user): void
    {
        $warehouse = Warehouse::where('code', Warehouse::DEFAULT_CODE)->firstOrFail();

        foreach (['MOVE-FAST' => 70, 'MOVE-MEDIUM' => 20, 'MOVE-SLOW' => 10, 'MOVE-NONE' => 0] as $sku => $qty) {
            $item = Item::create(['sku' => $sku, 'name' => "Item {$sku}", 'category_id' => null]);
            $unit = ItemUnit::create(['item_id' => $item->id, 'name' => 'PCS', 'conversion_qty' => 1, 'is_base' => true]);
            ItemStock::create(['warehouse_id' => $warehouse->id, 'item_id' => $item->id, 'stock' => 100]);

            if ($qty > 0) {
                StockMutation::create([
                    'warehouse_id' => $warehouse->id,
                    'item_id' => $item->id,
                    'unit_id' => $unit->id,
                    'direction' => 'out',
                    'qty' => $qty,
                    'qty_input' => $qty,
                    'conversion_qty' => 1,
                    'stock_before' => 100 + $qty,
                    'stock_after' => 100,
                    'source_type' => 'outbound',
                    'source_subtype' => 'manual',
                    'source_id' => 8000 + $item->id,
                    'occurred_at' => now()->subDay(),
                    'created_by' => $user->id,
                ]);
            }
        }
    }
}
