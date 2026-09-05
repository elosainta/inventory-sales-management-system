<x-app-shell>

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:28px;">
        <div class="app-page-header">
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Stock-take') }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px; max-width:560px;">
                {{ __('What came in and what went out, counted off the shelves and kept as a dated sheet. Recording one moves live inventory: In adds to an item, Out takes off it, and the balance is what that item is left holding.') }}
            </p>
        </div>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            @can('record-stock-take')
                <a href="{{ route('stock-take.create', ['section' => 'pantry']) }}"
                   style="padding:9px 16px; background:hsl(24,10%,16%); color:white; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none;">+ {{ __('Pantry count') }}</a>
            @endcan
            @can('manage-stock-take-items')
                <a href="{{ route('stock-take-items.index') }}"
                   style="padding:9px 16px; background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,25%); border-radius:6px; font-size:13px; font-weight:500; text-decoration:none;">{{ __('Manage items') }}</a>
            @endcan
        </div>
    </div>

    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:13px; min-width:560px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 20px; font-weight:600;">{{ __('Date') }}</th>
                        <th style="text-align:left; padding:12px 20px; font-weight:600;">{{ __('Section') }}</th>
                        <th style="text-align:left; padding:12px 20px; font-weight:600;">{{ __('Counted by') }}</th>
                        <th style="text-align:right; padding:12px 20px; font-weight:600;">{{ __('Items moved') }}</th>
                        <th style="padding:12px 20px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockTakes as $stockTake)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 20px; font-weight:500;">{{ $stockTake->taken_on->format('j M Y') }}</td>
                            <td style="padding:12px 20px;">
                                <span style="display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600;
                                    background:{{ $stockTake->section === 'pantry' ? 'hsl(35,60%,94%)' : 'hsl(200,45%,94%)' }};
                                    color:{{ $stockTake->section === 'pantry' ? 'hsl(30,55%,35%)' : 'hsl(200,55%,32%)' }};">
                                    {{ $stockTake->sectionLabel() }}
                                </span>
                            </td>
                            <td style="padding:12px 20px; color:hsl(24,10%,30%);">{{ $stockTake->counter?->name ?? '—' }}</td>
                            <td style="padding:12px 20px; text-align:right; font-family:'JetBrains Mono',monospace;">{{ $stockTake->entries_count }}</td>
                            <td style="padding:12px 20px; text-align:right;">
                                <a href="{{ route('stock-take.show', $stockTake) }}" style="color:hsl(20,60%,42%); font-weight:600; text-decoration:none;">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="padding:32px 20px; text-align:center; color:hsl(24,5%,50%);">
                            {{ __('No stock-takes recorded yet.') }}
                            @can('record-stock-take') Start one with the buttons above. @endcan
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:16px;">{{ $stockTakes->links() }}</div>

</x-app-shell>
