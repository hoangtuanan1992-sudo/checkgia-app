<?php

namespace App\Http\Controllers;

use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardExportController extends Controller
{
    public function products(Request $request): Response
    {
        $userId = $request->user()->effectiveUserId();

        $groupId = $request->query('group_id');
        $groupFilter = null;
        if ($groupId === '__none__') {
            $groupFilter = '__none__';
        } elseif (is_numeric($groupId)) {
            $groupFilter = (int) $groupId;
        }

        $competitorSites = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name']);

        $productsQuery = Product::query()
            ->where('user_id', $userId)
            ->with([
                'group:id,name',
                'competitors' => function ($q) {
                    $q->with(['prices' => function ($p) {
                        $p->latest('fetched_at')->limit(1);
                    }]);
                },
            ])
            ->latest();

        if ($groupFilter === '__none__') {
            $productsQuery->whereNull('product_group_id');
        } elseif (is_int($groupFilter)) {
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
            $latestTimes = $product->competitors->map(fn ($c) => $c->prices->first()?->fetched_at)->filter();
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
                $cPrice = $c?->prices->first()?->price;
                $cPrice = is_null($cPrice) ? null : (int) $cPrice;
                $diff = is_null($cPrice) ? null : ($cPrice - $own);

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
}
