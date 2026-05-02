<?php

use App\Http\Controllers\Api\ProductImportController;
use App\Http\Controllers\Api\ScanRequestController;
use App\Http\Controllers\Shopee\ShopeeAgentApiController;
use Illuminate\Support\Facades\Route;

Route::post('/products/import', [ProductImportController::class, 'store'])->name('api.products.import');
Route::get('/scan-requests/next', [ScanRequestController::class, 'next'])->name('api.scan-requests.next');
Route::post('/scan-requests/{scanRequest}/accepted', [ScanRequestController::class, 'accepted'])->name('api.scan-requests.accepted');
Route::post('/scan-requests/{scanRequest}/failed', [ScanRequestController::class, 'failed'])->name('api.scan-requests.failed');

Route::prefix('shopee')->group(function () {
    Route::middleware('shopee.cors')->group(function () {
        Route::options('/{any?}', fn () => response('', 204))->where('any', '.*');
        Route::post('/agent/heartbeat', [ShopeeAgentApiController::class, 'heartbeat']);

        Route::middleware('shopee.agent')->group(function () {
            Route::post('/agent/pull', [ShopeeAgentApiController::class, 'pull']);
            Route::post('/agent/report', [ShopeeAgentApiController::class, 'report']);
        });
    });
});
