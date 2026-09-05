<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Inventory</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Every ingredient on the line — quantity, cost, and reorder thresholds.</p>
        </div>
        <div style="display:flex; gap:8px;">
            @can('export-pdf')
            <a href="{{ route('inventory.export-pdf') }}"
               style="background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,15%); padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
                Export PDF
            </a>
            @endcan
            @can('record-inventory')
            <button
                onclick="document.getElementById('add-modal').style.display='flex'"
                style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                + Add Ingredient
            </button>
            @endcan
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('inventory.index') }}"
          class="app-filters" style="display:flex; gap:12px; margin-bottom:24px; align-items:center; flex-wrap:wrap;">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search ingredients..."
               style="padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; width:280px;">
        <select name="category"
                style="padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
            <option value="all" {{ request('category') === 'all' || !request('category') ? 'selected' : '' }}>All categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
            @endforeach
        </select>
        <button type="submit"
                style="padding:8px 16px; background:hsl(30,15%,92%); border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; cursor:pointer;">
            Filter
        </button>
        <span style="margin-left:auto; font-size:14px; color:hsl(24,5%,45%);">
            Total value: <strong>@money($totalValue)</strong>
        </span>
    </form>

    {{-- Table --}}
    @if($items->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            No ingredients found.
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Name</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Category</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Available</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Threshold/Limit</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Unit Cost</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Value</th>
                        @can('record-inventory')
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr id="item-{{ $item->id }}" style="border-bottom:1px solid hsl(30,15%,93%); scroll-margin-top:64px;">
                            <td style="padding:12px 16px;">
                                {{ $item->name }}
                                @if($item->isLowStock())
                                    <span style="margin-left:8px; background:#fee2e2; color:#991b1b; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px;">Low</span>
                                @endif
                            </td>
                            <td style="padding:12px 16px;">
                                <span style="background:hsl(30,15%,92%); font-size:12px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; letter-spacing:0.05em;">
                                    {{ $item->category }}
                                </span>
                            </td>
                            <td style="padding:12px 16px;">{{ $item->quantity_on_hand }} {{ $item->unit }}</td>
                            <td style="padding:12px 16px;">{{ $item->reorder_threshold }}</td>
                            <td style="padding:12px 16px;">@money($item->unit_cost)</td>
                            <td style="padding:12px 16px;">@money($item->monetary_value)</td>
                            @can('record-inventory')
                            <td style="padding:12px 16px; white-space:nowrap;">
                                <button onclick="openEditModal(this)"
                                        data-edit-url="{{ route('inventory.update', $item) }}"
                                        data-name="{{ $item->name }}"
                                        data-category="{{ $item->category }}"
                                        data-unit="{{ $item->unit }}"
                                        data-qty="{{ $item->quantity_on_hand }}"
                                        data-reorder="{{ $item->reorder_threshold }}"
                                        data-cost="{{ $item->unit_cost }}"
                                        data-pack-size="{{ $item->pack_size }}"
                                        style="background:none; border:none; cursor:pointer; color:hsl(24,5%,45%); padding:4px; margin-right:4px;" title="Edit">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                @can('manage-inventory')
                                <form action="{{ route('inventory.destroy', $item) }}" method="POST"
                                      onsubmit="return confirm('Remove {{ addslashes($item->name) }}?')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Remove">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                </form>
                                @endcan
                            </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @can('record-inventory')
    {{-- Add Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">New Ingredient</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Add a new ingredient to your inventory.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('inventory.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Name</label>
                    <input type="text" name="name" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Category</label>
                        <select name="category" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Unit</label>
                        <select name="unit" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            @foreach(\App\Models\InventoryItem::UNITS as $u)
                                <option value="{{ $u }}">{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Qty on Hand</label>
                        <input type="number" name="quantity_on_hand" value="0" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Unit Cost (RM)</label>
                        <input id="add-unit-cost" type="number" name="unit_cost" value="0" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Threshold/Limit</label>
                        <input type="number" name="reorder_threshold" value="0" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                {{-- Optional: price per piece from a pack (e.g. eggs, cheese slices, sausages) --}}
                <div style="border:1px dashed hsl(30,15%,80%); border-radius:6px; padding:12px; margin-bottom:16px; background:hsl(40,33%,98%);">
                    <div style="font-size:13px; font-weight:600; margin-bottom:2px;">Sold in a pack? Price it per piece <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span></div>
                    <p style="font-size:12px; color:hsl(24,5%,50%); margin-bottom:10px;">Enter how many pieces are in a pack and the pack price — we'll fill Unit Cost per piece for you. Pick unit <strong>pcs</strong> above.</p>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Units per pack</label>
                            <input id="add-pack-size" type="number" name="pack_size" value="" step="1" min="1" placeholder="e.g. 30"
                                   oninput="calcUnitCost('add')"
                                   style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Pack price (RM)</label>
                            <input id="add-pack-price" type="number" step="0.01" min="0" placeholder="e.g. 16.00"
                                   oninput="calcUnitCost('add')"
                                   style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('add-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Add ingredient
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

    @can('record-inventory')
    {{-- Edit Modal --}}
    <div id="edit-modal"
         onclick="if(event.target===this)this.style.display='none'"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Edit Ingredient</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Update the ingredient details below.</p>
                </div>
                <button onclick="document.getElementById('edit-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form id="edit-form" method="POST" action="">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Name</label>
                    <input id="edit-name" @cannot("manage-inventory") disabled @endcannot type="text" name="name" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Category</label>
                        <select id="edit-category" @cannot("manage-inventory") disabled @endcannot name="category" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Unit</label>
                        <select id="edit-unit" @cannot("manage-inventory") disabled @endcannot name="unit" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            @foreach(\App\Models\InventoryItem::UNITS as $u)
                                <option value="{{ $u }}">{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Qty on Hand</label>
                        <input id="edit-qty" type="number" name="quantity_on_hand" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Unit Cost (RM)</label>
                        <input id="edit-cost" @cannot("manage-inventory") disabled @endcannot type="number" name="unit_cost" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Threshold/Limit</label>
                        <input id="edit-reorder" @cannot("manage-inventory") disabled @endcannot type="number" name="reorder_threshold" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                {{-- Optional: price per piece from a pack (e.g. eggs, cheese slices, sausages) --}}
                <div style="border:1px dashed hsl(30,15%,80%); border-radius:6px; padding:12px; margin-bottom:16px; background:hsl(40,33%,98%);">
                    <div style="font-size:13px; font-weight:600; margin-bottom:2px;">Sold in a pack? Price it per piece <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span></div>
                    <p style="font-size:12px; color:hsl(24,5%,50%); margin-bottom:10px;">Enter how many pieces are in a pack and the pack price — we'll fill Unit Cost per piece for you. Pick unit <strong>pcs</strong> above.</p>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                        <div>
                            <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Units per pack</label>
                            <input id="edit-pack-size" @cannot("manage-inventory") disabled @endcannot type="number" name="pack_size" value="" step="1" min="1" placeholder="e.g. 30"
                                   oninput="calcUnitCost('edit')"
                                   style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                        </div>
                        <div>
                            <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Pack price (RM)</label>
                            <input id="edit-pack-price" @cannot("manage-inventory") disabled @endcannot type="number" step="0.01" min="0" placeholder="e.g. 16.00"
                                   oninput="calcUnitCost('edit')"
                                   style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button" onclick="document.getElementById('edit-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(btn) {
            var d = btn.dataset;
            document.getElementById('edit-form').action = d.editUrl;
            document.getElementById('edit-name').value = d.name;
            document.getElementById('edit-category').value = d.category;
            document.getElementById('edit-unit').value = d.unit;
            document.getElementById('edit-qty').value = d.qty;
            document.getElementById('edit-cost').value = d.cost;
            document.getElementById('edit-reorder').value = d.reorder;
            // Prefill the pack helper: units per pack, and back-calculate the pack
            // price from the stored per-piece cost so the fields stay consistent.
            var packSize = parseFloat(d.packSize);
            document.getElementById('edit-pack-size').value = (packSize > 0) ? d.packSize : '';
            document.getElementById('edit-pack-price').value =
                (packSize > 0 && parseFloat(d.cost) > 0) ? (parseFloat(d.cost) * packSize).toFixed(2) : '';
            document.getElementById('edit-modal').style.display = 'flex';
            // The name box is disabled for anyone who may only key a count in,
            // so send them to the one field they can actually change.
            (document.querySelector('#edit-name:not([disabled])')
                || document.getElementById('edit-qty')).focus();
        }

        // Divide a pack price by units-per-pack to fill the per-piece Unit Cost.
        function calcUnitCost(prefix) {
            var size  = parseFloat(document.getElementById(prefix + '-pack-size').value);
            var price = parseFloat(document.getElementById(prefix + '-pack-price').value);
            var costEl = document.getElementById(prefix === 'edit' ? 'edit-cost' : 'add-unit-cost');
            if (size > 0 && price >= 0 && !isNaN(price)) {
                costEl.value = (price / size).toFixed(2);
            }
        }
    </script>
    @endcan
</x-app-shell>