<?php

use App\Http\Controllers\Shopee\ShopeeAgentApiController;
use Illuminate\Support\Facades\Route;

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
