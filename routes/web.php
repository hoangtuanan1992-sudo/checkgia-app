<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\CompetitorHistoryController;
use App\Http\Controllers\DashboardCompetitorSetupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardExportController;
use App\Http\Controllers\DashboardProductController;
use App\Http\Controllers\DashboardScrapeNowController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductHistoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Shopee\ShopeeAdminController;
use App\Http\Controllers\Shopee\ShopeeDashboardController;
use App\Http\Controllers\Shopee\ShopeeSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
})->name('home');

Route::get('/demo', DemoController::class)->name('demo');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/reports', [ReportController::class, 'index'])->name('dashboard.reports');
    Route::get('/dashboard/competitors', [DashboardCompetitorSetupController::class, 'index'])->name('dashboard.competitors');
    Route::get('/dashboard/export/products', [DashboardExportController::class, 'products'])->name('dashboard.export.products');
    Route::post('/dashboard/scrape-now', [DashboardScrapeNowController::class, 'run'])->name('dashboard.scrape.now');

    Route::get('/shopee', [ShopeeDashboardController::class, 'index'])->name('shopee.dashboard');
    Route::get('/shopee/history/{product}', [ShopeeDashboardController::class, 'history'])->name('shopee.history');
    Route::middleware('owner')->group(function () {
        Route::get('/shopee/settings', [ShopeeSettingsController::class, 'index'])->name('shopee.settings');
        Route::post('/shopee/shops', [ShopeeSettingsController::class, 'storeShop'])->name('shopee.shops.store');
        Route::delete('/shopee/shops/{shop}', [ShopeeSettingsController::class, 'destroyShop'])->name('shopee.shops.destroy');
        Route::post('/shopee/shops/{shop}/move', [ShopeeSettingsController::class, 'moveShop'])->name('shopee.shops.move');
        Route::post('/shopee/products', [ShopeeSettingsController::class, 'storeProduct'])->name('shopee.products.store');
        Route::post('/shopee/products/{product}/toggle', [ShopeeSettingsController::class, 'toggleProduct'])->name('shopee.products.toggle');
        Route::delete('/shopee/products/{product}', [ShopeeSettingsController::class, 'destroyProduct'])->name('shopee.products.destroy');
        Route::put('/shopee/products/{product}/url', [ShopeeSettingsController::class, 'updateOwnUrl'])->name('shopee.products.url.update');
        Route::match(['put', 'post'], '/shopee/products/{product}/shops/{shop}', [ShopeeSettingsController::class, 'upsertCompetitorUrl'])->name('shopee.products.competitors.upsert');
        Route::match(['put', 'post'], '/shopee/competitors/{competitor}/adjustment', [ShopeeSettingsController::class, 'updateCompetitorAdjustment'])->name('shopee.competitors.adjustment.update');
    });

    Route::middleware('admin')->group(function () {
        Route::get('/shopee/admin-settings', [ShopeeAdminController::class, 'edit'])->name('shopee.admin-settings');
        Route::match(['put', 'post'], '/shopee/admin-settings', [ShopeeAdminController::class, 'update'])->name('shopee.admin-settings.update');
        Route::post('/shopee/admin-settings/agents/{agent}', [ShopeeAdminController::class, 'updateAgent'])->name('shopee.admin-settings.agent.update');
        Route::delete('/shopee/admin-settings/agents/{agent}', [ShopeeAdminController::class, 'destroyAgent'])->name('shopee.admin-settings.agent.destroy');
        Route::post('/shopee/admin-settings/agents/{agent}/approve', [ShopeeAdminController::class, 'approveAgent'])->name('shopee.admin-settings.agent.approve');
    });

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/competitors/{competitor}/history', [CompetitorHistoryController::class, 'show'])->name('competitors.history');
    Route::get('/products/{product}/history', [ProductHistoryController::class, 'show'])->name('products.history');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [AdminUserController::class, 'create'])->name('users.create');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
        Route::post('/impersonate/{user}', [AdminUserController::class, 'impersonate'])->name('impersonate');
        Route::post('/impersonate/stop', [AdminUserController::class, 'stopImpersonate'])->name('impersonate.stop');
        Route::get('/impersonate/stop', [AdminUserController::class, 'stopImpersonate'])->name('impersonate.stop.get');
        Route::get('/settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');
    });

    Route::post('/dashboard/products', [DashboardProductController::class, 'store'])->name('dashboard.products.store');

    Route::middleware('owner')->group(function () {
        Route::put('/dashboard/products/{product}/url', [ProductController::class, 'updateUrl'])->name('dashboard.products.url.update');
        Route::delete('/dashboard/products/{product}', [ProductController::class, 'destroyFromDashboard'])->name('dashboard.products.destroy');
        Route::match(['put', 'post', 'get'], '/dashboard/products/{product}/competitor-sites/{competitorSite}', [CompetitorController::class, 'upsertUrl'])->name('dashboard.products.competitors.upsert');
        Route::post('/dashboard/competitor-sites', [DashboardCompetitorSetupController::class, 'storeSite'])->name('dashboard.competitors.sites.store');
        Route::match(['delete', 'post'], '/dashboard/competitor-sites/{competitorSite}', [DashboardCompetitorSetupController::class, 'destroySite'])->name('dashboard.competitors.sites.destroy');
        Route::post('/dashboard/competitor-sites/{competitorSite}/move', [DashboardCompetitorSetupController::class, 'moveSite'])->name('dashboard.competitors.sites.move');
        Route::post('/dashboard/scrape-settings', [DashboardCompetitorSetupController::class, 'updateScrapeSettings'])->name('dashboard.scrape-settings.update');
        Route::put('/account/notifications', [AccountController::class, 'updateNotifications'])->name('account.notifications');
        Route::post('/account/subusers', [AccountController::class, 'createSubUser'])->name('account.subusers.store');
        Route::delete('/account/subusers/{user}', [AccountController::class, 'destroySubUser'])->name('account.subusers.destroy');
        Route::post('/account/product-groups', [AccountController::class, 'createGroup'])->name('account.product-groups.store');
        Route::delete('/account/product-groups/{productGroup}', [AccountController::class, 'destroyGroup'])->name('account.product-groups.destroy');
        Route::resource('products', ProductController::class)->except(['show', 'index'])->names('products');
        Route::post('/products/{product}/competitors', [CompetitorController::class, 'store'])->name('products.competitors.store');
        Route::put('/products/{product}/competitors/{competitor}', [CompetitorController::class, 'update'])->name('products.competitors.update');
        Route::delete('/products/{product}/competitors/{competitor}', [CompetitorController::class, 'destroy'])->name('products.competitors.destroy');
        Route::post('/competitors/{competitor}/prices', [CompetitorController::class, 'storePrice'])->name('competitors.prices.store');
        Route::put('/competitors/{competitor}/url', [CompetitorController::class, 'updateUrl'])->name('competitors.url.update');
        Route::post('/competitors/{competitor}/scrape', [CompetitorController::class, 'scrapeLatestPrice'])->name('competitors.scrape');
    });

    Route::get('/dashboard/competitor-sites/{competitorSite}', fn () => redirect()->route('dashboard.competitors'))->name('dashboard.competitors.sites.show');
    Route::get('/dashboard/products/{product}/competitor-sites/{competitorSite}', fn () => redirect()->route('dashboard'))->name('dashboard.products.competitors.show');

    Route::match(['put', 'post'], '/competitors/{competitor}/price-adjustment', [CompetitorController::class, 'updatePriceAdjustment'])->name('competitors.adjustment.update');
    Route::get('/competitors/{competitor}/price-adjustment', fn () => redirect()->route('dashboard'));

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
});
