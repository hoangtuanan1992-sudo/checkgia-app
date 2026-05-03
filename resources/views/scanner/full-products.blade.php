@extends('layouts.app')

@section('content')
    @php
        $websiteUrl = (string) ($websiteUrl ?? '');
        $q = (string) ($q ?? '');
        $perPage = (int) ($perPage ?? 200);
        $selectedJob = $selectedJob ?? null;
        $products = $products ?? null;
        $total = $products ? (int) $products->total() : 0;
        $jobUpdatedAt = $selectedJob ? (string) (($selectedJob->last_pushed_at ?? null) ?: ($selectedJob->updated_at ?? '')) : '';
        $fullLink = $websiteUrl !== '' ? route('scanner.full-products', ['website_url' => $websiteUrl]) : route('scanner.full-products');
    @endphp

    <div style="width:100%;max-width:1380px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                <div>
                    <h1 class="card-title">Sản phẩm đã quét</h1>
                    <p class="card-sub">Hiển thị tất cả sản phẩm của website đã được Windows scanner đẩy lên Check Giá.</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                    <a class="btn btn-secondary" href="{{ $fullLink }}">Link đầy đủ</a>
                    <a class="btn btn-secondary" href="{{ request()->fullUrl() }}">Làm mới</a>
                </div>
            </div>

            <div class="card-body">
                <form method="GET" action="{{ route('scanner.full-products') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px">
                    <div class="field" style="margin-top:0;min-width:min(100%,420px);flex:2">
                        <label class="label" for="website_url">Link website</label>
                        <input class="input" id="website_url" name="website_url" type="url" value="{{ $websiteUrl }}" placeholder="https://dienmaydo.vn/" required>
                    </div>
                    <div class="field" style="margin-top:0;min-width:min(100%,260px);flex:1">
                        <label class="label" for="q">Tìm kiếm</label>
                        <input class="input" id="q" name="q" type="text" value="{{ $q }}" placeholder="Tên, mã hoặc link...">
                    </div>
                    <div class="field" style="margin-top:0;min-width:150px">
                        <label class="label" for="per_page">Số dòng</label>
                        <select class="input" id="per_page" name="per_page">
                            @foreach([50, 100, 200, 500] as $pp)
                                <option value="{{ $pp }}" @selected($perPage === $pp)>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn" type="submit">Xem sản phẩm</button>
                </form>

                @if(!empty($error))
                    <div class="error" style="padding:12px;border:1px solid rgba(220,53,69,.24);border-radius:12px;background:rgba(220,53,69,.06)">{{ $error }}</div>
                @endif

                @if($websiteUrl === '')
                    <div class="hint">Nhập link website để xem danh sách sản phẩm đã quét. Ví dụ: {{ route('scanner.full-products') }}?website_url=https://dienmaydo.vn/</div>
                @elseif(!$selectedJob && empty($error))
                    <div class="hint">Chưa có dữ liệu quét cho website này.</div>
                @endif

                @if($selectedJob)
                    <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:12px">
                        <div class="pill" style="justify-content:space-between;border-radius:10px">
                            <span>Website</span>
                            <strong style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $websiteUrl }}</strong>
                        </div>
                        <div class="pill" style="justify-content:space-between;border-radius:10px">
                            <span>Tổng sản phẩm</span>
                            <strong>{{ number_format($total, 0, ',', '.') }}</strong>
                        </div>
                        <div class="pill" style="justify-content:space-between;border-radius:10px">
                            <span>Batch cuối</span>
                            <strong>{{ (int) ($selectedJob->batch_index ?? 0) }}/{{ (int) ($selectedJob->batch_total ?? 0) }}</strong>
                        </div>
                        <div class="pill" style="justify-content:space-between;border-radius:10px">
                            <span>Cập nhật</span>
                            <strong>{{ $jobUpdatedAt ?: '---' }}</strong>
                        </div>
                    </div>
                @endif

                <div class="table-wrap" style="max-height:none">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:64px">#</th>
                                <th style="width:160px">Mã</th>
                                <th>Tên sản phẩm</th>
                                <th style="width:160px;text-align:right">Giá</th>
                                <th>Link sản phẩm</th>
                                <th style="width:190px">Cập nhật</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products ?? [] as $index => $product)
                                @php
                                    $rowNumber = ($products->firstItem() ?? 1) + $index;
                                    $name = (string) ($product['name'] ?? '');
                                    $code = (string) ($product['productCode'] ?? '');
                                    $url = (string) ($product['url'] ?? '');
                                    $priceText = trim((string) ($product['priceText'] ?? ''));
                                    $priceValue = (int) ($product['priceValue'] ?? 0);
                                    $digits = preg_replace('/\D+/', '', $priceText) ?? '';
                                    $displayPrice = $priceValue > 0
                                        ? number_format($priceValue, 0, ',', '.').'đ'
                                        : ($digits !== '' ? number_format((int) $digits, 0, ',', '.').'đ' : $priceText);
                                @endphp
                                <tr>
                                    <td>{{ $rowNumber }}</td>
                                    <td>{{ $code !== '' ? $code : '---' }}</td>
                                    <td style="font-weight:650">{{ $name !== '' ? $name : '---' }}</td>
                                    <td style="text-align:right;font-weight:750">{{ $displayPrice !== '' ? $displayPrice : '---' }}</td>
                                    <td style="word-break:break-word">
                                        @if($url !== '')
                                            <a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a>
                                        @else
                                            ---
                                        @endif
                                    </td>
                                    <td>{{ (string) ($product['updatedAt'] ?? '') ?: '---' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="hint">Chưa có sản phẩm phù hợp để hiển thị.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($products && $products->lastPage() > 1)
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px">
                        <div class="hint" style="margin-top:0">
                            Trang {{ $products->currentPage() }}/{{ $products->lastPage() }} - Hiển thị {{ number_format($products->count(), 0, ',', '.') }}/{{ number_format($products->total(), 0, ',', '.') }} dòng
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                            @if($products->onFirstPage())
                                <span class="btn btn-secondary" style="opacity:.5;pointer-events:none">Trước</span>
                            @else
                                <a class="btn btn-secondary" href="{{ $products->previousPageUrl() }}">Trước</a>
                            @endif

                            @php($firstPageUrls = $products->getUrlRange(1, min(4, $products->lastPage())))
                            @foreach($firstPageUrls as $p => $url)
                                @if((int) $p === (int) $products->currentPage())
                                    <span class="btn btn-secondary" style="background:#111827;color:#fff;border-color:#111827;pointer-events:none">{{ $p }}</span>
                                @else
                                    <a class="btn btn-secondary" href="{{ $url }}">{{ $p }}</a>
                                @endif
                            @endforeach
                            @if($products->lastPage() > 4)
                                <span style="display:inline-flex;align-items:center;color:var(--muted);font-weight:700;padding:0 4px">.....</span>
                                <a class="btn btn-secondary" href="{{ $products->url($products->lastPage()) }}">{{ $products->lastPage() }}</a>
                            @endif

                            @if($products->hasMorePages())
                                <a class="btn btn-secondary" href="{{ $products->nextPageUrl() }}">Sau</a>
                            @else
                                <span class="btn btn-secondary" style="opacity:.5;pointer-events:none">Sau</span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
