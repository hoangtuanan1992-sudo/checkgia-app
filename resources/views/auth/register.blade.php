@extends('layouts.app')

@section('content')
    <div class="centered">
        <div class="card">
        <div class="card-header">
            <h1 class="card-title">Tạo tài khoản</h1>
            <p class="card-sub">Bắt đầu sử dụng hệ thống CheckGia</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div class="field">
                    <label class="label" for="name">Tên</label>
                    <input class="input" id="name" name="name" type="text" value="{{ old('name') }}" required autocomplete="name">
                    @error('name')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
                    @error('email')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="password">Mật khẩu</label>
                    <input class="input" id="password" name="password" type="password" required autocomplete="new-password">
                    @error('password')
                        <div class="error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="field">
                    <label class="label" for="password_confirmation">Nhập lại mật khẩu</label>
                    <input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Tạo tài khoản</button>
                </div>
            </form>

            <div class="hint">
                Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a>.
            </div>
        </div>
    </div>
    </div>
@endsection
