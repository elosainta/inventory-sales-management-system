<x-app-shell>
    {{-- Back link --}}
    <a href="{{ route('recipes.index') }}"
       style="display:inline-block; margin-bottom:16px; font-size:14px; color:hsl(24,5%,45%); text-decoration:none;">
        &larr; Back to recipes
    </a>

    {{-- Header --}}
    <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:32px;">
        <div style="display:flex; align-items:flex-start; gap:16px;">
            <div style="width:56px; height:56px; border-radius:8px; background:hsl(20,60%,90%); display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <span style="font-size:28px;">📖</span>
            </div>
            <div>
                <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:4px;">
                    {{ $recipe->name }}
                </h1>
                <p style="color:hsl(24,5%,45%); font-size:14px;">
                    Serves {{ $recipe->serving_size }} ·
                    {{ $recipe->ingredients->count() }} ingredient{{ $recipe->ingredients->count() === 1 ? '' : 's' }}
                </p>
            </div>
        </div>
        @can('manage-recipes')
        <button onclick="openEditModal()"
                style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer; flex-shrink:0;">
            Edit Recipe
        </button>
        @endcan
    </div>

    {{-- KPI strip --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:32px;">
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Plate Cost</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif; color:hsl(20,60%,45%);">
                @money($recipe->plate_cost)
            </div>
            <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Cost to plate one serving</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Selling Price</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">
                @money($recipe->selling_price)
            </div>
            <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Menu price per serving</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px; border-color:hsl(142,40%,80%);">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(142,50%,35%); margin-bottom:8px;">Profit Per Dish</div>
            <div style="font-size:26px; font-weight:600; font-family:'DM Sans',sans-serif; color:hsl(142,50%,35%);">
                @money($recipe->profit)
            </div>
            <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Selling price − plate cost</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Ingredients</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">
                {{ $recipe->ingredients->count() }}
            </div>
            <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Items used in this recipe</div>
        </div>
    </div>

    {{-- Ingredient breakdown table --}}
    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400; margin-bottom:16px;">
        Cost Breakdown
    </h2>

    @if($recipe->ingredients->isEmpty())
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:48px; text-align:center; color:hsl(24,5%,45%);">
            This recipe has no ingredients yet.
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Ingredient</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Category</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Quantity</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Unit Cost</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recipe->ingredients as $ing)
                        @php
                            $unitCost = $ing->inventoryItem?->unit_cost ?? 0;
                            $subtotal = $ing->quantity * $unitCost;
                            $qtyDisplay = rtrim(rtrim(number_format($ing->quantity, 4), '0'), '.');
                        @endphp
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px; font-weight:500;">
                                {{ $ing->inventoryItem?->name ?? 'Unknown ingredient' }}
                            </td>
                            <td style="padding:12px 16px;">
                                @if($ing->inventoryItem)
                                    <span style="background:hsl(30,15%,92%); font-size:12px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; letter-spacing:0.05em;">
                                        {{ $ing->inventoryItem->category }}
                                    </span>
                                @else
                                    <span style="color:hsl(24,5%,45%);">—</span>
                                @endif
                            </td>
                            <td style="padding:12px 16px; text-align:right;">
                                {{ $qtyDisplay }} {{ $ing->inventoryItem?->unit ?? '' }}
                            </td>
                            <td style="padding:12px 16px; text-align:right;">
                                @money($unitCost)
                            </td>
                            <td style="padding:12px 16px; text-align:right; font-weight:500;">
                                @money($subtotal)
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    @php
                        $ingredientTotal = $recipe->ingredientCost();
                        $miscPercent     = (float) ($recipe->misc_percent ?? 0);
                        $miscAmount      = round($ingredientTotal * $miscPercent / 100, 2);
                    @endphp
                    <tr style="border-top:1px solid hsl(30,15%,90%);">
                        <td colspan="4" style="padding:10px 16px; text-align:right; color:hsl(24,5%,45%);">
                            Ingredients subtotal
                        </td>
                        <td style="padding:10px 16px; text-align:right; color:hsl(24,5%,45%);">
                            @money($ingredientTotal)
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" style="padding:10px 16px; text-align:right; color:hsl(24,5%,45%);">
                            Miscellaneous ({{ rtrim(rtrim(number_format($miscPercent, 2), '0'), '.') }}%)
                            <span style="font-size:12px;">— gas, condiments &amp; small consumables</span>
                        </td>
                        <td style="padding:10px 16px; text-align:right; color:hsl(24,5%,45%);">
                            @money($miscAmount)
                        </td>
                    </tr>
                    <tr style="background:hsl(30,15%,97%); border-top:2px solid hsl(30,15%,85%);">
                        <td colspan="4" style="padding:14px 16px; font-weight:600; text-align:right;">
                            Total Plate Cost
                        </td>
                        <td style="padding:14px 16px; text-align:right; font-weight:700; font-size:16px; color:hsl(20,60%,45%);">
                            @money($recipe->plate_cost)
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    @can('manage-recipes')
    {{-- Edit Modal --}}
    <div id="edit-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:540px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Edit Recipe</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Update name, serving size, or ingredients.</p>
                </div>
                <button onclick="document.getElementById('edit-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('recipes.update', $recipe) }}" method="POST">
                @csrf
                @method('PUT')

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Recipe Name</label>
                    <input type="text" name="name" value="{{ $recipe->name }}" required maxlength="255"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Serving Size</label>
                        <input type="number" name="serving_size" value="{{ $recipe->serving_size }}" required min="1"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Selling Price (RM)</label>
                        <input type="number" name="selling_price" value="{{ $recipe->selling_price }}" required min="0" step="0.01"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Miscellaneous (%)</label>
                    <input type="number" id="edit-misc-percent" name="misc_percent" value="{{ rtrim(rtrim(number_format((float) ($recipe->misc_percent ?? 30), 2), '0'), '.') }}" min="0" max="100" step="0.01"
                           oninput="recalcEditCost()"
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
                            <option value="{{ $i->id }}" @selected($recipe->output_inventory_item_id === $i->id)>{{ $i->name }} ({{ $i->unit }})</option>
                        @endforeach
                    </select>
                    <p style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">The stock a batch of this dish creates. Set it and the pipeline turns on for this recipe: production takes the ingredients off the shelf and puts finished dishes on it, and a sale takes a finished dish. Leave it as &ldquo;Nothing&rdquo; and a sale deducts the raw ingredients instead, as before.</p>
                </div>

                <div style="border-top:1px solid hsl(30,15%,90%); padding-top:16px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                        <h3 style="font-size:15px; font-weight:600;">Ingredients</h3>
                        <button type="button" onclick="addEditIngredientRow()"
                                style="background:hsl(30,15%,92%); border:1px solid hsl(30,15%,85%); padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer;">
                            + Add Ingredient
                        </button>
                    </div>
                    <div id="edit-ingredient-rows"></div>
                    <div id="edit-dupe-warning" role="alert"
                         style="display:none; margin-top:8px; padding:8px 12px; border-radius:6px; font-size:13px; background:hsl(45,90%,95%); border:1px solid hsl(45,80%,75%); color:hsl(35,70%,30%);"></div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:hsl(30,15%,96%); border-radius:6px; margin-bottom:16px;">
                    <span style="font-size:13px; color:hsl(24,5%,45%);">Plate cost preview</span>
                    <span id="edit-cost-preview" style="font-weight:600; font-size:16px;">RM 0.00</span>
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
        const editInventoryItems = [
            @foreach($items as $i)
            { id: {{ $i->id }}, name: @json($i->name), unit: @json($i->unit), unit_cost: {{ $i->unit_cost }} },
            @endforeach
        ];

        const existingIngredients = [
            @foreach($recipe->ingredients as $ing)
            { inventory_item_id: {{ $ing->inventory_item_id }}, quantity: {{ $ing->quantity }} },
            @endforeach
        ];

        let editIngCounter = 0;

        function addEditIngredientRow(preselect = null, prequantity = null) {
            const idx = editIngCounter++;
            const row = document.createElement('div');
            row.className = 'edit-ing-row';
            row.style.cssText = 'display:grid; grid-template-columns:2fr 1fr auto; gap:8px; align-items:end; margin-bottom:8px;';

            const options = editInventoryItems
                .map(i => `<option value="${i.id}" data-cost="${i.unit_cost}" data-unit="${i.unit}"${preselect == i.id ? ' selected' : ''}>${i.name} (${i.unit})</option>`)
                .join('');

            row.innerHTML = `
                <div>
                    <label style="display:block; font-size:12px; color:hsl(24,5%,45%); margin-bottom:2px;">Ingredient</label>
                    <select name="ingredients[${idx}][inventory_item_id]" required
                            onchange="recalcEditCost()"
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select ingredient</option>
                        ${options}
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size:12px; color:hsl(24,5%,45%); margin-bottom:2px;">Quantity</label>
                    <input type="number" name="ingredients[${idx}][quantity]" step="0.0001" min="0.0001" required
                           value="${prequantity ?? ''}"
                           oninput="recalcEditCost()"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <button type="button" onclick="this.parentElement.remove(); recalcEditCost();"
                        style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); font-size:18px; padding:8px;">×</button>
            `;
            document.getElementById('edit-ingredient-rows').appendChild(row);
        }

        function recalcEditCost() {
            const rows = document.querySelectorAll('.edit-ing-row');
            let total = 0;
            rows.forEach(row => {
                const select = row.querySelector('select');
                const qtyInput = row.querySelector('input[type=number]');
                const cost = parseFloat(select.options[select.selectedIndex]?.dataset.cost || 0);
                const qty = parseFloat(qtyInput.value) || 0;
                total += qty * cost;
            });
            const miscPct = parseFloat(document.getElementById('edit-misc-percent')?.value) || 0;
            const withMisc = total * (1 + miscPct / 100);
            document.getElementById('edit-cost-preview').textContent = 'RM ' + withMisc.toFixed(2);
            flagDuplicateIngredients('edit-ing-row', 'edit-dupe-warning');
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

        function openEditModal() {
            document.getElementById('edit-ingredient-rows').innerHTML = '';
            editIngCounter = 0;
            existingIngredients.forEach(ing => addEditIngredientRow(ing.inventory_item_id, ing.quantity));
            recalcEditCost();
            document.getElementById('edit-modal').style.display = 'flex';
        }
    </script>
    @endcan
</x-app-shell>
