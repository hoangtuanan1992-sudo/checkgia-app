@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1500px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Cài đặt</h1>
                    <p class="card-sub">Cấu hình đối thủ, XPath, tự động cập nhật và thông báo</p>
                </div>
                <div style="display:flex;gap:8px">
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
                </div>
            </div>
            <div class="card-body">
                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                    <div class="card-header" style="padding:16px 16px 6px">
                        <h2 class="card-title" style="font-size:18px">Danh sách đối thủ</h2>
                        <p class="card-sub">Tạo danh sách đối thủ trước, sau đó gán link theo từng sản phẩm</p>
                    </div>
                    <div class="card-body" style="padding:8px 16px 16px">
                        <form method="POST" action="{{ route('dashboard.competitors.sites.store') }}">
                            @csrf
                            <div style="display:grid;grid-template-columns:1fr auto;gap:12px;align-items:end">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="site_name">website đối thủ</label>
                                    <input class="input" id="site_name" name="name" type="text" value="{{ old('name') }}" placeholder="VD: laptopaz.com hoặc https://laptopaz.vn/" required>
                                    @error('name')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <button class="btn" type="submit" style="height:44px">Thêm đối thủ</button>
                            </div>
                        </form>

                        @if($competitorSites->isNotEmpty())
                            <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                                @foreach($competitorSites as $s)
                                    <div class="pill">
                                        {{ $s->name }}
                                        <form method="POST" action="{{ route('dashboard.competitors.sites.move', $s) }}" style="display:inline">
                                            @csrf
                                            <input type="hidden" name="direction" value="up">
                                            <button type="submit" style="border:none;background:transparent;color:#3730a3;cursor:pointer" title="Lên">↑</button>
                                        </form>
                                        <form method="POST" action="{{ route('dashboard.competitors.sites.move', $s) }}" style="display:inline">
                                            @csrf
                                            <input type="hidden" name="direction" value="down">
                                            <button type="submit" style="border:none;background:transparent;color:#3730a3;cursor:pointer" title="Xuống">↓</button>
                                        </form>
                                        <form method="POST" action="{{ route('dashboard.competitors.sites.destroy', $s) }}" onsubmit="return confirm('Xoá đối thủ này?')" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="border:none;background:transparent;color:#3730a3;cursor:pointer">x</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="hint">Chưa có đối thủ nào.</div>
                        @endif
                    </div>
                </div>

                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                    <div class="card-header" style="padding:16px 16px 6px">
                        <h2 class="card-title" style="font-size:18px">Cài đặt lấy tên & giá</h2>
                        <p class="card-sub">Nhập XPath để hệ thống tự động lấy tên/giá từ website của bạn và đối thủ</p>
                    </div>
                    <div class="card-body" style="padding:8px 16px 16px">
                        <form method="POST" action="{{ route('dashboard.scrape-settings.update') }}">
                            @csrf

                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="own_name_xpath">XPath tên sản phẩm của bạn</label>
                                    <input class="input" id="own_name_xpath" name="own_name_xpath" type="text" value="{{ old('own_name_xpath', $userSetting->own_name_xpath) }}" placeholder="//h1">
                                    @error('own_name_xpath')<div class="error">{{ $message }}</div>@enderror
                                    <div style="margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px">
                                        <span class="label">XPath tên dự phòng</span>
                                        <button type="button" class="icon-btn js-add-fallback" data-container="#ownNameFallbacks" data-name="own_name_fallbacks[]">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div id="ownNameFallbacks" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                                        @foreach(old('own_name_fallbacks', $ownNameFallbacks->all()) as $v)
                                            <div style="display:flex;gap:8px;align-items:center">
                                                <input class="input" name="own_name_fallbacks[]" type="text" value="{{ $v }}">
                                                <button type="button" class="icon-btn js-remove-fallback">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        <path d="M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="own_price_xpath">XPath giá của bạn</label>
                                    <input class="input" id="own_price_xpath" name="own_price_xpath" type="text" value="{{ old('own_price_xpath', $userSetting->own_price_xpath) }}" placeholder="//*[contains(@class,'price')]">
                                    @error('own_price_xpath')<div class="error">{{ $message }}</div>@enderror
                                    <div style="margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px">
                                        <span class="label">XPath giá dự phòng</span>
                                        <button type="button" class="icon-btn js-add-fallback" data-container="#ownPriceFallbacks" data-name="own_price_fallbacks[]">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div id="ownPriceFallbacks" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                                        @foreach(old('own_price_fallbacks', $ownPriceFallbacks->all()) as $v)
                                            <div style="display:flex;gap:8px;align-items:center">
                                                <input class="input" name="own_price_fallbacks[]" type="text" value="{{ $v }}">
                                                <button type="button" class="icon-btn js-remove-fallback">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        <path d="M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="field">
                                <label class="label" for="own_price_regex">Regex lọc giá (tuỳ chọn)</label>
                                <input class="input" id="own_price_regex" name="own_price_regex" type="text" value="{{ old('own_price_regex', $userSetting->price_regex) }}" placeholder="/(\\d[\\d\\.\\,\\s]*)/">
                                @error('own_price_regex')<div class="error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field">
                                <label class="label" for="scrape_interval_minutes">Tự động cập nhật giá mỗi (phút)</label>
                                <input class="input" id="scrape_interval_minutes" name="scrape_interval_minutes" type="number" min="5" step="1" value="{{ old('scrape_interval_minutes', $userSetting->scrape_interval_minutes ?? 5) }}" required>
                                @error('scrape_interval_minutes')<div class="error">{{ $message }}</div>@enderror
                                <div class="hint">Tối thiểu 5 phút. Hệ thống sẽ tự cập nhật giá các sản phẩm đã thêm để so sánh.</div>
                            </div>

                            @if($competitorSites->isNotEmpty())
                                <div class="table-wrap" style="margin-top:14px">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th style="min-width:180px">Đối thủ</th>
                                                <th style="min-width:320px">XPath tên (tuỳ chọn)</th>
                                                <th style="min-width:320px">XPath giá</th>
                                                <th style="min-width:220px">Regex giá (tuỳ chọn)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($competitorSites as $s)
                                                <tr>
                                                    <td style="font-weight:600">{{ $s->name }}</td>
                                                    <td>
                                                        <input class="input" name="site_name_xpath[{{ $s->id }}]" type="text" value="{{ old('site_name_xpath.'.$s->id, $s->name_xpath) }}">
                                                        <div style="margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px">
                                                            <span class="label">Tên dự phòng</span>
                                                            <button type="button" class="icon-btn js-add-fallback" data-container="#siteNameFallbacks{{ $s->id }}" data-name="site_name_fallbacks[{{ $s->id }}][]">
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                    <path d="M12 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                    <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <div id="siteNameFallbacks{{ $s->id }}" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                                                            @php($fallbackName = $s->scrapeXpaths->where('type','name')->sortBy('position')->pluck('xpath')->all())
                                                            @foreach(old('site_name_fallbacks.'.$s->id, $fallbackName) as $v)
                                                                <div style="display:flex;gap:8px;align-items:center">
                                                                    <input class="input" name="site_name_fallbacks[{{ $s->id }}][]" type="text" value="{{ $v }}">
                                                                    <button type="button" class="icon-btn js-remove-fallback">
                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                            <path d="M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                            <path d="M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input class="input" name="site_price_xpath[{{ $s->id }}]" type="text" value="{{ old('site_price_xpath.'.$s->id, $s->price_xpath) }}">
                                                        <div style="margin-top:10px;display:flex;align-items:center;justify-content:space-between;gap:8px">
                                                            <span class="label">Giá dự phòng</span>
                                                            <button type="button" class="icon-btn js-add-fallback" data-container="#sitePriceFallbacks{{ $s->id }}" data-name="site_price_fallbacks[{{ $s->id }}][]">
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                    <path d="M12 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                    <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                </svg>
                                                            </button>
                                                        </div>
                                                        <div id="sitePriceFallbacks{{ $s->id }}" style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
                                                            @php($fallbackPrice = $s->scrapeXpaths->where('type','price')->sortBy('position')->pluck('xpath')->all())
                                                            @foreach(old('site_price_fallbacks.'.$s->id, $fallbackPrice) as $v)
                                                                <div style="display:flex;gap:8px;align-items:center">
                                                                    <input class="input" name="site_price_fallbacks[{{ $s->id }}][]" type="text" value="{{ $v }}">
                                                                    <button type="button" class="icon-btn js-remove-fallback">
                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                            <path d="M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                            <path d="M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                        </svg>
                                                                    </button>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <input class="input" name="site_price_regex[{{ $s->id }}]" type="text" value="{{ old('site_price_regex.'.$s->id, $s->price_regex) }}">
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif

                            <div class="actions">
                                <button class="btn" type="submit">Lưu cài đặt</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                    <div class="card-header" style="padding:16px 16px 6px">
                        <h2 class="card-title" style="font-size:18px">Theo dõi biến động giá</h2>
                        <p class="card-sub">Thiết lập kênh nhận thông báo và rule theo dõi</p>
                    </div>
                    <div class="card-body" style="padding:8px 16px 16px">
                        @if(auth()->user()->isViewer())
                            <div class="hint">Tài khoản con chỉ được xem. Vui lòng đăng nhập tài khoản chính để thay đổi cài đặt.</div>
                        @else
                            <form method="POST" action="{{ route('account.notifications') }}" autocomplete="off">
                                @csrf
                                @method('PUT')
                                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                                    <div class="card-header" style="padding:12px 12px 6px">
                                        <h3 class="card-title" style="font-size:16px">Kênh nhận thông báo</h3>
                                    </div>
                                    <div class="card-body" style="padding:8px 12px 12px">
                                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="email_to">Email</label>
                                                <input class="input" id="email_to" name="email_to" type="email" value="{{ old('email_to', $notification->email_to) }}" placeholder="you@example.com" autocomplete="off">
                                                @error('email_to')<div class="error">{{ $message }}</div>@enderror
                                                <label style="display:flex;gap:8px;align-items:center;margin-top:8px">
                                                    <input type="checkbox" name="email_enabled" value="1" @checked(old('email_enabled', $notification->email_enabled))>
                                                    <span class="label" style="margin:0">Bật email</span>
                                                </label>
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="telegram_chat_id">Telegram</label>
                                                <input class="input" id="telegram_chat_id" name="telegram_chat_id" type="text" value="{{ old('telegram_chat_id', $notification->telegram_chat_id) }}" placeholder="chat_id" autocomplete="off">
                                                @error('telegram_chat_id')<div class="error">{{ $message }}</div>@enderror
                                                <div class="field" style="margin-top:10px">
                                                    <label class="label" for="telegram_bot_token">Bot token</label>
                                                    <input class="input" id="telegram_bot_token" name="telegram_bot_token" type="password" value="{{ old('telegram_bot_token') }}" placeholder="123456:ABC..." autocomplete="new-password">
                                                    @error('telegram_bot_token')<div class="error">{{ $message }}</div>@enderror
                                                </div>
                                                <label style="display:flex;gap:8px;align-items:center;margin-top:8px">
                                                    <input type="checkbox" name="telegram_enabled" value="1" @checked(old('telegram_enabled', $notification->telegram_enabled))>
                                                    <span class="label" style="margin:0">Bật telegram</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div style="height:1px;background:var(--border);margin:14px 0"></div>

                                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                                    <div class="card-header" style="padding:12px 12px 6px">
                                        <h3 class="card-title" style="font-size:16px">Rule theo dõi</h3>
                                        <p class="card-sub">Bật rule cần dùng. Bấm dấu + để chỉnh tiêu đề/nội dung (tuỳ chọn)</p>
                                    </div>
                                    <div class="card-body" style="padding:8px 12px 12px">
                                        <div class="hint" style="margin-top:0">
                                            Mẹo: Ưu tiên dùng các biến có đuôi <strong>_fmt</strong> để tự format tiền như 200.000đ.
                                            <button type="button" class="icon-btn icon-btn-sm js-toggle" data-target="#ruleVars" style="margin-left:8px">?</button>
                                        </div>
                                        <div id="ruleVars" style="display:none;margin-top:10px;border:1px solid var(--border);border-radius:12px;padding:12px;background:#fff">
                                            <div style="font-weight:600;margin-bottom:8px">Danh sách biến</div>
                                            <div class="hint" style="margin-top:0;line-height:1.7">
                                                {product_name}: tên sản phẩm<br>
                                                {own_price_fmt}: giá của bạn (đã format)<br>
                                                {competitor_name}: tên đối thủ<br>
                                                {competitor_price_fmt}: giá đối thủ (đã format)<br>
                                                {previous_competitor_price_fmt}: giá đối thủ trước đó (đã format)<br>
                                                {diff_amount_fmt}: chênh lệch (giá đối thủ - giá bạn, đã format)<br>
                                                {diff_percent_fmt}: chênh lệch % (đã format)<br>
                                                {drop_amount_fmt}: số tiền đối thủ giảm (đã format)<br>
                                                {competitor_url}: link đối thủ<br>
                                                {time}: thời gian
                                            </div>
                                        </div>

                                        <div style="border:1px solid var(--border);border-radius:12px;padding:12px">
                                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                                                <div style="display:flex;flex-direction:column;gap:2px">
                                                    <div style="font-weight:600">Biến động giá</div>
                                                    <div class="hint" style="margin-top:0">Thông báo khi giá đối thủ thay đổi</div>
                                                </div>
                                                <div style="display:flex;gap:10px;align-items:center">
                                                    <label style="display:flex;gap:8px;align-items:center">
                                                        <input type="checkbox" name="notify_all_price_changes" value="1" @checked(old('notify_all_price_changes', $notification->notify_all_price_changes))>
                                                        <span class="label" style="margin:0">Bật</span>
                                                    </label>
                                                    <button type="button" class="icon-btn icon-btn-sm js-toggle" data-target="#tplAllPrice">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M12 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="tplAllPrice" style="display:none;margin-top:12px">
                                                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                                    <div class="field" style="margin-top:0">
                                                        <label class="label" for="notify_all_price_changes_title">Tiêu đề</label>
                                                        <input class="input" id="notify_all_price_changes_title" name="notify_all_price_changes_title" type="text" value="{{ old('notify_all_price_changes_title', $notification->notify_all_price_changes_title ?: 'Biến động giá') }}">
                                                        @error('notify_all_price_changes_title')<div class="error">{{ $message }}</div>@enderror
                                                    </div>
                                                </div>
                                                <div class="field">
                                                    <label class="label" for="notify_all_price_changes_body">Nội dung</label>
                                                    <textarea class="input" id="notify_all_price_changes_body" name="notify_all_price_changes_body" rows="6" placeholder="Nội dung (tuỳ chọn)">{{ old('notify_all_price_changes_body', $notification->notify_all_price_changes_body ?: "Sản phẩm: {product_name}\nGiá của bạn: {own_price_fmt}\nĐối thủ: {competitor_name}\nGiá đối thủ: {competitor_price_fmt}\nGiá trước đó: {previous_competitor_price_fmt}\nChênh lệch: {diff_amount_fmt} ({diff_percent_fmt})\nLink: {competitor_url}\nThời gian: {time}") }}</textarea>
                                                    @error('notify_all_price_changes_body')<div class="error">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                        </div>

                                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px">
                                            <div style="border:1px solid var(--border);border-radius:12px;padding:12px">
                                                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                                                    <div style="font-weight:600">Đối thủ rẻ hơn bạn</div>
                                                    <button type="button" class="icon-btn icon-btn-sm js-toggle" data-target="#tplCheaper">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M12 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="field" style="margin-top:10px">
                                                    <label class="label" for="alert_competitor_cheaper_percent">Ngưỡng (%)</label>
                                                    <input class="input" id="alert_competitor_cheaper_percent" name="alert_competitor_cheaper_percent" type="number" min="1" max="95" step="1" value="{{ old('alert_competitor_cheaper_percent', $notification->alert_competitor_cheaper_percent) }}" placeholder="VD: 5">
                                                    @error('alert_competitor_cheaper_percent')<div class="error">{{ $message }}</div>@enderror
                                                </div>
                                                <div id="tplCheaper" style="display:none;margin-top:12px">
                                                    <div class="field" style="margin-top:0">
                                                        <label class="label" for="alert_cheaper_title">Tiêu đề</label>
                                                        <input class="input" id="alert_cheaper_title" name="alert_cheaper_title" type="text" value="{{ old('alert_cheaper_title', $notification->alert_cheaper_title ?: 'Cảnh báo giá') }}">
                                                        @error('alert_cheaper_title')<div class="error">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="field">
                                                        <label class="label" for="alert_cheaper_body">Nội dung</label>
                                                        <textarea class="input" id="alert_cheaper_body" name="alert_cheaper_body" rows="5" placeholder="Nội dung (tuỳ chọn)">{{ old('alert_cheaper_body', $notification->alert_cheaper_body ?: "Đối thủ {competitor_name} đang rẻ hơn bạn {cheaper_percent_fmt}.\nSản phẩm: {product_name}\nGiá của bạn: {own_price_fmt}\nGiá đối thủ: {competitor_price_fmt}\nChênh lệch: {diff_amount_fmt} ({diff_percent_fmt})\nLink: {competitor_url}\nThời gian: {time}") }}</textarea>
                                                        @error('alert_cheaper_body')<div class="error">{{ $message }}</div>@enderror
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="border:1px solid var(--border);border-radius:12px;padding:12px">
                                                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
                                                    <div style="font-weight:600">Đối thủ giảm giá</div>
                                                    <button type="button" class="icon-btn icon-btn-sm js-toggle" data-target="#tplDrop">
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M12 5v14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <div class="field" style="margin-top:10px">
                                                    <label class="label" for="alert_competitor_drop_amount">Ngưỡng (đ)</label>
                                                    <input class="input" id="alert_competitor_drop_amount" name="alert_competitor_drop_amount" type="number" min="1" step="1" value="{{ old('alert_competitor_drop_amount', $notification->alert_competitor_drop_amount) }}" placeholder="VD: 200000">
                                                    @error('alert_competitor_drop_amount')<div class="error">{{ $message }}</div>@enderror
                                                </div>
                                                <div id="tplDrop" style="display:none;margin-top:12px">
                                                    <div class="field" style="margin-top:0">
                                                        <label class="label" for="alert_drop_title">Tiêu đề</label>
                                                        <input class="input" id="alert_drop_title" name="alert_drop_title" type="text" value="{{ old('alert_drop_title', $notification->alert_drop_title ?: 'Cảnh báo giá') }}">
                                                        @error('alert_drop_title')<div class="error">{{ $message }}</div>@enderror
                                                    </div>
                                                    <div class="field">
                                                        <label class="label" for="alert_drop_body">Nội dung</label>
                                                        <textarea class="input" id="alert_drop_body" name="alert_drop_body" rows="5" placeholder="Nội dung (tuỳ chọn)">{{ old('alert_drop_body', $notification->alert_drop_body ?: "Đối thủ {competitor_name} vừa giảm giá.\nSản phẩm: {product_name}\nGiá trước đó: {previous_competitor_price_fmt}\nGiá hiện tại: {competitor_price_fmt}\nGiảm: {drop_amount_fmt}\nLink: {competitor_url}\nThời gian: {time}") }}</textarea>
                                                        @error('alert_drop_body')<div class="error">{{ $message }}</div>@enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="actions">
                                    <button class="btn" type="submit">Lưu thông báo</button>
                                </div>
                            </form>
                            <div class="hint">Thông báo sẽ được gửi khi hệ thống tự cập nhật giá.</div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
    <script>
        (function () {
            function addRow(container, name) {
                const row = document.createElement('div');
                row.style.display = 'flex';
                row.style.gap = '8px';
                row.style.alignItems = 'center';

                const input = document.createElement('input');
                input.className = 'input';
                input.name = name;
                input.type = 'text';

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'icon-btn js-remove-fallback';
                btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M6 6l12 12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
                btn.addEventListener('click', () => row.remove());

                row.appendChild(input);
                row.appendChild(btn);
                container.appendChild(row);
                input.focus();
            }

            document.querySelectorAll('.js-add-fallback').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const sel = btn.dataset.container;
                    const name = btn.dataset.name;
                    const container = document.querySelector(sel);
                    if (!container || !name) return;
                    addRow(container, name);
                });
            });

            document.querySelectorAll('.js-remove-fallback').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const row = btn.closest('div');
                    if (row) row.remove();
                });
            });

            document.querySelectorAll('.js-toggle').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const sel = btn.dataset.target;
                    if (!sel) return;
                    const el = document.querySelector(sel);
                    if (!el) return;
                    el.style.display = el.style.display === 'none' ? '' : 'none';
                });
            });
        })();
    </script>
@endsection
