<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sản phẩm đã quét</title>
</head>
<body>
@php
    $websiteUrl = (string) ($websiteUrl ?? '');
    $q = (string) ($q ?? '');
    $perPage = (int) ($perPage ?? 200);
    $products = $products ?? null;
@endphp

<h1>Sản phẩm đã quét</h1>

<form method="GET" action="{{ route('scanner.full-products') }}">
    <label>
        Website
        <input name="website_url" value="{{ $websiteUrl }}" size="48" placeholder="https://dienmaydo.vn/">
    </label>
    <label>
        Tìm kiếm
        <input name="q" value="{{ $q }}" size="32">
    </label>
    <label>
        Số dòng
        <select name="per_page">
            @foreach([50, 100, 200, 500] as $pp)
                <option value="{{ $pp }}" @selected($perPage === $pp)>{{ $pp }}</option>
            @endforeach
        </select>
    </label>
    <button type="submit">Xem</button>
</form>

@if(!empty($error))
    <p>{{ $error }}</p>
@elseif($websiteUrl === '')
    <p>Nhập link website để xem sản phẩm đã quét.</p>
@elseif(!$selectedJob)
    <p>Chưa có dữ liệu quét cho website này.</p>
@else
    <p>Website: {{ $websiteUrl }}</p>
    <p>Tổng: {{ number_format($products->total(), 0, ',', '.') }} sản phẩm</p>
@endif

<table border="1" cellpadding="4" cellspacing="0">
    <thead>
        <tr>
            <th>Mã</th>
            <th>Tên sản phẩm</th>
            <th>Link sản phẩm</th>
        </tr>
    </thead>
    <tbody>
        @forelse($products ?? [] as $product)
            @php
                $name = (string) ($product['name'] ?? '');
                $code = (string) ($product['productCode'] ?? '');
                $url = (string) ($product['url'] ?? '');
            @endphp
            <tr>
                <td>{{ $code !== '' ? $code : '---' }}</td>
                <td>{{ $name !== '' ? $name : '---' }}</td>
                <td>
                    @if($url !== '')
                        <a href="{{ $url }}">{{ $url }}</a>
                    @else
                        ---
                    @endif
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="3">Không có sản phẩm.</td>
            </tr>
        @endforelse
    </tbody>
</table>

@if($products && $products->lastPage() > 1)
    <p>
        Trang {{ $products->currentPage() }}/{{ $products->lastPage() }}
        @if($products->onFirstPage())
            Trước
        @else
            <a href="{{ $products->previousPageUrl() }}">Trước</a>
        @endif
        @if($products->hasMorePages())
            <a href="{{ $products->nextPageUrl() }}">Sau</a>
        @else
            Sau
        @endif
    </p>
@endif
</body>
</html>
