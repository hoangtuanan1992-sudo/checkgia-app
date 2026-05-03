@php
    $items = $items ?? collect();
    $selectedIds = array_values(array_unique(array_map('intval', (array) ($selectedIds ?? []))));
    $selectedIdStrings = array_map('strval', $selectedIds);
    $inputName = $inputName ?? 'group_ids[]';
    $emptyText = $emptyText ?? 'Tất cả';
    $selectedItems = $items->filter(fn ($item) => in_array((string) $item->id, $selectedIdStrings, true));
@endphp

<div class="subuser-group-picker js-subuser-group-picker" data-empty-text="{{ $emptyText }}" data-input-name="{{ $inputName }}">
    <div class="subuser-picker-chips js-picker-list">
        <span class="hint js-picker-empty" style="margin-top:0;{{ $selectedItems->isNotEmpty() ? 'display:none' : '' }}">{{ $emptyText }}</span>
        @foreach($selectedItems as $item)
            <span class="subuser-picker-chip js-picker-chip" data-id="{{ $item->id }}">
                <span>{{ $item->name }}</span>
                <input type="hidden" name="{{ $inputName }}" value="{{ $item->id }}">
                <button type="button" class="subuser-picker-remove js-picker-remove" title="Xoá nhóm" aria-label="Xoá nhóm {{ $item->name }}">×</button>
            </span>
        @endforeach
    </div>

    @if($items->isNotEmpty())
        <div class="subuser-picker-add-row">
            <button type="button" class="icon-btn icon-btn-sm js-picker-add" title="Thêm nhóm" aria-label="Thêm nhóm">+</button>
            <select class="input js-picker-source" style="display:none;min-width:220px">
                <option value="">-- Chọn nhóm --</option>
                @foreach($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
</div>
