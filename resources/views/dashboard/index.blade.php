@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1500px">
        <div class="card" style="max-width:none;margin-bottom:16px">
            <div id="addProductHeader" style="display:flex;justify-content:space-between;gap:12px;align-items:center;cursor:pointer;user-select:none;padding:16px 16px 6px">
                <div>
                    <h1 class="card-title">Nhập link sản phẩm</h1>
                    <p class="card-sub">Thêm nhanh sản phẩm và link đối thủ để so sánh</p>
                </div>
                <div id="addProductChevron" style="width:28px;height:28px;border-radius:999px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
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
                                    <span style="font-weight:700;flex:0 0 auto;color:{{ $isUp ? 'var(--danger)' : 'var(--success)' }}">
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
                        <form method="POST" action="{{ route('dashboard.scrape.now') }}" style="display:inline">
                            @csrf
                            <button class="btn btn-secondary" type="submit">Cập nhật</button>
                        </form>
                        <button class="btn btn-secondary" type="button" id="filterReset">Reset</button>
                    </div>
                </div>
                <div class="table-wrap">
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
                                                <button
                                                    type="button"
                                                    class="icon-btn icon-btn-sm js-edit-url"
                                                    data-action="{{ route('dashboard.products.url.update', $product) }}"
                                                    data-field="product_url"
                                                    data-value="{{ $product->product_url }}"
                                                    title="Sửa link sản phẩm của bạn"
                                                >
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
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
                                                            <a href="{{ $c->url }}" target="_blank" style="color:#6b7280">không chênh</a>
                                                        @elseif($diff > 0)
                                                            <a href="{{ $c->url }}" target="_blank" style="color:var(--danger)">+{{ number_format($diff, 0, ',', '.') }}đ</a>
                                                        @else
                                                            <a href="{{ $c->url }}" target="_blank" style="color:var(--success)">{{ number_format($diff, 0, ',', '.') }}đ</a>
                                                        @endif

                                                        @if(! is_null($adjDiff))
                                                            @if($adj !== 0)
                                                                @php($adjColor = $adjDiff > 0 ? '#991b1b' : ($adjDiff < 0 ? '#166534' : '#111827'))
                                                                <span style="font-weight:800;color:{{ $adjColor }}">
                                                                    @if($adjDiff > 0)
                                                                        +{{ number_format($adjDiff, 0, ',', '.') }}đ
                                                                    @elseif($adjDiff < 0)
                                                                        {{ number_format($adjDiff, 0, ',', '.') }}đ
                                                                    @else
                                                                        0đ
                                                                    @endif
                                                                </span>
                                                            @endif
                                                            <button
                                                                type="button"
                                                                class="icon-btn icon-btn-sm js-edit-adjustment"
                                                                data-action="{{ route('competitors.adjustment.update', $c) }}"
                                                                data-value="{{ $adj }}"
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
                                                                data-value="{{ $c->url }}"
                                                                title="Sửa URL">
                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                                <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                                                            </svg>
                                                        </button>
                                                        @if($cPrice)
                                                            <a href="{{ route('competitors.history', $c) }}" title="{{ $product->name }}">{{ number_format($cPrice, 0, ',', '.') }}đ</a>
                                                            @if(! is_null($delta) && $delta !== 0)
                                                                <span title="{{ $delta > 0 ? 'Tăng' : 'Giảm' }} {{ number_format(abs($delta), 0, ',', '.') }}đ" style="display:inline-flex;align-items:center">
                                                                    @if($delta > 0)
                                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="color:var(--danger)">
                                                                            <path d="M12 5l6 6h-4v8h-4v-8H6l6-6z" fill="currentColor"/>
                                                                        </svg>
                                                                    @else
                                                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="color:var(--success)">
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
                                                    <button
                                                        type="button"
                                                        class="icon-btn icon-btn-sm js-edit-url"
                                                        data-action="{{ route('dashboard.products.competitors.upsert', [$product, $site]) }}"
                                                        data-field="url"
                                                        data-value=""
                                                        title="Thêm URL"
                                                    >
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
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
                                    <td colspan="{{ 5 + $competitorSites->count() }}" class="hint">Chưa có dữ liệu. Hãy thêm sản phẩm trước.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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

            function open(action, value, fieldName) {
                form.action = action;
                input.value = value || '';
                input.name = fieldName || 'url';
                input.required = true;
                if (clear) clear.value = '0';
                if (typeof dialog.showModal === 'function') {
                    dialog.showModal();
                }
                input.focus();
            }

            openButtons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    open(btn.dataset.action, btn.dataset.value, btn.dataset.field);
                });
            });

            cancel.addEventListener('click', () => dialog.close());
            dialog.addEventListener('click', (e) => {
                if (e.target === dialog) dialog.close();
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

            function openAdjust(action, value) {
                adjustForm.action = action;
                adjustInput.value = value || '0';
                if (typeof adjustDialog.showModal === 'function') {
                    adjustDialog.showModal();
                }
                adjustInput.focus();
                adjustInput.select();
            }

            adjustButtons.forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();
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
                            'Accept': 'application/json',
                        },
                    });

                    if (!res.ok) {
                        throw new Error('delete_failed');
                    }

                    const row = document.querySelector(`[data-product-row="${pendingDelete.productId}"]`);
                    if (row) row.remove();
                } catch (e) {
                } finally {
                    deleteConfirm.disabled = false;
                    pendingDelete = null;
                    deleteDialog.close();
                }
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
                if (!tbody) return;
                const rows = Array.from(tbody.querySelectorAll('tr[data-product-row]'));
                const q = (filterSearch?.value || '').trim().toLowerCase();
                const group = filterGroup?.value || '';
                    const sort = sortSelect?.value || 'row_asc';

                rows.forEach((row) => {
                    const name = (row.dataset.productName || '').toLowerCase();
                    const id = String(row.dataset.productId || '');
                    const groupId = String(row.dataset.groupId || '');

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
                    row.style.display = visible ? '' : 'none';
                });

                const visibleRows = rows.filter((r) => r.style.display !== 'none');

                visibleRows.sort((a, b) => {
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

                visibleRows.forEach((row) => tbody.appendChild(row));
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
    </script>
@endsection
