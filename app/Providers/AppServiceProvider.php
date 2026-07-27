<?php

namespace App\Providers;

use App\Models\Item;
use App\Models\ItemBundle;
use App\Models\ItemUnit;
use App\Models\ItemWarehouseSetting;
use App\Support\StockApiSyncService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('stock-api', fn (Request $request) => Limit::perMinute(config('stock_api.rate_limit_per_minute'))
            ->by(hash('sha256', (string) $request->bearerToken().'|'.$request->ip())));

        Item::saved(fn (Item $item) => StockApiSyncService::syncItemInAllActiveWarehouses($item->id));
        Item::deleting(fn (Item $item) => StockApiSyncService::markDeleted($item));
        ItemUnit::saved(fn (ItemUnit $unit) => StockApiSyncService::syncItemInAllActiveWarehouses($unit->item_id));
        ItemUnit::deleted(fn (ItemUnit $unit) => StockApiSyncService::syncItemInAllActiveWarehouses($unit->item_id));
        ItemWarehouseSetting::saved(fn (ItemWarehouseSetting $setting) => StockApiSyncService::syncItem($setting->warehouse_id, $setting->item_id));
        ItemWarehouseSetting::deleted(fn (ItemWarehouseSetting $setting) => StockApiSyncService::syncItem($setting->warehouse_id, $setting->item_id));
        ItemBundle::saved(fn (ItemBundle $bundle) => StockApiSyncService::syncItemInAllActiveWarehouses($bundle->bundle_item_id));
        ItemBundle::deleted(fn (ItemBundle $bundle) => StockApiSyncService::syncItemInAllActiveWarehouses($bundle->bundle_item_id));
    }
}
