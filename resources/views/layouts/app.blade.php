<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('brand-logo.jpg') }}" type="image/jpeg">
    <link rel="apple-touch-icon" href="{{ asset('brand-logo.jpg') }}">
    <style>
        :root{--bg:#f5f7fb;--card:#ffffff;--muted:#6b7280;--text:#111827;--border:#e5e7eb;--accent:#0d6efd;--accent-hover:#0b5ed7;--danger:#dc3545;--success:#16a34a;--table-head:#0d6efd}
        *{box-sizing:border-box}
        body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;flex-direction:column}
        a{color:var(--accent);text-decoration:none}
        a:hover{color:var(--accent-hover)}
        nav{display:flex;align-items:center;justify-content:space-between;gap:0px;padding:0px 18px;background:rgba(255,255,255,.85);border-bottom:1px solid var(--border);backdrop-filter:saturate(180%) blur(10px);/* position: sticky; */top:0;z-index:100}
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
        .table-wrap{overflow-x:auto !important;overflow-y:auto !important;max-height:93vh}
        .table{width:100%;border-collapse:separate;border-spacing:0}
        .table thead th{position:sticky !important;top:0 !important;z-index:999 !important;background-color:#007bff !important;color:#ffffff !important;box-shadow:0 2px 4px rgba(0,0,0,0.1);padding:10px 12px;font-weight:600;font-size:13px;border-right:1px solid rgba(255,255,255,.22);white-space:nowrap}
        .table thead th:last-child{border-right:none}
        .table tbody td{background:var(--card);border-bottom:1px solid var(--border);padding:12px}
        .table tbody tr:first-child td{border-top:1px solid var(--border)}
        .table tbody td:first-child{border-left:1px solid var(--border)}
        .table tbody td:last-child{border-right:1px solid var(--border)}
        .pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;background:#eef2ff;border:1px solid #e0e7ff;color:#3730a3;font-size:12px}
        .compare-card{position:relative;background:rgba(255,255,255,.88);backdrop-filter:saturate(180%) blur(12px);border:1px solid rgba(17,24,39,.18)}
        .compare-card-header{padding:16px 18px 10px}
        .compare-card-title{font-weight:800;font-size:18px;line-height:1.2}
        .compare-card-title-full{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:97%;display:block}
        .compare-card-title-mobile{display:none}
        .compare-card-delete{position:absolute;top:10px;right:10px;width:44px;height:44px;border-radius:14px;border:1px solid rgba(220,53,69,.18);background:rgba(220,53,69,.12);color:#b42318;display:inline-flex;align-items:center;justify-content:center;cursor:pointer}
        .compare-card-delete:hover{background:rgba(220,53,69,.16)}
        .compare-card-own-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 18px;/* background: linear-gradient(90deg, rgba(13, 110, 253, .12), rgba(13, 110, 253, .03)); */}
        .compare-card-own-label{color:var(--muted);font-size:13px}
        .compare-card-own-price{font-weight:900;font-size:22px;color:var(--accent)}
        .compare-card-table-head{display:grid;grid-template-columns:1.15fr 0.9fr 0.9fr 88px;gap:10px;padding:10px 18px;background:rgba(17,24,39,.03);color:var(--muted);font-size:12px;font-weight:700;border-top:1px solid var(--border);border-bottom:1px solid var(--border)}
        .compare-card-table-row{display:grid;grid-template-columns:1.15fr 0.9fr 0.9fr 88px;gap:10px;align-items:center;padding:12px 18px;border-bottom:1px solid var(--border)}
        .compare-card-cell-site{font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .compare-card-cell-price{text-align:right}
        .compare-card-cell-diff{text-align:right}
        .compare-card-cell-actions{display:flex;justify-content:flex-end;gap:8px}
        .compare-diff-pill{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:999px;font-weight:900;line-height:1}
        .compare-diff-pos{background:rgba(22,163,74,.14);color:#166534}
        .compare-diff-neg{background:rgba(220,53,69,.14);color:#991b1b}
        .compare-diff-zero{background:rgba(107,114,128,.14);color:#374151}
        .compare-diff-arrow{font-weight:900}
        .compare-card-addlink{width:100%;border:1px dashed rgba(107,114,128,.45);background:transparent;border-radius:14px;padding:12px 14px;font-weight:800;color:#111827;cursor:pointer}
        .compare-card-addlink:hover{background:rgba(17,24,39,.03)}
        .history-page .btn{padding:8px 10px}
        .history-series-grid{grid-template-columns:repeat(3,minmax(0,1fr))}
        .history-series-item span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block}
        @media (max-width: 768px){
            nav{flex-wrap:wrap;justify-content:center;gap:8px;padding:8px 10px}
            nav img{height:44px !important}
            nav > div{flex:1 1 100%;display:flex;flex-wrap:wrap;justify-content:center}
            body{font-size:12px}
            .btn{padding:8px 10px;font-size:12px}
            .container{padding:12px;align-items:stretch}
            .card{max-width:none}
            .card-title{font-size:20px}
            .card-sub{font-size:13px}
            .hint{font-size:12px}
            .card-header{padding:16px 16px 8px}
            .card-body{padding:8px 16px 16px}
            .icon-btn-sm{width:26px;height:26px;border-radius:9px}
            .icon-btn-sm svg{width:13px;height:13px}
            .compare-card-title{font-size:17px}
            .compare-card-title-full{display:none}
            .compare-card-title-mobile{display:block;white-space:normal;overflow:visible}
            .compare-card-header{padding:14px 14px 10px}
            .compare-card-delete{margin-top:71px;width:32px;height:32px;border-radius:14px}
            .compare-card-own-row{padding:10px 14px}
            .compare-card-own-price{font-size:20px}
            .compare-card-table-head{grid-template-columns:1fr 0.9fr 1fr 74px;gap:8px;padding:10px 14px}
            .compare-card-table-row{grid-template-columns:1fr 0.9fr 1fr 74px;gap:8px;padding:12px 14px}
            .compare-card-cell-site{white-space:normal}
            .compare-diff-pill{padding:7px 10px}
            .compare-card-addlink{padding:12px 12px}
            .history-page .card-title{font-size:18px}
            .history-page .card-sub{font-size:12px}
            .history-page .actions{gap:8px;flex-wrap:wrap}
            .history-page .btn{padding:6px 8px;font-size:11px;border-radius:10px}
            .history-page .pill{padding:5px 8px;font-size:11px}
            .history-page .table{font-size:12px}
            .history-page .table tbody td{padding:9px 10px}
            .history-series-grid{grid-template-columns:repeat(2,minmax(0,1fr)) !important;gap:8px}
            .history-series-item{padding:8px 10px !important}
            .history-series-item span{font-size:12px !important}
        }
    </style>
</head>
<body>
    <nav>
        <a class="brand" href="{{ route('home') }}" style="display:flex;align-items:center;gap:10px">
            <img src="https://checkgia.id.vn/brand-logo.jpg" alt="Check Giá" style="height:70px;width:auto;border-radius:6px;display:block">
        </a>
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
