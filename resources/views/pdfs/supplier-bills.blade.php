<x-pdf-base title="Supplier Bills">
    <style>
        /* A month is a sheet you can hand to someone, so each one starts a
           new page. The rows are tighter than the house table only here:
           at the standard 10pt with 8px padding a page holds about 28 rows,
           and a busy month runs to forty-odd once the supplier subtotals are
           counted, which would split it across two sheets. */
        .month { page-break-before: always; }
        .month table td { padding: 3px 6px; font-size: 8.5pt; }
        .month table th { padding: 4px 6px; font-size: 8pt; }
        .month h3 { margin: 0 0 6px 0; font-size: 13pt; }
        .month .totals { color: #78716c; font-size: 9pt; margin: 0 0 10px 0; }
    </style>

    <h2>Supplier Bills</h2>
    <p class="subtitle">
        {{ ['unpaid' => 'Bills with money still owed on them', 'paid' => 'Bills that have been settled in full', 'all' => 'Every bill on the books'][$status] }},
        by month, oldest first. Read from Bukku.
    </p>

    <div class="summary">
        Bills: <strong>{{ number_format($totals['count']) }}</strong> &middot;
        Billed: <strong>RM {{ number_format($totals['billed'], 2) }}</strong> &middot;
        Paid: <strong>RM {{ number_format($totals['paid'], 2) }}</strong> &middot;
        Still owed: <strong>RM {{ number_format($totals['outstanding'], 2) }}</strong>
    </div>

    <h3>Month by month</h3>
    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="right">Bills</th>
                <th class="right">Suppliers</th>
                <th class="right">Billed</th>
                <th class="right">Paid</th>
                <th class="right">Still owed</th>
            </tr>
        </thead>
        <tbody>
            @foreach($byMonth as $month)
                <tr>
                    <td>{{ $month['label'] }}</td>
                    <td class="right num">{{ $month['count'] }}</td>
                    <td class="right num">{{ $month['suppliers']->count() }}</td>
                    <td class="right num">RM {{ number_format($month['billed'], 2) }}</td>
                    <td class="right num">RM {{ number_format($month['paid'], 2) }}</td>
                    <td class="right num">RM {{ number_format($month['outstanding'], 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>Total</strong></td>
                <td class="right num"><strong>{{ $totals['count'] }}</strong></td>
                <td class="right num"></td>
                <td class="right num"><strong>RM {{ number_format($totals['billed'], 2) }}</strong></td>
                <td class="right num"><strong>RM {{ number_format($totals['paid'], 2) }}</strong></td>
                <td class="right num"><strong>RM {{ number_format($totals['outstanding'], 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    @foreach($byMonth as $month)
        <div class="month">
        <h3>{{ $month['label'] }}</h3>
        <p class="totals">
            {{ $month['count'] }} {{ Str::plural('bill', $month['count']) }} from {{ $month['suppliers']->count() }} {{ Str::plural('supplier', $month['suppliers']->count()) }} &middot;
            Billed RM {{ number_format($month['billed'], 2) }} &middot;
            Paid RM {{ number_format($month['paid'], 2) }} &middot;
            Still owed RM {{ number_format($month['outstanding'], 2) }}
        </p>
        <table>
            <thead>
                <tr>
                    <th>Supplier / bill</th>
                    <th>Invoice</th>
                    <th>Date</th>
                    <th class="right">Amount</th>
                    <th class="right">Owed</th>
                </tr>
            </thead>
            <tbody>
                @foreach($month['suppliers'] as $supplier => $rows)
                    <tr>
                        <td colspan="3"><strong>{{ $supplier }}</strong> ({{ $rows['count'] }})</td>
                        <td class="right num"><strong>RM {{ number_format($rows['billed'], 2) }}</strong></td>
                        <td class="right num"><strong>RM {{ number_format($rows['outstanding'], 2) }}</strong></td>
                    </tr>
                    @foreach($rows['bills'] as $bill)
                        <tr>
                            <td>&nbsp;&nbsp;&nbsp;&nbsp;{{ $bill['number'] }}</td>
                            <td>{{ $bill['number2'] ?: '—' }}</td>
                            <td>{{ \Illuminate\Support\Carbon::parse($bill['date'])->format('d M Y') }}</td>
                            <td class="right num">RM {{ number_format((float) $bill['amount'], 2) }}</td>
                            <td class="right num">{{ (float) $bill['balance'] > 0 ? 'RM ' . number_format((float) $bill['balance'], 2) : 'Paid' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
        </div>
    @endforeach

    <p class="subtitle">
        Bukku's bill list carries no payment date, so a month's "paid" is what has been settled against bills dated in that month, not money that left the bank that month.
    </p>
</x-pdf-base>
