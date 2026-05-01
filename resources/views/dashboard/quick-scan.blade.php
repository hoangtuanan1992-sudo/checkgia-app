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
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                        <div class="field" style="margin-top:0">
                            <label class="label" for="base_url">Website shop</label>
                            <input class="input" id="base_url" name="base_url" type="url" value="{{ old('base_url', $baseUrl ?? '') }}" placeholder="https://tenmiencuaban.com" required>
                            @error('base_url')<div class="error">{{ $message }}</div>@enderror
                        </div>
                        <div class="field" style="margin-top:0">
                            <label class="label" for="sitemap_url">Sitemap (tuỳ chọn)</label>
                            <input class="input" id="sitemap_url" name="sitemap_url" type="url" value="{{ old('sitemap_url', $sitemapUrl ?? '') }}" placeholder="https://tenmiencuaban.com/sitemap.xml">
                            @error('sitemap_url')<div class="error">{{ $message }}</div>@enderror
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

                @php($urls = $urls ?? [])
                @if(is_array($urls) && count($urls) > 0)
                    <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:14px">
                        <div class="card-header" style="padding:16px 16px 6px;display:flex;justify-content:space-between;gap:10px;align-items:flex-end;flex-wrap:wrap">
                            <div>
                                <h2 class="card-title" style="font-size:18px">Kết quả quét</h2>
                                <p class="card-sub">Tìm thấy {{ number_format(count($urls), 0, ',', '.') }} link</p>
                            </div>
                            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                                <div class="field" style="margin-top:0;min-width:280px">
                                    <label class="label" for="scanSearch">Tìm trong danh sách</label>
                                    <input class="input" id="scanSearch" type="text" placeholder="Nhập từ khoá...">
                                </div>
                                <div class="actions" style="margin-top:0">
                                    <button class="btn btn-secondary" type="button" id="scanSelectAll">Chọn tất cả</button>
                                    <button class="btn btn-secondary" type="button" id="scanUnselectAll">Bỏ chọn</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            <form method="POST" action="{{ route('dashboard.quick-scan.import') }}" id="scanImportForm">
                                @csrf
                                <input type="hidden" name="base_url" value="{{ $baseUrl ?? '' }}">
                                <div class="table-wrap" style="max-height:70vh">
                                    <table class="table" style="font-size:13px">
                                        <thead>
                                            <tr>
                                                <th style="width:60px">Chọn</th>
                                                <th>URL</th>
                                            </tr>
                                        </thead>
                                        <tbody id="scanTbody">
                                            @foreach($urls as $i => $u)
                                                @php($u = (string) $u)
                                                <tr data-url="{{ $u }}">
                                                    <td style="width:60px">
                                                        <input type="checkbox" name="urls[]" value="{{ $u }}" class="scan-check">
                                                    </td>
                                                    <td style="word-break:break-word">
                                                        <a href="{{ $u }}" target="_blank">{{ $u }}</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="actions" style="justify-content:flex-end;margin-top:12px">
                                    <button class="btn" type="submit">Thêm check giá</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const search = document.getElementById('scanSearch');
            const tbody = document.getElementById('scanTbody');
            const selectAllBtn = document.getElementById('scanSelectAll');
            const unselectAllBtn = document.getElementById('scanUnselectAll');
            const checks = Array.from(document.querySelectorAll('.scan-check'));

            function normalize(s) {
                return String(s || '').toLowerCase().trim();
            }

            function applyFilter() {
                if (!tbody || !search) return;
                const q = normalize(search.value);
                const rows = Array.from(tbody.querySelectorAll('tr'));
                rows.forEach((tr) => {
                    const url = normalize(tr.dataset.url || tr.textContent || '');
                    tr.style.display = q === '' || url.includes(q) ? '' : 'none';
                });
            }

            if (search) {
                search.addEventListener('input', applyFilter);
            }

            function setAll(checked) {
                checks.forEach((c) => {
                    const tr = c.closest('tr');
                    if (tr && tr.style.display === 'none') return;
                    c.checked = checked;
                });
            }

            if (selectAllBtn) selectAllBtn.addEventListener('click', () => setAll(true));
            if (unselectAllBtn) unselectAllBtn.addEventListener('click', () => setAll(false));
        })();
    </script>
@endsection

