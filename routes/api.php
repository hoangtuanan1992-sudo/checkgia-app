<?php

use App\Http\Controllers\Shopee\ShopeeAgentApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('shopee')
    ->middleware('shopee.token')
    ->group(function () {
        Route::post('/agent/heartbeat', [ShopeeAgentApiController::class, 'heartbeat']);
        Route::post('/agent/pull', [ShopeeAgentApiController::class, 'pull']);
        Route::post('/agent/report', [ShopeeAgentApiController::class, 'report']);
    });
