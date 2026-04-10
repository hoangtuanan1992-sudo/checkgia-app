<?php

namespace App\Http\Controllers;

use App\Jobs\ScrapeProductPrices;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardScrapeNowController extends Controller
{
    public function run(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isViewer(), 403);

        $userId = $user->effectiveUserId();
        $lockKey = 'scrape_now:user:'.$userId;
        $locked = ! Cache::add($lockKey, 1, now()->addSeconds(30));
        if ($locked) {
            return back()->with('status', 'Đang cập nhật, vui lòng chờ một chút rồi thử lại.');
        }

        $productIds = Product::query()
            ->where('user_id', $userId)
            ->whereNotNull('product_url')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        foreach ($productIds as $id) {
            ScrapeProductPrices::dispatch($id);
        }

        return back()->with('status', 'Đã bắt đầu cập nhật '.$productIds->count().' sản phẩm.');
    }
}
