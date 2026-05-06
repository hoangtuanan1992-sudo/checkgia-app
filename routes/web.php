<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWindowsAgentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\CompetitorController;
use App\Http\Controllers\CompetitorHistoryController;
use App\Http\Controllers\DashboardCompareMatchController;
use App\Http\Controllers\DashboardCompetitorSetupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardExcelImportController;
use App\Http\Controllers\DashboardExportController;
use App\Http\Controllers\DashboardProductController;
use App\Http\Controllers\DashboardQuickScanController;
use App\Http\Controllers\DashboardScrapeNowController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductHistoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScannedProductFullController;
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
Route::get('/san-pham-full', ScannedProductFullController::class)->name('scanner.full-products');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/quick-scan', [DashboardQuickScanController::class, 'index'])->name('dashboard.quick-scan');
    Route::post('/dashboard/quick-scan/request', [DashboardQuickScanController::class, 'requestScan'])->name('dashboard.quick-scan.request');
    Route::post('/dashboard/quick-scan/add-to-compare', [DashboardQuickScanController::class, 'addToCompare'])->name('dashboard.quick-scan.add-to-compare');
    Route::get('/dashboard/reports', [ReportController::class, 'index'])->name('dashboard.reports');
    Route::get('/dashboard/competitors', [DashboardCompetitorSetupController::class, 'index'])->name('dashboard.competitors');
    Route::get('/dashboard/export/products', [DashboardExportController::class, 'products'])->name('dashboard.export.products');
    Route::get('/dashboard/import/template', [DashboardExcelImportController::class, 'template'])->name('dashboard.import.template');
    Route::post('/dashboard/import/excel', [DashboardExcelImportController::class, 'import'])->name('dashboard.import.excel');
    Route::post('/dashboard/scrape-now', [DashboardScrapeNowController::class, 'run'])->name('dashboard.scrape.now');
    Route::post('/dashboard/compare-match', [DashboardCompareMatchController::class, 'run'])->name('dashboard.compare-match.run');
    Route::post('/dashboard/compare-match/{compareMatchRun}/tick', [DashboardCompareMatchController::class, 'tick'])->name('dashboard.compare-match.tick');

    Route::get('/shopee', [ShopeeDashboardController::class, 'index'])->middleware('shopee.check')->name('shopee.dashboard');
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
        Route::put('/shopee/admin-settings', [ShopeeAdminController::class, 'update'])->name('shopee.admin-settings.update');
        Route::post('/shopee/admin-settings/agents/{agent}', [ShopeeAdminController::class, 'updateAgent'])->name('shopee.admin-settings.agent.update');
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
        Route::get('/windows-agent', [AdminWindowsAgentController::class, 'index'])->name('windows-agent.index');
        Route::post('/windows-agent/api-key', [AdminWindowsAgentController::class, 'updateApiKey'])->name('windows-agent.api-key.update');
        Route::post('/windows-agent/rebuild-queue', [AdminWindowsAgentController::class, 'rebuildQueue'])->name('windows-agent.rebuild-queue');
        Route::post('/windows-agent/test-jobs', [AdminWindowsAgentController::class, 'storeTestJob'])->name('windows-agent.test-jobs.store');
        Route::get('/windows-agent/test-jobs/{scrapeAgentJob}', [AdminWindowsAgentController::class, 'testJobStatus'])->name('windows-agent.test-jobs.status');
        Route::post('/settings/ai/{provider}/test', [AdminSettingController::class, 'testAiProvider'])->name('settings.ai.test');
        Route::post('/settings/ai/{provider}/models', [AdminSettingController::class, 'scanAiProviderModels'])->name('settings.ai.models');
        Route::post('/xpath-templates', [AdminSettingController::class, 'upsertXpathTemplate'])->name('xpath-templates.upsert');
        Route::delete('/xpath-templates/{competitorSiteTemplate}', [AdminSettingController::class, 'destroyXpathTemplate'])->name('xpath-templates.destroy');
        Route::post('/xpath-users/{user}', [AdminSettingController::class, 'updateUserXPaths'])->name('xpath-users.update');
        Route::post('/xpath-users/{user}/promote-site/{competitorSite}', [AdminSettingController::class, 'promoteUserSiteToTemplate'])->name('xpath-users.promote-site');
    });

    Route::post('/dashboard/products', [DashboardProductController::class, 'store'])->name('dashboard.products.store');
    Route::delete('/dashboard/products/bulk-delete', [ProductController::class, 'destroyFilteredFromDashboard'])->name('dashboard.products.bulk-destroy');
    Route::delete('/dashboard/products/{product}', [ProductController::class, 'destroyFromDashboard'])->name('dashboard.products.destroy');

    Route::middleware('owner')->group(function () {
        Route::post('/dashboard/products/assign-group/{productGroup}', [ProductController::class, 'assignFilteredGroupFromDashboard'])->name('dashboard.products.assign-group');
        Route::put('/dashboard/products/{product}/url', [ProductController::class, 'updateUrl'])->name('dashboard.products.url.update');
        Route::match(['put', 'post', 'get'], '/dashboard/products/{product}/competitor-sites/{competitorSite}', [CompetitorController::class, 'upsertUrl'])->name('dashboard.products.competitors.upsert');
        Route::post('/dashboard/products/{product}/competitor-sites/{competitorSite}/note', [CompetitorController::class, 'updateNote'])->name('dashboard.products.competitors.note');
        Route::post('/dashboard/competitor-sites', [DashboardCompetitorSetupController::class, 'storeSite'])->name('dashboard.competitors.sites.store');
        Route::match(['delete', 'post'], '/dashboard/competitor-sites/{competitorSite}', [DashboardCompetitorSetupController::class, 'destroySite'])->name('dashboard.competitors.sites.destroy');
        Route::post('/dashboard/competitor-sites/{competitorSite}/move', [DashboardCompetitorSetupController::class, 'moveSite'])->name('dashboard.competitors.sites.move');
        Route::post('/dashboard/scrape-settings', [DashboardCompetitorSetupController::class, 'updateScrapeSettings'])->name('dashboard.scrape-settings.update');
        Route::put('/account/notifications', [AccountController::class, 'updateNotifications'])->name('account.notifications');
        Route::post('/account/subusers', [AccountController::class, 'createSubUser'])->name('account.subusers.store');
        Route::match(['get', 'post'], '/account/subusers/{user}/update', [AccountController::class, 'updateSubUserFromPost'])->name('account.subusers.update-post');
        Route::match(['get', 'post'], '/account/subusers/{user}/delete', [AccountController::class, 'destroySubUserFromPost'])->name('account.subusers.delete-post');
        Route::put('/account/subusers/{user}', [AccountController::class, 'updateSubUser'])->name('account.subusers.update');
        Route::delete('/account/subusers/{user}', [AccountController::class, 'destroySubUser'])->name('account.subusers.destroy');
        Route::match(['get', 'post'], '/account/subusers/{user}', [AccountController::class, 'legacySubUserRequest'])->name('account.subusers.show');
        Route::post('/account/deleted-products/{product}/restore', [AccountController::class, 'restoreDeletedProduct'])->name('account.deleted-products.restore');
        Route::post('/account/product-groups', [AccountController::class, 'createGroup'])->name('account.product-groups.store');
        Route::match(['get', 'post'], '/account/product-groups/{productGroup}/update', [AccountController::class, 'updateGroupFromPost'])->name('account.product-groups.update-post');
        Route::match(['get', 'post'], '/account/product-groups/{productGroup}/delete', [AccountController::class, 'destroyGroupFromPost'])->name('account.product-groups.delete-post');
        Route::put('/account/product-groups/{productGroup}', [AccountController::class, 'updateGroup'])->name('account.product-groups.update');
        Route::delete('/account/product-groups/{productGroup}', [AccountController::class, 'destroyGroup'])->name('account.product-groups.destroy');
        Route::match(['get', 'post'], '/account/product-groups/{productGroup}', [AccountController::class, 'legacyProductGroupRequest'])->name('account.product-groups.show');
        Route::post('/account/competitor-site-groups', [AccountController::class, 'createCompetitorGroup'])->name('account.competitor-site-groups.store');
        Route::match(['get', 'post'], '/account/competitor-site-groups/{competitorSiteGroup}/update', [AccountController::class, 'updateCompetitorGroupFromPost'])->name('account.competitor-site-groups.update-post');
        Route::match(['get', 'post'], '/account/competitor-site-groups/{competitorSiteGroup}/delete', [AccountController::class, 'destroyCompetitorGroupFromPost'])->name('account.competitor-site-groups.delete-post');
        Route::put('/account/competitor-site-groups/{competitorSiteGroup}', [AccountController::class, 'updateCompetitorGroup'])->name('account.competitor-site-groups.update');
        Route::delete('/account/competitor-site-groups/{competitorSiteGroup}', [AccountController::class, 'destroyCompetitorGroup'])->name('account.competitor-site-groups.destroy');
        Route::match(['get', 'post'], '/account/competitor-site-groups/{competitorSiteGroup}', [AccountController::class, 'legacyCompetitorGroupRequest'])->name('account.competitor-site-groups.show');
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
    Route::get('/competitors/{competitor}/variants', [CompetitorController::class, 'variants'])->name('competitors.variants');
    Route::get('/competitors/{competitor}/price-adjustment', fn () => redirect()->route('dashboard'));

    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
});
