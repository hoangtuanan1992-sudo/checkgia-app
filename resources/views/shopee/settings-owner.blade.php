@extends('layouts.app')

@section('content')
    <style>
        .input {
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid var(--border);
            background: #fff;
            color: var(--text);
            outline: none;
            width: 100%;
        }
    </style>
    <div class="card" style="max-width:1100px">
        <div class="card-header">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="min-width:0">
                    <h1 class="card-title" style="margin:0">Cài đặt Shopee</h1>
                    <p class="card-sub" style="margin:6px 0 0">Thêm tên shop đối thủ và sắp xếp thứ tự cột</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <a class="btn btn-secondary" href="{{ route('shopee.dashboard') }}">Dashboard</a>
                    @if(auth()->user()?->isAdmin())
                        <a class="btn btn-secondary" href="{{ route('shopee.admin-settings') }}">Admin</a>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-body">
            @if(session('status'))
                <div class="pill" style="margin-bottom:14px">{{ session('status') }}</div>
            @endif

            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                <div class="card-header">
                    <h2 class="card-title" style="font-size:18px;margin:0">Shops</h2>
                    <p class="card-sub" style="margin-top:6px">Danh sách shop đối thủ để tạo cột so sánh</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shopee.shops.store') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
                        @csrf
                        <div style="flex:1 1 320px">
                            <div class="hint" style="margin-top:0">Tên shop</div>
                            <input class="input" name="name" placeholder="VD: LaptopGame" required>
                        </div>
                        <button class="btn" type="submit">Thêm</button>
                    </form>

                    <div style="height:12px"></div>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:60px">#</th>
                                    <th>Shop</th>
                                    <th style="width:120px">Thứ tự</th>
                                    <th style="width:190px">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shops as $i => $shop)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td style="font-weight:800">{{ $shop->name }}</td>
                                        <td>
                                            <div style="display:flex;gap:6px;align-items:center">
                                                <form method="POST" action="{{ route('shopee.shops.move', $shop) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button class="btn btn-secondary" type="submit" style="padding:6px 10px">↑</button>
                                                </form>
                                                <form method="POST" action="{{ route('shopee.shops.move', $shop) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button class="btn btn-secondary" type="submit" style="padding:6px 10px">↓</button>
                                                </form>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:8px;align-items:center">
                                                <form method="POST" action="{{ route('shopee.shops.destroy', $shop) }}" onsubmit="return confirm('Xoá shop này?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-secondary" type="submit" style="padding:6px 10px;color:var(--danger)">Xoá</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="hint">Chưa có shop nào.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="hint" style="margin-top:12px">
                        Thêm sản phẩm Shopee ở trang Dashboard.
                    </div>
                </div>
            </div>

            <div class="card" style="max-width:none;box-shadow:none;margin-top:14px">
                <div class="card-header">
                    <h2 class="card-title" style="font-size:18px;margin:0">Theo dõi biến động giá</h2>
                    <p class="card-sub" style="margin-top:6px">Thiết lập kênh nhận thông báo và rule theo dõi</p>
                </div>
                <div class="card-body">
                    @if(auth()->user()->isViewer())
                        <div class="hint">Tài khoản con chỉ được xem. Vui lòng đăng nhập tài khoản chính để thay đổi cài đặt.</div>
                    @else
                        <form method="POST" action="{{ route('account.notifications') }}" autocomplete="off">
                            @csrf
                            @method('PUT')
                            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                                <div class="card-header">
                                    <h3 class="card-title" style="font-size:16px;margin:0">Kênh nhận thông báo</h3>
                                </div>
                                <div class="card-body">
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

                            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                                <div class="card-header">
                                    <h3 class="card-title" style="font-size:16px;margin:0">Rule theo dõi</h3>
                                    <p class="card-sub" style="margin-top:6px">Bật rule cần dùng. Bấm dấu + để chỉnh tiêu đề/nội dung (tuỳ chọn)</p>
                                </div>
                                <div class="card-body">
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

                                    <div style="border:1px solid var(--border);border-radius:12px;padding:12px;margin-top:12px">
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
                        <div class="hint">Thông báo sẽ được gửi khi hệ thống cập nhật giá Shopee.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
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
