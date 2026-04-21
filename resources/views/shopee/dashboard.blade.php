@extends('layouts.app')

@section('content')
    <div class="card" style="max-width:1500px">
        <div class="card-header">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                <div style="min-width:0">
                    <h1 class="card-title" style="margin:0">Check Giá Shopee</h1>
                    <p class="card-sub" style="margin:6px 0 0">Giá được cập nhật bởi Chrome extension</p>
                </div>
                <div style="display:flex;gap:8px;align-items:center">
                    @if(auth()->user())
                        <a class="btn btn-secondary" href="{{ route('shopee.settings') }}">Cài đặt</a>
                    @endif
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
                    <h2 class="card-title" style="font-size:18px;margin:0">Thêm sản phẩm Shopee</h2>
                    <p class="card-sub" style="margin-top:6px">Nhập link shop bạn, và nhập thêm link của đối thủ (nếu có) để so sánh</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('shopee.products.store') }}" id="add-product-form">
                        @csrf
                        <div class="field" style="margin-top:0">
                            <label class="label">Link shop bạn (Shopee)</label>
                            <input class="input" name="own_url" type="url" placeholder="https://shopee.vn/..." value="{{ old('own_url') }}" required>
                        </div>

                        @if($shops->isNotEmpty())
                            <div style="margin-top:12px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                                @foreach($shops as $shop)
                                    <div class="field" style="margin-top:0">
                                        <label class="label">Link {{ $shop->name }}</label>
                                        <input class="input" name="competitor_urls[{{ $shop->id }}]" type="url" value="{{ old('competitor_urls.'.$shop->id) }}" placeholder="https://shopee.vn/...">
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="hint" style="margin-top:10px">Chưa có shop đối thủ. Hãy vào Cài đặt để thêm shop đối thủ.</div>
                        @endif

                        <div class="actions">
                            <button class="btn" type="submit">Thêm</button>
                        </div>
                    </form>
                </div>
            </div>

            <script>
                function triggerExtensionPoll() {
                    window.postMessage({ type: 'CHECKGIA_TRIGGER_POLL' }, '*');
                }

                document.getElementById('add-product-form').addEventListener('submit', function() {
                    // Trigger extension poll after a short delay (after form submission starts)
                    setTimeout(triggerExtensionPoll, 500);
                });
                
                // Also trigger on page load to catch any pending tasks immediately
                window.addEventListener('load', function() {
                    triggerExtensionPoll();
                });
            </script>

            <div style="height:14px"></div>

            <form id="update-url-form" method="POST" style="display:none">
                @csrf
                @method('PUT')
                <input type="hidden" name="own_url" id="update-url-input">
            </form>

            <form id="upsert-competitor-form" method="POST" style="display:none">
                @csrf
                <input type="hidden" name="url" id="upsert-competitor-url">
            </form>

            <form id="update-adjustment-form" method="POST" style="display:none">
                @csrf
                <input type="hidden" name="price_adjustment" id="update-adjustment-input">
            </form>

            <div id="custom-modal" class="modal-overlay" style="display:none">
                <div class="modal-content">
                    <h3 id="modal-title" style="margin-top:0;font-size:16px">Cập nhật</h3>
                    <input type="text" id="modal-input" class="input" style="margin:12px 0">
                    <div style="display:flex;justify-content:flex-end;gap:10px">
                        <button class="btn btn-secondary" onclick="closeModal()">Hủy</button>
                        <button class="btn" id="modal-ok-btn">OK</button>
                    </div>
                </div>
            </div>

            <script>
                let currentModalCallback = null;
                let currentModalFormat = null;

                function formatMoneyInput(value) {
                    let v = String(value ?? '');
                    v = v.replace(/\s+/g, '');

                    let sign = '';
                    if (v.startsWith('-')) {
                        sign = '-';
                        v = v.slice(1);
                    }

                    const digits = v.replace(/[^\d]/g, '');
                    const raw = sign + digits;
                    const display = (sign ? '-' : '') + (digits ? digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '');

                    return { raw, display };
                }

                function showModal(title, defaultValue, callback, options = {}) {
                    document.getElementById('modal-title').innerText = title;
                    const input = document.getElementById('modal-input');
                    currentModalFormat = options.format || null;
                    currentModalCallback = callback;

                    if (currentModalFormat === 'money') {
                        const fmt = formatMoneyInput(defaultValue);
                        input.value = fmt.display;
                        input.dataset.raw = fmt.raw;
                        input.inputMode = 'numeric';
                        input.autocomplete = 'off';
                    } else {
                        input.value = defaultValue ?? '';
                        input.dataset.raw = '';
                        input.inputMode = '';
                        input.autocomplete = '';
                    }

                    document.getElementById('custom-modal').style.display = 'flex';
                    input.focus();
                }

                function closeModal() {
                    document.getElementById('custom-modal').style.display = 'none';
                    currentModalCallback = null;
                    currentModalFormat = null;
                }

                document.getElementById('modal-ok-btn').onclick = function() {
                    if (currentModalCallback) {
                        const input = document.getElementById('modal-input');
                        const value = currentModalFormat === 'money' ? (input.dataset.raw ?? '') : input.value;
                        currentModalCallback(value);
                    }
                    closeModal();
                };

                // Close modal on Enter key
                document.getElementById('modal-input').onkeydown = function(e) {
                    if (e.key === 'Enter') {
                        document.getElementById('modal-ok-btn').click();
                    }
                    if (e.key === 'Escape') {
                        closeModal();
                    }
                };

                document.getElementById('modal-input').oninput = function() {
                    if (currentModalFormat !== 'money') {
                        return;
                    }

                    const input = document.getElementById('modal-input');
                    const fmt = formatMoneyInput(input.value);
                    input.value = fmt.display;
                    input.dataset.raw = fmt.raw;
                };

                function updateOwnUrl(productId, currentUrl) {
                    showModal('Nhập link Shopee mới cho sản phẩm của bạn:', currentUrl, function(newUrl) {
                        if (newUrl !== null && newUrl !== currentUrl) {
                            const form = document.getElementById('update-url-form');
                            form.action = `/shopee/products/${productId}/url`;
                            document.getElementById('update-url-input').value = newUrl;
                            form.submit();
                        }
                    });
                }

                function upsertCompetitorUrl(productId, shopId, currentUrl) {
                    showModal('Nhập link Shopee của đối thủ:', currentUrl || '', function(newUrl) {
                        if (newUrl !== null && newUrl !== currentUrl) {
                            const form = document.getElementById('upsert-competitor-form');
                            form.action = `/shopee/products/${productId}/shops/${shopId}`;
                            document.getElementById('upsert-competitor-url').value = newUrl;
                            form.submit();
                        }
                    });
                }

                function updateAdjustment(competitorId, currentAdj) {
                    showModal('Nhập số tiền điều chỉnh (VD: -200000 hoặc 200000):', currentAdj || 0, function(newAdj) {
                        if (newAdj !== null && newAdj !== currentAdj.toString()) {
                            const form = document.getElementById('update-adjustment-form');
                            form.action = `/shopee/competitors/${competitorId}/adjustment`;
                            document.getElementById('update-adjustment-input').value = newAdj;
                            form.submit();
                        }
                    }, { format: 'money' });
                }
            </script>

            <style>
                .modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.5);
                    display: flex;
                    justify-content: center;
                    align-items: flex-start; /* Aligns modal towards the top but with margin */
                    padding-top: 40vh; /* This positions the modal around 2/3 down from the top */
                    z-index: 9999;
                }
                .modal-content {
                    background: white;
                    padding: 20px;
                    border-radius: 12px;
                    width: 100%;
                    max-width: 450px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                }
                .input {
                    padding: 12px 14px;
                    border-radius: 12px;
                    border: 1px solid var(--border, #e5e7eb);
                    background: #fff;
                    color: var(--text, #111827);
                    outline: none;
                    width: 100%;
                }
                .table thead th.header-blue {
                    background: #007bff !important;
                    color: white !important;
                    text-align: center;
                    padding: 10px 8px;
                    border: none;
                    font-size: 13px;
                }
                .icon-box {
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                    padding: 3px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    background: white;
                    width: 24px;
                    height: 24px;
                    color: #007bff;
                }
                .price-val {
                    font-size: 16px;
                    font-weight: 800;
                    color: #111827;
                }
                .diff-val {
                    font-weight: 700;
                    font-size: 13px;
                }
                .link-text {
                    color: #007bff;
                    text-decoration: none;
                    font-weight: 500;
                    font-size: 13px;
                }
                .table td {
                    font-size: 13px;
                    padding: 10px 8px;
                }
            </style>

            <div class="card" style="max-width:none;box-shadow:none;margin-top:0">
                <div class="card-header">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px">
                        <div style="min-width:0">
                            <h2 class="card-title" style="font-size:18px;margin:0">Kết quả so sánh</h2>
                            <p class="card-sub" style="margin-top:6px">Giá chênh = Giá đối thủ - Giá của bạn</p>
                        </div>
                        <div class="pill" id="cmp-count" style="white-space:nowrap">Tổng sản phẩm: {{ $products->count() }}</div>
                    </div>
                </div>
                <div class="card-body">
                    <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">
                        <div style="flex:1 1 380px;min-width:260px">
                            <div class="hint" style="margin-top:0">Tìm kiếm</div>
                            <input class="input" id="cmp-search" placeholder="Nhập tên sản phẩm hoặc ID...">
                        </div>
                        <div style="width:170px;min-width:150px">
                            <div class="hint" style="margin-top:0">Nhóm</div>
                            <select class="input" id="cmp-group">
                                <option value="all">Tất cả</option>
                                <option value="enabled">Đang bật</option>
                                <option value="disabled">Đang tắt</option>
                            </select>
                        </div>
                        <div style="width:170px;min-width:150px">
                            <div class="hint" style="margin-top:0">Sắp xếp</div>
                            <select class="input" id="cmp-sort">
                                <option value="order">Số thứ tự</option>
                                <option value="maxdiff_desc">Chênh lệch</option>
                                <option value="own_asc">Giá của bạn ↑</option>
                                <option value="own_desc">Giá của bạn ↓</option>
                                <option value="updated_desc">Thời gian ↓</option>
                            </select>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center;flex:1 1 auto;justify-content:flex-end">
                            <button type="button" class="btn btn-secondary" id="cmp-export">Xuất Excel</button>
                            <button type="button" class="btn btn-secondary" id="cmp-refresh">Cập nhật</button>
                            <button type="button" class="btn btn-secondary" id="cmp-view">Dạng thẻ</button>
                            <button type="button" class="btn btn-secondary" id="cmp-reset">Reset</button>
                        </div>
                    </div>

                    <div style="height:12px"></div>

                    <div class="table-wrap" id="cmp-table-wrap">
                        <table class="table" id="cmp-table">
                            <thead>
                                <tr>
                                    <th style="width:52px">#</th>
                                    <th style="min-width:340px">Tên sản phẩm</th>
                                    <th class="header-blue" style="min-width:160px">Giá của bạn</th>
                                    @foreach($shops as $shop)
                                        <th class="header-blue" style="min-width:180px">{{ $shop->name }}</th>
                                    @endforeach
                                    <th class="header-blue" style="min-width:140px">Thời gian</th>
                                    <th style="width:120px">Hành động</th>
                                </tr>
                            </thead>
                            <tbody id="cmp-tbody">
                                @forelse($products as $i => $product)
                                    @php($own = is_null($product->last_price) ? null : (int) $product->last_price)
                                    @php($map = $product->competitors->keyBy('shopee_shop_id'))
                                    @php($maxAbsDiff = 0)
                                    @foreach($shops as $shop)
                                        @php($tmpC = $map->get($shop->id))
                                        @php($tmpCPrice = $tmpC?->last_price)
                                        @php($tmpAdj = (int) ($tmpC?->price_adjustment ?? 0))
                                        @php($tmpD = (! is_null($own) && ! is_null($tmpCPrice)) ? abs(((int) $tmpCPrice + $tmpAdj - (int) $own)) : 0)
                                        @php($maxAbsDiff = max($maxAbsDiff, $tmpD))
                                    @endforeach
                                    @php($updatedTs = $product->last_scraped_at ? (int) $product->last_scraped_at->timestamp : 0)
                                    <tr class="cmp-row"
                                        data-search="{{ $product->id }} {{ $product->name }}"
                                        data-enabled="{{ $product->is_enabled ? 1 : 0 }}"
                                        data-order="{{ $i + 1 }}"
                                        data-own="{{ is_null($own) ? '' : (string) $own }}"
                                        data-maxdiff="{{ $maxAbsDiff }}"
                                        data-updated="{{ $updatedTs }}">
                                        <td style="font-weight:800">{{ $i + 1 }}</td>
                                        <td>
                                            <div style="font-weight:800;font-size:13px">{{ $product->name ?: 'Shopee product #' . $product->id }}</div>
                                            <div class="hint" style="margin-top:3px;font-size:11px">ID: {{ $product->id }}</div>
                                        </td>
                                        <td>
                                            @if(is_null($own))
                                                <div style="display:flex;align-items:center;gap:8px">
                                                    <a href="javascript:void(0)" onclick="updateOwnUrl({{ $product->id }}, '')" class="icon-box" title="Thêm link Shopee">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                    </a>
                                                    <span class="hint" style="margin-top:0">---</span>
                                                </div>
                                            @else
                                                <div style="display:flex;flex-direction:column;gap:6px">
                                                    <div style="display:flex;align-items:center;gap:8px">
                                                        <a href="javascript:void(0)" onclick="updateOwnUrl({{ $product->id }}, '{{ $product->own_url }}')" class="icon-box" title="Sửa link Shopee">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                        </a>
                                                        <a href="{{ $product->own_url }}" target="_blank" class="link-text">link sản phẩm</a>
                                                    </div>
                                                    <a href="{{ route('shopee.history', $product) }}" class="price-val" style="text-decoration:none;display:block">{{ number_format($own, 0, ',', '.') }}đ</a>
                                                </div>
                                            @endif
                                        </td>
                                        @foreach($shops as $shop)
                                            @php($c = $map->get($shop->id))
                                            @php($cPrice = $c?->last_price)
                                            @php($adj = (int) ($c?->price_adjustment ?? 0))
                                            @php($diff = (! is_null($own) && ! is_null($cPrice)) ? ((int) $cPrice + $adj - (int) $own) : null)
                                            <td>
                                                @if(! $c || is_null($cPrice) || is_null($own))
                                                    <div style="display:flex;align-items:center;gap:8px">
                                                        <a href="javascript:void(0)" onclick="upsertCompetitorUrl({{ $product->id }}, {{ $shop->id }}, '{{ $c?->url ?? '' }}')" class="icon-box" title="Thêm link đối thủ">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                        </a>
                                                        <span class="hint" style="margin-top:0">---</span>
                                                    </div>
                                                @else
                                                    <div style="display:flex;flex-direction:column;gap:6px">
                                                        <div style="display:flex;align-items:center;gap:10px">
                                                            <a href="{{ $c->url }}" target="_blank" class="diff-val" style="color:{{ $diff > 0 ? 'var(--success)' : ($diff < 0 ? 'var(--danger)' : '#6b7280') }};text-decoration:none">
                                                                {{ $diff > 0 ? '+' : '' }}{{ number_format((int) $diff, 0, ',', '.') }}đ
                                                            </a>
                                                            <a href="javascript:void(0)" onclick="updateAdjustment({{ $c->id }}, {{ $adj }})" class="icon-box" style="color:#6b7280;width:20px;height:20px" title="Điều chỉnh giá chênh">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                                                            </a>
                                                        </div>
                                                        <div style="display:flex;align-items:center;gap:6px">
                                                            <a href="javascript:void(0)" onclick="upsertCompetitorUrl({{ $product->id }}, {{ $shop->id }}, '{{ $c->url }}')" class="icon-box" title="Sửa link đối thủ">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                            </a>
                                                            <a href="{{ route('shopee.history', $product) }}" class="link-text" style="font-size:16px;font-weight:600">
                                                                {{ number_format((int) $cPrice, 0, ',', '.') }}đ
                                                            </a>
                                                        </div>
                                                    </div>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td>
                                            @if($product->last_scraped_at)
                                                <div style="font-weight:700">{{ $product->last_scraped_at->format('d/m H:i') }}</div>
                                            @else
                                                <span class="hint" style="margin-top:0">---</span>
                                            @endif
                                        </td>
                                        <td style="text-align:right">
                                            <form method="POST" action="{{ route('shopee.products.destroy', $product) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xoá sản phẩm này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn" type="submit" style="padding:6px 10px;background:var(--danger);border-color:rgba(220,53,69,.2)">Xoá</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 5 + $shops->count() }}" class="hint">Chưa có dữ liệu. Hãy thêm sản phẩm ở phía trên và thêm shop đối thủ trong Cài đặt.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div id="cmp-cards" style="display:none">
                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                            @foreach($products as $i => $product)
                                @php($own = is_null($product->last_price) ? null : (int) $product->last_price)
                                @php($map = $product->competitors->keyBy('shopee_shop_id'))
                                @php($maxAbsDiff = 0)
                                @foreach($shops as $shop)
                                    @php($tmpC = $map->get($shop->id))
                                    @php($tmpCPrice = $tmpC?->last_price)
                                    @php($tmpAdj = (int) ($tmpC?->price_adjustment ?? 0))
                                    @php($tmpD = (! is_null($own) && ! is_null($tmpCPrice)) ? abs(((int) $tmpCPrice + $tmpAdj - (int) $own)) : 0)
                                    @php($maxAbsDiff = max($maxAbsDiff, $tmpD))
                                @endforeach
                                @php($updatedTs = $product->last_scraped_at ? (int) $product->last_scraped_at->timestamp : 0)
                                <div class="cmp-card"
                                     data-search="{{ $product->id }} {{ $product->name }}"
                                     data-enabled="{{ $product->is_enabled ? 1 : 0 }}"
                                     data-order="{{ $i + 1 }}"
                                     data-own="{{ is_null($own) ? '' : (string) $own }}"
                                     data-maxdiff="{{ $maxAbsDiff }}"
                                     data-updated="{{ $updatedTs }}"
                                     style="border:1px solid var(--border, #e5e7eb);border-radius:12px;background:#fff;padding:12px">
                                    <div style="display:flex;justify-content:space-between;gap:10px">
                                        <div style="min-width:0">
                                            <div style="font-weight:900;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $product->name ?: 'Shopee product #' . $product->id }}</div>
                                            <div class="hint" style="margin-top:3px;font-size:11px">ID: {{ $product->id }}</div>
                                        </div>
                                        <div style="text-align:right">
                                            @if($product->last_scraped_at)
                                                <div class="hint" style="margin-top:0;font-size:11px">{{ $product->last_scraped_at->format('d/m H:i') }}</div>
                                            @endif
                                        </div>
                                    </div>

                                    <div style="height:10px"></div>

                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                                        <div>
                                            <div class="hint" style="margin-top:0">Giá của bạn</div>
                                            @if(is_null($own))
                                                <div style="display:flex;align-items:center;gap:8px">
                                                    <a href="javascript:void(0)" onclick="updateOwnUrl({{ $product->id }}, '')" class="icon-box" title="Thêm link Shopee">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                    </a>
                                                    <span class="hint" style="margin-top:0">---</span>
                                                </div>
                                            @else
                                                <div style="display:flex;align-items:center;gap:8px">
                                                    <a href="javascript:void(0)" onclick="updateOwnUrl({{ $product->id }}, '{{ $product->own_url }}')" class="icon-box" title="Sửa link Shopee">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                    </a>
                                                    <a href="{{ route('shopee.history', $product) }}" class="link-text" style="font-size:16px;font-weight:900;text-decoration:none">{{ number_format($own, 0, ',', '.') }}đ</a>
                                                </div>
                                            @endif
                                        </div>
                                        <div>
                                            <form method="POST" action="{{ route('shopee.products.destroy', $product) }}" onsubmit="return confirm('Bạn có chắc chắn muốn xoá sản phẩm này?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn" type="submit" style="padding:6px 10px;background:var(--danger);border-color:rgba(220,53,69,.2)">Xoá</button>
                                            </form>
                                        </div>
                                    </div>

                                    <div style="height:10px"></div>

                                    <div style="display:grid;grid-template-columns:1fr;gap:10px">
                                        @foreach($shops as $shop)
                                            @php($c = $map->get($shop->id))
                                            @php($cPrice = $c?->last_price)
                                            @php($adj = (int) ($c?->price_adjustment ?? 0))
                                            @php($diff = (! is_null($own) && ! is_null($cPrice)) ? ((int) $cPrice + $adj - (int) $own) : null)
                                            <div style="border:1px solid var(--border, #e5e7eb);border-radius:12px;padding:10px">
                                                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px">
                                                    <div style="font-weight:900;font-size:12px">{{ $shop->name }}</div>
                                                    @if(! $c || is_null($cPrice) || is_null($own))
                                                        <a href="javascript:void(0)" onclick="upsertCompetitorUrl({{ $product->id }}, {{ $shop->id }}, '{{ $c?->url ?? '' }}')" class="icon-box" title="Thêm link đối thủ">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                        </a>
                                                    @else
                                                        <div style="display:flex;align-items:center;gap:8px">
                                                            <a href="{{ $c->url }}" target="_blank" class="diff-val" style="color:{{ $diff > 0 ? 'var(--success)' : ($diff < 0 ? 'var(--danger)' : '#6b7280') }};text-decoration:none">
                                                                {{ $diff > 0 ? '+' : '' }}{{ number_format((int) $diff, 0, ',', '.') }}đ
                                                            </a>
                                                            <a href="javascript:void(0)" onclick="updateAdjustment({{ $c->id }}, {{ $adj }})" class="icon-box" style="color:#6b7280;width:20px;height:20px" title="Điều chỉnh giá chênh">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path><path d="m15 5 4 4"></path></svg>
                                                            </a>
                                                            <a href="javascript:void(0)" onclick="upsertCompetitorUrl({{ $product->id }}, {{ $shop->id }}, '{{ $c->url }}')" class="icon-box" title="Sửa link đối thủ">
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg>
                                                            </a>
                                                        </div>
                                                    @endif
                                                </div>
                                                @if(! $c || is_null($cPrice) || is_null($own))
                                                    <div class="hint" style="margin-top:6px">---</div>
                                                @else
                                                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:6px">
                                                        <a href="{{ route('shopee.history', $product) }}" class="link-text" style="font-size:15px;font-weight:900;text-decoration:none">
                                                            {{ number_format((int) $cPrice, 0, ',', '.') }}đ
                                                        </a>
                                                        @if($adj !== 0)
                                                            <div class="hint" style="margin-top:0;font-size:11px">Adj: {{ $adj > 0 ? '+' : '' }}{{ number_format((int) $adj, 0, ',', '.') }}đ</div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <script>
                (function() {
                    const searchEl = document.getElementById('cmp-search');
                    const groupEl = document.getElementById('cmp-group');
                    const sortEl = document.getElementById('cmp-sort');
                    const countEl = document.getElementById('cmp-count');
                    const tableWrap = document.getElementById('cmp-table-wrap');
                    const cardsWrap = document.getElementById('cmp-cards');
                    const viewBtn = document.getElementById('cmp-view');

                    function parseNum(v) {
                        const n = parseInt(String(v || '').replace(/[^\d-]/g, ''), 10);
                        return Number.isFinite(n) ? n : 0;
                    }

                    function getVisibleCount() {
                        const inTable = tableWrap.style.display !== 'none';
                        if (inTable) {
                            return Array.from(document.querySelectorAll('#cmp-tbody .cmp-row')).filter(r => r.style.display !== 'none').length;
                        }
                        return Array.from(document.querySelectorAll('#cmp-cards .cmp-card')).filter(r => r.style.display !== 'none').length;
                    }

                    function setCount() {
                        countEl.innerText = `Tổng sản phẩm: ${getVisibleCount()}`;
                    }

                    function applyFilterToElements(elements) {
                        const q = (searchEl.value || '').toLowerCase().trim();
                        const g = groupEl.value;
                        elements.forEach(el => {
                            const hay = (el.dataset.search || '').toLowerCase();
                            const enabled = el.dataset.enabled === '1';
                            let ok = true;
                            if (q && !hay.includes(q)) ok = false;
                            if (g === 'enabled' && !enabled) ok = false;
                            if (g === 'disabled' && enabled) ok = false;
                            el.style.display = ok ? '' : 'none';
                        });
                    }

                    function sortElements(container, selector) {
                        const mode = sortEl.value;
                        const items = Array.from(container.querySelectorAll(selector));
                        const visible = items.filter(el => el.style.display !== 'none');
                        const hidden = items.filter(el => el.style.display === 'none');

                        function key(el) {
                            if (mode === 'own_asc') return parseNum(el.dataset.own) || 0;
                            if (mode === 'own_desc') return -(parseNum(el.dataset.own) || 0);
                            if (mode === 'updated_desc') return -(parseNum(el.dataset.updated) || 0);
                            if (mode === 'maxdiff_desc') return -(parseNum(el.dataset.maxdiff) || 0);
                            return parseNum(el.dataset.order) || 0;
                        }

                        visible.sort((a, b) => key(a) - key(b));

                        const all = [...visible, ...hidden];
                        all.forEach(el => container.appendChild(el));
                    }

                    function applyAll() {
                        applyFilterToElements(Array.from(document.querySelectorAll('#cmp-tbody .cmp-row')));
                        applyFilterToElements(Array.from(document.querySelectorAll('#cmp-cards .cmp-card')));
                        sortElements(document.getElementById('cmp-tbody'), '.cmp-row');
                        sortElements(cardsWrap.querySelector('div'), '.cmp-card');
                        setCount();
                    }

                    function exportCsv() {
                        const table = document.getElementById('cmp-table');
                        const rows = Array.from(document.querySelectorAll('#cmp-tbody .cmp-row')).filter(r => r.style.display !== 'none');
                        const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText.trim());

                        function esc(s) {
                            const v = String(s ?? '');
                            const needs = /[",\n]/.test(v);
                            const out = v.replace(/"/g, '""');
                            return needs ? `"${out}"` : out;
                        }

                        const lines = [];
                        lines.push(headers.map(esc).join(','));
                        rows.forEach(r => {
                            const cells = Array.from(r.querySelectorAll('td')).map(td => td.innerText.replace(/\s+/g, ' ').trim());
                            lines.push(cells.map(esc).join(','));
                        });

                        const csv = '\ufeff' + lines.join('\n');
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'shopee-compare.csv';
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        URL.revokeObjectURL(url);
                    }

                    function refreshNow() {
                        if (typeof triggerExtensionPoll === 'function') {
                            triggerExtensionPoll();
                        }
                        setTimeout(() => window.location.reload(), 600);
                    }

                    function toggleView() {
                        const nowCards = cardsWrap.style.display === 'none';
                        cardsWrap.style.display = nowCards ? '' : 'none';
                        tableWrap.style.display = nowCards ? 'none' : '';
                        viewBtn.innerText = nowCards ? 'Dạng bảng' : 'Dạng thẻ';
                        setCount();
                    }

                    function resetAll() {
                        searchEl.value = '';
                        groupEl.value = 'all';
                        sortEl.value = 'order';
                        if (cardsWrap.style.display !== 'none') {
                            toggleView();
                        }
                        applyAll();
                    }

                    document.getElementById('cmp-export').addEventListener('click', exportCsv);
                    document.getElementById('cmp-refresh').addEventListener('click', refreshNow);
                    document.getElementById('cmp-view').addEventListener('click', toggleView);
                    document.getElementById('cmp-reset').addEventListener('click', resetAll);
                    searchEl.addEventListener('input', applyAll);
                    groupEl.addEventListener('change', applyAll);
                    sortEl.addEventListener('change', applyAll);

                    applyAll();
                })();
            </script>
        </div>
    </div>
@endsection
