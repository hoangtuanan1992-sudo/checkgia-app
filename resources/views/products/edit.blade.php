@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Cập nhật sản phẩm</h1>
            <p class="card-sub">Sửa thông tin sản phẩm</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('products.update', $product) }}">
                @csrf
                @method('PUT')

                <div class="field">
                    <label class="label">Tên sản phẩm (tự lấy)</label>
                    <input class="input" type="text" value="{{ $product->name }}" disabled>
                </div>

                <div class="field">
                    <label class="label">Giá của bạn (tự lấy)</label>
                    <input class="input" type="text" value="{{ number_format($product->price, 0, ',', '.') }}đ" disabled>
                </div>

                <div class="field">
                    <label class="label" for="product_url">Link sản phẩm (website của bạn)</label>
                    <input class="input" id="product_url" name="product_url" type="url" value="{{ old('product_url', $product->product_url) }}" required placeholder="https://...">
                    @error('product_url')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field">
                    <label class="label">Nhóm sản phẩm (tuỳ chọn)</label>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:end">
                        <div class="field" style="margin-top:0">
                            <label class="label" for="product_group_id">Chọn nhóm</label>
                            <select class="input" id="product_group_id" name="product_group_id">
                                <option value="">-- Chưa chọn --</option>
                                @foreach($groups as $g)
                                    <option value="{{ $g->id }}" @selected((string) old('product_group_id', $product->product_group_id) === (string) $g->id)>{{ $g->name }}</option>
                                @endforeach
                            </select>
                            @error('product_group_id')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field" style="margin-top:0">
                            <label class="label" for="product_group_name">Tạo nhóm mới</label>
                            <input class="input" id="product_group_name" name="product_group_name" type="text" value="{{ old('product_group_name') }}" placeholder="VD: Màn hình" autocomplete="off">
                            @error('product_group_name')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Lưu</button>
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
                </div>
            </form>

            <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">

            <h2 class="card-title" style="font-size:18px">Đối thủ</h2>
            <p class="card-sub">Quản lý link sản phẩm của đối thủ cạnh tranh</p>

            <form method="POST" action="{{ route('products.competitors.store', $product) }}" style="margin-top:10px">
                @csrf
                <div class="field">
                    <label class="label" for="comp_name">Tên website</label>
                    <input class="input" id="comp_name" name="name" type="text" required>
                </div>
                <div class="field">
                    <label class="label" for="comp_url">URL sản phẩm đối thủ</label>
                    <input class="input" id="comp_url" name="url" type="url" required>
                </div>
                <div class="actions">
                    <button class="btn" type="submit">Thêm đối thủ</button>
                </div>
            </form>

            @php($competitors = $product->competitors()->latest()->get())
            @if($competitors->isNotEmpty())
                <div class="table-wrap" style="margin-top:12px">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tên website</th>
                                <th>URL</th>
                                <th>Thêm giá thủ công</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($competitors as $c)
                            <tr>
                                <td>{{ $c->name }}</td>
                                <td><a href="{{ $c->url }}" target="_blank">{{ $c->url }}</a></td>
                                <td style="text-align:right">
                                    <form method="POST" action="{{ route('products.competitors.update', [$product, $c]) }}" style="display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                                        @csrf
                                        @method('PUT')
                                        <input class="input" name="name" type="text" value="{{ $c->name }}" style="width:180px">
                                        <input class="input" name="url" type="url" value="{{ $c->url }}" style="width:320px">
                                        <button class="btn" type="submit">Lưu</button>
                                    </form>
                                </td>
                                <td>
                                    <div style="display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap">
                                        <form method="POST" action="{{ route('competitors.prices.store', $c) }}" style="display:inline-flex;gap:8px;align-items:center">
                                        @csrf
                                        <input class="input" name="price" type="number" min="0" step="1" placeholder="Nhập giá">
                                        <button class="btn" type="submit">Thêm</button>
                                    </form>
                                        <form method="POST" action="{{ route('products.competitors.destroy', [$product, $c]) }}" style="display:inline" onsubmit="return confirm('Xoá đối thủ này?')">
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
@endsection
