<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserScrapeSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $service = (string) $request->query('service', '');
        $createdFrom = (string) $request->query('created_from', '');
        $createdTo = (string) $request->query('created_to', '');

        $tz = 'Asia/Ho_Chi_Minh';
        $today = Carbon::now($tz)->toDateString();

        $users = User::query()
            ->where('role', 'owner')
            ->whereNull('parent_user_id')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', '%'.$q.'%')
                        ->orWhere('email', 'like', '%'.$q.'%')
                        ->orWhere('id', $q);
                });
            })
            ->when($service !== '', function ($query) use ($service, $today) {
                if ($service === 'no_plan') {
                    $query->where(function ($w) {
                        $w->whereNull('service_start_date')->orWhereNull('service_end_date');
                    });

                    return;
                }

                if ($service === 'not_started') {
                    $query->whereNotNull('service_start_date')
                        ->whereNotNull('service_end_date')
                        ->whereDate('service_start_date', '>', $today);

                    return;
                }

                if ($service === 'expired') {
                    $query->whereNotNull('service_start_date')
                        ->whereNotNull('service_end_date')
                        ->whereDate('service_end_date', '<', $today);

                    return;
                }

                if ($service === 'active') {
                    $query->whereNotNull('service_start_date')
                        ->whereNotNull('service_end_date')
                        ->whereDate('service_start_date', '<=', $today)
                        ->whereDate('service_end_date', '>=', $today);

                    return;
                }

                if ($service === 'exp_7') {
                    $query->whereNotNull('service_start_date')
                        ->whereNotNull('service_end_date')
                        ->whereDate('service_start_date', '<=', $today)
                        ->whereRaw('DATEDIFF(service_end_date, ?) BETWEEN 0 AND 6', [$today]);

                    return;
                }

                if ($service === 'exp_30') {
                    $query->whereNotNull('service_start_date')
                        ->whereNotNull('service_end_date')
                        ->whereDate('service_start_date', '<=', $today)
                        ->whereRaw('DATEDIFF(service_end_date, ?) BETWEEN 0 AND 29', [$today]);
                }
            })
            ->when($createdFrom !== '', function ($query) use ($createdFrom) {
                $query->whereDate('created_at', '>=', $createdFrom);
            })
            ->when($createdTo !== '', function ($query) use ($createdTo) {
                $query->whereDate('created_at', '<=', $createdTo);
            })
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'role', 'parent_user_id', 'created_at', 'service_start_date', 'service_end_date']);

        $impersonateId = (int) session('impersonate_user_id', 0);
        $impersonatedUser = $impersonateId ? User::query()->find($impersonateId) : null;

        return view('admin.users.index', [
            'users' => $users,
            'q' => $q,
            'service' => $service,
            'createdFrom' => $createdFrom,
            'createdTo' => $createdTo,
            'impersonatedUser' => $impersonatedUser,
        ]);
    }

    public function create(): View
    {
        $owners = User::query()
            ->where('role', 'owner')
            ->whereNull('parent_user_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('admin.users.create', compact('owners'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'in:owner,viewer,admin'],
            'parent_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'owner')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'service_start_date' => ['nullable', 'date'],
            'service_end_date' => ['nullable', 'date', 'after_or_equal:service_start_date'],
            'admin_note' => ['nullable', 'string', 'max:10000'],
        ]);

        $canonical = User::canonicalEmail($data['email']);
        $existsCanonical = User::query()->where('email_canonical', $canonical)->exists();
        if ($existsCanonical) {
            return back()->withInput()->withErrors(['email' => 'Email này đã được dùng để tạo tài khoản (theo quy tắc Gmail).']);
        }

        $parentUserId = null;
        if ($data['role'] === 'viewer') {
            $parentUserId = (int) ($data['parent_user_id'] ?? 0);
            if ($parentUserId <= 0) {
                return back()->withInput()->withErrors(['parent_user_id' => 'Vui lòng chọn tài khoản chính.']);
            }
        }

        if ($data['role'] !== 'viewer') {
            $parentUserId = null;
        }

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'parent_user_id' => $parentUserId,
            'password' => Hash::make($data['password']),
            'service_start_date' => $data['service_start_date'] ?? null,
            'service_end_date' => $data['service_end_date'] ?? null,
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        return redirect()->route('admin.users.index')->with('status', 'Đã tạo tài khoản');
    }

    public function impersonate(Request $request, User $user): RedirectResponse
    {
        session(['impersonate_user_id' => $user->id]);

        return redirect()->route('dashboard');
    }

    public function stopImpersonate(): RedirectResponse
    {
        session()->forget('impersonate_user_id');

        return redirect()->route('dashboard')->with('status', 'Đã thoát chế độ xem');
    }

    public function edit(User $user): View
    {
        $owners = User::query()
            ->where('role', 'owner')
            ->whereNull('parent_user_id')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $shopUserId = (int) ($user->parent_user_id ?: $user->id);
        $shopUser = User::query()->find($shopUserId);
        $scrapeSetting = null;
        if ($shopUser && Schema::hasTable('user_scrape_settings')) {
            $scrapeSetting = UserScrapeSetting::query()->firstOrCreate(['user_id' => $shopUserId]);
        }

        return view('admin.users.edit', compact('user', 'owners', 'shopUser', 'scrapeSetting'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', 'in:owner,viewer,admin'],
            'parent_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'owner')],
            'password' => ['nullable', 'string', 'min:8'],
            'service_start_date' => ['nullable', 'date'],
            'service_end_date' => ['nullable', 'date', 'after_or_equal:service_start_date'],
            'product_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'admin_note' => ['nullable', 'string', 'max:10000'],
            'scrape_schedule_times' => ['nullable', 'string', 'max:10000'],
        ]);

        $canonical = User::canonicalEmail($data['email']);
        $existsCanonical = User::query()
            ->where('email_canonical', $canonical)
            ->where('id', '!=', $user->id)
            ->exists();
        if ($existsCanonical) {
            return back()->withInput()->withErrors(['email' => 'Email này đã được dùng để tạo tài khoản (theo quy tắc Gmail).']);
        }

        $updates = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'service_start_date' => $data['service_start_date'] ?? null,
            'service_end_date' => $data['service_end_date'] ?? null,
            'admin_note' => $data['admin_note'] ?? null,
        ];

        if ($updates['role'] === 'viewer') {
            $parentUserId = (int) ($data['parent_user_id'] ?? 0);
            if ($parentUserId <= 0) {
                return back()->withInput()->withErrors(['parent_user_id' => 'Vui lòng chọn tài khoản chính.']);
            }
            $updates['parent_user_id'] = $parentUserId;
        } else {
            $updates['parent_user_id'] = null;
        }

        if ($updates['role'] === 'owner' && User::hasProductLimitColumn()) {
            $updates['product_limit'] = (int) ($data['product_limit'] ?? 100);
        }

        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        $user->update($updates);

        $shopUserId = (int) ($updates['parent_user_id'] ?? $user->id);
        if (in_array($updates['role'], ['owner', 'viewer'], true) && Schema::hasTable('user_scrape_settings')) {
            $setting = UserScrapeSetting::query()->firstOrCreate(['user_id' => $shopUserId]);
            if (Schema::hasColumn($setting->getTable(), 'scrape_schedule_times')) {
                $raw = trim((string) ($data['scrape_schedule_times'] ?? ''));
                $lines = $raw === '' ? [] : preg_split('/\R+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
                $times = [];
                foreach ($lines ?: [] as $line) {
                    $s = trim((string) $line);
                    if ($s === '') {
                        continue;
                    }

                    $h = null;
                    $m = null;

                    if (preg_match('/^(\d{1,2})\s*:\s*(\d{1,2})$/', $s, $mm) === 1) {
                        $h = (int) $mm[1];
                        $m = (int) $mm[2];
                    } elseif (preg_match('/^(\d{1,2})\s*h(?:\s*(\d{1,2}))?$/iu', $s, $mm) === 1) {
                        $h = (int) $mm[1];
                        $m = isset($mm[2]) && $mm[2] !== '' ? (int) $mm[2] : 0;
                    } elseif (preg_match('/^(\d{1,2})$/', $s, $mm) === 1) {
                        $h = (int) $mm[1];
                        $m = 0;
                    }

                    if (is_null($h) || is_null($m) || $h < 0 || $h > 23 || $m < 0 || $m > 59) {
                        continue;
                    }

                    $times[] = sprintf('%02d:%02d', $h, $m);
                }

                $times = array_values(array_unique($times));
                sort($times);

                $setting->scrape_schedule_times = $times ? json_encode($times, JSON_UNESCAPED_UNICODE) : null;
                $setting->save();
            }
        }

        return redirect()->route('admin.users.index')->with('status', 'Đã cập nhật người dùng');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ((int) $request->user()->id === (int) $user->id) {
            return back()->withErrors(['user' => 'Không thể xoá chính bạn.']);
        }

        if ($user->isAdmin()) {
            $adminCount = User::query()->where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['user' => 'Không thể xoá admin cuối cùng.']);
            }
        }

        if ((int) session('impersonate_user_id') === (int) $user->id) {
            session()->forget('impersonate_user_id');
        }

        $user->delete();

        return back()->with('status', 'Đã xoá tài khoản');
    }
}
