<?php

namespace App\Http\Controllers\Shopee;

use App\Http\Controllers\Controller;
use App\Models\ShopeeProduct;
use App\Models\ShopeeShop;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopeeDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $ownerId = $request->user()->effectiveUserId();

        $shops = ShopeeShop::query()
            ->where('user_id', $ownerId)
            ->where('is_own', false)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        $products = ShopeeProduct::query()
            ->with(['competitors' => function ($q) {
                $q->with('shop:id,name')->orderBy('shopee_shop_id');
            }])
            ->where('user_id', $ownerId)
            ->latest()
            ->get();

        return view('shopee.dashboard', [
            'shops' => $shops,
            'products' => $products,
        ]);
    }
}
