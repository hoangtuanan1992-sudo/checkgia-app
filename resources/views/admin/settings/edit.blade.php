@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:820px">
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

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Check giá website</h2>
                            <p class="card-sub">Giới hạn tải để tránh treo hosting</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
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
            </div>
        </div>
    </div>
@endsection
