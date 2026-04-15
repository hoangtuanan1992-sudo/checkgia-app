<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\ProductPriceHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetitorHistoryController extends Controller
{
    public function show(Request $request, Competitor $competitor): View
    {
        $product = $competitor->product;
        $user = $request->user();
        abort_unless($user, 403);

        if (! $product || (! $user->isAdmin() && (int) $product->user_id !== (int) $user->effectiveUserId())) {
            abort(404);
        }

        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;
        $since = now()->subDays($days);

        $prices = $competitor->prices()
            ->where('fetched_at', '>=', $since)
            ->orderBy('fetched_at')
            ->get(['price', 'fetched_at']);

        $competitors = $product->competitors()
            ->with(['prices' => function ($q) use ($since) {
                $q->where('fetched_at', '>=', $since)->orderBy('fetched_at');
            }])
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'competitor_site_id', 'product_id']);

        $ownHistory = ProductPriceHistory::query()
            ->where('product_id', $product->id)
            ->where('fetched_at', '>=', $since)
            ->orderBy('fetched_at')
            ->get(['price', 'fetched_at']);

        return view('competitors.history', [
            'competitor' => $competitor,
            'product' => $product,
            'prices' => $prices,
            'competitors' => $competitors,
            'ownHistory' => $ownHistory,
            'days' => $days,
        ]);
    }
}
