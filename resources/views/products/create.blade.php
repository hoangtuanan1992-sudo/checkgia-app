@extends('layouts.app')

@section('content')
    <div class="card">
        <div class="card-header">
            <h1 class="card-title">Thêm sản phẩm</h1>
            <p class="card-sub">Hệ thống sẽ tự lấy tên và giá từ link của bạn</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('products.store') }}">
                @csrf

                <div class="field">
                    <label class="label" for="product_url">Link sản phẩm (website của bạn)</label>
                    <input class="input" id="product_url" name="product_url" type="url" value="{{ old('product_url') }}" required placeholder="https://...">
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
                                    <option value="{{ $g->id }}" @selected((string) old('product_group_id') === (string) $g->id)>{{ $g->name }}</option>
                                @endforeach
                            </select>
                            @error('product_group_id')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field" style="margin-top:0">
                            <label class="label" for="product_group_name">Tạo nhóm mới</label>
                            <input class="input" id="product_group_name" name="product_group_name" type="text" value="{{ old('product_group_name') }}" placeholder="VD: Phụ kiện" autocomplete="off">
                            @error('product_group_name')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn" type="submit">Lưu</button>
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
                </div>
            </form>
        </div>
    </div>
@endsection
