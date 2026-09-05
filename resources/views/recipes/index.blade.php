<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Recipes</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">The dishes on your menu and what each one costs to plate.</p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('recipes.export-pdf') }}"
               style="background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,15%); padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
                Export PDF
            </a>
            @can('manage-recipes')
            <button onclick="document.getElementById('add-modal').style.display='flex'"
                    style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                + Add Recipe
            </button>
            @endcan
        </div>
    </div>

    @if($recipes->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            No recipes yet. Add your first one.
        </div>
    @else
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px;">
            @foreach($recipes as $recipe)
                <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px; position:relative;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <a href="{{ route('recipes.show', $recipe) }}"
                           title="View recipe details"
                           style="width:36px; height:36px; border-radius:6px; background:hsl(30,15%,94%); display:flex; align-items:center; justify-content:center; text-decoration:none; transition:background 0.15s;"
                           onmouseover="this.style.background='hsl(20,60%,85%)'"
                           onmouseout="this.style.background='hsl(30,15%,94%)'">
                            <span style="font-size:16px;">📖</span>
                        </a>
                        @can('manage-recipes')
                        <form action="{{ route('recipes.destroy', $recipe) }}" method="POST"
                              onsubmit="return confirm('Remove this recipe?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Remove">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            </button>
                        </form>
                        @endcan
                    </div>
                    <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:4px;">
                        {{ $recipe->name }}
                    </h3>
                    <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:8px;">
                        Serves {{ $recipe->serving_size }} · {{ $recipe->ingredients->count() }} ingredient{{ $recipe->ingredients->count() === 1 ? '' : 's' }}
                    </p>
                    @if($recipe->ingredients->isNotEmpty())
                        <div style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:12px; line-height:1.6;">
                            @foreach($recipe->ingredients as $ing)
                                {{ $ing->inventoryItem->name }} ({{ rtrim(rtrim(number_format($ing->quantity, 4), '0'), '.') }} {{ $ing->inventoryItem->unit }})@if(!$loop->last), @endif
                            @endforeach
                        </div>
                    @endif
                    <div style="border-top:1px solid hsl(30,15%,90%); padding-top:12px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%);">Plate Cost</span>
                            <span style="font-size:16px; font-weight:500; font-family:'DM Sans',sans-serif;">@money($recipe->plate_cost)</span>
                        </div>
                        @if($recipe->selling_price > 0)
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%);">Selling Price</span>
                            <span style="font-size:16px; font-weight:500; font-family:'DM Sans',sans-serif;">@money($recipe->selling_price)</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; padding-top:6px; border-top:1px solid hsl(30,15%,93%);">
                            <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(142,50%,35%);">Profit Per Dish</span>
                            <span style="font-size:19px; font-weight:600; font-family:'DM Sans',sans-serif; color:hsl(142,50%,35%);">@money($recipe->profit)</span>
                        </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @can('manage-recipes')
    @if($trashed->isNotEmpty())
    <div style="margin-top:48px;">
        <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500; color:hsl(24,5%,45%); margin-bottom:16px;">
            Recently Deleted
        </h2>
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:16px;">
            @foreach($trashed as $recipe)
                <div style="background:white; border:1px dashed hsl(30,15%,80%); border-radius:8px; padding:20px; opacity:0.75;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px;">
                        <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(0,50%,50%); background:hsl(0,50%,96%); padding:2px 8px; border-radius:4px;">Deleted</span>
                        <form action="{{ route('recipes.restore', $recipe->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    style="background:hsl(142,50%,45%); color:white; border:none; cursor:pointer; padding:4px 10px; border-radius:5px; font-size:12px; font-weight:500;"
                                    title="Restore recipe">
                                ↩ Restore
                            </button>
                        </form>
                    </div>
                    <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:4px;">
                        {{ $recipe->name }}
                    </h3>
                    <p style="font-size:13px; color:hsl(24,5%,45%); margin-bottom:8px;">
                        Serves {{ $recipe->serving_size }} · {{ $recipe->ingredients->count() }} ingredient{{ $recipe->ingredients->count() === 1 ? '' : 's' }}
                    </p>
                    <div style="border-top:1px solid hsl(30,15%,90%); padding-top:12px; display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%);">Plate Cost</span>
                        <span style="font-size:19px; font-weight:500; font-family:'DM Sans',sans-serif;">@money($recipe->plate_cost)</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
    @endcan

    @can('manage-recipes')
    {{-- Add Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:flex-start; justify-content:center; padding:32px 16px; overflow-y:auto;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:640px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">New Recipe</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Plate cost is calculated from ingredient prices.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('recipes.store') }}" method="POST">
                @csrf
                <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Name</label>
                        <input type="text" name="name" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Serving Size</label>
                        <input type="number" name="serving_size" value="1" min="1" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Selling Price (RM)</label>
                        <input type="number" name="selling_price" value="0" min="0" step="0.01" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Miscellaneous (%)</label>
                    <input type="number" id="add-misc-percent" name="misc_percent" value="30" min="0" max="100" step="0.01"
                           oninput="recalcCost()"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    <p style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Overhead added on top of ingredient cost (gas, condiments, small consumables). Default 30%.</p>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">
                        Finished dish <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span>
                    </label>
                    <select name="output_inventory_item_id"
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                        <option value="">Nothing — a sale deducts the raw ingredients</option>
                        @foreach($items as $i)
                            <option value="{{ $i->id }}">{{ $i->name }} ({{ $i->unit }})</option>
                        @endforeach
                    </select>
                    <p style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">The stock a batch of this dish creates. Set it and the pipeline turns on for this recipe: production takes the ingredients off the shelf and puts finished dishes on it, and a sale takes a finished dish. Leave it as &ldquo;Nothing&rdquo; and a sale deducts the raw ingredients instead, as before.</p>
                </div>

                {{-- Ingredients --}}
                <div style="border-top:1px solid hsl(30,15%,90%); padding-top:16px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h3 style="font-size:15px; font-weight:600;">Ingredients</h3>
                        <button type="button" onclick="addIngredientRow()"
                                style="background:hsl(30,15%,92%); border:1px solid hsl(30,15%,85%); padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer;">
                            + Add Ingredient
                        </button>
                    </div>
                    <div id="ingredient-rows"></div>
                    <div id="add-dupe-warning" role="alert"
                         style="display:none; margin-top:8px; padding:8px 12px; border-radius:6px; font-size:13px; background:hsl(45,90%,95%); border:1px solid hsl(45,80%,75%); color:hsl(35,70%,30%);"></div>
                </div>

                {{-- Cost preview --}}
                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:hsl(30,15%,96%); border-radius:6px; margin-bottom:16px;">
                    <span style="font-size:13px; color:hsl(24,5%,45%);">Plate cost preview</span>
                    <span id="cost-preview" style="font-weight:600; font-size:16px;">RM 0.00</span>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('add-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save recipe
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const inventoryItems = [
            @foreach($items as $i)
            { id: {{ $i->id }}, name: @json($i->name), unit: @json($i->unit), unit_cost: {{ $i->unit_cost }} },
            @endforeach
        ];
        let ingCounter = 0;

        function addIngredientRow() {
            const idx = ingCounter++;
            const row = document.createElement('div');
            row.className = 'ing-row';
            row.style.cssText = 'display:grid; grid-template-columns:2fr 1fr auto; gap:8px; align-items:end; margin-bottom:8px;';
            row.innerHTML = `
                <div>
                    <label style="display:block; font-size:12px; color:hsl(24,5%,45%); margin-bottom:2px;">Ingredient</label>
                    <select name="ingredients[${idx}][inventory_item_id]" required
                            onchange="recalcCost()"
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select ingredient</option>
                        ${inventoryItems.map(i => '<option value="' + i.id + '" data-cost="' + i.unit_cost + '" data-unit="' + i.unit + '">' + i.name + ' (' + i.unit + ')</option>').join('')}
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; color:hsl(24,5%,45%); margin-bottom:2px;">Quantity</label>
                    <input type="number" name="ingredients[${idx}][quantity]" step="0.0001" min="0.0001" required
                           oninput="recalcCost()"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <button type="button" onclick="this.parentElement.remove(); recalcCost();"
                        style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); font-size:18px; padding:8px;">×</button>
            `;
            document.getElementById('ingredient-rows').appendChild(row);
        }

        function recalcCost() {
            const rows = document.querySelectorAll('.ing-row');
            let total = 0;
            rows.forEach(row => {
                const select = row.querySelector('select');
                const qtyInput = row.querySelector('input[type=number]');
                const cost = parseFloat(select.options[select.selectedIndex]?.dataset.cost || 0);
                const qty = parseFloat(qtyInput.value) || 0;
                total += qty * cost;
            });
            const miscPct = parseFloat(document.getElementById('add-misc-percent')?.value) || 0;
            const withMisc = total * (1 + miscPct / 100);
            document.getElementById('cost-preview').textContent = 'RM ' + withMisc.toFixed(2);
            flagDuplicateIngredients('ing-row', 'add-dupe-warning');
        }

        // Warn (non-blocking) when the same ingredient is chosen in more than one row.
        function flagDuplicateIngredients(rowClass, warnId) {
            const selects = document.querySelectorAll('.' + rowClass + ' select');
            const seen = {};
            const dupes = new Set();
            selects.forEach(s => {
                if (!s.value) return;
                const label = (s.options[s.selectedIndex]?.text || 'ingredient').replace(/\s*\(.*\)$/, '');
                if (seen[s.value]) dupes.add(label);
                seen[s.value] = true;
            });
            const warn = document.getElementById(warnId);
            if (!warn) return;
            if (dupes.size > 0) {
                warn.textContent = '⚠ Same ingredient added more than once (' + [...dupes].join(', ') + '). Their quantities will be added together when you save.';
                warn.style.display = 'block';
            } else {
                warn.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', () => addIngredientRow());
    </script>
    @endcan
</x-app-shell>