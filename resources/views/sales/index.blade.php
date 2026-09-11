<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Sales</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Plates leaving the pass — revenue per recipe.</p>
        </div>
        <div style="display:flex; gap:8px;">
            @can('export-pdf')
            <a href="{{ route('sales.export-pdf') }}"
               style="background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,15%); padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
                Export PDF
            </a>
            @endcan
            @can('manage-sales')
            {{-- A dish on the menu is logged on the sheet below. This is the one
                 way to key in an OPEN ORDER - off-menu food for the team, typed in
                 by hand - so the pop-up opens with that already ticked. --}}
            <button onclick="document.getElementById('open-order-input').checked = true; syncAddOpenOrder(); updatePreview(); document.getElementById('add-modal').style.display='flex'"
                    style="background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,15%); padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; cursor:pointer;">
                + Open order
            </button>
            @endcan
        </div>
    </div>
    @can('manage-sales')
        @include('sales._sheet')
        <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500; margin-bottom:14px;">Sales log</h2>
    @endcan
    @include('partials.period-filter', ['route' => 'sales.index', 'totalLabel' => 'Total revenue', 'total' => $totalRevenue])
    @if($sales->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            @if($totalOnRecord > 0)
                Nothing here for this period. There {{ $totalOnRecord === 1 ? 'is' : 'are' }}
                <strong>{{ $totalOnRecord }}</strong> {{ \Illuminate\Support\Str::plural('sale', $totalOnRecord) }} on record — change the month above to see them.
            @else
                No sales logged yet.
            @endif
        </div>
    @else
        @php
            // One collapsible section per day, most recent day first. Individual
            // sales stay hidden until the day header is clicked.
            $grouped = $sales->groupBy(fn ($s) => $s->sale_date->format('Y-m-d'))->sortKeysDesc();
        @endphp
        <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:12px;">{{ $grouped->count() }} {{ $grouped->count() === 1 ? 'day' : 'days' }} — click a day to see its individual sales.</p>
        <div style="display:flex; flex-direction:column; gap:12px;">
            @foreach($grouped as $day => $group)
                @php
                    $dLabel = \Illuminate\Support\Carbon::parse($day)->format('l, M d, Y');
                    $dQty   = $group->sum('qty_sold');
                    $dRev   = $group->sum('total_revenue');
                    $dCount = $group->count();
                @endphp
                <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
                    <button type="button" onclick="toggleSaleGroup('day-{{ $day }}', this)"
                            style="width:100%; display:flex; align-items:center; gap:12px; padding:14px 18px; background:hsl(30,15%,97%); border:none; cursor:pointer; text-align:left;">
                        <svg class="chev" style="transition:transform 0.15s; flex-shrink:0; color:hsl(24,5%,45%);" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                        <span style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; flex:1;">{{ $dLabel }}</span>
                        <span style="font-size:13px; color:hsl(24,5%,45%); white-space:nowrap;">{{ $dQty }} sold · {{ $dCount }} {{ $dCount === 1 ? 'sale' : 'sales' }}</span>
                        <span style="font-family:'JetBrains Mono',monospace; font-weight:600; color:hsl(140,60%,30%); min-width:100px; text-align:right;">@money($dRev)</span>
                    </button>
                    <div id="day-{{ $day }}" style="display:none;">
                        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px; border-top:1px solid hsl(30,15%,90%);">
                            <thead>
                                <tr style="border-bottom:1px solid hsl(30,15%,90%);">
                                    <th style="text-align:left; padding:10px 16px; font-weight:600;">Dish</th>
                                    <th style="text-align:left; padding:10px 16px; font-weight:600;">Qty</th>
                                    <th style="text-align:left; padding:10px 16px; font-weight:600;">Price</th>
                                    <th style="text-align:left; padding:10px 16px; font-weight:600;">Revenue</th>
                                    <th style="text-align:left; padding:10px 16px; font-weight:600;">Photo</th>
                                    @canany(['manage-sales', 'delete-entries'])
                                    <th style="text-align:left; padding:10px 16px; font-weight:600;">Actions</th>
                                    @endcanany
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group as $sale)
                                    <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                                        <td style="padding:12px 16px;">
                                            {{ $sale->label }}
                                            @if($sale->is_open_order)
                                                <span style="display:inline-block; margin-left:6px; padding:1px 7px; background:hsl(20,60%,95%); color:hsl(20,60%,38%); border:1px solid hsl(20,50%,85%); border-radius:10px; font-size:11px; font-weight:500; white-space:nowrap;">Open order</span>
                                            @endif
                                        </td>
                                        <td style="padding:12px 16px;">{{ $sale->qty_sold }}</td>
                                        <td style="padding:12px 16px;">@money($sale->selling_price)</td>
                                        <td style="padding:12px 16px; font-weight:500; color:hsl(140,60%,30%);">
                                            @money($sale->total_revenue)
                                            @if($sale->discount > 0)
                                                <span style="display:block; font-weight:400; font-size:12px; color:hsl(20,60%,45%);">less @money($sale->discount) discount</span>
                                            @endif
                                        </td>
                                        <td style="padding:12px 16px;">
                                            @forelse($sale->attachments as $att)
                                                <a href="{{ route('sales.attachment', $att) }}" target="_blank" rel="noopener" title="{{ $att->filename }}" style="display:inline-block; margin-right:6px; vertical-align:middle;">
                                                    @if($att->isImage())
                                                        <img src="{{ route('sales.attachment', $att) }}" alt="{{ $att->filename }}" style="width:40px; height:40px; object-fit:cover; border-radius:4px; border:1px solid hsl(30,15%,85%);">
                                                    @else
                                                        <span style="color:hsl(20,60%,45%); font-size:13px;">📎 {{ $att->filename }}</span>
                                                    @endif
                                                </a>
                                            @empty
                                                <span style="color:hsl(24,5%,70%);">—</span>
                                            @endforelse
                                        </td>
                                        @canany(['manage-sales', 'delete-entries'])
                                        <td style="padding:12px 16px;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                @can('manage-sales')
                                                <button onclick="openEditModal(this)"
                                                        data-edit-url="{{ route('sales.update', $sale) }}"
                                                        data-recipe-id="{{ $sale->recipe_id }}"
                                                        data-item-name="{{ $sale->item_name }}"
                                                        data-qty="{{ $sale->qty_sold }}"
                                                        data-price="{{ $sale->selling_price }}"
                                                        data-discount="{{ $sale->discount }}"
                                                        data-open-order="{{ $sale->is_open_order ? '1' : '0' }}"
                                                        data-date="{{ $sale->sale_date->format('Y-m-d') }}"
                                                        style="background:none; border:none; cursor:pointer; color:hsl(24,5%,45%); padding:4px;" title="Edit">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                                </button>
                                                @endcan
                                                @can('delete-entries')
                                                <form action="{{ route('sales.destroy', $sale) }}" method="POST"
                                                      onsubmit="return confirm('Remove this sale?')" style="display:inline;">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Remove">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                                    </button>
                                                </form>
                                                @endcan
                                            </div>
                                        </td>
                                        @endcanany
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @can('manage-sales')
    {{-- Add Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Log Sale</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Record dishes sold during service.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('sales.store') }}" method="POST" id="sale-form" enctype="multipart/form-data">
                @csrf
                <div id="recipe-field" style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Recipe</label>
                    <select name="recipe_id" required id="recipe-select"
                            onchange="fillPriceFromRecipe()"
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select recipe</option>
                        @foreach($recipes as $recipe)
                            <option value="{{ $recipe->id }}" data-cost="{{ $recipe->plate_cost }}" data-price="{{ $recipe->selling_price }}">
                                {{ $recipe->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Quantity</label>
                        <input type="number" name="qty_sold" id="qty-input" value="1" min="1" required
                               onchange="updatePreview()"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Selling Price (RM) <span style="font-weight:400; color:hsl(24,5%,55%); font-size:12px;">· auto-filled from recipe</span></label>
                        <input type="number" name="selling_price" id="price-input" value="0" step="0.01" min="0" required
                               onchange="updatePreview()"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" name="sale_date" value="{{ date('Y-m-d') }}" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                {{-- Open order: a staff extra order from the kitchen, usually discounted.
                     Recorded anonymously — no name is asked for or shown. --}}
                <div style="margin-bottom:16px; padding:12px 16px; background:hsl(30,15%,97%); border:1px solid hsl(30,15%,90%); border-radius:6px;">
                    <input type="hidden" name="is_open_order" value="0">
                    <label style="display:flex; align-items:flex-start; gap:9px; cursor:pointer;">
                        <input type="checkbox" name="is_open_order" value="1" id="open-order-input"
                               onchange="syncAddOpenOrder()"
                               style="margin-top:3px; width:15px; height:15px; accent-color:hsl(20,60%,45%); cursor:pointer;">
                        <span>
                            <span style="display:block; font-size:14px; font-weight:500;">Open order — staff extra order</span>
                            <span style="display:block; font-size:12px; color:hsl(24,5%,50%);">Off-menu food a staff member ordered from the kitchen. Key the dish in by hand — no recipe, so nothing is deducted from stock. Logged without a name.</span>
                        </span>
                    </label>
                    <div id="item-name-field" style="display:none; margin-top:12px;">
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Dish <span style="font-weight:400; color:hsl(24,5%,55%); font-size:12px;">· type what was made</span></label>
                        <input type="text" name="item_name" id="item-name-input" maxlength="255" placeholder="e.g. Fried rice with egg"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div style="margin-top:12px;">
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Discount (RM) <span style="font-weight:400; color:hsl(24,5%,55%); font-size:12px;">· total taken off, not per plate</span></label>
                        <input type="number" name="discount" id="discount-input" value="0" step="0.01" min="0"
                               onchange="updatePreview()" oninput="updatePreview()"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Photo <span style="font-weight:400; color:hsl(24,5%,55%); font-size:12px;">· optional — receipt, POS screen, or plate</span></label>
                    <input type="file" name="photos[]" accept="image/*" multiple
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px; box-sizing:border-box; background:white;">
                </div>
                <div id="revenue-preview"
                     style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:hsl(30,15%,96%); border-radius:6px; margin-bottom:16px;">
                    <span style="font-size:13px; color:hsl(24,5%,45%);">Revenue preview</span>
                    <span id="revenue-amount" style="font-weight:600; font-size:15px;">RM 0.00</span>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('add-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Log sale
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Edit Modal --}}
    <div id="edit-modal"
         onclick="if(event.target===this)document.getElementById('edit-modal').style.display='none'"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Edit Sale</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Correct a mistake — inventory won't be re-adjusted.</p>
                </div>
                <button onclick="document.getElementById('edit-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form id="edit-form" method="POST">
                @csrf
                @method('PATCH')
                <div id="edit-recipe-field" style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Recipe</label>
                    <select id="edit-recipe" name="recipe_id" required
                            onchange="fillEditPriceFromRecipe()"
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select recipe</option>
                        @foreach($recipes as $recipe)
                            <option value="{{ $recipe->id }}" data-price="{{ $recipe->selling_price }}">{{ $recipe->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Quantity</label>
                        <input type="number" id="edit-qty" name="qty_sold" min="1" required
                               onchange="updateEditPreview()"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Selling Price (RM)</label>
                        <input type="number" id="edit-price" name="selling_price" step="0.01" min="0" required
                               onchange="updateEditPreview()"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" id="edit-date" name="sale_date" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px; padding:12px 16px; background:hsl(30,15%,97%); border:1px solid hsl(30,15%,90%); border-radius:6px;">
                    <input type="hidden" name="is_open_order" value="0">
                    <label style="display:flex; align-items:center; gap:9px; cursor:pointer;">
                        <input type="checkbox" id="edit-open-order" name="is_open_order" value="1"
                               onchange="syncEditOpenOrder()"
                               style="width:15px; height:15px; accent-color:hsl(20,60%,45%); cursor:pointer;">
                        <span style="font-size:14px; font-weight:500;">Open order — staff extra order</span>
                    </label>
                    <div id="edit-item-name-field" style="display:none; margin-top:12px;">
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Dish</label>
                        <input type="text" id="edit-item-name" name="item_name" maxlength="255"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div style="margin-top:12px;">
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Discount (RM)</label>
                        <input type="number" id="edit-discount" name="discount" step="0.01" min="0"
                               onchange="updateEditPreview()" oninput="updateEditPreview()"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:hsl(30,15%,96%); border-radius:6px; margin-bottom:16px;">
                    <span style="font-size:13px; color:hsl(24,5%,45%);">Revenue preview</span>
                    <span id="edit-revenue" style="font-weight:600; font-size:15px;">RM 0.00</span>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('edit-modal').style.display='none'"
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
        // Collapsible per-recipe sections: individual sales stay hidden until clicked.
        function toggleSaleGroup(id, btn) {
            var body = document.getElementById(id);
            var isHidden = (body.style.display === 'none' || body.style.display === '');
            body.style.display = isHidden ? 'block' : 'none';
            var chev = btn.querySelector('.chev');
            if (chev) chev.style.transform = isHidden ? 'rotate(90deg)' : 'rotate(0deg)';
        }
        // An open order is off-menu, so the recipe picker gives way to a plain
        // text field. required has to move with it — a hidden required control
        // blocks submit with an error the browser can't point at.
        function syncOpenOrder(cb, recipeField, recipe, nameField, name) {
            const open = document.getElementById(cb).checked;
            document.getElementById(recipeField).style.display = open ? 'none' : 'block';
            document.getElementById(nameField).style.display   = open ? 'block' : 'none';
            document.getElementById(recipe).required = !open;
            document.getElementById(name).required   = open;
        }
        function syncAddOpenOrder() {
            syncOpenOrder('open-order-input', 'recipe-field', 'recipe-select', 'item-name-field', 'item-name-input');
        }
        function syncEditOpenOrder() {
            syncOpenOrder('edit-open-order', 'edit-recipe-field', 'edit-recipe', 'edit-item-name-field', 'edit-item-name');
        }
        // Auto-fill the selling price from the chosen recipe (still editable).
        function fillPriceFromRecipe() {
            const sel = document.getElementById('recipe-select');
            const opt = sel.options[sel.selectedIndex];
            const price = opt ? parseFloat(opt.dataset.price) : NaN;
            if (!isNaN(price)) document.getElementById('price-input').value = price.toFixed(2);
            updatePreview();
        }
        function fillEditPriceFromRecipe() {
            const sel = document.getElementById('edit-recipe');
            const opt = sel.options[sel.selectedIndex];
            const price = opt ? parseFloat(opt.dataset.price) : NaN;
            if (!isNaN(price)) document.getElementById('edit-price').value = price.toFixed(2);
            updateEditPreview();
        }
        // Previews mirror Sale::revenue() — gross less discount, never negative.
        function updatePreview() {
            const qty = parseFloat(document.getElementById('qty-input').value) || 0;
            const price = parseFloat(document.getElementById('price-input').value) || 0;
            const disc = parseFloat(document.getElementById('discount-input').value) || 0;
            const revenue = Math.max(0, qty * price - disc);
            document.getElementById('revenue-amount').textContent = 'RM ' + revenue.toFixed(2);
        }
        function updateEditPreview() {
            const qty = parseFloat(document.getElementById('edit-qty').value) || 0;
            const price = parseFloat(document.getElementById('edit-price').value) || 0;
            const disc = parseFloat(document.getElementById('edit-discount').value) || 0;
            document.getElementById('edit-revenue').textContent = 'RM ' + Math.max(0, qty * price - disc).toFixed(2);
        }
        function openEditModal(btn) {
            var d = btn.dataset;
            document.getElementById('edit-form').action = d.editUrl;
            document.getElementById('edit-recipe').value = d.recipeId;
            document.getElementById('edit-qty').value = d.qty;
            document.getElementById('edit-price').value = d.price;
            document.getElementById('edit-date').value = d.date;
            document.getElementById('edit-discount').value = d.discount;
            document.getElementById('edit-open-order').checked = d.openOrder === '1';
            document.getElementById('edit-item-name').value = d.itemName || '';
            syncEditOpenOrder();
            updateEditPreview();
            document.getElementById('edit-modal').style.display = 'flex';
            document.getElementById(d.openOrder === '1' ? 'edit-item-name' : 'edit-recipe').focus();
        }
    </script>
    @endcan
</x-app-shell>