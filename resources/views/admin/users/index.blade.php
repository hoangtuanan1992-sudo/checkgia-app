@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1500px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Quản trị người dùng</h1>
                    <p class="card-sub">Chọn tài khoản để xem/sửa</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                    <a class="btn" href="{{ route('admin.users.create') }}">Tạo tài khoản</a>
                    <a class="btn btn-secondary" href="{{ route('admin.settings.edit') }}">Cài đặt tổng</a>
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                </div>
            </div>
            <div class="card-body">
                @if($errors->has('user'))
                    <div class="error">{{ $errors->first('user') }}</div>
                @endif

                <form method="GET" action="{{ route('admin.users.index') }}" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
                    <div class="field" style="margin-top:0;flex:1;min-width:240px">
                        <label class="label" for="q">Tìm kiếm</label>
                        <input class="input" id="q" name="q" type="text" value="{{ $q }}" placeholder="Tên / Email / ID">
                    </div>
                    <div class="field" style="margin-top:0;min-width:220px">
                        <label class="label" for="service">Thời gian còn lại</label>
                        <select class="input" id="service" name="service">
                            <option value="" @selected($service === '')>Tất cả</option>
                            <option value="active" @selected($service === 'active')>Đang hoạt động</option>
                            <option value="exp_7" @selected($service === 'exp_7')>Còn ≤ 7 ngày</option>
                            <option value="exp_30" @selected($service === 'exp_30')>Còn ≤ 30 ngày</option>
                            <option value="not_started" @selected($service === 'not_started')>Chưa bắt đầu</option>
                            <option value="expired" @selected($service === 'expired')>Đã hết hạn</option>
                            <option value="no_plan" @selected($service === 'no_plan')>Chưa đặt thời hạn</option>
                        </select>
                    </div>
                    <div class="field" style="margin-top:0;min-width:180px">
                        <label class="label" for="created_from">Đăng ký từ</label>
                        <input class="input" id="created_from" name="created_from" type="date" value="{{ $createdFrom }}">
                    </div>
                    <div class="field" style="margin-top:0;min-width:180px">
                        <label class="label" for="created_to">Đăng ký đến</label>
                        <input class="input" id="created_to" name="created_to" type="date" value="{{ $createdTo }}">
                    </div>
                    <div class="actions" style="margin-top:0">
                        <button class="btn" type="submit">Tìm</button>
                        <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Reset</a>
                    </div>
                </form>

                <div class="table-wrap" style="margin-top:14px">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:70px">ID</th>
                                <th style="min-width:220px">Shop</th>
                                <th style="min-width:260px">Email</th>
                                <th style="min-width:220px">Thời hạn</th>
                                <th style="min-width:170px">Còn lại</th>
                                <th style="width:220px"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $u)
                                <tr>
                                    <td>{{ $u->id }}</td>
                                    <td style="font-weight:600">{{ $u->name }}</td>
                                    <td>{{ $u->email }}</td>
                                    <td>
                                        @if($u->service_start_date || $u->service_end_date)
                                            {{ $u->service_start_date?->format('d/m/Y') }} - {{ $u->service_end_date?->format('d/m/Y') }}
                                        @else
                                            <span class="hint" style="margin-top:0">---</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($u->service_start_date && $u->service_end_date)
                                            {{ $u->serviceRemainingText() }}
                                        @else
                                            <span class="hint" style="margin-top:0">---</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right">
                                        <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:nowrap;white-space:nowrap">
                                            <a class="btn btn-secondary" href="{{ route('admin.users.edit', $u) }}">Sửa</a>
                                            <form method="POST" action="{{ route('admin.impersonate', $u) }}">
                                                @csrf
                                                <button class="btn" type="submit">Xem</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" onsubmit="return confirm('Xoá tài khoản này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn" type="submit">Xoá</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
