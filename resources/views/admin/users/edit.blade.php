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
                            <div class="field" style="margin-top:12px">
                                <label class="label" for="product_limit">Giới hạn sản phẩm so sánh</label>
                                <input class="input" id="product_limit" name="product_limit" type="number" min="1" max="1000000" value="{{ old('product_limit', (int) ($user->product_limit ?? 100)) }}">
                                @error('product_limit')<div class="error">{{ $message }}</div>@enderror
                            </div>
                            <div class="hint" style="margin-top:10px">
                                {{ $user->serviceRemainingText() ?: '---' }}
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
            const noteCard = document.getElementById('noteCard');

            function sync() {
                if (!role || !parentField) return;
                parentField.style.display = role.value === 'viewer' ? '' : 'none';
                const showForShop = role.value === 'owner';
                if (serviceCard) serviceCard.style.display = showForShop ? '' : 'none';
                if (noteCard) noteCard.style.display = showForShop ? '' : 'none';
            }

            if (role) role.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection
