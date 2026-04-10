@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:980px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;align-items:center">
                <div>
                    <h1 class="card-title">Sản phẩm của bạn</h1>
                    <p class="card-sub">Quản lý danh sách sản phẩm để so sánh giá</p>
                </div>
                <a class="btn" href="{{ route('products.create') }}">Thêm sản phẩm</a>
            </div>
            <div class="card-body">
                @if($products->isEmpty())
                    <div class="hint">Chưa có sản phẩm nào. Hãy bấm <strong>Thêm sản phẩm</strong>.</div>
                @else
                    <div class="table-wrap">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Tên sản phẩm</th>
                                    <th>Giá của bạn</th>
                                    <th>Link</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($products as $p)
                                    <tr>
                                        <td>{{ $p->name }}</td>
                                        <td>{{ number_format($p->price, 0, ',', '.') }}đ</td>
                                        <td>
                                            @if($p->product_url)
                                                <a href="{{ $p->product_url }}" target="_blank">Mở link</a>
                                            @else
                                                <span class="hint">Chưa có</span>
                                            @endif
                                        </td>
                                        <td style="text-align:right">
                                            <div style="display:flex;gap:8px;justify-content:flex-end">
                                                <a class="btn btn-secondary" href="{{ route('products.edit', $p) }}">Sửa</a>
                                                <form action="{{ route('products.destroy', $p) }}" method="POST" onsubmit="return confirm('Xoá sản phẩm này?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn" type="submit">Xoá</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
