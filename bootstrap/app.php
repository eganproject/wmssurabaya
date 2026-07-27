<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Console\Commands\RecalculatePoLineFulfillment;
use App\Console\Commands\MovePickingDate;
use App\Console\Commands\MovePackerScanDates;
use App\Console\Commands\MovePackerTransitDate;
use App\Console\Commands\BackfillStockApiRecords;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        RecalculatePoLineFulfillment::class,
        MovePickingDate::class,
        MovePackerScanDates::class,
        MovePackerTransitDate::class,
        BackfillStockApiRecords::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'activity.log' => \App\Http\Middleware\LogUserActivity::class,
            'menu.permission' => \App\Http\Middleware\AuthorizeMenuPermission::class,
            'restrict.picker' => \App\Http\Middleware\RestrictPickerAccess::class,
            'stock.api.access' => \App\Http\Middleware\StockApiAccess::class,
        ]);

        $middleware->appendToGroup('web', 'restrict.picker');
        $middleware->appendToGroup('web', 'activity.log');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('admin/outbound/scan-out*')) {
                return response()->json([
                    'message' => 'Sesi halaman Scan Out berakhir. Refresh halaman Scan Out, lalu scan ulang.',
                    'redirect' => null,
                ], 419);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi berakhir. Silakan login kembali.',
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()->guest(route('login'));
        });
    })->create();
