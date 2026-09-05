<x-pdf-base title="R&D Report">
    <h2>R&amp;D</h2>
    <p class="subtitle">A costing sheet per trial: what the dish was made with, what it came to, and what the Owner decided.</p>

    @php
        $approved = $entries->where('status', \App\Models\RndEntry::STATUS_APPROVED);
        $pending  = $entries->where('status', \App\Models\RndEntry::STATUS_PENDING);
        $rejected = $entries->where('status', \App\Models\RndEntry::STATUS_REJECTED);
        $qty      = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
    @endphp

    <div class="summary">
        Trials: <strong>{{ $entries->count() }}</strong> &middot;
        Approved: <strong>{{ $approved->count() }}</strong> (RM {{ number_format($approved->sum('grand_total'), 2) }}) &middot;
        Waiting: <strong>{{ $pending->count() }}</strong> (RM {{ number_format($pending->sum('grand_total'), 2) }}) &middot;
        Rejected: <strong>{{ $rejected->count() }}</strong> (RM {{ number_format($rejected->sum('grand_total'), 2) }}) &middot;
        Became a dish: <strong>{{ $entries->whereNotNull('recipe_id')->count() }}</strong>
    </div>

    <h3>Spent per menu</h3>
    <table>
        <thead>
            <tr>
                <th>Menu</th>
                <th class="right">Trials</th>
                <th class="right">Approved</th>
                <th class="right">Waiting</th>
                <th class="right">Total spent</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byMenu as $row)
                <tr>
                    <td>{{ $row['menu'] }}{{ $row['recipe'] ? ' (on the menu)' : '' }}</td>
                    <td class="right num">{{ $row['trials'] }}</td>
                    <td class="right num">RM {{ number_format($row['approved'], 2) }}</td>
                    <td class="right num">RM {{ number_format($row['pending'], 2) }}</td>
                    <td class="right num">RM {{ number_format($row['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>All menus</strong></td>
                <td class="right num"><strong>{{ $byMenu->sum('trials') }}</strong></td>
                <td class="right num"><strong>RM {{ number_format($byMenu->sum('approved'), 2) }}</strong></td>
                <td class="right num"><strong>RM {{ number_format($byMenu->sum('pending'), 2) }}</strong></td>
                <td class="right num"><strong>RM {{ number_format($byMenu->sum('total'), 2) }}</strong></td>
            </tr>
        </tbody>
    </table>
    <p class="subtitle">Grand totals &mdash; ingredients plus the miscellaneous overhead &mdash; and they include rejected trials, because the ingredients still left the shelf.</p>

    <h3>Every trial</h3>
    @foreach($entries as $entry)
        <p style="margin:14px 0 4px;">
            <strong>{{ $entry->menu_name ?: '—' }}</strong>
            &mdash; {{ $entry->serving_size }}pax
            &middot; {{ $entry->purchased_on->format('M d, Y') }}
            &middot; {{ ucfirst($entry->status) }}@if($entry->decided_at) by {{ $entry->decider?->name ?? 'a former team member' }}@endif
            @if($entry->recipe) &middot; on the menu as {{ $entry->recipe->name }} @endif
        </p>
        @if($entry->remark)<p class="subtitle" style="margin:0 0 4px;">{{ $entry->remark }}</p>@endif

        <table>
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th class="right">Price per unit</th>
                    <th>Unit</th>
                    <th class="right">Qty used</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entry->lines as $line)
                    <tr>
                        <td>{{ $line->item }}</td>
                        <td class="right num">RM {{ number_format($line->unit_price, 2) }}</td>
                        <td>{{ $line->unit ?: '—' }}</td>
                        <td class="right num">{{ $qty($line->quantity) }}</td>
                        <td class="right num">RM {{ number_format($line->total, 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="4" class="right">Total</td>
                    <td class="right num">RM {{ number_format($entry->total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="right">Miscellaneous {{ $qty($entry->misc_percent) }}%</td>
                    <td class="right num">RM {{ number_format($entry->misc_amount, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="right"><strong>Grand total</strong></td>
                    <td class="right num"><strong>RM {{ number_format($entry->grand_total, 2) }}</strong></td>
                </tr>
                @if($entry->selling_price !== null)
                    <tr>
                        <td colspan="4" class="right">Selling price</td>
                        <td class="right num">RM {{ number_format($entry->selling_price, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="4" class="right"><strong>Profit per dish</strong></td>
                        <td class="right num"><strong>RM {{ number_format($entry->profit, 2) }}</strong></td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endforeach
</x-pdf-base>
