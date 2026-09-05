<x-app-shell>

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:28px;">
        <div class="app-page-header">
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">{{ __('Tally check') }}</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px; max-width:600px;">
                {{-- Double-quoted so the apostrophe in "item's" needs no escaping. --}}
                {{ __("A manual count against live inventory — walk the shelves and enter what you actually find. Saving a tally records what was counted, who counted, and when, and reconciles each counted item's live stock to the figure you entered. The recorded variance keeps a history of every discrepancy for the Owner.") }}
            </p>
        </div>
        @can('record-tally')
            <a href="{{ route('tally.create') }}"
               style="padding:9px 16px; background:hsl(24,10%,16%); color:white; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; white-space:nowrap;">+ {{ __('New tally check') }}</a>
        @endcan
    </div>

    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:13px; min-width:560px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 20px; font-weight:600;">{{ __('Date') }}</th>
                        <th style="text-align:left; padding:12px 20px; font-weight:600;">{{ __('Counted by') }}</th>
                        <th style="text-align:right; padding:12px 20px; font-weight:600;">{{ __('Items counted') }}</th>
                        <th style="padding:12px 20px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tallies as $tally)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 20px; font-weight:500;">{{ $tally->counted_on->format('j M Y') }}</td>
                            <td style="padding:12px 20px; color:hsl(24,10%,30%);">{{ $tally->counter?->name ?? '—' }}</td>
                            <td style="padding:12px 20px; text-align:right; font-family:'JetBrains Mono',monospace;">{{ $tally->lines_count }}</td>
                            <td style="padding:12px 20px; text-align:right;">
                                <a href="{{ route('tally.show', $tally) }}" style="color:hsl(20,60%,42%); font-weight:600; text-decoration:none;">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="padding:32px 20px; text-align:center; color:hsl(24,5%,50%);">
                            {{ __('No tally checks recorded yet.') }}
                            @can('record-tally') Start one with the button above. @endcan
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:16px;">{{ $tallies->links() }}</div>

</x-app-shell>
