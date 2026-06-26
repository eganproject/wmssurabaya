<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\ItemStockController;
use App\Http\Controllers\Admin\InboundController;
use App\Http\Controllers\Admin\OutboundController;
use App\Http\Controllers\Admin\OutboundManualScanController;
use App\Http\Controllers\Admin\StockMutationController;
use App\Http\Controllers\Admin\StockOpnameController;
use App\Http\Controllers\Admin\StockAdjustmentController;
use App\Http\Controllers\Admin\StockTransferController;
use App\Http\Controllers\Admin\WarehouseController;
use App\Http\Controllers\Admin\DamagedGoodsController;
use App\Http\Controllers\Admin\DamagedAllocationController;
use App\Http\Controllers\Admin\ResiImportController;
use App\Http\Controllers\Admin\PickerTransitController;
use App\Http\Controllers\Admin\PickingListController;
use App\Http\Controllers\Admin\QcScanHistoryController;
use App\Http\Controllers\Admin\PackerHistoryController;
use App\Http\Controllers\Admin\PackerScanExceptionController;
use App\Http\Controllers\Admin\PackerPackingReportController;
use App\Http\Controllers\Admin\PackerReportController;
use App\Http\Controllers\Admin\PackerScanOutHistoryController;
use App\Http\Controllers\Admin\PackerScanOutInputController;
use App\Http\Controllers\Admin\PickerReportController;
use App\Http\Controllers\Admin\LowStockReportController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\StockOpnameReportController;
use App\Http\Controllers\Admin\StockPlanningReportController;
use App\Http\Controllers\Admin\StockReportController;
use App\Http\Controllers\Admin\TransferAnalyticsReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\DivisiController;
use App\Http\Controllers\Admin\KurirController;
use App\Http\Controllers\Admin\QcScanInputController;
use App\Http\Controllers\Mobile\StockOpnameMobileController;
use App\Http\Controllers\Picker\PickerDashboardController;
use App\Http\Controllers\Picker\PackerScanOutController;
use App\Http\Controllers\Picker\PickingListMobileController;
use App\Http\Controllers\Picker\PickerSessionController;
use App\Http\Controllers\Qc\QcScanController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Basic health check route for debugging blank page on '/'
Route::get('/healthz', function () {
    return response('OK', 200);
});

Route::get('/page-expired', function () {
    return response()->view('errors.419', [], 419);
})->name('page-expired');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware('auth')->get('/picker/{path?}', function (?string $path = null) {
    $target = '/mobile'.($path ? '/'.$path : '');
    $query = request()->getQueryString();

    return redirect($query ? $target.'?'.$query : $target);
})->where('path', '.*');

