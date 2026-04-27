<?php

namespace App\Http\Controllers;

use App\Models\CompetitorPrice;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->effectiveUserId();
        $since = now()->subDays(7);

        $products = Product::query()
            ->where('user_id', $userId)
            ->with([
                'competitors' => function ($q) use ($userId) {
                    $q->with([
                        'competitorSite:id,name',
                        'prices' => function ($p) {
                            $p->latest('fetched_at')->limit(1);
                        },
                    ])
                        ->whereNotNull('competitor_site_id')
                        ->whereHas('competitorSite', function ($s) use ($userId) {
                            $s->where('user_id', $userId);
                        });
                },
            ])
            ->get(['id', 'name', 'price']);

        $topProductsCheaper = $products
            ->map(function ($product) {
                $own = (int) $product->price;
                $best = null;

                foreach ($product->competitors as $c) {
                    $p = $c->prices->first()?->price;
                    if (is_null($p)) {
                        continue;
                    }

                    $p = (int) $p;
                    if (is_null($best) || $p < $best['price']) {
                        $best = [
                            'competitor' => $c,
                            'price' => $p,
                        ];
                    }
                }

                if (! $best || $best['price'] >= $own) {
                    return null;
                }

                return [
                    'product' => $product,
                    'own' => $own,
                    'best_competitor' => $best['competitor'],
                    'best_price' => $best['price'],
                    'diff' => $own - $best['price'],
                ];
            })
            ->filter()
            ->sortByDesc('diff')
            ->take(10)
            ->values();

        $topCompetitorsOftenCheaper = $products
            ->flatMap(function ($product) {
                $own = (int) $product->price;

                return $product->competitors->map(function ($c) use ($own) {
                    $p = $c->prices->first()?->price;
                    if (is_null($p)) {
                        return null;
                    }

                    $p = (int) $p;
                    if ($p >= $own) {
                        return null;
                    }

                    return [
                        'site_id' => $c->competitor_site_id,
                        'site_name' => $c->competitorSite?->name ?? $c->name,
                        'diff' => $own - $p,
                    ];
                })->filter()->values();
            })
            ->groupBy('site_id')
            ->map(function ($rows, $siteId) {
                $count = $rows->count();
                $avgDiff = (int) round($rows->avg('diff'));

                return [
                    'site_id' => (int) $siteId,
                    'site_name' => (string) ($rows->first()['site_name'] ?? 'Đối thủ'),
                    'count' => $count,
                    'avg_diff' => $avgDiff,
                ];
            })
            ->sortByDesc('count')
            ->take(10)
            ->values();

        $ownChangesCount = ProductPriceHistory::query()
            ->where('fetched_at', '>=', $since)
            ->whereIn('product_id', Product::query()->where('user_id', $userId)->pluck('id'))
            ->count();

        $competitorChangesCount = CompetitorPrice::query()
            ->where('fetched_at', '>=', $since)
            ->whereIn('competitor_id', function ($sub) use ($userId) {
                $sub->from('competitors')
                    ->select('competitors.id')
                    ->join('products', 'products.id', '=', 'competitors.product_id')
                    ->join('competitor_sites', 'competitor_sites.id', '=', 'competitors.competitor_site_id')
                    ->where('products.user_id', $userId)
                    ->where('competitor_sites.user_id', $userId);
            })
            ->count();

        $topProductsMostChanges = ProductPriceHistory::query()
            ->select('product_id', DB::raw('COUNT(*) as changes'))
            ->where('fetched_at', '>=', $since)
            ->whereIn('product_id', Product::query()->where('user_id', $userId)->pluck('id'))
            ->groupBy('product_id')
            ->orderByDesc('changes')
            ->limit(10)
            ->get()
            ->map(function ($row) use ($products) {
                $product = $products->firstWhere('id', (int) $row->product_id);

                return [
                    'product' => $product,
                    'changes' => (int) $row->changes,
                ];
            })
            ->filter(fn ($r) => $r['product'])
            ->values();

        return view('dashboard.reports', [
            'topProductsCheaper' => $topProductsCheaper,
            'topCompetitorsOftenCheaper' => $topCompetitorsOftenCheaper,
            'ownChangesCount' => $ownChangesCount,
            'competitorChangesCount' => $competitorChangesCount,
            'topProductsMostChanges' => $topProductsMostChanges,
        ]);
    }
}
