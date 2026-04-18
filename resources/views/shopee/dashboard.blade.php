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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + $shops->count() }}" class="hint">Chưa có dữ liệu. Hãy vào Cài đặt để thêm shop và sản phẩm.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
