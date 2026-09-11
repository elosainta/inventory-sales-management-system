<x-pdf-base title="Staff Meal Report">
    <h2>Staff's Meal</h2>
    <p class="subtitle">What the kitchen cooked for its own team, and what it cost.</p>

    @php
        $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
    @endphp

    <div class="summary">
        Meals: <strong>{{ $meals->count() }}</strong> &middot;
        Total spent: <strong>RM {{ number_format($byMonth->sum('total'), 2) }}</strong>
    </div>

    <h3>Spent per month</h3>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="right">Meals</th>
                <th class="right">Total spent</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byMonth as $row)
                <tr>
                    <td>{{ $row['month'] }}</td>
                    <td class="right num">{{ $row['meals'] }}</td>
                    <td class="right num">RM {{ number_format($row['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>All months</strong></td>
                <td class="right num"><strong>{{ $byMonth->sum('meals') }}</strong></td>
                <td class="right num"><strong>RM {{ number_format($byMonth->sum('total'), 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <h3>Every meal</h3>
    @foreach($meals as $meal)
        <p style="margin:14px 0 4px;">
            <strong>{{ $meal->dish }}</strong>
            &mdash; {{ $meal->meal_date->format('M d, Y') }}
            &middot; by {{ $meal->creator?->name ?? 'a former team member' }}
        </p>
        @if($meal->remark)<p class="subtitle" style="margin:0 0 4px;">{{ $meal->remark }}</p>@endif

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
                @foreach($meal->lines as $line)
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
                    <td class="right num">RM {{ number_format($meal->total, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="right">Miscellaneous {{ $qty($meal->misc_percent) }}%</td>
                    <td class="right num">RM {{ number_format($meal->misc_amount, 2) }}</td>
                </tr>
                <tr>
                    <td colspan="4" class="right"><strong>Grand total</strong></td>
                    <td class="right num"><strong>RM {{ number_format($meal->grand_total, 2) }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endforeach
</x-pdf-base>
