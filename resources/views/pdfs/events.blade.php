<x-pdf-base title="Special Events Report">
    <h2>Special Events</h2>
    <p class="subtitle">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</p>

    <div class="summary">
        Total events: <strong>{{ $events->count() }}</strong> &middot;
        Revenue: <strong>RM {{ number_format($events->sum('revenue'), 2) }}</strong> &middot;
        Cost: <strong>RM {{ number_format($events->sum('cost'), 2) }}</strong> &middot;
        Net Profit: <strong>RM {{ number_format($events->sum('revenue') - $events->sum('cost'), 2) }}</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Event Name</th>
                <th>Menu</th>
                <th class="right">Revenue</th>
                <th class="right">Cost</th>
                <th class="right">Profit</th>
            </tr>
        </thead>
        <tbody>
            @foreach($events as $event)
                <tr>
                    <td>{{ $event->event_date->format('M d, Y') }}</td>
                    <td>{{ $event->name }}</td>
                    <td>{{ $event->menu ?? '—' }}</td>
                    <td class="right num">RM {{ number_format($event->revenue, 2) }}</td>
                    <td class="right num">RM {{ number_format($event->cost, 2) }}</td>
                    <td class="right num">RM {{ number_format($event->revenue - $event->cost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-pdf-base>
