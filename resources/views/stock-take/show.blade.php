<x-app-shell>

    @php($num = fn ($v) => $v !== null ? rtrim(rtrim(number_format($v, 2), '0'), '.') : '—')

    <div class="app-page-header" style="margin-bottom:20px;">
        <a href="{{ route('stock-take.index') }}" style="font-size:13px; color:hsl(24,5%,45%); text-decoration:none;">&larr; All stock-takes</a>
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin:8px 0;">
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400;">{{ $stockTake->sectionLabel() }} count</h1>
            <span style="display:inline-block; padding:3px 12px; border-radius:999px; font-size:13px; font-weight:600;
                background:{{ $stockTake->section === 'pantry' ? 'hsl(35,60%,94%)' : 'hsl(200,45%,94%)' }};
                color:{{ $stockTake->section === 'pantry' ? 'hsl(30,55%,35%)' : 'hsl(200,55%,32%)' }};">
                {{ $stockTake->taken_on->format('j M Y') }}
            </span>
        </div>
        <p style="color:hsl(24,5%,45%); font-size:14px;">
            Counted by {{ $stockTake->counter?->name ?? 'Unknown' }} · recorded {{ $stockTake->created_at->format('j M Y, g:ia') }}
        </p>
        @if($stockTake->note)
            <p style="margin-top:10px; padding:10px 14px; background:hsl(35,60%,96%); border-radius:6px; font-size:14px; color:hsl(24,10%,30%);">{{ $stockTake->note }}</p>
        @endif
    </div>

    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden; margin-bottom:20px;">
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:720px;">
                <thead>
                    <tr style="background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%);">
                        <th style="text-align:left; padding:11px 16px; font-weight:600;">Item</th>
                        <th style="text-align:left; padding:11px 16px; font-weight:600;">Unit</th>
                        <th style="text-align:right; padding:11px 16px; font-weight:600;">Current stock</th>
                        <th style="text-align:right; padding:11px 16px; font-weight:600;">In</th>
                        <th style="text-align:right; padding:11px 16px; font-weight:600;">Out</th>
                        <th style="text-align:right; padding:11px 16px; font-weight:600;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($stockTake->entries as $entry)
                        <tr style="border-bottom:1px solid hsl(30,15%,94%);">
                            <td style="padding:10px 16px; font-weight:500;">
                                {{ $entry->item_name }}
                                {{-- A line with no stock figure had no inventory item behind it, so it
                                     recorded a count and moved nothing. Saying so here is the only place
                                     the sheet can show which lines reached inventory and which did not. --}}
                                @if($entry->current_stock === null)
                                    <span style="margin-left:6px; padding:1px 7px; border-radius:999px; font-size:11px; font-weight:600; background:hsl(30,15%,94%); color:hsl(24,5%,45%);"
                                          title="No inventory item is linked to this line, so it moved no stock.">count only</span>
                                @endif
                            </td>
                            <td style="padding:10px 16px; color:hsl(24,5%,45%);">{{ $entry->unit ?? '—' }}</td>
                            <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(24,5%,45%);">{{ $num($entry->current_stock) }}</td>
                            <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(145,45%,35%);">{{ $num($entry->qty_in) }}</td>
                            <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(0,55%,45%);">{{ $num($entry->qty_out) }}</td>
                            <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">{{ $num($entry->balance) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:24px 16px; text-align:center; color:hsl(24,5%,50%);">No stock moved on this sheet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($stockTake->openOrders->isNotEmpty())
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <div style="padding:13px 20px; border-bottom:1px solid hsl(30,15%,90%);">
                <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500;">Open order</h3>
                <span style="font-size:12px; color:hsl(24,5%,50%);">Noted at the time of the count. Not in inventory until it arrives and is entered as In.</span>
            </div>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:480px;">
                    <thead>
                        <tr style="background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%);">
                            <th style="text-align:left; padding:10px 16px; font-weight:600;">Item</th>
                            <th style="text-align:left; padding:10px 16px; font-weight:600;">Quantity</th>
                            <th style="text-align:left; padding:10px 16px; font-weight:600;">Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($stockTake->openOrders as $order)
                            <tr style="border-bottom:1px solid hsl(30,15%,94%);">
                                <td style="padding:10px 16px; font-weight:500;">{{ $order->item_name }}</td>
                                <td style="padding:10px 16px; font-family:'JetBrains Mono',monospace;">{{ $order->quantity ?? '—' }}</td>
                                <td style="padding:10px 16px; color:hsl(24,5%,45%);">{{ $order->note ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</x-app-shell>
