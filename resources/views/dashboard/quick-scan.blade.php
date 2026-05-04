@extends('layouts.app')

@section('content')
    @php
        $websiteUrl = (string) ($websiteUrl ?? '');
        $websiteKey = (string) ($websiteKey ?? '');
        $q = (string) ($q ?? '');
        $perPage = (int) ($perPage ?? 200);
        $products = $products ?? null;
        $productGroups = collect($productGroups ?? []);
        $formatPrice = static function (int $value, string $text = ''): string {
            if ($value > 0) {
                return number_format($value, 0, ',', '.').'đ';
            }

            return trim($text) !== '' ? $text : '---';
        };
        $requestStatus = (string) ($latestRequest->status ?? '');
        $requestStatusText = [
            'pending' => 'Đang chờ phần mềm Windows nhận lệnh',
            'running' => 'Phần mềm Windows đang quét',
            'completed' => 'Đã quét xong',
            'failed' => 'Quét lỗi',
        ][$requestStatus] ?? '';
    @endphp

    <div style="width:100%;max-width:1500px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Quét nhanh</h1>
                    <p class="card-sub">Hiển thị dữ liệu sản phẩm phần mềm Windows đã đẩy vào Check Giá.</p>
                </div>
                <div class="actions" style="margin-top:0">
                    <a class="btn btn-secondary" href="{{ request()->fullUrl() }}">Làm mới</a>
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
                </div>
            </div>

            <div class="card-body">
                @if(session('status'))
                    <div class="status">{{ session('status') }}</div>
                @endif

                @if(!empty($error))
                    <div style="border:1px solid rgba(220,53,69,.25);background:rgba(220,53,69,.08);color:#991b1b;border-radius:12px;padding:14px;margin-bottom:14px">
                        {{ $error }}
                    </div>
                @endif

                <form method="GET" action="{{ route('dashboard.quick-scan') }}" style="display:grid;grid-template-columns:minmax(280px,1fr) minmax(220px,340px) minmax(130px,180px) auto;gap:14px;align-items:end">
                    <div class="field" style="margin-top:0">
                        <label class="label" for="website_url">Link website muốn hiển thị</label>
                        <input class="input" id="website_url" name="website_url" value="{{ $websiteUrl }}" placeholder="https://dienmaydo.vn/" autocomplete="off">
                        @error('website_url')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field" style="margin-top:0">
                        <label class="label" for="q">Tìm kiếm</label>
                        <input class="input" id="q" name="q" value="{{ $q }}" placeholder="Tên, mã hoặc link...">
                    </div>
                    <div class="field" style="margin-top:0">
                        <label class="label" for="per_page">Số dòng</label>
                        <select class="input" id="per_page" name="per_page">
                            @foreach([50, 100, 200, 500] as $pp)
                                <option value="{{ $pp }}" @selected($perPage === $pp)>{{ $pp }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="btn" type="submit">Quét</button>
                </form>

                @if($websiteUrl === '')
                    <div class="hint" style="margin-top:16px">Hãy nhập link website rồi bấm Quét để hiển thị dữ liệu.</div>
                @elseif(!$selectedJob && empty($error))
                    <div style="margin-top:16px;border:1px solid rgba(13,110,253,.22);background:rgba(13,110,253,.06);border-radius:12px;padding:16px">
                        <div style="font-weight:800;margin-bottom:6px">Website này chưa được quét.</div>
                        <div class="hint" style="margin-top:0">Bạn có muốn quét không? Sau khi gửi lệnh, vui lòng chờ tầm 1 ngày để phần mềm Windows quét xong và đẩy dữ liệu lên.</div>
                        @if($requestStatusText !== '')
                            <div class="hint">Trạng thái yêu cầu gần nhất: <strong>{{ $requestStatusText }}</strong></div>
                        @endif
                        <form method="POST" action="{{ route('dashboard.quick-scan.request') }}" class="actions">
                            @csrf
                            <input type="hidden" name="website_url" value="{{ $websiteUrl }}">
                            <button class="btn" type="submit">Gửi lệnh quét</button>
                        </form>
                    </div>
                @endif

                @if($selectedJob)
                    <div style="margin-top:16px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px">
                        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:#fff">
                            <div class="label">Website</div>
                            <div style="font-weight:800;word-break:break-word">{{ $websiteUrl }}</div>
                        </div>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:#fff">
                            <div class="label">Trạng thái</div>
                            <div class="pill" style="margin-top:6px;color:#047857;background:#ecfdf5;border-color:#bbf7d0">Imported</div>
                        </div>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:#fff">
                            <div class="label">Sản phẩm</div>
                            <div style="font-weight:800">{{ number_format((int) ($selectedJob->imported_product_count ?? $selectedJob->product_count ?? $products->total()), 0, ',', '.') }}</div>
                        </div>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:#fff">
                            <div class="label">Sản phẩm có giá</div>
                            <div style="font-weight:800">{{ number_format((int) ($selectedJob->priced_product_count ?? 0), 0, ',', '.') }}</div>
                        </div>
                        <div style="border:1px solid var(--border);border-radius:10px;padding:12px;background:#fff">
                            <div class="label">Cập nhật</div>
                            <div style="font-weight:800">{{ $selectedJob->last_pushed_at ?? $selectedJob->updated_at ?? '---' }}</div>
                        </div>
                    </div>

                    <div class="hint">
                        Endpoint nhận dữ liệu: https://checkgia.id.vn/api/products/import - Hiển thị {{ number_format($products->count(), 0, ',', '.') }}/{{ number_format($products->total(), 0, ',', '.') }} dòng
                    </div>
                @endif

                <div class="table-wrap" style="margin-top:14px;max-height:70vh">
                    <table class="table">
                        <thead>
                            <tr>
                                <th style="width:58px">#</th>
                                <th style="min-width:150px">Mã</th>
                                <th style="min-width:320px">Tên sản phẩm</th>
                                <th style="min-width:130px;text-align:right">Giá</th>
                                <th style="min-width:280px">Link sản phẩm</th>
                                <th style="min-width:160px">Cập nhật</th>
                                <th style="width:88px;text-align:center">
                                    <input id="quickScanCheckPage" type="checkbox" style="width:24px;height:24px">
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products ?? [] as $idx => $product)
                                @php
                                    $rowNumber = method_exists($products, 'firstItem') ? (($products->firstItem() ?? 1) + $idx) : ($idx + 1);
                                    $url = (string) ($product['url'] ?? '');
                                @endphp
                                <tr data-quick-scan-row>
                                    <td>{{ $rowNumber }}</td>
                                    <td>{{ ($product['code'] ?? '') !== '' ? $product['code'] : '---' }}</td>
                                    <td style="font-weight:700">{{ ($product['name'] ?? '') !== '' ? $product['name'] : '---' }}</td>
                                    <td style="text-align:right;font-weight:800">{{ $formatPrice((int) ($product['priceValue'] ?? 0), (string) ($product['priceText'] ?? '')) }}</td>
                                    <td style="word-break:break-word">
                                        @if($url !== '')
                                            <a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a>
                                        @else
                                            ---
                                        @endif
                                    </td>
                                    <td>{{ ($product['updatedAt'] ?? '') !== '' ? $product['updatedAt'] : '---' }}</td>
                                    <td style="text-align:center">
                                        <input data-quick-scan-checkbox type="checkbox" value="{{ (int) ($product['id'] ?? 0) }}" style="width:24px;height:24px">
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="text-align:center;color:var(--muted)">
                                        @if($websiteUrl === '')
                                            Hãy nhập link website rồi bấm Quét để hiển thị dữ liệu.
                                        @elseif($selectedJob)
                                            Chưa có sản phẩm phù hợp để hiển thị.
                                        @else
                                            Website này chưa được quét.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($products && $products->lastPage() > 1)
                    <div class="actions" style="justify-content:flex-end;flex-wrap:wrap">
                        <span class="hint" style="margin-top:0">Trang {{ $products->currentPage() }}/{{ $products->lastPage() }}</span>
                        @if($products->onFirstPage())
                            <span class="btn btn-secondary" style="opacity:.55;pointer-events:none">Trước</span>
                        @else
                            <a class="btn btn-secondary" href="{{ $products->previousPageUrl() }}">Trước</a>
                        @endif
                        @if($products->hasMorePages())
                            <a class="btn btn-secondary" href="{{ $products->nextPageUrl() }}">Sau</a>
                        @else
                            <span class="btn btn-secondary" style="opacity:.55;pointer-events:none">Sau</span>
                        @endif
                    </div>
                @endif

                @if($selectedJob)
                    <div style="margin-top:14px;border:1px solid #bfdbfe;background:#eff6ff;border-radius:10px;padding:12px;display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
                        <strong>Đã chọn <span id="quickScanSelectedCount">0</span> sản phẩm</strong>
                        <div class="actions" style="margin-top:0">
                            <button class="btn btn-secondary" type="button" id="quickScanSelectAll">Chọn tất cả</button>
                            <button class="btn btn-secondary" type="button" id="quickScanClear">Bỏ chọn</button>
                            <form id="quickScanAddForm" method="POST" action="{{ route('dashboard.quick-scan.add-to-compare') }}" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap">
                                @csrf
                                <input type="hidden" name="website_url" value="{{ $websiteUrl }}">
                                <input type="hidden" id="quickScanIdsJson" name="scanner_product_ids_json" value="[]">
                                <div class="field" style="margin-top:0;min-width:220px">
                                    <select class="input" id="quickScanProductGroup" name="product_group_id">
                                        <option value="">-- Không chọn nhóm --</option>
                                        @foreach($productGroups as $group)
                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn" type="submit" id="quickScanAddButton" disabled>Thêm so sánh giá</button>
                            </form>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const checkboxes = Array.from(document.querySelectorAll('[data-quick-scan-checkbox]'));
            if (!checkboxes.length) return;

            const storageKey = 'checkgia.quickScan.selected.' + @json($websiteKey !== '' ? $websiteKey : 'default');
            const allProductIds = @json(collect($allProductIds ?? [])->map(fn ($id) => (string) $id)->values());
            const selectedCount = document.getElementById('quickScanSelectedCount');
            const checkPage = document.getElementById('quickScanCheckPage');
            const selectAll = document.getElementById('quickScanSelectAll');
            const clear = document.getElementById('quickScanClear');
            const form = document.getElementById('quickScanAddForm');
            const idsJson = document.getElementById('quickScanIdsJson');
            const addButton = document.getElementById('quickScanAddButton');

            let selected = new Set();
            try {
                selected = new Set(JSON.parse(localStorage.getItem(storageKey) || '[]').map(String));
            } catch (error) {
                selected = new Set();
            }

            const save = () => localStorage.setItem(storageKey, JSON.stringify(Array.from(selected)));
            const allFilteredSelected = () => allProductIds.length > 0 && allProductIds.every((id) => selected.has(String(id)));
            const anyFilteredSelected = () => allProductIds.some((id) => selected.has(String(id)));
            const paintRow = (checkbox) => {
                const row = checkbox.closest('tr');
                if (!row) return;
                const color = checkbox.checked ? '#eef6ff' : '';
                row.querySelectorAll('td').forEach((cell) => {
                    cell.style.backgroundColor = color;
                });
            };
            const render = () => {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = selected.has(String(checkbox.value));
                    paintRow(checkbox);
                });

                if (selectedCount) selectedCount.textContent = String(selected.size);
                if (idsJson) idsJson.value = JSON.stringify(Array.from(selected));
                if (addButton) addButton.disabled = selected.size === 0;
                if (checkPage) {
                    checkPage.checked = allFilteredSelected();
                    checkPage.indeterminate = anyFilteredSelected() && !checkPage.checked;
                }
            };

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        selected.add(String(checkbox.value));
                    } else {
                        selected.delete(String(checkbox.value));
                    }
                    save();
                    render();
                });
            });

            if (checkPage) {
                checkPage.addEventListener('change', () => {
                    allProductIds.forEach((id) => {
                        if (checkPage.checked) {
                            selected.add(String(id));
                        } else {
                            selected.delete(String(id));
                        }
                    });
                    save();
                    render();
                });
            }

            if (selectAll) {
                selectAll.addEventListener('click', () => {
                    allProductIds.forEach((id) => selected.add(String(id)));
                    save();
                    render();
                });
            }

            if (clear) {
                clear.addEventListener('click', () => {
                    selected.clear();
                    save();
                    render();
                });
            }

            if (form) {
                form.addEventListener('submit', (event) => {
                    if (selected.size === 0) {
                        event.preventDefault();
                        return;
                    }

                    if (idsJson) idsJson.value = JSON.stringify(Array.from(selected));
                });
            }

            render();
        })();
    </script>
@endsection
