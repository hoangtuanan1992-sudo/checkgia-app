@extends('layouts.app')

@section('content')
    @php
        $scannerJobs = $scannerJobs ?? collect();
        $scannerProducts = $scannerProducts ?? null;
        $selectedScannerJob = is_array($selectedScannerJob ?? null) ? $selectedScannerJob : null;
        $selectedJobId = (string) ($selectedJobId ?? '');
        $scannerMode = (string) ($scannerMode ?? 'all');
        $q = (string) ($q ?? '');
        $perPage = (int) ($perPage ?? 50);
        $jobUrl = (string) ($selectedScannerJob['startUrl'] ?? '');
        $jobStatus = (string) ($selectedScannerJob['status'] ?? 'unknown');
        $productCount = (int) ($selectedScannerJob['productCount'] ?? ($scannerProducts?->total() ?? 0));
        $filteredProductCount = (int) ($selectedScannerJob['filteredProductCount'] ?? 0);
        $batchIndex = (int) ($selectedScannerJob['batchIndex'] ?? 0);
        $batchTotal = (int) ($selectedScannerJob['batchTotal'] ?? 0);
        $createdAt = (string) ($selectedScannerJob['createdAt'] ?? '');
        $updatedAt = (string) ($selectedScannerJob['updatedAt'] ?? '');
        $importEndpoint = (string) ($importEndpoint ?? url('/api/products/import'));
        $apiKeyConfigured = (bool) ($apiKeyConfigured ?? false);
    @endphp

    <div style="width:100%;max-width:1280px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap">
                <div>
                    <h1 class="card-title">Quét nhanh</h1>
                    <p class="card-sub">Hiển thị kết quả Windows scanner đã đẩy vào database Check Giá.</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                    <a class="btn btn-secondary" href="{{ request()->fullUrl() }}">Làm mới</a>
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
                </div>
            </div>

            <div class="card-body">
                @if(!empty($scannerError))
                    <div class="scan-alert">
                        <strong>Chưa sẵn sàng nhận dữ liệu quét.</strong>
                        <div>{{ $scannerError }}</div>
                        <div class="hint" style="margin-top:8px">Endpoint import: {{ $importEndpoint }}</div>
                    </div>
                @endif

                @if(!$apiKeyConfigured)
                    <div class="scan-alert scan-alert-warning">
                        <strong>Chưa cấu hình API key import.</strong>
                        <div>Hãy đặt biến <code>CHECKGIA_IMPORT_API_KEY</code> trên hosting, rồi nhập cùng key đó vào phần mềm quét Windows.</div>
                    </div>
                @endif

                <form method="GET" action="{{ route('dashboard.quick-scan') }}" class="scan-filter">
                    <div class="field" style="margin-top:0;min-width:min(100%,360px);flex:2">
                        <label class="label" for="job_id">Link website đã quét</label>
                        <select class="input" id="job_id" name="job_id" required>
                            <option value="" @selected($selectedJobId === '') disabled>-- Chọn website muốn hiển thị --</option>
                            @forelse($scannerJobs as $job)
                                @php
                                    $jobId = (string) ($job['id'] ?? '');
                                    $startUrl = (string) ($job['startUrl'] ?? $jobId);
                                    $status = (string) ($job['status'] ?? 'unknown');
                                    $count = (int) ($job['productCount'] ?? 0);
                                @endphp
                                <option value="{{ $jobId }}" @selected($jobId === $selectedJobId)>
                                    {{ $startUrl }} - {{ number_format($count, 0, ',', '.') }} sản phẩm
                                </option>
                            @empty
                                <option value="" disabled>Chưa có dữ liệu đã đẩy lên</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="field" style="margin-top:0;min-width:min(100%,260px);flex:1">
                        <label class="label" for="q">Tìm kiếm</label>
                        <input class="input" id="q" name="q" type="text" value="{{ $q }}" placeholder="Tên, mã hoặc link...">
                    </div>
                    <div class="field" style="margin-top:0;min-width:160px">
                        <label class="label" for="mode">Lọc</label>
                        <select class="input" id="mode" name="mode">
                            <option value="all" @selected($scannerMode !== 'priced')>Tất cả</option>
                            <option value="priced" @selected($scannerMode === 'priced')>Có giá</option>
                        </select>
                    </div>
                    <div class="field" style="margin-top:0;min-width:150px">
                        <label class="label" for="per_page">Số dòng</label>
                        <select class="input" id="per_page" name="per_page">
                            @foreach([25, 50, 100, 200] as $pp)
                                <option value="{{ $pp }}" @selected($perPage === $pp)>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="actions" style="margin-top:0">
                        <button class="btn" type="submit">Quét</button>
                    </div>
                </form>

                @if($selectedScannerJob)
                    <div class="scan-summary">
                        <div class="scan-stat">
                            <div class="scan-label">Website</div>
                            <div class="scan-value" title="{{ $jobUrl }}">{{ $jobUrl ?: '---' }}</div>
                        </div>
                        <div class="scan-stat">
                            <div class="scan-label">Trạng thái</div>
                            <div class="scan-value"><span class="scan-badge scan-badge-{{ preg_replace('/[^a-z0-9_-]/i', '', $jobStatus) }}">{{ $jobStatus }}</span></div>
                        </div>
                        <div class="scan-stat">
                            <div class="scan-label">Sản phẩm</div>
                            <div class="scan-value">{{ number_format($productCount, 0, ',', '.') }}</div>
                        </div>
                        <div class="scan-stat">
                            <div class="scan-label">Sản phẩm có giá</div>
                            <div class="scan-value">{{ number_format($filteredProductCount, 0, ',', '.') }}</div>
                        </div>
                        <div class="scan-stat">
                            <div class="scan-label">Batch cuối</div>
                            <div class="scan-value">
                                @if($batchIndex > 0 && $batchTotal > 0)
                                    {{ number_format($batchIndex, 0, ',', '.') }}/{{ number_format($batchTotal, 0, ',', '.') }}
                                @else
                                    ---
                                @endif
                            </div>
                        </div>
                        <div class="scan-stat">
                            <div class="scan-label">Cập nhật</div>
                            <div class="scan-value" title="{{ $updatedAt ?: $createdAt }}">{{ $updatedAt ?: ($createdAt ?: '---') }}</div>
                        </div>
                    </div>
                @endif

                <div class="hint" style="margin-top:12px">
                    Endpoint nhận dữ liệu: {{ $importEndpoint }}
                    @if($selectedJobId !== '' && $scannerProducts)
                        - Hiển thị {{ number_format($scannerProducts->count(), 0, ',', '.') }}/{{ number_format($scannerProducts->total(), 0, ',', '.') }} dòng
                    @endif
                </div>

                <div class="table-wrap scan-table-wrap">
                    <table class="table scan-table">
                        <thead>
                            <tr>
                                <th style="width:64px">#</th>
                                <th style="width:150px">Mã</th>
                                <th>Tên sản phẩm</th>
                                <th style="width:160px;text-align:right">Giá</th>
                                <th>Link sản phẩm</th>
                                <th>Nguồn quét</th>
                                <th style="width:190px">Cập nhật</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($scannerProducts ?? [] as $index => $product)
                                @php
                                    $rowNumber = ($scannerProducts->firstItem() ?? 1) + $index;
                                    $name = (string) ($product['name'] ?? '');
                                    $url = (string) ($product['url'] ?? '');
                                    $sourceUrl = (string) ($product['sourceUrl'] ?? '');
                                    $code = (string) ($product['productCode'] ?? '');
                                    $priceText = (string) ($product['price'] ?? '');
                                    $priceValue = (int) ($product['priceValue'] ?? 0);
                                    $displayPrice = $priceText !== '' ? $priceText : ($priceValue > 0 ? number_format($priceValue, 0, ',', '.').'đ' : '');
                                @endphp
                                <tr>
                                    <td class="scan-muted">{{ $rowNumber }}</td>
                                    <td>{{ $code !== '' ? $code : '---' }}</td>
                                    <td style="font-weight:650">{{ $name !== '' ? $name : '---' }}</td>
                                    <td style="text-align:right;font-weight:750">
                                        @if($displayPrice !== '')
                                            {{ $displayPrice }}
                                        @else
                                            <span class="scan-muted">---</span>
                                        @endif
                                    </td>
                                    <td style="word-break:break-word">
                                        @if($url !== '')
                                            <a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a>
                                        @else
                                            <span class="scan-muted">---</span>
                                        @endif
                                    </td>
                                    <td style="word-break:break-word">
                                        @if($sourceUrl !== '')
                                            <a href="{{ $sourceUrl }}" target="_blank" rel="noopener">{{ $sourceUrl }}</a>
                                        @else
                                            <span class="scan-muted">---</span>
                                        @endif
                                    </td>
                                    <td>{{ (string) ($product['updatedAt'] ?? '') ?: '---' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="scan-empty">
                                        @if($selectedJobId === '')
                                            Hãy chọn link website đã quét rồi bấm Quét để hiển thị dữ liệu.
                                        @else
                                            Chưa có sản phẩm phù hợp để hiển thị.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($scannerProducts && $scannerProducts->total() > 0)
                    @php($current = (int) $scannerProducts->currentPage())
                    @php($last = (int) $scannerProducts->lastPage())
                    <div class="scan-pagination">
                        @if($last > 1)
                            @if($scannerProducts->onFirstPage())
                                <span class="btn btn-secondary" style="opacity:0.5;pointer-events:none">Trước</span>
                            @else
                                <a class="btn btn-secondary" href="{{ $scannerProducts->previousPageUrl() }}">Trước</a>
                            @endif

                            @php($pagesRaw = [1, 2, 3, 4, $last, $current - 1, $current, $current + 1])
                            @php($pages = array_values(array_unique(array_filter($pagesRaw, fn ($p) => is_int($p) && $p >= 1 && $p <= $last))))
                            @php(sort($pages))
                            @php($pageUrls = $scannerProducts->getUrlRange(1, $last))
                            @php($prev = 0)
                            @foreach($pages as $page)
                                @if($prev && $page > $prev + 1)
                                    <span class="hint" style="margin-top:0;padding:0 2px">...</span>
                                @endif
                                @php($prev = $page)
                                @if($page === $current)
                                    <span class="btn btn-secondary" style="background:#111827;color:#fff;border-color:#111827;pointer-events:none">{{ $page }}</span>
                                @else
                                    <a class="btn btn-secondary" href="{{ $pageUrls[$page] ?? '' }}">{{ $page }}</a>
                                @endif
                            @endforeach

                            @if($scannerProducts->hasMorePages())
                                <a class="btn btn-secondary" href="{{ $scannerProducts->nextPageUrl() }}">Sau</a>
                            @else
                                <span class="btn btn-secondary" style="opacity:0.5;pointer-events:none">Sau</span>
                            @endif
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <style>
        .scan-filter{display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap}
        .scan-alert{border:1px solid #fecaca;background:#fef2f2;color:#991b1b;border-radius:10px;padding:12px 14px;margin-bottom:14px}
        .scan-alert-warning{border-color:#fed7aa;background:#fff7ed;color:#9a3412}
        .scan-summary{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:14px}
        .scan-stat{border:1px solid #e5e7eb;border-radius:10px;padding:11px 12px;min-width:0;background:#fff}
        .scan-label{font-size:12px;color:#6b7280;margin-bottom:6px}
        .scan-value{font-size:14px;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .scan-badge{display:inline-flex;align-items:center;height:24px;padding:0 9px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:750;text-transform:capitalize}
        .scan-badge-done{background:#ecfdf5;color:#047857}
        .scan-badge-running{background:#eff6ff;color:#1d4ed8}
        .scan-badge-queued{background:#fff7ed;color:#c2410c}
        .scan-badge-failed{background:#fef2f2;color:#b91c1c}
        .scan-badge-imported{background:#ecfdf5;color:#047857}
        .scan-table-wrap{max-height:70vh;margin-top:14px}
        .scan-table{font-size:13px}
        .scan-table tbody tr:hover{background:rgba(17,24,39,.04)}
        .scan-muted{color:#6b7280;font-weight:500}
        .scan-empty{text-align:center;color:#6b7280;padding:24px}
        .scan-pagination{display:flex;justify-content:flex-end;gap:8px;align-items:center;flex-wrap:nowrap;white-space:nowrap;margin-top:12px;overflow-x:auto}
        @media (max-width:1100px){.scan-summary{grid-template-columns:repeat(3,minmax(0,1fr))}}
        @media (max-width:720px){.scan-summary{grid-template-columns:repeat(1,minmax(0,1fr))}.scan-filter .field{width:100%;min-width:100%}.scan-filter .actions{width:100%}.scan-filter .btn{width:100%}}
    </style>
@endsection
