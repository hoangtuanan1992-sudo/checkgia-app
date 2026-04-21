@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:1500px">
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
                function triggerExtensionPoll() {
                    window.postMessage({ type: 'CHECKGIA_TRIGGER_POLL' }, '*');
                }

                document.getElementById('add-product-form').addEventListener('submit', function() {
                    // Trigger extension poll after a short delay (after form submission starts)
                    setTimeout(triggerExtensionPoll, 500);
                });
                
                // Also trigger on page load to catch any pending tasks immediately
                window.addEventListener('load', function() {
                    triggerExtensionPoll();
                });
            </script>

            <div style="height:14px"></div>

            <style>
                .table thead th.header-blue {
                    background: #007bff !important;
                    color: white !important;
                    text-align: center;
                    padding: 10px 8px;
                    border: none;
                    font-size: 13px;
                }
                .icon-box {
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    padding: 3px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    background: white;
                    width: 24px;
                    height: 24px;
                    color: #007bff;
                }
                .price-val {
                    font-size: 16px;
                    font-weight: 800;
                    color: #111827;
                }
                .diff-val {
                    font-weight: 700;
                    font-size: 13px;
                }
                .link-text {
                    color: #007bff;
                    text-decoration: none;
                    font-weight: 500;
                    font-size: 13px;
                }
                .table td {
                    font-size: 13px;
                    padding: 10px 8px;
                }
            </style>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:52px">#</th>
                            <th style="min-width:340px">Tên sản phẩm</th>
                            <th class="header-blue" style="min-width:160px">Giá của bạn</th>
                            @foreach($shops as $shop)
                                <th class="header-blue" style="min-width:180px">{{ $shop->name }}</th>
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
                                    <div style="font-weight:800;font-size:13px">{{ $product->name ?: 'Shopee product #' . $product->id }}</div>
                                    <div class="hint" style="margin-top:3px;font-size:11px">ID: {{ $product->id }}</div>
                                </td>
                                <td>
                                    @if(is_null($own))
                                        <span class="hint" style="margin-top:0">---</span>
                                    @else
                                        <div style="display:flex;flex-direction:column;gap:6px">
                                            <div style="display:flex;align-items:center;gap:6px">
                                                <a href="{{ $product->own_url }}" target="_blank" class="icon-box">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                </a>
                                                <a href="{{ $product->own_url }}" target="_blank" class="link-text">link sản phẩm</a>
                                            </div>
                                            <div class="price-val">{{ number_format($own, 0, ',', '.') }}đ</div>
                                        </div>
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
                                            <div style="display:flex;flex-direction:column;gap:6px">
                                                <div style="display:flex;align-items:center;gap:10px">
                                                    <div class="diff-val" style="color:{{ $diff > 0 ? 'var(--success)' : ($diff < 0 ? 'var(--danger)' : '#6b7280') }}">
                                                        {{ $diff > 0 ? '+' : '' }}{{ number_format((int) $diff, 0, ',', '.') }}đ
                                                    </div>
                                                    <a href="{{ route('shopee.settings') }}" class="icon-box" style="color:#6b7280;width:20px;height:20px">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                                                    </a>
                                                </div>
                                                <div style="display:flex;align-items:center;gap:6px">
                                                    <a href="{{ $c->url }}" target="_blank" class="icon-box">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                    </a>
                                                    <a href="{{ $c->url }}" target="_blank" class="link-text" style="font-size:16px;font-weight:600">
                                                        {{ number_format((int) $cPrice, 0, ',', '.') }}đ
                                                    </a>
                                                </div>
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
