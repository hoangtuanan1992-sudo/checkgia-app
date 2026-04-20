@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:1100px">
        <div class="card-header">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="min-width:0">
                    <h1 class="card-title" style="margin:0">Check Giá Shopee</h1>
                    <p class="card-sub" style="margin:6px 0 0">Giá được cập nhật bởi Chrome extension</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    @if(auth()->user())
                        <a class="btn btn-secondary" href="{{ route('shopee.settings') }}">Cài đặt</a>
                    @endif
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
                    <h2 class="card-title" style="font-size:18px;margin:0">Thêm sản phẩm Shopee</h2>
                    <p class="card-sub" style="margin-top:6px">Nhập link shop bạn, và nhập thêm link của đối thủ (nếu có) để so sánh</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shopee.products.store') }}" id="add-product-form">
                        @csrf
                        <div class="field" style="margin-top:0">
                            <label class="label">Link shop bạn (Shopee)</label>
                            <input class="input" name="own_url" type="url" placeholder="https://shopee.vn/..." value="{{ old('own_url') }}" required>
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
                            <div class="hint" style="margin-top:10px">Chưa có shop đối thủ. Hãy vào Cài đặt để thêm shop đối thủ.</div>
                        @endif

                        <div class="actions">
                            <button class="btn" type="submit">Thêm</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                document.getElementById('add-product-form').addEventListener('submit', function() {
                    // Trigger extension poll after a short delay (after form submission starts)
                    setTimeout(function() {
                        window.dispatchEvent(new CustomEvent('checkgia:trigger_poll'));
                    }, 500);
                });
                
                // Also trigger on page load to catch any pending tasks immediately
                window.addEventListener('load', function() {
                    window.dispatchEvent(new CustomEvent('checkgia:trigger_poll'));
                });
            </script>

            <div style="height:14px"></div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:52px">#</th>
                            <th style="min-width:340px">Tên sản phẩm</th>
                            <th style="min-width:160px">Giá bạn</th>
                            @foreach($shops as $shop)
                                <th style="min-width:180px">{{ $shop->name }}</th>
                            @endforeach
                            <th style="width:80px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $i => $product)
                            @php($own = is_null($product->last_price) ? null : (int) $product->last_price)
                            @php($map = $product->competitors->keyBy('shopee_shop_id'))
                            <tr>
                                <td style="font-weight:800">{{ $i + 1 }}</td>
                                <td>
                                    <div style="font-weight:800">{{ $product->name ?: 'Shopee product #' . $product->id }}</div>
                                    <div class="hint" style="margin-top:3px">ID: {{ $product->id }}</div>
                                    <div class="hint" style="margin-top:3px;word-break:break-word">
                                        <a href="{{ $product->own_url }}" target="_blank">link shop bạn</a>
                                    </div>
                                </td>
                                <td style="font-weight:900">
                                    @if(is_null($own))
                                        <span class="hint" style="margin-top:0">---</span>
                                    @else
                                        {{ number_format($own, 0, ',', '.') }}đ
                                    @endif
                                </td>
                                @foreach($shops as $shop)
                                    @php($c = $map->get($shop->id))
                                    @php($cPrice = $c?->last_price)
                                    @php($adj = (int) ($c?->price_adjustment ?? 0))
                                    @php($diff = (! is_null($own) && ! is_null($cPrice)) ? ((int) $cPrice + $adj - (int) $own) : null)
                                    <td>
                                        @if(! $c || is_null($cPrice) || is_null($own))
                                            <span class="hint" style="margin-top:0">---</span>
                                        @else
                                            <div style="display:flex;justify-content:space-between;align-items:center;gap:10px">
                                                <div style="display:flex;flex-direction:column;gap:2px">
                                                    <a href="{{ $c->url }}" target="_blank" style="color:#6b7280;font-weight:700">
                                                        {{ number_format((int) $cPrice, 0, ',', '.') }}đ
                                                    </a>
                                                    <a href="{{ $c->url }}" target="_blank" style="font-weight:900;color:{{ $diff > 0 ? 'var(--success)' : ($diff < 0 ? 'var(--danger)' : '#6b7280') }}">
                                                        {{ $diff > 0 ? '+' : '' }}{{ number_format((int) $diff, 0, ',', '.') }}đ
                                                    </a>
                                                </div>
                                                <a class="btn btn-secondary" href="{{ route('shopee.settings') }}" style="padding:6px 10px">Sửa</a>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('shopee.products.destroy', $product) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xoá sản phẩm này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn" type="submit" style="padding:6px 10px;background:var(--danger);border-color:rgba(220,53,69,.2)">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 4 + $shops->count() }}" class="hint">Chưa có dữ liệu. Hãy thêm sản phẩm ở phía trên và thêm shop đối thủ trong Cài đặt.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
