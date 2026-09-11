{{-- "Not in inventory yet — add it?", shown under an item picker when what was
     typed matches nothing on the shelf. Purchases, Market purchases and the
     invoice-scan review screen all carry it; the behaviour lives in
     partials/item-picker, which finds this panel beside its input.

     Rendered inside a JS template literal on the purchase forms, so nothing in
     here may contain a backtick or a dollar-brace. --}}
@can('record-inventory')
<div class="new-item" hidden style="margin-top:6px; padding:8px; background:hsl(30,15%,96%); border:1px solid hsl(30,15%,88%); border-radius:6px;">
    <div style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:6px;">
        Not in inventory yet. Add <strong class="new-item-name"></strong>?
    </div>
    <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:6px;">
        <select class="new-item-category" style="padding:6px 8px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px;">
            @foreach(\App\Models\InventoryItem::CATEGORIES as $cat)
                <option value="{{ $cat }}">{{ $cat }}</option>
            @endforeach
        </select>
        <select class="new-item-unit" style="padding:6px 8px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px;">
            @foreach(\App\Models\InventoryItem::UNITS as $u)
                <option value="{{ $u }}">{{ $u }}</option>
            @endforeach
        </select>
        <button type="button" class="new-item-add"
                style="background:hsl(24,45%,42%); color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer;">Add</button>
    </div>
</div>
@endcan
