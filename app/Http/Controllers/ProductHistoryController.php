<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductHistoryController extends Controller
{
    public function show(Request $request, Product $product): View
    {
        abort_unless($product->user_id === $request->user()->effectiveUserId(), 404);

        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $since = now()->subDays($days);

        $history = ProductPriceHistory::query()
            ->where('product_id', $product->id)
            ->where('fetched_at', '>=', $since)
            ->orderBy('fetched_at')
            ->get(['price', 'fetched_at']);

        return view('products.history', [
            'product' => $product,
            'history' => $history,
            'days' => $days,
        ]);
    }
}
