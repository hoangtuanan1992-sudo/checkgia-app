<?php

namespace App\Http\Controllers;

use App\Models\CompetitorPrice;
use App\Models\Product;
use App\Models\ProductPriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $authUser = $request->user();
        $userId = $authUser->effectiveUserId();
        $productGroupRestrictionIds = $authUser->isViewer() ? $authUser->visibleProductGroupIds() : [];
        $hasProductGroupRestriction = $authUser->isViewer() && $productGroupRestrictionIds !== [];
        $restrictedCompetitorSiteIds = $authUser->isViewer()
            ? $this->competitorSiteIdsForGroups($userId, $authUser->visibleCompetitorSiteGroupIds())
            : null;
        $since = now()->subDays(7);

        $productsQuery = Product::query()
            ->where('user_id', $userId)
            ->with([
                'competitors' => function ($q) use ($restrictedCompetitorSiteIds) {
                    $this->constrainToIds($q, $restrictedCompetitorSiteIds, 'competitor_site_id');
                    $q->with([
                        'competitorSite:id,name',
                        'prices' => function ($p) {
                            $p->latest('fetched_at')->limit(1);
                        },
                    ]);
                },
            ]);

        if ($hasProductGroupRestriction) {
            $productsQuery->whereIn('product_group_id', $productGroupRestrictionIds);
        }

        $products = $productsQuery
            ->get(['id', 'name', 'price']);
        $visibleProductIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $topProductsCheaper = $products
            ->map(function ($product) {
                $own = (int) $product->price;
                if ($own <= 0) {
                    return null;
                }
                $best = null;

                foreach ($product->competitors as $c) {
                    if ($c->price_missing_at) {
                        continue;
                    }
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
                if ($own <= 0) {
                    return collect();
                }

                return $product->competitors->map(function ($c) use ($own) {
                    if ($c->price_missing_at) {
                        return null;
                    }
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
            ->whereIn('product_id', $visibleProductIds)
            ->count();

        $competitorChangesCount = CompetitorPrice::query()
            ->where('fetched_at', '>=', $since)
            ->whereIn('competitor_id', function ($sub) use ($userId, $visibleProductIds, $restrictedCompetitorSiteIds) {
                $sub->from('competitors')
                    ->select('competitors.id')
                    ->join('products', 'products.id', '=', 'competitors.product_id')
                    ->where('products.user_id', $userId)
                    ->whereIn('products.id', $visibleProductIds);
                $this->constrainToIds($sub, $restrictedCompetitorSiteIds, 'competitors.competitor_site_id');
            })
            ->count();

        $topProductsMostChanges = ProductPriceHistory::query()
            ->select('product_id', DB::raw('COUNT(*) as changes'))
            ->where('fetched_at', '>=', $since)
            ->whereIn('product_id', $visibleProductIds)
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

    /**
     * @param array<int, int> $groupIds
     * @return array<int, int>|null
     */
    private function competitorSiteIdsForGroups(int $userId, array $groupIds): ?array
    {
        if ($groupIds === []) {
            return null;
        }

        if (! Schema::hasTable('competitor_site_groups') || ! Schema::hasTable('competitor_site_group_sites')) {
            return [];
        }

        return DB::table('competitor_site_group_sites')
            ->join('competitor_site_groups', 'competitor_site_groups.id', '=', 'competitor_site_group_sites.competitor_site_group_id')
            ->where('competitor_site_groups.user_id', $userId)
            ->whereIn('competitor_site_group_sites.competitor_site_group_id', $groupIds)
            ->pluck('competitor_site_group_sites.competitor_site_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param array<int, int>|null $ids
     */
    private function constrainToIds($query, ?array $ids, string $column): void
    {
        if (! is_array($ids)) {
            return;
        }

        if ($ids === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($column, $ids);
    }
}
