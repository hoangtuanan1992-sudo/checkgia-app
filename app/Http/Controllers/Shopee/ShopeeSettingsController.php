<?php

namespace App\Http\Controllers\Shopee;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ShopeeCompetitor;
use App\Models\ShopeeProduct;
use App\Models\ShopeeShop;
use App\Models\User;
use App\Models\UserNotificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ShopeeSettingsController extends Controller
{
    public function index(Request $request): View
    {
        $ownerId = $request->user()->effectiveUserId();

        $notification = UserNotificationSetting::query()->firstOrCreate([
            'user_id' => $ownerId,
        ]);

        $shops = ShopeeShop::query()
            ->where('user_id', $ownerId)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return view('shopee.settings-owner', [
            'shops' => $shops,
            'notification' => $notification,
        ]);
    }

    public function storeShop(Request $request): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $maxPos = (int) ShopeeShop::query()->where('user_id', $ownerId)->max('position');

        ShopeeShop::create([
            'user_id' => $ownerId,
            'name' => trim((string) $data['name']),
            'is_own' => false,
            'position' => $maxPos + 1,
        ]);

        return back()->with('status', 'Đã thêm shop');
    }

    public function destroyShop(Request $request, ShopeeShop $shop): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();
        abort_unless((int) $shop->user_id === (int) $ownerId, 404);

        $shop->delete();

        return back()->with('status', 'Đã xoá shop');
    }

    public function moveShop(Request $request, ShopeeShop $shop): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();
        abort_unless((int) $shop->user_id === (int) $ownerId, 404);

        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        DB::transaction(function () use ($ownerId, $shop, $data) {
            $shops = ShopeeShop::query()
                ->where('user_id', $ownerId)
                ->orderBy('position')
                ->orderBy('name')
                ->lockForUpdate()
                ->get();

            $shops->values()->each(function ($s, $i) {
                if ((int) $s->position !== (int) $i) {
                    $s->position = $i;
                    $s->save();
                }
            });

            $shops = ShopeeShop::query()
                ->where('user_id', $ownerId)
                ->orderBy('position')
                ->orderBy('name')
                ->lockForUpdate()
                ->get()
                ->values();

            $idx = $shops->search(fn ($s) => (int) $s->id === (int) $shop->id);
            if ($idx === false) {
                return;
            }

            $to = $data['direction'] === 'up' ? $idx - 1 : $idx + 1;
            if ($to < 0 || $to >= $shops->count()) {
                return;
            }

            $a = $shops[$idx];
            $b = $shops[$to];

            $aPos = (int) $a->position;
            $bPos = (int) $b->position;

            $a->position = $bPos;
            $b->position = $aPos;
            $a->save();
            $b->save();
        });

        return back()->with('status', 'Đã cập nhật vị trí');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();

        $data = $request->validate([
            'own_url' => ['required', 'url', 'max:2048'],
            'price_pick' => ['nullable', 'string', 'in:low,high'],
            'competitor_urls' => ['nullable', 'array'],
            'competitor_price_picks' => ['nullable', 'array'],
            'competitor_price_picks.*' => ['nullable', 'string', 'in:low,high'],
        ]);

        $limit = User::resolveProductLimitById($ownerId);
        $used = (int) Product::query()->where('user_id', $ownerId)->count()
            + (int) ShopeeProduct::query()->where('user_id', $ownerId)->count();
        if ($used >= $limit) {
            return back()
                ->withInput()
                ->with('status', 'Bạn đã đến giới hạn so sánh '.$limit.' sản phẩm, để dùng tiếp hãy xóa bớt sản phẩm so sánh hoặc liên hệ admin để nâng cấp tài khoản');
        }

        $product = ShopeeProduct::create([
            'user_id' => $ownerId,
            'own_url' => trim((string) $data['own_url']),
            'price_pick' => (string) ($data['price_pick'] ?? 'low'),
            'is_enabled' => true,
        ]);

        $shops = ShopeeShop::query()->where('user_id', $ownerId)->get()->keyBy('id');
        $urls = (array) ($data['competitor_urls'] ?? []);
        $picks = (array) ($data['competitor_price_picks'] ?? []);
        foreach ($urls as $shopId => $url) {
            $shopId = (int) $shopId;
            $url = is_string($url) ? trim($url) : '';
            if ($url === '') {
                continue;
            }
            $shop = $shops->get($shopId);
            if (! $shop) {
                continue;
            }

            ShopeeCompetitor::create([
                'shopee_product_id' => (int) $product->id,
                'shopee_shop_id' => (int) $shopId,
                'url' => $url,
                'price_pick' => in_array(($picks[$shopId] ?? null), ['low', 'high'], true) ? (string) $picks[$shopId] : 'low',
                'is_enabled' => true,
            ]);
        }

        return redirect()
            ->route('shopee.dashboard')
            ->with('status', 'Đã thêm sản phẩm Shopee')
            ->with('shopee_pending_product_id', (int) $product->id);
    }

    public function toggleProduct(Request $request, ShopeeProduct $product): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();
        abort_unless((int) $product->user_id === (int) $ownerId, 404);

        $product->update([
            'is_enabled' => ! (bool) $product->is_enabled,
        ]);

        return back()->with('status', 'Đã cập nhật');
    }

    public function destroyProduct(Request $request, ShopeeProduct $product): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();
        abort_unless((int) $product->user_id === (int) $ownerId, 404);

        $product->delete();

        return back()->with('status', 'Đã xoá sản phẩm');
    }

    public function updateOwnUrl(Request $request, ShopeeProduct $product): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();
        abort_unless((int) $product->user_id === (int) $ownerId, 404);

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'own_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if ((bool) ($data['clear'] ?? false)) {
            $product->own_url = '';
        } else {
            $product->own_url = trim((string) ($data['own_url'] ?? ''));
        }
        $product->save();

        return back()->with('status', 'Đã cập nhật URL');
    }

    public function upsertCompetitorUrl(Request $request, ShopeeProduct $product, ShopeeShop $shop): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();
        abort_unless((int) $product->user_id === (int) $ownerId && (int) $shop->user_id === (int) $ownerId, 404);

        $data = $request->validate([
            'clear' => ['nullable', 'boolean'],
            'url' => ['nullable', 'url', 'max:2048'],
        ]);

        $clear = (bool) ($data['clear'] ?? false);
        $url = trim((string) ($data['url'] ?? ''));

        $competitor = ShopeeCompetitor::query()->firstOrNew([
            'shopee_product_id' => (int) $product->id,
            'shopee_shop_id' => (int) $shop->id,
        ]);

        if ($clear || $url === '') {
            if ($competitor->exists) {
                $competitor->delete();
            }

            return back()->with('status', 'Đã xoá URL');
        }

        $competitor->url = $url;
        $competitor->is_enabled = true;
        if (! $competitor->exists) {
            $competitor->price_adjustment = 0;
        }
        $competitor->save();

        return back()->with('status', 'Đã cập nhật URL');
    }

    public function updateCompetitorAdjustment(Request $request, ShopeeCompetitor $competitor): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();
        $product = $competitor->product;
        abort_unless($product && (int) $product->user_id === (int) $ownerId, 404);

        $data = $request->validate([
            'price_adjustment' => ['required', 'integer', 'min:-999999999', 'max:999999999'],
        ]);

        $competitor->update([
            'price_adjustment' => (int) $data['price_adjustment'],
        ]);

        return back()->with('status', 'Đã cập nhật điều chỉnh');
    }
}
