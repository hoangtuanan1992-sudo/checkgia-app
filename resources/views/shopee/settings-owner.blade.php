@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:1100px">
        <div class="card-header">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="min-width:0">
                    <h1 class="card-title" style="margin:0">Cài đặt Shopee</h1>
                    <p class="card-sub" style="margin:6px 0 0">Thêm tên shop đối thủ và sắp xếp thứ tự cột</p>
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

                    <div class="hint" style="margin-top:12px">
                        Thêm sản phẩm Shopee ở trang Dashboard.
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
