@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:1100px">
        <div class="card-header">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="min-width:0">
                    <h1 class="card-title" style="margin:0">Cài đặt Shopee Extension</h1>
                    <p class="card-sub" style="margin:6px 0 0">Cấu hình dùng chung cho tất cả máy chạy extension</p>
                </div>
                <a class="btn btn-secondary" href="{{ route('shopee.dashboard') }}">Dashboard</a>
            </div>
        </div>
        <div class="card-body">
            @if(session('status'))
                <div class="pill" style="margin-bottom:14px">{{ session('status') }}</div>
            @endif

            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                <div class="card-header">
                    <h2 class="card-title" style="font-size:18px;margin:0">Cấu hình chung</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shopee.admin-settings.update') }}" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        @csrf
                        @method('PUT')

                        <label style="display:flex;gap:10px;align-items:center">
                            <input type="checkbox" name="shopee_enabled" value="1" @checked($setting->shopee_enabled)>
                            <span style="font-weight:700">Bật Shopee extension</span>
                        </label>

                        <div></div>

                        <div style="grid-column:1 / -1">
                            <div class="hint" style="margin-top:0">Token</div>
                            <input class="input" name="shopee_extension_token" value="{{ old('shopee_extension_token', $setting->shopee_extension_token) }}" required>
                            <div class="hint" style="margin-top:6px">Extension cần token này để gọi API. Không chia sẻ ra ngoài.</div>
                        </div>

                        <div>
                            <div class="hint" style="margin-top:0">Chu kỳ cập nhật (giây)</div>
                            <input class="input" type="number" min="10" max="86400" name="shopee_scrape_interval_seconds" value="{{ old('shopee_scrape_interval_seconds', $setting->shopee_scrape_interval_seconds) }}" required>
                        </div>

                        <div>
                            <div class="hint" style="margin-top:0">Nghỉ ngẫu nhiên (min / max giây)</div>
                            <div style="display:flex;gap:10px">
                                <input class="input" type="number" min="0" max="3600" name="shopee_rest_seconds_min" value="{{ old('shopee_rest_seconds_min', $setting->shopee_rest_seconds_min) }}" required>
                                <input class="input" type="number" min="0" max="3600" name="shopee_rest_seconds_max" value="{{ old('shopee_rest_seconds_max', $setting->shopee_rest_seconds_max) }}" required>
                            </div>
                        </div>

                        <div style="grid-column:1 / -1;display:flex;justify-content:flex-end">
                            <button class="btn" type="submit">Lưu</button>
                        </div>
                    </form>
                </div>
            </div>

            <div style="height:14px"></div>

            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                <div class="card-header">
                    <h2 class="card-title" style="font-size:18px;margin:0">Agents (máy chạy extension)</h2>
                </div>
                <div class="card-body">
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:60px">#</th>
                                    <th>Agent</th>
                                    <th style="width:160px">Last seen</th>
                                    <th style="width:120px">Bật</th>
                                    <th style="width:140px">Mode</th>
                                    <th>Chạy cho user</th>
                                    <th style="width:160px">Lưu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($agents as $idx => $agent)
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>
                                            <div style="font-weight:800">{{ $agent->name ?: $agent->agent_key }}</div>
                                            <div class="hint" style="margin-top:3px;word-break:break-word">{{ $agent->agent_key }}</div>
                                            <div class="hint" style="margin-top:3px">{{ $agent->platform }} {{ $agent->version }}</div>
                                        </td>
                                        <td>
                                            @if($agent->last_seen_at)
                                                {{ $agent->last_seen_at->format('d/m/Y H:i') }}
                                            @else
                                                <span class="hint" style="margin-top:0">---</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('shopee.admin-settings.agent.update', $agent) }}" style="display:flex;gap:10px;align-items:center">
                                                @csrf
                                                <input type="hidden" name="name" value="{{ $agent->name }}">
                                                <input type="checkbox" name="is_enabled" value="1" @checked($agent->is_enabled)>
                                        </td>
                                        <td>
                                                <select class="input" name="mode">
                                                    <option value="all" @selected($agent->mode === 'all')>Tất cả</option>
                                                    <option value="user" @selected($agent->mode === 'user')>1 user</option>
                                                </select>
                                        </td>
                                        <td>
                                                <select class="input" name="assigned_user_id">
                                                    <option value="">--</option>
                                                    @foreach($users as $u)
                                                        <option value="{{ $u->id }}" @selected((int) $agent->assigned_user_id === (int) $u->id)>
                                                            {{ $u->email }} ({{ $u->role }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                        </td>
                                        <td>
                                                <button class="btn btn-secondary" type="submit" style="padding:6px 10px">Lưu</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="hint">Chưa có agent nào. Cài extension và mở Chrome để agent tự đăng ký.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
