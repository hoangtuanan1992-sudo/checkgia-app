@extends('layouts.app')

@section('content')
    @php($isViewer = auth()->user()?->isViewer())
    <div style="width:100%;max-width:1500px">
        <style>
            #comparisonCardView{
                grid-template-columns:repeat(2,minmax(0,1fr));
                gap:12px;
                width:100%;
                align-items:start;
            }
            #comparisonCardView .compare-card{
                max-width:none;
                width:100%;
                height:100%;
            }
            #comparisonTableView{
                --compare-name-col-width:360px;
                --compare-price-col-width:170px;
            }
            #comparisonTableView .compare-sticky-name,
            #comparisonTableView .compare-sticky-price{
                position:sticky !important;
                background:#fff;
                z-index:35;
                background-clip:padding-box;
            }
            #comparisonTableView .compare-sticky-name{
                left:0;
                min-width:var(--compare-name-col-width);
                width:var(--compare-name-col-width);
                max-width:var(--compare-name-col-width);
            }
            #comparisonTableView .compare-sticky-price{
                left:var(--compare-name-col-width);
                min-width:var(--compare-price-col-width);
                width:var(--compare-price-col-width);
                max-width:var(--compare-price-col-width);
            }
            #comparisonTableView thead .compare-sticky-name,
            #comparisonTableView thead .compare-sticky-price{
                background-color:#007bff !important;
                color:#fff !important;
                z-index:1001 !important;
            }
            .compare-note-button{
                border:0;
                background:transparent;
                color:var(--muted);
                padding:0;
                font:inherit;
                cursor:pointer;
                text-decoration:underline;
                text-underline-offset:2px;
            }
            .compare-note{
                margin-top:4px;
                padding:7px 9px;
                border:1px solid #bfdbfe;
                border-radius:8px;
                background:#eff6ff;
                color:#1f2937;
                font-size:12px;
                line-height:1.35;
                white-space:pre-wrap;
                word-break:break-word;
                text-align:left;
                cursor:pointer;
            }
            @media (max-width: 768px){
                #comparisonTableView{
                    --compare-name-col-width:280px;
                    --compare-price-col-width:150px;
                }
            }
            .comparison-pagination{
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:12px;
                flex-wrap:wrap;
                margin-top:14px;
                padding-top:14px;
                border-top:1px solid var(--border);
            }
            .comparison-page-left,
            .comparison-page-buttons{
                display:flex;
                align-items:center;
                gap:8px;
                flex-wrap:wrap;
            }
            .comparison-page-left{
                color:var(--muted);
                font-size:13px;
            }
            .comparison-page-input{
                width:92px;
                height:42px;
                padding:0 10px;
                text-align:center;
            }
            .comparison-per-page{
                width:92px;
                height:42px;
                padding:0 10px;
            }
            .comparison-card-column-control{
                display:none;
                align-items:center;
                gap:8px;
            }
            .comparison-card-column-control.is-visible{
                display:flex;
            }
            .comparison-card-columns{
                width:92px;
                height:42px;
                padding:0 10px;
            }
            .group-filter-picker{
                position:relative;
            }
            .group-filter-select{
                position:absolute;
                width:1px;
                height:1px;
                opacity:0;
                pointer-events:none;
            }
            .group-filter-trigger{
                width:100%;
                min-height:42px;
                display:flex;
                justify-content:space-between;
                align-items:center;
                gap:8px;
                text-align:left;
                cursor:pointer;
            }
            .group-filter-menu{
                position:absolute;
                left:0;
                right:0;
                top:calc(100% + 6px);
                z-index:1200;
                max-height:280px;
                overflow:auto;
                border:1px solid var(--border);
                border-radius:12px;
                background:#fff;
                box-shadow:0 18px 45px rgba(15,23,42,.14);
                padding:6px;
            }
            .group-filter-row{
                display:flex;
                align-items:center;
                gap:4px;
            }
            .group-filter-option,
            .group-filter-add{
                border:0;
                background:transparent;
                color:#111827;
                cursor:pointer;
            }
            .group-filter-option{
                flex:1;
                min-height:36px;
                padding:8px 10px;
                text-align:left;
                border-radius:8px;
            }
            .group-filter-option:hover,
            .group-filter-option.is-active{
                background:#eff6ff;
                color:#1d4ed8;
            }
            .group-filter-add{
                width:34px;
                height:34px;
                border:1px solid var(--border);
                border-radius:9px;
                font-size:18px;
                font-weight:800;
                line-height:1;
            }
            .group-filter-add:hover{
                border-color:#2563eb;
                background:#eff6ff;
                color:#1d4ed8;
            }
            .comparison-page-btn{
                min-width:42px;
                height:42px;
                border:1px solid var(--border);
                border-radius:10px;
                background:#fff;
                color:#111827;
                font-weight:600;
                cursor:pointer;
            }
            .comparison-page-btn.is-active{
                background:#111827;
                border-color:#111827;
                color:#fff;
            }
            .comparison-page-btn:disabled{
                color:#9ca3af;
                background:#f9fafb;
                cursor:not-allowed;
            }
            .comparison-floating-pager{
                position:fixed;
                right:18px;
                bottom:18px;
                z-index:9999;
                display:none;
                gap:8px;
                align-items:center;
                padding:8px;
                border:1px solid var(--border);
                border-radius:12px;
                background:rgba(255,255,255,.96);
                box-shadow:0 18px 45px rgba(15,23,42,.18);
                backdrop-filter:blur(8px);
            }
            .comparison-floating-pager.is-visible{
                display:flex;
            }
            .comparison-floating-pager .btn{
                min-width:76px;
            }
            .compare-restore-highlight{
                outline:2px solid #2563eb;
                outline-offset:-2px;
                background:#eff6ff !important;
            }
            .comparison-page-ellipsis{
                min-width:34px;
                text-align:center;
                color:var(--muted);
                font-weight:700;
            }
            @media (max-width: 1100px){
                #comparisonCardView{
                    grid-template-columns:1fr;
                }
            }
        </style>

        <div class="card" style="max-width:none;margin-bottom:16px">
            <div id="addProductHeader" style="display:flex;justify-content:space-between;gap:12px;align-items:center;cursor:pointer;user-select:none;padding:16px 16px 6px">
                <div>
                    <h1 class="card-title">Nhập link sản phẩm</h1>
                    <p class="card-sub">Thêm nhanh sản phẩm và link đối thủ để so sánh</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center">
                    <a class="btn btn-secondary" href="{{ route('dashboard.quick-scan') }}" onclick="event.stopPropagation()">Quét nhanh</a>
                    <div id="addProductChevron" style="width:28px;height:28px;border-radius:999px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>
            </div>
            <div id="addProductBody" class="card-body">
                <form method="POST" action="{{ route('dashboard.products.store') }}">
                    @csrf
                    <div class="field" style="margin-top:0">
                        <label class="label" for="product_url">Link sản phẩm của bạn</label>
                        <input class="input" id="product_url" name="product_url" type="url" value="{{ old('product_url') }}" placeholder="https://..." required>
                        @error('product_url')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    @if($competitorSites->isNotEmpty())
                        <div style="margin-top:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                            @foreach($competitorSites as $site)
                                <div class="field" style="margin-top:0">
                                    <label class="label">Link {{ $site->name }}</label>
                                    <input class="input" name="competitor_urls[{{ $site->id }}]" type="url" value="{{ old('competitor_urls.'.$site->id) }}" placeholder="https://...">
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="hint">Chưa có đối thủ nào. Hãy bấm “Cài đặt” để tạo cột so sánh.</div>
                    @endif

                    <div class="field">
                        <label class="label">Nhóm sản phẩm{{ auth()->user()->isViewer() ? '' : ' (tuỳ chọn)' }}</label>
                        <div style="display:grid;grid-template-columns:{{ auth()->user()->isViewer() ? '1fr' : 'repeat(2,minmax(0,1fr))' }};gap:12px;align-items:end">
                            <div class="field" style="margin-top:0">
                                <label class="label" for="product_group_id">Chọn nhóm</label>
                                <select class="input" id="product_group_id" name="product_group_id" @if(auth()->user()->isViewer()) required @endif>
                                    <option value="">{{ auth()->user()->isViewer() ? '-- Chọn nhóm được cấp quyền --' : '-- Chưa chọn --' }}</option>
                                    @foreach($productGroups as $g)
                                        <option value="{{ $g->id }}" @selected((string) old('product_group_id') === (string) $g->id)>{{ $g->name }}</option>
                                    @endforeach
                                </select>
                                @error('product_group_id')<div class="error">{{ $message }}</div>@enderror
                            </div>
                            @unless(auth()->user()->isViewer())
                            <div class="field" style="margin-top:0">
                                <label class="label" for="product_group_name">Tạo nhóm mới</label>
                                <input class="input" id="product_group_name" name="product_group_name" type="text" value="{{ old('product_group_name') }}" placeholder="VD: Laptop Gaming" autocomplete="off">
                                @error('product_group_name')<div class="error">{{ $message }}</div>@enderror
                            </div>
                            @endunless
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn" type="submit">Thêm vào danh sách</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card" style="max-width:none;margin-bottom:16px">
            <div id="priceFeedHeader" style="display:flex;justify-content:space-between;gap:12px;align-items:center;cursor:pointer;user-select:none;padding:16px 16px 6px">
                <div>
                    <h2 class="card-title" style="margin:0">Biến động giá mới nhất</h2>
                    <p class="card-sub" style="margin:6px 0 0">Hiển thị 6 biến động gần nhất</p>
                </div>
                <div id="priceFeedChevron" style="width:28px;height:28px;border-radius:999px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div id="priceFeedBody" class="card-body" style="padding-top:10px">
                @php($events = collect($priceEvents ?? []))
                @php($cols = $events->values()->chunk(3))
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                    @for($i = 0; $i < 2; $i++)
                        @php($col = $cols->get($i, collect()))
                        <div style="display:flex;flex-direction:column;gap:10px;min-height:84px">
                            @foreach($col as $e)
                                @php($isUp = (int) $e['delta'] > 0)
                                <a href="{{ route('competitors.history', $e['competitor_id']) }}" style="display:flex;gap:8px;align-items:baseline;text-decoration:none;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <span class="hint" style="margin-top:0;flex:0 0 auto">{{ $e['ago'] }}</span>
                                    <span style="font-weight:700;flex:0 0 auto">#{{ $e['product_id'] }}</span>
                                    <span style="font-weight:600;overflow:hidden;text-overflow:ellipsis">{{ $e['product_name'] }}</span>
                                    <span class="hint" style="margin-top:0;flex:0 0 auto">{{ $e['site_name'] }}</span>
                                    <span style="font-weight:700;flex:0 0 auto;color:{{ $isUp ? 'var(--success)' : 'var(--danger)' }}">
                                        {{ $isUp ? 'Tăng' : 'Giảm' }} {{ $e['delta_text'] }}
                                    </span>
                                </a>
                            @endforeach
                            @if($col->isEmpty())
                                <div class="hint" style="margin-top:0">Chưa có biến động giá.</div>
                            @endif
                        </div>
                    @endfor
                </div>
            </div>
        </div>

        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h2 class="card-title">Kết quả so sánh</h2>
                    <p class="card-sub">Giá chênh = Giá đối thủ - Giá của bạn</p>
                </div>
                <div class="pill" id="productsTotalPill">Tổng sản phẩm: {{ number_format((int) ($productsTotal ?? $products->count()), 0, ',', '.') }}</div>
            </div>
            <div class="card-body">
                <div id="dashboardToolbar" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
                    <div class="field" style="margin-top:0;min-width:240px;flex:1">
                        <label class="label" for="filterSearch">Tìm kiếm</label>
                        <input class="input" id="filterSearch" type="text" value="{{ request('q') }}" placeholder="Nhập tên sản phẩm hoặc ID...">
                    </div>
                    <div class="field" style="margin-top:0;min-width:220px">
                        <label class="label" for="filterGroup">Nhóm</label>
                        <div class="group-filter-picker" id="filterGroupPicker">
                        <select class="input group-filter-select" id="filterGroup" tabindex="-1" aria-hidden="true">
                            @unless($isViewer)
                                <option value="">Tất cả</option>
                                <option value="__none__" @selected(request('group') === '__none__')>Chưa có nhóm</option>
                            @else
                                @if($productGroups->isEmpty())
                                    <option value="" selected disabled>Chưa được cấp nhóm</option>
                                @endif
                            @endunless
                            @foreach($productGroups as $g)
                                <option value="{{ $g->id }}" @selected((string) request('group') === (string) $g->id)>{{ $g->name }}</option>
                            @endforeach
                        </select>
                        <button class="input group-filter-trigger" id="filterGroupTrigger" type="button" aria-haspopup="listbox" aria-expanded="false">
                            <span id="filterGroupLabel">
                                @if($isViewer)
                                    {{ $productGroups->firstWhere('id', (int) request('group'))?->name ?? ($productGroups->first()->name ?? 'Chưa được cấp nhóm') }}
                                @else
                                    Tất cả
                                @endif
                            </span>
                            <span aria-hidden="true">▾</span>
                        </button>
                        <div class="group-filter-menu" id="filterGroupMenu" hidden>
                            @unless($isViewer)
                                <div class="group-filter-row">
                                    <button class="group-filter-option" type="button" data-group-filter-value="">Tất cả</button>
                                </div>
                                <div class="group-filter-row">
                                    <button class="group-filter-option" type="button" data-group-filter-value="__none__">Chưa có nhóm</button>
                                </div>
                            @else
                                @if($productGroups->isEmpty())
                                    <div class="group-filter-row">
                                        <button class="group-filter-option" type="button" disabled>Chưa được cấp nhóm</button>
                                    </div>
                                @endif
                            @endunless
                            @foreach($productGroups as $g)
                                <div class="group-filter-row">
                                    <button class="group-filter-option" type="button" data-group-filter-value="{{ $g->id }}">{{ $g->name }}</button>
                                    @unless($isViewer)
                                        <button class="group-filter-add" type="button" data-group-id="{{ $g->id }}" data-group-name="{{ e($g->name) }}" data-action="{{ route('dashboard.products.assign-group', $g) }}" title="Thêm sản phẩm đang xem vào nhóm {{ $g->name }}" aria-label="Thêm vào nhóm {{ $g->name }}">+</button>
                                    @endunless
                                </div>
                            @endforeach
                        </div>
                        </div>
                    </div>
                    <div class="field" style="margin-top:0;min-width:220px">
                        <label class="label" for="filterCompetitorGroup">Nhóm đối thủ</label>
                        <select class="input" id="filterCompetitorGroup">
                            @unless($isViewer)
                                <option value="">Tất cả</option>
                            @else
                                @if(($competitorGroups ?? collect())->isEmpty())
                                    <option value="" selected disabled>Chưa được cấp nhóm</option>
                                @endif
                            @endunless
                            @foreach(($competitorGroups ?? collect()) as $group)
                                <option value="{{ $group->id }}" @selected((int) ($selectedCompetitorGroupId ?? 0) === (int) $group->id)>{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin-top:0;min-width:240px">
                        <label class="label" for="sortSelect">Sắp xếp</label>
                        <select class="input" id="sortSelect">
                            <option value="name_asc" @selected(request('sort') === 'name_asc')>ABC (A-Z)</option>
                            <option value="row_asc" @selected(request('sort', 'row_asc') === 'row_asc')>Số thứ tự</option>
                            <option value="last_desc" @selected(request('sort') === 'last_desc')>Cập nhật gần nhất</option>
                            <option value="last_asc" @selected(request('sort') === 'last_asc')>Cập nhật cũ nhất</option>
                            <option value="price_asc" @selected(request('sort') === 'price_asc')>Giá của bạn: thấp → cao</option>
                            <option value="price_desc" @selected(request('sort') === 'price_desc')>Giá của bạn: cao → thấp</option>
                            <option value="diff_asc" @selected(request('sort') === 'diff_asc')>Chênh lệch: rẻ hơn nhiều nhất</option>
                            <option value="diff_desc" @selected(request('sort') === 'diff_desc')>Chênh lệch: đắt hơn nhiều nhất</option>
                        </select>
                    </div>
                    <div class="actions" style="margin-top:0">
                        <a class="btn btn-secondary" id="exportAll" href="{{ route('dashboard.export.products') }}">Xuất Excel</a>
                        <a class="btn btn-secondary" id="exportGroup" href="{{ route('dashboard.export.products') }}">Xuất theo nhóm</a>
                        <button class="btn btn-secondary" type="button" id="excelImportOpen">Nhập Excel</button>
                        <form method="POST" action="{{ route('dashboard.scrape.now') }}" id="comparisonScrapeNowForm" style="display:inline">
                            @csrf
                            <button class="btn btn-secondary" type="submit">Cập nhật</button>
                        </form>
                        <button class="btn btn-secondary" type="button" id="compareViewToggle" style="display:none">Dạng thẻ</button>
                        @if($compareMatchEnabled ?? false)
                            <button class="btn btn-secondary" type="button" id="compareMatchOpen">So Khớp</button>
                        @endif
                        <button class="btn btn-secondary" type="button" id="bulkDeleteOpen" data-action="{{ route('dashboard.products.bulk-destroy') }}">Xóa</button>
                    </div>
                </div>
                <div id="comparisonPagination" class="comparison-pagination" style="margin-top:0;margin-bottom:12px;padding-top:0;border-top:0">
                    <div class="comparison-page-left">
                        <span id="comparePageSummary">Trang {{ (int) ($comparisonMeta['page'] ?? 1) }}/{{ (int) ($comparisonMeta['pageCount'] ?? 1) }} • Hiển thị {{ (int) ($comparisonMeta['shown'] ?? $products->count()) }}/{{ number_format((int) ($comparisonMeta['total'] ?? $products->count()), 0, ',', '.') }}</span>
                        <label class="label" for="comparePerPage" style="margin:0">Số dòng</label>
                        <select class="input comparison-per-page" id="comparePerPage">
                            @foreach([20, 50, 100, 200, 500] as $size)
                                <option value="{{ $size }}" @selected((int) ($comparisonMeta['perPage'] ?? 50) === $size)>{{ $size }}</option>
                            @endforeach
                        </select>
                        <label class="label" for="comparePageJump" style="margin:0">Bạn muốn xem trang:</label>
                        <input class="input comparison-page-input" id="comparePageJump" type="number" min="1" value="{{ (int) ($comparisonMeta['page'] ?? 1) }}" inputmode="numeric">
                        <span class="comparison-card-column-control" id="compareCardColumnsWrap">
                            <label class="label" for="compareCardColumns" style="margin:0">Số cột thẻ</label>
                            <select class="input comparison-card-columns" id="compareCardColumns">
                                <option value="1">1</option>
                                <option value="2">2</option>
                            </select>
                        </span>
                    </div>
                    <div class="comparison-page-buttons" id="comparePageButtons" aria-label="Phân trang kết quả so sánh"></div>
                </div>

                @php($rowOffset = method_exists($products, 'firstItem') ? (($products->firstItem() ?? 1) - 1) : 0)
                <div id="comparisonResults"
                     data-current-page="{{ (int) ($comparisonMeta['page'] ?? 1) }}"
                     data-page-count="{{ (int) ($comparisonMeta['pageCount'] ?? 1) }}"
                     data-per-page="{{ (int) ($comparisonMeta['perPage'] ?? 50) }}"
                     data-total="{{ (int) ($comparisonMeta['total'] ?? $products->count()) }}"
                     data-shown="{{ (int) ($comparisonMeta['shown'] ?? $products->count()) }}">
                <div class="table-wrap" id="comparisonTableView">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:52px">#</th>
                                <th class="compare-sticky-name">Tên sản phẩm</th>
                                <th class="compare-sticky-price">Giá của bạn</th>
                                @foreach($comparisonCompetitorSites as $site)
                                    <th style="min-width:160px">{{ $site->name }}</th>
                                @endforeach
                                <th style="min-width:160px">Thời gian</th>
                                <th style="width:110px">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $idx => $product)
                                @php($own = (int) $product->price)
                                @php($map = $product->competitors->keyBy('competitor_site_id'))
                                @php($latestTimes = $product->competitors->map(fn($c) => $c->price_missing_at ? null : $c->prices->first()?->fetched_at)->filter())
                                @php($lastTime = $latestTimes->max())
                                @php($lastUpdated = collect([$lastTime, $product->last_scraped_at])->filter()->max())
                                @php($minDiff = $own > 0 ? $product->competitors->map(function ($c) use ($own) {
                                    $p = $c->price_missing_at ? null : $c->prices->first()?->price;
                                    if (is_null($p)) {
                                        return null;
                                    }

                                    return (int) $p + (int) ($c->price_adjustment ?? 0) - $own;
                                })->filter(fn ($v) => ! is_null($v))->min() : null)
                                <tr
                                    data-product-row="{{ $product->id }}"
                                    data-row-order="{{ $rowOffset + $idx }}"
                                    data-product-name="{{ $product->name }}"
                                    data-product-id="{{ $product->id }}"
                                    data-group-id="{{ $product->product_group_id ?? '' }}"
                                    data-own-price="{{ $own }}"
                                    data-last-updated="{{ $lastUpdated?->timestamp ?? 0 }}"
                                    data-min-diff="{{ is_null($minDiff) ? '' : $minDiff }}"
                                >
                                    <td>{{ $rowOffset + $idx + 1 }}</td>
                                    <td class="compare-sticky-name">
                                        <div style="display:flex;gap:10px;align-items:center">
                                            <div style="display:flex;flex-direction:column;gap:4px">
                                                <span style="font-weight:600">{{ $product->name }}</span>
                                                <span class="hint" style="margin-top:0">ID: {{ $product->id }}</span>
                                                @if($product->group)
                                                    <span class="hint" style="margin-top:0">Nhóm: {{ $product->group->name }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="compare-sticky-price">
                                        <div style="display:flex;flex-direction:column;gap:4px;padding-top:0px">
                                            <div style="display:flex;align-items:center;gap:8px">
                                                <button
                                                    type="button"
                                                    class="icon-btn icon-btn-sm js-edit-url"
                                                    data-action="{{ route('dashboard.products.url.update', $product) }}"
                                                    data-field="product_url"
                                                    data-value="{{ $product->product_url }}"
                                                    title="Sửa link sản phẩm của bạn"
                                                >
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        <path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.41a5 5 0 0 0 7.07 7.07L14 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </button>
                                                @if($product->product_url)
                                                    <a href="{{ $product->product_url }}" target="_blank" style="font-size:13px">link sản phẩm</a>
                                                @else
                                                    <span class="hint" style="margin-top:0">chưa có link</span>
                                                @endif
                                            </div>
                                            <div style="font-weight:600">
                                                <a href="{{ route('products.history', $product) }}" style="color:#111827">
                                                    {{ $own > 0 ? number_format($own, 0, ',', '.').'đ' : 'Liên hệ' }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    @foreach($comparisonCompetitorSites as $site)
                                        @php($c = $map->get($site->id))
                                        @php($cUrl = trim((string) ($c?->url ?? '')))
                                        @php($cNote = trim((string) ($c?->note ?? '')))
                                        @php($cVariantName = trim((string) ($c?->variant_name ?? '')))
                                        @php($latest = $c?->prices->first())
                                        @php($prev = $c?->prices->skip(1)->first())
                                        @php($cPrice = ($c?->price_missing_at || $cUrl === '') ? null : $latest?->price)
                                        @php($prevPrice = $prev?->price)
                                        @php($diff = is_null($cPrice) || $own <= 0 ? null : ((int) $cPrice - $own))
                                        @php($adj = (int) ($c?->price_adjustment ?? 0))
                                        @php($adjDiff = is_null($cPrice) || $own <= 0 ? null : ((int) $cPrice + $adj - $own))
                                        @php($delta = (! is_null($cPrice) && ! is_null($prevPrice)) ? ((int) $cPrice - (int) $prevPrice) : null)
                                        <td>
                                            @if($c)
                                                <div style="display:flex;flex-direction:column;gap:4px;padding-top:6px">
                                                    <div style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px">
                                                        @if($cUrl === '')
                                                            <button
                                                                type="button"
                                                                class="compare-note-button js-edit-note"
                                                                data-action="{{ route('dashboard.products.competitors.note', [$product, $site]) }}"
                                                                data-value="{{ $cNote }}"
                                                                title="Thêm note cho ô chưa có link"
                                                            >---</button>
                                                        @elseif(is_null($diff))
                                                            <span class="hint" style="margin-top:0">---</span>
                                                        @elseif($diff === 0)
                                                            <a href="{{ $cUrl }}" target="_blank" style="color:#6b7280">không chênh</a>
                                                        @elseif($diff > 0)
                                                            <a href="{{ $cUrl }}" target="_blank" style="color:var(--success)">+{{ number_format($diff, 0, ',', '.') }}đ</a>
                                                        @else
                                                            <a href="{{ $cUrl }}" target="_blank" style="color:var(--danger)">{{ number_format($diff, 0, ',', '.') }}đ</a>
                                                        @endif

                                                        @if(! is_null($adjDiff))
                                                            @php($adjColor = $adjDiff > 0 ? '#166534' : ($adjDiff < 0 ? '#991b1b' : '#111827'))
                                                            <a href="{{ $cUrl }}" target="_blank" id="adjDiff-{{ $c->id }}" style="display:{{ $adj !== 0 && $cUrl !== '' ? 'inline' : 'none' }};font-weight:800;color:{{ $adjColor }}">
                                                                @if($adjDiff > 0)
                                                                    +{{ number_format($adjDiff, 0, ',', '.') }}đ
                                                                @elseif($adjDiff < 0)
                                                                    {{ number_format($adjDiff, 0, ',', '.') }}đ
                                                                @else
                                                                    0đ
                                                                @endif
                                                            </a>
                                                            <button
                                                                type="button"
                                                                class="icon-btn icon-btn-sm js-edit-adjustment"
                                                                data-action="{{ route('competitors.adjustment.update', $c) }}"
                                                                data-value="{{ $adj }}"
                                                                data-span-id="adjDiff-{{ $c->id }}"
                                                                data-span-ids="adjDiff-{{ $c->id }},adjDiffCard-{{ $c->id }}"
                                                                data-own="{{ $own }}"
                                                                data-cprice="{{ is_null($cPrice) ? '' : (int) $cPrice }}"
                                                                data-variants-url="{{ route('competitors.variants', $c) }}"
                                                                data-variant-key="{{ $c?->variant_key }}"
                                                                data-variant-name="{{ $cVariantName }}"
                                                                title="Điều chỉnh giá (+/-)"
                                                            >
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                    <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                                </svg>
                                                            </button>
                                                        @endif
                                                    </div>
                                                    <div style="display:flex;align-items:center;gap:8px">
                                                        <button type="button"
                                                                class="icon-btn icon-btn-sm js-edit-url"
                                                                data-action="{{ route('dashboard.products.competitors.upsert', [$product, $site]) }}"
                                                                data-field="url"
                                                                data-value="{{ $cUrl }}"
                                                                title="Sửa URL">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.41a5 5 0 0 0 7.07 7.07L14 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        </button>
                                                        @if($cPrice)
                                                            <a href="{{ route('competitors.history', $c) }}" title="{{ $product->name }}">{{ number_format($cPrice, 0, ',', '.') }}đ</a>
                                                            @if(! is_null($delta) && $delta !== 0)
                                                                <span title="{{ $delta > 0 ? 'Tăng' : 'Giảm' }} {{ number_format(abs($delta), 0, ',', '.') }}đ" style="display:inline-flex;align-items:center">
                                                                    @if($delta > 0)
                                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="color:var(--success)">
                                                                            <path d="M12 5l6 6h-4v8h-4v-8H6l6-6z" fill="currentColor"/>
                                                                        </svg>
                                                                    @else
                                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="color:var(--danger)">
                                                                            <path d="M12 19l-6-6h4V5h4v8h4l-6 6z" fill="currentColor"/>
                                                                        </svg>
                                                                    @endif
                                                                </span>
                                                            @endif
                                                        @else
                                                            <span class="hint" style="margin-top:0">---</span>
                                                        @endif
                                                    </div>
                                                    @if($cVariantName !== '')
                                                        <div class="hint" style="margin-top:0">Cấu hình: {{ $cVariantName }}</div>
                                                    @endif
                                                    @if($cNote !== '')
                                                        <button
                                                            type="button"
                                                            class="compare-note js-edit-note"
                                                            data-action="{{ route('dashboard.products.competitors.note', [$product, $site]) }}"
                                                            data-value="{{ $cNote }}"
                                                            title="Sửa note"
                                                        >{{ $cNote }}</button>
                                                    @endif
                                                </div>
                                            @else
                                                <div style="display:flex;flex-direction:column;gap:6px;padding-top:15px">
                                                    <button
                                                        type="button"
                                                        class="compare-note-button js-edit-note"
                                                        data-action="{{ route('dashboard.products.competitors.note', [$product, $site]) }}"
                                                        data-value=""
                                                        title="Thêm note cho ô chưa có link"
                                                    >---</button>
                                                    <button
                                                        type="button"
                                                        class="icon-btn icon-btn-sm js-edit-url"
                                                        data-action="{{ route('dashboard.products.competitors.upsert', [$product, $site]) }}"
                                                        data-field="url"
                                                        data-value=""
                                                        title="Thêm URL"
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            <path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.41a5 5 0 0 0 7.07 7.07L14 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>
                                        @if($lastUpdated)
                                            {{ $lastUpdated->setTimezone('Asia/Ho_Chi_Minh')->format('d/m/Y H:i') }}
                                        @else
                                            <span class="hint" style="margin-top:0">---</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right">
                                        <button
                                            type="button"
                                            class="btn js-delete-product"
                                            data-action="{{ route('dashboard.products.destroy', $product) }}"
                                            data-product-id="{{ $product->id }}"
                                        >
                                            Xoá
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 5 + $comparisonCompetitorSites->count() }}" class="hint">Chưa có dữ liệu. Hãy thêm sản phẩm trước.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div id="comparisonCardView" style="display:none">
                    @forelse($products as $idx => $product)
                        @php($own = (int) $product->price)
                        @php($map = $product->competitors->keyBy('competitor_site_id'))
                        @php($latestTimes = $product->competitors->map(fn($c) => $c->price_missing_at ? null : $c->prices->first()?->fetched_at)->filter())
                        @php($lastTime = $latestTimes->max())
                        @php($lastUpdated = collect([$lastTime, $product->last_scraped_at])->filter()->max())
                        @php($minDiff = $own > 0 ? $product->competitors->map(function ($c) use ($own) {
                            $p = $c->price_missing_at ? null : $c->prices->first()?->price;
                            if (is_null($p)) {
                                return null;
                            }

                            return (int) $p + (int) ($c->price_adjustment ?? 0) - $own;
                        })->filter(fn ($v) => ! is_null($v))->min() : null)
                        @php($missingSites = $comparisonCompetitorSites->filter(fn ($s) => ! $map->has($s->id)))

                        <div
                            class="card compare-card"
                            data-product-card="{{ $product->id }}"
                            data-row-order="{{ $rowOffset + $idx }}"
                            data-product-name="{{ $product->name }}"
                            data-product-id="{{ $product->id }}"
                            data-group-id="{{ $product->product_group_id ?? '' }}"
                            data-own-price="{{ $own }}"
                            data-last-updated="{{ $lastUpdated?->timestamp ?? 0 }}"
                            data-min-diff="{{ is_null($minDiff) ? '' : $minDiff }}"
                            style="max-width:none;border-radius:16px;margin-top:0;overflow:hidden"
                        >
                            <button
                                type="button"
                                class="compare-card-delete js-delete-product"
                                data-action="{{ route('dashboard.products.destroy', $product) }}"
                                data-product-id="{{ $product->id }}"
                                title="Xoá"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M3 6h18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M8 6V4h8v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M19 6l-1 14H6L5 6" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                    <path d="M10 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                    <path d="M14 11v6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                </svg>
                            </button>

                            <div class="compare-card-header">
                                <div class="compare-card-title">
                                    <span class="compare-card-title-full">{{ $product->name }}</span>
                                    <span class="compare-card-title-mobile">{{ \Illuminate\Support\Str::limit($product->name, 117) }}</span>
                                </div>
                                <div class="hint" style="margin-top:4px">ID: {{ $product->id }}</div>
                            </div>

                            <div class="compare-card-own-row">
                                <div class="compare-card-own-label">Giá bạn</div>
                                <a href="{{ route('products.history', $product) }}" class="compare-card-own-price">
                                    {{ $own > 0 ? number_format($own, 0, ',', '.').'đ' : 'Liên hệ' }}
                                </a>
                            </div>

                            <div>
                                <div class="compare-card-table-head">
                                    <div>Đối thủ</div>
                                    <div style="text-align:right">Giá (VNĐ)</div>
                                    <div style="text-align:right">Chênh lệch</div>
                                    <div style="text-align:right">Hành động</div>
                                </div>

                                @foreach($comparisonCompetitorSites as $site)
                                    @php($c = $map->get($site->id))
                                    @if(! $c)
                                        @continue
                                    @endif

                                    @php($cUrl = trim((string) ($c?->url ?? '')))
                                    @php($cNote = trim((string) ($c?->note ?? '')))
                                    @php($cVariantName = trim((string) ($c?->variant_name ?? '')))
                                    @php($latest = $c?->prices->first())
                                    @php($prev = $c?->prices->skip(1)->first())
                                    @php($cPrice = ($c?->price_missing_at || $cUrl === '') ? null : $latest?->price)
                                    @php($prevPrice = $prev?->price)
                                    @php($adj = (int) ($c?->price_adjustment ?? 0))
                                    @php($adjDiff = is_null($cPrice) || $own <= 0 ? null : ((int) $cPrice + $adj - $own))
                                    @php($diffSign = is_null($adjDiff) ? 'na' : ($adjDiff > 0 ? 'pos' : ($adjDiff < 0 ? 'neg' : 'zero')))
                                    @php($diffArrow = is_null($adjDiff) ? '' : ($adjDiff > 0 ? '↑' : ($adjDiff < 0 ? '↓' : '←')))

                                    <div class="compare-card-table-row">
                                        <div class="compare-card-cell-site">{{ $site->name }}</div>
                                        <div class="compare-card-cell-price">
                                            @if($cPrice)
                                                <a href="{{ route('competitors.history', $c) }}" style="color:#6b7280">
                                                    {{ number_format($cPrice, 0, ',', '.') }}
                                                </a>
                                            @else
                                                <span class="hint" style="margin-top:0">---</span>
                                            @endif
                                        </div>
                                        <div class="compare-card-cell-diff">
                                            @if($cUrl === '')
                                                <button
                                                    type="button"
                                                    class="compare-note-button js-edit-note"
                                                    data-action="{{ route('dashboard.products.competitors.note', [$product, $site]) }}"
                                                    data-value="{{ $cNote }}"
                                                    title="Thêm note cho ô chưa có link"
                                                >---</button>
                                            @elseif(is_null($adjDiff))
                                                <span class="hint" style="margin-top:0">---</span>
                                            @else
                                                <a href="{{ $cUrl }}" target="_blank" style="text-decoration:none">
                                                    <span id="adjDiffCard-{{ $c->id }}" class="compare-diff-pill compare-diff-{{ $diffSign }}" data-pill="1">
                                                        {{ $adjDiff > 0 ? '+' : ($adjDiff < 0 ? '-' : '') }}{{ number_format(abs($adjDiff), 0, ',', '.') }}
                                                        <span class="compare-diff-arrow">{{ $diffArrow }}</span>
                                                    </span>
                                                </a>
                                            @endif
                                        </div>
                                        <div class="compare-card-cell-actions">
                                            <button
                                                type="button"
                                                class="icon-btn icon-btn-sm js-edit-url"
                                                data-action="{{ route('dashboard.products.competitors.upsert', [$product, $site]) }}"
                                                data-field="url"
                                                data-value="{{ $cUrl }}"
                                                title="Sửa URL"
                                            >
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                    <path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.41a5 5 0 0 0 7.07 7.07L14 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>

                                            <button
                                                type="button"
                                                class="icon-btn icon-btn-sm js-edit-adjustment"
                                                data-action="{{ route('competitors.adjustment.update', $c) }}"
                                                data-value="{{ $adj }}"
                                                data-span-ids="adjDiff-{{ $c->id }},adjDiffCard-{{ $c->id }}"
                                                data-own="{{ $own }}"
                                                data-cprice="{{ is_null($cPrice) ? '' : (int) $cPrice }}"
                                                data-variants-url="{{ route('competitors.variants', $c) }}"
                                                data-variant-key="{{ $c?->variant_key }}"
                                                data-variant-name="{{ $cVariantName }}"
                                                title="Điều chỉnh giá (+/-)"
                                            >
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                    <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    @if($cVariantName !== '')
                                        <div class="hint" style="margin:0 16px 8px">Cấu hình: {{ $cVariantName }}</div>
                                    @endif
                                    @if($cNote !== '')
                                        <button
                                            type="button"
                                            class="compare-note js-edit-note"
                                            data-action="{{ route('dashboard.products.competitors.note', [$product, $site]) }}"
                                            data-value="{{ $cNote }}"
                                            title="Sửa note"
                                            style="margin:0 16px 10px"
                                        >{{ $cNote }}</button>
                                    @endif
                                @endforeach

                                @if($missingSites->isNotEmpty())
                                    <div style="padding:12px 16px 16px">
                                        <button type="button" class="compare-card-addlink js-add-link" data-target="addLink-{{ $product->id }}">
                                            + Thêm link
                                        </button>
                                    </div>
                                    <div id="addLink-{{ $product->id }}" style="display:none;padding:0 16px 16px">
                                        <select class="input js-add-link-select" data-target="addLink-{{ $product->id }}">
                                            <option value="">Chọn đối thủ...</option>
                                            @foreach($missingSites as $site)
                                                <option value="{{ route('dashboard.products.competitors.upsert', [$product, $site]) }}">{{ $site->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="hint">Chưa có dữ liệu. Hãy thêm sản phẩm trước.</div>
                    @endforelse
                </div>

                <div id="comparisonBottomSentinel" style="height:1px"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="comparisonFloatingPager" class="comparison-floating-pager" aria-label="Phân trang nhanh kết quả so sánh">
        <button class="btn btn-secondary" type="button" id="compareFloatingPrev">Trước</button>
        <button class="btn" type="button" id="compareFloatingNext">Sau</button>
    </div>

    @if($compareMatchEnabled ?? false)
        @php($compareMatchCounts = $compareMatchCounts ?? ['allCells' => 0, 'emptyCells' => 0, 'emptyCheckedCells' => 0, 'emptySkipRemainingCells' => 0])
        <dialog id="compareMatchDialog" class="dialog">
            <div class="dialog-header">
                <h3 class="card-title" style="font-size:18px">So khớp link đối thủ</h3>
                <p class="card-sub">Tìm ứng viên từ dữ liệu scanner rồi dùng AI đang chọn trong Admin để xác nhận sản phẩm trùng.</p>
            </div>
            <div class="dialog-body">
                <div id="compareMatchChoices" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px">
                    <button class="btn" type="button" data-compare-mode="all" style="flex-direction:column;gap:4px;min-height:58px;line-height:1.2">
                        <span>So khớp toàn bộ</span>
                        <span style="font-size:12px;font-weight:600;opacity:.86">{{ number_format((int) $compareMatchCounts['allCells'], 0, ',', '.') }} ô</span>
                    </button>
                    <button class="btn btn-secondary" type="button" data-compare-mode="empty" style="flex-direction:column;gap:4px;min-height:58px;line-height:1.2">
                        <span>So khớp ô trống</span>
                        <span style="font-size:12px;font-weight:600;color:var(--muted)">{{ number_format((int) $compareMatchCounts['emptyCells'], 0, ',', '.') }} ô trống</span>
                    </button>
                    <button class="btn btn-secondary" type="button" data-compare-mode="empty_skip_checked" style="flex-direction:column;gap:4px;min-height:58px;line-height:1.2">
                        <span>Ô trống mới, bỏ qua ô đã so khớp</span>
                        <span style="font-size:12px;font-weight:600;color:var(--muted)">Còn {{ number_format((int) $compareMatchCounts['emptySkipRemainingCells'], 0, ',', '.') }} / đã so khớp {{ number_format((int) $compareMatchCounts['emptyCheckedCells'], 0, ',', '.') }}</span>
                    </button>
                </div>
                <div id="compareMatchProgress" style="display:none;margin-top:14px">
                    <div class="hint" id="compareMatchProgressText" style="margin-top:0">Đang chuẩn bị...</div>
                    <div style="height:10px;background:#e5e7eb;border-radius:999px;overflow:hidden;margin-top:8px">
                        <div id="compareMatchProgressBar" style="height:100%;width:0%;background:#1677ff"></div>
                    </div>
                    <div id="compareMatchStats" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:10px">
                        <div style="border:1px solid var(--border);border-radius:10px;padding:10px;background:#fff">
                            <div class="label">Tiến trình</div>
                            <strong id="compareMatchPercent">0%</strong>
                        </div>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:10px;background:#fff">
                            <div class="label">Đã xử lý</div>
                            <strong id="compareMatchProcessed">0/0 ô</strong>
                        </div>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:10px;background:#fff">
                            <div class="label">Đã điền link</div>
                            <strong id="compareMatchMatched">0 link</strong>
                        </div>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:10px;background:#fff">
                            <div class="label">Dự kiến còn lại</div>
                            <strong id="compareMatchEta">Đang tính</strong>
                        </div>
                    </div>
                    <div class="hint" id="compareMatchMessage" style="margin-top:8px"></div>
                </div>
                <div class="actions" style="justify-content:flex-end;margin-top:14px">
                    <button class="btn btn-secondary" type="button" id="compareMatchCancel">Huỷ</button>
                </div>
            </div>
        </dialog>
    @endif

    <dialog id="excelImportDialog" class="dialog">
        <div class="dialog-header">
            <h3 class="card-title" style="font-size:18px">Nhập Excel</h3>
            <p class="card-sub">Cột 1 là sản phẩm của shop, cột 2 là nhóm sản phẩm, các cột sau là link đối thủ.</p>
        </div>
        <div class="dialog-body">
            <form id="excelImportForm" method="POST" action="{{ route('dashboard.import.excel') }}" enctype="multipart/form-data">
                @csrf
                <div class="field" style="margin-top:0">
                    <label class="label" for="excel_file">File Excel</label>
                    <input class="input" id="excel_file" name="excel_file" type="file" accept=".xlsx,.xls,.csv" required>
                    @error('excel_file')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="hint" id="excelImportStatus" style="display:none;margin-top:10px;font-weight:600;color:var(--accent)">Đang nhập, chờ trong chốc lát...</div>
                <div class="actions" style="justify-content:space-between;align-items:center">
                    <a class="btn btn-secondary" href="{{ route('dashboard.import.template') }}">Tải file mẫu</a>
                    <span style="display:flex;gap:10px">
                        <button class="btn btn-secondary" type="button" id="excelImportCancel">Huỷ</button>
                        <button class="btn" type="submit" id="excelImportSubmit">Nhập Excel</button>
                    </span>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="urlDialog" class="dialog">
        <div class="dialog-header">
            <h3 class="card-title" style="font-size:18px">Sửa URL</h3>
            <p class="card-sub">Dán link mới và bấm Lưu</p>
        </div>
        <div class="dialog-body">
            <form id="urlDialogForm" method="POST" action="">
                @csrf
                @method('PUT')
                <input type="hidden" id="urlDialogClear" name="clear" value="0">
                <div class="field" style="margin-top:0">
                    <label class="label" for="urlDialogInput">URL</label>
                    <input class="input" id="urlDialogInput" name="url" type="url" required placeholder="https://...">
                </div>
                <div class="actions" style="justify-content:flex-end">
                    <button class="btn btn-secondary" type="button" id="urlDialogCancel">Huỷ</button>
                    <button class="btn btn-secondary" type="button" id="urlDialogDelete">Xoá</button>
                    <button class="btn" type="submit">Lưu</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="noteDialog" class="dialog">
        <div class="dialog-header">
            <h3 class="card-title" style="font-size:18px">Note cho ô chưa có link</h3>
            <p class="card-sub">Ghi chú sẽ hiển thị ngay trong bảng Kết quả so sánh</p>
        </div>
        <div class="dialog-body">
            <form id="noteDialogForm" method="POST" action="">
                @csrf
                <div class="field" style="margin-top:0">
                    <label class="label" for="noteDialogInput">Note</label>
                    <textarea class="input" id="noteDialogInput" name="note" rows="5" maxlength="5000" placeholder="VD: Chưa tìm được link, sản phẩm hết hàng, cần kiểm tra lại..."></textarea>
                </div>
                <div class="actions" style="justify-content:flex-end">
                    <button class="btn btn-secondary" type="button" id="noteDialogCancel">Huỷ</button>
                    <button class="btn btn-secondary" type="button" id="noteDialogClear">Xoá note</button>
                    <button class="btn" type="submit">Lưu</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="adjustDialog" class="dialog">
        <div class="dialog-header">
            <h3 class="card-title" style="font-size:18px">Điều chỉnh giá</h3>
            <p class="card-sub">Nhập số + hoặc - để cân bằng cấu hình</p>
        </div>
        <div class="dialog-body">
            <form id="adjustDialogForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="field" style="margin-top:0">
                    <label class="label" for="adjustDialogInput">Giá điều chỉnh (+/-)</label>
                    <input class="input" id="adjustDialogInput" name="price_adjustment" type="text" inputmode="numeric" placeholder="+200000 hoặc -200000">
                    @error('price_adjustment')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field" id="adjustVariantField" style="display:none">
                    <label class="label" for="adjustVariantSelect">Cấu hình so sánh</label>
                    <select class="input" id="adjustVariantSelect" name="variant_key" disabled>
                        <option value="">Giá mặc định</option>
                    </select>
                    <div class="hint" id="adjustVariantHint" style="margin-top:0"></div>
                </div>
                <div class="actions" style="justify-content:flex-end">
                    <button class="btn btn-secondary" type="button" id="adjustDialogCancel">Huỷ</button>
                    <button class="btn" type="submit">Lưu</button>
                </div>
            </form>
        </div>
    </dialog>

    <dialog id="deleteDialog" class="dialog">
        <div class="dialog-header">
            <h3 class="card-title" style="font-size:18px">Xoá sản phẩm?</h3>
            <p class="card-sub">Hành động này sẽ xoá toàn bộ dữ liệu so sánh liên quan</p>
        </div>
        <div class="dialog-body">
            <div class="actions" style="justify-content:flex-end">
                <button class="btn btn-secondary" type="button" id="deleteDialogCancel">Huỷ</button>
                <button class="btn" type="button" id="deleteDialogConfirm">Xoá</button>
            </div>
        </div>
    </dialog>

    <dialog id="bulkDeleteDialog" class="dialog">
        <div class="dialog-header">
            <h3 class="card-title" style="font-size:18px">Xoá sản phẩm đang xem?</h3>
            <p class="card-sub" id="bulkDeleteDialogText">Bạn có chắc chắn xóa hết tất cả sản phẩm ở bảng đang xem?</p>
        </div>
        <div class="dialog-body">
            <p class="card-sub" id="bulkDeleteDialogCount" style="margin-top:0"></p>
            <div class="actions" style="justify-content:flex-end">
                <button class="btn btn-secondary" type="button" id="bulkDeleteCancel">Huỷ</button>
                <button class="btn" type="button" id="bulkDeleteConfirm">Xóa</button>
            </div>
        </div>
    </dialog>

    <dialog id="assignGroupDialog" class="dialog">
        <div class="dialog-header">
            <h3 class="card-title" style="font-size:18px">Thêm vào nhóm sản phẩm?</h3>
            <p class="card-sub" id="assignGroupDialogText"></p>
        </div>
        <div class="dialog-body">
            <p class="card-sub" id="assignGroupDialogCount" style="margin-top:0"></p>
            <div class="actions" style="justify-content:flex-end">
                <button class="btn btn-secondary" type="button" id="assignGroupCancel">Huỷ</button>
                <button class="btn" type="button" id="assignGroupConfirm">Thêm vào nhóm</button>
            </div>
        </div>
    </dialog>

    <script>
        (function () {
            const addProductHeader = document.getElementById('addProductHeader');
            const addProductBody = document.getElementById('addProductBody');
            const addProductChevron = document.getElementById('addProductChevron');
            const addProductKey = 'checkgia_add_product_open';

            function setAddProductOpen(open) {
                if (!addProductBody) return;
                addProductBody.style.display = open ? '' : 'none';
                if (addProductChevron) {
                    addProductChevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                try {
                    localStorage.setItem(addProductKey, open ? '1' : '0');
                } catch (e) {
                }
            }

            function getAddProductOpen() {
                try {
                    return localStorage.getItem(addProductKey) !== '0';
                } catch (e) {
                    return true;
                }
            }

            setAddProductOpen(getAddProductOpen());
            if (addProductHeader) {
                addProductHeader.addEventListener('click', () => {
                    const open = addProductBody && addProductBody.style.display !== 'none';
                    setAddProductOpen(!open);
                });
            }

            const priceFeedHeader = document.getElementById('priceFeedHeader');
            const priceFeedBody = document.getElementById('priceFeedBody');
            const priceFeedChevron = document.getElementById('priceFeedChevron');
            const priceFeedKey = 'checkgia_price_feed_open';

            function setPriceFeedOpen(open) {
                if (!priceFeedBody) return;
                priceFeedBody.style.display = open ? '' : 'none';
                if (priceFeedChevron) {
                    priceFeedChevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
                }
                try {
                    localStorage.setItem(priceFeedKey, open ? '1' : '0');
                } catch (e) {
                }
            }

            function getPriceFeedOpen() {
                try {
                    return localStorage.getItem(priceFeedKey) !== '0';
                } catch (e) {
                    return true;
                }
            }

            setPriceFeedOpen(getPriceFeedOpen());
            if (priceFeedHeader) {
                priceFeedHeader.addEventListener('click', () => {
                    const open = priceFeedBody && priceFeedBody.style.display !== 'none';
                    setPriceFeedOpen(!open);
                });
            }

            const dialog = document.getElementById('urlDialog');
            const form = document.getElementById('urlDialogForm');
            const input = document.getElementById('urlDialogInput');
            const cancel = document.getElementById('urlDialogCancel');
            const del = document.getElementById('urlDialogDelete');
            const clear = document.getElementById('urlDialogClear');
            const openButtons = document.querySelectorAll('.js-edit-url');
            const noteDialog = document.getElementById('noteDialog');
            const noteForm = document.getElementById('noteDialogForm');
            const noteInput = document.getElementById('noteDialogInput');
            const noteCancel = document.getElementById('noteDialogCancel');
            const noteClear = document.getElementById('noteDialogClear');
            const noteButtons = document.querySelectorAll('.js-edit-note');
            const comparisonScrapeNowForm = document.getElementById('comparisonScrapeNowForm');
            const comparisonRestoreKey = 'checkgia_comparison_restore_target';
            let activeUrlTrigger = null;
            let activeNoteTrigger = null;
            let restoringComparisonPosition = false;

            function productIdFromComparisonElement(el) {
                const holder = el?.closest?.('[data-product-row], [data-product-card]');
                return holder?.dataset?.productRow || holder?.dataset?.productCard || holder?.dataset?.productId || '';
            }

            function comparisonTableScrollLeft() {
                return Number(document.getElementById('comparisonTableView')?.scrollLeft || 0);
            }

            function visibleComparisonElement() {
                const mode = compareViewToggle?.dataset?.mode || (isMobileView() ? 'cards' : getStoredCompareView());
                const selector = mode === 'cards' ? '[data-product-card]' : '[data-product-row]';
                const candidates = Array.from(document.querySelectorAll(selector));
                if (!candidates.length) return null;

                const topLine = 120;
                let best = candidates[0];
                let bestDistance = Number.POSITIVE_INFINITY;
                candidates.forEach((el) => {
                    const rect = el.getBoundingClientRect();
                    const distance = Math.abs(rect.top - topLine);
                    if (rect.bottom >= topLine && distance < bestDistance) {
                        best = el;
                        bestDistance = distance;
                    }
                });

                return best || null;
            }

            function productIdFromComparisonHolder(holder) {
                return holder?.dataset?.productRow || holder?.dataset?.productCard || holder?.dataset?.productId || '';
            }

            function comparisonAnchorPayload(holder) {
                if (!holder) {
                    return {
                        productId: '',
                        anchorOffset: null,
                    };
                }

                const top = holder.getBoundingClientRect().top + window.scrollY;

                return {
                    productId: productIdFromComparisonHolder(holder),
                    anchorOffset: Math.round((window.scrollY || window.pageYOffset || 0) - top),
                };
            }

            function saveComparisonReturnTarget(el, reason = 'action') {
                const anchor = comparisonAnchorPayload(el?.closest?.('[data-product-row], [data-product-card]'));
                const payload = {
                    productId: anchor.productId || productIdFromComparisonElement(el),
                    anchorOffset: anchor.anchorOffset,
                    scrollY: Math.max(0, Math.round(window.scrollY || window.pageYOffset || 0)),
                    tableScrollLeft: comparisonTableScrollLeft(),
                    viewMode: compareViewToggle?.dataset?.mode || '',
                    reason,
                    path: window.location.pathname,
                    savedAt: Date.now(),
                };

                try {
                    localStorage.setItem(comparisonRestoreKey, JSON.stringify(payload));
                } catch (e) {
                }
            }

            function saveComparisonViewport(reason = 'viewport') {
                if (restoringComparisonPosition) {
                    return;
                }
                const anchor = comparisonAnchorPayload(visibleComparisonElement());
                const payload = {
                    productId: anchor.productId,
                    anchorOffset: anchor.anchorOffset,
                    scrollY: Math.max(0, Math.round(window.scrollY || window.pageYOffset || 0)),
                    tableScrollLeft: comparisonTableScrollLeft(),
                    viewMode: compareViewToggle?.dataset?.mode || '',
                    reason,
                    path: window.location.pathname,
                    savedAt: Date.now(),
                };

                try {
                    localStorage.setItem(comparisonRestoreKey, JSON.stringify(payload));
                } catch (e) {
                }
            }

            function comparisonTargetElement(productId) {
                if (!productId) return null;
                const mode = compareViewToggle?.dataset?.mode || (isMobileView() ? 'cards' : getStoredCompareView());
                const primary = mode === 'cards'
                    ? `[data-product-card="${productId}"]`
                    : `[data-product-row="${productId}"]`;
                const fallback = mode === 'cards'
                    ? `[data-product-row="${productId}"]`
                    : `[data-product-card="${productId}"]`;

                return document.querySelector(primary) || document.querySelector(fallback);
            }

            function restoreComparisonReturnTarget() {
                let payload = null;
                try {
                    const raw = localStorage.getItem(comparisonRestoreKey);
                    if (raw) payload = JSON.parse(raw);
                    localStorage.removeItem(comparisonRestoreKey);
                } catch (e) {
                    payload = null;
                }
                if (!payload || payload.path !== window.location.pathname || Date.now() - Number(payload.savedAt || 0) > 120000) {
                    return;
                }

                const restore = () => {
                    restoringComparisonPosition = true;
                    const table = document.getElementById('comparisonTableView');
                    if (table && Number.isFinite(Number(payload.tableScrollLeft))) {
                        table.scrollLeft = Number(payload.tableScrollLeft || 0);
                    }

                    const target = comparisonTargetElement(String(payload.productId || ''));
                    if (target) {
                        const offset = Number.isFinite(Number(payload.anchorOffset))
                            ? Number(payload.anchorOffset)
                            : -118;
                        const top = Math.max(0, target.getBoundingClientRect().top + window.scrollY + offset);
                        window.scrollTo({top, behavior: 'auto'});
                        target.classList.add('compare-restore-highlight');
                        window.setTimeout(() => target.classList.remove('compare-restore-highlight'), 1800);
                        window.setTimeout(() => {
                            if (table && Number.isFinite(Number(payload.tableScrollLeft))) {
                                table.scrollLeft = Number(payload.tableScrollLeft || 0);
                            }
                            restoringComparisonPosition = false;
                        }, 80);
                        return;
                    }

                    if (Number.isFinite(Number(payload.scrollY))) {
                        window.scrollTo({top: Math.max(0, Number(payload.scrollY || 0)), behavior: 'auto'});
                    }
                    window.setTimeout(() => {
                        restoringComparisonPosition = false;
                    }, 80);
                };

                window.requestAnimationFrame(() => window.requestAnimationFrame(restore));
            }

            function showDialog(el) {
                if (!el) return false;
                if (typeof el.showModal === 'function') {
                    el.showModal();
                    return true;
                }
                el.setAttribute('open', '');
                return true;
            }

            function closeDialog(el) {
                if (!el) return false;
                if (typeof el.close === 'function') {
                    el.close();
                    return true;
                }
                el.removeAttribute('open');
                return true;
            }

            function open(action, value, fieldName, trigger = null) {
                activeUrlTrigger = trigger;
                form.action = action;
                input.value = value || '';
                input.name = fieldName || 'url';
                input.required = true;
                if (clear) clear.value = '0';
                showDialog(dialog);
                input.focus();
            }

            function openNote(action, value, trigger = null) {
                if (!noteForm || !noteInput) return;
                activeNoteTrigger = trigger;
                noteForm.action = action || '';
                noteInput.value = value || '';
                showDialog(noteDialog);
                noteInput.focus();
            }

            openButtons.forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    open(btn.dataset.action, btn.dataset.value, btn.dataset.field, btn);
                });
            });

            noteButtons.forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    openNote(btn.dataset.action, btn.dataset.value, btn);
                });
            });

            if (form) {
                form.addEventListener('submit', () => saveComparisonReturnTarget(activeUrlTrigger, 'url'));
            }
            if (noteForm) {
                noteForm.addEventListener('submit', () => saveComparisonReturnTarget(activeNoteTrigger, 'note'));
            }
            if (comparisonScrapeNowForm) {
                comparisonScrapeNowForm.addEventListener('submit', () => saveComparisonViewport('scrape-now'));
            }
            if ('scrollRestoration' in history) {
                history.scrollRestoration = 'manual';
            }

            let comparisonViewportSaveTimer = null;
            function scheduleComparisonViewportSave(reason = 'scroll') {
                window.clearTimeout(comparisonViewportSaveTimer);
                comparisonViewportSaveTimer = window.setTimeout(() => saveComparisonViewport(reason), 180);
            }

            function bindComparisonPositionTracking(root = document) {
                const table = root.querySelector?.('#comparisonTableView') || document.getElementById('comparisonTableView');
                if (table && table.dataset.positionTrackingBound !== '1') {
                    table.dataset.positionTrackingBound = '1';
                    table.addEventListener('scroll', () => scheduleComparisonViewportSave('table-scroll'), {passive: true});
                }
            }

            window.addEventListener('scroll', () => scheduleComparisonViewportSave('window-scroll'), {passive: true});
            window.addEventListener('beforeunload', () => saveComparisonViewport('beforeunload'));
            window.addEventListener('pagehide', () => saveComparisonViewport('pagehide'));
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'hidden') {
                    saveComparisonViewport('visibility-hidden');
                }
            });
            bindComparisonPositionTracking();

            cancel.addEventListener('click', () => closeDialog(dialog));
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) closeDialog(dialog);
            });

            if (noteCancel) {
                noteCancel.addEventListener('click', () => closeDialog(noteDialog));
            }
            if (noteDialog) {
                noteDialog.addEventListener('click', (e) => {
                    if (e.target === noteDialog) closeDialog(noteDialog);
                });
            }
            if (noteClear && noteForm && noteInput) {
                noteClear.addEventListener('click', () => {
                    noteInput.value = '';
                    noteForm.requestSubmit();
                });
            }

            if (del) {
                del.addEventListener('click', () => {
                    if (clear) clear.value = '1';
                    input.required = false;
                    input.value = '';
                    form.requestSubmit();
                });
            }

            const adjustDialog = document.getElementById('adjustDialog');
            const adjustForm = document.getElementById('adjustDialogForm');
            const adjustInput = document.getElementById('adjustDialogInput');
            const adjustVariantField = document.getElementById('adjustVariantField');
            const adjustVariantSelect = document.getElementById('adjustVariantSelect');
            const adjustVariantHint = document.getElementById('adjustVariantHint');
            const adjustCancel = document.getElementById('adjustDialogCancel');
            const adjustButtons = document.querySelectorAll('.js-edit-adjustment');
            const csrfToken = '{{ csrf_token() }}';
            let lastAdjustButton = null;

            const compareMatchOpen = document.getElementById('compareMatchOpen');
            const compareMatchDialog = document.getElementById('compareMatchDialog');
            const compareMatchCancel = document.getElementById('compareMatchCancel');
            const compareMatchChoices = document.getElementById('compareMatchChoices');
            const compareMatchProgress = document.getElementById('compareMatchProgress');
            const compareMatchProgressText = document.getElementById('compareMatchProgressText');
            const compareMatchProgressBar = document.getElementById('compareMatchProgressBar');
            const compareMatchPercent = document.getElementById('compareMatchPercent');
            const compareMatchProcessed = document.getElementById('compareMatchProcessed');
            const compareMatchMatched = document.getElementById('compareMatchMatched');
            const compareMatchEta = document.getElementById('compareMatchEta');
            const compareMatchMessage = document.getElementById('compareMatchMessage');
            const compareMatchRunUrl = '{{ route('dashboard.compare-match.run') }}';
            const compareMatchTickUrlTemplate = '{{ route('dashboard.compare-match.tick', ['compareMatchRun' => '__RUN_ID__']) }}';
            let compareMatchRunning = false;

            function updateCompareProgress(run, fallbackMessage) {
                const total = Number(run?.totalCells || 0);
                const processed = Number(run?.processedCells || 0);
                const remaining = Math.max(0, Number(run?.remainingCells || 0));
                const matched = Number(run?.matchedLinks ?? run?.matched ?? 0);
                const percent = Math.max(0, Math.min(100, Number(run?.percent ?? (total > 0 ? Math.floor((processed / total) * 100) : 0))));
                const etaText = run?.etaText || (processed > 0 ? 'Đang tính' : 'Chưa đủ dữ liệu');
                if (compareMatchProgressText) {
                    compareMatchProgressText.textContent = total > 0
                        ? `Đang so khớp ${processed}/${total} ô (${percent}%), còn ${remaining} ô.`
                        : (fallbackMessage || 'Đang chuẩn bị...');
                }
                if (compareMatchProgressBar) {
                    compareMatchProgressBar.style.width = `${percent}%`;
                }
                if (compareMatchPercent) {
                    compareMatchPercent.textContent = `${percent}%`;
                }
                if (compareMatchProcessed) {
                    compareMatchProcessed.textContent = `${processed}/${total} ô`;
                }
                if (compareMatchMatched) {
                    compareMatchMatched.textContent = `${matched} link`;
                }
                if (compareMatchEta) {
                    compareMatchEta.textContent = etaText;
                }
                if (compareMatchMessage) {
                    compareMatchMessage.textContent = run?.message || fallbackMessage || '';
                }
            }

            async function postCompareJson(url, body) {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify(body || {}),
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.ok === false) {
                    throw new Error(payload.message || 'Không chạy được so khớp.');
                }

                return payload;
            }

            async function tickCompareMatch(runId) {
                const url = compareMatchTickUrlTemplate.replace('__RUN_ID__', String(runId));
                while (compareMatchRunning) {
                    const payload = await postCompareJson(url);
                    updateCompareProgress(payload.run);
                    if (['done', 'failed'].includes(String(payload.run?.status || ''))) {
                        compareMatchRunning = false;
                        if (payload.run?.status === 'done') {
                            setTimeout(() => {
                                saveComparisonViewport('compare-match');
                                window.location.reload();
                            }, 900);
                        }
                        return;
                    }
                    await new Promise((resolve) => setTimeout(resolve, 250));
                }
            }

            async function startCompareMatch(mode) {
                compareMatchRunning = true;
                if (compareMatchChoices) compareMatchChoices.style.display = 'none';
                if (compareMatchProgress) compareMatchProgress.style.display = '';
                updateCompareProgress(null, 'Đang tạo tiến trình so khớp...');
                try {
                    const payload = await postCompareJson(compareMatchRunUrl, {mode});
                    updateCompareProgress(payload.run);
                    if (payload.run?.id && !['done', 'failed'].includes(String(payload.run?.status || ''))) {
                        await tickCompareMatch(payload.run.id);
                    } else {
                        compareMatchRunning = false;
                    }
                } catch (error) {
                    compareMatchRunning = false;
                    if (compareMatchMessage) {
                        compareMatchMessage.textContent = error instanceof Error ? error.message : 'Không chạy được so khớp.';
                    }
                }
            }

            if (compareMatchOpen && compareMatchDialog) {
                compareMatchOpen.addEventListener('click', () => {
                    compareMatchRunning = false;
                    if (compareMatchChoices) compareMatchChoices.style.display = 'grid';
                    if (compareMatchProgress) compareMatchProgress.style.display = 'none';
                    updateCompareProgress(null, '');
                    showDialog(compareMatchDialog);
                });
            }

            document.querySelectorAll('[data-compare-mode]').forEach((button) => {
                button.addEventListener('click', () => startCompareMatch(button.dataset.compareMode || 'empty'));
            });

            if (compareMatchCancel && compareMatchDialog) {
                compareMatchCancel.addEventListener('click', () => {
                    compareMatchRunning = false;
                    closeDialog(compareMatchDialog);
                });
            }

            function resetAdjustVariants(message = '') {
                if (!adjustVariantField || !adjustVariantSelect || !adjustVariantHint) return;
                adjustVariantSelect.innerHTML = '<option value="">Giá mặc định</option>';
                adjustVariantSelect.disabled = true;
                adjustVariantField.style.display = 'none';
                adjustVariantHint.textContent = message;
            }

            async function loadAdjustVariants(btn) {
                if (!adjustVariantField || !adjustVariantSelect || !adjustVariantHint) return;
                const url = btn.dataset.variantsUrl || '';
                if (!url) {
                    resetAdjustVariants();
                    return;
                }

                adjustVariantField.style.display = '';
                adjustVariantSelect.disabled = true;
                adjustVariantHint.textContent = 'Đang kiểm tra cấu hình...';
                adjustVariantSelect.innerHTML = '<option value="">Giá mặc định</option>';

                try {
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'include',
                    });
                    const data = await res.json().catch(() => ({}));
                    const variants = Array.isArray(data.variants) ? data.variants : [];
                    if (!res.ok || variants.length === 0) {
                        resetAdjustVariants();
                        return;
                    }

                    variants.forEach((variant) => {
                        const option = document.createElement('option');
                        option.value = String(variant.key || '');
                        option.textContent = `${variant.name || 'Cấu hình'} - ${variant.price_text || ''}`.trim();
                        adjustVariantSelect.appendChild(option);
                    });
                    adjustVariantSelect.value = String(btn.dataset.variantKey || data.selected_key || '');
                    adjustVariantSelect.disabled = false;
                    adjustVariantField.style.display = '';
                    adjustVariantHint.textContent = 'Chọn cấu hình cụ thể để lấy đúng giá biến thể cho ô đối thủ này.';
                } catch (error) {
                    resetAdjustVariants();
                }
            }

            function openAdjust(btn) {
                adjustForm.action = btn.dataset.action || '';
                adjustInput.value = btn.dataset.value || '0';
                resetAdjustVariants();
                if (typeof adjustDialog.showModal === 'function') {
                    adjustDialog.showModal();
                }
                loadAdjustVariants(btn);
                adjustInput.focus();
                adjustInput.select();
            }

            function formatAdjustmentValue(value) {
                const trimmed = String(value ?? '').trim();
                const sign = trimmed.startsWith('-') ? '-' : trimmed.startsWith('+') ? '+' : '';
                const digits = trimmed.replace(/[^\d]/g, '');
                if (!digits) {
                    return sign;
                }

                const formattedDigits = digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

                return sign + formattedDigits;
            }

            function setCaretByDigitIndex(input, digitIndex) {
                const value = input.value;
                const hasSign = value.startsWith('-') || value.startsWith('+');
                const start = hasSign ? 1 : 0;
                let digitsSeen = 0;
                let pos = start;

                while (pos < value.length && digitsSeen < digitIndex) {
                    if (/\d/.test(value[pos])) {
                        digitsSeen += 1;
                    }
                    pos += 1;
                }

                input.setSelectionRange(pos, pos);
            }

            function formatAdjustmentInput(input) {
                const oldValue = input.value;
                const caret = input.selectionStart ?? oldValue.length;
                const prefix = oldValue.slice(0, caret);
                const digitIndex = (prefix.replace(/[^\d]/g, '')).length;

                const nextValue = formatAdjustmentValue(oldValue);
                if (nextValue === oldValue) {
                    return;
                }

                input.value = nextValue;
                setCaretByDigitIndex(input, digitIndex);
            }

            adjustButtons.forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    lastAdjustButton = btn;
                    openAdjust(btn);
                });
            });

            if (adjustCancel) {
                adjustCancel.addEventListener('click', () => adjustDialog.close());
            }
            if (adjustDialog) {
                adjustDialog.addEventListener('click', (e) => {
                    if (e.target === adjustDialog) adjustDialog.close();
                });
            }

            function formatVndSigned(n) {
                const sign = n > 0 ? '+' : n < 0 ? '-' : '';
                return sign + Math.abs(n).toLocaleString('vi-VN') + 'đ';
            }

            function formatNumberSigned(n) {
                const sign = n > 0 ? '+' : n < 0 ? '-' : '';
                return sign + Math.abs(n).toLocaleString('vi-VN');
            }

            function diffMeta(n) {
                if (n > 0) return { cls: 'compare-diff-pos', arrow: '↑' };
                if (n < 0) return { cls: 'compare-diff-neg', arrow: '↓' };
                return { cls: 'compare-diff-zero', arrow: '←' };
            }

            if (adjustInput) {
                adjustInput.addEventListener('input', () => {
                    formatAdjustmentInput(adjustInput);
                });
            }

            if (adjustForm) {
                adjustForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const action = adjustForm.action;
                    if (!action) {
                        return;
                    }

                    const body = new FormData(adjustForm);

                    try {
                        const res = await fetch(action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'include',
                            body,
                        });

                        const contentType = res.headers.get('content-type') || '';
                        const data = contentType.includes('application/json') ? await res.json().catch(() => null) : null;

                        if (!res.ok) {
                            alert((data && (data.message || data.error)) ? (data.message || data.error) : `Không lưu được (HTTP ${res.status}).`);
                            return;
                        }

                        if (typeof adjustDialog.close === 'function') {
                            adjustDialog.close();
                        }

                        if (data && data.reload) {
                            saveComparisonReturnTarget(lastAdjustButton, 'adjustment-variant');
                            window.location.reload();
                            return;
                        }

                        const adj = Number(data && data.price_adjustment !== undefined ? data.price_adjustment : Number(adjustInput.value || 0));
                        if (lastAdjustButton) {
                            lastAdjustButton.dataset.value = String(adj);
                            if (data && Object.prototype.hasOwnProperty.call(data, 'variant_key')) {
                                lastAdjustButton.dataset.variantKey = data.variant_key || '';
                                lastAdjustButton.dataset.variantName = data.variant_name || '';
                            }

                            const spanIdsRaw = lastAdjustButton.dataset.spanIds || lastAdjustButton.dataset.spanId || '';
                            const spanIds = spanIdsRaw.split(',').map((s) => s.trim()).filter(Boolean);
                            const spans = spanIds.map((id) => document.getElementById(id)).filter(Boolean);
                            const cPrice = Number(lastAdjustButton.dataset.cprice || 0);
                            const own = Number(lastAdjustButton.dataset.own || 0);

                            if (spans.length) {
                                const baseDiff = cPrice - own;
                                const effectiveDiff = cPrice + adj - own;

                                spans.forEach((span) => {
                                    if (span.dataset.pill === '1') {
                                        const d = adj ? effectiveDiff : baseDiff;
                                        const meta = diffMeta(d);
                                        span.classList.remove('compare-diff-pos', 'compare-diff-neg', 'compare-diff-zero');
                                        span.classList.add(meta.cls);
                                        span.innerHTML = `${formatNumberSigned(d)} <span class="compare-diff-arrow">${meta.arrow}</span>`;
                                        span.style.display = 'inline-flex';
                                        return;
                                    }

                                    if (!adj) {
                                        span.style.display = 'none';
                                        return;
                                    }

                                    span.textContent = formatVndSigned(effectiveDiff);
                                    span.style.display = 'inline';
                                    span.style.color = effectiveDiff > 0 ? '#166534' : (effectiveDiff < 0 ? '#991b1b' : '#111827');
                                });
                            } else {
                                saveComparisonReturnTarget(lastAdjustButton, 'adjustment-fallback');
                                window.location.reload();
                            }
                        } else {
                            saveComparisonViewport('adjustment-fallback');
                            window.location.reload();
                        }
                    } catch (err) {
                        alert('Không lưu được. Vui lòng thử lại.');
                    }
                });
            }

            const deleteDialog = document.getElementById('deleteDialog');
            const deleteCancel = document.getElementById('deleteDialogCancel');
            const deleteConfirm = document.getElementById('deleteDialogConfirm');
            const deleteButtons = document.querySelectorAll('.js-delete-product');
            const csrf = '{{ csrf_token() }}';
            let pendingDelete = null;

            function openDelete(action, productId) {
                pendingDelete = { action, productId };
                if (typeof deleteDialog.showModal === 'function') {
                    deleteDialog.showModal();
                }
            }

            deleteButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    openDelete(btn.dataset.action, btn.dataset.productId);
                });
            });

            deleteCancel.addEventListener('click', () => {
                pendingDelete = null;
                deleteDialog.close();
            });

            deleteDialog.addEventListener('click', (e) => {
                if (e.target === deleteDialog) {
                    pendingDelete = null;
                    deleteDialog.close();
                }
            });

            deleteConfirm.addEventListener('click', async () => {
                if (!pendingDelete) return;
                deleteConfirm.disabled = true;

                try {
                    const res = await fetch(pendingDelete.action, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'include',
                    });

                    const contentType = res.headers.get('content-type') || '';
                    const data = contentType.includes('application/json') ? await res.json().catch(() => null) : null;

                    if (!res.ok) {
                        if (res.status === 403) {
                            alert('Bạn không có quyền xoá.');
                            return;
                        }
                        if (res.status === 419) {
                            alert('Phiên đăng nhập đã hết hạn. Vui lòng tải lại trang và thử lại.');
                            return;
                        }
                        if (data && data.message) {
                            alert(data.message);
                            return;
                        }
                        if (!contentType.includes('application/json') && (res.redirected || (res.url && res.url.includes('/login')))) {
                            window.location.href = res.url;
                            return;
                        }
                        alert(`Xoá thất bại (HTTP ${res.status}).`);
                        return;
                    }

                    if (data && data.ok === false) {
                        alert('Xoá thất bại.');
                        return;
                    }

                    const row = document.querySelector(`[data-product-row="${pendingDelete.productId}"]`);
                    if (row) row.remove();
                    const card = document.querySelector(`[data-product-card="${pendingDelete.productId}"]`);
                    if (card) card.remove();
                    applyFiltersAndSort(false);
                } catch (e) {
                    alert('Xoá thất bại. Vui lòng thử lại.');
                } finally {
                    deleteConfirm.disabled = false;
                    pendingDelete = null;
                    deleteDialog.close();
                }
            });

            const bulkDeleteOpen = document.getElementById('bulkDeleteOpen');
            const bulkDeleteDialog = document.getElementById('bulkDeleteDialog');
            const bulkDeleteCancel = document.getElementById('bulkDeleteCancel');
            const bulkDeleteConfirm = document.getElementById('bulkDeleteConfirm');
            const bulkDeleteDialogText = document.getElementById('bulkDeleteDialogText');
            const bulkDeleteDialogCount = document.getElementById('bulkDeleteDialogCount');

            function bulkDeleteQueryParams() {
                const params = new URLSearchParams();
                const q = (filterSearch?.value || '').trim();
                const group = filterGroup?.value || '';
                if (q) params.set('q', q);
                if (group) params.set('group', group);

                return params;
            }

            function openBulkDeleteDialog() {
                if (!bulkDeleteDialog || !bulkDeleteOpen) return;
                const meta = comparisonMetaFromDom();
                const count = Number(meta.total || 0);
                if (count <= 0) {
                    alert('Không có sản phẩm nào trong bảng đang xem để xóa.');
                    return;
                }

                const countText = count.toLocaleString('vi-VN');
                if (bulkDeleteDialogText) {
                    bulkDeleteDialogText.textContent = `Bạn có chắc chắn xóa hết tất cả sản phẩm ở bảng đang xem?`;
                }
                if (bulkDeleteDialogCount) {
                    bulkDeleteDialogCount.textContent = `Số sản phẩm sẽ xóa: ${countText}`;
                }
                if (typeof bulkDeleteDialog.showModal === 'function') {
                    bulkDeleteDialog.showModal();
                }
            }

            if (bulkDeleteOpen) {
                bulkDeleteOpen.addEventListener('click', openBulkDeleteDialog);
            }
            if (bulkDeleteCancel && bulkDeleteDialog) {
                bulkDeleteCancel.addEventListener('click', () => bulkDeleteDialog.close());
                bulkDeleteDialog.addEventListener('click', (e) => {
                    if (e.target === bulkDeleteDialog) {
                        bulkDeleteDialog.close();
                    }
                });
            }
            if (bulkDeleteConfirm && bulkDeleteOpen) {
                bulkDeleteConfirm.addEventListener('click', async () => {
                    bulkDeleteConfirm.disabled = true;
                    const params = bulkDeleteQueryParams();
                    const action = bulkDeleteOpen.dataset.action + (params.toString() ? `?${params.toString()}` : '');

                    try {
                        const res = await fetch(action, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'include',
                        });
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok || data.ok === false) {
                            alert(data.message || `Xóa thất bại (HTTP ${res.status}).`);
                            return;
                        }

                        if (bulkDeleteDialog) {
                            bulkDeleteDialog.close();
                        }
                        await loadComparisonPage(1);
                    } catch (e) {
                        alert('Xóa thất bại. Vui lòng thử lại.');
                    } finally {
                        bulkDeleteConfirm.disabled = false;
                    }
                });
            }

            const compareViewToggle = document.getElementById('compareViewToggle');
            const comparisonTableView = document.getElementById('comparisonTableView');
            const comparisonCardView = document.getElementById('comparisonCardView');
            const compareCardColumnsWrap = document.getElementById('compareCardColumnsWrap');
            const compareCardColumns = document.getElementById('compareCardColumns');
            const compareViewKey = 'checkgia_compare_view';
            const compareCardColumnsKey = 'checkgia_compare_card_columns';

            function isMobileView() {
                return window.matchMedia('(max-width: 768px)').matches;
            }

            function getStoredCompareView() {
                try {
                    const v = localStorage.getItem(compareViewKey);
                    return v === 'cards' || v === 'table' ? v : 'table';
                } catch (e) {
                    return 'table';
                }
            }

            function getStoredCardColumns() {
                try {
                    const v = localStorage.getItem(compareCardColumnsKey);
                    return v === '1' || v === '2' ? v : '2';
                } catch (e) {
                    return '2';
                }
            }

            function applyCardColumns() {
                const cardView = document.getElementById('comparisonCardView');
                if (!cardView) {
                    return;
                }

                const columns = isMobileView() ? '1' : getStoredCardColumns();
                cardView.style.gridTemplateColumns = columns === '2'
                    ? 'repeat(2,minmax(0,1fr))'
                    : '1fr';

                if (compareCardColumns && compareCardColumns.value !== columns) {
                    compareCardColumns.value = columns;
                }
            }

            function setCompareView(mode, persist) {
                const nextMode = mode === 'cards' ? 'cards' : 'table';
                const tableView = document.getElementById('comparisonTableView');
                const cardView = document.getElementById('comparisonCardView');

                if (cardView) {
                    cardView.style.display = nextMode === 'cards' ? 'grid' : 'none';
                }
                if (tableView) {
                    tableView.style.display = nextMode === 'cards' ? 'none' : '';
                }

                if (compareViewToggle) {
                    compareViewToggle.dataset.mode = nextMode;
                    compareViewToggle.textContent = nextMode === 'cards' ? 'Dạng bảng' : 'Dạng thẻ';
                    compareViewToggle.style.display = isMobileView() ? 'none' : '';
                }
                if (compareCardColumnsWrap) {
                    compareCardColumnsWrap.classList.toggle('is-visible', nextMode === 'cards');
                }
                applyCardColumns();

                if (persist) {
                    try {
                        localStorage.setItem(compareViewKey, nextMode);
                    } catch (e) {
                    }
                }
            }

            let lastMobile = isMobileView();
            setCompareView(lastMobile ? 'cards' : getStoredCompareView(), false);

            if (compareViewToggle) {
                compareViewToggle.addEventListener('click', () => {
                    const current = compareViewToggle.dataset.mode || 'table';
                    setCompareView(current === 'cards' ? 'table' : 'cards', true);
                });
            }
            if (compareCardColumns) {
                compareCardColumns.value = getStoredCardColumns();
                compareCardColumns.addEventListener('change', () => {
                    const value = compareCardColumns.value === '1' ? '1' : '2';
                    try {
                        localStorage.setItem(compareCardColumnsKey, value);
                    } catch (e) {
                    }
                    applyCardColumns();
                });
            }

            window.addEventListener('resize', () => {
                const nowMobile = isMobileView();
                if (nowMobile !== lastMobile) {
                    setCompareView(nowMobile ? 'cards' : getStoredCompareView(), false);
                    lastMobile = nowMobile;
                }
                applyCardColumns();
            });

            document.querySelectorAll('.js-add-link').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const targetId = btn.dataset.target || '';
                    const target = targetId ? document.getElementById(targetId) : null;
                    if (!target) return;
                    target.style.display = target.style.display === 'none' ? '' : 'none';
                    const select = target.querySelector('select');
                    if (select) select.focus();
                });
            });

            document.querySelectorAll('.js-add-link-select').forEach((select) => {
                select.addEventListener('change', () => {
                    const action = select.value || '';
                    if (!action) return;
                    open(action, '', 'url', select);
                    const wrapId = select.dataset.target || '';
                    const wrap = wrapId ? document.getElementById(wrapId) : null;
                    if (wrap) wrap.style.display = 'none';
                    select.value = '';
                });
            });

            function bindDynamicComparisonControls(root) {
                const scope = root || document;
                scope.querySelectorAll('.js-edit-url').forEach((btn) => {
                    if (btn.dataset.dynamicBound === '1') return;
                    btn.dataset.dynamicBound = '1';
                    btn.addEventListener('click', (e) => {
                        if (e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        open(btn.dataset.action, btn.dataset.value, btn.dataset.field, btn);
                    });
                });
                scope.querySelectorAll('.js-edit-note').forEach((btn) => {
                    if (btn.dataset.dynamicBound === '1') return;
                    btn.dataset.dynamicBound = '1';
                    btn.addEventListener('click', (e) => {
                        if (e) {
                            e.preventDefault();
                            e.stopPropagation();
                        }
                        openNote(btn.dataset.action, btn.dataset.value, btn);
                    });
                });
                scope.querySelectorAll('.js-edit-adjustment').forEach((btn) => {
                    if (btn.dataset.dynamicBound === '1') return;
                    btn.dataset.dynamicBound = '1';
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        lastAdjustButton = btn;
                        openAdjust(btn);
                    });
                });
                scope.querySelectorAll('.js-delete-product').forEach((btn) => {
                    if (btn.dataset.dynamicBound === '1') return;
                    btn.dataset.dynamicBound = '1';
                    btn.addEventListener('click', () => {
                        openDelete(btn.dataset.action, btn.dataset.productId);
                    });
                });
                scope.querySelectorAll('.js-add-link').forEach((btn) => {
                    if (btn.dataset.dynamicBound === '1') return;
                    btn.dataset.dynamicBound = '1';
                    btn.addEventListener('click', () => {
                        const targetId = btn.dataset.target || '';
                        const target = targetId ? document.getElementById(targetId) : null;
                        if (!target) return;
                        target.style.display = target.style.display === 'none' ? '' : 'none';
                        const select = target.querySelector('select');
                        if (select) select.focus();
                    });
                });
                scope.querySelectorAll('.js-add-link-select').forEach((select) => {
                    if (select.dataset.dynamicBound === '1') return;
                    select.dataset.dynamicBound = '1';
                    select.addEventListener('change', () => {
                        const action = select.value || '';
                        if (!action) return;
                        open(action, '', 'url', select);
                        const wrapId = select.dataset.target || '';
                        const wrap = wrapId ? document.getElementById(wrapId) : null;
                        if (wrap) wrap.style.display = 'none';
                        select.value = '';
                    });
                });
            }

            const filterSearch = document.getElementById('filterSearch');
            const filterGroup = document.getElementById('filterGroup');
            const filterCompetitorGroup = document.getElementById('filterCompetitorGroup');
            const filterGroupPicker = document.getElementById('filterGroupPicker');
            const filterGroupTrigger = document.getElementById('filterGroupTrigger');
            const filterGroupLabel = document.getElementById('filterGroupLabel');
            const filterGroupMenu = document.getElementById('filterGroupMenu');
            const assignGroupDialog = document.getElementById('assignGroupDialog');
            const assignGroupDialogText = document.getElementById('assignGroupDialogText');
            const assignGroupDialogCount = document.getElementById('assignGroupDialogCount');
            const assignGroupCancel = document.getElementById('assignGroupCancel');
            const assignGroupConfirm = document.getElementById('assignGroupConfirm');
            const sortSelect = document.getElementById('sortSelect');
            const excelImportOpen = document.getElementById('excelImportOpen');
            const excelImportDialog = document.getElementById('excelImportDialog');
            const excelImportCancel = document.getElementById('excelImportCancel');
            const excelImportForm = document.getElementById('excelImportForm');
            const excelImportSubmit = document.getElementById('excelImportSubmit');
            const excelImportStatus = document.getElementById('excelImportStatus');
            const filterReset = document.getElementById('filterReset');
            const exportAll = document.getElementById('exportAll');
            const exportGroup = document.getElementById('exportGroup');
            const productsTotalPill = document.getElementById('productsTotalPill');
            const tbody = document.querySelector('table.table tbody');
            const comparisonPagination = document.getElementById('comparisonPagination');
            const comparePerPage = document.getElementById('comparePerPage');
            const comparePageSummary = document.getElementById('comparePageSummary');
            const comparePageJump = document.getElementById('comparePageJump');
            const comparePageButtons = document.getElementById('comparePageButtons');
            let comparisonBottomSentinel = document.getElementById('comparisonBottomSentinel');
            const comparisonFloatingPager = document.getElementById('comparisonFloatingPager');
            const compareFloatingPrev = document.getElementById('compareFloatingPrev');
            const compareFloatingNext = document.getElementById('compareFloatingNext');
            const comparePerPageKey = 'checkgia_compare_per_page';
            const comparisonFetchUrl = '{{ route('dashboard') }}';
            const exportBaseUrl = '{{ route('dashboard.export.products') }}';
            let compareCurrentPage = 1;
            let comparisonBottomVisible = false;
            let compareLastPageCount = 0;
            let comparisonRequest = null;
            let floatingPagerObserver = null;
            let pendingAssignGroup = null;

            function normalizeSelectSelection(select) {
                if (select && select.selectedIndex < 0 && select.options.length) {
                    select.selectedIndex = 0;
                }
            }

            function syncGroupFilterPicker() {
                if (!filterGroup || !filterGroupLabel) return;
                normalizeSelectSelection(filterGroup);
                const selected = filterGroup.options[filterGroup.selectedIndex];
                filterGroupLabel.textContent = selected ? selected.textContent : 'Tất cả';
                document.querySelectorAll('.group-filter-option').forEach((button) => {
                    button.classList.toggle('is-active', String(button.dataset.groupFilterValue || '') === String(filterGroup.value || ''));
                });
            }

            function setGroupFilterValue(value) {
                if (!filterGroup) return;
                filterGroup.value = value;
                syncGroupFilterPicker();
                filterGroup.dispatchEvent(new Event('change', {bubbles: true}));
            }

            function closeGroupFilterMenu() {
                if (!filterGroupMenu || !filterGroupTrigger) return;
                filterGroupMenu.hidden = true;
                filterGroupTrigger.setAttribute('aria-expanded', 'false');
            }

            function openAssignGroupDialog(button) {
                if (!assignGroupDialog) return;
                const meta = comparisonMetaFromDom();
                const count = Number(meta.total || 0);
                if (count <= 0) {
                    alert('Không có sản phẩm nào trong bảng đang xem để thêm vào nhóm.');
                    return;
                }

                pendingAssignGroup = {
                    action: button.dataset.action || '',
                    name: button.dataset.groupName || '',
                };
                const countText = count.toLocaleString('vi-VN');
                if (assignGroupDialogText) {
                    assignGroupDialogText.textContent = `Bạn muốn thêm ${countText} sản phẩm đang hiển thị ở bảng kết quả so sánh hiện tại vào nhóm "${pendingAssignGroup.name}" này không?`;
                }
                if (assignGroupDialogCount) {
                    assignGroupDialogCount.textContent = 'Chỉ áp dụng cho các sản phẩm đang khớp tìm kiếm và bộ lọc nhóm hiện tại.';
                }
                closeGroupFilterMenu();
                if (typeof assignGroupDialog.showModal === 'function') {
                    assignGroupDialog.showModal();
                }
            }

            if (filterGroupTrigger && filterGroupMenu) {
                filterGroupTrigger.addEventListener('click', () => {
                    const nextHidden = !filterGroupMenu.hidden;
                    filterGroupMenu.hidden = nextHidden;
                    filterGroupTrigger.setAttribute('aria-expanded', nextHidden ? 'false' : 'true');
                });
                document.addEventListener('click', (event) => {
                    if (filterGroupPicker && !filterGroupPicker.contains(event.target)) {
                        closeGroupFilterMenu();
                    }
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        closeGroupFilterMenu();
                    }
                });
            }
            document.querySelectorAll('.group-filter-option').forEach((button) => {
                button.addEventListener('click', () => {
                    setGroupFilterValue(button.dataset.groupFilterValue || '');
                    closeGroupFilterMenu();
                });
            });
            document.querySelectorAll('.group-filter-add').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    openAssignGroupDialog(button);
                });
            });
            if (assignGroupCancel && assignGroupDialog) {
                assignGroupCancel.addEventListener('click', () => assignGroupDialog.close());
                assignGroupDialog.addEventListener('click', (event) => {
                    if (event.target === assignGroupDialog) {
                        assignGroupDialog.close();
                    }
                });
            }
            if (assignGroupConfirm) {
                assignGroupConfirm.addEventListener('click', async () => {
                    if (!pendingAssignGroup?.action) return;
                    assignGroupConfirm.disabled = true;
                    const params = bulkDeleteQueryParams();
                    const action = pendingAssignGroup.action + (params.toString() ? `?${params.toString()}` : '');

                    try {
                        const response = await fetch(action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'include',
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || data.ok === false) {
                            alert(data.message || `Thêm vào nhóm thất bại (HTTP ${response.status}).`);
                            return;
                        }

                        assignGroupDialog?.close();
                        pendingAssignGroup = null;
                        await loadComparisonPage(1);
                    } catch (e) {
                        alert('Thêm vào nhóm thất bại. Vui lòng thử lại.');
                    } finally {
                        assignGroupConfirm.disabled = false;
                    }
                });
            }
            if (excelImportOpen && excelImportDialog) {
                excelImportOpen.addEventListener('click', () => {
                    if (typeof excelImportDialog.showModal === 'function') {
                        excelImportDialog.showModal();
                    }
                });
                excelImportDialog.addEventListener('click', (event) => {
                    if (event.target === excelImportDialog) {
                        excelImportDialog.close();
                    }
                });
            }
            if (excelImportCancel && excelImportDialog) {
                excelImportCancel.addEventListener('click', () => excelImportDialog.close());
            }
            if (excelImportForm) {
                excelImportForm.addEventListener('submit', () => {
                    if (excelImportSubmit) {
                        excelImportSubmit.disabled = true;
                        excelImportSubmit.textContent = 'Đang nhập...';
                    }
                    if (excelImportCancel) {
                        excelImportCancel.disabled = true;
                    }
                    if (excelImportStatus) {
                        excelImportStatus.style.display = '';
                    }
                });
            }
            @if($errors->has('excel_file'))
                if (excelImportDialog && typeof excelImportDialog.showModal === 'function') {
                    excelImportDialog.showModal();
                }
            @endif
            syncGroupFilterPicker();

            function parseNum(v) {
                if (v === null || v === undefined) return null;
                const s = String(v).trim();
                if (!s) return null;
                const n = Number(s);
                return Number.isFinite(n) ? n : null;
            }

            function getStoredPerPage() {
                try {
                    const value = localStorage.getItem(comparePerPageKey);
                    return ['20', '50', '100', '200', '500'].includes(value) ? value : '50';
                } catch (e) {
                    return '50';
                }
            }

            if (comparePerPage) {
                comparePerPage.value = getStoredPerPage();
            }

            function getComparePerPageValue() {
                const value = comparePerPage?.value || '50';
                const parsed = Number(value);
                return Number.isFinite(parsed) && parsed > 0 ? parsed : 50;
            }

            function itemMatchesFilter(el, q, group) {
                const name = (el.dataset.productName || '').toLowerCase();
                const id = String(el.dataset.productId || '');
                const groupId = String(el.dataset.groupId || '');

                if (q && !name.includes(q) && !id.includes(q)) {
                    return false;
                }
                if (group) {
                    return group === '__none__' ? !groupId : groupId === group;
                }

                return true;
            }

            function compareDashboardItems(a, b, sort) {
                const aRow = parseNum(a.dataset.rowOrder) ?? 0;
                const bRow = parseNum(b.dataset.rowOrder) ?? 0;
                const aLast = parseNum(a.dataset.lastUpdated) ?? 0;
                const bLast = parseNum(b.dataset.lastUpdated) ?? 0;
                const aPrice = parseNum(a.dataset.ownPrice) ?? 0;
                const bPrice = parseNum(b.dataset.ownPrice) ?? 0;
                const aDiff = parseNum(a.dataset.minDiff);
                const bDiff = parseNum(b.dataset.minDiff);
                const aDiffVal = aDiff === null ? Number.POSITIVE_INFINITY : aDiff;
                const bDiffVal = bDiff === null ? Number.POSITIVE_INFINITY : bDiff;

                if (sort === 'row_asc') return aRow - bRow;
                if (sort === 'name_asc') return String(a.dataset.productName || '').localeCompare(String(b.dataset.productName || ''), 'vi', {sensitivity: 'base'}) || (bRow - aRow);
                if (sort === 'last_desc') return bLast - aLast;
                if (sort === 'last_asc') return aLast - bLast;
                if (sort === 'price_asc') return aPrice - bPrice;
                if (sort === 'price_desc') return bPrice - aPrice;
                if (sort === 'diff_asc') return aDiffVal - bDiffVal;
                if (sort === 'diff_desc') return bDiffVal - aDiffVal;

                return aRow - bRow;
            }

            function filteredSortedItems(items, q, group, sort) {
                return items
                    .filter((el) => itemMatchesFilter(el, q, group))
                    .sort((a, b) => compareDashboardItems(a, b, sort));
            }

            function pageInfoFor(total) {
                const perPage = getComparePerPageValue();
                const pageCount = total > 0
                    ? (perPage === Number.POSITIVE_INFINITY ? 1 : Math.max(1, Math.ceil(total / perPage)))
                    : 0;
                compareCurrentPage = pageCount > 0 ? Math.min(Math.max(1, compareCurrentPage), pageCount) : 1;

                const start = pageCount > 0 && perPage !== Number.POSITIVE_INFINITY
                    ? (compareCurrentPage - 1) * perPage
                    : 0;
                const end = pageCount > 0 && perPage !== Number.POSITIVE_INFINITY
                    ? Math.min(total, start + perPage)
                    : total;

                return {
                    pageCount,
                    start,
                    end,
                    shown: Math.max(0, end - start),
                };
            }

            function currentComparisonResults() {
                return document.getElementById('comparisonResults');
            }

            function comparisonMetaFromDom() {
                const node = currentComparisonResults();
                const total = Number(node?.dataset.total || 0);
                const pageCount = Math.max(0, Number(node?.dataset.pageCount || 0));
                return {
                    page: Math.max(1, Number(node?.dataset.currentPage || 1)),
                    pageCount,
                    perPage: Math.max(1, Number(node?.dataset.perPage || getComparePerPageValue())),
                    total,
                    shown: Math.max(0, Number(node?.dataset.shown || 0)),
                };
            }

            function renderComparisonFromCurrentResults() {
                const meta = comparisonMetaFromDom();
                compareCurrentPage = meta.page;
                compareLastPageCount = meta.pageCount;
                if (comparePerPage && String(comparePerPage.value || '') !== String(meta.perPage)) {
                    comparePerPage.value = String(meta.perPage);
                }
                renderComparePagination(meta.total, {pageCount: meta.pageCount, shown: meta.shown});
                setCompareView(isMobileView() ? 'cards' : getStoredCompareView(), false);
            }

            function comparisonQueryParams(page) {
                const params = new URLSearchParams();
                const q = (filterSearch?.value || '').trim();
                const group = filterGroup?.value || '';
                const competitorGroup = filterCompetitorGroup?.value || '';
                const sort = sortSelect?.value || 'row_asc';
                const perPage = comparePerPage?.value || '50';
                if (q) params.set('q', q);
                if (group) params.set('group', group);
                if (competitorGroup) params.set('competitor_group', competitorGroup);
                if (sort && sort !== 'row_asc') params.set('sort', sort);
                params.set('per_page', perPage);
                params.set('page', String(Math.max(1, Number(page) || 1)));

                return params;
            }

            async function loadComparisonPage(page) {
                const params = comparisonQueryParams(page);
                const url = `${comparisonFetchUrl}?${params.toString()}`;
                const current = currentComparisonResults();
                if (current) {
                    current.style.opacity = '.55';
                    current.style.pointerEvents = 'none';
                }
                if (comparisonRequest) {
                    comparisonRequest.abort();
                }
                comparisonRequest = new AbortController();

                try {
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'text/html',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'include',
                        signal: comparisonRequest.signal,
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const nextResults = doc.getElementById('comparisonResults');
                    if (!nextResults) {
                        throw new Error('Missing comparison results');
                    }

                    const nextPill = doc.getElementById('productsTotalPill');
                    if (productsTotalPill && nextPill) {
                        productsTotalPill.textContent = nextPill.textContent;
                    }

                    const activeMode = compareViewToggle?.dataset.mode || (isMobileView() ? 'cards' : getStoredCompareView());
                    currentComparisonResults()?.replaceWith(nextResults);
                    setupFloatingPagerObserver();
                    bindComparisonPositionTracking(nextResults);
                    bindDynamicComparisonControls(nextResults);
                    renderComparisonFromCurrentResults();
                    setCompareView(activeMode, false);
                    history.replaceState(null, '', url);
                } catch (error) {
                    if (!(error instanceof DOMException && error.name === 'AbortError')) {
                        alert('Không tải được phân trang. Vui lòng thử lại.');
                    }
                } finally {
                    const node = currentComparisonResults();
                    if (node) {
                        node.style.opacity = '';
                        node.style.pointerEvents = '';
                    }
                    comparisonRequest = null;
                }
            }

            function renderComparePagination(total, info) {
                if (!comparisonPagination || !comparePageSummary || !comparePageButtons) {
                    return;
                }

                const pageCount = info.pageCount;
                compareLastPageCount = pageCount;
                const pageText = total > 0 ? compareCurrentPage : 0;
                comparePageSummary.textContent = `Trang ${pageText}/${pageCount} • Hiển thị ${info.shown}/${total}`;

                if (comparePageJump) {
                    comparePageJump.disabled = pageCount <= 1;
                    comparePageJump.max = String(Math.max(1, pageCount));
                    comparePageJump.value = total > 0 ? String(compareCurrentPage) : '';
                }

                comparePageButtons.innerHTML = '';

                const addButton = (label, page, disabled, active) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'comparison-page-btn' + (active ? ' is-active' : '');
                    button.textContent = label;
                    button.disabled = !!disabled;
                    button.addEventListener('click', () => goToComparePage(page));
                    comparePageButtons.appendChild(button);
                };
                const addDots = () => {
                    const dots = document.createElement('span');
                    dots.className = 'comparison-page-ellipsis';
                    dots.textContent = '.....';
                    comparePageButtons.appendChild(dots);
                };

                addButton('Trước', compareCurrentPage - 1, pageCount <= 1 || compareCurrentPage <= 1, false);

                if (pageCount > 0) {
                    const firstBlockEnd = Math.min(4, pageCount);
                    for (let page = 1; page <= firstBlockEnd; page += 1) {
                        addButton(String(page), page, false, page === compareCurrentPage);
                    }
                    if (pageCount > 5) {
                        addDots();
                        addButton(String(pageCount), pageCount, false, pageCount === compareCurrentPage);
                    } else {
                        for (let page = firstBlockEnd + 1; page <= pageCount; page += 1) {
                            addButton(String(page), page, false, page === compareCurrentPage);
                        }
                    }
                }

                addButton('Sau', compareCurrentPage + 1, pageCount <= 1 || compareCurrentPage >= pageCount, false);
                updateFloatingPager(pageCount);
            }

            function updateFloatingPager(pageCount) {
                if (!comparisonFloatingPager || !compareFloatingPrev || !compareFloatingNext) {
                    return;
                }

                const hasPages = Number(pageCount || 0) > 1;
                comparisonFloatingPager.classList.toggle('is-visible', comparisonBottomVisible && hasPages);
                compareFloatingPrev.disabled = !hasPages || compareCurrentPage <= 1;
                compareFloatingNext.disabled = !hasPages || compareCurrentPage >= pageCount;
            }

            function applyPageVisibility(items, appendTo, visibleItems, q, group, sort, info) {
                const pageItems = visibleItems.slice(info.start, info.end);
                const visibleIds = new Set(pageItems.map((el) => String(el.dataset.productId || '')));

                if (appendTo) {
                    pageItems.forEach((el) => appendTo.appendChild(el));
                }

                items.forEach((el) => {
                    el.style.display = visibleIds.has(String(el.dataset.productId || '')) ? '' : 'none';
                });
            }

            function applyFiltersAndSort(resetPage = false) {
                return loadComparisonPage(resetPage ? 1 : compareCurrentPage);
            }

            function goToComparePage(page) {
                const target = Math.min(Math.max(1, Number(page) || 1), Math.max(1, compareLastPageCount || 1));
                if (target === compareCurrentPage) {
                    return;
                }
                compareCurrentPage = target;
                return loadComparisonPage(compareCurrentPage);
            }

            let comparisonSearchTimer = null;
            if (filterSearch) {
                filterSearch.addEventListener('input', () => {
                    clearTimeout(comparisonSearchTimer);
                    comparisonSearchTimer = setTimeout(() => applyFiltersAndSort(true), 250);
                });
            }
            if (filterGroup) filterGroup.addEventListener('change', () => {
                syncGroupFilterPicker();
                applyFiltersAndSort(true);
            });
            if (filterCompetitorGroup) filterCompetitorGroup.addEventListener('change', () => applyFiltersAndSort(true));
            if (sortSelect) sortSelect.addEventListener('change', () => applyFiltersAndSort(true));
            if (comparePerPage) {
                comparePerPage.addEventListener('change', () => {
                    try {
                        localStorage.setItem(comparePerPageKey, comparePerPage.value || '50');
                    } catch (e) {
                    }
                    applyFiltersAndSort(true);
                });
            }
            if (comparePageJump) {
                comparePageJump.addEventListener('change', () => goToComparePage(comparePageJump.value));
                comparePageJump.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        goToComparePage(comparePageJump.value);
                    }
                });
            }
            if (compareFloatingPrev) {
                compareFloatingPrev.addEventListener('click', () => goToComparePage(compareCurrentPage - 1));
            }
            if (compareFloatingNext) {
                compareFloatingNext.addEventListener('click', () => goToComparePage(compareCurrentPage + 1));
            }
            function currentComparisonBottomSentinel() {
                return document.getElementById('comparisonBottomSentinel');
            }
            function refreshFloatingPagerVisibility() {
                comparisonBottomSentinel = currentComparisonBottomSentinel();
                if (!comparisonBottomSentinel) {
                    comparisonBottomVisible = false;
                    updateFloatingPager(compareLastPageCount);
                    return;
                }

                const rect = comparisonBottomSentinel.getBoundingClientRect();
                comparisonBottomVisible = rect.top <= window.innerHeight && rect.bottom >= 0;
                updateFloatingPager(compareLastPageCount);
            }
            function setupFloatingPagerObserver() {
                comparisonBottomSentinel = currentComparisonBottomSentinel();
                if (floatingPagerObserver) {
                    floatingPagerObserver.disconnect();
                    floatingPagerObserver = null;
                }
                if (comparisonBottomSentinel && 'IntersectionObserver' in window) {
                    floatingPagerObserver = new IntersectionObserver((entries) => {
                        comparisonBottomVisible = entries.some((entry) => entry.isIntersecting);
                        updateFloatingPager(compareLastPageCount);
                    }, {threshold: 0});
                    floatingPagerObserver.observe(comparisonBottomSentinel);
                }
                refreshFloatingPagerVisibility();
            }
            if (!('IntersectionObserver' in window)) {
                window.addEventListener('scroll', refreshFloatingPagerVisibility, {passive: true});
                window.addEventListener('resize', refreshFloatingPagerVisibility);
            }
            setupFloatingPagerObserver();
            if (filterReset) {
                filterReset.addEventListener('click', () => {
                    if (filterSearch) filterSearch.value = '';
                    if (filterGroup) {
                        filterGroup.value = '';
                        normalizeSelectSelection(filterGroup);
                    }
                    if (filterCompetitorGroup) {
                        filterCompetitorGroup.value = '';
                        normalizeSelectSelection(filterCompetitorGroup);
                    }
                    syncGroupFilterPicker();
                    syncExportLinks();
                    if (sortSelect) sortSelect.value = 'row_asc';
                    applyFiltersAndSort(true);
                });
            }

            function syncExportLinks() {
                if (!exportAll || !exportGroup || !filterGroup) return;
                const group = filterGroup.value || '';
                const competitorGroup = filterCompetitorGroup?.value || '';
                const baseParams = new URLSearchParams();
                if (competitorGroup) baseParams.set('competitor_group_id', competitorGroup);
                exportAll.href = baseParams.toString() ? `${exportBaseUrl}?${baseParams.toString()}` : exportBaseUrl;
                if (!group) {
                    exportGroup.style.display = 'none';
                    exportGroup.href = exportAll.href;
                    return;
                }
                const groupParams = new URLSearchParams(baseParams);
                groupParams.set('group_id', group);
                exportGroup.style.display = '';
                exportGroup.href = `${exportBaseUrl}?${groupParams.toString()}`;
            }

            if (filterGroup) filterGroup.addEventListener('change', syncExportLinks);
            if (filterCompetitorGroup) filterCompetitorGroup.addEventListener('change', syncExportLinks);
            syncExportLinks();
            renderComparisonFromCurrentResults();
            restoreComparisonReturnTarget();
        })();
    </script>
@endsection
