<?php

namespace App\Http\Controllers\Shopee;

use App\Http\Controllers\Controller;
use App\Models\ShopeeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopeeDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $ownerId = $user->effectiveUserId();

        $items = ShopeeItem::query()
            ->where('user_id', $ownerId)
            ->orderByDesc('is_enabled')
            ->orderByRaw('last_scraped_at is null desc')
            ->orderBy('last_scraped_at')
            ->get();

        return view('shopee.dashboard', [
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $ownerId = $user->effectiveUserId();

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
        ]);

        ShopeeItem::create([
            'user_id' => $ownerId,
            'name' => trim((string) ($data['name'] ?? '')) ?: null,
            'url' => trim((string) $data['url']),
            'is_enabled' => true,
        ]);

        return back()->with('status', 'Đã thêm sản phẩm Shopee');
    }

    public function toggle(Request $request, ShopeeItem $item): RedirectResponse
    {
        $user = $request->user();
        $ownerId = $user->effectiveUserId();
        abort_unless((int) $item->user_id === (int) $ownerId, 404);

        $item->update([
            'is_enabled' => ! (bool) $item->is_enabled,
        ]);

        return back()->with('status', 'Đã cập nhật');
    }

    public function destroy(Request $request, ShopeeItem $item): RedirectResponse
    {
        $user = $request->user();
        $ownerId = $user->effectiveUserId();
        abort_unless((int) $item->user_id === (int) $ownerId, 404);

        $item->delete();

        return back()->with('status', 'Đã xoá');
    }
}
