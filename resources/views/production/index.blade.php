<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Production') }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px; max-width:600px;">
                What the kitchen cooked. Choose the dish and how many were made — the recipe works out
                what came off the shelf, so there is nothing to key in ingredient by ingredient.
            </p>
        </div>
        @can('manage-production')
        <button
            onclick="document.getElementById('add-modal').style.display='flex'; document.getElementById('recipe-search').focus();"
            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer; white-space:nowrap;">
            + Log Production
        </button>
        @endcan
    </div>

    {{-- Date range filter --}}
    @php
        $active = 'background:hsl(20,60%,45%); color:white; border-color:hsl(20,60%,45%);';
        $normal = 'background:white; color:hsl(24,10%,20%); border-color:hsl(30,15%,85%);';
    @endphp
    <div style="display:flex; gap:8px; align-items:center; margin-bottom:16px; flex-wrap:wrap;">
        <a href="{{ route('production.index', ['range' => 'today']) }}"
           style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; {{ $range === 'today' ? $active : $normal }}">{{ __('Today') }}</a>
        <a href="{{ route('production.index', ['range' => 'week']) }}"
           style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; {{ $range === 'week' ? $active : $normal }}">{{ __('This Week') }}</a>
        <a href="{{ route('production.index', ['range' => 'month']) }}"
           style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; {{ $range === 'month' ? $active : $normal }}">{{ __('This Month') }}</a>
        <a href="{{ route('production.index', ['range' => 'year']) }}"
           style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; {{ $range === 'year' ? $active : $normal }}">{{ __('This Year') }}</a>
        <form method="GET" action="{{ route('production.index') }}" style="display:flex; gap:6px; align-items:center;">
            <input type="month" name="month" value="{{ $month }}"
                   style="padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px;">
            <button type="submit"
                    style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; {{ !$range ? $active : $normal }}">{{ __('Custom') }}</button>
        </form>
        <span style="margin-left:auto; font-size:14px; color:hsl(24,5%,45%);">
            Total value: <strong>@money($totalValue)</strong>
        </span>
    </div>

    @if($batches->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            @if($totalOnRecord > 0)
                Nothing here for this period. There {{ $totalOnRecord === 1 ? 'is' : 'are' }}
                <strong>{{ $totalOnRecord }}</strong> {{ \Illuminate\Support\Str::plural('production batch', $totalOnRecord) }} on record — change the month above to see them.
            @else
                No production logged yet. Record your first batch.
            @endif
        </div>
    @else
        {{-- Search the batches on screen. Filters what this period already loaded;
             use the range buttons above to look at a different period. --}}
        <div style="display:flex; gap:10px; align-items:center; margin-bottom:12px; flex-wrap:wrap;">
            <input type="search" id="batch-search" placeholder="{{ __('Search dish, chef or note…') }}" autocomplete="off"
                   style="width:280px; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px; background:white;">
            <span id="batch-count" style="font-size:12px; color:hsl(24,5%,50%); font-family:'JetBrains Mono',monospace;">{{ $batches->count() }} batches</span>
        </div>

        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <div style="overflow-x:auto;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px; min-width:820px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">{{ __('Date') }}</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">{{ __('Dish') }}</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">{{ __('Made') }}</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">{{ __('Produced By') }}</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">{{ __('Logged By') }}</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">{{ __('Notes') }}</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">{{ __('Value') }}</th>
                        @can('delete-entries')
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">{{ __('Actions') }}</th>
                        @endcan
                    </tr>
                </thead>
                <tbody id="batch-rows">
                    @foreach($batches as $batch)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px;">{{ $batch->production_date->format('M d, Y') }}</td>
                            <td style="padding:12px 16px; font-weight:500;">
                                {{ $batch->recipe?->name ?? '—' }}
                                <span style="display:block; font-size:12px; color:hsl(24,5%,55%); font-weight:400;">
                                    used {{ $batch->lines->count() }} ingredient{{ $batch->lines->count() === 1 ? '' : 's' }}
                                </span>
                            </td>
                            <td style="padding:12px 16px; text-align:right; font-family:'JetBrains Mono',monospace;">
                                {{ $batch->quantity_produced !== null ? number_format((int) $batch->quantity_produced) : '—' }}
                            </td>
                            <td style="padding:12px 16px;">{{ $batch->produced_by }}</td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%);">{{ $batch->user?->name ?? '—' }}</td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%);">{{ $batch->notes ?: '—' }}</td>
                            <td style="padding:12px 16px; text-align:right; font-weight:500;">@money($batch->total_value)</td>
                            @can('delete-entries')
                            <td style="padding:12px 16px;">
                                <form action="{{ route('production.destroy', $batch) }}" method="POST"
                                      onsubmit="return confirm('Remove this production entry? This does not put back the ingredients it used, or take away the finished dish it made.')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Remove">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <script>
            (function () {
                const search = document.getElementById('batch-search');
                const count  = document.getElementById('batch-count');
                const rows   = document.getElementById('batch-rows');
                const total  = rows.querySelectorAll('tr').length;

                function filterBatches() {
                    const term = search.value.trim().toLowerCase();
                    let shown = 0;

                    rows.querySelectorAll('tr').forEach((row) => {
                        const hit = !term || row.textContent.toLowerCase().includes(term);
                        row.style.display = hit ? '' : 'none';
                        if (hit) shown++;
                    });

                    count.textContent = term ? shown + ' of ' + total + ' batches' : total + ' batches';
                }

                search.addEventListener('input', filterBatches);
                search.addEventListener('keydown', (e) => { if (e.key === 'Escape') { search.value = ''; filterBatches(); } });
            })();
        </script>
    @endif

    @can('manage-production')
    @php
        // Each recipe's formula for the form's live preview. Built here rather
        // than inline in @json() — Blade's directive parser cannot read the
        // nested array literal. The HEX flags are the ones @json() would have
        // applied, and they are not optional: a recipe name is user input, and
        // without them a dish called "</script>…" would break straight out of
        // the script block below.
        $recipeFormulas = $recipes->mapWithKeys(fn ($r) => [$r->id => [
            'name'   => $r->name,
            'cost'   => (float) $r->plate_cost,
            'output' => $r->outputInventoryItem?->name,
            'uses'   => $r->ingredients
                ->filter(fn ($i) => $i->inventoryItem)
                ->map(fn ($i) => [
                    // id is what lets the preview merge an ingredient shared by
                    // two dishes into one line — two half-covered lines would
                    // each look fine while the shelf cannot cover the pair.
                    'id'   => (int) $i->inventory_item_id,
                    'name' => $i->inventoryItem->name,
                    'unit' => $i->inventoryItem->unit,
                    'per'  => (float) $i->quantity,
                    'have' => (float) $i->inventoryItem->quantity_on_hand,
                ])->values(),
        ]])->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    @endphp

    {{-- ═══════ LOG PRODUCTION ═══════ --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:flex-start; justify-content:center; padding:32px 16px; overflow-y:auto;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:720px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">{{ __('Log Production') }}</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">{{ __('Say which dishes were made and how many. The recipes do the rest.') }}</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('production.store') }}" method="POST">
                @csrf

                {{-- ---- recipes produced: type a quantity next to each dish ---- --}}
                <label style="display:block; font-size:14px; font-weight:500; margin-bottom:2px;">{{ __('Recipes produced') }}</label>
                <p style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:6px;">
                    Search for a dish and type how many were made. Do that for every dish worked on —
                    the recipes decide what comes off the shelf.
                </p>
                <input type="search" id="recipe-search" placeholder="{{ __('Search recipes…') }}" autocomplete="off"
                       style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box; margin-bottom:6px;">
                <div id="recipe-list"
                     style="max-height:190px; overflow-y:auto; border:1px solid hsl(30,15%,88%); border-radius:6px; margin-bottom:4px;">
                    @forelse($recipes as $recipe)
                        <label class="recipe-row" data-name="{{ strtolower($recipe->name) }}"
                               style="display:flex; align-items:center; gap:10px; padding:9px 12px; border-bottom:1px solid hsl(30,15%,95%); cursor:pointer; font-size:14px;">
                            <span style="flex:1;">{{ $recipe->name }}</span>
                            @unless($recipe->output_inventory_item_id)
                                <span title="No finished dish is linked to this recipe, so a batch uses up its ingredients but adds nothing back. A manager can set this on the Recipes page."
                                      style="font-size:11px; font-weight:600; padding:1px 7px; border-radius:999px; background:hsl(35,60%,93%); color:hsl(30,55%,35%);">no finished item</span>
                            @endunless
                            <span style="font-size:12px; color:hsl(24,5%,50%); font-family:'JetBrains Mono',monospace;">@money($recipe->plate_cost)</span>
                            {{-- Typing a quantity is what selects the dish — no checkbox to forget.
                                 Rows hidden by the search still submit whatever was typed in them. --}}
                            {{-- Whole dishes only — you cannot cook half a toastie. 0 means
                                 none made and is treated the same as leaving it blank. --}}
                            <input type="number" class="recipe-qty" name="quantities[{{ $recipe->id }}]"
                                   step="1" min="0" inputmode="numeric" placeholder="0" autocomplete="off"
                                   aria-label="Quantity made of {{ $recipe->name }}"
                                   style="width:78px; padding:5px 8px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:13px; text-align:right; font-family:'JetBrains Mono',monospace;">
                        </label>
                    @empty
                        <p style="padding:14px; font-size:13px; color:hsl(24,5%,50%);">{{ __('No recipes yet — add one on the Recipes page first.') }}</p>
                    @endforelse
                </div>
                <p id="recipe-count" style="font-size:12px; color:hsl(24,5%,50%); margin-bottom:14px;">{{ $recipes->count() }} recipes</p>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">{{ __('Produced By') }}</label>
                        <input type="text" name="produced_by" placeholder="{{ __('Staff name') }}" required maxlength="100"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">{{ __('Date') }}</label>
                        <input type="date" name="production_date" value="{{ date('Y-m-d') }}" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">
                        Notes <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span>
                    </label>
                    <input type="text" name="notes" placeholder="{{ __('e.g. prepped for dinner service') }}" maxlength="500"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>

                {{-- ---- what this will take off the shelf ---- --}}
                <div style="border-top:1px solid hsl(30,15%,90%); padding-top:16px; margin-bottom:16px;">
                    <h3 style="font-size:15px; font-weight:600; margin-bottom:8px;">{{ __('What this uses') }}</h3>
                    <div id="formula" style="font-size:13px; color:hsl(24,5%,50%);">{{ __('Type a quantity next to a dish to see what comes off the shelf.') }}</div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:hsl(30,15%,96%); border-radius:6px; margin-bottom:16px;">
                    <span style="font-size:13px; color:hsl(24,5%,45%);">{{ __('Total value') }}</span>
                    <span id="total-preview" style="font-weight:600; font-size:16px; font-family:'JetBrains Mono',monospace;">RM 0.00</span>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('add-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Log production
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // The recipe formula, so the chef can see what a batch will cost the
        // shelf before saving. Preview only — LogProduction reads the recipe
        // again on save, and nothing about ingredients is submitted.
        const RECIPES = {!! $recipeFormulas !!};

        (function () {
            const search  = document.getElementById('recipe-search');
            const list    = document.getElementById('recipe-list');
            const count   = document.getElementById('recipe-count');
            const formula = document.getElementById('formula');
            const value   = document.getElementById('total-preview');
            const rows    = [...list.querySelectorAll('.recipe-row')];
            const total   = rows.length;

            const trim = (n) => Number(n.toFixed(3)).toString();
            const esc  = (s) => String(s).replace(/[&<>"']/g, (c) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[c]));

            // Every dish with a quantity typed against it. Hidden rows count —
            // a search term narrows the view, it does not clear what was typed.
            const entered = () => rows
                .map((row) => {
                    const input = row.querySelector('.recipe-qty');
                    const n     = parseFloat(input.value) || 0;
                    return n > 0 ? { id: input.name.match(/\[(\d+)\]/)[1], n } : null;
                })
                .filter(Boolean);

            function filterRecipes() {
                const term = search.value.trim().toLowerCase();
                let shown  = 0;

                rows.forEach((row) => {
                    const hit = !term || row.dataset.name.includes(term);
                    row.style.display = hit ? 'flex' : 'none';
                    if (hit) shown++;
                });

                const picked = entered().length;
                count.textContent = (term ? shown + ' of ' + total + ' recipes' : total + ' recipes')
                    + (picked ? ' · ' + picked + ' ' + (picked === 1 ? 'dish' : 'dishes') + ' entered' : '');
            }

            function paintFormula() {
                const picks = entered();
                filterRecipes();

                if (!picks.length) {
                    formula.innerHTML = 'Type a quantity next to a dish to see what comes off the shelf.';
                    value.textContent = 'RM 0.00';
                    return;
                }

                // Merge the formulas of every dish entered. An ingredient used
                // by two dishes becomes one line for the combined amount —
                // showing it twice would hide a shortage that only appears once
                // the two are added together.
                const need = new Map();
                const made = [];
                let cost   = 0;

                picks.forEach(({ id, n }) => {
                    const r = RECIPES[id];
                    if (!r) return;

                    cost += r.cost * n;
                    made.push({ name: r.name, n, output: r.output });

                    r.uses.forEach((u) => {
                        const at = need.get(u.id) || { name: u.name, unit: u.unit, have: u.have, qty: 0 };
                        at.qty += u.per * n;
                        need.set(u.id, at);
                    });
                });

                value.textContent = 'RM ' + cost.toFixed(2);

                if (!need.size) {
                    formula.innerHTML = '<span style="color:hsl(30,55%,35%);">None of these recipes list ingredients, so nothing will come off the shelf.</span>';
                    return;
                }

                // Flag anything the shelf cannot cover. Stock is never taken
                // below zero, so a short item means the figure inventory holds
                // is already behind what the kitchen actually has.
                const table = [...need.values()].map((u) => {
                    const short = u.qty > u.have;
                    return '<tr style="border-bottom:1px solid hsl(30,15%,95%);">'
                        + '<td style="padding:4px 8px 4px 0;">' + esc(u.name) + '</td>'
                        + '<td style="padding:4px 8px; text-align:right; font-family:\'JetBrains Mono\',monospace; color:' + (short ? 'hsl(0,60%,45%)' : 'hsl(24,10%,25%)') + ';">'
                        + trim(u.qty) + ' ' + esc(u.unit || '') + '</td>'
                        + '<td style="padding:4px 0 4px 8px; font-size:12px; color:' + (short ? 'hsl(0,60%,45%)' : 'hsl(24,5%,55%)') + ';">'
                        + (short ? 'only ' + trim(u.have) + ' on hand' : 'of ' + trim(u.have) + ' on hand') + '</td>'
                        + '</tr>';
                }).join('');

                const adds = made.map((m) => m.output
                    ? '<li style="color:hsl(145,45%,32%);">Adds <b>' + trim(m.n) + '</b> to <b>' + esc(m.output) + '</b> in inventory.</li>'
                    : '<li style="color:hsl(30,55%,35%);"><b>' + esc(m.name) + '</b> has no finished dish linked, so its ingredients are used up but nothing is added back.</li>'
                ).join('');

                formula.innerHTML = '<table style="width:100%; border-collapse:collapse; font-size:13px;">' + table + '</table>'
                    + '<ul style="margin:8px 0 0 18px; font-size:13px;">' + adds + '</ul>';
            }

            search.addEventListener('input', filterRecipes);
            search.addEventListener('keydown', (e) => { if (e.key === 'Escape') { search.value = ''; filterRecipes(); } });
            list.addEventListener('input', (e) => { if (e.target.classList.contains('recipe-qty')) paintFormula(); });

            // Guard the empty submit: every quantity blank would post nothing.
            list.closest('form').addEventListener('submit', (e) => {
                if (!entered().length) {
                    e.preventDefault();
                    search.value = '';
                    filterRecipes();
                    formula.innerHTML = '<span style="color:hsl(0,60%,45%);">Enter how many were made against at least one dish.</span>';
                }
            });
        })();
    </script>
    @endcan
</x-app-shell>
