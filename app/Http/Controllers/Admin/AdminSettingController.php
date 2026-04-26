<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\CompetitorSite;
use App\Models\CompetitorSiteScrapeXpath;
use App\Models\CompetitorSiteTemplate;
use App\Models\CompetitorSiteTemplateScrapeXpath;
use App\Models\User;
use App\Models\UserScrapeSetting;
use App\Models\UserScrapeXpath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $setting = AppSetting::current() ?? new AppSetting;

        $demoUsers = User::query()
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('role', 'owner')->whereNull('parent_user_id');
                })->orWhere(function ($qq) {
                    $qq->where('role', 'viewer')->whereNotNull('parent_user_id');
                });
            })
            ->orderBy('parent_user_id')
            ->orderBy('role')
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'parent_user_id', 'role']);

        $scrapeStatus = [
            'last_started_at' => Cache::get('checkgia:scrape-due:last_started_at'),
            'last_finished_at' => Cache::get('checkgia:scrape-due:last_finished_at'),
            'last_selected' => Cache::get('checkgia:scrape-due:last_selected'),
            'last_dispatched' => Cache::get('checkgia:scrape-due:last_dispatched'),
            'last_updated' => Cache::get('checkgia:scrape-due:last_updated'),
            'last_job_finished_at' => Cache::get('checkgia:scrape-due:last_job_finished_at'),
            'last_job_error' => Cache::get('checkgia:scrape-due:last_job_error'),
        ];

        $templates = CompetitorSiteTemplate::query()
            ->orderBy('domain')
            ->with(['scrapeXpaths' => function ($q) {
                $q->orderBy('type')->orderBy('position');
            }])
            ->get();

        $templateUsage = CompetitorSite::query()
            ->whereNotNull('domain')
            ->selectRaw('domain, COUNT(*) as c')
            ->groupBy('domain')
            ->pluck('c', 'domain');

        $xpathUserId = trim((string) $request->query('xpath_user_id', ''));
        $xpathUser = null;
        $xpathUserSetting = null;
        $xpathOwnNameFallbacks = collect();
        $xpathOwnPriceFallbacks = collect();
        $xpathUserSites = collect();

        if ($xpathUserId !== '' && ctype_digit($xpathUserId)) {
            $xpathUser = User::query()->where('id', (int) $xpathUserId)->first();
            if ($xpathUser) {
                $uid = (int) $xpathUser->id;
                $xpathUserSetting = UserScrapeSetting::query()->firstOrCreate(['user_id' => $uid]);
                $xpathOwnNameFallbacks = UserScrapeXpath::query()
                    ->where('user_id', $uid)
                    ->where('type', 'name')
                    ->orderBy('position')
                    ->pluck('xpath');
                $xpathOwnPriceFallbacks = UserScrapeXpath::query()
                    ->where('user_id', $uid)
                    ->where('type', 'price')
                    ->orderBy('position')
                    ->pluck('xpath');
                $xpathUserSites = CompetitorSite::query()
                    ->where('user_id', $uid)
                    ->orderBy('position')
                    ->orderBy('name')
                    ->with(['scrapeXpaths' => function ($q) {
                        $q->orderBy('type')->orderBy('position');
                    }])
                    ->get();
            }
        }

        return view('admin.settings.edit', compact(
            'setting',
            'demoUsers',
            'scrapeStatus',
            'templates',
            'templateUsage',
            'xpathUserId',
            'xpathUser',
            'xpathUserSetting',
            'xpathOwnNameFallbacks',
            'xpathOwnPriceFallbacks',
            'xpathUserSites'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mail_mailer' => ['nullable', 'in:smtp,log,array'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string', 'max:2048'],
            'mail_encryption' => ['nullable', 'in:,tls,ssl'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:255'],
            'demo_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('role', 'owner')->whereNull('parent_user_id');
                })->orWhere(function ($qq) {
                    $qq->where('role', 'viewer')->whereNotNull('parent_user_id');
                });
            })],
            'website_scrape_batch_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'website_scrape_concurrency' => ['nullable', 'integer', 'min:1', 'max:50'],
            'website_scrape_timeout_seconds' => ['nullable', 'integer', 'min:3', 'max:60'],
        ]);

        $setting = AppSetting::current() ?? new AppSetting;
        $passwordInput = array_key_exists('mail_password', $data) ? trim((string) $data['mail_password']) : null;
        if ($passwordInput === '') {
            unset($data['mail_password']);
        } else {
            $data['mail_password'] = $passwordInput;
        }

        if (array_key_exists('mail_encryption', $data) && $data['mail_encryption'] === '') {
            $data['mail_encryption'] = null;
        }

        if (array_key_exists('demo_user_id', $data) && (string) $data['demo_user_id'] === '') {
            $data['demo_user_id'] = null;
        }

        $table = (new AppSetting)->getTable();
        foreach (['website_scrape_batch_per_minute', 'website_scrape_concurrency', 'website_scrape_timeout_seconds'] as $col) {
            if (array_key_exists($col, $data) && ! Schema::hasColumn($table, $col)) {
                unset($data[$col]);
            }
        }

        if (array_key_exists('website_scrape_batch_per_minute', $data) && (string) $data['website_scrape_batch_per_minute'] === '') {
            $data['website_scrape_batch_per_minute'] = null;
        }

        if (array_key_exists('website_scrape_concurrency', $data) && (string) $data['website_scrape_concurrency'] === '') {
            $data['website_scrape_concurrency'] = null;
        }

        if (array_key_exists('website_scrape_timeout_seconds', $data) && (string) $data['website_scrape_timeout_seconds'] === '') {
            $data['website_scrape_timeout_seconds'] = null;
        }

        $setting->fill($data);
        $setting->save();

        return back()->with('status', 'Đã lưu cài đặt');
    }

    public function upsertXpathTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'name_xpath' => ['nullable', 'string', 'max:10000'],
            'price_xpath' => ['nullable', 'string', 'max:10000'],
            'price_regex' => ['nullable', 'string', 'max:10000'],
            'name_fallbacks' => ['nullable', 'string', 'max:50000'],
            'price_fallbacks' => ['nullable', 'string', 'max:50000'],
            'is_approved' => ['nullable', 'boolean'],
        ]);

        $domainInput = trim((string) $data['domain']);
        $normalized = str_contains($domainInput, '://')
            ? CompetitorSite::normalizedDomainFromUrl($domainInput)
            : CompetitorSite::normalizedDomain($domainInput);

        if (! $normalized) {
            return back()->withErrors(['domain' => 'Domain không hợp lệ.'])->withInput();
        }

        $nameFallbacks = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            preg_split('/\R+/', (string) ($data['name_fallbacks'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
        $priceFallbacks = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            preg_split('/\R+/', (string) ($data['price_fallbacks'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));

        DB::transaction(function () use ($data, $normalized, $nameFallbacks, $priceFallbacks) {
            $template = CompetitorSiteTemplate::query()->firstOrNew(['domain' => $normalized]);
            $template->name = trim((string) ($data['name'] ?? '')) ?: null;
            $template->name_xpath = trim((string) ($data['name_xpath'] ?? '')) ?: null;
            $template->price_xpath = trim((string) ($data['price_xpath'] ?? '')) ?: null;
            $template->price_regex = trim((string) ($data['price_regex'] ?? '')) ?: null;

            $approved = (bool) ($data['is_approved'] ?? false);
            if ($approved && ! $template->is_approved) {
                $template->approved_at = now();
            }
            if (! $approved) {
                $template->approved_at = null;
            }
            $template->is_approved = $approved;
            $template->save();

            CompetitorSiteTemplateScrapeXpath::query()
                ->where('competitor_site_template_id', $template->id)
                ->whereIn('type', ['name', 'price'])
                ->delete();

            $rows = [];
            foreach ($nameFallbacks as $i => $xp) {
                $rows[] = [
                    'competitor_site_template_id' => $template->id,
                    'type' => 'name',
                    'position' => $i,
                    'xpath' => $xp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach ($priceFallbacks as $i => $xp) {
                $rows[] = [
                    'competitor_site_template_id' => $template->id,
                    'type' => 'price',
                    'position' => $i,
                    'xpath' => $xp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows) {
                CompetitorSiteTemplateScrapeXpath::query()->insert($rows);
            }
        });

        return back()->with('status', 'Đã lưu template XPath');
    }

    public function destroyXpathTemplate(Request $request, CompetitorSiteTemplate $competitorSiteTemplate): RedirectResponse
    {
        $competitorSiteTemplate->delete();

        return back()->with('status', 'Đã xoá template XPath');
    }

    public function updateUserXPaths(Request $request, User $user): RedirectResponse
    {
        $uid = (int) $user->id;

        $data = $request->validate([
            'own_name_xpath' => ['nullable', 'string', 'max:10000'],
            'own_price_xpath' => ['nullable', 'string', 'max:10000'],
            'own_price_regex' => ['nullable', 'string', 'max:10000'],
            'own_name_fallbacks' => ['nullable', 'string', 'max:50000'],
            'own_price_fallbacks' => ['nullable', 'string', 'max:50000'],
            'site_domain' => ['array'],
            'site_domain.*' => ['nullable', 'string', 'max:255'],
            'site_name_xpath' => ['array'],
            'site_name_xpath.*' => ['nullable', 'string', 'max:10000'],
            'site_price_xpath' => ['array'],
            'site_price_xpath.*' => ['nullable', 'string', 'max:10000'],
            'site_price_regex' => ['array'],
            'site_price_regex.*' => ['nullable', 'string', 'max:10000'],
            'site_name_fallbacks' => ['array'],
            'site_name_fallbacks.*' => ['nullable', 'string', 'max:50000'],
            'site_price_fallbacks' => ['array'],
            'site_price_fallbacks.*' => ['nullable', 'string', 'max:50000'],
        ]);

        $ownNameFallbacks = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            preg_split('/\R+/', (string) ($data['own_name_fallbacks'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
        $ownPriceFallbacks = array_values(array_filter(array_map(
            fn ($v) => trim((string) $v),
            preg_split('/\R+/', (string) ($data['own_price_fallbacks'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));

        DB::transaction(function () use ($uid, $data, $ownNameFallbacks, $ownPriceFallbacks) {
            $setting = UserScrapeSetting::query()->firstOrCreate(['user_id' => $uid]);
            $setting->own_name_xpath = trim((string) ($data['own_name_xpath'] ?? '')) ?: null;
            $setting->own_price_xpath = trim((string) ($data['own_price_xpath'] ?? '')) ?: null;
            $setting->price_regex = trim((string) ($data['own_price_regex'] ?? '')) ?: null;
            $setting->save();

            UserScrapeXpath::query()
                ->where('user_id', $uid)
                ->whereIn('type', ['name', 'price'])
                ->delete();

            foreach ($ownNameFallbacks as $i => $xp) {
                UserScrapeXpath::create([
                    'user_id' => $uid,
                    'type' => 'name',
                    'position' => $i,
                    'xpath' => $xp,
                ]);
            }
            foreach ($ownPriceFallbacks as $i => $xp) {
                UserScrapeXpath::create([
                    'user_id' => $uid,
                    'type' => 'price',
                    'position' => $i,
                    'xpath' => $xp,
                ]);
            }

            $sites = CompetitorSite::query()->where('user_id', $uid)->get();
            foreach ($sites as $site) {
                $id = (string) $site->id;

                $domainInput = trim((string) (($data['site_domain'][$id] ?? '') ?: ''));
                $domain = $domainInput !== '' ? CompetitorSite::normalizedDomain($domainInput) : null;

                $site->update([
                    'domain' => $domain,
                    'name_xpath' => trim((string) (($data['site_name_xpath'][$id] ?? '') ?: '')) ?: null,
                    'price_xpath' => trim((string) (($data['site_price_xpath'][$id] ?? '') ?: '')) ?: null,
                    'price_regex' => trim((string) (($data['site_price_regex'][$id] ?? '') ?: '')) ?: null,
                ]);

                CompetitorSiteScrapeXpath::query()
                    ->where('competitor_site_id', $site->id)
                    ->whereIn('type', ['name', 'price'])
                    ->delete();

                $siteNameFallbacks = array_values(array_filter(array_map(
                    fn ($v) => trim((string) $v),
                    preg_split('/\R+/', (string) (($data['site_name_fallbacks'][$id] ?? '') ?: ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
                )));
                foreach ($siteNameFallbacks as $i => $xp) {
                    CompetitorSiteScrapeXpath::create([
                        'competitor_site_id' => $site->id,
                        'type' => 'name',
                        'position' => $i,
                        'xpath' => $xp,
                    ]);
                }

                $sitePriceFallbacks = array_values(array_filter(array_map(
                    fn ($v) => trim((string) $v),
                    preg_split('/\R+/', (string) (($data['site_price_fallbacks'][$id] ?? '') ?: ''), -1, PREG_SPLIT_NO_EMPTY) ?: []
                )));
                foreach ($sitePriceFallbacks as $i => $xp) {
                    CompetitorSiteScrapeXpath::create([
                        'competitor_site_id' => $site->id,
                        'type' => 'price',
                        'position' => $i,
                        'xpath' => $xp,
                    ]);
                }
            }
        });

        return redirect()->route('admin.settings.edit', ['xpath_user_id' => $uid])->with('status', 'Đã cập nhật XPath của shop');
    }
}
