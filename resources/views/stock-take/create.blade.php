<x-app-shell>

@php
    // Shaped here, not inline in the json directive further down — see the
    // note there for why that breaks.
    // Pantry is inventory. The sheet no longer prints a 57-line catalog for a
    // chef to scroll past; they search inventory and the ones that moved go on
    // it. What is searched is inventory plus the handful of catalog lines with
    // no inventory item behind them — those record a count and move no stock,
    // and the kitchen has been counting some of them for months, so they stay
    // findable rather than disappearing with the printed list.
    $pickerPayload = $inventoryItems->map(fn ($i) => [
        'id'   => (int) $i->id,
        'name' => $i->name,
        'unit' => $i->unit,
        'qty'  => (float) $i->quantity_on_hand,
        'kind' => 'inventory',
    ])->concat($items->whereNull('inventory_item_id')->map(fn ($i) => [
        'id'   => (int) $i->id,
        'name' => $i->name,
        'unit' => $i->default_unit,
        'qty'  => null,
        'kind' => 'catalog',
    ]))->values();

    // The notices JS rewrites. Shaped here, not inline in @json below: that
    // directive splits its argument on commas, so the day one of these strings
    // gains one it compiles to a ParseError instead of a page.
    $jsStrings = [
        'empty'  => __('nothing on the sheet yet'),
        'prompt' => __('Search for an ingredient to put it on this sheet.'),
    ];
