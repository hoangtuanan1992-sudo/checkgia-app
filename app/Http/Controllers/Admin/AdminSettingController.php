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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSettingController extends Controller
{
    public function edit(Request $request): View
    {
        $setting = AppSetting::current() ?? new AppSetting;

        $demoUsers = User::query()
            ->where('role', 'owner')
            ->whereNull('parent_user_id')
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

        $templates = collect();
        if (Schema::hasTable('competitor_site_templates')) {
            $templatesQuery = CompetitorSiteTemplate::query()->orderBy('domain');
            if (Schema::hasTable('competitor_site_template_scrape_xpaths')) {
                $templatesQuery->with(['scrapeXpaths' => function ($q) {
                    $q->orderBy('type')->orderBy('position');
                }]);
            }
            $templates = $templatesQuery->get();
        }

        $templateUsage = collect();
        if (Schema::hasTable('competitor_sites') && Schema::hasColumn('competitor_sites', 'domain')) {
            $templateUsage = CompetitorSite::query()
                ->whereNotNull('domain')
                ->selectRaw('domain, COUNT(*) as c')
                ->groupBy('domain')
                ->pluck('c', 'domain');
        }

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
                if (Schema::hasTable('user_scrape_settings')) {
                    $xpathUserSetting = UserScrapeSetting::query()->firstOrCreate(['user_id' => $uid]);
                }
                if (Schema::hasTable('user_scrape_xpaths')) {
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
                }
                if (Schema::hasTable('competitor_sites')) {
                    $sitesQuery = CompetitorSite::query()
                        ->where('user_id', $uid)
                        ->orderBy('position')
                        ->orderBy('name');
                    if (Schema::hasTable('competitor_site_scrape_xpaths')) {
                        $sitesQuery->with(['scrapeXpaths' => function ($q) {
                            $q->orderBy('type')->orderBy('position');
                        }]);
                    }
                    $xpathUserSites = $sitesQuery->get();
                }
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
            'demo_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', 'owner')->whereNull('parent_user_id')],
            'website_scrape_batch_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'website_scrape_concurrency' => ['nullable', 'integer', 'min:1', 'max:50'],
            'website_scrape_timeout_seconds' => ['nullable', 'integer', 'min:3', 'max:60'],
            'ai_provider' => ['nullable', 'in:grok,gemini,chatgpt'],
            'ai_api_key' => ['nullable', 'string', 'max:4096'],
            'ai_model' => ['nullable', 'string', 'max:255'],
            'grok_api_key' => ['nullable', 'string', 'max:4096'],
            'grok_model' => ['nullable', 'string', 'max:255'],
            'gemini_api_key' => ['nullable', 'string', 'max:4096'],
            'gemini_model' => ['nullable', 'string', 'max:255'],
            'chatgpt_api_key' => ['nullable', 'string', 'max:4096'],
            'chatgpt_model' => ['nullable', 'string', 'max:255'],
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
        $selectedAiProvider = strtolower((string) ($data['ai_provider'] ?? ''));
        $selectedAiConfig = $selectedAiProvider !== '' ? $this->aiProviderConfig($selectedAiProvider) : null;
        if ($selectedAiConfig && Schema::hasColumn($table, 'ai_provider')) {
            $data['ai_provider'] = $selectedAiProvider;
            $keyInput = trim((string) ($data['ai_api_key'] ?? ''));
            if ($keyInput !== '' && Schema::hasColumn($table, $selectedAiConfig['key_column'])) {
                $data[$selectedAiConfig['key_column']] = $keyInput;
            }
            if (Schema::hasColumn($table, $selectedAiConfig['model_column'])) {
                $data[$selectedAiConfig['model_column']] = trim((string) ($data['ai_model'] ?? '')) ?: null;
            }
        } else {
            unset($data['ai_provider']);
        }
        unset($data['ai_api_key'], $data['ai_model']);

        foreach (['website_scrape_batch_per_minute', 'website_scrape_concurrency', 'website_scrape_timeout_seconds'] as $col) {
            if (array_key_exists($col, $data) && ! Schema::hasColumn($table, $col)) {
                unset($data[$col]);
            }
        }

        foreach (['website_scrape_batch_per_minute', 'website_scrape_concurrency', 'website_scrape_timeout_seconds'] as $col) {
            if (array_key_exists($col, $data) && (string) $data[$col] === '') {
                $data[$col] = null;
            }
        }

        foreach (['grok_api_key', 'gemini_api_key', 'chatgpt_api_key'] as $col) {
            if (! array_key_exists($col, $data)) {
                continue;
            }

            $keyInput = trim((string) $data[$col]);
            if ($keyInput === '' || ! Schema::hasColumn($table, $col)) {
                unset($data[$col]);
            } else {
                $data[$col] = $keyInput;
            }
        }

        foreach (['grok_model', 'gemini_model', 'chatgpt_model'] as $col) {
            if (! array_key_exists($col, $data)) {
                continue;
            }

            if (! Schema::hasColumn($table, $col)) {
                unset($data[$col]);
            } else {
                $data[$col] = trim((string) $data[$col]) ?: null;
            }
        }

        $setting->fill($data);
        $setting->save();

        return back()->with('status', 'Đã lưu cài đặt');
    }

    public function upsertXpathTemplate(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('competitor_site_templates')) {
            return back()->withErrors(['domain' => 'Chưa có bảng thư viện XPath. Hãy chạy migration.'])->withInput();
        }

        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'name_xpath' => ['nullable', 'string', 'max:10000'],
            'price_xpath' => ['nullable', 'string', 'max:10000'],
            'price_regex' => ['nullable', 'string', 'max:10000'],
            'use_browser' => ['nullable', 'boolean'],
            'name_css' => ['nullable', 'string', 'max:10000'],
            'price_css' => ['nullable', 'string', 'max:10000'],
            'price_attribute' => ['nullable', 'string', 'max:255'],
            'api_url_template' => ['nullable', 'string', 'max:10000'],
            'api_name_path' => ['nullable', 'string', 'max:255'],
            'api_price_path' => ['nullable', 'string', 'max:255'],
            'api_headers' => ['nullable', 'string', 'max:20000'],
            'name_fallbacks' => ['nullable', 'string', 'max:50000'],
            'price_fallbacks' => ['nullable', 'string', 'max:50000'],
            'is_approved' => ['nullable', 'boolean'],
        ]);

        $normalized = CompetitorSite::normalizedDomainFromUserInput($data['domain'] ?? '');
        if (! $normalized) {
            return back()->withErrors(['domain' => 'Domain không hợp lệ.'])->withInput();
        }

        $nameFallbacks = $this->splitXPathLines($data['name_fallbacks'] ?? '');
        $priceFallbacks = $this->splitXPathLines($data['price_fallbacks'] ?? '');
        try {
            $apiHeaders = $this->parseApiHeaders($data['api_headers'] ?? '');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['api_headers' => $e->getMessage()])->withInput();
        }

        DB::transaction(function () use ($data, $normalized, $nameFallbacks, $priceFallbacks, $apiHeaders) {
            $template = CompetitorSiteTemplate::query()->firstOrNew(['domain' => $normalized]);
            $template->name = trim((string) ($data['name'] ?? '')) ?: null;
            $template->name_xpath = trim((string) ($data['name_xpath'] ?? '')) ?: null;
            $template->price_xpath = trim((string) ($data['price_xpath'] ?? '')) ?: null;
            $template->price_regex = trim((string) ($data['price_regex'] ?? '')) ?: null;

            $table = $template->getTable();
            $advanced = [
                'use_browser' => (bool) ($data['use_browser'] ?? false),
                'name_css' => trim((string) ($data['name_css'] ?? '')) ?: null,
                'price_css' => trim((string) ($data['price_css'] ?? '')) ?: null,
                'price_attribute' => trim((string) ($data['price_attribute'] ?? '')) ?: null,
                'api_url_template' => trim((string) ($data['api_url_template'] ?? '')) ?: null,
                'api_name_path' => trim((string) ($data['api_name_path'] ?? '')) ?: null,
                'api_price_path' => trim((string) ($data['api_price_path'] ?? '')) ?: null,
                'api_headers' => $apiHeaders,
            ];
            foreach ($advanced as $column => $value) {
                if (Schema::hasColumn($table, $column)) {
                    $template->{$column} = $value;
                }
            }

            $approved = (bool) ($data['is_approved'] ?? false);
            if ($approved && ! $template->is_approved) {
                $template->approved_at = now();
            }
            if (! $approved) {
                $template->approved_at = null;
            }
            $template->is_approved = $approved;
            $template->save();

            if (! Schema::hasTable('competitor_site_template_scrape_xpaths')) {
                return;
            }

            CompetitorSiteTemplateScrapeXpath::query()
                ->where('competitor_site_template_id', $template->id)
                ->whereIn('type', ['name', 'price'])
                ->delete();

            $rows = [];
            foreach ($nameFallbacks as $i => $xpath) {
                $rows[] = [
                    'competitor_site_template_id' => $template->id,
                    'type' => 'name',
                    'position' => $i,
                    'xpath' => $xpath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach ($priceFallbacks as $i => $xpath) {
                $rows[] = [
                    'competitor_site_template_id' => $template->id,
                    'type' => 'price',
                    'position' => $i,
                    'xpath' => $xpath,
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

        $ownNameFallbacks = $this->splitXPathLines($data['own_name_fallbacks'] ?? '');
        $ownPriceFallbacks = $this->splitXPathLines($data['own_price_fallbacks'] ?? '');

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

            foreach ($ownNameFallbacks as $i => $xpath) {
                UserScrapeXpath::create([
                    'user_id' => $uid,
                    'type' => 'name',
                    'position' => $i,
                    'xpath' => $xpath,
                ]);
            }
            foreach ($ownPriceFallbacks as $i => $xpath) {
                UserScrapeXpath::create([
                    'user_id' => $uid,
                    'type' => 'price',
                    'position' => $i,
                    'xpath' => $xpath,
                ]);
            }

            $hasDomainColumn = Schema::hasColumn('competitor_sites', 'domain');
            $sites = CompetitorSite::query()->where('user_id', $uid)->get();
            foreach ($sites as $site) {
                $id = (string) $site->id;
                $updates = [
                    'name_xpath' => trim((string) (($data['site_name_xpath'][$id] ?? '') ?: '')) ?: null,
                    'price_xpath' => trim((string) (($data['site_price_xpath'][$id] ?? '') ?: '')) ?: null,
                    'price_regex' => trim((string) (($data['site_price_regex'][$id] ?? '') ?: '')) ?: null,
                ];

                if ($hasDomainColumn) {
                    $domainInput = trim((string) (($data['site_domain'][$id] ?? '') ?: ''));
                    $updates['domain'] = $domainInput !== '' ? CompetitorSite::normalizedDomainFromUserInput($domainInput) : null;
                }

                $site->update($updates);

                CompetitorSiteScrapeXpath::query()
                    ->where('competitor_site_id', $site->id)
                    ->whereIn('type', ['name', 'price'])
                    ->delete();

                foreach ($this->splitXPathLines($data['site_name_fallbacks'][$id] ?? '') as $i => $xpath) {
                    CompetitorSiteScrapeXpath::create([
                        'competitor_site_id' => $site->id,
                        'type' => 'name',
                        'position' => $i,
                        'xpath' => $xpath,
                    ]);
                }

                foreach ($this->splitXPathLines($data['site_price_fallbacks'][$id] ?? '') as $i => $xpath) {
                    CompetitorSiteScrapeXpath::create([
                        'competitor_site_id' => $site->id,
                        'type' => 'price',
                        'position' => $i,
                        'xpath' => $xpath,
                    ]);
                }
            }
        });

        return redirect()->route('admin.settings.edit', ['xpath_user_id' => $uid])->with('status', 'Đã cập nhật XPath của shop');
    }

    public function promoteUserSiteToTemplate(Request $request, User $user, CompetitorSite $competitorSite): RedirectResponse
    {
        if ((int) $competitorSite->user_id !== (int) $user->id) {
            abort(404);
        }

        $redirectBase = route('admin.settings.edit', ['xpath_user_id' => (int) $user->id]);
        $anchor = trim((string) $request->query('anchor', ''));
        $redirectUrl = (preg_match('/^[A-Za-z0-9\-_]+$/', $anchor) === 1) ? ($redirectBase.'#'.$anchor) : $redirectBase;

        if (! Schema::hasTable('competitor_site_templates')) {
            return redirect()->to($redirectUrl)
                ->withErrors(['status' => 'Chưa có bảng thư viện XPath. Hãy chạy migrate.']);
        }

        $siteId = (string) $competitorSite->id;
        $domainInput = trim((string) $request->input("site_domain.$siteId", $competitorSite->domain));
        $domain = $domainInput !== '' ? CompetitorSite::normalizedDomainFromUserInput($domainInput) : null;
        if (! $domain) {
            $domain = CompetitorSite::normalizedDomainFromUserInput($competitorSite->name);
        }
        if (! $domain) {
            return redirect()->to($redirectUrl)
                ->withErrors(['status' => 'Site chưa có domain hợp lệ.']);
        }

        $nameXpath = trim((string) $request->input("site_name_xpath.$siteId", $competitorSite->name_xpath)) ?: null;
        $priceXpath = trim((string) $request->input("site_price_xpath.$siteId", $competitorSite->price_xpath)) ?: null;
        $priceRegex = trim((string) $request->input("site_price_regex.$siteId", $competitorSite->price_regex)) ?: null;
        $nameFallbacks = $this->splitXPathLines($request->input("site_name_fallbacks.$siteId", ''));
        $priceFallbacks = $this->splitXPathLines($request->input("site_price_fallbacks.$siteId", ''));

        DB::transaction(function () use ($competitorSite, $domain, $nameXpath, $priceXpath, $priceRegex, $nameFallbacks, $priceFallbacks) {
            $template = CompetitorSiteTemplate::query()->firstOrNew(['domain' => $domain]);
            if (! $template->name) {
                $template->name = $competitorSite->name ?: $domain;
            }
            $template->name_xpath = $nameXpath;
            $template->price_xpath = $priceXpath;
            $template->price_regex = $priceRegex;
            $template->is_approved = true;
            $template->approved_at = now();
            $template->save();

            if (! Schema::hasTable('competitor_site_template_scrape_xpaths')) {
                return;
            }

            CompetitorSiteTemplateScrapeXpath::query()
                ->where('competitor_site_template_id', $template->id)
                ->whereIn('type', ['name', 'price'])
                ->delete();

            $rows = [];
            foreach ($nameFallbacks as $i => $xpath) {
                $rows[] = [
                    'competitor_site_template_id' => $template->id,
                    'type' => 'name',
                    'position' => $i,
                    'xpath' => $xpath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            foreach ($priceFallbacks as $i => $xpath) {
                $rows[] = [
                    'competitor_site_template_id' => $template->id,
                    'type' => 'price',
                    'position' => $i,
                    'xpath' => $xpath,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($rows) {
                CompetitorSiteTemplateScrapeXpath::query()->insert($rows);
            }
        });

        return redirect()->to($redirectUrl)->with('status', 'Đã duyệt và chuyển XPath vào thư viện');
    }

    public function testAiProvider(Request $request, string $provider): JsonResponse
    {
        return $this->handleAiProviderRequest($request, $provider, false);
    }

    public function scanAiProviderModels(Request $request, string $provider): JsonResponse
    {
        return $this->handleAiProviderRequest($request, $provider, true);
    }

    private function handleAiProviderRequest(Request $request, string $provider, bool $storeModels): JsonResponse
    {
        $config = $this->aiProviderConfig($provider);
        if (! $config) {
            return response()->json([
                'ok' => false,
                'message' => 'Nhà cung cấp API không hợp lệ.',
            ], 404);
        }

        $data = $request->validate([
            'api_key' => ['nullable', 'string', 'max:4096'],
        ]);

        $setting = AppSetting::current() ?? new AppSetting;
        $key = trim((string) ($data['api_key'] ?? ''));
        if ($key === '') {
            $key = trim((string) ($setting->{$config['key_column']} ?? ''));
        }

        if ($key === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Chưa có API key để kiểm tra.',
            ], 422);
        }

        try {
            $models = $this->fetchAiProviderModels($config['provider'], $key);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Không kết nối được API: '.$e->getMessage(),
            ], 502);
        }

        if ($storeModels) {
            $table = (new AppSetting)->getTable();
            if (! Schema::hasColumn($table, $config['models_column'])) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Chưa có cột lưu danh sách mô hình. Hãy chạy migration.',
                ], 503);
            }

            $setting->{$config['models_column']} = $models;
            if (Schema::hasColumn($table, 'ai_provider')) {
                $setting->ai_provider = $config['provider'];
            }
            if (! $setting->{$config['model_column']} && isset($models[0]['id'])) {
                $setting->{$config['model_column']} = $models[0]['id'];
            }
            $setting->save();
        }

        return response()->json([
            'ok' => true,
            'message' => ($storeModels ? 'Đã quét ' : 'Key hợp lệ, tìm thấy ').count($models).' mô hình.',
            'models' => $models,
        ]);
    }

    private function aiProviderConfig(string $provider): ?array
    {
        return [
            'grok' => [
                'provider' => 'grok',
                'key_column' => 'grok_api_key',
                'model_column' => 'grok_model',
                'models_column' => 'grok_models',
            ],
            'gemini' => [
                'provider' => 'gemini',
                'key_column' => 'gemini_api_key',
                'model_column' => 'gemini_model',
                'models_column' => 'gemini_models',
            ],
            'chatgpt' => [
                'provider' => 'chatgpt',
                'key_column' => 'chatgpt_api_key',
                'model_column' => 'chatgpt_model',
                'models_column' => 'chatgpt_models',
            ],
        ][strtolower($provider)] ?? null;
    }

    private function splitXPathLines(mixed $value): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            preg_split('/\R+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: []
        )));
    }

    /**
     * @return array<string, string>|null
     */
    private function parseApiHeaders(mixed $value): ?array
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                throw new \InvalidArgumentException('API headers phai la JSON object hoac moi dong dang Header: value.');
            }

            $headers = [];
            foreach ($decoded as $key => $headerValue) {
                $key = trim((string) $key);
                $headerValue = trim((string) $headerValue);
                if ($key !== '' && $headerValue !== '') {
                    $headers[mb_substr($key, 0, 120)] = mb_substr($headerValue, 0, 1000);
                }
            }

            return $headers ?: null;
        }

        $headers = [];
        foreach (preg_split('/\R+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $separator = str_contains($line, ':') ? ':' : (str_contains($line, '=') ? '=' : null);
            if (! $separator) {
                throw new \InvalidArgumentException('Moi header API can co dang Header: value hoac Header=value.');
            }

            [$key, $headerValue] = array_map('trim', explode($separator, $line, 2));
            if ($key !== '' && $headerValue !== '') {
                $headers[mb_substr($key, 0, 120)] = mb_substr($headerValue, 0, 1000);
            }
        }

        return $headers ?: null;
    }

    private function fetchAiProviderModels(string $provider, string $apiKey): array
    {
        $provider = strtolower($provider);

        if ($provider === 'gemini') {
            $response = Http::acceptJson()
                ->timeout(20)
                ->get('https://generativelanguage.googleapis.com/v1beta/models', [
                    'key' => $apiKey,
                ]);
        } elseif ($provider === 'grok') {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->get('https://api.x.ai/v1/models');
        } else {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(20)
                ->get('https://api.openai.com/v1/models');
        }

        if (! $response->successful()) {
            $message = $this->summarizeAiApiError($response->json(), $response->body(), $response->status());

            throw new \RuntimeException($message);
        }

        return $this->normalizeAiModels($response->json(), $provider);
    }

    private function normalizeAiModels(mixed $payload, string $provider): array
    {
        $payload = is_array($payload) ? $payload : [];
        $items = [];

        if ($provider === 'gemini') {
            $items = is_array($payload['models'] ?? null) ? $payload['models'] : [];
        } elseif (is_array($payload['data'] ?? null)) {
            $items = $payload['data'];
        } elseif (is_array($payload['models'] ?? null)) {
            $items = $payload['models'];
        }

        $models = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? $item['name'] ?? ''));
            if ($id === '') {
                continue;
            }

            $label = trim((string) ($item['displayName'] ?? $item['display_name'] ?? $item['name'] ?? $id));
            $models[$id] = [
                'id' => mb_substr($id, 0, 255),
                'label' => mb_substr($label ?: $id, 0, 255),
            ];
        }

        $models = array_values($models);
        usort($models, fn (array $a, array $b): int => strnatcasecmp((string) $a['id'], (string) $b['id']));

        return array_slice($models, 0, 300);
    }

    private function summarizeAiApiError(mixed $json, string $body, int $status): string
    {
        if (is_array($json)) {
            $message = $json['error']['message'] ?? $json['error'] ?? $json['message'] ?? null;
            if (is_string($message) && trim($message) !== '') {
                return trim($message);
            }
        }

        $body = trim(strip_tags($body));
        if ($body !== '') {
            return 'HTTP '.$status.' - '.mb_substr($body, 0, 300);
        }

        return 'HTTP '.$status;
    }
}
