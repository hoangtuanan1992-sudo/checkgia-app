<?php

namespace App\Http\Controllers;

use App\Models\CompetitorSite;
use App\Models\CompetitorSiteScrapeXpath;
use App\Models\UserNotificationSetting;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardCompetitorSetupController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->effectiveUserId();

        $userSetting = UserScrapeSetting::query()->firstOrCreate(['user_id' => $userId]);
        $notification = UserNotificationSetting::query()->firstOrCreate(['user_id' => $userId]);

        $competitorSites = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('name')
            ->with(['scrapeXpaths' => function ($q) {
                $q->orderBy('type')->orderBy('position');
            }])
            ->get(['id', 'name', 'position', 'name_xpath', 'price_xpath', 'price_regex']);

        $ownNameFallbacks = UserScrapeXpath::query()
            ->where('user_id', $userId)
            ->where('type', 'name')
            ->orderBy('position')
            ->pluck('xpath');

        $ownPriceFallbacks = UserScrapeXpath::query()
            ->where('user_id', $userId)
            ->where('type', 'price')
            ->orderBy('position')
            ->pluck('xpath');

        return view('dashboard.competitors', [
            'competitorSites' => $competitorSites,
            'userSetting' => $userSetting,
            'ownNameFallbacks' => $ownNameFallbacks,
            'ownPriceFallbacks' => $ownPriceFallbacks,
            'notification' => $notification,
        ]);
    }

    public function storeSite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $userId = $request->user()->effectiveUserId();
        $position = ((int) CompetitorSite::query()->where('user_id', $userId)->max('position')) + 1;

        CompetitorSite::firstOrCreate([
            'user_id' => $request->user()->effectiveUserId(),
            'name' => trim($validated['name']),
        ], [
            'position' => $position,
        ]);

        $this->normalizeSitePositions($userId);

        return back()->with('status', 'Đã thêm đối thủ');
    }

    public function destroySite(Request $request, CompetitorSite $competitorSite): RedirectResponse
    {
        abort_unless($competitorSite->user_id === $request->user()->effectiveUserId(), 404);

        $userId = $request->user()->effectiveUserId();
        $competitorSite->delete();
        $this->normalizeSitePositions($userId);

        return back()->with('status', 'Đã xoá đối thủ');
    }

    public function moveSite(Request $request, CompetitorSite $competitorSite): RedirectResponse
    {
        abort_unless($competitorSite->user_id === $request->user()->effectiveUserId(), 404);

        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $userId = $request->user()->effectiveUserId();
        $this->normalizeSitePositions($userId);

        $current = CompetitorSite::query()->where('id', $competitorSite->id)->first();
        if (! $current) {
            return back();
        }

        $neighbor = null;
        if ($data['direction'] === 'up') {
            $neighbor = CompetitorSite::query()
                ->where('user_id', $userId)
                ->where('position', '<', $current->position)
                ->orderByDesc('position')
                ->first();
        } else {
            $neighbor = CompetitorSite::query()
                ->where('user_id', $userId)
                ->where('position', '>', $current->position)
                ->orderBy('position')
                ->first();
        }

        if (! $neighbor) {
            return back();
        }

        DB::transaction(function () use ($current, $neighbor) {
            $a = $current->position;
            $b = $neighbor->position;
            $current->update(['position' => $b]);
            $neighbor->update(['position' => $a]);
        });

        $this->normalizeSitePositions($userId);

        return back()->with('status', 'Đã cập nhật thứ tự đối thủ');
    }

    private function normalizeSitePositions(int $userId): void
    {
        $sites = CompetitorSite::query()
            ->where('user_id', $userId)
            ->orderBy('position')
            ->orderBy('id')
            ->get(['id', 'position']);

        DB::transaction(function () use ($sites) {
            foreach ($sites as $i => $site) {
                $pos = $i + 1;
                if ((int) $site->position !== $pos) {
                    CompetitorSite::query()->where('id', $site->id)->update(['position' => $pos]);
                }
            }
        });
    }

    public function updateScrapeSettings(Request $request): RedirectResponse
    {
        $userId = $request->user()->effectiveUserId();

        $validated = $request->validate([
            'own_name_xpath' => ['nullable', 'string', 'max:10000'],
            'own_price_xpath' => ['nullable', 'string', 'max:10000'],
            'own_price_regex' => ['nullable', 'string', 'max:10000'],
            'scrape_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'own_name_fallbacks' => ['array'],
            'own_name_fallbacks.*' => ['nullable', 'string', 'max:10000'],
            'own_price_fallbacks' => ['array'],
            'own_price_fallbacks.*' => ['nullable', 'string', 'max:10000'],
            'site_name_xpath' => ['array'],
            'site_name_xpath.*' => ['nullable', 'string', 'max:10000'],
            'site_price_xpath' => ['array'],
            'site_price_xpath.*' => ['nullable', 'string', 'max:10000'],
            'site_price_regex' => ['array'],
            'site_price_regex.*' => ['nullable', 'string', 'max:10000'],
            'site_name_fallbacks' => ['array'],
            'site_name_fallbacks.*' => ['array'],
            'site_name_fallbacks.*.*' => ['nullable', 'string', 'max:10000'],
            'site_price_fallbacks' => ['array'],
            'site_price_fallbacks.*' => ['array'],
            'site_price_fallbacks.*.*' => ['nullable', 'string', 'max:10000'],
        ]);

        DB::transaction(function () use ($userId, $validated) {
            $userSetting = UserScrapeSetting::query()->firstOrCreate(['user_id' => $userId]);
            $userSetting->update([
                'own_name_xpath' => $validated['own_name_xpath'] ?? null,
                'own_price_xpath' => $validated['own_price_xpath'] ?? null,
                'price_regex' => $validated['own_price_regex'] ?? null,
                'scrape_interval_minutes' => (int) $validated['scrape_interval_minutes'],
            ]);

            UserScrapeXpath::query()
                ->where('user_id', $userId)
                ->whereIn('type', ['name', 'price'])
                ->delete();

            $nameFallbacks = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $validated['own_name_fallbacks'] ?? [])));
            foreach ($nameFallbacks as $i => $xpath) {
                UserScrapeXpath::create([
                    'user_id' => $userId,
                    'type' => 'name',
                    'position' => $i,
                    'xpath' => $xpath,
                ]);
            }

            $priceFallbacks = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $validated['own_price_fallbacks'] ?? [])));
            foreach ($priceFallbacks as $i => $xpath) {
                UserScrapeXpath::create([
                    'user_id' => $userId,
                    'type' => 'price',
                    'position' => $i,
                    'xpath' => $xpath,
                ]);
            }

            $sites = CompetitorSite::query()
                ->where('user_id', $userId)
                ->get();

            foreach ($sites as $site) {
                $id = (string) $site->id;
                $site->update([
                    'name_xpath' => $validated['site_name_xpath'][$id] ?? null,
                    'price_xpath' => $validated['site_price_xpath'][$id] ?? null,
                    'price_regex' => $validated['site_price_regex'][$id] ?? null,
                ]);

                CompetitorSiteScrapeXpath::query()
                    ->where('competitor_site_id', $site->id)
                    ->whereIn('type', ['name', 'price'])
                    ->delete();

                $siteNameFallbacks = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $validated['site_name_fallbacks'][$id] ?? [])));
                foreach ($siteNameFallbacks as $i => $xpath) {
                    CompetitorSiteScrapeXpath::create([
                        'competitor_site_id' => $site->id,
                        'type' => 'name',
                        'position' => $i,
                        'xpath' => $xpath,
                    ]);
                }

                $sitePriceFallbacks = array_values(array_filter(array_map(fn ($v) => trim((string) $v), $validated['site_price_fallbacks'][$id] ?? [])));
                foreach ($sitePriceFallbacks as $i => $xpath) {
                    CompetitorSiteScrapeXpath::create([
                        'competitor_site_id' => $site->id,
                        'type' => 'price',
                        'position' => $i,
                        'xpath' => $xpath,
                    ]);
                }
            }
        });

        return back()->with('status', 'Đã lưu cài đặt lấy dữ liệu');
    }
}
