@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1500px">
        <div class="card" id="addProductCard" style="max-width:none;margin-bottom:16px">
            <div id="addProductHeader" style="display:flex;justify-content:space-between;gap:12px;align-items:center;cursor:pointer;user-select:none;padding:16px 16px 6px">
                <div>
                    <h1 class="card-title">Nhập link sản phẩm</h1>
                    <p class="card-sub">Thêm nhanh sản phẩm và link đối thủ để so sánh</p>
                </div>
                <div style="display:flex;gap:10px;align-items:center">
                    <button type="button" class="icon-btn" id="dashboardTourStart" title="Hướng dẫn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M9.09 9a3 3 0 1 1 4.83 2.36c-.76.57-1.42 1.07-1.42 2.14V14" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M12 22c5.52 0 10-4.48 10-10S17.52 2 12 2 2 6.48 2 12s4.48 10 10 10z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
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
                        <div id="competitorUrlGrid" style="margin-top:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                            @foreach($competitorSites as $site)
                                <div class="field" style="margin-top:0">
                                    <label class="label">Link {{ $site->name }}</label>
                                    <input class="input" name="competitor_urls[{{ $site->id }}]" type="url" value="{{ old('competitor_urls.'.$site->id) }}" placeholder="https://..." data-tour="competitor-url">
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="hint">Chưa có đối thủ nào. Hãy bấm “Cài đặt” để tạo cột so sánh.</div>
                    @endif

                    <div class="field">
                        <label class="label">Nhóm sản phẩm (tuỳ chọn)</label>
                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:end">
                            <div class="field" style="margin-top:0">
                                <label class="label" for="product_group_id">Chọn nhóm</label>
                                <select class="input" id="product_group_id" name="product_group_id">
                                    <option value="">-- Chưa chọn --</option>
                                    @foreach($productGroups as $g)
                                        <option value="{{ $g->id }}" @selected((string) old('product_group_id') === (string) $g->id)>{{ $g->name }}</option>
                                    @endforeach
                                </select>
                                @error('product_group_id')<div class="error">{{ $message }}</div>@enderror
                            </div>
                            <div class="field" style="margin-top:0">
                                <label class="label" for="product_group_name">Tạo nhóm mới</label>
                                <input class="input" id="product_group_name" name="product_group_name" type="text" value="{{ old('product_group_name') }}" placeholder="VD: Laptop Gaming" autocomplete="off">
                                @error('product_group_name')<div class="error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn" type="submit" id="addProductSubmit">Thêm vào danh sách</button>
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

        <div class="card" id="comparisonCard" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h2 class="card-title">Kết quả so sánh</h2>
                    <p class="card-sub">Giá chênh = Giá đối thủ - Giá của bạn</p>
                </div>
                <div class="pill">Tổng sản phẩm: {{ $products->count() }}</div>
            </div>
            <div class="card-body">
                <div id="dashboardToolbar" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:12px">
                    <div class="field" style="margin-top:0;min-width:240px;flex:1">
                        <label class="label" for="filterSearch">Tìm kiếm</label>
                        <input class="input" id="filterSearch" type="text" placeholder="Nhập tên sản phẩm hoặc ID...">
                    </div>
                    <div class="field" style="margin-top:0;min-width:220px">
                        <label class="label" for="filterGroup">Nhóm</label>
                        <select class="input" id="filterGroup">
                            <option value="">Tất cả</option>
                            <option value="__none__">Chưa có nhóm</option>
                            @foreach($productGroups as $g)
                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="margin-top:0;min-width:240px">
                        <label class="label" for="sortSelect">Sắp xếp</label>
                        <select class="input" id="sortSelect">
                            <option value="row_asc">Số thứ tự</option>
                            <option value="last_desc">Cập nhật gần nhất</option>
                            <option value="last_asc">Cập nhật cũ nhất</option>
                            <option value="price_asc">Giá của bạn: thấp → cao</option>
                            <option value="price_desc">Giá của bạn: cao → thấp</option>
                            <option value="diff_asc">Chênh lệch: rẻ hơn nhiều nhất</option>
                            <option value="diff_desc">Chênh lệch: đắt hơn nhiều nhất</option>
                        </select>
                    </div>
                    <div class="actions" style="margin-top:0">
                        <a class="btn btn-secondary" id="exportAll" href="{{ route('dashboard.export.products') }}">Xuất Excel</a>
                        <a class="btn btn-secondary" id="exportGroup" href="{{ route('dashboard.export.products') }}">Xuất theo nhóm</a>
                        @if(!auth()->user()->isViewer())
                            <form method="POST" action="{{ route('dashboard.scrape.now') }}" style="display:inline">
                                @csrf
                                <button class="btn btn-secondary" type="submit">Cập nhật</button>
                            </form>
                        @endif
                        <button class="btn btn-secondary" type="button" id="compareViewToggle" style="display:none">Dạng thẻ</button>
                        <button class="btn btn-secondary" type="button" id="filterReset">Reset</button>
                    </div>
                </div>
                <div class="table-wrap" id="comparisonTableView">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:52px">#</th>
                                <th style="min-width:340px">Tên sản phẩm</th>
                                <th style="min-width:150px">Giá của bạn</th>
                                @foreach($competitorSites as $site)
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
                                @php($latestTimes = $product->competitors->map(fn($c) => $c->prices->first()?->fetched_at)->filter())
                                @php($lastTime = $latestTimes->max())
                                @php($lastUpdated = collect([$lastTime, $product->last_scraped_at])->filter()->max())
                                @php($minDiff = $product->competitors->map(function ($c) use ($own) {
                                    $p = $c->prices->first()?->price;
                                    if (is_null($p)) {
                                        return null;
                                    }

                                    return (int) $p + (int) ($c->price_adjustment ?? 0) - $own;
                                })->filter(fn ($v) => ! is_null($v))->min())
                                <tr
                                    data-product-row="{{ $product->id }}"
                                    data-row-order="{{ $idx }}"
                                    data-product-name="{{ $product->name }}"
                                    data-product-id="{{ $product->id }}"
                                    data-group-id="{{ $product->product_group_id ?? '' }}"
                                    data-own-price="{{ $own }}"
                                    data-last-updated="{{ $lastUpdated?->timestamp ?? 0 }}"
                                    data-min-diff="{{ is_null($minDiff) ? '' : $minDiff }}"
                                >
                                    <td>{{ $idx+1 }}</td>
                                    <td>
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
                                    <td>
                                        <div style="display:flex;flex-direction:column;gap:4px;padding-top:0px">
                                            <div style="display:flex;align-items:center;gap:8px">
                                                    @if(!auth()->user()->isViewer())
                                                        <button
                                                            type="button"
                                                            class="icon-btn icon-btn-sm js-edit-url"
                                                            data-action="{{ route('dashboard.products.url.update', $product) }}"
                                                            data-field="product_url"
                                                            data-value="{{ $product->product_url }}"
                                                            data-tour="edit-own-url"
                                                            title="Sửa link sản phẩm của bạn"
                                                        >
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.41a5 5 0 0 0 7.07 7.07L14 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                @if($product->product_url)
                                                    <a href="{{ $product->product_url }}" target="_blank" style="font-size:13px" data-tour="open-own-link">link sản phẩm</a>
                                                @else
                                                    <span class="hint" style="margin-top:0">chưa có link</span>
                                                @endif
                                            </div>
                                            <div style="font-weight:600">
                                                <a href="{{ route('products.history', $product) }}" style="color:#111827">
                                                    {{ number_format($own, 0, ',', '.') }}đ
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    @foreach($competitorSites as $site)
                                        @php($c = $map->get($site->id))
                                        @php($latest = $c?->prices->first())
                                        @php($prev = $c?->prices->skip(1)->first())
                                        @php($cPrice = $latest?->price)
                                        @php($prevPrice = $prev?->price)
                                        @php($diff = is_null($cPrice) ? null : ((int) $cPrice - $own))
                                        @php($adj = (int) ($c?->price_adjustment ?? 0))
                                        @php($adjDiff = is_null($cPrice) ? null : ((int) $cPrice + $adj - $own))
                                        @php($delta = (! is_null($cPrice) && ! is_null($prevPrice)) ? ((int) $cPrice - (int) $prevPrice) : null)
                                        <td>
                                            @if($c)
                                                <div style="display:flex;flex-direction:column;gap:4px;padding-top:6px">
                                                    <div style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px">
                                                        @if(is_null($diff))
                                                            <span class="hint" style="margin-top:0">---</span>
                                                        @elseif($diff === 0)
                                                            <a href="{{ $c->url }}" target="_blank" style="color:#6b7280" data-tour="open-competitor-link">không chênh</a>
                                                        @elseif($diff > 0)
                                                            <a href="{{ $c->url }}" target="_blank" style="color:var(--success)" data-tour="open-competitor-link">+{{ number_format($diff, 0, ',', '.') }}đ</a>
                                                        @else
                                                            <a href="{{ $c->url }}" target="_blank" style="color:var(--danger)" data-tour="open-competitor-link">{{ number_format($diff, 0, ',', '.') }}đ</a>
                                                        @endif

                                                        @if(! is_null($adjDiff))
                                                            @php($adjColor = $adjDiff > 0 ? '#166534' : ($adjDiff < 0 ? '#991b1b' : '#111827'))
                                                            <a href="{{ $c->url }}" target="_blank" id="adjDiff-{{ $c->id }}" style="display:{{ $adj !== 0 ? 'inline' : 'none' }};font-weight:800;color:{{ $adjColor }}">
                                                                @if($adjDiff > 0)
                                                                    +{{ number_format($adjDiff, 0, ',', '.') }}đ
                                                                @elseif($adjDiff < 0)
                                                                    {{ number_format($adjDiff, 0, ',', '.') }}đ
                                                                @else
                                                                    0đ
                                                                @endif
                                                            </a>
                                                            @if(!auth()->user()->isViewer())
                                                                <button
                                                                    type="button"
                                                                    class="icon-btn icon-btn-sm js-edit-adjustment"
                                                                    data-action="{{ route('competitors.adjustment.update', $c) }}"
                                                                    data-value="{{ $adj }}"
                                                                    data-span-id="adjDiff-{{ $c->id }}"
                                                                    data-span-ids="adjDiff-{{ $c->id }},adjDiffCard-{{ $c->id }}"
                                                                    data-own="{{ $own }}"
                                                                    data-cprice="{{ is_null($cPrice) ? '' : (int) $cPrice }}"
                                                                    data-tour="price-adjustment"
                                                                    title="Điều chỉnh giá (+/-)"
                                                                >
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                        <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                                    </svg>
                                                                </button>
                                                            @endif
                                                        @endif
                                                    </div>
                                                    <div style="display:flex;align-items:center;gap:8px">
                                                        @if(!auth()->user()->isViewer())
                                                            <button type="button"
                                                                    class="icon-btn icon-btn-sm js-edit-url"
                                                                    data-action="{{ route('dashboard.products.competitors.upsert', [$product, $site]) }}"
                                                                    data-field="url"
                                                                    data-value="{{ $c->url }}"
                                                                    data-tour="edit-competitor-url"
                                                                    title="Sửa URL">
                                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                    <path d="M10 13a5 5 0 0 0 7.07 0l1.41-1.41a5 5 0 0 0-7.07-7.07L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    <path d="M14 11a5 5 0 0 0-7.07 0L5.52 12.41a5 5 0 0 0 7.07 7.07L14 20" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                            </button>
                                                        @endif
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
                                                </div>
                                            @else
                                                <div style="display:flex;flex-direction:column;gap:6px;padding-top:15px">
                                                    <span class="hint" style="margin-top:0">---</span>
                                                    @if(!auth()->user()->isViewer())
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
                                                    @endif
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
                                        @if(!auth()->user()->isViewer())
                                            <button
                                                type="button"
                                                class="btn js-delete-product"
                                                data-action="{{ route('dashboard.products.destroy', $product) }}"
                                                data-product-id="{{ $product->id }}"
                                            >
                                                Xoá
                                            </button>
                                        @else
                                            <span class="hint" style="margin-top:0">---</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ 5 + $competitorSites->count() }}" class="hint">Chưa có dữ liệu. Hãy thêm sản phẩm trước.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div id="comparisonCardView" style="display:none;flex-direction:column;gap:12px;width:100%">
                    @forelse($products as $idx => $product)
                        @php($own = (int) $product->price)
                        @php($map = $product->competitors->keyBy('competitor_site_id'))
                        @php($latestTimes = $product->competitors->map(fn($c) => $c->prices->first()?->fetched_at)->filter())
                        @php($lastTime = $latestTimes->max())
                        @php($lastUpdated = collect([$lastTime, $product->last_scraped_at])->filter()->max())
                        @php($minDiff = $product->competitors->map(function ($c) use ($own) {
                            $p = $c->prices->first()?->price;
                            if (is_null($p)) {
                                return null;
                            }

                            return (int) $p + (int) ($c->price_adjustment ?? 0) - $own;
                        })->filter(fn ($v) => ! is_null($v))->min())
                        @php($missingSites = $competitorSites->filter(fn ($s) => ! $map->has($s->id)))

                        <div
                            class="card compare-card"
                            data-product-card="{{ $product->id }}"
                            data-row-order="{{ $idx }}"
                            data-product-name="{{ $product->name }}"
                            data-product-id="{{ $product->id }}"
                            data-group-id="{{ $product->product_group_id ?? '' }}"
                            data-own-price="{{ $own }}"
                            data-last-updated="{{ $lastUpdated?->timestamp ?? 0 }}"
                            data-min-diff="{{ is_null($minDiff) ? '' : $minDiff }}"
                            style="max-width:none;border-radius:16px;margin-top:0;overflow:hidden"
                        >
                            @if(!auth()->user()->isViewer())
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
                            @endif

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
                                    {{ number_format($own, 0, ',', '.') }}đ
                                </a>
                            </div>

                            <div>
                                <div class="compare-card-table-head">
                                    <div>Đối thủ</div>
                                    <div style="text-align:right">Giá (VNĐ)</div>
                                    <div style="text-align:right">Chênh lệch</div>
                                    <div style="text-align:right">Hành động</div>
                                </div>

                                @foreach($competitorSites as $site)
                                    @php($c = $map->get($site->id))
                                    @if(! $c)
                                        @continue
                                    @endif

                                    @php($latest = $c?->prices->first())
                                    @php($prev = $c?->prices->skip(1)->first())
                                    @php($cPrice = $latest?->price)
                                    @php($prevPrice = $prev?->price)
                                    @php($adj = (int) ($c?->price_adjustment ?? 0))
                                    @php($adjDiff = is_null($cPrice) ? null : ((int) $cPrice + $adj - $own))
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
                                            @if(is_null($adjDiff))
                                                <span class="hint" style="margin-top:0">---</span>
                                            @else
                                                <a href="{{ $c->url }}" target="_blank" style="text-decoration:none">
                                                    <span id="adjDiffCard-{{ $c->id }}" class="compare-diff-pill compare-diff-{{ $diffSign }}" data-pill="1">
                                                        {{ $adjDiff > 0 ? '+' : ($adjDiff < 0 ? '-' : '') }}{{ number_format(abs($adjDiff), 0, ',', '.') }}
                                                        <span class="compare-diff-arrow">{{ $diffArrow }}</span>
                                                    </span>
                                                </a>
                                            @endif
                                        </div>
                                        <div class="compare-card-cell-actions">
                                            @if(!auth()->user()->isViewer())
                                                <button
                                                    type="button"
                                                    class="icon-btn icon-btn-sm js-edit-url"
                                                    data-action="{{ route('dashboard.products.competitors.upsert', [$product, $site]) }}"
                                                    data-field="url"
                                                    data-value="{{ $c->url }}"
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
                                                    title="Điều chỉnh giá (+/-)"
                                                >
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                    </svg>
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                @if($missingSites->isNotEmpty() && !auth()->user()->isViewer())
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
            </div>
        </div>
    </div>

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

    <style>
        .cg-tour-overlay{position:fixed;inset:0;background:rgba(17,24,39,.55);z-index:2147483646 !important;display:none}
        .cg-tour-tooltip{position:fixed;z-index:2147483647 !important;max-width:min(380px,calc(100% - 24px));background:#fff;border:1px solid var(--border);border-radius:14px;box-shadow:0 18px 40px rgba(17,24,39,.25);padding:12px}
        .cg-tour-title{font-weight:900;font-size:14px;margin:0}
        .cg-tour-text{margin-top:6px;color:var(--muted);font-size:13px;white-space:pre-line;line-height:1.45}
        .cg-tour-actions{margin-top:10px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
        .cg-tour-step{margin-top:10px;color:var(--muted);font-size:12px}
        .cg-tour-highlight{position:relative !important;z-index:2147483647 !important;box-shadow:0 0 0 4px rgba(13,110,253,.22);border-radius:12px}
    </style>

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

            function open(action, value, fieldName) {
                form.action = action;
                input.value = value || '';
                input.name = fieldName || 'url';
                input.required = true;
                if (clear) clear.value = '0';
                showDialog(dialog);
                input.focus();
            }

            openButtons.forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    if (e) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    open(btn.dataset.action, btn.dataset.value, btn.dataset.field);
                });
            });

            cancel.addEventListener('click', () => closeDialog(dialog));
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) closeDialog(dialog);
            });

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
            const adjustCancel = document.getElementById('adjustDialogCancel');
            const adjustButtons = document.querySelectorAll('.js-edit-adjustment');
            const csrfToken = '{{ csrf_token() }}';
            let lastAdjustButton = null;

            function openAdjust(action, value) {
                adjustForm.action = action;
                adjustInput.value = value || '0';
                if (typeof adjustDialog.showModal === 'function') {
                    adjustDialog.showModal();
                }
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
                    openAdjust(btn.dataset.action, btn.dataset.value);
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

                        const adj = Number(data && data.price_adjustment !== undefined ? data.price_adjustment : Number(adjustInput.value || 0));
                        if (lastAdjustButton) {
                            lastAdjustButton.dataset.value = String(adj);

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
                                window.location.reload();
                            }
                        } else {
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
                } catch (e) {
                    alert('Xoá thất bại. Vui lòng thử lại.');
                } finally {
                    deleteConfirm.disabled = false;
                    pendingDelete = null;
                    deleteDialog.close();
                }
            });

            const compareViewToggle = document.getElementById('compareViewToggle');
            const comparisonTableView = document.getElementById('comparisonTableView');
            const comparisonCardView = document.getElementById('comparisonCardView');
            const compareViewKey = 'checkgia_compare_view';

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

            function setCompareView(mode, persist) {
                const nextMode = mode === 'cards' ? 'cards' : 'table';

                if (comparisonCardView) {
                    comparisonCardView.style.display = nextMode === 'cards' ? 'flex' : 'none';
                }
                if (comparisonTableView) {
                    comparisonTableView.style.display = nextMode === 'cards' ? 'none' : '';
                }

                if (compareViewToggle) {
                    compareViewToggle.dataset.mode = nextMode;
                    compareViewToggle.textContent = nextMode === 'cards' ? 'Dạng bảng' : 'Dạng thẻ';
                    compareViewToggle.style.display = isMobileView() ? 'none' : '';
                }

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

            window.addEventListener('resize', () => {
                const nowMobile = isMobileView();
                if (nowMobile !== lastMobile) {
                    setCompareView(nowMobile ? 'cards' : getStoredCompareView(), false);
                    lastMobile = nowMobile;
                }
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
                    open(action, '', 'url');
                    const wrapId = select.dataset.target || '';
                    const wrap = wrapId ? document.getElementById(wrapId) : null;
                    if (wrap) wrap.style.display = 'none';
                    select.value = '';
                });
            });

            const filterSearch = document.getElementById('filterSearch');
            const filterGroup = document.getElementById('filterGroup');
            const sortSelect = document.getElementById('sortSelect');
            const filterReset = document.getElementById('filterReset');
            const exportAll = document.getElementById('exportAll');
            const exportGroup = document.getElementById('exportGroup');
            const tbody = document.querySelector('table.table tbody');

            function parseNum(v) {
                if (v === null || v === undefined) return null;
                const s = String(v).trim();
                if (!s) return null;
                const n = Number(s);
                return Number.isFinite(n) ? n : null;
            }

            function applyFiltersAndSort() {
                const q = (filterSearch?.value || '').trim().toLowerCase();
                const group = filterGroup?.value || '';
                const sort = sortSelect?.value || 'row_asc';

                function applyToItems(items, appendTo) {
                    items.forEach((el) => {
                        const name = (el.dataset.productName || '').toLowerCase();
                        const id = String(el.dataset.productId || '');
                        const groupId = String(el.dataset.groupId || '');

                        let visible = true;
                        if (q) {
                            visible = name.includes(q) || id.includes(q);
                        }
                        if (visible && group) {
                            if (group === '__none__') {
                                visible = !groupId;
                            } else {
                                visible = groupId === group;
                            }
                        }
                        el.style.display = visible ? '' : 'none';
                    });

                    const visibleItems = items.filter((r) => r.style.display !== 'none');
                    visibleItems.sort((a, b) => {
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
                        if (sort === 'last_desc') return bLast - aLast;
                        if (sort === 'last_asc') return aLast - bLast;
                        if (sort === 'price_asc') return aPrice - bPrice;
                        if (sort === 'price_desc') return bPrice - aPrice;
                        if (sort === 'diff_asc') return aDiffVal - bDiffVal;
                        if (sort === 'diff_desc') return bDiffVal - aDiffVal;
                        return 0;
                    });

                    if (appendTo) {
                        visibleItems.forEach((el) => appendTo.appendChild(el));
                    }
                }

                if (tbody) {
                    const rows = Array.from(tbody.querySelectorAll('tr[data-product-row]'));
                    applyToItems(rows, tbody);
                }

                if (comparisonCardView) {
                    const cards = Array.from(comparisonCardView.querySelectorAll('[data-product-card]'));
                    applyToItems(cards, comparisonCardView);
                }
            }

            if (filterSearch) filterSearch.addEventListener('input', applyFiltersAndSort);
            if (filterGroup) filterGroup.addEventListener('change', applyFiltersAndSort);
            if (sortSelect) sortSelect.addEventListener('change', applyFiltersAndSort);
            if (filterReset) {
                filterReset.addEventListener('click', () => {
                    if (filterSearch) filterSearch.value = '';
                    if (filterGroup) filterGroup.value = '';
                    if (sortSelect) sortSelect.value = 'row_asc';
                    applyFiltersAndSort();
                });
            }

            function syncExportLinks() {
                if (!exportAll || !exportGroup || !filterGroup) return;
                const group = filterGroup.value || '';
                if (!group) {
                    exportGroup.style.display = 'none';
                    exportGroup.href = exportAll.href;
                    return;
                }
                exportGroup.style.display = '';
                exportGroup.href = `${exportAll.href}?group_id=${encodeURIComponent(group)}`;
            }

            if (filterGroup) filterGroup.addEventListener('change', syncExportLinks);
            syncExportLinks();
            applyFiltersAndSort();
        })();

        (function () {
            const tourKey = 'checkgia_tour_dashboard_v1_done';
            const overlay = document.createElement('div');
            overlay.className = 'cg-tour-overlay';

            const tooltip = document.createElement('div');
            tooltip.className = 'cg-tour-tooltip';

            const titleEl = document.createElement('div');
            titleEl.className = 'cg-tour-title';
            tooltip.appendChild(titleEl);

            const textEl = document.createElement('div');
            textEl.className = 'cg-tour-text';
            tooltip.appendChild(textEl);

            const stepEl = document.createElement('div');
            stepEl.className = 'cg-tour-step';
            tooltip.appendChild(stepEl);

            const actions = document.createElement('div');
            actions.className = 'cg-tour-actions';

            const btnSkip = document.createElement('button');
            btnSkip.type = 'button';
            btnSkip.className = 'btn btn-secondary';
            btnSkip.textContent = 'Bỏ qua';
            actions.appendChild(btnSkip);

            const btnBack = document.createElement('button');
            btnBack.type = 'button';
            btnBack.className = 'btn btn-secondary';
            btnBack.textContent = 'Trước';
            actions.appendChild(btnBack);

            const btnNext = document.createElement('button');
            btnNext.type = 'button';
            btnNext.className = 'btn';
            btnNext.textContent = 'Tiếp';
            actions.appendChild(btnNext);

            tooltip.appendChild(actions);

            document.body.appendChild(overlay);
            document.body.appendChild(tooltip);

            let current = -1;
            let highlighted = null;

            function clearHighlight() {
                if (!highlighted) return;
                highlighted.classList.remove('cg-tour-highlight');
                highlighted = null;
            }

            function getEl(selector) {
                if (!selector) return null;
                if (typeof selector === 'function') return selector();
                return document.querySelector(selector);
            }

            function ensureAddFormOpen() {
                const body = document.getElementById('addProductBody');
                const chevron = document.getElementById('addProductChevron');
                if (body && body.style.display === 'none') {
                    body.style.display = '';
                    if (chevron) chevron.style.transform = 'rotate(180deg)';
                }
            }

            const isViewer = @json(auth()->user()->isViewer());
            const steps = [
                {
                    title: 'Nhập link sản phẩm',
                    text: 'Dán link sản phẩm của bạn vào ô URL.\nSau đó có thể nhập thêm link đối thủ để so sánh.',
                    target: '#product_url',
                    before: ensureAddFormOpen,
                },
                {
                    title: 'Link đối thủ (tuỳ chọn)',
                    text: 'Nếu đã tạo cột đối thủ, dán link đối thủ vào các ô bên dưới.\nNếu chưa có đối thủ, vào mục “Cài đặt” để tạo cột so sánh.',
                    target: () => document.getElementById('competitorUrlGrid') || document.getElementById('addProductCard'),
                    before: ensureAddFormOpen,
                },
                {
                    title: 'Nhóm sản phẩm (tuỳ chọn)',
                    text: 'Bạn có thể chọn nhóm có sẵn hoặc tạo nhóm mới để lọc và xuất Excel theo nhóm.',
                    target: '#product_group_id',
                    before: ensureAddFormOpen,
                },
                {
                    title: 'Thêm vào danh sách',
                    text: 'Bấm nút để thêm sản phẩm vào bảng so sánh.\nSau khi thêm, dữ liệu sẽ xuất hiện ở phần “Kết quả so sánh”.',
                    target: '#addProductSubmit',
                    before: ensureAddFormOpen,
                },
                {
                    title: 'Bảng kết quả so sánh',
                    text: 'Đây là nơi xem giá của bạn, giá đối thủ, chênh lệch và thời gian cập nhật.',
                    target: '#comparisonCard',
                },
                {
                    title: 'Bấm link sản phẩm',
                    text: 'Bấm “link sản phẩm” để mở trang sản phẩm của bạn.',
                    target: '[data-tour="open-own-link"]',
                },
                {
                    title: 'Bấm link đối thủ',
                    text: 'Bấm vào chênh lệch (hoặc link) để mở trang đối thủ.',
                    target: '[data-tour="open-competitor-link"]',
                },
            ];

            if (!isViewer) {
                steps.push(
                    {
                        title: 'Sửa link sản phẩm',
                        text: 'Bấm nút “Sửa link” để đổi URL sản phẩm của bạn.',
                        target: '[data-tour="edit-own-url"]',
                    },
                    {
                        title: 'Sửa link đối thủ',
                        text: 'Bấm nút “Sửa URL” ở cột đối thủ để cập nhật link đối thủ.',
                        target: '[data-tour="edit-competitor-url"]',
                    },
                    {
                        title: 'Giá điều chỉnh',
                        text: 'Dùng “Giá điều chỉnh (+/-)” để cộng/trừ chênh lệch theo phí ship/khuyến mãi.',
                        target: '[data-tour="price-adjustment"]',
                    }
                );
            }

            function positionTooltip(el) {
                const pad = 12;
                const rect = el.getBoundingClientRect();
                const tipRect = tooltip.getBoundingClientRect();

                let top = rect.bottom + 10;
                let left = rect.left;

                if (left + tipRect.width > window.innerWidth - pad) {
                    left = window.innerWidth - pad - tipRect.width;
                }
                if (left < pad) left = pad;

                if (top + tipRect.height > window.innerHeight - pad) {
                    top = rect.top - 10 - tipRect.height;
                }
                if (top < pad) top = pad;

                tooltip.style.top = `${Math.round(top)}px`;
                tooltip.style.left = `${Math.round(left)}px`;
            }

            function showStep(idx) {
                if (idx < 0 || idx >= steps.length) return;
                const step = steps[idx];
                if (step.before) step.before();

                const el = getEl(step.target);
                if (!el) {
                    const next = idx + 1;
                    if (next < steps.length) showStep(next);
                    else close(true);
                    return;
                }

                current = idx;
                clearHighlight();
                highlighted = el;
                highlighted.classList.add('cg-tour-highlight');
                highlighted.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });

                titleEl.textContent = `Bước ${idx + 1}: ${step.title}`;
                textEl.textContent = step.text;
                stepEl.textContent = `(${idx + 1}/${steps.length})`;

                btnBack.style.display = idx === 0 ? 'none' : '';
                btnNext.textContent = idx === steps.length - 1 ? 'Hoàn tất' : 'Tiếp';

                overlay.style.display = '';
                tooltip.style.display = '';

                requestAnimationFrame(() => positionTooltip(el));
            }

            function close(markDone) {
                clearHighlight();
                overlay.style.display = 'none';
                tooltip.style.display = 'none';
                current = -1;
                if (markDone) {
                    try {
                        localStorage.setItem(tourKey, '1');
                    } catch (e) {
                    }
                }
            }

            function start(force) {
                if (!force) {
                    try {
                        if (localStorage.getItem(tourKey) === '1') return;
                    } catch (e) {
                    }
                }
                showStep(0);
            }

            btnSkip.addEventListener('click', () => close(true));
            btnBack.addEventListener('click', () => showStep(Math.max(0, current - 1)));
            btnNext.addEventListener('click', () => {
                if (current >= steps.length - 1) {
                    close(true);
                    return;
                }
                showStep(current + 1);
            });
            overlay.addEventListener('click', () => close(true));
            window.addEventListener('resize', () => {
                if (current < 0) return;
                const step = steps[current];
                const el = getEl(step.target);
                if (el) positionTooltip(el);
            });

            const startBtn = document.getElementById('dashboardTourStart');
            if (startBtn) {
                startBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    start(true);
                });
            }

            setTimeout(() => start(false), 800);
        })();
    </script>
@endsection
