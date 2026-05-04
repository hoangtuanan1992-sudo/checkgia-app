@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1500px">
        <style>
            .account-grid{display:grid;grid-template-columns:minmax(0,0.9fr) minmax(0,1.1fr);gap:14px;align-items:start}
            .account-inner-card{max-width:none;border-radius:14px;box-shadow:none;margin-top:0}
            .account-check-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;max-height:172px;overflow:auto;border:1px solid var(--border);border-radius:12px;padding:10px;background:#fff}
            .account-check-item{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text);min-width:0}
            .account-check-item input{width:18px;height:18px;flex:0 0 auto}
            .account-check-item span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            .account-row-actions{display:flex;gap:8px;align-items:center;justify-content:flex-end;flex-wrap:wrap}
            .account-compact-table td{vertical-align:middle}
            .account-detail-row{display:none}
            .account-detail-row.is-open{display:table-row}
            .account-tag-list{display:flex;gap:6px;flex-wrap:wrap}
            .account-tag{display:inline-flex;padding:5px 8px;border-radius:999px;border:1px solid #dbeafe;background:#eff6ff;color:#1d4ed8;font-size:12px}
            @media (max-width: 980px){
                .account-grid{grid-template-columns:1fr}
                .account-check-list{grid-template-columns:1fr}
            }
        </style>

        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Thông tin tài khoản</h1>
                    <p class="card-sub">Đổi mật khẩu, cài đặt thông báo và quản lý tài khoản con</p>
                </div>
                <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
            </div>

            <div class="card-body">
                <div class="card account-inner-card">
                    <div class="card-body" style="padding:16px">
                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap">
                            <div style="display:flex;gap:12px;align-items:center;min-width:280px">
                                <div style="width:44px;height:44px;border-radius:999px;background:#eef2ff;border:1px solid #e0e7ff;color:#3730a3;display:flex;align-items:center;justify-content:center;font-weight:700">
                                    {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                                </div>
                                <div style="display:flex;flex-direction:column;gap:2px">
                                    <div style="font-weight:700">{{ $user->name }}</div>
                                    <div class="hint" style="margin-top:0">{{ $user->email }}</div>
                                </div>
                            </div>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
                                @if($user->isViewer())
                                    <span class="pill">Tài khoản con (chỉ xem)</span>
                                @else
                                    <span class="pill">Tài khoản chính</span>
                                @endif
                            </div>
                        </div>

                        @if($owner && $owner->service_start_date && $owner->service_end_date)
                            <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                                <span class="pill">Thời hạn: {{ $owner->service_start_date->format('d/m/Y') }} - {{ $owner->service_end_date->format('d/m/Y') }}</span>
                                <span class="pill">{{ $owner->serviceRemainingText() }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="account-grid" style="margin-top:14px">
                    <div style="display:flex;flex-direction:column;gap:14px">
                        @if(! $isImpersonating)
                            <div class="card account-inner-card">
                                <div class="card-header" style="padding:16px 16px 6px">
                                    <h2 class="card-title" style="font-size:18px">Đổi mật khẩu</h2>
                                </div>
                                <div class="card-body" style="padding:8px 16px 16px">
                                    <form method="POST" action="{{ route('account.password') }}" autocomplete="off">
                                        @csrf
                                        @method('PUT')
                                        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="current_password">Mật khẩu hiện tại</label>
                                                <input class="input" id="current_password" name="current_password" type="password" required autocomplete="off">
                                                @error('current_password')<div class="error">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="password">Mật khẩu mới</label>
                                                <input class="input" id="password" name="password" type="password" required autocomplete="new-password">
                                                @error('password')<div class="error">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <label class="label" for="password_confirmation">Nhập lại</label>
                                                <input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                                            </div>
                                        </div>
                                        <div class="actions">
                                            <button class="btn" type="submit">Lưu mật khẩu</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <div class="card account-inner-card">
                            <div class="card-header" style="padding:16px 16px 6px">
                                <h2 class="card-title" style="font-size:18px">Nhóm sản phẩm</h2>
                                <p class="card-sub">Dùng để lọc sản phẩm và phân quyền cho tài khoản con</p>
                            </div>
                            <div class="card-body" style="padding:8px 16px 16px">
                                @if($user->isViewer())
                                    <div class="hint">Tài khoản con chỉ được xem.</div>
                                @else
                                    <form method="POST" action="{{ route('account.product-groups.store') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
                                        @csrf
                                        <div class="field" style="margin-top:0;flex:1;min-width:240px">
                                            <label class="label" for="group_name">Tên nhóm</label>
                                            <input class="input" id="group_name" name="name" type="text" required autocomplete="off">
                                            @error('name')<div class="error">{{ $message }}</div>@enderror
                                        </div>
                                        <button class="btn" type="submit">Thêm</button>
                                    </form>

                                    @if($groups->isNotEmpty())
                                        <div class="table-wrap" style="margin-top:12px;max-height:360px">
                                            <table class="table account-compact-table">
                                                <thead>
                                                    <tr>
                                                        <th>Tên nhóm</th>
                                                        <th style="width:190px">Thao tác</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($groups as $g)
                                                        <tr>
                                                            <td>
                                                                <form method="POST" action="{{ route('account.product-groups.update-post', $g) }}" style="display:flex;gap:8px;align-items:center">
                                                                    @csrf
                                                                    <input class="input" name="name" type="text" value="{{ $g->name }}" required style="flex:1;min-width:160px">
                                                                    <button class="btn btn-secondary" type="submit">Sửa</button>
                                                                </form>
                                                            </td>
                                                            <td>
                                                                <div class="account-row-actions">
                                                                    <form method="POST" action="{{ route('account.product-groups.delete-post', $g) }}" onsubmit="return confirm('Xoá nhóm sản phẩm này?')">
                                                                        @csrf
                                                                        <button class="btn" type="submit">Xoá</button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="hint">Chưa có nhóm.</div>
                                    @endif
                                @endif
                            </div>
                        </div>

                        <div class="card account-inner-card">
                            <div class="card-header" style="padding:16px 16px 6px">
                                <h2 class="card-title" style="font-size:18px">Nhóm đối thủ</h2>
                                <p class="card-sub">Gom các website đối thủ để phân quyền cho tài khoản con</p>
                            </div>
                            <div class="card-body" style="padding:8px 16px 16px">
                                @if($user->isViewer())
                                    <div class="hint">Tài khoản con chỉ được xem.</div>
                                @elseif($competitorSites->isEmpty())
                                    <div class="hint">Chưa có website đối thủ. Hãy thêm trong phần Cài đặt trước.</div>
                                @else
                                    <form method="POST" action="{{ route('account.competitor-site-groups.store') }}">
                                        @csrf
                                        <div class="field" style="margin-top:0">
                                            <label class="label" for="competitor_group_name">Tên nhóm</label>
                                            <input class="input" id="competitor_group_name" name="name" type="text" required autocomplete="off">
                                        </div>
                                        <div class="field">
                                            <label class="label">Website đối thủ trong nhóm</label>
                                            <div class="account-check-list">
                                                @foreach($competitorSites as $site)
                                                    <label class="account-check-item">
                                                        <input type="checkbox" name="competitor_site_ids[]" value="{{ $site->id }}">
                                                        <span>{{ $site->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                        <div class="actions">
                                            <button class="btn" type="submit">Thêm nhóm đối thủ</button>
                                        </div>
                                    </form>

                                    @if($competitorGroups->isNotEmpty())
                                        <div class="table-wrap" style="margin-top:12px;max-height:460px">
                                            <table class="table account-compact-table">
                                                <thead>
                                                    <tr>
                                                        <th style="min-width:210px">Tên nhóm</th>
                                                        <th>Website</th>
                                                        <th style="width:190px">Thao tác</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($competitorGroups as $cg)
                                                        @php($selectedSiteIds = $cg->competitorSites->pluck('id')->map(fn ($id) => (int) $id)->all())
                                                        <tr>
                                                            <td>
                                                                <form id="competitor-group-form-{{ $cg->id }}" method="POST" action="{{ route('account.competitor-site-groups.update', $cg) }}">
                                                                    @csrf
                                                                    @method('PUT')
                                                                    <input class="input" name="name" type="text" value="{{ $cg->name }}" required>
                                                                    <div class="account-check-list" style="grid-template-columns:1fr;margin-top:8px;max-height:150px">
                                                                        @foreach($competitorSites as $site)
                                                                            <label class="account-check-item">
                                                                                <input type="checkbox" name="competitor_site_ids[]" value="{{ $site->id }}" @checked(in_array((int) $site->id, $selectedSiteIds, true))>
                                                                                <span>{{ $site->name }}</span>
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                </form>
                                                            </td>
                                                            <td>
                                                                <div class="account-tag-list">
                                                                    @forelse($cg->competitorSites as $site)
                                                                        <span class="account-tag">{{ $site->name }}</span>
                                                                    @empty
                                                                        <span class="hint" style="margin-top:0">Chưa chọn website</span>
                                                                    @endforelse
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <div class="account-row-actions">
                                                                    <button class="btn btn-secondary" type="submit" form="competitor-group-form-{{ $cg->id }}">Sửa</button>
                                                                    <form method="POST" action="{{ route('account.competitor-site-groups.destroy', $cg) }}" onsubmit="return confirm('Xoá nhóm đối thủ này?')">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button class="btn" type="submit">Xoá</button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @else
                                        <div class="hint">Chưa có nhóm đối thủ.</div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card account-inner-card">
                        <div class="card-header" style="padding:16px 16px 6px">
                            <h2 class="card-title" style="font-size:18px">Tài khoản con</h2>
                            <p class="card-sub">Tài khoản con chỉ có quyền xem. Không chọn nhóm nào = Tất cả.</p>
                        </div>
                        <div class="card-body" style="padding:8px 16px 16px">
                            @if($user->isViewer())
                                <div class="hint">Tài khoản con không thể quản lý tài khoản khác.</div>
                            @else
                                <form method="POST" action="{{ route('account.subusers.store') }}" autocomplete="off">
                                    @csrf
                                    <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px">
                                        <div class="field" style="margin-top:0">
                                            <label class="label" for="sub_name">Tên</label>
                                            <input class="input" id="sub_name" name="name" type="text" value="{{ old('name') }}" required autocomplete="off">
                                            @error('name')<div class="error">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="field" style="margin-top:0">
                                            <label class="label" for="sub_email">Email</label>
                                            <input class="input" id="sub_email" name="email" type="email" value="{{ old('email') }}" required autocomplete="off">
                                            @error('email')<div class="error">{{ $message }}</div>@enderror
                                        </div>
                                        <div class="field" style="margin-top:0">
                                            <label class="label" for="sub_password">Mật khẩu</label>
                                            <input class="input" id="sub_password" name="password" type="password" required autocomplete="new-password">
                                            @error('password')<div class="error">{{ $message }}</div>@enderror
                                        </div>
                                    </div>
                                    <div class="field" style="margin-top:12px">
                                        <label class="label" for="sub_password_confirmation">Nhập lại mật khẩu</label>
                                        <input class="input" id="sub_password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                                    </div>
                                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                        <div class="field" style="margin-top:0">
                                            <label class="label">Nhóm sản phẩm được xem</label>
                                            <div class="account-check-list" style="grid-template-columns:1fr">
                                                @forelse($groups as $g)
                                                    <label class="account-check-item">
                                                        <input type="checkbox" name="product_group_ids[]" value="{{ $g->id }}">
                                                        <span>{{ $g->name }}</span>
                                                    </label>
                                                @empty
                                                    <span class="hint" style="margin-top:0">Chưa có nhóm sản phẩm</span>
                                                @endforelse
                                            </div>
                                        </div>
                                        <div class="field" style="margin-top:0">
                                            <label class="label">Nhóm đối thủ được xem</label>
                                            <div class="account-check-list" style="grid-template-columns:1fr">
                                                @forelse($competitorGroups as $cg)
                                                    <label class="account-check-item">
                                                        <input type="checkbox" name="competitor_site_group_ids[]" value="{{ $cg->id }}">
                                                        <span>{{ $cg->name }}</span>
                                                    </label>
                                                @empty
                                                    <span class="hint" style="margin-top:0">Chưa có nhóm đối thủ</span>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>
                                    <div class="actions">
                                        <button class="btn" type="submit">Tạo tài khoản con</button>
                                    </div>
                                </form>

                                @if($subUsers->isNotEmpty())
                                    <div class="table-wrap" style="margin-top:14px;max-height:720px">
                                        <table class="table account-compact-table">
                                            <thead>
                                                <tr>
                                                    <th style="min-width:240px">Email</th>
                                                    <th style="min-width:150px">Tạo lúc</th>
                                                    <th style="width:100px">Xem</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($subUsers as $su)
                                                    @php($suProductIds = $su->visibleProductGroupIds())
                                                    @php($suCompetitorGroupIds = $su->visibleCompetitorSiteGroupIds())
                                                    <tr>
                                                        <td>
                                                            <div style="font-weight:700">{{ $su->email }}</div>
                                                            <div class="hint" style="margin-top:3px">{{ $su->name }}</div>
                                                        </td>
                                                        <td>{{ $su->created_at?->format('d/m/Y H:i') }}</td>
                                                        <td style="text-align:right">
                                                            <button class="btn btn-secondary" type="button" data-subuser-toggle="{{ $su->id }}">Xem</button>
                                                        </td>
                                                    </tr>
                                                    <tr class="account-detail-row" data-subuser-detail="{{ $su->id }}">
                                                        <td colspan="3">
                                                            <form method="POST" action="{{ route('account.subusers.update', $su) }}" autocomplete="off">
                                                                @csrf
                                                                @method('PUT')
                                                                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                                                    <div class="field" style="margin-top:0">
                                                                        <label class="label">Tên</label>
                                                                        <input class="input" name="name" type="text" value="{{ $su->name }}" required autocomplete="off">
                                                                    </div>
                                                                    <div class="field" style="margin-top:0">
                                                                        <label class="label">Email</label>
                                                                        <input class="input" name="email" type="email" value="{{ $su->email }}" required autocomplete="off">
                                                                    </div>
                                                                    <div class="field" style="margin-top:0">
                                                                        <label class="label">Mật khẩu mới</label>
                                                                        <input class="input" name="password" type="password" autocomplete="new-password" placeholder="Để trống nếu không đổi">
                                                                    </div>
                                                                    <div class="field" style="margin-top:0">
                                                                        <label class="label">Nhập lại mật khẩu mới</label>
                                                                        <input class="input" name="password_confirmation" type="password" autocomplete="new-password">
                                                                    </div>
                                                                </div>
                                                                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px">
                                                                    <div class="field" style="margin-top:0">
                                                                        <label class="label">Nhóm sản phẩm</label>
                                                                        <div class="account-check-list" style="grid-template-columns:1fr">
                                                                            @forelse($groups as $g)
                                                                                <label class="account-check-item">
                                                                                    <input type="checkbox" name="product_group_ids[]" value="{{ $g->id }}" @checked(in_array((int) $g->id, $suProductIds, true))>
                                                                                    <span>{{ $g->name }}</span>
                                                                                </label>
                                                                            @empty
                                                                                <span class="hint" style="margin-top:0">Chưa có nhóm sản phẩm</span>
                                                                            @endforelse
                                                                        </div>
                                                                    </div>
                                                                    <div class="field" style="margin-top:0">
                                                                        <label class="label">Nhóm đối thủ</label>
                                                                        <div class="account-check-list" style="grid-template-columns:1fr">
                                                                            @forelse($competitorGroups as $cg)
                                                                                <label class="account-check-item">
                                                                                    <input type="checkbox" name="competitor_site_group_ids[]" value="{{ $cg->id }}" @checked(in_array((int) $cg->id, $suCompetitorGroupIds, true))>
                                                                                    <span>{{ $cg->name }}</span>
                                                                                </label>
                                                                            @empty
                                                                                <span class="hint" style="margin-top:0">Chưa có nhóm đối thủ</span>
                                                                            @endforelse
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="actions" style="justify-content:space-between;flex-wrap:wrap">
                                                                    <div class="hint" style="margin-top:0">
                                                                        Nhóm sản phẩm:
                                                                        {{ $suProductIds === [] ? 'Tất cả' : $groups->whereIn('id', $suProductIds)->pluck('name')->join(', ') }}
                                                                        <br>
                                                                        Nhóm đối thủ:
                                                                        {{ $suCompetitorGroupIds === [] ? 'Tất cả' : $competitorGroups->whereIn('id', $suCompetitorGroupIds)->pluck('name')->join(', ') }}
                                                                    </div>
                                                                    <button class="btn" type="submit">Lưu</button>
                                                                </div>
                                                            </form>
                                                            <form method="POST" action="{{ route('account.subusers.destroy', $su) }}" onsubmit="return confirm('Xoá tài khoản con này?')" style="margin-top:10px;display:flex;justify-content:flex-end">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button class="btn" type="submit">Xoá tài khoản con</button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="hint">Chưa có tài khoản con.</div>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            document.querySelectorAll('[data-subuser-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const id = button.getAttribute('data-subuser-toggle');
                    const row = document.querySelector(`[data-subuser-detail="${id}"]`);
                    if (!row) return;
                    row.classList.toggle('is-open');
                    button.textContent = row.classList.contains('is-open') ? 'Ẩn' : 'Xem';
                });
            });
        </script>
    </div>
@endsection
