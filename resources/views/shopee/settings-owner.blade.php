@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:1100px">
        <div class="card-header">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="min-width:0">
                    <h1 class="card-title" style="margin:0">Cài đặt Shopee</h1>
                    <p class="card-sub" style="margin:6px 0 0">Thêm shop đối thủ và nhập link để so sánh</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    <a class="btn btn-secondary" href="{{ route('shopee.dashboard') }}">Dashboard</a>
                    @if(auth()->user()?->isAdmin())
                        <a class="btn btn-secondary" href="{{ route('shopee.admin-settings') }}">Admin</a>
                    @endif
                </div>
            </div>
        </div>
        <div class="card-body">
            @if(session('status'))
                <div class="pill" style="margin-bottom:14px">{{ session('status') }}</div>
            @endif

            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                <div class="card-header">
                    <h2 class="card-title" style="font-size:18px;margin:0">Shops</h2>
                    <p class="card-sub" style="margin-top:6px">Danh sách shop đối thủ để tạo cột so sánh</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shopee.shops.store') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
                        @csrf
                        <div style="flex:1 1 320px">
                            <div class="hint" style="margin-top:0">Tên shop</div>
                            <input class="input" name="name" placeholder="VD: LaptopGame" required>
                        </div>
                        <button class="btn" type="submit">Thêm</button>
                    </form>

                    <div style="height:12px"></div>

                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width:60px">#</th>
                                    <th>Shop</th>
                                    <th style="width:120px">Thứ tự</th>
                                    <th style="width:190px">Hành động</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($shops as $i => $shop)
                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td style="font-weight:800">{{ $shop->name }}</td>
                                        <td>
                                            <div style="display:flex;gap:6px;align-items:center">
                                                <form method="POST" action="{{ route('shopee.shops.move', $shop) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="up">
                                                    <button class="btn btn-secondary" type="submit" style="padding:6px 10px">↑</button>
                                                </form>
                                                <form method="POST" action="{{ route('shopee.shops.move', $shop) }}">
                                                    @csrf
                                                    <input type="hidden" name="direction" value="down">
                                                    <button class="btn btn-secondary" type="submit" style="padding:6px 10px">↓</button>
                                                </form>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display:flex;gap:8px;align-items:center">
                                                <form method="POST" action="{{ route('shopee.shops.destroy', $shop) }}" onsubmit="return confirm('Xoá shop này?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-secondary" type="submit" style="padding:6px 10px;color:var(--danger)">Xoá</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="hint">Chưa có shop nào.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div style="height:14px"></div>

            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                <div class="card-header">
                    <h2 class="card-title" style="font-size:18px;margin:0">Thêm sản phẩm Shopee</h2>
                    <p class="card-sub" style="margin-top:6px">Chỉ cần nhập link shop bạn, và nhập thêm link của đối thủ (nếu có) để so sánh</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shopee.products.store') }}">
                        @csrf
                        <div style="display:grid;grid-template-columns:1fr 1.7fr;gap:12px">
                            <div class="field" style="margin-top:0">
                                <label class="label">Tên (tuỳ chọn)</label>
                                <input class="input" name="name" placeholder="VD: ASUS ProArt PA278QV" value="{{ old('name') }}">
                            </div>
                            <div class="field" style="margin-top:0">
                                <label class="label">Link shop bạn (Shopee)</label>
                                <input class="input" name="own_url" type="url" placeholder="https://shopee.vn/..." value="{{ old('own_url') }}" required>
                            </div>
                        </div>

                        @if($shops->isNotEmpty())
                            <div style="margin-top:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                                @foreach($shops as $shop)
                                    <div class="field" style="margin-top:0">
                                        <label class="label">Link {{ $shop->name }}</label>
                                        <input class="input" name="competitor_urls[{{ $shop->id }}]" type="url" value="{{ old('competitor_urls.'.$shop->id) }}" placeholder="https://shopee.vn/...">
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="hint" style="margin-top:10px">Chưa có shop đối thủ. Hãy thêm shop để tạo cột so sánh.</div>
                        @endif

                        <div class="actions">
                            <button class="btn" type="submit">Thêm</button>
                        </div>
                    </form>
                </div>
            </div>

            <div style="height:14px"></div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Sản phẩm</th>
                            <th style="width:160px">Bật</th>
                            <th style="width:160px">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $i => $p)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <div style="font-weight:800">{{ $p->name ?: 'Shopee product #' . $p->id }}</div>
                                    <div class="hint" style="margin-top:3px;word-break:break-word">
                                        <a href="{{ $p->own_url }}" target="_blank">{{ $p->own_url }}</a>
                                    </div>
                                </td>
                                <td>
                                    @if($p->is_enabled)
                                        <span class="pill" style="background:rgba(22,163,74,.12);border-color:rgba(22,163,74,.25);color:#166534">Đang bật</span>
                                    @else
                                        <span class="pill" style="background:rgba(220,53,69,.10);border-color:rgba(220,53,69,.20);color:#991b1b">Đã tắt</span>
                                    @endif
                                </td>
                                <td style="display:flex;gap:8px;align-items:center">
                                    <form method="POST" action="{{ route('shopee.products.toggle', $p) }}">
                                        @csrf
                                        <button class="btn btn-secondary" type="submit" style="padding:6px 10px">
                                            {{ $p->is_enabled ? 'Tắt' : 'Bật' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('shopee.products.destroy', $p) }}" onsubmit="return confirm('Xoá sản phẩm này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-secondary" type="submit" style="padding:6px 10px;color:var(--danger)">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="hint">Chưa có sản phẩm nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
