<x-app-shell>

    <div class="app-page-header" style="margin-bottom:24px;">
        <a href="{{ route('stock-take.index') }}" style="font-size:13px; color:hsl(24,5%,45%); text-decoration:none;">&larr; Back to stock-take</a>
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin:8px 0;">Stock-take items</h1>
        <p style="color:hsl(24,5%,45%); font-size:14px;">The list of things counted in each section. Edit names and units, remove what you no longer stock, or add new items.</p>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;" class="app-chart-grid">
        @foreach($sections as $sectionKey => $items)
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
                <div style="padding:16px 20px; border-bottom:1px solid hsl(30,15%,90%); display:flex; justify-content:space-between; align-items:center;">
                    <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500;">{{ \App\Models\StockTakeItem::SECTIONS[$sectionKey] }}</h3>
                    <span style="font-size:12px; color:hsl(24,5%,50%); font-family:'JetBrains Mono',monospace;">{{ $items->count() }} items</span>
                </div>

                {{-- Add new --}}
                <form method="POST" action="{{ route('stock-take-items.store') }}" style="padding:14px 20px; border-bottom:1px solid hsl(30,15%,93%); background:hsl(30,15%,98%); display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end;">
                    @csrf
                    <input type="hidden" name="section" value="{{ $sectionKey }}">
                    <div style="flex:1; min-width:130px;">
                        <label style="display:block; font-size:11px; font-weight:600; color:hsl(24,10%,40%); margin-bottom:4px;">New item</label>
                        <input type="text" name="name" required placeholder="Item name" style="width:100%; padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:13px;">
                    </div>
                    <div style="width:80px;">
                        <label style="display:block; font-size:11px; font-weight:600; color:hsl(24,10%,40%); margin-bottom:4px;">Unit</label>
                        <input type="text" name="default_unit" placeholder="kg" style="width:100%; padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:13px;">
                    </div>
                    <div style="flex:1; min-width:160px;">
                        <label style="display:block; font-size:11px; font-weight:600; color:hsl(24,10%,40%); margin-bottom:4px;">Deducts from</label>
                        <input type="text" class="item-picker" list="inventory-options" data-for="inventory_item_id"
                               placeholder="Nothing — count only" autocomplete="off"
                               style="width:100%; padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:13px;">
                        <input type="hidden" name="inventory_item_id">
                    </div>
                    <button type="submit" style="padding:8px 14px; background:hsl(24,10%,16%); color:white; border:none; border-radius:5px; font-size:13px; font-weight:500; cursor:pointer;">Add</button>
                </form>

                <div style="max-height:520px; overflow-y:auto;">
                    @forelse($items as $item)
                        <div style="display:flex; gap:8px; align-items:center; padding:8px 20px; border-bottom:1px solid hsl(30,15%,95%);">
                            <form method="POST" action="{{ route('stock-take-items.update', $item) }}" style="display:flex; gap:6px; align-items:center; flex:1;">
                                @csrf
                                @method('PATCH')
                                <input type="text" name="name" value="{{ $item->name }}" required style="flex:1; min-width:0; padding:6px 9px; border:1px solid hsl(30,15%,90%); border-radius:5px; font-size:13px;">
                                <input type="text" name="default_unit" value="{{ $item->default_unit }}" placeholder="unit" style="width:64px; padding:6px 9px; border:1px solid hsl(30,15%,90%); border-radius:5px; font-size:13px;">
                                {{-- The visible text is filled in from the id below once ItemPicker
                                     has built its labels, so a duplicated name can never save the
                                     wrong item just because it typed the same. --}}
                                <input type="text" class="item-picker" list="inventory-options" data-for="inventory_item_id"
                                       data-item-id="{{ $item->inventory_item_id }}" placeholder="Nothing — count only" autocomplete="off"
                                       title="Stock this item's Out column comes off"
                                       style="flex:1; min-width:0; max-width:200px; padding:6px 9px; border:1px solid hsl(30,15%,90%); border-radius:5px; font-size:13px;">
                                <input type="hidden" name="inventory_item_id" value="{{ $item->inventory_item_id }}">
                                <button type="submit" title="Save" style="padding:6px 10px; background:white; border:1px solid hsl(30,15%,82%); border-radius:5px; font-size:12px; cursor:pointer; color:hsl(24,10%,35%);">Save</button>
                            </form>
                            <form method="POST" action="{{ route('stock-take-items.destroy', $item) }}" onsubmit="return confirm('Remove {{ addslashes($item->name) }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Remove" style="padding:6px 9px; background:none; border:none; cursor:pointer; color:hsl(0,55%,50%);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div style="padding:24px 20px; text-align:center; color:hsl(24,5%,50%); font-size:13px;">No items yet — add one above.</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    @include('partials.item-picker', ['pickerItems' => $inventoryItems])
    <script>
        document.querySelectorAll('.item-picker[data-item-id]').forEach((input) => {
            input.value = ItemPicker.labelFor(input.dataset.itemId);
        });
    </script>

</x-app-shell>
