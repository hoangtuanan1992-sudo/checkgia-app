<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        return view('admin.settings.edit', compact('setting', 'demoUsers', 'scrapeStatus'));
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
