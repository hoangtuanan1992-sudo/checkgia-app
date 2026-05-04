<?php

namespace App\Http\Controllers;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\UserNotificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View
    {
        $authUser = $request->user();
        $ownerId = $authUser->effectiveUserId();
        $owner = User::query()->find($ownerId);

        $isImpersonating = $authUser->isAdmin() && (int) session('impersonate_user_id', 0) > 0;
        $user = $isImpersonating ? (User::query()->find($ownerId) ?? $authUser) : $authUser;

        $notification = UserNotificationSetting::query()->firstOrCreate([
            'user_id' => $ownerId,
        ]);

        $subUsers = collect();
        $groups = collect();
        $competitorSites = collect();
        $competitorGroups = collect();

        if (! $user->isViewer()) {
            $subUsers = User::query()
                ->where('parent_user_id', $ownerId)
                ->orderBy('email')
                ->get(['id', 'name', 'email', 'created_at', 'visible_product_group_ids', 'visible_competitor_site_group_ids']);

            $groups = ProductGroup::query()
                ->where('user_id', $ownerId)
                ->orderBy('name')
                ->get(['id', 'name', 'created_at']);

            $competitorSites = CompetitorSite::query()
                ->where('user_id', $ownerId)
                ->orderBy('position')
                ->orderBy('name')
                ->get(['id', 'name', 'domain']);

            if (Schema::hasTable('competitor_site_groups')) {
                $competitorGroups = CompetitorSiteGroup::query()
                    ->where('user_id', $ownerId)
                    ->with(['competitorSites:id,name'])
                    ->orderBy('name')
                    ->get(['id', 'user_id', 'name', 'created_at']);
            }
        }

        return view('account.index', [
            'user' => $user,
            'authUser' => $authUser,
            'isImpersonating' => $isImpersonating,
            'owner' => $owner,
            'notification' => $notification,
            'subUsers' => $subUsers,
            'groups' => $groups,
            'competitorSites' => $competitorSites,
            'competitorGroups' => $competitorGroups,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }

        $user->update([
            'password' => $data['password'],
        ]);

        return back()->with('status', 'Đã đổi mật khẩu');
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        $ownerId = $request->user()->effectiveUserId();

        $data = $request->validate([
            'email_enabled' => ['nullable', 'boolean'],
            'email_to' => ['nullable', 'email', 'max:255'],
            'telegram_enabled' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:2048'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255'],
            'alert_competitor_cheaper_percent' => ['nullable', 'integer', 'min:1', 'max:95'],
            'alert_competitor_drop_amount' => ['nullable', 'integer', 'min:1'],
            'notify_all_price_changes' => ['nullable', 'boolean'],
            'notify_all_price_changes_title' => ['nullable', 'string', 'max:255'],
            'notify_all_price_changes_body' => ['nullable', 'string', 'max:10000'],
            'alert_cheaper_title' => ['nullable', 'string', 'max:255'],
            'alert_cheaper_body' => ['nullable', 'string', 'max:10000'],
            'alert_drop_title' => ['nullable', 'string', 'max:255'],
            'alert_drop_body' => ['nullable', 'string', 'max:10000'],
        ]);

        $notification = UserNotificationSetting::query()->firstOrCreate([
            'user_id' => $ownerId,
        ]);

        $tokenInput = array_key_exists('telegram_bot_token', $data) ? trim((string) $data['telegram_bot_token']) : null;
        $telegramToken = $tokenInput !== null && $tokenInput !== '' ? $tokenInput : $notification->telegram_bot_token;

        $notification->update([
            'email_enabled' => (bool) ($data['email_enabled'] ?? false),
            'email_to' => $data['email_to'] ?? null,
            'telegram_enabled' => (bool) ($data['telegram_enabled'] ?? false),
            'telegram_bot_token' => $telegramToken,
            'telegram_chat_id' => $data['telegram_chat_id'] ?? null,
            'alert_competitor_cheaper_percent' => $data['alert_competitor_cheaper_percent'] ?? null,
            'alert_competitor_drop_amount' => $data['alert_competitor_drop_amount'] ?? null,
            'notify_all_price_changes' => (bool) ($data['notify_all_price_changes'] ?? false),
            'notify_all_price_changes_title' => $data['notify_all_price_changes_title'] ?? null,
            'notify_all_price_changes_body' => $data['notify_all_price_changes_body'] ?? null,
            'alert_cheaper_title' => $data['alert_cheaper_title'] ?? null,
            'alert_cheaper_body' => $data['alert_cheaper_body'] ?? null,
            'alert_drop_title' => $data['alert_drop_title'] ?? null,
            'alert_drop_body' => $data['alert_drop_body'] ?? null,
        ]);

        return back()->with('status', 'Đã lưu cài đặt thông báo');
    }

    public function createSubUser(Request $request): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);

        $ownerId = $owner->effectiveUserId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'product_group_ids' => ['nullable', 'array'],
            'product_group_ids.*' => ['integer'],
            'competitor_site_group_ids' => ['nullable', 'array'],
            'competitor_site_group_ids.*' => ['integer'],
        ]);

        $canonical = User::canonicalEmail($data['email']);
        $existsCanonical = User::query()->where('email_canonical', $canonical)->exists();
        if ($existsCanonical) {
            return back()->withInput()->withErrors(['email' => 'Email này đã được dùng để tạo tài khoản (theo quy tắc Gmail).']);
        }

        User::create([
            'parent_user_id' => $ownerId,
            'role' => 'viewer',
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'visible_product_group_ids' => $this->ownedProductGroupIds($data['product_group_ids'] ?? [], $ownerId),
            'visible_competitor_site_group_ids' => $this->ownedCompetitorGroupIds($data['competitor_site_group_ids'] ?? [], $ownerId),
        ]);

        return back()->with('status', 'Đã tạo tài khoản con');
    }

    public function updateSubUser(Request $request, User $user): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);

        $ownerId = $owner->effectiveUserId();
        abort_unless($user->parent_user_id === $ownerId && $user->role === 'viewer', 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'product_group_ids' => ['nullable', 'array'],
            'product_group_ids.*' => ['integer'],
            'competitor_site_group_ids' => ['nullable', 'array'],
            'competitor_site_group_ids.*' => ['integer'],
        ]);

        $canonical = User::canonicalEmail($data['email']);
        $existsCanonical = User::query()
            ->where('email_canonical', $canonical)
            ->whereKeyNot($user->id)
            ->exists();

        if ($existsCanonical) {
            return back()->withInput()->withErrors(['email' => 'Email này đã được dùng để tạo tài khoản (theo quy tắc Gmail).']);
        }

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
            'visible_product_group_ids' => $this->ownedProductGroupIds($data['product_group_ids'] ?? [], $ownerId),
            'visible_competitor_site_group_ids' => $this->ownedCompetitorGroupIds($data['competitor_site_group_ids'] ?? [], $ownerId),
        ];

        if (! empty($data['password'])) {
            $updates['password'] = $data['password'];
        }

        $user->update($updates);

        return back()->with('status', 'Đã lưu tài khoản con');
    }

    public function destroySubUser(Request $request, User $user): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);

        $ownerId = $owner->effectiveUserId();
        abort_unless($user->parent_user_id === $ownerId && $user->role === 'viewer', 404);

        $user->delete();

        return back()->with('status', 'Đã xoá tài khoản con');
    }

    public function createGroup(Request $request): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);

        $ownerId = $owner->effectiveUserId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        ProductGroup::firstOrCreate([
            'user_id' => $ownerId,
            'name' => trim($data['name']),
        ]);

        return back()->with('status', 'Đã thêm nhóm sản phẩm');
    }

    public function updateGroup(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);
        abort_unless($productGroup->user_id === $owner->effectiveUserId(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $productGroup->update([
            'name' => trim($data['name']),
        ]);

        return back()->with('status', 'Đã sửa nhóm sản phẩm');
    }

    public function destroyGroup(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);

        abort_unless($productGroup->user_id === $owner->effectiveUserId(), 404);

        $productGroup->delete();

        return back()->with('status', 'Đã xoá nhóm sản phẩm');
    }

    public function createCompetitorGroup(Request $request): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);

        $ownerId = $owner->effectiveUserId();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'competitor_site_ids' => ['nullable', 'array'],
            'competitor_site_ids.*' => ['integer'],
        ]);

        $group = CompetitorSiteGroup::firstOrCreate([
            'user_id' => $ownerId,
            'name' => trim($data['name']),
        ]);

        $group->competitorSites()->sync($this->ownedCompetitorSiteIds($data['competitor_site_ids'] ?? [], $ownerId));

        return back()->with('status', 'Đã thêm nhóm đối thủ');
    }

    public function updateCompetitorGroup(Request $request, CompetitorSiteGroup $competitorSiteGroup): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);
        abort_unless($competitorSiteGroup->user_id === $owner->effectiveUserId(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'competitor_site_ids' => ['nullable', 'array'],
            'competitor_site_ids.*' => ['integer'],
        ]);

        $competitorSiteGroup->update([
            'name' => trim($data['name']),
        ]);
        $competitorSiteGroup->competitorSites()->sync($this->ownedCompetitorSiteIds($data['competitor_site_ids'] ?? [], $owner->effectiveUserId()));

        return back()->with('status', 'Đã sửa nhóm đối thủ');
    }

    public function destroyCompetitorGroup(Request $request, CompetitorSiteGroup $competitorSiteGroup): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);
        abort_unless($competitorSiteGroup->user_id === $owner->effectiveUserId(), 404);

        $competitorSiteGroup->delete();

        return back()->with('status', 'Đã xoá nhóm đối thủ');
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private function ownedProductGroupIds(array $ids, int $ownerId): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        return ProductGroup::query()
            ->where('user_id', $ownerId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private function ownedCompetitorGroupIds(array $ids, int $ownerId): array
    {
        if (! Schema::hasTable('competitor_site_groups')) {
            return [];
        }

        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        return CompetitorSiteGroup::query()
            ->where('user_id', $ownerId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private function ownedCompetitorSiteIds(array $ids, int $ownerId): array
    {
        $ids = $this->normalizeIds($ids);
        if ($ids === []) {
            return [];
        }

        return CompetitorSite::query()
            ->where('user_id', $ownerId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }
}
