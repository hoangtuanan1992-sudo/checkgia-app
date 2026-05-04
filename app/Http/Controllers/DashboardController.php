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

        $this->applyComparisonFilters($productsQuery, $request);
        $this->applyComparisonSort($productsQuery, $request, $restrictedCompetitorSiteIds);

        $perPage = $this->comparisonPerPage($request);
        $products = $productsQuery
            ->paginate($perPage, ['products.id', 'products.user_id', 'products.product_group_id', 'products.name', 'products.price', 'products.product_url', 'products.last_scraped_at', 'products.created_at'], 'page')
            ->withQueryString();
        $productsTotal = $products->total();
        $comparisonMeta = [
            'page' => $products->currentPage(),
            'pageCount' => $products->lastPage(),
            'perPage' => $perPage,
            'total' => $productsTotal,
            'shown' => $products->count(),
        ];

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
        $compareMatchEnabled = User::compareMatchEnabledForId($userId);
        $compareMatchProducts = collect();
        if ($compareMatchEnabled) {
            $compareMatchProductsQuery = Product::query()
                ->with(['competitors' => function ($q) use ($restrictedCompetitorSiteIds) {
                    $this->constrainToIds($q, $restrictedCompetitorSiteIds, 'competitor_site_id');
                }])
                ->where('user_id', $userId);
            if ($hasProductGroupRestriction) {
                $compareMatchProductsQuery->whereIn('product_group_id', $productGroupRestrictionIds);
            }
            $compareMatchProducts = $compareMatchProductsQuery->get(['id', 'user_id', 'product_group_id']);
        }
        $compareMatchCounts = $compareMatchEnabled
            ? $this->compareMatchCounts($userId, $compareMatchProducts, $competitorSites)
            : ['allCells' => 0, 'emptyCells' => 0, 'emptyCheckedCells' => 0, 'emptySkipRemainingCells' => 0];

        return view('dashboard.index', [
            'products' => $products,
            'productsTotal' => $productsTotal,
            'comparisonMeta' => $comparisonMeta,
            'competitorSites' => $competitorSites,
            'productGroups' => $productGroups,
            'priceEvents' => $priceEvents,
            'compareMatchEnabled' => $compareMatchEnabled,
            'compareMatchCounts' => $compareMatchCounts,
        ]);
    }

    private function applyComparisonFilters($query, Request $request): void
    {
        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $q->orWhere('products.id', (int) $search);
                }
            });
        }

        $group = (string) $request->query('group', '');
        if ($group === '__none__') {
            $query->whereNull('products.product_group_id');
        } elseif (ctype_digit($group)) {
            $query->where('products.product_group_id', (int) $group);
        }
    }

    private function applyComparisonSort($query, Request $request, ?array $restrictedCompetitorSiteIds): void
    {
        $sort = (string) $request->query('sort', 'row_asc');

        if ($sort === 'price_asc') {
            $query->orderBy('products.price')->orderByDesc('products.id');

            return;
        }

        if ($sort === 'price_desc') {
            $query->orderByDesc('products.price')->orderByDesc('products.id');

            return;
        }

        if (in_array($sort, ['last_desc', 'last_asc'], true)) {
            $latest = DB::table('competitor_prices as cp')
                ->join('competitors as c', 'c.id', '=', 'cp.competitor_id')
                ->whereColumn('c.product_id', 'products.id')
                ->selectRaw('MAX(cp.fetched_at)');
            $this->constrainToIds($latest, $restrictedCompetitorSiteIds, 'c.competitor_site_id');

            $query->select('products.*')
                ->selectSub($latest, 'latest_competitor_fetched_at');

            $direction = $sort === 'last_asc' ? 'asc' : 'desc';
            $query->orderByRaw('COALESCE(latest_competitor_fetched_at, products.last_scraped_at, products.updated_at) '.$direction)
                ->orderByDesc('products.id');

            return;
        }

        if (in_array($sort, ['diff_asc', 'diff_desc'], true)) {
            $minDiff = DB::table('competitors as c')
                ->whereColumn('c.product_id', 'products.id')
                ->selectRaw('MIN((SELECT cp.price FROM competitor_prices cp WHERE cp.competitor_id = c.id ORDER BY cp.fetched_at DESC, cp.id DESC LIMIT 1) + COALESCE(c.price_adjustment, 0) - products.price)');
            $this->constrainToIds($minDiff, $restrictedCompetitorSiteIds, 'c.competitor_site_id');

            $query->select('products.*')
                ->selectSub($minDiff, 'min_diff_sort');

            $direction = $sort === 'diff_asc' ? 'asc' : 'desc';
            $query->orderByRaw('min_diff_sort IS NULL')
                ->orderBy('min_diff_sort', $direction)
                ->orderByDesc('products.id');

            return;
        }

        $query->orderByDesc('products.created_at')->orderByDesc('products.id');
    }

    private function comparisonPerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 50);
        $allowed = [20, 50, 100, 200, 500];

        return in_array($perPage, $allowed, true) ? $perPage : 50;
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
