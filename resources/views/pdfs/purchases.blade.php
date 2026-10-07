@php
    // One sheet is the requirement, so the type follows the row count rather
    // than the report quietly spilling onto a second page the next time a
    // busy month lands. The thresholds are measured, not guessed — see
    // PurchasesPdfTest, which renders at each of them and counts the pages.
    $rows = $months->count() + $months->sum(fn ($month) => $month['suppliers']->count());
    // Measured on A4 with real supplier names, which wrap and are what
    // actually sets the row height: 8pt holds 34 rows, 7pt 38, 6.5pt 40.
    // Below that the gain is a row or two per point and the type stops being
    // readable, so past ~40 rows the report spills rather than shrink into
    // something nobody can use.
    $size = match (true) {
        $rows > 38 => 6.5,
        $rows > 34 => 7.0,
        default    => 8.0,
    };
@endphp
<x-pdf-base title="Purchases Report">
    <style>
        /* The whole report is one sheet, so the rows are tighter than the
           house table and the months are header rows inside a single table
           rather than a section each. A separate month-by-month summary would
           only repeat these same figures. */
        table.compact td { padding: 2px 5px; font-size: {{ $size }}pt; }
        table.compact th { padding: 3px 5px; font-size: {{ $size - 0.5 }}pt; }
        tr.month td { background: #f5f5f4; font-weight: bold; padding-top: 4px; }
        h2 { margin-bottom: 2px; }
        .subtitle { margin-bottom: 8px; font-size: 9pt; }
        .summary { padding: 6px 10px; margin-bottom: 10px; font-size: 9pt; }
        .pending { color: #b45309; }
        .paid { color: #15803d; }
    </style>

    <h2>Purchases</h2>
    <p class="subtitle">What was spent with each supplier, and what is still owed, a month at a time.</p>

    <div class="summary">
        Purchases: <strong>{{ number_format($summary['count']) }}</strong> &middot;
        Spent: <strong>RM {{ number_format($summary['total'], 2) }}</strong> &middot;
        Paid: <strong>RM {{ number_format($summary['paid'], 2) }}</strong> &middot;
        Unpaid: <strong>RM {{ number_format($summary['pending'], 2) }}</strong>
    </div>

    <table class="compact">
        <thead>
            <tr>
                <th>Supplier</th>
                <th class="right">Purchases</th>
                <th class="right">Spent</th>
                <th class="right">Paid</th>
                <th class="right">Owed</th>
            </tr>
        </thead>
        <tbody>
            @forelse($months as $month)
                <tr class="month">
                    <td>{{ $month['label'] }}</td>
                    <td class="right num">{{ $month['count'] }}</td>
                    <td class="right num">RM {{ number_format($month['total'], 2) }}</td>
                    <td class="right num paid">RM {{ number_format($month['paid'], 2) }}</td>
                    <td class="right num {{ $month['pending'] > 0 ? 'pending' : 'muted' }}">
                        {{ $month['pending'] > 0 ? 'RM ' . number_format($month['pending'], 2) : '—' }}
                    </td>
                </tr>
                @foreach($month['suppliers'] as $supplier => $row)
                    <tr>
                        <td>&nbsp;&nbsp;&nbsp;{{ $supplier }}</td>
                        <td class="right num">{{ $row['count'] }}</td>
                        <td class="right num">RM {{ number_format($row['total'], 2) }}</td>
                        <td class="right num paid">RM {{ number_format($row['paid'], 2) }}</td>
                        <td class="right num {{ $row['pending'] > 0 ? 'pending' : 'muted' }}">
                            {{ $row['pending'] > 0 ? 'RM ' . number_format($row['pending'], 2) : '—' }}
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="5" class="muted">No purchases recorded.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td class="right" style="border-top:1.5px solid #57534e; font-weight:bold;">TOTAL</td>
                <td class="right num" style="border-top:1.5px solid #57534e; font-weight:bold;">{{ $summary['count'] }}</td>
                <td class="right num" style="border-top:1.5px solid #57534e; font-weight:bold;">RM {{ number_format($summary['total'], 2) }}</td>
                <td class="right num paid" style="border-top:1.5px solid #57534e; font-weight:bold;">RM {{ number_format($summary['paid'], 2) }}</td>
                <td class="right num pending" style="border-top:1.5px solid #57534e; font-weight:bold;">RM {{ number_format($summary['pending'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <p class="subtitle" style="margin-top:10px;">
        Paid and owed here are this kitchen's own Completed and Pending status on a purchase, set from the Purchase log. For what the supplier's bill still has owing on it in Bukku, see the Supplier Bills report.
    </p>
</x-pdf-base>