@endphp

    <div class="app-page-header" style="margin-bottom:20px;">
        <a href="{{ route('stock-take.index') }}" style="font-size:13px; color:hsl(24,5%,45%); text-decoration:none;">&larr; {{ __('All stock-takes') }}</a>
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin:8px 0;">{{ __('New stock-take') }}</h1>
    </div>

    {{-- Section tabs — only worth showing while there is a choice to make. --}}
    @if(count(\App\Models\StockTakeItem::SECTIONS) > 1)
    <div style="display:flex; gap:8px; margin-bottom:20px;">
        @foreach(\App\Models\StockTakeItem::SECTIONS as $key => $label)
            <a href="{{ route('stock-take.create', ['section' => $key]) }}"
               style="padding:8px 18px; border-radius:6px; font-size:14px; font-weight:600; text-decoration:none;
                      background:{{ $section === $key ? 'hsl(20,60%,45%)' : 'white' }};
                      color:{{ $section === $key ? 'white' : 'hsl(24,10%,35%)' }};
                      border:1px solid {{ $section === $key ? 'hsl(20,60%,45%)' : 'hsl(30,15%,85%)' }};">
                {{ $label }}
            </a>
        @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('stock-take.store') }}">
        @csrf
        <input type="hidden" name="section" value="{{ $section }}">

        <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:20px;">
            <div>
                <label style="display:block; font-size:12px; font-weight:600; color:hsl(24,10%,35%); margin-bottom:6px;">{{ __('Count date') }}</label>
                <input type="date" name="taken_on" value="{{ old('taken_on', now()->toDateString()) }}" required
                       style="padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; background:white;">
            </div>
        </div>

        {{-- ═══════ COUNT SHEET ═══════ --}}
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden; margin-bottom:20px;">
            <div style="padding:14px 20px; border-bottom:1px solid hsl(30,15%,90%); display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500;">{{ __(ucfirst($section) . ' count') }}</h3>
                <span id="sheet-count" style="font-size:12px; color:hsl(24,5%,50%); font-family:'JetBrains Mono',monospace;">{{ __('nothing on the sheet yet') }}</span>
            </div>

            {{-- The sheet starts empty and this is how things get on to it.
                 It used to print all 57 catalog lines, and a chef counting the
                 four things that moved scrolled past fifty-three that did not. --}}
            <div style="padding:14px 20px; border-bottom:1px solid hsl(30,15%,92%); background:hsl(30,20%,98%);">
                <input type="search" id="item-search" placeholder="{{ __('Search inventory to add an item…') }}" autocomplete="off"
                       style="width:100%; padding:11px 14px; border:1px solid hsl(30,15%,80%); border-radius:6px; font-size:14px; background:white;">
                <div id="suggest-list" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;"></div>
                <p id="suggest-note" style="font-size:12px; color:hsl(24,5%,50%); margin:9px 0 0;">{{ __('Search for an ingredient to put it on this sheet.') }}</p>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:760px;">
                    <thead>
                        <tr style="background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%);">
                            <th style="text-align:left; padding:10px 14px; font-weight:600; min-width:180px;">{{ __('Item') }}</th>
                            <th style="text-align:left; padding:10px 14px; font-weight:600; width:80px;">{{ __('Unit') }}</th>
                            <th style="text-align:right; padding:10px 14px; font-weight:600; width:120px;">{{ __('Current stock') }}</th>
                            <th style="text-align:right; padding:10px 14px; font-weight:600; width:100px;">{{ __('In') }}</th>
                            <th style="text-align:right; padding:10px 14px; font-weight:600; width:100px;">{{ __('Out') }}</th>
                            <th style="text-align:right; padding:10px 14px; font-weight:600; width:120px;">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody id="entry-rows"></tbody>
                </table>
            </div>

            <div id="empty-sheet" style="padding:22px 20px; text-align:center; font-size:13px; color:hsl(24,5%,55%); border-top:1px solid hsl(30,15%,94%);">
                {{ __('No items yet. Use the search above to add what moved.') }}
            </div>

        </div>

        <div style="display:flex; gap:10px;">
            <button type="submit" style="padding:11px 26px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;">{{ __('Save stock-take') }}</button>
            <a href="{{ route('stock-take.index') }}" style="padding:11px 22px; background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,35%); border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">{{ __('Cancel') }}</a>
        </div>
    </form>

    <script>
        let entryIdx = 0;

        // Everything a chef can put on the sheet: inventory, plus the catalog
        // lines nothing in inventory backs. Shaped at the top of this file
        // rather than inline here — the json directive splits its argument on
        // commas, so an expression containing any loses its tail and compiles
        // to broken PHP, which view:cache does NOT catch because it writes the
        // compiled file without parsing it. Same trap as partials/item-picker.
        const pickable = @json($pickerPayload);

        // Left as English literals these would overwrite the translated Blade
        // defaults the moment the page loaded. Shaped at the top of this file.
        const T = @json($jsStrings);

        const rows       = document.getElementById('entry-rows');
        const emptySheet = document.getElementById('empty-sheet');
        const sheetCount = document.getElementById('sheet-count');

        // Preview only. The server reads current stock off live inventory and
        // works the balance out again on save, so nothing here is trusted —
        // it is here so the chef can see the shelf figure before committing.
        function paintBalance(row) {
            const current = row.dataset.current;
            const cell    = row.querySelector('.balance');
            if (!cell) return;

            if (current === undefined || current === '') {
                cell.textContent = '—';
                return;
            }

            const value = Math.max(0, Number(current)
                + Number(row.querySelector('.qty-in')?.value || 0)
                - Number(row.querySelector('.qty-out')?.value || 0));

            cell.textContent = Number(value.toFixed(2)).toString();
            cell.style.color = value < Number(current) ? 'hsl(0,55%,45%)'
                : value > Number(current) ? 'hsl(145,45%,32%)'
                : 'hsl(24,10%,25%)';
        }

        function numCell(i, field, cls) {
            return `<td style="padding:6px 8px;"><input type="number" step="0.01" min="0" name="entries[${i}][${field}]" class="${cls}" style="width:90px; padding:6px 8px; border:1px solid hsl(30,15%,88%); border-radius:5px; font-size:13px; text-align:right; font-family:'JetBrains Mono',monospace;"></td>`;
        }

        function refreshSheet() {
            const total  = rows.children.length;
            const filled = [...rows.querySelectorAll('.qty-in, .qty-out')].filter((i) => i.value !== '').length;

            emptySheet.style.display = total ? 'none' : '';
            sheetCount.textContent = total
                ? total + ' item' + (total === 1 ? '' : 's') + (filled ? ' · ' + filled + ' entered' : '')
                : T.empty;
        }

        // Put a searched item on the sheet. An inventory pick carries its id and
        // the server turns it into a catalog line, so it moves stock like any
        // other row; a catalog pick with nothing behind it stays count-only.
        function addPickedRow(item) {
            const key      = item.kind + ':' + item.id;
            const existing = rows.querySelector(`tr[data-picked="${key}"]`);
            if (existing) {
                existing.scrollIntoView({ block: 'center', behavior: 'smooth' });
                existing.querySelector('.qty-in')?.focus();
                flash(existing);
                return;
            }

            const i      = entryIdx++;
            const linked = item.qty !== null && item.qty !== undefined;
            const field  = item.kind === 'inventory' ? 'inventory_item_id' : 'stock_take_item_id';

            const row = document.createElement('tr');
            row.style.borderBottom = '1px solid hsl(30,15%,94%)';
            row.dataset.picked = key;
            if (linked) row.dataset.current = item.qty;

            row.innerHTML =
                `<td style="padding:6px 14px; font-weight:500;"></td>`
                + `<td style="padding:6px 14px; color:hsl(24,5%,45%);"></td>`
                + (linked
                    ? `<td style="padding:6px 14px; text-align:right; font-family:'JetBrains Mono',monospace;">${Number(item.qty.toFixed(2))}</td>`
                    : `<td style="padding:6px 14px; text-align:right; font-size:12px; color:hsl(24,5%,58%);">Not linked</td>`)
                + numCell(i, 'qty_in', 'qty-in') + numCell(i, 'qty_out', 'qty-out')
                + `<td class="balance" style="padding:6px 14px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600; color:hsl(24,10%,25%);">—</td>`;

            // textContent, not innerHTML: ingredient names are user input.
            row.cells[0].textContent = item.name;
            row.cells[1].textContent = item.unit || '—';
            row.cells[0].appendChild(Object.assign(document.createElement('input'), {
                type: 'hidden', name: `entries[${i}][${field}]`, value: item.id,
            }));

            rows.prepend(row);
            paintBalance(row);
            refreshSheet();
            flash(row);
            row.querySelector('.qty-in').focus();
        }

        function flash(row) {
            row.style.transition = 'background 1.2s';
            row.style.background = 'hsl(40,60%,90%)';
            setTimeout(() => { row.style.background = ''; }, 1200);
        }

        rows.addEventListener('input', (e) => {
            if (e.target.matches('.qty-in, .qty-out')) paintBalance(e.target.closest('tr'));
            refreshSheet();
        });

        // Search. One box, and it is the only way something reaches the sheet
        // apart from the free-text button below it.
        const search      = document.getElementById('item-search');
        const suggestList = document.getElementById('suggest-list');
        const suggestNote = document.getElementById('suggest-note');
        const SUGGEST_MAX = 10;

        function renderSuggestions() {
            const term = search.value.trim().toLowerCase();
            suggestList.textContent = '';

            if (! term) {
                suggestNote.textContent = T.prompt;
                return;
            }

            const onSheet = new Set([...rows.querySelectorAll('tr[data-picked]')].map((r) => r.dataset.picked));
            const hits    = pickable.filter((i) => i.name.toLowerCase().includes(term)
                && ! onSheet.has(i.kind + ':' + i.id));

            hits.slice(0, SUGGEST_MAX).forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                // textContent, not innerHTML: ingredient names are user input.
                button.textContent = item.name + (item.unit ? ' (' + item.unit + ')' : '')
                    + (item.kind === 'catalog' ? ' · count only' : '');
                button.style.cssText = 'padding:7px 13px; border:1px solid hsl(20,40%,75%); background:white; color:hsl(20,60%,38%); border-radius:6px; font-size:13px; font-weight:500; cursor:pointer;';
                button.addEventListener('click', () => {
                    addPickedRow(item);
                    search.value = '';
                    renderSuggestions();
                    search.focus();
                });
                suggestList.appendChild(button);
            });

            if (! hits.length) {
                // textContent, not innerHTML — the term is whatever was typed.
                suggestNote.textContent = 'Nothing in inventory matches “' + search.value.trim() + '”.';
            } else {
                suggestNote.textContent = hits.length > SUGGEST_MAX
                    ? 'Showing ' + SUGGEST_MAX + ' of ' + hits.length + ' — keep typing to narrow it down.'
                    : '';
            }
        }

        search.addEventListener('input', renderSuggestions);
        search.addEventListener('keydown', (e) => { if (e.key === 'Escape') { search.value = ''; renderSuggestions(); } });
        refreshSheet();

    </script>

</x-app-shell>
