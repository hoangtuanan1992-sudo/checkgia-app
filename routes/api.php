<?php

use App\Http\Controllers\Shopee\ShopeeAgentApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('shopee')->group(function () {
    Route::post('/agent/heartbeat', [ShopeeAgentApiController::class, 'heartbeat']);
    Route::middleware('shopee.agent')->group(function () {
        Route::post('/agent/pull', [ShopeeAgentApiController::class, 'pull']);
        Route::post('/agent/report', [ShopeeAgentApiController::class, 'report']);
    });
});
