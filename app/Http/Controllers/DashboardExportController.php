<?php

namespace App\Http\Controllers;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class DashboardExportController extends Controller
{
    public function products(Request $request): Response
    {
        $authUser = $request->user();
        $userId = $authUser->effectiveUserId();
        $productGroupRestrictionIds = $authUser->isViewer() ? $authUser->visibleProductGroupIds() : [];
        $hasProductGroupRestriction = $authUser->isViewer() && $productGroupRestrictionIds !== [];
        $viewerCompetitorGroupIds = $authUser->isViewer() ? $authUser->visibleCompetitorSiteGroupIds() : [];
        $viewerCompetitorSiteIds = $authUser->isViewer()
            ? $this->competitorSiteIdsForGroups($userId, $viewerCompetitorGroupIds)
            : null;

        $groupId = $request->query('group_id');
        $groupFilter = null;
        if ($groupId === '__none__') {
            $groupFilter = '__none__';
        } elseif (is_numeric($groupId)) {
            $groupFilter = (int) $groupId;
        }

        $selectedCompetitorGroupId = null;
        $competitorGroupId = $request->query('competitor_group_id', $request->query('competitor_group'));
        if (is_numeric($competitorGroupId) && Schema::hasTable('competitor_site_groups')) {
            $competitorGroupQuery = CompetitorSiteGroup::query()
                ->where('user_id', $userId)
                ->where('id', (int) $competitorGroupId);
            if ($authUser->isViewer() && $viewerCompetitorGroupIds !== []) {
                $competitorGroupQuery->whereIn('id', $viewerCompetitorGroupIds);
            }
            if ($competitorGroupQuery->exists()) {
                $selectedCompetitorGroupId = (int) $competitorGroupId;
            }
        }

        $selectedCompetitorSiteIds = $selectedCompetitorGroupId
            ? $this->competitorSiteIdsForGroups($userId, [$selectedCompetitorGroupId])
            : null;
        $restrictedCompetitorSiteIds = $this->intersectIdFilters($viewerCompetitorSiteIds, $selectedCompetitorSiteIds);

        $competitorSitesQuery = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('name');
        $this->constrainToIds($competitorSitesQuery, $restrictedCompetitorSiteIds, 'id');
        $competitorSites = $competitorSitesQuery
            ->get(['id', 'name']);

        $productsQuery = Product::query()
            ->where('user_id', $userId)
            ->with([
                'group:id,name',
                'competitors' => function ($q) use ($restrictedCompetitorSiteIds) {
                    $this->constrainToIds($q, $restrictedCompetitorSiteIds, 'competitor_site_id');
                    $q->with(['prices' => function ($p) {
                        $p->latest('fetched_at')->limit(1);
                    }]);
                },
            ])
            ->latest();

        if ($hasProductGroupRestriction) {
            $productsQuery->whereIn('product_group_id', $productGroupRestrictionIds);
        }

        if ($groupFilter === '__none__') {
            if ($hasProductGroupRestriction) {
                $productsQuery->whereRaw('1 = 0');
            } else {
                $productsQuery->whereNull('product_group_id');
            }
        } elseif (is_int($groupFilter)) {
            if ($hasProductGroupRestriction && ! in_array($groupFilter, $productGroupRestrictionIds, true)) {
                abort(404);
            }
            $productsQuery->where('product_group_id', $groupFilter);
        }

        $products = $productsQuery->get(['id', 'product_group_id', 'name', 'price', 'product_url', 'last_scraped_at']);

        $groupName = null;
        if (is_int($groupFilter)) {
            $groupName = ProductGroup::query()
                ->where('user_id', $userId)
                ->where('id', $groupFilter)
                ->value('name');
        } elseif ($groupFilter === '__none__') {
            $groupName = 'Chua-co-nhom';
        }

        $now = now()->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d_H-i');
        $suffix = $groupName ? '_'.$this->slug((string) $groupName) : '_tat-ca';
        $filename = 'checkgia_so-sanh'.$suffix.'_'.$now.'.xls';

        $html = $this->renderExcelHtml($products, $competitorSites);
        $content = "\xEF\xBB\xBF".$html;

        return response($content, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function renderExcelHtml($products, $competitorSites): string
    {
        $thead = '<tr>'
            .'<th>#</th>'
            .'<th>ID</th>'
            .'<th>Nhóm</th>'
            .'<th>Tên sản phẩm</th>'
            .'<th>URL</th>'
            .'<th>Giá của bạn</th>';

        foreach ($competitorSites as $site) {
            $name = htmlspecialchars((string) $site->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $thead .= '<th>'.$name.' URL</th><th>'.$name.' Giá</th><th>'.$name.' Chênh</th>';
        }

        $thead .= '<th>Cập nhật</th></tr>';

        $rows = '';
        foreach ($products as $i => $product) {
            $own = (int) $product->price;
            $map = $product->competitors->keyBy('competitor_site_id');
            $latestTimes = $product->competitors->map(fn ($c) => $c->price_missing_at ? null : $c->prices->first()?->fetched_at)->filter();
            $lastTime = $latestTimes->max();
            $lastUpdated = collect([$lastTime, $product->last_scraped_at])->filter()->max();

            $rows .= '<tr>';
            $rows .= '<td>'.($i + 1).'</td>';
            $rows .= '<td>'.(int) $product->id.'</td>';
            $rows .= '<td>'.htmlspecialchars((string) ($product->group?->name ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
            $rows .= '<td>'.htmlspecialchars((string) $product->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
            $rows .= '<td>'.htmlspecialchars((string) ($product->product_url ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
            $rows .= '<td>'.$own.'</td>';

            foreach ($competitorSites as $site) {
                $c = $map->get($site->id);
                $url = $c?->url ?? '';
                $cPrice = $c?->price_missing_at ? null : $c?->prices->first()?->price;
                $cPrice = is_null($cPrice) ? null : (int) $cPrice;
                $diff = is_null($cPrice) || $own <= 0 ? null : ($cPrice - $own);

                $rows .= '<td>'.htmlspecialchars((string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
                $rows .= '<td>'.(is_null($cPrice) ? '' : $cPrice).'</td>';
                $rows .= '<td>'.(is_null($diff) ? '' : $diff).'</td>';
            }

            $rows .= '<td>'.htmlspecialchars((string) ($lastUpdated?->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
            $rows .= '</tr>';
        }

        return '<html><head><meta charset="UTF-8"></head><body>'
            .'<table border="1" cellspacing="0" cellpadding="4">'
            .'<thead>'.$thead.'</thead>'
            .'<tbody>'.$rows.'</tbody>'
            .'</table>'
            .'</body></html>';
    }

    private function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '-', $value) ?? $value;
        $value = trim($value, '-');

        return $value === '' ? 'nhom' : $value;
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

    /**
     * @param array<int, int>|null $base
     * @param array<int, int>|null $selected
     * @return array<int, int>|null
     */
    private function intersectIdFilters(?array $base, ?array $selected): ?array
    {
        if (! is_array($base)) {
            return $selected;
        }

        if (! is_array($selected)) {
            return $base;
        }

        return array_values(array_intersect($base, $selected));
    }
}
