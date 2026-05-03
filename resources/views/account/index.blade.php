@extends('layouts.app')

@section('content')
    <div style="width:100%;max-width:1500px">
        <div class="card" style="max-width:none">
            <div class="card-header" style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start">
                <div>
                    <h1 class="card-title">Thông tin tài khoản</h1>
                    <p class="card-sub">Đổi mật khẩu, cài đặt thông báo và quản lý tài khoản con</p>
                </div>
                <div style="display:flex;gap:8px">
                    <a class="btn btn-secondary" href="{{ route('dashboard') }}">Quay lại</a>
                </div>
            </div>
            <div class="card-body">
                <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
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

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px;align-items:start">
                    <div style="display:flex;flex-direction:column;gap:14px">
                        @if(! $isImpersonating)
                            <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
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

                        <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                            <div class="card-header" style="padding:16px 16px 6px">
                                <h2 class="card-title" style="font-size:18px">Nhóm sản phẩm</h2>
                                <p class="card-sub">Dùng để lọc nhanh trên Dashboard</p>
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
                                        <div class="actions" style="margin-top:0">
                                            <button class="btn" type="submit">Thêm</button>
                                        </div>
                                    </form>

                                    @if($groups->isNotEmpty())
                                        <div class="table-wrap" style="margin-top:12px">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Tên nhóm</th>
                                                        <th style="width:80px">Sửa</th>
                                                        <th style="width:100px">Xoá</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($groups as $g)
                                                        <tr>
                                                            <td style="font-weight:600">{{ $g->name }}</td>
                                                            <td style="text-align:right">
                                                                <button
                                                                    type="button"
                                                                    class="icon-btn icon-btn-sm js-edit-group"
                                                                    data-action="{{ route('account.product-groups.update', $g) }}"
                                                                    data-group-id="{{ $g->id }}"
                                                                    data-name="{{ $g->name }}"
                                                                    title="Sửa tên nhóm"
                                                                >
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                        <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    </svg>
                                                                </button>
                                                            </td>
                                                            <td style="text-align:right">
                                                                <form method="POST" action="{{ route('account.product-groups.destroy', $g) }}" onsubmit="return confirm('Xoá nhóm này?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button class="btn" type="submit">Xoá</button>
                                                                </form>
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

                        <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                            <div class="card-header" style="padding:16px 16px 6px">
                                <h2 class="card-title" style="font-size:18px">Nhóm đối thủ</h2>
                                <p class="card-sub">Dùng để lọc nhanh cột đối thủ trên Dashboard</p>
                            </div>
                            <div class="card-body" style="padding:8px 16px 16px">
                                @if($user->isViewer())
                                    <div class="hint">Tài khoản con chỉ được xem.</div>
                                @else
                                    <form method="POST" action="{{ route('account.competitor-site-groups.store') }}" style="display:flex;flex-direction:column;gap:10px">
                                        @csrf
                                        <div class="field" style="margin-top:0">
                                            <label class="label" for="competitor_group_name">Tên nhóm đối thủ</label>
                                            <input class="input" id="competitor_group_name" name="competitor_group_name" type="text" required autocomplete="off" value="{{ old('competitor_group_name') }}">
                                            @error('competitor_group_name')<div class="error">{{ $message }}</div>@enderror
                                        </div>

                                        <div class="field" style="margin-top:0">
                                            <div class="label">Chọn đối thủ trong nhóm</div>
                                            @if(($competitorSites ?? collect())->isEmpty())
                                                <div class="hint">Bạn chưa có đối thủ.</div>
                                            @else
                                                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
                                                    @foreach($competitorSites as $s)
                                                        <label style="display:flex;gap:10px;align-items:center;border:1px solid var(--border);border-radius:12px;padding:10px 12px;background:#fff">
                                                            <input type="checkbox" name="competitor_site_ids[]" value="{{ $s->id }}" @checked(in_array((string) $s->id, array_map('strval', old('competitor_site_ids', [])), true))>
                                                            <span style="font-weight:600">{{ $s->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @error('competitor_site_ids')<div class="error">{{ $message }}</div>@enderror
                                            @endif
                                        </div>

                                        <div class="actions" style="margin-top:0;justify-content:flex-end">
                                            <button class="btn" type="submit">Thêm nhóm</button>
                                        </div>
                                    </form>

                                    @if(($competitorSiteGroups ?? collect())->isNotEmpty())
                                        <div class="table-wrap" style="margin-top:12px">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th>Tên nhóm</th>
                                                        <th style="width:120px;text-align:right">Số đối thủ</th>
                                                        <th style="width:80px">Sửa</th>
                                                        <th style="width:100px">Xoá</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($competitorSiteGroups as $g)
                                                        @php($siteIds = $g->competitorSites?->pluck('id')->all() ?? [])
                                                        <tr>
                                                            <td style="font-weight:600">{{ $g->name }}</td>
                                                            <td style="text-align:right">{{ number_format(count($siteIds), 0, ',', '.') }}</td>
                                                            <td style="text-align:right">
                                                                <button
                                                                    type="button"
                                                                    class="icon-btn icon-btn-sm js-edit-competitor-group"
                                                                    data-action="{{ route('account.competitor-site-groups.update', $g) }}"
                                                                    data-group-id="{{ $g->id }}"
                                                                    data-name="{{ $g->name }}"
                                                                    data-site-ids="{{ implode(',', $siteIds) }}"
                                                                    title="Sửa nhóm đối thủ"
                                                                >
                                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                                        <path d="M12 20h9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                        <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                    </svg>
                                                                </button>
                                                            </td>
                                                            <td style="text-align:right">
                                                                <form method="POST" action="{{ route('account.competitor-site-groups.destroy', $g) }}" onsubmit="return confirm('Xoá nhóm đối thủ này?')">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button class="btn" type="submit">Xoá</button>
                                                                </form>
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

                    <div style="display:flex;flex-direction:column;gap:14px">
                        <div class="card" style="max-width:none;border-radius:14px;box-shadow:none;margin-top:0">
                            <div class="card-header" style="padding:16px 16px 6px">
                                <h2 class="card-title" style="font-size:18px">Tài khoản con</h2>
                                <p class="card-sub">Tài khoản con chỉ có quyền xem</p>
                            </div>
                            <div class="card-body" style="padding:8px 16px 16px">
                                @if($user->isViewer())
                                    <div class="hint">Tài khoản con không thể quản lý tài khoản khác.</div>
                                @else
                                    <form method="POST" action="{{ route('account.subusers.store') }}" autocomplete="off">
                                        @csrf
                                        @php($oldSubProductGroupIds = array_map('strval', (array) old('product_group_ids', [])))
                                        @php($oldSubCompetitorGroupIds = array_map('strval', (array) old('competitor_site_group_ids', [])))
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
                                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px">
                                            <div class="field" style="margin-top:0">
                                                <div class="label">Nhóm sản phẩm</div>
                                                @include('account.partials.subuser-group-picker', [
                                                    'items' => $groups,
                                                    'selectedIds' => $oldSubProductGroupIds,
                                                    'inputName' => 'product_group_ids[]',
                                                ])
                                                <div class="hint">Không thêm nhóm nào = Tất cả</div>
                                                @error('product_group_ids')<div class="error">{{ $message }}</div>@enderror
                                                @error('product_group_ids.*')<div class="error">{{ $message }}</div>@enderror
                                            </div>
                                            <div class="field" style="margin-top:0">
                                                <div class="label">Nhóm đối thủ</div>
                                                @include('account.partials.subuser-group-picker', [
                                                    'items' => $competitorSiteGroups ?? collect(),
                                                    'selectedIds' => $oldSubCompetitorGroupIds,
                                                    'inputName' => 'competitor_site_group_ids[]',
                                                ])
                                                <div class="hint">Không thêm nhóm nào = Tất cả</div>
                                                @error('competitor_site_group_ids')<div class="error">{{ $message }}</div>@enderror
                                                @error('competitor_site_group_ids.*')<div class="error">{{ $message }}</div>@enderror
                                            </div>
                                        </div>
                                        <div class="actions">
                                            <button class="btn" type="submit">Tạo tài khoản con</button>
                                        </div>
                                    </form>

                                    @if($subUsers->isNotEmpty())
                                        <div class="table-wrap" style="margin-top:14px">
                                            @php($editSubUserId = (int) session('edit_subuser_id'))
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th style="min-width:220px">Email</th>
                                                        <th style="min-width:160px">Tạo lúc</th>
                                                        <th style="width:100px">Xem</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($subUsers as $su)
                                                        <tr>
                                                            <td>{{ $su->email }}</td>
                                                            <td>{{ $su->created_at?->format('d/m/Y H:i') }}</td>
                                                            <td style="text-align:right">
                                                                <button class="btn btn-secondary js-subuser-view" type="button" data-dialog-id="subUserDialog{{ $su->id }}">Xem</button>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                        @foreach($subUsers as $su)
                                            @php($subProductGroupIds = $editSubUserId === (int) $su->id ? array_values(array_map('intval', (array) old('product_group_ids', []))) : $su->visibleProductGroupIds())
                                            @php($subCompetitorGroupIds = $editSubUserId === (int) $su->id ? array_values(array_map('intval', (array) old('competitor_site_group_ids', []))) : $su->visibleCompetitorSiteGroupIds())
                                            @php($subProductGroupNames = $groups->whereIn('id', $subProductGroupIds)->pluck('name')->implode(', '))
                                            @php($subCompetitorGroupNames = ($competitorSiteGroups ?? collect())->whereIn('id', $subCompetitorGroupIds)->pluck('name')->implode(', '))
                                            <dialog class="dialog subuser-dialog" id="subUserDialog{{ $su->id }}" data-subuser-dialog data-open-on-load="{{ $editSubUserId === (int) $su->id ? '1' : '0' }}">
                                                <div class="dialog-header">
                                                    <div>
                                                        <div style="font-weight:800">Thông tin tài khoản con</div>
                                                        <div class="hint" style="margin-top:4px">{{ $su->email }}</div>
                                                    </div>
                                                </div>
                                                <div class="dialog-body">
                                                    <form id="subUserEditForm{{ $su->id }}" method="POST" action="{{ route('account.subusers.update', $su) }}" autocomplete="off">
                                                        @csrf
                                                        @method('PUT')
                                                        <div class="subuser-detail-grid">
                                                            <div class="field" style="margin-top:0">
                                                                <label class="label" for="subuser_name_{{ $su->id }}">Tên</label>
                                                                <input class="input" id="subuser_name_{{ $su->id }}" name="name" type="text" value="{{ $editSubUserId === (int) $su->id ? old('name', $su->name) : $su->name }}" required autocomplete="off">
                                                                @if($editSubUserId === (int) $su->id)
                                                                    @error('name')<div class="error">{{ $message }}</div>@enderror
                                                                @endif
                                                            </div>
                                                            <div class="field" style="margin-top:0">
                                                                <label class="label" for="subuser_email_{{ $su->id }}">Email</label>
                                                                <input class="input" id="subuser_email_{{ $su->id }}" name="email" type="email" value="{{ $editSubUserId === (int) $su->id ? old('email', $su->email) : $su->email }}" required autocomplete="off">
                                                                @if($editSubUserId === (int) $su->id)
                                                                    @error('email')<div class="error">{{ $message }}</div>@enderror
                                                                @endif
                                                            </div>
                                                            <div class="field" style="margin-top:0">
                                                                <label class="label">Tạo lúc</label>
                                                                <div class="pill" style="justify-content:flex-start;border-radius:10px">{{ $su->created_at?->format('d/m/Y H:i') }}</div>
                                                            </div>
                                                            <div class="field" style="margin-top:0">
                                                                <label class="label" for="subuser_password_{{ $su->id }}">Mật khẩu mới</label>
                                                                <input class="input" id="subuser_password_{{ $su->id }}" name="password" type="password" autocomplete="new-password">
                                                                <div class="hint">Để trống nếu không đổi mật khẩu</div>
                                                                @if($editSubUserId === (int) $su->id)
                                                                    @error('password')<div class="error">{{ $message }}</div>@enderror
                                                                @endif
                                                            </div>
                                                            <div class="field" style="margin-top:0">
                                                                <label class="label" for="subuser_password_confirmation_{{ $su->id }}">Nhập lại mật khẩu mới</label>
                                                                <input class="input" id="subuser_password_confirmation_{{ $su->id }}" name="password_confirmation" type="password" autocomplete="new-password">
                                                            </div>
                                                        </div>

                                                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:12px">
                                                            <div class="field" style="margin-top:0">
                                                                <div class="label">Nhóm sản phẩm</div>
                                                                @include('account.partials.subuser-group-picker', [
                                                                    'items' => $groups,
                                                                    'selectedIds' => $subProductGroupIds,
                                                                    'inputName' => 'product_group_ids[]',
                                                                ])
                                                                <div class="hint">Hiện tại: {{ $subProductGroupIds === [] ? 'Tất cả' : ($subProductGroupNames ?: 'Nhóm đã xoá') }}</div>
                                                                @if($editSubUserId === (int) $su->id)
                                                                    @error('product_group_ids')<div class="error">{{ $message }}</div>@enderror
                                                                    @error('product_group_ids.*')<div class="error">{{ $message }}</div>@enderror
                                                                @endif
                                                            </div>
                                                            <div class="field" style="margin-top:0">
                                                                <div class="label">Nhóm đối thủ</div>
                                                                @include('account.partials.subuser-group-picker', [
                                                                    'items' => $competitorSiteGroups ?? collect(),
                                                                    'selectedIds' => $subCompetitorGroupIds,
                                                                    'inputName' => 'competitor_site_group_ids[]',
                                                                ])
                                                                <div class="hint">Hiện tại: {{ $subCompetitorGroupIds === [] ? 'Tất cả' : ($subCompetitorGroupNames ?: 'Nhóm đã xoá') }}</div>
                                                                @if($editSubUserId === (int) $su->id)
                                                                    @error('competitor_site_group_ids')<div class="error">{{ $message }}</div>@enderror
                                                                    @error('competitor_site_group_ids.*')<div class="error">{{ $message }}</div>@enderror
                                                                @endif
                                                            </div>
                                                        </div>

                                                        <div class="actions" style="justify-content:flex-end;margin-top:16px">
                                                            <button type="button" class="btn btn-secondary js-subuser-dialog-close">Huỷ</button>
                                                            <button type="submit" class="btn">Lưu</button>
                                                        </div>
                                                    </form>
                                                    <form method="POST" action="{{ route('account.subusers.destroy', $su) }}" onsubmit="return confirm('Xoá tài khoản con này?')" style="margin-top:10px;display:flex;justify-content:flex-end">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-secondary" type="submit">Xoá tài khoản con</button>
                                                    </form>
                                                </div>
                                            </dialog>
                                        @endforeach
                                    @else
                                        <div class="hint">Chưa có tài khoản con.</div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php($editGroupId = session('edit_group_id'))
    <dialog class="dialog" id="groupDialog" data-edit-id="{{ $editGroupId }}">
        <div class="dialog-header">
            <div style="font-weight:700">Sửa tên nhóm</div>
        </div>
        <div class="dialog-body">
            <form id="groupDialogForm" method="POST">
                @csrf
                @method('PUT')
                <div class="field" style="margin-top:0">
                    <label class="label" for="groupDialogInput">Tên nhóm</label>
                    <input class="input" id="groupDialogInput" name="group_name" type="text" value="{{ old('group_name') }}" required autocomplete="off">
                    @error('group_name')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="actions" style="justify-content:flex-end;margin-top:16px">
                    <button type="button" class="btn btn-secondary" id="groupDialogCancel">Huỷ</button>
                    <button type="submit" class="btn">Lưu</button>
                </div>
            </form>
        </div>
    </dialog>

    @php($editCompetitorGroupId = session('edit_competitor_site_group_id'))
    @php($competitorGroupHasErrors = $errors->has('competitor_group_name') || $errors->has('competitor_site_ids') || $errors->has('competitor_site_ids.*'))
    <dialog class="dialog" id="competitorGroupDialog" data-edit-id="{{ $editCompetitorGroupId }}">
        <div class="dialog-header">
            <div style="font-weight:700">Sửa nhóm đối thủ</div>
        </div>
        <div class="dialog-body">
            <form id="competitorGroupDialogForm" method="POST">
                @csrf
                @method('PUT')
                <div class="field" style="margin-top:0">
                    <label class="label" for="competitorGroupDialogName">Tên nhóm</label>
                    <input class="input" id="competitorGroupDialogName" name="competitor_group_name" type="text" value="{{ old('competitor_group_name') }}" required autocomplete="off">
                    @error('competitor_group_name')<div class="error">{{ $message }}</div>@enderror
                </div>

                <div class="field" style="margin-top:12px">
                    <div class="label">Chọn đối thủ trong nhóm</div>
                    @if(($competitorSites ?? collect())->isEmpty())
                        <div class="hint">Bạn chưa có đối thủ.</div>
                    @else
                        <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px">
                            @foreach($competitorSites as $s)
                                <label style="display:flex;gap:10px;align-items:center;border:1px solid var(--border);border-radius:12px;padding:10px 12px;background:#fff">
                                    <input type="checkbox" class="js-competitor-group-site" name="competitor_site_ids[]" value="{{ $s->id }}" @checked(in_array((string) $s->id, array_map('strval', old('competitor_site_ids', [])), true))>
                                    <span style="font-weight:600">{{ $s->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('competitor_site_ids')<div class="error">{{ $message }}</div>@enderror
                    @endif
                </div>

                <div class="actions" style="justify-content:flex-end;margin-top:16px">
                    <button type="button" class="btn btn-secondary" id="competitorGroupDialogCancel">Huỷ</button>
                    <button type="submit" class="btn">Lưu</button>
                </div>
            </form>
        </div>
    </dialog>

    <style>
        .subuser-group-picker{display:flex;flex-direction:column;gap:8px}
        .subuser-picker-chips{min-height:42px;border:1px solid var(--border);border-radius:12px;background:#fff;padding:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .subuser-picker-chip{display:inline-flex;align-items:center;gap:8px;border:1px solid #bfdbfe;background:#eff6ff;color:#111827;border-radius:999px;padding:6px 8px 6px 10px;font-weight:700;font-size:13px}
        .subuser-picker-remove{width:22px;height:22px;border:0;border-radius:999px;background:#dbeafe;color:#1d4ed8;cursor:pointer;line-height:1;font-size:16px}
        .subuser-picker-add-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .subuser-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
        .subuser-dialog{width:min(860px, calc(100vw - 32px))}
    </style>

    <script>
        (function () {
            function refreshPicker(picker) {
                const empty = picker.querySelector('.js-picker-empty');
                const hasItems = picker.querySelectorAll('.js-picker-chip').length > 0;
                if (empty) {
                    empty.style.display = hasItems ? 'none' : '';
                }
            }

            function addChip(picker, id, name) {
                const list = picker.querySelector('.js-picker-list');
                const inputName = picker.dataset.inputName || 'group_ids[]';
                const exists = Array.from(picker.querySelectorAll('.js-picker-chip'))
                    .some((chip) => chip.dataset.id === String(id));
                if (!list || !id || exists) {
                    return;
                }

                const chip = document.createElement('span');
                chip.className = 'subuser-picker-chip js-picker-chip';
                chip.dataset.id = String(id);

                const label = document.createElement('span');
                label.textContent = name || String(id);

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = inputName;
                input.value = String(id);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'subuser-picker-remove js-picker-remove';
                remove.title = 'Xoá nhóm';
                remove.setAttribute('aria-label', 'Xoá nhóm ' + (name || id));
                remove.textContent = '×';

                chip.appendChild(label);
                chip.appendChild(input);
                chip.appendChild(remove);
                list.appendChild(chip);
                refreshPicker(picker);
            }

            document.querySelectorAll('.js-subuser-group-picker').forEach((picker) => {
                const add = picker.querySelector('.js-picker-add');
                const source = picker.querySelector('.js-picker-source');

                if (add && source) {
                    add.addEventListener('click', () => {
                        source.style.display = source.style.display === 'none' ? '' : 'none';
                        if (source.style.display !== 'none') {
                            source.focus();
                        }
                    });

                    source.addEventListener('change', () => {
                        const option = source.selectedOptions[0];
                        if (option && option.value) {
                            addChip(picker, option.value, option.textContent.trim());
                        }
                        source.value = '';
                        source.style.display = 'none';
                    });
                }

                picker.addEventListener('click', (event) => {
                    const remove = event.target.closest('.js-picker-remove');
                    if (!remove) return;
                    const chip = remove.closest('.js-picker-chip');
                    if (chip) {
                        chip.remove();
                        refreshPicker(picker);
                    }
                });

                refreshPicker(picker);
            });

            document.querySelectorAll('.js-subuser-view').forEach((btn) => {
                btn.addEventListener('click', () => {
                    const dialog = document.getElementById(btn.dataset.dialogId || '');
                    if (dialog) {
                        dialog.showModal();
                    }
                });
            });

            document.querySelectorAll('[data-subuser-dialog]').forEach((dialog) => {
                dialog.querySelectorAll('.js-subuser-dialog-close').forEach((btn) => {
                    btn.addEventListener('click', () => dialog.close());
                });
                if (dialog.dataset.openOnLoad === '1') {
                    dialog.showModal();
                }
            });
        })();
    </script>

    <script>
        (function () {
            const dialog = document.getElementById('groupDialog');
            const form = document.getElementById('groupDialogForm');
            const input = document.getElementById('groupDialogInput');
            const cancel = document.getElementById('groupDialogCancel');
            if (!dialog || !form || !input) return;

            function openDialog(action, name) {
                form.setAttribute('action', action || '');
                if (!input.value) {
                    input.value = name || '';
                }
                dialog.showModal();
                input.focus();
            }

            document.querySelectorAll('.js-edit-group').forEach((btn) => {
                btn.addEventListener('click', () => {
                    input.value = '';
                    openDialog(btn.dataset.action || '', btn.dataset.name || '');
                });
            });

            if (cancel) {
                cancel.addEventListener('click', () => dialog.close());
            }

            const editId = dialog.dataset.editId;
            if (editId) {
                const btn = document.querySelector(`.js-edit-group[data-group-id="${editId}"]`);
                if (btn) {
                    openDialog(btn.dataset.action || '', btn.dataset.name || '');
                }
            }
        })();
    </script>

    <script>
        (function () {
            const dialog = document.getElementById('competitorGroupDialog');
            const form = document.getElementById('competitorGroupDialogForm');
            const nameInput = document.getElementById('competitorGroupDialogName');
            const cancel = document.getElementById('competitorGroupDialogCancel');
            if (!dialog || !form || !nameInput) return;

            function setCheckedSiteIds(siteIds) {
                document.querySelectorAll('.js-competitor-group-site').forEach((el) => {
                    const id = el.value;
                    el.checked = siteIds.includes(String(id));
                });
            }

            function openDialog(action, name, siteIds) {
                form.setAttribute('action', action || '');
                if (!nameInput.value) {
                    nameInput.value = name || '';
                }
                if (!{{ $competitorGroupHasErrors ? 'true' : 'false' }}) {
                    setCheckedSiteIds(siteIds);
                }
                dialog.showModal();
                nameInput.focus();
            }

            document.querySelectorAll('.js-edit-competitor-group').forEach((btn) => {
                btn.addEventListener('click', () => {
                    nameInput.value = '';
                    const siteIds = String(btn.dataset.siteIds || '')
                        .split(',')
                        .map((s) => s.trim())
                        .filter(Boolean);
                    setCheckedSiteIds(siteIds);
                    openDialog(btn.dataset.action || '', btn.dataset.name || '', siteIds);
                });
            });

            if (cancel) {
                cancel.addEventListener('click', () => dialog.close());
            }

            const editId = dialog.dataset.editId;
            if (editId) {
                const btn = document.querySelector(`.js-edit-competitor-group[data-group-id="${editId}"]`);
                if (btn) {
                    const siteIds = String(btn.dataset.siteIds || '')
                        .split(',')
                        .map((s) => s.trim())
                        .filter(Boolean);
                    openDialog(btn.dataset.action || '', btn.dataset.name || '', siteIds);
                }
            }
        })();
    </script>
@endsection
