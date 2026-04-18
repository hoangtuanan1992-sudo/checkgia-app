@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:1100px">
        <div class="card-header">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="min-width:0">
                    <h1 class="card-title" style="margin:0">Check Giá Shopee</h1>
                    <p class="card-sub" style="margin:6px 0 0">Danh sách link Shopee của bạn để extension cào giá</p>
                </div>
                @if(auth()->user()?->isAdmin())
                    <a class="btn btn-secondary" href="{{ route('shopee.settings') }}">Cài đặt</a>
                @endif
            </div>
        </div>
        <div class="card-body">
            @if(session('status'))
                <div class="pill" style="margin-bottom:14px">{{ session('status') }}</div>
            @endif

            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                <div class="card-header">
                    <h2 class="card-title" style="font-size:18px;margin:0">Thêm link Shopee</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shopee.items.store') }}" style="display:grid;grid-template-columns:1fr 1.7fr auto;gap:10px;align-items:end">
                        @csrf
                        <div>
                            <div class="hint" style="margin-top:0">Tên (tuỳ chọn)</div>
                            <input class="input" name="name" placeholder="VD: LaptopGame - ProArt PA278QV" value="{{ old('name') }}">
                        </div>
                        <div>
                            <div class="hint" style="margin-top:0">Link Shopee</div>
                            <input class="input" name="url" placeholder="https://shopee.vn/..." value="{{ old('url') }}" required>
                        </div>
                        <button class="btn" type="submit">Thêm</button>
                    </form>
                </div>
            </div>

            <div style="height:14px"></div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:60px">#</th>
                            <th>Link</th>
                            <th style="width:170px">Giá mới nhất</th>
                            <th style="width:190px">Lần cào gần nhất</th>
                            <th style="width:160px">Trạng thái</th>
                            <th style="width:160px">Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $i => $item)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td>
                                    <div style="font-weight:700">{{ $item->name ?: 'Shopee item #' . $item->id }}</div>
                                    <div class="hint" style="margin-top:3px;word-break:break-word">
                                        <a href="{{ $item->url }}" target="_blank">{{ $item->url }}</a>
                                    </div>
                                </td>
                                <td style="font-weight:800">
                                    @if(is_null($item->last_price))
                                        <span class="hint" style="margin-top:0">---</span>
                                    @else
                                        {{ number_format($item->last_price, 0, ',', '.') }}đ
                                    @endif
                                </td>
                                <td>
                                    @if($item->last_scraped_at)
                                        {{ $item->last_scraped_at->format('d/m/Y H:i') }}
                                    @else
                                        <span class="hint" style="margin-top:0">---</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->is_enabled)
                                        <span class="pill" style="background:rgba(22,163,74,.12);border-color:rgba(22,163,74,.25);color:#166534">Đang bật</span>
                                    @else
                                        <span class="pill" style="background:rgba(220,53,69,.10);border-color:rgba(220,53,69,.20);color:#991b1b">Đã tắt</span>
                                    @endif
                                </td>
                                <td style="display:flex;gap:8px;align-items:center">
                                    <form method="POST" action="{{ route('shopee.items.toggle', $item) }}">
                                        @csrf
                                        <button class="btn btn-secondary" type="submit" style="padding:6px 10px">
                                            {{ $item->is_enabled ? 'Tắt' : 'Bật' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('shopee.items.destroy', $item) }}" onsubmit="return confirm('Xoá link này?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-secondary" type="submit" style="padding:6px 10px;color:var(--danger)">Xoá</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="hint">Chưa có link Shopee nào.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

