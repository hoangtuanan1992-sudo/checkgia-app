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

                        <div>
                            <div class="hint" style="margin-top:0">Số lần check tối đa/ngày/sản phẩm</div>
                            <input class="input" type="number" min="1" max="1000" name="shopee_max_checks_per_day" value="{{ old('shopee_max_checks_per_day', $setting->shopee_max_checks_per_day ?? 24) }}" required>
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
                                    <th style="min-width:220px">Note</th>
                                    <th style="min-width:180px">Kết nối</th>
                                    <th style="min-width:260px">Trạng thái</th>
                                    <th style="width:160px">Last seen</th>
                                    <th style="width:120px">Bật</th>
                                    <th style="width:140px">Mode</th>
                                    <th>Chạy cho user</th>
                                    <th style="width:220px">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($agents as $idx => $agent)
                                    @php
                                        $isOffline = !$agent->last_seen_at || $agent->last_seen_at->diffInMinutes(now()) > 10;
                                    @endphp
                                    <tr>
                                        <td>{{ $idx + 1 }}</td>
                                        <td>
                                            <div style="font-weight:800">{{ $agent->name ?: $agent->agent_key }}</div>
                                            <div class="hint" style="margin-top:3px;word-break:break-word;font-size:11px">{{ $agent->agent_key }}</div>
                                            <div class="hint" style="margin-top:3px;font-size:11px">{{ $agent->platform }} {{ $agent->version }}</div>
                                        </td>
                                        <form method="POST" action="{{ route('shopee.admin-settings.agent.update', $agent) }}" id="form-agent-{{ $agent->id }}">
                                            @csrf
                                            <input type="hidden" name="name" value="{{ $agent->name }}">
                                            <td>
                                                <input class="input" name="note" value="{{ $agent->note }}" placeholder="VD: Máy văn phòng HN">
                                            </td>
                                            <td>
                                                @if($agent->is_approved)
                                                    <span class="pill" style="background:rgba(22,163,74,.12);border-color:rgba(22,163,74,.25);color:#166534">Đã duyệt</span>
                                                @else
                                                    <span class="pill" style="background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.25);color:#92400e">Chờ duyệt</span>
                                                    <div class="hint" style="margin-top:6px">Mã: {{ $agent->pair_code ?: '---' }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($isOffline)
                                                    <div style="color:var(--danger);font-weight:700">Offline</div>
                                                    <div class="hint" style="margin-top:6px">Mất kết nối > 10 phút</div>
                                                @elseif($agent->last_error)
                                                    <div style="color:var(--danger);font-weight:700">Lỗi</div>
                                                    <div class="hint" style="margin-top:6px;word-break:break-word">{{ $agent->last_error }}</div>
                                                @elseif($agent->last_report_at)
                                                    <div style="color:var(--success);font-weight:700">OK</div>
                                                    <div class="hint" style="margin-top:6px">Lần cuối: {{ $agent->last_report_at->format('d/m/Y H:i') }}</div>
                                                @else
                                                    <div class="hint" style="margin-top:0">Đang chờ task...</div>
                                                @endif
                                                @if($agent->last_task_url)
                                                    <div class="hint" style="margin-top:6px;word-break:break-word">
                                                        <a href="{{ $agent->last_task_url }}" target="_blank">Task URL</a>
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                @if($agent->last_seen_at)
                                                    <div>{{ $agent->last_seen_at->format('d/m/Y H:i') }}</div>
                                                    <div class="hint" style="font-size:11px">{{ $agent->last_seen_at->diffForHumans() }}</div>
                                                @else
                                                    <span class="hint" style="margin-top:0">---</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="checkbox" name="is_enabled" value="1" @checked($agent->is_enabled)>
                                            </td>
                                            <td>
                                                <select class="input" name="mode" style="padding:4px 8px">
                                                    <option value="all" @selected($agent->mode === 'all')>Tất cả</option>
                                                    <option value="user" @selected($agent->mode === 'user')>1 user</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="input" name="assigned_user_id" style="padding:4px 8px;max-width:120px">
                                                    <option value="">--</option>
                                                    @foreach($users as $u)
                                                        <option value="{{ $u->id }}" @selected((int) $agent->assigned_user_id === (int) $u->id)>
                                                            {{ $u->email }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:flex-end">
                                                    <button class="btn btn-secondary" type="submit" form="form-agent-{{ $agent->id }}" style="padding:6px 10px">Lưu</button>
                                        </form>
                                                @if(! $agent->is_approved)
                                                    <form method="POST" action="{{ route('shopee.admin-settings.agent.approve', $agent) }}" onsubmit="return confirm('Duyệt agent này để bắt đầu cập nhật giá?')">
                                                        @csrf
                                                        <button class="btn" type="submit" style="padding:6px 10px">Duyệt</button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('shopee.admin-settings.agent.destroy', $agent) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xoá agent này?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-danger" type="submit" style="padding:6px 10px;background:var(--danger)">Xoá</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="hint">Chưa có agent nào. Cài extension và mở Chrome để agent tự đăng ký.</td>
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
