@extends('layouts.app')

@section('content')
    <div class="centered">
        <div class="card">
            <div class="card-header">
                <h1 class="card-title">Quên mật khẩu</h1>
                <p class="card-sub">Nhập email đã đăng ký. Hệ thống sẽ gửi mật khẩu tạm thời cho bạn.</p>
            </div>
            <div class="card-body">
                @if (session('status'))
                    <div class="status">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="field">
                        <label class="label" for="email">Email</label>
                        <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required>
                        @error('email')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="actions">
                        <button class="btn" type="submit">Gửi mật khẩu</button>
                        <a class="btn btn-secondary" href="{{ route('login') }}">Quay lại đăng nhập</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

