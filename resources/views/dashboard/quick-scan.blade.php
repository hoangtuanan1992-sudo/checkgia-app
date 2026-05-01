@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1100px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Quét nhanh</h1>
                    <p class="card-sub">Quét link sản phẩm từ sitemap của website shop, chọn hàng loạt và thêm vào bảng Kết quả so sánh</p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end">
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('dashboard.quick-scan.scan') }}">
                    @csrf
                    @php($sitemapVal = old('sitemap_url', $sitemapUrl ?? ''))
                    @php($showSitemap = trim((string) $sitemapVal) !== '' || $errors->has('sitemap_url'))
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                        <div class="field" style="margin-top:0">
                            <label class="label" for="base_url">Website shop</label>
                            <input class="input" id="base_url" name="base_url" type="url" value="{{ old('base_url', $baseUrl ?? '') }}" placeholder="https://tenmiencuaban.com" required>
                            @error('base_url')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field" style="margin-top:0">
                            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-end">
                                <label class="label" for="sitemap_url">Sitemap</label>
                                <button class="btn btn-secondary" type="button" id="showSitemapBtn" style="height:32px;padding:0 10px;display:{{ $showSitemap ? 'none' : '' }}">Nhập sitemap</button>
                            </div>
                            <div id="sitemapField" style="display:{{ $showSitemap ? '' : 'none' }}">
                                <input class="input" id="sitemap_url" name="sitemap_url" type="url" value="{{ $sitemapVal }}" placeholder="https://tenmiencuaban.com/sitemap.xml">
                                @error('sitemap_url')<div class="error">{{ $message }}</div>@enderror
                                <div class="hint" style="margin-top:10px">
                                    Nếu để trống, hệ thống sẽ tự tìm sitemap qua robots.txt và các đường dẫn phổ biến. Chỉ nhập khi không tự tìm được.
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-top:12px">
                        <div class="field" style="margin-top:0;min-width:220px">
                            <label class="label" for="limit">Giới hạn link</label>
                            <input class="input" id="limit" name="limit" type="number" min="1" max="5000" value="{{ old('limit', (int) ($limit ?? 2000)) }}">
                            @error('limit')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <div class="actions" style="margin-top:0">
                            <button class="btn" type="submit">Quét sitemap</button>
                        </div>
                    </div>
                </form>

                @if(!empty($scanMessage))
                    <div class="hint" style="margin-top:12px">{{ $scanMessage }}</div>
                @endif

                @php($run = $run ?? null)
                @php($items = $items ?? null)
                @if($run && $items)
                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                        <div class="card-header" style="padding:16px 16px 6px;display:flex;justify-content:space-between;gap:10px;align-items:flex-end;flex-wrap:wrap">
                            <div>
                                <h2 class="card-title" style="font-size:18px">Kết quả quét</h2>
                                <p class="card-sub">
                                    Run #{{ $run->id }} • Tìm thấy {{ number_format((int) ($run->found_urls ?? 0), 0, ',', '.') }} link • Hiển thị {{ $items->count() }}/{{ $items->total() }}
                                </p>
                            </div>
                            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                                <form method="GET" action="{{ route('dashboard.quick-scan') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                                    <input type="hidden" name="run" value="{{ $run->id }}">
                                    <div class="field" style="margin-top:0;min-width:260px">
                                        <label class="label" for="q">Tìm kiếm</label>
                                        <input class="input" id="q" name="q" type="text" value="{{ $q ?? '' }}" placeholder="Tên hoặc link...">
                                    </div>
                                    <div class="field" style="margin-top:0;min-width:160px">
                                        <label class="label" for="per_page">Số dòng</label>
                                        <select class="input" id="per_page" name="per_page">
                                            @foreach([25, 50, 100, 200] as $pp)
                                                <option value="{{ $pp }}" @selected(((int) ($perPage ?? 50)) === $pp)>{{ $pp }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="actions" style="margin-top:0">
                                        <button class="btn btn-secondary" type="submit">Lọc</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <form method="POST" action="{{ route('dashboard.quick-scan.import') }}" id="scanImportForm">
                                @csrf
                                <input type="hidden" name="run_id" value="{{ $run->id }}">
                                <div class="table-wrap" style="max-height:70vh">
                                    <table class="table" style="font-size:13px">
                                        <thead>
                                            <tr>
                                                <th>Tên sản phẩm</th>
                                                <th style="min-width:140px;text-align:right">Giá</th>
                                                <th>Link</th>
                                            </tr>
                                        </thead>
                                        <tbody id="scanTbody">
                                            @foreach($items as $item)
                                                @php($url = (string) ($item->url ?? ''))
                                                @php($name = trim((string) ($item->name ?? '')) ?: trim((string) ($item->name_guess ?? '')))
                                                @php($price = $item->price)
                                                <tr data-url="{{ $url }}" class="scan-row" style="cursor:pointer">
                                                    <td style="font-weight:600">{{ $name ?: '---' }}</td>
                                                    <td style="text-align:right;font-weight:700">
                                                        @if(!is_null($price))
                                                            {{ number_format((int) $price, 0, ',', '.') }}đ
                                                        @else
                                                            <span class="hint" style="margin-top:0">---</span>
                                                        @endif
                                                    </td>
                                                    <td style="word-break:break-word">
                                                        <a href="{{ $url }}" target="_blank" onclick="event.stopPropagation()">{{ $url }}</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:12px">
                                    <div class="hint" id="scanSelectedCount" style="margin-top:0">Đã chọn: 0</div>
                                    <div class="actions" style="margin-top:0">
                                        <button class="btn btn-secondary" type="button" id="scanSelectAll">Chọn tất cả trang</button>
                                        <button class="btn btn-secondary" type="button" id="scanUnselectAll">Bỏ chọn</button>
                                        <button class="btn" type="submit">Thêm check giá</button>
                                    </div>
                                </div>

                                @if($items->total() > 0)
                                    @php($current = (int) $items->currentPage())
                                    @php($last = (int) $items->lastPage())
                                    <div style="display:flex;justify-content:flex-end;gap:8px;align-items:center;flex-wrap:nowrap;white-space:nowrap;margin-top:10px;overflow-x:auto">
                                        @if($last > 1)
                                            @if($items->onFirstPage())
                                                <span class="btn btn-secondary" style="opacity:0.5;pointer-events:none">Trước</span>
                                            @else
                                                <a class="btn btn-secondary" href="{{ $items->previousPageUrl() }}">Trước</a>
                                            @endif

                                            @php($pagesRaw = [1, 2, 3, 4, $last, $current - 1, $current, $current + 1])
                                            @php($pages = array_values(array_unique(array_filter($pagesRaw, fn ($p) => is_int($p) && $p >= 1 && $p <= $last))))
                                            @php(sort($pages))
                                            @php($pageUrls = $items->getUrlRange(1, $last))
                                            @php($prev = 0)
                                            @foreach($pages as $p)
                                                @if($prev && $p > $prev + 1)
                                                    <span class="hint" style="margin-top:0;padding:0 2px">...</span>
                                                @endif
                                                @php($prev = $p)
                                                @if($p === $current)
                                                    <span class="btn btn-secondary" style="background:#111827;color:#fff;border-color:#111827;pointer-events:none">{{ $p }}</span>
                                                @else
                                                    <a class="btn btn-secondary" href="{{ $pageUrls[$p] ?? '' }}">{{ $p }}</a>
                                                @endif
                                            @endforeach

                                            @if($items->hasMorePages())
                                                <a class="btn btn-secondary" href="{{ $items->nextPageUrl() }}">Sau</a>
                                            @else
                                                <span class="btn btn-secondary" style="opacity:0.5;pointer-events:none">Sau</span>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const selectAllBtn = document.getElementById('scanSelectAll');
            const unselectAllBtn = document.getElementById('scanUnselectAll');
            const selectedCount = document.getElementById('scanSelectedCount');
            const form = document.getElementById('scanImportForm');
            const showSitemapBtn = document.getElementById('showSitemapBtn');
            const sitemapField = document.getElementById('sitemapField');
            const runId = @json(($run ?? null)?->id ? (int) $run->id : null);
            const storageKey = runId ? `checkgia_quick_scan_selected:${runId}` : null;
            const rows = Array.from(document.querySelectorAll('tr.scan-row'));

            function loadSelected() {
                if (!storageKey) return {};
                try {
                    const raw = sessionStorage.getItem(storageKey);
                    if (!raw) return {};
                    const arr = JSON.parse(raw);
                    if (!Array.isArray(arr)) return {};
                    const out = {};
                    arr.forEach((u) => {
                        const s = String(u || '').trim();
                        if (s) out[s] = true;
                    });
                    return out;
                } catch (e) {
                    return {};
                }
            }

            function saveSelected(map) {
                if (!storageKey) return;
                try {
                    sessionStorage.setItem(storageKey, JSON.stringify(Object.keys(map)));
                } catch (e) {
                }
            }

            function setRowSelected(tr, on) {
                if (!tr) return;
                tr.style.background = on ? 'rgba(13,110,253,.10)' : '';
            }

            function syncUi(map) {
                rows.forEach((tr) => {
                    const url = String(tr.dataset.url || '').trim();
                    setRowSelected(tr, !!map[url]);
                });
                if (selectedCount) selectedCount.textContent = `Đã chọn: ${Object.keys(map).length}`;
            }

            const selected = loadSelected();
            syncUi(selected);

            rows.forEach((tr) => {
                tr.addEventListener('click', () => {
                    const url = String(tr.dataset.url || '').trim();
                    if (!url) return;
                    if (selected[url]) delete selected[url];
                    else selected[url] = true;
                    saveSelected(selected);
                    syncUi(selected);
                });
            });

            function setAllVisible(on) {
                rows.forEach((tr) => {
                    const url = String(tr.dataset.url || '').trim();
                    if (!url) return;
                    if (on) selected[url] = true;
                    else delete selected[url];
                });
                saveSelected(selected);
                syncUi(selected);
            }

            if (selectAllBtn) selectAllBtn.addEventListener('click', () => setAllVisible(true));
            if (unselectAllBtn) unselectAllBtn.addEventListener('click', () => setAllVisible(false));
            if (showSitemapBtn && sitemapField) {
                showSitemapBtn.addEventListener('click', () => {
                    sitemapField.style.display = '';
                    showSitemapBtn.style.display = 'none';
                    const input = document.getElementById('sitemap_url');
                    if (input) input.focus();
                });
            }

            if (form) {
                form.addEventListener('submit', (e) => {
                    const urls = Object.keys(selected);
                    if (urls.length === 0) {
                        e.preventDefault();
                        alert('Vui lòng chọn ít nhất 1 sản phẩm.');
                        return;
                    }
                    form.querySelectorAll('input[name="urls[]"]').forEach((n) => n.remove());
                    urls.forEach((u) => {
                        const inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'urls[]';
                        inp.value = u;
                        form.appendChild(inp);
                    });
                });
            }
        })();
    </script>
@endsection
