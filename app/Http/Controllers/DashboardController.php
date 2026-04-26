<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\CompetitorSite;
use App\Models\Product;
use App\Models\ProductGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->effectiveUserId();

        $competitorSites = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'position']);

        $productGroups = ProductGroup::query()
            ->where('user_id', $userId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $products = Product::query()
            ->with(['group:id,name', 'competitors' => function ($q) {
                $q->with(['prices' => function ($p) {
                    $p->latest('fetched_at')->limit(2);
                }, 'competitorSite']);
            }])
            ->where('user_id', $userId)
            ->latest()
            ->paginate(50, ['id', 'user_id', 'product_group_id', 'name', 'price', 'product_url', 'last_scraped_at', 'created_at'])
            ->withQueryString();

        $competitorsForEvents = Competitor::query()
            ->whereHas('product', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with([
                'product:id,name',
                'competitorSite:id,name',
                'prices' => function ($p) {
                    $p->latest('fetched_at')->limit(2);
                },
            ])
            ->get(['id', 'product_id', 'competitor_site_id', 'name']);

        $tz = 'Asia/Ho_Chi_Minh';
        $now = Carbon::now($tz);
        $priceEvents = [];
        foreach ($competitorsForEvents as $competitor) {
            $product = $competitor->product;
            if (! $product) {
                continue;
            }

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

        usort($priceEvents, function ($a, $b) {
            return $b['at'] <=> $a['at'];
        });
        $priceEvents = array_slice($priceEvents, 0, 6);

        return view('dashboard.index', [
            'products' => $products,
            'competitorSites' => $competitorSites,
            'productGroups' => $productGroups,
            'priceEvents' => $priceEvents,
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
}
