@extends('layouts.app')

@section('content')
    <div class="centered">
        <div class="card">
        <div class="card-header">
            <h1 class="card-title">Đăng nhập</h1>
            <p class="card-sub">Truy cập hệ thống CheckGia</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                    @error('email')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="password">Mật khẩu</label>
                    <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
                    @error('password')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Đăng nhập</button>
                    <label style="display:flex;align-items:center;gap:8px;color:var(--muted);font-size:14px">
                        <input id="remember" name="remember" type="checkbox" value="1">
                        Ghi nhớ đăng nhập
                    </label>
                </div>
            </form>

            <div style="margin-top:12px">
                <a href="{{ route('password.request') }}">Quên mật khẩu?</a>
            </div>
        </div>
    </div>
    </div>
@endsection
