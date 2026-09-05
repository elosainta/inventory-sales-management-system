<x-app-shell>

    @php
        $lines = $tally->lines;
        $withVariance = $lines->filter(fn ($l) => $l->variance() !== null && abs($l->variance()) > 0.001)->count();
        $fmt = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2), '0'), '.');
    @endphp

    <div class="app-page-header" style="margin-bottom:20px;">
        <a href="{{ route('tally.index') }}" style="font-size:13px; color:hsl(24,5%,45%); text-decoration:none;">&larr; All tally checks</a>
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin:8px 0;">
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400;">Tally check</h1>
            <span style="display:inline-block; padding:3px 12px; border-radius:999px; font-size:13px; font-weight:600; background:hsl(35,60%,94%); color:hsl(30,55%,35%);">
                {{ $tally->counted_on->format('j M Y') }}
            </span>
        </div>
        <p style="color:hsl(24,5%,45%); font-size:14px;">
            Counted by {{ $tally->counter?->name ?? 'Unknown' }} · recorded {{ $tally->created_at->format('j M Y, g:ia') }}
        </p>
        @if($tally->note)
            <p style="margin-top:10px; padding:10px 14px; background:hsl(35,60%,96%); border-radius:6px; font-size:14px; color:hsl(24,10%,30%);">{{ $tally->note }}</p>
        @endif
    </div>

    <div style="display:flex; gap:14px; flex-wrap:wrap; margin-bottom:20px;">
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:14px 20px;">
            <div style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:4px;">Items counted</div>
            <div style="font-size:24px; font-family:'JetBrains Mono',monospace; font-weight:600;">{{ $lines->count() }}</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:14px 20px;">
            <div style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:4px;">Differs from system</div>
            <div style="font-size:24px; font-family:'JetBrains Mono',monospace; font-weight:600; color:{{ $withVariance ? 'hsl(0,55%,45%)' : 'hsl(145,45%,35%)' }};">{{ $withVariance }}</div>
        </div>
    </div>

    <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px; min-width:640px;">
                <thead>
                    <tr style="background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%);">
                        <th style="text-align:left; padding:11px 20px; font-weight:600;">Item</th>
                        <th style="text-align:left; padding:11px 16px; font-weight:600;">Category</th>
                        <th style="text-align:left; padding:11px 16px; font-weight:600;">Unit</th>
                        <th style="text-align:right; padding:11px 16px; font-weight:600;">System</th>
                        <th style="text-align:right; padding:11px 16px; font-weight:600;">Counted</th>
                        <th style="text-align:right; padding:11px 20px; font-weight:600;">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($lines as $line)
                        @php
                            $v = $line->variance();
                            $vColor = $v === null ? 'hsl(24,5%,55%)' : (abs($v) < 0.001 ? 'hsl(145,45%,35%)' : ($v < 0 ? 'hsl(0,55%,45%)' : 'hsl(30,60%,42%)'));
                            $vText = $v === null ? '—' : ($v > 0.001 ? '+' : '') . rtrim(rtrim(number_format($v, 2), '0'), '.');
                        @endphp
                        <tr style="border-bottom:1px solid hsl(30,15%,94%);">
                            <td style="padding:10px 20px; font-weight:500;">{{ $line->item_name }}</td>
                            <td style="padding:10px 16px; color:hsl(24,5%,45%);">{{ $line->category ?? '—' }}</td>
                            <td style="padding:10px 16px; color:hsl(24,5%,45%);">{{ $line->unit ?? '—' }}</td>
                            <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(24,5%,50%);">{{ $fmt($line->system_quantity) }}</td>
                            <td style="padding:10px 16px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600;">{{ $fmt($line->counted_quantity) }}</td>
                            <td style="padding:10px 20px; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:600; color:{{ $vColor }};">{{ $vText }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:24px 20px; text-align:center; color:hsl(24,5%,50%);">No items counted.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p style="margin-top:14px; font-size:12px; color:hsl(24,5%,50%);">
        Variance is counted minus system at the time of counting. A negative figure means less on the shelf than the
        records showed. Saving this tally reconciled each counted item's live stock to the figure entered here.
    </p>

</x-app-shell>
