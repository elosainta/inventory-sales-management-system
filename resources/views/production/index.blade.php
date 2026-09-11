<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Production') }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px; max-width:600px;">
                What the kitchen cooked. Tap a dish, say how many were made and what each ingredient
                actually took — that is what comes off the shelf.
            </p>
        </div>
    </div>

    @include('partials.dish-grid', [
        'dishRoute'  => 'production.dish',
        'ability'    => 'manage-production',
        'hint'       => __('Tap a dish to log how many were made.'),
        'cta'        => __('Log production'),
        'logHeading' => __('Production log'),
    ])

    @include('partials.period-filter', ['route' => 'production.index', 'totalLabel' => 'Total value', 'total' => $totalValue])

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
                                      onsubmit="return confirm('Remove this production entry? What it took off the shelf goes back, and the dishes it made come off.')" style="display:inline;">
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

</x-app-shell>
