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
                                                #{{ $u->id }} - {{ $u->name }} ({{ $u->email }}){{ $u->role === 'viewer' && $u->parent_user_id ? ' - viewer của #'.$u->parent_user_id : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('demo_user_id')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px">Link demo: <a href="{{ route('demo') }}" target="_blank">{{ route('demo') }}</a></div>
                                </div>
                                <div class="hint" style="margin-top:0">
                                    Có thể chọn Shop (owner) hoặc tài khoản con (viewer, chỉ xem).
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Check giá website</h2>
                            <p class="card-sub">Giới hạn tải để tránh treo hosting</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div class="hint" style="margin-top:0">
                                Cron gần nhất: bắt đầu {{ $scrapeStatus['last_started_at'] ?? '-' }}, kết thúc {{ $scrapeStatus['last_finished_at'] ?? '-' }}, chọn {{ $scrapeStatus['last_selected'] ?? '-' }}, đẩy job {{ $scrapeStatus['last_dispatched'] ?? '-' }}, job cập nhật {{ $scrapeStatus['last_updated'] ?? '-' }}, job gần nhất {{ $scrapeStatus['last_job_finished_at'] ?? '-' }}, lỗi job {{ $scrapeStatus['last_job_error'] ?? '-' }}.
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="website_scrape_batch_per_minute">Sản phẩm/phút</label>
                                    <input class="input" id="website_scrape_batch_per_minute" name="website_scrape_batch_per_minute" type="number" min="1" max="1000" value="{{ old('website_scrape_batch_per_minute', $setting->website_scrape_batch_per_minute ?? 40) }}">
                                    @error('website_scrape_batch_per_minute')<div class="error">{{ $message }}</div>@enderror
                                    <div class="hint" style="margin-top:6px">Khuyến nghị 40–80.</div>
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
                                    <div class="hint" style="margin-top:6px">Khuyến nghị 7–15.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="actions" style="justify-content:flex-end">
                        <button class="btn" type="submit">Lưu</button>
                    </div>
                </form>

                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                    <div class="card-header" style="padding:16px 16px 6px">
                        <h2 class="card-title" style="font-size:18px">Thư viện XPath theo domain</h2>
                        <p class="card-sub">Template dùng chung toàn hệ thống (copy-once khi shop tạo đối thủ theo domain)</p>
                    </div>
                    <div class="card-body" style="padding:8px 16px 16px">
                        <div class="hint" style="margin-top:0">Nhập 1 XPath mỗi dòng ở phần dự phòng.</div>

                        <div style="display:flex;flex-direction:column;gap:12px;margin-top:10px">
                            <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                                <div class="card-header" style="padding:14px 14px 6px">
                                    <h3 class="card-title" style="font-size:16px">Thêm / cập nhật template</h3>
                                </div>
                                <div class="card-body" style="padding:8px 14px 14px">
                                    <form method="POST" action="{{ route('admin.xpath-templates.upsert') }}">
                                        @csrf
                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="tpl_domain">Domain</label>
                                                <input class="input" id="tpl_domain" name="domain" type="text" placeholder="thegioididong.com" required>
                                                @error('domain')<div class="error">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="tpl_name">Tên hiển thị</label>
                                                <input class="input" id="tpl_name" name="name" type="text" placeholder="Thế Giới Di Động">
                                            </div>
                                        </div>

                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="tpl_name_xpath">XPath tên (primary)</label>
                                                <textarea class="input" id="tpl_name_xpath" name="name_xpath" rows="2" style="min-height:44px"></textarea>
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="tpl_price_xpath">XPath giá (primary)</label>
                                                <textarea class="input" id="tpl_price_xpath" name="price_xpath" rows="2" style="min-height:44px"></textarea>
                                            </div>
                                        </div>

                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="tpl_price_regex">Regex lọc giá</label>
                                                <textarea class="input" id="tpl_price_regex" name="price_regex" rows="2" style="min-height:44px"></textarea>
                                            </div>
                                            <div class="field" style="margin-top:0;display:flex;align-items:flex-end;gap:10px">
                                                <label style="display:flex;align-items:center;gap:8px">
                                                    <input type="checkbox" name="is_approved" value="1">
                                                    <span class="label" style="margin:0">Đã duyệt</span>
                                                </label>
                                                <div class="hint" style="margin-top:0">Chỉ template đã duyệt mới tự áp dụng cho shop.</div>
                                            </div>
                                        </div>

                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="tpl_name_fallbacks">XPath tên dự phòng</label>
                                                <textarea class="input" id="tpl_name_fallbacks" name="name_fallbacks" rows="5"></textarea>
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="tpl_price_fallbacks">XPath giá dự phòng</label>
                                                <textarea class="input" id="tpl_price_fallbacks" name="price_fallbacks" rows="5"></textarea>
                                            </div>
                                        </div>

                                        <div class="actions" style="justify-content:flex-end">
                                            <button class="btn" type="submit">Lưu template</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            @forelse($templates as $t)
                                @php($nameFallbacksText = $t->scrapeXpaths->where('type', 'name')->sortBy('position')->pluck('xpath')->implode("\n"))
                                @php($priceFallbacksText = $t->scrapeXpaths->where('type', 'price')->sortBy('position')->pluck('xpath')->implode("\n"))
                                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                                    <div class="card-header" style="padding:14px 14px 6px;display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                                        <div>
                                            <h3 class="card-title" style="font-size:16px;margin:0">{{ $t->domain }}</h3>
                                            <div class="hint" style="margin-top:4px">
                                                Dùng bởi {{ (int) ($templateUsage[$t->domain] ?? 0) }} shop • Duyệt: {{ $t->is_approved ? 'có' : 'không' }}
                                            </div>
                                        </div>
                                        <form method="POST" action="{{ route('admin.xpath-templates.destroy', $t) }}" onsubmit="return confirm('Xoá template này?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-secondary" type="submit">Xoá</button>
                                        </form>
                                    </div>
                                    <div class="card-body" style="padding:8px 14px 14px">
                                        <form method="POST" action="{{ route('admin.xpath-templates.upsert') }}">
                                            @csrf
                                            <input type="hidden" name="domain" value="{{ $t->domain }}">
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                                <div class="field" style="margin-top:0">
                                                    <label class="label">Tên hiển thị</label>
                                                    <input class="input" name="name" type="text" value="{{ $t->name }}">
                                                </div>
                                                <div class="field" style="margin-top:0;display:flex;align-items:flex-end;gap:10px">
                                                    <label style="display:flex;align-items:center;gap:8px">
                                                        <input type="checkbox" name="is_approved" value="1" @checked($t->is_approved)>
                                                        <span class="label" style="margin:0">Đã duyệt</span>
                                                    </label>
                                                    <div class="hint" style="margin-top:0">{{ $t->approved_at ? 'Từ '.$t->approved_at->format('d/m/Y H:i') : '' }}</div>
                                                </div>
                                            </div>

                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                                <div class="field" style="margin-top:0">
                                                    <label class="label">XPath tên (primary)</label>
                                                    <textarea class="input" name="name_xpath" rows="2" style="min-height:44px">{{ $t->name_xpath }}</textarea>
                                                </div>
                                                <div class="field" style="margin-top:0">
                                                    <label class="label">XPath giá (primary)</label>
                                                    <textarea class="input" name="price_xpath" rows="2" style="min-height:44px">{{ $t->price_xpath }}</textarea>
                                                </div>
                                            </div>

                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                                <div class="field" style="margin-top:0">
                                                    <label class="label">Regex lọc giá</label>
                                                    <textarea class="input" name="price_regex" rows="2" style="min-height:44px">{{ $t->price_regex }}</textarea>
                                                </div>
                                                <div></div>
                                            </div>

                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                                <div class="field" style="margin-top:0">
                                                    <label class="label">XPath tên dự phòng</label>
                                                    <textarea class="input" name="name_fallbacks" rows="5">{{ $nameFallbacksText }}</textarea>
                                                </div>
                                                <div class="field" style="margin-top:0">
                                                    <label class="label">XPath giá dự phòng</label>
                                                    <textarea class="input" name="price_fallbacks" rows="5">{{ $priceFallbacksText }}</textarea>
                                                </div>
                                            </div>

                                            <div class="actions" style="justify-content:flex-end">
                                                <button class="btn" type="submit">Lưu</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="hint">Chưa có template nào.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                    <div class="card-header" style="padding:16px 16px 6px">
                        <h2 class="card-title" style="font-size:18px">XPath của shop</h2>
                        <p class="card-sub">Tổng hợp XPath mà shop đang dùng, và cho phép sửa trực tiếp</p>
                    </div>
                    <div class="card-body" style="padding:8px 16px 16px">
                        <form method="GET" action="{{ route('admin.settings.edit') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                            <div class="field" style="margin-top:0;min-width:220px">
                                <label class="label" for="xpath_user_id">User ID</label>
                                <input class="input" id="xpath_user_id" name="xpath_user_id" type="number" min="1" value="{{ $xpathUserId }}" placeholder="VD: 12">
                            </div>
                            <button class="btn btn-secondary" type="submit" style="height:44px">Xem</button>
                        </form>

                        @if($xpathUser)
                            <div class="pill" style="margin-top:12px">Shop: #{{ $xpathUser->id }} - {{ $xpathUser->name }} ({{ $xpathUser->email }})</div>

                            <form method="POST" action="{{ route('admin.xpath-users.update', $xpathUser) }}" style="margin-top:12px">
                                @csrf

                                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                                    <div class="card-header" style="padding:14px 14px 6px">
                                        <h3 class="card-title" style="font-size:16px">XPath của shop (website của bạn)</h3>
                                    </div>
                                    <div class="card-body" style="padding:8px 14px 14px">
                                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                                            <div class="field" style="margin-top:0">
                                                <label class="label">XPath tên (primary)</label>
                                                <textarea class="input" name="own_name_xpath" rows="2" style="min-height:44px">{{ $xpathUserSetting?->own_name_xpath }}</textarea>
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label">XPath giá (primary)</label>
                                                <textarea class="input" name="own_price_xpath" rows="2" style="min-height:44px">{{ $xpathUserSetting?->own_price_xpath }}</textarea>
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label">Regex lọc giá</label>
                                                <textarea class="input" name="own_price_regex" rows="2" style="min-height:44px">{{ $xpathUserSetting?->price_regex }}</textarea>
                                            </div>
                                        </div>

                                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                            <div class="field" style="margin-top:0">
                                                <label class="label">XPath tên dự phòng</label>
                                                <textarea class="input" name="own_name_fallbacks" rows="5">{{ $xpathOwnNameFallbacks->implode("\n") }}</textarea>
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label">XPath giá dự phòng</label>
                                                <textarea class="input" name="own_price_fallbacks" rows="5">{{ $xpathOwnPriceFallbacks->implode("\n") }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:12px">
                                    <div class="card-header" style="padding:14px 14px 6px">
                                        <h3 class="card-title" style="font-size:16px">XPath đối thủ ({{ $xpathUserSites->count() }})</h3>
                                    </div>
                                    <div class="card-body" style="padding:8px 14px 14px;display:flex;flex-direction:column;gap:12px">
                                        @foreach($xpathUserSites as $s)
                                            @php($siteNameFallbacksText = $s->scrapeXpaths->where('type', 'name')->sortBy('position')->pluck('xpath')->implode("\n"))
                                            @php($sitePriceFallbacksText = $s->scrapeXpaths->where('type', 'price')->sortBy('position')->pluck('xpath')->implode("\n"))
                                            <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0;border:1px solid var(--border)">
                                                <div class="card-header" style="padding:12px 12px 6px">
                                                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start">
                                                        <div>
                                                            <div style="font-weight:800">{{ $s->name }} <span class="hint">(#{{ $s->id }})</span></div>
                                                            <div class="hint" style="margin-top:4px">{{ $s->domain ?: '-' }}</div>
                                                        </div>
                                                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                                                            <button
                                                                class="btn btn-secondary"
                                                                type="submit"
                                                                formmethod="POST"
                                                                formaction="{{ route('admin.xpath-users.promote-site', [$xpathUser, $s]) }}"
                                                                onclick="return confirm('Duyệt và chuyển XPath của site này vào Thư viện XPath theo domain? Template hiện tại (nếu có) sẽ bị cập nhật theo nội dung đang nhập.')"
                                                            >
                                                                Duyệt → Thư viện
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-body" style="padding:8px 12px 12px">
                                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                                        <div class="field" style="margin-top:0">
                                                            <label class="label">Domain</label>
                                                            <input class="input" name="site_domain[{{ $s->id }}]" type="text" value="{{ $s->domain }}" placeholder="cellphones.com.vn">
                                                        </div>
                                                        <div class="hint" style="margin-top:0;display:flex;align-items:flex-end">Domain dùng để tự map template.</div>
                                                    </div>
                                                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-top:12px">
                                                        <div class="field" style="margin-top:0">
                                                            <label class="label">XPath tên (primary)</label>
                                                            <textarea class="input" name="site_name_xpath[{{ $s->id }}]" rows="2" style="min-height:44px">{{ $s->name_xpath }}</textarea>
                                                        </div>
                                                        <div class="field" style="margin-top:0">
                                                            <label class="label">XPath giá (primary)</label>
                                                            <textarea class="input" name="site_price_xpath[{{ $s->id }}]" rows="2" style="min-height:44px">{{ $s->price_xpath }}</textarea>
                                                        </div>
                                                        <div class="field" style="margin-top:0">
                                                            <label class="label">Regex lọc giá</label>
                                                            <textarea class="input" name="site_price_regex[{{ $s->id }}]" rows="2" style="min-height:44px">{{ $s->price_regex }}</textarea>
                                                        </div>
                                                    </div>
                                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                                        <div class="field" style="margin-top:0">
                                                            <label class="label">XPath tên dự phòng</label>
                                                            <textarea class="input" name="site_name_fallbacks[{{ $s->id }}]" rows="5">{{ $siteNameFallbacksText }}</textarea>
                                                        </div>
                                                        <div class="field" style="margin-top:0">
                                                            <label class="label">XPath giá dự phòng</label>
                                                            <textarea class="input" name="site_price_fallbacks[{{ $s->id }}]" rows="5">{{ $sitePriceFallbacksText }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="actions" style="justify-content:flex-end">
                                    <button class="btn" type="submit">Lưu XPath shop</button>
                                </div>
                            </form>
                        @elseif($xpathUserId)
                            <div class="error" style="margin-top:12px">Không tìm thấy user: #{{ $xpathUserId }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
