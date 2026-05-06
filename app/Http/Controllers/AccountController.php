<?php

namespace App\Http\Controllers;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteGroup;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\User;
use App\Models\UserNotificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $authUser = $request->user();
        $ownerId = $authUser->effectiveUserId();
        $owner = User::query()->find($ownerId);

        $isImpersonating = $authUser->isAdmin() && (int) session('impersonate_user_id', 0) > 0;
        $user = $isImpersonating ? (User::query()->find($ownerId) ?? $authUser) : $authUser;

        if ($request->query('product_group_action')) {
            return $this->handleProductGroupQueryAction($request, $user, $ownerId);
        }

        if ($request->query('competitor_group_action')) {
            return $this->handleCompetitorGroupQueryAction($request, $user, $ownerId);
        }

        if ($request->query('subuser_action')) {
            return $this->handleSubUserQueryAction($request, $user, $ownerId);
        }

        $notification = UserNotificationSetting::query()->firstOrCreate([
            'user_id' => $ownerId,
        ]);

        $subUsers = collect();
        $groups = collect();
        $competitorSites = collect();
        $competitorGroups = collect();
        $deletedProducts = collect();

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

            if (Product::hasSoftDeleteColumn()) {
                $deletedProducts = Product::onlyTrashed()
                    ->where('user_id', $ownerId)
                    ->with(['group:id,name', 'deletedBy:id,name,email'])
                    ->orderByDesc('deleted_at')
                    ->limit(100)
                    ->get($this->deletedProductSelectColumns());
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
            'deletedProducts' => $deletedProducts,
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

    public function updateSubUserFromPost(Request $request, User $user): RedirectResponse
    {
        return $this->updateSubUser($request, $user);
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

    public function destroySubUserFromPost(Request $request, User $user): RedirectResponse
    {
        return $this->destroySubUser($request, $user);
    }

    public function restoreDeletedProduct(Request $request, string $product): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);

        if (! Product::hasSoftDeleteColumn()) {
            return back()->with('status', 'Chưa có bảng lịch sử xoá. Hãy chạy migration trên hosting.');
        }

        $restorable = Product::onlyTrashed()
            ->where('user_id', $owner->effectiveUserId())
            ->whereKey((int) $product)
            ->first();

        if (! $restorable) {
            return back()->with('status', 'Không tìm thấy sản phẩm đã xoá để khôi phục.');
        }

        $restorable->restore();
        if (Product::hasDeletedByColumn()) {
            $restorable->forceFill(['deleted_by_user_id' => null])->save();
        }

        return back()->with('status', 'Đã khôi phục sản phẩm.');
    }

    public function legacySubUserRequest(Request $request, User $user): RedirectResponse
    {
        if ($request->isMethod('get')) {
            return redirect()
                ->route('account')
                ->with('status', 'Hãy bấm Xem rồi Lưu trực tiếp trong trang Tài khoản.');
        }

        $method = strtoupper((string) $request->input('_method', ''));
        if ($method === 'DELETE') {
            return $this->destroySubUser($request, $user);
        }

        return $this->updateSubUser($request, $user);
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

    public function updateGroupFromPost(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        if ($request->isMethod('get') && ! $request->filled('name')) {
            return redirect()
                ->route('account')
                ->with('status', 'Hãy nhập tên nhóm rồi bấm Sửa trong trang Tài khoản.');
        }

        return $this->updateGroup($request, $productGroup);
    }

    public function destroyGroup(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        $owner = $request->user();
        abort_if($owner->isViewer(), 403);

        abort_unless($productGroup->user_id === $owner->effectiveUserId(), 404);

        $productGroup->delete();

        return back()->with('status', 'Đã xoá nhóm sản phẩm');
    }

    public function destroyGroupFromPost(Request $request, ProductGroup $productGroup): RedirectResponse
    {
        return $this->destroyGroup($request, $productGroup);
    }

    public function legacyProductGroupRequest(Request $request, string $productGroup): RedirectResponse
    {
        if ($request->isMethod('get')) {
            return redirect()
                ->route('account')
                ->with('status', 'Hãy sửa hoặc xoá nhóm sản phẩm trực tiếp trong trang Tài khoản.');
        }

        $group = ProductGroup::query()->find((int) $productGroup);
        if (! $group) {
            return redirect()
                ->route('account')
                ->with('status', 'Nhóm sản phẩm này không còn tồn tại.');
        }

        $method = strtoupper((string) $request->input('_method', ''));
        if ($method === 'DELETE') {
            return $this->destroyGroup($request, $group);
        }

        if ($request->filled('name')) {
            return $this->updateGroup($request, $group);
        }

        return redirect()->route('account');
    }

    private function handleProductGroupQueryAction(Request $request, User $user, int $ownerId): RedirectResponse
    {
        abort_if($user->isViewer(), 403);

        $action = (string) $request->query('product_group_action', '');
        $groupId = (int) $request->query('product_group_id', 0);

        if ($groupId <= 0) {
            return redirect()
                ->route('account')
                ->with('status', 'Không tìm thấy nhóm sản phẩm cần xử lý.');
        }

        $group = ProductGroup::query()
            ->where('user_id', $ownerId)
            ->whereKey($groupId)
            ->first();

        if (! $group) {
            return redirect()
                ->route('account')
                ->with('status', 'Nhóm sản phẩm này không còn tồn tại.');
        }

        if ($action === 'delete') {
            $group->delete();

            return redirect()
                ->route('account')
                ->with('status', 'Đã xoá nhóm sản phẩm');
        }

        if ($action === 'update') {
            $name = trim((string) $request->query('product_group_name', ''));
            if ($name === '') {
                return redirect()
                    ->route('account')
                    ->with('status', 'Tên nhóm sản phẩm không được để trống.');
            }

            $group->update(['name' => mb_substr($name, 0, 255)]);

            return redirect()
                ->route('account')
                ->with('status', 'Đã sửa nhóm sản phẩm');
        }

        return redirect()->route('account');
    }

    private function handleSubUserQueryAction(Request $request, User $user, int $ownerId): RedirectResponse
    {
        abort_if($user->isViewer(), 403);

        $action = (string) $request->query('subuser_action', '');
        $subUserId = (int) $request->query('subuser_id', 0);

        if ($subUserId <= 0) {
            return redirect()
                ->route('account')
                ->with('status', 'Không tìm thấy tài khoản con cần xử lý.');
        }

        $subUser = User::query()
            ->where('parent_user_id', $ownerId)
            ->where('role', 'viewer')
            ->whereKey($subUserId)
            ->first();

        if (! $subUser) {
            return redirect()
                ->route('account')
                ->with('status', 'Tài khoản con này không còn tồn tại.');
        }

        if ($action === 'delete') {
            $subUser->delete();

            return redirect()
                ->route('account')
                ->with('status', 'Đã xoá tài khoản con');
        }

        if ($action !== 'update') {
            return redirect()->route('account');
        }

        $data = [
            'name' => trim((string) $request->query('name', '')),
            'email' => trim((string) $request->query('email', '')),
            'product_group_ids' => (array) $request->query('product_group_ids', []),
            'competitor_site_group_ids' => (array) $request->query('competitor_site_group_ids', []),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($subUser->id)],
            'product_group_ids' => ['nullable', 'array'],
            'product_group_ids.*' => ['integer'],
            'competitor_site_group_ids' => ['nullable', 'array'],
            'competitor_site_group_ids.*' => ['integer'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('account')
                ->withErrors($validator)
                ->withInput();
        }

        $canonical = User::canonicalEmail($data['email']);
        $existsCanonical = User::query()
            ->where('email_canonical', $canonical)
            ->whereKeyNot($subUser->id)
            ->exists();

        if ($existsCanonical) {
            return redirect()
                ->route('account')
                ->withErrors(['email' => 'Email này đã được dùng để tạo tài khoản (theo quy tắc Gmail).'])
                ->withInput();
        }

        $subUser->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'visible_product_group_ids' => $this->ownedProductGroupIds($data['product_group_ids'], $ownerId),
            'visible_competitor_site_group_ids' => $this->ownedCompetitorGroupIds($data['competitor_site_group_ids'], $ownerId),
        ]);

        return redirect()
            ->route('account')
            ->with('status', 'Đã lưu tài khoản con');
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

    public function updateCompetitorGroupFromPost(Request $request, CompetitorSiteGroup $competitorSiteGroup): RedirectResponse
    {
        if ($request->isMethod('get') && ! $request->filled('name')) {
            return redirect()
                ->route('account')
                ->with('status', 'Hãy nhập tên nhóm đối thủ rồi bấm Sửa trong trang Tài khoản.');
        }

        return $this->updateCompetitorGroup($request, $competitorSiteGroup);
    }

    public function destroyCompetitorGroupFromPost(Request $request, CompetitorSiteGroup $competitorSiteGroup): RedirectResponse
    {
        return $this->destroyCompetitorGroup($request, $competitorSiteGroup);
    }

    public function legacyCompetitorGroupRequest(Request $request, string $competitorSiteGroup): RedirectResponse
    {
        if ($request->isMethod('get')) {
            return redirect()
                ->route('account')
                ->with('status', 'Hãy sửa hoặc xoá nhóm đối thủ trực tiếp trong trang Tài khoản.');
        }

        $group = CompetitorSiteGroup::query()->find((int) $competitorSiteGroup);
        if (! $group) {
            return redirect()
                ->route('account')
                ->with('status', 'Nhóm đối thủ này không còn tồn tại.');
        }

        $method = strtoupper((string) $request->input('_method', ''));
        if ($method === 'DELETE') {
            return $this->destroyCompetitorGroup($request, $group);
        }

        if ($request->filled('name')) {
            return $this->updateCompetitorGroup($request, $group);
        }

        return redirect()->route('account');
    }

    private function handleCompetitorGroupQueryAction(Request $request, User $user, int $ownerId): RedirectResponse
    {
        abort_if($user->isViewer(), 403);

        if (! Schema::hasTable('competitor_site_groups')) {
            return redirect()
                ->route('account')
                ->with('status', 'Chưa có bảng nhóm đối thủ. Hãy chạy migration trên hosting.');
        }

        $action = (string) $request->query('competitor_group_action', '');
        $groupId = (int) $request->query('competitor_group_id', 0);

        if ($groupId <= 0) {
            return redirect()
                ->route('account')
                ->with('status', 'Không tìm thấy nhóm đối thủ cần xử lý.');
        }

        $group = CompetitorSiteGroup::query()
            ->where('user_id', $ownerId)
            ->whereKey($groupId)
            ->first();

        if (! $group) {
            return redirect()
                ->route('account')
                ->with('status', 'Nhóm đối thủ này không còn tồn tại.');
        }

        if ($action === 'delete') {
            $group->delete();

            return redirect()
                ->route('account')
                ->with('status', 'Đã xoá nhóm đối thủ');
        }

        if ($action === 'update') {
            $name = trim((string) $request->query('competitor_group_name', $request->query('name', '')));
            if ($name === '') {
                return redirect()
                    ->route('account')
                    ->with('status', 'Tên nhóm đối thủ không được để trống.');
            }

            $group->update(['name' => mb_substr($name, 0, 255)]);
            $group->competitorSites()->sync($this->ownedCompetitorSiteIds((array) $request->query('competitor_site_ids', []), $ownerId));

            return redirect()
                ->route('account')
                ->with('status', 'Đã sửa nhóm đối thủ');
        }

        return redirect()->route('account');
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
