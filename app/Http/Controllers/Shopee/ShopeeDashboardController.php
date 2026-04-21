<?php

namespace App\Http\Controllers\Shopee;

use App\Http\Controllers\Controller;
use App\Models\ShopeeProduct;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use App\Models\ShopeeShop;
use Illuminate\Support\Facades\DB;

class ShopeeDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $ownerId = $request->user()->effectiveUserId();
        $products = $user->shopeeProducts()
            ->where('user_id', $ownerId)
            ->with(['competitors.shop'])
            ->orderBy('id', 'desc')
            ->get();

        $shops = ShopeeShop::query()
            ->where('user_id', $ownerId)
            ->where('is_own', false)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        return view('shopee.dashboard', compact('products', 'shops'));
    }

    public function history(Request $request, ShopeeProduct $product): View
    {
        $ownerId = $request->user()->effectiveUserId();
        abort_unless((int) $product->user_id === (int) $ownerId, 403, 'This action is unauthorized.');

        $days = (int) $request->get('days', 7);
        $since = now()->subDays($days);

        // Lấy lịch sử giá của chính shop mình
        $ownPrices = $product->prices()
            ->where('scraped_at', '>=', $since)
            ->orderBy('scraped_at')
            ->get()
            ->map(fn($p) => [
                't' => $p->scraped_at->toIso8601String(),
                'y' => (int) $p->price,
                'label' => 'Shop bạn'
            ]);

        // Lấy lịch sử giá của tất cả đối thủ
        $competitors = $product->competitors()->with('shop')->get();
        $competitorSeries = [];

        foreach ($competitors as $competitor) {
            $prices = $competitor->prices()
                ->where('scraped_at', '>=', $since)
                ->orderBy('scraped_at')
                ->get()
                ->map(fn($p) => [
                    't' => $p->scraped_at->toIso8601String(),
                    'y' => (int) $p->price + (int) ($competitor->price_adjustment ?? 0),
                    'label' => $competitor->shop->name
                ]);
            
            if ($prices->isNotEmpty()) {
                $competitorSeries[] = [
                    'name' => $competitor->shop->name,
                    'data' => $prices
                ];
            }
        }

        $chartData = [
            [
                'name' => 'Shop bạn',
                'data' => $ownPrices
            ],
            ...$competitorSeries
        ];

        return view('shopee.history', compact('product', 'chartData', 'days'));
    }
}
