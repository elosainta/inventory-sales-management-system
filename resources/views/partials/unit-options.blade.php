{{-- The <option>s for a unit dropdown, ending in "+ New unit…". The select
     itself carries onfocus="this.dataset.prev=this.value" onchange="newUnit(this)";
     newUnit() lives in components/app-shell.

     Rendered inside a JS template literal on the purchase forms (via
     partials/new-item-panel), so nothing in here may contain a backtick or a
     dollar-brace. --}}
@foreach(\App\Models\InventoryItem::units() as $u)
    <option value="{{ $u }}">{{ $u }}</option>
@endforeach
<option value="__new">+ New unit…</option>
