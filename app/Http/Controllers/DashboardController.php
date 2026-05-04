<?php

namespace App\Http\Controllers;

use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
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

        $competitorSitesQuery = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('name');
        $this->constrainToIds($competitorSitesQuery, $restrictedCompetitorSiteIds, 'id');
        $competitorSites = $competitorSitesQuery
            ->get(['id', 'name', 'position']);

        $productGroupsQuery = ProductGroup::query()
            ->where('user_id', $userId)
            ->orderBy('name');
        if ($hasProductGroupRestriction) {
            $productGroupsQuery->whereIn('id', $productGroupRestrictionIds);
        }
        $productGroups = $productGroupsQuery
            ->get(['id', 'name']);

        $productsQuery = Product::query()
            ->with(['group:id,name', 'competitors' => function ($q) use ($restrictedCompetitorSiteIds) {
                $this->constrainToIds($q, $restrictedCompetitorSiteIds, 'competitor_site_id');
                $q->with(['prices' => function ($p) {
                    $p->latest('fetched_at')->limit(2);
                }, 'competitorSite']);
            }])
            ->where('user_id', $userId)
            ->latest();

        if ($hasProductGroupRestriction) {
            $productsQuery->whereIn('product_group_id', $productGroupRestrictionIds);
        }

        $products = $productsQuery
            ->get(['id', 'user_id', 'product_group_id', 'name', 'price', 'product_url', 'last_scraped_at', 'created_at']);

        $tz = 'Asia/Ho_Chi_Minh';
        $now = Carbon::now($tz);
        $priceEvents = [];
        foreach ($products as $product) {
            foreach ($product->competitors as $competitor) {
                $latest = $competitor->prices->get(0);
                $prev = $competitor->prices->get(1);
                if (! $latest || ! $prev) {
                    continue;
                }
                $delta = (int) $latest->price - (int) $prev->price;
                if ($delta === 0) {
                    continue;
                }

                $eventTime = $latest->fetched_at ? Carbon::parse($latest->fetched_at)->setTimezone($tz) : null;
                if (! $eventTime) {
                    continue;
                }

                $priceEvents[] = [
                    'at' => $eventTime,
                    'ago' => $this->agoText($eventTime, $now),
                    'product_id' => (int) $product->id,
                    'product_name' => (string) $product->name,
                    'competitor_id' => (int) $competitor->id,
                    'site_name' => (string) ($competitor->competitorSite?->name ?? $competitor->name),
                    'delta' => $delta,
                    'delta_text' => $this->deltaText($delta),
                ];
            }
        }

        usort($priceEvents, function ($a, $b) {
            return $b['at'] <=> $a['at'];
        });
        $priceEvents = array_slice($priceEvents, 0, 6);
        $compareMatchCounts = $this->compareMatchCounts($userId, $products, $competitorSites);

        return view('dashboard.index', [
            'products' => $products,
            'competitorSites' => $competitorSites,
            'productGroups' => $productGroups,
            'priceEvents' => $priceEvents,
            'compareMatchEnabled' => User::compareMatchEnabledForId($userId),
            'compareMatchCounts' => $compareMatchCounts,
        ]);
    }

    private function agoText(Carbon $at, Carbon $now): string
    {
        $seconds = max(0, $at->diffInSeconds($now));
        if ($seconds < 60) {
            return 'vừa xong';
        }
        if ($seconds < 3600) {
            return (int) floor($seconds / 60).' phút trước';
        }
        if ($seconds < 86400) {
            return (int) floor($seconds / 3600).' giờ trước';
        }
        if ($seconds < 2592000) {
            return (int) floor($seconds / 86400).' ngày trước';
        }
        if ($seconds < 31536000) {
            return (int) floor($seconds / 2592000).' tháng trước';
        }

        return (int) floor($seconds / 31536000).' năm trước';
    }

    private function deltaText(int $delta): string
    {
        $abs = abs($delta);
        if ($abs >= 1000000) {
            $m = $abs / 1000000;
            $v = abs($m - round($m)) < 0.00001 ? (string) (int) round($m) : number_format($m, 1, ',', '.');

            return $v.'M';
        }
        if ($abs >= 1000) {
            $k = (int) round($abs / 1000);

            return $k.'K';
        }

        return (string) $abs;
    }

    private function compareMatchCounts(int $userId, $products, $competitorSites): array
    {
        $productIds = $products->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $siteIds = $competitorSites->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $totalCells = count($productIds) * count($siteIds);

        if ($totalCells === 0) {
            return [
                'allCells' => 0,
                'emptyCells' => 0,
                'emptyCheckedCells' => 0,
                'emptySkipRemainingCells' => 0,
            ];
        }

        $emptyPairs = [];
        foreach ($products as $product) {
            $map = $product->competitors->keyBy('competitor_site_id');
            foreach ($competitorSites as $site) {
                $competitor = $map->get($site->id);
                if (! $competitor || trim((string) $competitor->url) === '') {
                    $emptyPairs[(int) $product->id.'|'.(int) $site->id] = true;
                }
            }
        }

        $emptyCells = count($emptyPairs);
        $emptyCheckedCells = 0;

        if ($emptyCells > 0 && Schema::hasTable('compare_match_runs') && Schema::hasTable('compare_match_run_items')) {
            $checkedPairs = DB::table('compare_match_run_items as i')
                ->join('compare_match_runs as r', 'r.id', '=', 'i.compare_match_run_id')
                ->where('r.user_id', $userId)
                ->whereIn('i.product_id', $productIds)
                ->whereIn('i.competitor_site_id', $siteIds)
                ->whereIn('i.status', ['matched', 'no_candidates', 'no_match'])
                ->select('i.product_id', 'i.competitor_site_id')
                ->distinct()
                ->get();

            foreach ($checkedPairs as $pair) {
                if (isset($emptyPairs[(int) $pair->product_id.'|'.(int) $pair->competitor_site_id])) {
                    $emptyCheckedCells++;
                }
            }
        }

        return [
            'allCells' => $totalCells,
            'emptyCells' => $emptyCells,
            'emptyCheckedCells' => $emptyCheckedCells,
            'emptySkipRemainingCells' => max(0, $emptyCells - $emptyCheckedCells),
        ];
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
