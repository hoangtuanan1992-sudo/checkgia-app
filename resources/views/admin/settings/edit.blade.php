@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1500px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Cài đặt tổng</h1>
                    <p class="card-sub">Cấu hình chung toàn hệ thống</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                    <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Người dùng</a>
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                </div>
            </div>

            <div class="card-body">
                <form method="POST" action="{{ route('admin.settings.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">SMTP</h2>
                            <p class="card-sub">Cấu hình gửi email</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="mail_mailer">Mailer</label>
                                    <select class="input" id="mail_mailer" name="mail_mailer">
                                        @foreach(['smtp' => 'smtp', 'log' => 'log', 'array' => 'array'] as $v => $label)
                                            <option value="{{ $v }}" @selected(old('mail_mailer', $setting->mail_mailer ?: 'smtp') === $v)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('mail_mailer')<div class="error">{{ $message }}</div>@enderror
                                </div>

                                <div class="field" style="margin-top:0">
                                    <label class="label" for="mail_encryption">Encryption</label>
                                    <select class="input" id="mail_encryption" name="mail_encryption">
                                        <option value="" @selected(old('mail_encryption', $setting->mail_encryption) === null || old('mail_encryption', $setting->mail_encryption) === '')>none</option>
                                        <option value="tls" @selected(old('mail_encryption', $setting->mail_encryption) === 'tls')>tls</option>
                                        <option value="ssl" @selected(old('mail_encryption', $setting->mail_encryption) === 'ssl')>ssl</option>
                                    </select>
                                    @error('mail_encryption')<div class="error">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="mail_host">Host</label>
                                    <input class="input" id="mail_host" name="mail_host" type="text" value="{{ old('mail_host', $setting->mail_host) }}" placeholder="smtp.gmail.com">
                                    @error('mail_host')<div class="error">{{ $message }}</div>@enderror
                                </div>

                                <div class="field" style="margin-top:0">
                                    <label class="label" for="mail_port">Port</label>
                                    <input class="input" id="mail_port" name="mail_port" type="number" min="1" max="65535" value="{{ old('mail_port', $setting->mail_port) }}" placeholder="587">
                                    @error('mail_port')<div class="error">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="mail_username">Username</label>
                                    <input class="input" id="mail_username" name="mail_username" type="text" value="{{ old('mail_username', $setting->mail_username) }}" placeholder="user@example.com">
                                    @error('mail_username')<div class="error">{{ $message }}</div>@enderror
                                </div>

                                <div class="field" style="margin-top:0">
                                    <label class="label" for="mail_password">Password</label>
                                    <input class="input" id="mail_password" name="mail_password" type="password" value="" placeholder="Nhập để cập nhật" autocomplete="new-password">
                                    @error('mail_password')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px">Để trống nếu không đổi.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">From</h2>
                            <p class="card-sub">Tên/email hiển thị khi gửi</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="mail_from_address">Email gửi đi (From)</label>
                                    <input class="input" id="mail_from_address" name="mail_from_address" type="email" value="{{ old('mail_from_address', $setting->mail_from_address) }}" placeholder="no-reply@example.com">
                                    @error('mail_from_address')<div class="error">{{ $message }}</div>@enderror
                                </div>

                                <div class="field" style="margin-top:0">
                                    <label class="label" for="mail_from_name">Tên người gửi (From name)</label>
                                    <input class="input" id="mail_from_name" name="mail_from_name" type="text" value="{{ old('mail_from_name', $setting->mail_from_name) }}" placeholder="CheckGia">
                                    @error('mail_from_name')<div class="error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Demo</h2>
                            <p class="card-sub">Chọn tài khoản demo để truy cập nhanh từ trang chủ</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="demo_user_id">Tài khoản demo (Shop)</label>
                                    <select class="input" id="demo_user_id" name="demo_user_id">
                                        <option value="" @selected(old('demo_user_id', $setting->demo_user_id) === null || (string) old('demo_user_id', $setting->demo_user_id) === '')>Không dùng demo</option>
                                        @foreach($demoUsers as $u)
                                            <option value="{{ $u->id }}" @selected((string) old('demo_user_id', $setting->demo_user_id) === (string) $u->id)>
                                                #{{ $u->id }} - {{ $u->name }} ({{ $u->email }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('demo_user_id')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px">Link demo: <a href="{{ route('demo') }}" target="_blank">{{ route('demo') }}</a></div>
                                </div>

                                <div class="hint" style="margin-top:0">
                                    Chỉ hiển thị các Shop (role owner, không phải sub-user).
                                </div>
                            </div>
                        </div>
                    </div>

                    @php
                        $scrapeStatus = $scrapeStatus ?? [];
                    @endphp
                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Check giá website</h2>
                            <p class="card-sub">Giới hạn tải để tránh treo hosting</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div class="hint" style="margin-top:0">
                                Cron gần nhất: bắt đầu {{ $scrapeStatus['last_started_at'] ?? '-' }},
                                kết thúc {{ $scrapeStatus['last_finished_at'] ?? '-' }},
                                chọn {{ $scrapeStatus['last_selected'] ?? '-' }},
                                đẩy job {{ $scrapeStatus['last_dispatched'] ?? '-' }},
                                job cập nhật {{ $scrapeStatus['last_updated'] ?? '-' }},
                                job gần nhất {{ $scrapeStatus['last_job_finished_at'] ?? '-' }},
                                lỗi job {{ $scrapeStatus['last_job_error'] ?? '-' }}.
                            </div>

                            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="website_scrape_batch_per_minute">Sản phẩm/phút</label>
                                    <input class="input" id="website_scrape_batch_per_minute" name="website_scrape_batch_per_minute" type="number" min="1" max="1000" value="{{ old('website_scrape_batch_per_minute', $setting->website_scrape_batch_per_minute ?? 40) }}">
                                    @error('website_scrape_batch_per_minute')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px">Khuyến nghị 40-80.</div>
                                </div>

                                <div class="field" style="margin-top:0">
                                    <label class="label" for="website_scrape_concurrency">Concurrency</label>
                                    <input class="input" id="website_scrape_concurrency" name="website_scrape_concurrency" type="number" min="1" max="50" value="{{ old('website_scrape_concurrency', $setting->website_scrape_concurrency ?? 10) }}">
                                    @error('website_scrape_concurrency')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px">Số kết nối tải HTML song song.</div>
                                </div>

                                <div class="field" style="margin-top:0">
                                    <label class="label" for="website_scrape_timeout_seconds">Timeout (giây)</label>
                                    <input class="input" id="website_scrape_timeout_seconds" name="website_scrape_timeout_seconds" type="number" min="3" max="60" value="{{ old('website_scrape_timeout_seconds', $setting->website_scrape_timeout_seconds ?? 7) }}">
                                    @error('website_scrape_timeout_seconds')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px">Khuyến nghị 7-15.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @php
                        $aiProviders = [
                            'grok' => [
                                'label' => 'Grok',
                                'keyField' => 'grok_api_key',
                                'modelField' => 'grok_model',
                                'modelsField' => 'grok_models',
                                'placeholder' => 'xai-...',
                            ],
                            'gemini' => [
                                'label' => 'Gemini',
                                'keyField' => 'gemini_api_key',
                                'modelField' => 'gemini_model',
                                'modelsField' => 'gemini_models',
                                'placeholder' => 'AIza...',
                            ],
                            'chatgpt' => [
                                'label' => 'ChatGPT',
                                'keyField' => 'chatgpt_api_key',
                                'modelField' => 'chatgpt_model',
                                'modelsField' => 'chatgpt_models',
                                'placeholder' => 'sk-...',
                            ],
                        ];
                        $activeAiProvider = old('ai_provider', $setting->ai_provider ?: 'chatgpt');
                        $activeAiProvider = array_key_exists($activeAiProvider, $aiProviders) ? $activeAiProvider : 'chatgpt';
                        $activeAi = $aiProviders[$activeAiProvider];
                        $activeAiModels = $setting->{$activeAi['modelsField']} ?? [];
                        $activeAiModels = is_array($activeAiModels) ? $activeAiModels : [];
                        $activeAiModel = (string) old('ai_model', $setting->{$activeAi['modelField']} ?? '');
                        $aiProviderData = [];
                        foreach ($aiProviders as $providerKey => $provider) {
                            $providerModels = $setting->{$provider['modelsField']} ?? [];
                            $providerModels = is_array($providerModels) ? $providerModels : [];
                            $aiProviderData[$providerKey] = [
                                'label' => $provider['label'],
                                'placeholder' => $provider['placeholder'],
                                'hasKey' => filled($setting->getRawOriginal($provider['keyField'])),
                                'selectedModel' => (string) ($setting->{$provider['modelField']} ?? ''),
                                'models' => $providerModels,
                                'testUrl' => route('admin.settings.ai.test', $providerKey),
                                'modelsUrl' => route('admin.settings.ai.models', $providerKey),
                            ];
                        }
                    @endphp

                    <div class="card ai-provider-card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px" data-ai-provider="{{ $activeAiProvider }}">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Kết nối API AI</h2>
                            <p class="card-sub">Chỉ dùng một mô hình AI tại một thời điểm</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="ai_provider">Nhà cung cấp</label>
                                    <select class="input" id="ai_provider" name="ai_provider">
                                        @foreach($aiProviders as $providerKey => $provider)
                                            <option value="{{ $providerKey }}" @selected($activeAiProvider === $providerKey)>{{ $provider['label'] }}</option>
                                        @endforeach
                                    </select>
                                    @error('ai_provider')<div class="error">{{ $message }}</div>@enderror
                                </div>

                                <div class="field" style="margin-top:0">
                                    <label class="label" for="ai_api_key">API key</label>
                                    <input
                                        class="input ai-key-input"
                                        id="ai_api_key"
                                        name="ai_api_key"
                                        type="password"
                                        value=""
                                        placeholder="{{ filled($setting->getRawOriginal($activeAi['keyField'])) ? 'Nhập để cập nhật key' : $activeAi['placeholder'] }}"
                                        autocomplete="new-password"
                                    >
                                    @error('ai_api_key')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px" data-ai-key-state>
                                        {{ filled($setting->getRawOriginal($activeAi['keyField'])) ? 'Đã lưu key cho '.$activeAi['label'] : 'Chưa lưu key cho '.$activeAi['label'] }}
                                    </div>
                                </div>
                            </div>

                            <div class="field" style="margin-top:12px">
                                <label class="label" for="ai_model">Mô hình mặc định</label>
                                <select class="input ai-model-select" id="ai_model" name="ai_model">
                                    <option value="" @selected($activeAiModel === '')>-- Chưa chọn --</option>
                                    @foreach($activeAiModels as $model)
                                        @php
                                            $modelId = is_array($model) ? (string) ($model['id'] ?? '') : (string) $model;
                                            $modelLabel = is_array($model) ? (string) ($model['label'] ?? $modelId) : $modelId;
                                        @endphp
                                        @if($modelId !== '')
                                            <option value="{{ $modelId }}" @selected($activeAiModel === $modelId)>{{ $modelLabel }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('ai_model')<div class="error">{{ $message }}</div>@enderror
                            </div>

                            <div class="hint ai-model-count" style="margin-top:8px">
                                Đã quét {{ number_format(count($activeAiModels), 0, ',', '.') }} mô hình.
                            </div>
                            <div class="hint ai-provider-status" style="margin-top:8px;min-height:20px"></div>

                            <div class="actions" style="justify-content:flex-end;gap:8px;flex-wrap:wrap">
                                <button class="btn btn-secondary ai-test-key" type="button">Test key</button>
                                <button class="btn btn-secondary ai-scan-models" type="button">Quét mô hình</button>
                            </div>
                        </div>
                    </div>

                    <div class="actions" style="justify-content:flex-end">
                        <button class="btn" type="submit">Lưu</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csrfToken = @json(csrf_token());
            const providerData = @json($aiProviderData);
            const providerSelect = document.getElementById('ai_provider');
            const aiCard = document.querySelector('.ai-provider-card');
            const modelSelect = document.getElementById('ai_model');

            function setStatus(message, ok = true) {
                const status = aiCard ? aiCard.querySelector('.ai-provider-status') : null;
                if (!status) return;
                status.textContent = message || '';
                status.style.color = ok ? 'var(--success)' : 'var(--danger)';
            }

            function currentProvider() {
                return providerSelect ? providerSelect.value : 'chatgpt';
            }

            function renderModels(models) {
                if (!modelSelect || !Array.isArray(models)) return;

                const provider = currentProvider();
                const current = (providerData[provider] && providerData[provider].selectedModel) || modelSelect.value || '';
                modelSelect.innerHTML = '';

                const blank = document.createElement('option');
                blank.value = '';
                blank.textContent = '-- Chưa chọn --';
                modelSelect.appendChild(blank);

                models.forEach((model) => {
                    const id = String(model && model.id ? model.id : '').trim();
                    if (!id) return;
                    const option = document.createElement('option');
                    option.value = id;
                    option.textContent = String((model && model.label) || id);
                    modelSelect.appendChild(option);
                });

                if (current && models.some((model) => String(model && model.id) === current)) {
                    modelSelect.value = current;
                } else if (models.length > 0 && models[0].id) {
                    modelSelect.value = String(models[0].id);
                }

                const count = aiCard ? aiCard.querySelector('.ai-model-count') : null;
                if (count) {
                    count.textContent = `Đã quét ${models.length.toLocaleString('vi-VN')} mô hình.`;
                }
                if (providerData[provider]) {
                    providerData[provider].models = models;
                    providerData[provider].selectedModel = modelSelect.value || '';
                }
            }

            function renderProviderConfig() {
                if (!aiCard || !providerSelect) return;
                const provider = currentProvider();
                const data = providerData[provider] || {};
                const keyInput = aiCard.querySelector('.ai-key-input');
                const keyState = aiCard.querySelector('[data-ai-key-state]');

                aiCard.dataset.aiProvider = provider;
                if (keyInput) {
                    keyInput.value = '';
                    keyInput.placeholder = data.hasKey ? 'Nhập để cập nhật key' : (data.placeholder || '');
                }
                if (keyState) {
                    keyState.textContent = data.hasKey ? `Đã lưu key cho ${data.label || provider}` : `Chưa lưu key cho ${data.label || provider}`;
                }
                renderModels(Array.isArray(data.models) ? data.models : []);
                setStatus('', true);
            }

            async function callAiEndpoint(button, shouldRenderModels) {
                if (!aiCard) return;
                const keyInput = aiCard.querySelector('.ai-key-input');
                const provider = currentProvider();
                const dataForProvider = providerData[provider] || {};
                const url = shouldRenderModels ? (dataForProvider.modelsUrl || '') : (dataForProvider.testUrl || '');
                if (!url) return;

                const originalText = button.textContent;
                button.disabled = true;
                button.textContent = shouldRenderModels ? 'Đang quét...' : 'Đang test...';
                setStatus('Đang kết nối API...', true);

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            api_key: keyInput ? keyInput.value : '',
                        }),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.ok) {
                        setStatus(data.message || `Lỗi HTTP ${response.status}`, false);
                        return;
                    }

                    if (shouldRenderModels && Array.isArray(data.models)) {
                        renderModels(data.models);
                    }
                    if (providerData[provider] && keyInput && keyInput.value.trim() !== '') {
                        providerData[provider].hasKey = true;
                    }
                    setStatus(data.message || 'Kết nối thành công.', true);
                } catch (error) {
                    setStatus(error && error.message ? error.message : 'Không kết nối được API.', false);
                } finally {
                    button.disabled = false;
                    button.textContent = originalText;
                }
            }

            document.querySelectorAll('.ai-test-key').forEach((button) => {
                button.addEventListener('click', () => callAiEndpoint(button, false));
            });
            document.querySelectorAll('.ai-scan-models').forEach((button) => {
                button.addEventListener('click', () => callAiEndpoint(button, true));
            });
            if (providerSelect) {
                providerSelect.addEventListener('change', renderProviderConfig);
            }
            if (modelSelect) {
                modelSelect.addEventListener('change', () => {
                    const provider = currentProvider();
                    if (providerData[provider]) {
                        providerData[provider].selectedModel = modelSelect.value || '';
                    }
                });
            }
        });
    </script>

    <style>
        @media (max-width: 900px) {
            form [style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
@endsection
