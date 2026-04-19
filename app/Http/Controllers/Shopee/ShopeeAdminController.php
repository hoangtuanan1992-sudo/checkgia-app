<?php

namespace App\Http\Controllers\Shopee;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\ShopeeAgent;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShopeeAdminController extends Controller
{
    public function edit(Request $request): View
    {
        $setting = AppSetting::query()->firstOrCreate([]);

        if (! $setting->shopee_extension_token) {
            $setting->shopee_extension_token = Str::random(48);
            $setting->save();
        }

        $agents = ShopeeAgent::query()
            ->orderByDesc('last_seen_at')
            ->get();

        $users = User::query()
            ->whereIn('role', ['owner', 'viewer'])
            ->orderBy('email')
            ->get(['id', 'email', 'role', 'parent_user_id']);

        return view('shopee.settings', [
            'setting' => $setting,
            'agents' => $agents,
            'users' => $users,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $setting = AppSetting::query()->firstOrCreate([]);

        $data = $request->validate([
            'shopee_enabled' => ['nullable', 'boolean'],
            'shopee_extension_token' => ['nullable', 'string', 'max:5000'],
            'shopee_scrape_interval_seconds' => ['required', 'integer', 'min:10', 'max:86400'],
            'shopee_rest_seconds_min' => ['required', 'integer', 'min:0', 'max:3600'],
            'shopee_rest_seconds_max' => ['required', 'integer', 'min:0', 'max:3600'],
        ]);

        $min = (int) $data['shopee_rest_seconds_min'];
        $max = (int) $data['shopee_rest_seconds_max'];
        if ($max < $min) {
            $max = $min;
        }

        $setting->update([
            'shopee_enabled' => (bool) ($data['shopee_enabled'] ?? false),
            'shopee_extension_token' => trim((string) ($data['shopee_extension_token'] ?? $setting->shopee_extension_token)),
            'shopee_scrape_interval_seconds' => (int) $data['shopee_scrape_interval_seconds'],
            'shopee_rest_seconds_min' => $min,
            'shopee_rest_seconds_max' => $max,
        ]);

        return back()->with('status', 'Đã lưu cài đặt Shopee');
    }

    public function updateAgent(Request $request, ShopeeAgent $agent): RedirectResponse
    {
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'mode' => ['required', 'in:all,user'],
            'assigned_user_id' => ['nullable', 'integer'],
            'name' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $assignedUserId = $data['mode'] === 'user' ? (int) ($data['assigned_user_id'] ?? 0) : null;
        if ($data['mode'] === 'user' && ! $assignedUserId) {
            $assignedUserId = null;
        }

        $agent->update([
            'is_enabled' => (bool) ($data['is_enabled'] ?? false),
            'mode' => (string) $data['mode'],
            'assigned_user_id' => $assignedUserId,
            'name' => trim((string) ($data['name'] ?? '')) ?: null,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
        ]);

        return back()->with('status', 'Đã cập nhật agent');
    }

    public function approveAgent(Request $request, ShopeeAgent $agent): RedirectResponse
    {
        $agent->is_approved = true;
        $agent->is_enabled = true;
        $agent->api_token = Str::random(48);
        $agent->pair_code = null;
        $agent->save();

        return back()->with('status', 'Đã duyệt agent');
    }
}
