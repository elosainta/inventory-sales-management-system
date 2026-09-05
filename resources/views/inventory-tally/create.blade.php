<x-app-shell>

@php
    // Shaped here, not inline in the json directive below — that directive
    // splits its argument on commas, so an expression containing any loses its
    // tail and compiles to broken PHP. `view:cache` does NOT catch it, because
    // it writes the compiled file without parsing it.
    $pickerPayload = $items->map(fn ($i) => [
        'id'       => (int) $i->id,
        'name'     => $i->name,
        'unit'     => $i->unit,
        'category' => $i->category ?: 'Uncategorised',
        'qty'      => (float) $i->quantity_on_hand,
    ])->values();

    // The notices JS rewrites, shaped here for the same reason as the payload
    // above: the json directive splits its argument on commas, and one of these
    // strings contains one. Inline, it compiled to a ParseError.
    $jsStrings = [
        'empty'  => __('nothing on the sheet yet'),
        'prompt' => __('Search for an ingredient, or add a whole category:'),
    ];
@endphp

    <div class="app-page-header" style="margin-bottom:20px;">
        <a href="{{ route('tally.index') }}" style="font-size:13px; color:hsl(24,5%,45%); text-decoration:none;">&larr; {{ __('Back to tally checks') }}</a>
        <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin:8px 0;">{{ __('New tally check') }}</h1>
    </div>

    @if(session('error'))
        <div style="margin-bottom:16px; padding:12px 16px; background:hsl(0,70%,96%); border:1px solid hsl(0,60%,85%); border-radius:6px; color:hsl(0,55%,38%); font-size:14px;">
            {{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('tally.store') }}">
        @csrf

        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px; margin-bottom:20px; display:flex; gap:20px; flex-wrap:wrap; align-items:flex-end;">
            <div style="min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:600; color:hsl(24,10%,35%); margin-bottom:6px;">{{ __('Count date') }}</label>
                <input type="date" name="counted_on" value="{{ old('counted_on', now()->toDateString()) }}" required
                       style="padding:8px 11px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:14px;">
            </div>
            <div style="flex:1; min-width:240px;">
                <label style="display:block; font-size:12px; font-weight:600; color:hsl(24,10%,35%); margin-bottom:6px;">{{ __('Note (optional)') }}</label>
                <input type="text" name="note" value="{{ old('note') }}" maxlength="2000" placeholder="{{ __('e.g. weekly Monday count') }}"
                       style="width:100%; padding:8px 11px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:14px;">
            </div>
        </div>

        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden; margin-bottom:20px;">
            <div style="padding:14px 20px; border-bottom:1px solid hsl(30,15%,90%); display:flex; justify-content:space-between; align-items:center; gap:8px;">
                <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500;">{{ __('Count sheet') }}</h3>
                <span id="sheet-count" style="font-size:12px; color:hsl(24,5%,50%); font-family:'JetBrains Mono',monospace;"></span>
            </div>

            {{-- The sheet starts empty and this is how things get on to it. It
                 used to print all 237 inventory items across eight category
                 cards, and counting one shelf meant scrolling past seven of
                 them. The category buttons below are what keeps a full walk of
                 the shelves possible — search is for a handful, a category is
                 for a shelf, and both put ordinary rows on the same sheet. --}}
            <div style="padding:14px 20px; border-bottom:1px solid hsl(30,15%,92%); background:hsl(30,20%,98%);">
                <input type="search" id="item-search" placeholder="{{ __('Search inventory to add an item…') }}" autocomplete="off"
                       style="width:100%; padding:11px 14px; border:1px solid hsl(30,15%,80%); border-radius:6px; font-size:14px; background:white;">
                <div id="suggest-list" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px;"></div>
                <p id="suggest-note" style="font-size:12px; color:hsl(24,5%,50%); margin:9px 0 0;"></p>
            </div>

            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:520px;">
                    <thead>
                        <tr style="background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%); color:hsl(24,5%,45%);">
                            <th style="text-align:left; padding:10px 20px; font-weight:600;">{{ __('Item') }}</th>
                            <th style="text-align:left; padding:10px 16px; font-weight:600; width:90px;">{{ __('Unit') }}</th>
                            <th style="text-align:right; padding:10px 16px; font-weight:600; width:110px;">{{ __('System') }}</th>
                            <th style="text-align:right; padding:10px 20px; font-weight:600; width:150px;">{{ __('Counted') }}</th>
                        </tr>
                    </thead>
                    <tbody id="tally-rows"></tbody>
                </table>
            </div>

            <div id="empty-sheet" style="padding:22px 20px; text-align:center; font-size:13px; color:hsl(24,5%,55%); border-top:1px solid hsl(30,15%,94%);">
                {{ __('No items yet. Use the search above to add what you counted.') }}
            </div>
        </div>

        <div style="display:flex; gap:10px; align-items:center; position:sticky; bottom:0; background:hsl(40,33%,98%); padding:14px 0;">
            <button type="submit" style="padding:11px 22px; background:hsl(20,60%,45%); color:white; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer;">{{ __('Save tally check') }}</button>
            <a href="{{ route('tally.index') }}" style="padding:11px 18px; color:hsl(24,5%,45%); text-decoration:none; font-size:14px;">{{ __('Cancel') }}</a>
        </div>
    </form>

    <script>
        // Every live inventory item, so the search and the category buttons can
        // put any of them on the sheet. See the note at the top of this file for
        // why it is shaped in PHP rather than inline here.
        const pickable = @json($pickerPayload);

        // Left as English literals these would overwrite the translated Blade
        // defaults the moment the page loaded. Shaped at the top of this file.
        const T = @json($jsStrings);

        let lineIdx = 0;

        const rows       = document.getElementById('tally-rows');
        const emptySheet = document.getElementById('empty-sheet');
        const sheetCount = document.getElementById('sheet-count');

        function refreshSheet() {
            const total  = rows.children.length;
            const filled = [...rows.querySelectorAll('.counted')].filter((i) => i.value !== '').length;

            emptySheet.style.display = total ? 'none' : '';
            sheetCount.textContent = total
                ? total + ' item' + (total === 1 ? '' : 's') + (filled ? ' · ' + filled + ' counted' : '')
                : T.empty;
        }

        // A row carries only the item id and what was counted. The name, unit,
        // category and system figure are all read off the database at save time
        // — a form is not where a stock record should come from, and the
        // variance the Owner reviews depends on that figure being ours.
        function addRow(item, options = {}) {
            if (rows.querySelector(`tr[data-item="${item.id}"]`)) {
                if (options.quiet) return false;
                const existing = rows.querySelector(`tr[data-item="${item.id}"]`);
                existing.scrollIntoView({ block: 'center', behavior: 'smooth' });
                existing.querySelector('.counted')?.focus();
                flash(existing);
                return false;
            }

            const i   = lineIdx++;
            const row = document.createElement('tr');
            row.style.borderBottom = '1px solid hsl(30,15%,95%)';
            row.dataset.item = item.id;
            row.innerHTML =
                `<td style="padding:8px 20px; font-weight:500;"></td>`
                + `<td style="padding:8px 16px; color:hsl(24,5%,45%);"></td>`
                + `<td style="padding:8px 16px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(24,5%,50%);">${Number(item.qty.toFixed(2))}</td>`
                + `<td style="padding:6px 20px; text-align:right;"><input type="number" step="0.01" min="0" class="counted" name="lines[${i}][counted_quantity]" placeholder="—" style="width:110px; padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:13px; text-align:right; font-family:'JetBrains Mono',monospace;"></td>`;

            // textContent, not innerHTML: ingredient names are user input.
            row.cells[0].textContent = item.name;
            row.cells[1].textContent = item.unit || '—';
            row.cells[0].appendChild(Object.assign(document.createElement('input'), {
                type: 'hidden', name: `lines[${i}][inventory_item_id]`, value: item.id,
            }));

            options.append ? rows.appendChild(row) : rows.prepend(row);
            return row;
        }

        function flash(row) {
            row.style.transition = 'background 1.2s';
            row.style.background = 'hsl(40,60%,90%)';
            setTimeout(() => { row.style.background = ''; }, 1200);
        }

        // ── Search, and whole categories ────────────────────────────────────
        const search      = document.getElementById('item-search');
        const suggestList = document.getElementById('suggest-list');
        const suggestNote = document.getElementById('suggest-note');
        const SUGGEST_MAX = 10;

        function chip(label, onClick) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;   // textContent: names are user input
            button.style.cssText = 'padding:7px 13px; border:1px solid hsl(20,40%,75%); background:white; color:hsl(20,60%,38%); border-radius:6px; font-size:13px; font-weight:500; cursor:pointer;';
            button.addEventListener('click', onClick);
            suggestList.appendChild(button);
            return button;
        }

        function onSheet() {
            return new Set([...rows.querySelectorAll('tr[data-item]')].map((r) => Number(r.dataset.item)));
        }

        // A shelf at a time. Rows are appended in inventory order so the sheet
        // reads the way the shelves do, and anything already on it is left
        // where it is rather than jumping to the end.
        function addCategory(category) {
            const already = onSheet();
            pickable.filter((i) => i.category === category && ! already.has(i.id))
                .forEach((item) => addRow(item, { append: true, quiet: true }));
            refreshSheet();
        }

        function renderSuggestions() {
            const term = search.value.trim().toLowerCase();
            suggestList.textContent = '';

            if (! term) {
                const already = onSheet();
                const counts  = new Map();
                pickable.forEach((i) => {
                    if (already.has(i.id)) return;
                    counts.set(i.category, (counts.get(i.category) || 0) + 1);
                });

                [...counts.entries()].sort((a, b) => a[0].localeCompare(b[0]))
                    .forEach(([category, n]) => chip(category + ' · ' + n, () => addCategory(category)));

                suggestNote.textContent = T.prompt;
                return;
            }

            const already = onSheet();
            const hits    = pickable.filter((i) => i.name.toLowerCase().includes(term) && ! already.has(i.id));

            hits.slice(0, SUGGEST_MAX).forEach((item) => {
                chip(item.name + (item.unit ? ' (' + item.unit + ')' : ''), () => {
                    const row = addRow(item);
                    refreshSheet();
                    search.value = '';
                    renderSuggestions();
                    if (row) { flash(row); row.querySelector('.counted').focus(); }
                });
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

        rows.addEventListener('input', refreshSheet);
        search.addEventListener('input', renderSuggestions);
        search.addEventListener('keydown', (e) => { if (e.key === 'Escape') { search.value = ''; renderSuggestions(); } });

        refreshSheet();
        renderSuggestions();
    </script>

</x-app-shell>
