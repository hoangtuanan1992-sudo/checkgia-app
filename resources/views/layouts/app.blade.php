<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        :root{--bg:#f5f7fb;--card:#ffffff;--muted:#6b7280;--text:#111827;--border:#e5e7eb;--accent:#0d6efd;--accent-hover:#0b5ed7;--danger:#dc3545;--success:#16a34a;--table-head:#0d6efd}
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
        a{color:var(--accent);text-decoration:none}
        a:hover{color:var(--accent-hover)}
        nav{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 18px;background:rgba(255,255,255,.85);border-bottom:1px solid var(--border);backdrop-filter:saturate(180%) blur(10px);position:sticky;top:0;z-index:100}
        .brand{font-weight:700;letter-spacing:.4px}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:10px;border:1px solid rgba(13,110,253,.25);background:var(--accent);color:#fff;cursor:pointer}
        .btn:hover{background:var(--accent-hover);border-color:rgba(13,110,253,.35)}
        .btn-secondary{background:#fff;color:var(--text);border:1px solid var(--border)}
        .btn-secondary:hover{background:#f3f4f6}
        .container{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-start;padding:28px;gap:14px;width:100%}
        .card{width:100%;max-width:520px;background:var(--card);border:1px solid var(--border);border-radius:16px;box-shadow:0 10px 22px rgba(17,24,39,.06)}
        .card-header{padding:24px 24px 8px}
        .card-title{margin:0;font-size:22px;font-weight:700}
        .card-sub{margin:6px 0 0;color:var(--muted);font-size:14px}
        .card-body{padding:8px 24px 24px}
        .field{display:flex;flex-direction:column;gap:6px;margin-top:14px}
        .label{font-size:14px;color:var(--muted)}
        .input{padding:12px 14px;border-radius:12px;border:1px solid var(--border);background:#fff;color:var(--text);outline:none}
        .input:focus{border-color:rgba(13,110,253,.55);box-shadow:0 0 0 3px rgba(13,110,253,.12)}
        .error{margin-top:6px;color:var(--danger);font-size:13px}
        .actions{margin-top:18px;display:flex;gap:10px;align-items:center}
        .hint{margin-top:12px;color:var(--muted);font-size:13px}
        .status{margin-bottom:12px;padding:10px 12px;border:1px solid rgba(22,163,74,.25);background:rgba(22,163,74,.08);border-radius:10px;font-size:14px}
        .centered{margin:auto}
        .toast{position:fixed;right:18px;top:76px;z-index:80;max-width:min(420px,calc(100% - 36px));padding:12px 14px;border-radius:12px;background:#111827;color:#fff;box-shadow:0 16px 30px rgba(17,24,39,.22);opacity:0;transform:translateY(-8px);transition:opacity .18s ease, transform .18s ease;pointer-events:none}
        .toast.show{opacity:1;transform:translateY(0);pointer-events:auto}
        .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:10px;border:1px solid var(--border);background:#fff;color:var(--accent);cursor:pointer}
        .icon-btn:hover{background:#f3f4f6}
        .icon-btn-sm{width:28px;height:28px;border-radius:9px}
        .icon-btn-sm svg{width:14px;height:14px}
        .dialog{border:none;border-radius:14px;padding:0;max-width:520px;width:calc(100% - 24px);box-shadow:0 20px 45px rgba(17,24,39,.18)}
        .dialog::backdrop{background:rgba(17,24,39,.38)}
        .dialog-header{padding:16px 16px 0}
        .dialog-body{padding:12px 16px 16px}
        .table-wrap{width:100%;overflow-x:auto;overflow-y:visible}
        .table{width:100%;border-collapse:separate;border-spacing:0}
        .table thead th{background:var(--table-head);color:#fff;padding:10px 12px;font-weight:600;font-size:13px;border-right:1px solid rgba(255,255,255,.22);white-space:nowrap}
        .table thead th:last-child{border-right:none}
        .table tbody td{background:var(--card);border-bottom:1px solid var(--border);padding:12px}
        .table tbody tr:first-child td{border-top:1px solid var(--border)}
        .table tbody td:first-child{border-left:1px solid var(--border)}
        .table tbody td:last-child{border-right:1px solid var(--border)}
        .pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:#eef2ff;border:1px solid #e0e7ff;color:#3730a3;font-size:12px}
    </style>
</head>
<body>
    <nav>
        <a class="brand" href="{{ route('home') }}">{{ config('app.name') }}</a>
        @auth
            <div style="display:flex;gap:8px;align-items:center">
                <a class="btn btn-secondary" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="btn btn-secondary" href="{{ route('dashboard.reports') }}">Báo cáo</a>
                <a class="btn btn-secondary" href="{{ route('dashboard.competitors') }}">Cài đặt</a>
                <a class="btn btn-secondary" href="{{ route('account') }}">Tài khoản</a>
                @if(auth()->user()->isAdmin())
                    <a class="btn btn-secondary" href="{{ route('admin.users.index') }}">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button class="btn" type="submit">Đăng xuất</button>
            </form>
            </div>
        @endauth

        @guest
            <div style="display:flex;gap:8px">
                <a class="btn btn-secondary" href="{{ route('login') }}">Đăng nhập</a>
            </div>
        @endguest
    </nav>

    <div id="toast" class="toast" style="display:none"></div>

    <main class="container">
        @if(auth()->check() && auth()->user()->isAdmin() && session('impersonate_user_id'))
            @php($impUser = \App\Models\User::query()->find((int) session('impersonate_user_id')))
            @if($impUser)
                <div class="pill" style="position:fixed;left:18px;top:72px;z-index:60">
                    Đang xem: {{ $impUser->email }}
                    <a href="{{ route('admin.impersonate.stop.get') }}" style="color:#3730a3">thoát</a>
                </div>
            @endif
        @endif
        @yield('content')
    </main>

    <script>
        (function () {
            const msg = @json(session('status'));
            if (!msg) return;
            const toast = document.getElementById('toast');
            if (!toast) return;
            toast.textContent = msg;
            toast.style.display = '';
            requestAnimationFrame(() => toast.classList.add('show'));
            const hide = () => {
                toast.classList.remove('show');
                setTimeout(() => {
                    toast.style.display = 'none';
                }, 200);
            };
            toast.addEventListener('click', hide);
            setTimeout(hide, 2000);
        })();
    </script>

</body>
</html>
