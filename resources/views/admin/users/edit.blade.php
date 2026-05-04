@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1100px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Sửa người dùng</h1>
                    <p class="card-sub">ID: {{ $user->id }}</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                    <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Quay lại</a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.users.update', $user) }}">
                    @csrf
                    @method('PUT')

                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                        <div class="field" style="margin-top:0">
                            <label class="label" for="name">Tên</label>
                            <input class="input" id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required>
                            @error('name')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field" style="margin-top:0">
                            <label class="label" for="email">Email</label>
                            <input class="input" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px">
                        <div class="field" style="margin-top:0">
                            <label class="label" for="role">Role</label>
                            <select class="input" id="role" name="role">
                                @foreach(['owner' => 'owner', 'viewer' => 'viewer', 'admin' => 'admin'] as $v => $label)
                                    <option value="{{ $v }}" @selected(old('role', $user->role) === $v)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field" style="margin-top:0" id="parentField">
                            <label class="label" for="parent_user_id">Tài khoản chính</label>
                            <select class="input" id="parent_user_id" name="parent_user_id">
                                <option value="">-- Chọn --</option>
                                @foreach($owners as $o)
                                    <option value="{{ $o->id }}" @selected((string) old('parent_user_id', $user->parent_user_id) === (string) $o->id)>{{ $o->name }} ({{ $o->email }})</option>
                                @endforeach
                            </select>
                            @error('parent_user_id')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field" style="margin-top:0">
                            <label class="label" for="password">Đặt lại mật khẩu (tuỳ chọn)</label>
                            <input class="input" id="password" name="password" type="password" autocomplete="new-password">
                            @error('password')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px" id="serviceCard">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Thời gian sử dụng</h2>
                            <p class="card-sub">Chọn ngày bắt đầu và ngày hết hạn</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="service_start_date">Từ ngày</label>
                                    <input class="input" id="service_start_date" name="service_start_date" type="date" value="{{ old('service_start_date', optional($user->service_start_date)->format('Y-m-d')) }}">
                                    @error('service_start_date')<div class="error">{{ $message }}</div>@enderror
                                </div>
                                <div class="field" style="margin-top:0">
                                    <label class="label" for="service_end_date">Đến ngày</label>
                                    <input class="input" id="service_end_date" name="service_end_date" type="date" value="{{ old('service_end_date', optional($user->service_end_date)->format('Y-m-d')) }}">
                                    @error('service_end_date')<div class="error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="hint" style="margin-top:10px">
                                {{ $user->serviceRemainingText() ?: '---' }}
                            </div>
                        </div>
                    </div>

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px" id="updateIntervalCard">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Thời gian cập nhật</h2>
                            <p class="card-sub">Nhập các giờ trong ngày để hệ thống cập nhật giá cho tài khoản này</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div class="field" style="margin-top:0;max-width:360px">
                                <label class="label" for="scrape_schedule_times">Giờ cập nhật mỗi ngày</label>
                                <input class="input" id="scrape_schedule_times" name="scrape_schedule_times" type="text" value="{{ old('scrape_schedule_times', $userScrapeSetting->scrape_schedule_times ?? '') }}" placeholder="VD: 5 10 20">
                                @error('scrape_schedule_times')<div class="error">{{ $message }}</div>@enderror
                                <div class="hint">Để trống thì cập nhật 10 phút/lần. VD: nhập 5 10 20 thì mỗi ngày cập nhật lúc 05:00, 10:00 và 20:00.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px" id="noteCard">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Ghi chú</h2>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <div class="field" style="margin-top:0">
                                <label class="label" for="admin_note">Note</label>
                                <textarea class="input" id="admin_note" name="admin_note" rows="5" placeholder="Ghi chú cho shop...">{{ old('admin_note', $user->admin_note) }}</textarea>
                                @error('admin_note')<div class="error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px" id="permissionCard">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Quyền chức năng</h2>
                            <p class="card-sub">Bật chức năng riêng cho shop này</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <label style="display:flex;align-items:center;gap:10px;font-weight:700;margin-bottom:12px">
                                <input type="hidden" name="allow_compare_match" value="0">
                                <input type="checkbox" name="allow_compare_match" value="1" @checked(old('allow_compare_match', (bool) $user->allow_compare_match)) style="width:20px;height:20px">
                                Hiện nút So khớp ở bảng Kết quả so sánh
                            </label>
                            @error('allow_compare_match')<div class="error">{{ $message }}</div>@enderror

                            <label style="display:flex;align-items:center;gap:10px;font-weight:700">
                                <input type="hidden" name="allow_shopee_check" value="0">
                                <input type="checkbox" name="allow_shopee_check" value="1" @checked(old('allow_shopee_check', (bool) $user->allow_shopee_check)) style="width:20px;height:20px">
                                Hiện nút Check Giá Shopee
                            </label>
                            @error('allow_shopee_check')<div class="error">{{ $message }}</div>@enderror
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
        (function () {
            const role = document.getElementById('role');
            const parentField = document.getElementById('parentField');
            const serviceCard = document.getElementById('serviceCard');
            const updateIntervalCard = document.getElementById('updateIntervalCard');
            const noteCard = document.getElementById('noteCard');
            const permissionCard = document.getElementById('permissionCard');

            function sync() {
                if (!role || !parentField) return;
                parentField.style.display = role.value === 'viewer' ? '' : 'none';
                const showForShop = role.value === 'owner';
                if (serviceCard) serviceCard.style.display = showForShop ? '' : 'none';
                if (updateIntervalCard) updateIntervalCard.style.display = showForShop ? '' : 'none';
                if (noteCard) noteCard.style.display = showForShop ? '' : 'none';
                if (permissionCard) permissionCard.style.display = showForShop ? '' : 'none';
            }

            if (role) role.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