Route::middleware('auth')->prefix('mobile')->as('picker.')->group(function () {
    Route::get('/dashboard', [PickerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/scan-out', [PackerScanOutController::class, 'index'])->name('scan-out.index');
    Route::post('/scan-out/scan', [PackerScanOutController::class, 'scan'])->name('scan-out.scan');
    Route::get('/scan-out/history', [PackerScanOutController::class, 'history'])->name('scan-out.history');
    Route::get('/scan-out/history/data', [PackerScanOutController::class, 'historyData'])->name('scan-out.history-data');
    Route::get('/scan-out-v2', [PackerScanOutController::class, 'indexV2'])->name('scan-out-v2.index');
    Route::get('/picking-list', [PickingListMobileController::class, 'index'])->name('picking-list.index');
    Route::get('/picking-list/data', [PickingListMobileController::class, 'data'])->name('picking-list.data');
    Route::get('/', [PickerSessionController::class, 'index'])->name('index');
    Route::get('/current', [PickerSessionController::class, 'current'])->name('current');
    Route::post('/start', [PickerSessionController::class, 'start'])->name('start');
    Route::get('/items/search', [PickerSessionController::class, 'searchItems'])->name('items.search');
    Route::post('/items', [PickerSessionController::class, 'storeItem'])->name('items.store');
    Route::put('/items/{id}', [PickerSessionController::class, 'updateItem'])->name('items.update');
    Route::delete('/items/{id}', [PickerSessionController::class, 'destroyItem'])->name('items.destroy');
    Route::post('/scan-item', [PickerSessionController::class, 'scanItem'])->name('scan-item');
    Route::post('/submit', [PickerSessionController::class, 'submit'])->name('submit');
});

Route::middleware('auth')->prefix('qc')->as('qc.')->group(function () {
    Route::get('/current', [QcScanController::class, 'current'])->name('current');
    Route::post('/start', [QcScanController::class, 'start'])->name('start');
    Route::get('/items/search', [QcScanController::class, 'searchItems'])->name('items.search');
    Route::post('/scan-item', [QcScanController::class, 'scanItem'])->name('scan-item');
    Route::get('/resi-lookup', [QcScanController::class, 'lookupResi'])->name('resi-lookup');
    Route::post('/resi-record', [QcScanController::class, 'recordResi'])->name('resi-record');
});

Route::middleware('auth')->prefix('opname')->as('opname.')->group(function () {
    Route::get('/', [StockOpnameMobileController::class, 'index'])->name('index');
    Route::post('/batch', [StockOpnameMobileController::class, 'createBatch'])->name('batch.create');
    Route::get('/batch/{code}', [StockOpnameMobileController::class, 'showBatch'])->name('batch.show');
    Route::post('/batch/{code}/complete', [StockOpnameMobileController::class, 'completeBatch'])->name('batch.complete');
    Route::get('/items/search', [StockOpnameMobileController::class, 'searchItems'])->name('items.search');
    Route::post('/batch/{code}/items', [StockOpnameMobileController::class, 'storeItem'])->name('items.store');
    Route::put('/batch/{code}/items/{id}', [StockOpnameMobileController::class, 'updateItem'])->name('items.update');
    Route::delete('/batch/{code}/items/{id}', [StockOpnameMobileController::class, 'destroyItem'])->name('items.destroy');
});

require __DIR__.'/auth.php';

// Admin area
Route::middleware(['auth', 'verified', 'menu.permission'])->prefix('admin')->as('admin.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/kurir-detail', [DashboardController::class, 'kurirDetail'])->name('dashboard.kurir-detail');

    Route::prefix('masterdata')->as('masterdata.')->group(function () {
        // Users DataTables
        Route::get('/users/data', [AdminUserController::class, 'data'])->name('users.data');
        Route::get('/users/template', [AdminUserController::class, 'template'])->name('users.template');
        Route::post('/users/import', [AdminUserController::class, 'import'])->name('users.import');
        // Users CRUD
        Route::resource('users', AdminUserController::class)->except(['show'])->names('users');

        // Roles DataTables
        Route::get('/roles/data', [RoleController::class, 'data'])->name('roles.data');
        // Roles CRUD
        Route::resource('roles', RoleController::class)->except(['show'])->names('roles');

        // Divisi
        Route::get('/divisi/data', [DivisiController::class, 'data'])->name('divisi.data');
        Route::resource('divisi', DivisiController::class)->except(['create','show','edit'])->names('divisi');

        // Kurir
        Route::get('/kurir/data', [KurirController::class, 'data'])->name('kurir.data');
        Route::resource('kurir', KurirController::class)->except(['create','show','edit'])->names('kurir');

        // Menus DataTables
        Route::get('/menus/data', [MenuController::class, 'data'])->name('menus.data');
        // Menus CRUD
        Route::resource('menus', MenuController::class)->except(['show'])->names('menus');

        // Categories (inheritance via parent)
        Route::get('/categories/data', [\App\Http\Controllers\Admin\CategoryController::class, 'data'])->name('categories.data');
        Route::resource('categories', \App\Http\Controllers\Admin\CategoryController::class)->except(['create','show','edit'])->names('categories');

        // Items
        Route::get('/items/data', [\App\Http\Controllers\Admin\ItemController::class, 'data'])->name('items.data');
        Route::get('/items/template', [\App\Http\Controllers\Admin\ItemController::class, 'template'])->name('items.template');
        Route::get('/items/{item}', [\App\Http\Controllers\Admin\ItemController::class, 'show'])->name('items.show');
        Route::resource('items', \App\Http\Controllers\Admin\ItemController::class)->except(['create','show','edit'])->names('items');
        Route::post('/items/import', [\App\Http\Controllers\Admin\ItemController::class, 'import'])->name('items.import');

        // Stores
        Route::get('/stores/data', [\App\Http\Controllers\Admin\StoreController::class, 'data'])->name('stores.data');
        Route::resource('stores', \App\Http\Controllers\Admin\StoreController::class)->except(['create','show','edit'])->names('stores');

        Route::get('/warehouses/data', [WarehouseController::class, 'data'])->name('warehouses.data');
        Route::resource('warehouses', WarehouseController::class)->except(['create','show','edit'])->names('warehouses');

        // Permissions management
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::get('/permissions/{role}/edit', [PermissionController::class, 'edit'])->name('permissions.edit');
        Route::put('/permissions/{role}', [PermissionController::class, 'update'])->name('permissions.update');
    });

    Route::prefix('inventory')->as('inventory.')->group(function () {
        // Item Stocks
        Route::get('/item-stocks', [ItemStockController::class, 'index'])->name('item-stocks.index');
        Route::get('/item-stocks/data', [ItemStockController::class, 'data'])->name('item-stocks.data');
        Route::get('/item-stocks/export', [ItemStockController::class, 'export'])->name('item-stocks.export');
        Route::get('/item-stocks/{id}', [ItemStockController::class, 'show'])->name('item-stocks.show');

        // Stock Mutations
        Route::get('/stock-mutations', [StockMutationController::class, 'index'])->name('stock-mutations.index');
        Route::get('/stock-mutations/data', [StockMutationController::class, 'data'])->name('stock-mutations.data');
        Route::get('/stock-mutations/{id}', [StockMutationController::class, 'show'])->name('stock-mutations.show');

        Route::get('/stock-transfers', [StockTransferController::class, 'index'])->name('stock-transfers.index');
        Route::get('/stock-transfers/data', [StockTransferController::class, 'data'])->name('stock-transfers.data');
        Route::post('/stock-transfers', [StockTransferController::class, 'store'])->name('stock-transfers.store');
        Route::get('/stock-transfers/{id}', [StockTransferController::class, 'show'])->name('stock-transfers.show');
        Route::put('/stock-transfers/{id}', [StockTransferController::class, 'update'])->name('stock-transfers.update');
        Route::post('/stock-transfers/{id}/cancel', [StockTransferController::class, 'cancel'])->name('stock-transfers.cancel');
        Route::post('/stock-transfers/{id}/ship', [StockTransferController::class, 'ship'])->name('stock-transfers.ship');
        Route::post('/stock-transfers/{id}/receive', [StockTransferController::class, 'receive'])->name('stock-transfers.receive');

        // Stock Opname
        Route::get('/stock-opname', [StockOpnameController::class, 'index'])->name('stock-opname.index');
        Route::get('/stock-opname/data', [StockOpnameController::class, 'data'])->name('stock-opname.data');
        Route::get('/stock-opname/items', [StockOpnameController::class, 'items'])->name('stock-opname.items');
        Route::post('/stock-opname', [StockOpnameController::class, 'store'])->name('stock-opname.store');
        Route::post('/stock-opname/import', [StockOpnameController::class, 'import'])->name('stock-opname.import');
        Route::get('/stock-opname/template', [StockOpnameController::class, 'template'])->name('stock-opname.template');
        Route::get('/stock-opname/{id}', [StockOpnameController::class, 'show'])->name('stock-opname.show');
        Route::get('/stock-opname/{id}/export', [StockOpnameController::class, 'export'])->name('stock-opname.export');
        Route::post('/stock-opname/{id}/approve', [StockOpnameController::class, 'approve'])->name('stock-opname.approve');
        Route::delete('/stock-opname/{id}', [StockOpnameController::class, 'destroy'])->name('stock-opname.destroy');

        // Stock Adjustments
        Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->name('stock-adjustments.index');
        Route::get('/stock-adjustments/data', [StockAdjustmentController::class, 'data'])->name('stock-adjustments.data');
        Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->name('stock-adjustments.store');
        Route::post('/stock-adjustments/import', [StockAdjustmentController::class, 'import'])->name('stock-adjustments.import');
        Route::get('/stock-adjustments/template', [StockAdjustmentController::class, 'template'])->name('stock-adjustments.template');
        Route::get('/stock-adjustments/{id}/detail', [StockAdjustmentController::class, 'detail'])->name('stock-adjustments.detail');
        Route::get('/stock-adjustments/{id}', [StockAdjustmentController::class, 'show'])->name('stock-adjustments.show');
        Route::put('/stock-adjustments/{id}', [StockAdjustmentController::class, 'update'])->name('stock-adjustments.update');
        Route::delete('/stock-adjustments/{id}', [StockAdjustmentController::class, 'destroy'])->name('stock-adjustments.destroy');
        Route::post('/stock-adjustments/{id}/approve', [StockAdjustmentController::class, 'approve'])->name('stock-adjustments.approve');

        // Damaged Goods
        Route::get('/damaged-goods', [DamagedGoodsController::class, 'index'])->name('damaged-goods.index');
        Route::get('/damaged-goods/data', [DamagedGoodsController::class, 'data'])->name('damaged-goods.data');
        Route::get('/damaged-goods/stock-summary', [DamagedGoodsController::class, 'stockSummary'])->name('damaged-goods.stock-summary');
        Route::post('/damaged-goods', [DamagedGoodsController::class, 'store'])->name('damaged-goods.store');
        Route::post('/damaged-goods/import', [DamagedGoodsController::class, 'import'])->name('damaged-goods.import');
        Route::get('/damaged-goods/template', [DamagedGoodsController::class, 'template'])->name('damaged-goods.template');
        Route::get('/damaged-goods/{id}', [DamagedGoodsController::class, 'show'])->name('damaged-goods.show');
        Route::put('/damaged-goods/{id}', [DamagedGoodsController::class, 'update'])->name('damaged-goods.update');
        Route::delete('/damaged-goods/{id}', [DamagedGoodsController::class, 'destroy'])->name('damaged-goods.destroy');
        Route::post('/damaged-goods/{id}/approve', [DamagedGoodsController::class, 'approve'])->name('damaged-goods.approve');
        Route::get('/damaged-allocations', [DamagedAllocationController::class, 'index'])->name('damaged-allocations.index');
        Route::get('/damaged-allocations/data', [DamagedAllocationController::class, 'data'])->name('damaged-allocations.data');
        Route::get('/damaged-allocations/stocks', [DamagedAllocationController::class, 'stocks'])->name('damaged-allocations.stocks');
        Route::post('/damaged-allocations', [DamagedAllocationController::class, 'store'])->name('damaged-allocations.store');
        Route::get('/damaged-allocations/{id}', [DamagedAllocationController::class, 'show'])->name('damaged-allocations.show');
        Route::put('/damaged-allocations/{id}', [DamagedAllocationController::class, 'update'])->name('damaged-allocations.update');
        Route::delete('/damaged-allocations/{id}', [DamagedAllocationController::class, 'destroy'])->name('damaged-allocations.destroy');
        Route::post('/damaged-allocations/{id}/approve', [DamagedAllocationController::class, 'approve'])->name('damaged-allocations.approve');

        // Resi Import
        Route::get('/resi-import', [ResiImportController::class, 'index'])->name('resi-import.index');
        Route::get('/resi-import/data', [ResiImportController::class, 'data'])->name('resi-import.data');
        Route::get('/resi-import/summary', [ResiImportController::class, 'summary'])->name('resi-import.summary');
        Route::get('/resi-import/buyer-notes', [ResiImportController::class, 'buyerNotes'])->name('resi-import.buyer-notes');
        Route::get('/resi-import/batches', [ResiImportController::class, 'batches'])->name('resi-import.batches');
        Route::get('/resi-import/batches/{batch}', [ResiImportController::class, 'showBatch'])->name('resi-import.batches.show');
        Route::delete('/resi-import/batches/{batch}', [ResiImportController::class, 'destroyBatch'])->name('resi-import.batches.destroy');
        Route::delete('/resi-import/resis/{resi}', [ResiImportController::class, 'destroyResi'])->name('resi-import.resis.destroy');
        Route::post('/resi-import/import', [ResiImportController::class, 'import'])->name('resi-import.import');
        Route::get('/resi-import/template', [ResiImportController::class, 'template'])->name('resi-import.template');
        Route::post('/resi-import/cancel', [ResiImportController::class, 'cancel'])->name('resi-import.cancel');
        Route::post('/resi-import/uncancel', [ResiImportController::class, 'uncancel'])->name('resi-import.uncancel');

        // Picker Transit
        Route::get('/picker-transit', [PickerTransitController::class, 'index'])->name('picker-transit.index');
        Route::get('/picker-transit/data', [PickerTransitController::class, 'data'])->name('picker-transit.data');
        Route::get('/picker-transit/detail', [PickerTransitController::class, 'pickerDetail'])->name('picker-transit.detail');
        Route::get('/picker-transit/audit', [PickerTransitController::class, 'auditPickerRemaining'])->name('picker-transit.audit');
        Route::post('/picker-transit/recalculate-today', [PickerTransitController::class, 'recalculateToday'])->name('picker-transit.recalculate-today');
        Route::get('/picker-transit/packer-data', [PickerTransitController::class, 'dataPacker'])->name('picker-transit.packer-data');
        Route::get('/picker-transit/export-picker', [PickerTransitController::class, 'exportPickerStatus'])->name('picker-transit.export-picker');
        Route::get('/picker-transit/export-packer', [PickerTransitController::class, 'exportPackerStatus'])->name('picker-transit.export-packer');

        // Picking List
        Route::get('/picking-list', [PickingListController::class, 'index'])->name('picking-list.index');
        Route::get('/picking-list/data', [PickingListController::class, 'data'])->name('picking-list.data');
        Route::get('/picking-list/exceptions', [PickingListController::class, 'dataExceptions'])->name('picking-list.exceptions');
        Route::get('/picking-list/export', [PickingListController::class, 'export'])->name('picking-list.export');
        Route::post('/picking-list/add-qty', [PickingListController::class, 'storeQty'])->name('picking-list.store-qty');
        Route::post('/picking-list/recalculate', [PickingListController::class, 'recalculate'])->name('picking-list.recalculate');
        Route::post('/picking-list/exception-return', [PickingListController::class, 'returnException'])->name('picking-list.exception-return');
    });

    Route::prefix('inbound')->as('inbound.')->group(function () {
        Route::get('/receipts', [InboundController::class, 'receipts'])->name('receipts.index');
        Route::get('/receipts/data', [InboundController::class, 'receiptsData'])->name('receipts.data');
        Route::post('/receipts', [InboundController::class, 'receiptsStore'])->name('receipts.store');
        Route::post('/receipts/import', [InboundController::class, 'receiptsImport'])->name('receipts.import');
        Route::get('/receipts/template', [InboundController::class, 'receiptsTemplate'])->name('receipts.template');
        Route::get('/receipts/{id}', [InboundController::class, 'receiptsShow'])->name('receipts.show');
        Route::put('/receipts/{id}', [InboundController::class, 'receiptsUpdate'])->name('receipts.update');
        Route::delete('/receipts/{id}', [InboundController::class, 'receiptsDestroy'])->name('receipts.destroy');
        Route::get('/receipts/{id}/detail', [InboundController::class, 'receiptsDetail'])->name('receipts.detail');
        Route::post('/receipts/{id}/approve', [InboundController::class, 'receiptsApprove'])->name('receipts.approve');

        Route::get('/returns', [InboundController::class, 'returns'])->name('returns.index');
        Route::get('/returns/data', [InboundController::class, 'returnsData'])->name('returns.data');
        Route::post('/returns', [InboundController::class, 'returnsStore'])->name('returns.store');
        Route::post('/returns/import', [InboundController::class, 'returnsImport'])->name('returns.import');
        Route::get('/returns/template', [InboundController::class, 'returnsTemplate'])->name('returns.template');
        Route::get('/returns/{id}', [InboundController::class, 'returnsShow'])->name('returns.show');
        Route::put('/returns/{id}', [InboundController::class, 'returnsUpdate'])->name('returns.update');
        Route::delete('/returns/{id}', [InboundController::class, 'returnsDestroy'])->name('returns.destroy');
        Route::get('/returns/{id}/detail', [InboundController::class, 'returnsDetail'])->name('returns.detail');
        Route::post('/returns/{id}/approve', [InboundController::class, 'returnsApprove'])->name('returns.approve');
        Route::post('/returns/{id}/finalize', [InboundController::class, 'returnsFinalize'])->name('returns.finalize');

    });

    Route::prefix('outbound')->as('outbound.')->group(function () {
        Route::get('/pickers', [OutboundController::class, 'pickers'])->name('pickers.index');
        Route::get('/pickers/data', [OutboundController::class, 'pickersData'])->name('pickers.data');
        Route::post('/pickers', [OutboundController::class, 'pickersStore'])->name('pickers.store');
        Route::get('/pickers/{id}', [OutboundController::class, 'pickersShow'])->name('pickers.show');
        Route::put('/pickers/{id}', [OutboundController::class, 'pickersUpdate'])->name('pickers.update');
        Route::delete('/pickers/{id}', [OutboundController::class, 'pickersDestroy'])->name('pickers.destroy');
        Route::get('/pickers/{id}/detail', [OutboundController::class, 'pickersDetail'])->name('pickers.detail');
        Route::post('/pickers/{id}/approve', [OutboundController::class, 'pickersApprove'])->name('pickers.approve');

        Route::get('/manuals', [OutboundController::class, 'manuals'])->name('manuals.index');
        Route::get('/manuals/data', [OutboundController::class, 'manualsData'])->name('manuals.data');
        Route::post('/manuals', [OutboundController::class, 'manualsStore'])->name('manuals.store');
        Route::post('/manuals/import', [OutboundController::class, 'manualsImport'])->name('manuals.import');
        Route::get('/manuals/template', [OutboundController::class, 'manualsTemplate'])->name('manuals.template');
        Route::get('/manuals/{id}', [OutboundController::class, 'manualsShow'])->name('manuals.show');
        Route::put('/manuals/{id}', [OutboundController::class, 'manualsUpdate'])->name('manuals.update');
        Route::delete('/manuals/{id}', [OutboundController::class, 'manualsDestroy'])->name('manuals.destroy');
        Route::get('/manuals/{id}/detail', [OutboundController::class, 'manualsDetail'])->name('manuals.detail');
        Route::post('/manuals/{id}/approve', [OutboundController::class, 'manualsApprove'])->name('manuals.approve');
        Route::get('/manuals/{id}/scan', [OutboundManualScanController::class, 'index'])->name('manuals.scan');
        Route::post('/manuals/{id}/scan', [OutboundManualScanController::class, 'scan'])->name('manuals.scan.store');
        Route::post('/manuals/{id}/scan/finish', [OutboundManualScanController::class, 'finish'])->name('manuals.scan.finish');
        Route::post('/manuals/{id}/surat-jalan', [OutboundController::class, 'manualsGenerateSuratJalan'])->name('manuals.surat-jalan.generate');
        Route::get('/manuals/{id}/surat-jalan', [OutboundController::class, 'manualsViewSuratJalan'])->name('manuals.surat-jalan');

        Route::get('/returns', [OutboundController::class, 'returns'])->name('returns.index');
        Route::get('/returns/data', [OutboundController::class, 'returnsData'])->name('returns.data');
        Route::post('/returns', [OutboundController::class, 'returnsStore'])->name('returns.store');
        Route::post('/returns/import', [OutboundController::class, 'returnsImport'])->name('returns.import');
        Route::get('/returns/template', [OutboundController::class, 'returnsTemplate'])->name('returns.template');
        Route::get('/returns/{id}', [OutboundController::class, 'returnsShow'])->name('returns.show');
        Route::put('/returns/{id}', [OutboundController::class, 'returnsUpdate'])->name('returns.update');
        Route::delete('/returns/{id}', [OutboundController::class, 'returnsDestroy'])->name('returns.destroy');
        Route::get('/returns/{id}/detail', [OutboundController::class, 'returnsDetail'])->name('returns.detail');
        Route::post('/returns/{id}/approve', [OutboundController::class, 'returnsApprove'])->name('returns.approve');

        Route::get('/qc-scan-history', [QcScanHistoryController::class, 'index'])->name('qc-scan-history.index');
        Route::get('/qc-scan-history/data', [QcScanHistoryController::class, 'data'])->name('qc-scan-history.data');
        Route::delete('/qc-scan-history/{id}', [QcScanHistoryController::class, 'destroy'])->name('qc-scan-history.destroy');

        Route::get('/qc-scan', [QcScanInputController::class, 'index'])->name('qc-scan.index');
        Route::get('/scan-out', [PackerScanOutInputController::class, 'index'])->name('scan-out.index');
        Route::get('/scan-out/csrf-token', [PackerScanOutInputController::class, 'csrfToken'])->name('scan-out.csrf-token');
        Route::post('/scan-out/scan', [PackerScanOutController::class, 'scan'])->name('scan-out.scan');

        Route::get('/packer-history', [PackerHistoryController::class, 'index'])->name('packer-history.index');
        Route::get('/packer-history/data', [PackerHistoryController::class, 'data'])->name('packer-history.data');
        Route::get('/packer-scan-outs', [PackerScanOutHistoryController::class, 'index'])->name('packer-scan-outs.index');
        Route::get('/packer-scan-outs/data', [PackerScanOutHistoryController::class, 'data'])->name('packer-scan-outs.data');
        Route::get('/packer-scan-exceptions', [PackerScanExceptionController::class, 'index'])->name('packer-scan-exceptions.index');
        Route::get('/packer-scan-exceptions/data', [PackerScanExceptionController::class, 'data'])->name('packer-scan-exceptions.data');
        Route::post('/packer-scan-exceptions', [PackerScanExceptionController::class, 'store'])->name('packer-scan-exceptions.store');
        Route::put('/packer-scan-exceptions/{exception}', [PackerScanExceptionController::class, 'update'])->name('packer-scan-exceptions.update');
        Route::delete('/packer-scan-exceptions/{exception}', [PackerScanExceptionController::class, 'destroy'])->name('packer-scan-exceptions.destroy');

        Route::get('/picker-reports', [PickerReportController::class, 'index'])->name('picker-reports.index');
        Route::get('/picker-reports/data', [PickerReportController::class, 'data'])->name('picker-reports.data');
        Route::get('/picker-reports/detail', [PickerReportController::class, 'detail'])->name('picker-reports.detail');
    });

    Route::prefix('reports')->as('reports.')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/data', [ActivityLogController::class, 'data'])->name('activity-logs.data');
        Route::get('/activity-logs/{id}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::get('/packer-reports', [PackerReportController::class, 'index'])->name('packer-reports.index');
        Route::get('/packer-reports/data', [PackerReportController::class, 'data'])->name('packer-reports.data');
        Route::get('/packer-packing-reports', [PackerPackingReportController::class, 'index'])->name('packer-packing-reports.index');
        Route::get('/packer-packing-reports/data', [PackerPackingReportController::class, 'data'])->name('packer-packing-reports.data');
        Route::get('/packer-packing-reports/detail', [PackerPackingReportController::class, 'detail'])->name('packer-packing-reports.detail');
        Route::get('/packer-packing-reports/search-resi', [PackerPackingReportController::class, 'searchResi'])->name('packer-packing-reports.search-resi');
        Route::get('/low-stock', [LowStockReportController::class, 'index'])->name('low-stock.index');
        Route::get('/low-stock/data', [LowStockReportController::class, 'data'])->name('low-stock.data');
        Route::get('/stock', [StockReportController::class, 'index'])->name('stock.index');
        Route::get('/stock/data', [StockReportController::class, 'data'])->name('stock.data');
        Route::get('/stock-opname', [StockOpnameReportController::class, 'index'])->name('stock-opname.index');
        Route::get('/stock-opname/data', [StockOpnameReportController::class, 'data'])->name('stock-opname.data');
        Route::get('/stock-opname/sku-diff', [StockOpnameReportController::class, 'diffSku'])->name('stock-opname.diff-sku');
        Route::get('/stock-opname/export', [StockOpnameReportController::class, 'export'])->name('stock-opname.export');
        Route::get('/transfer-analytics', [TransferAnalyticsReportController::class, 'index'])->name('transfer-analytics.index');
        Route::get('/transfer-analytics/data', [TransferAnalyticsReportController::class, 'data'])->name('transfer-analytics.data');
        Route::get('/stock-planning', [StockPlanningReportController::class, 'index'])->name('stock-planning.index');
        Route::get('/stock-planning/data', [StockPlanningReportController::class, 'data'])->name('stock-planning.data');
    });
});
