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
                                        <div class="actions">
                                            <button class="btn" type="submit">Tạo tài khoản con</button>
                                        </div>
                                    </form>

                                    @if($subUsers->isNotEmpty())
                                        <div class="table-wrap" style="margin-top:14px">
                                            <table class="table">
                                                <thead>
                                                    <tr>
                                                        <th style="min-width:200px">Tên</th>
                                                        <th style="min-width:240px">Email</th>
                                                        <th style="min-width:160px">Tạo lúc</th>
                                                        <th style="width:100px">Xoá</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($subUsers as $su)
                                                        <tr>
                                                            <td style="font-weight:600">{{ $su->name }}</td>
                                                            <td>{{ $su->email }}</td>
                                                            <td>{{ $su->created_at?->format('d/m/Y H:i') }}</td>
                                                            <td style="text-align:right">
                                                                <form method="POST" action="{{ route('account.subusers.destroy', $su) }}" onsubmit="return confirm('Xoá tài khoản con này?')">
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
@endsection
