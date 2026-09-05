<x-pdf-base title="Wastage Report">
    <h2>Wastage</h2>
    <p class="subtitle">The cost of what doesn't make it to the plate.</p>

    <div class="summary">
        Total entries: <strong>{{ $entries->count() }}</strong> &middot;
        Total cost lost: <strong>RM {{ number_format($entries->sum('cost_lost'), 2) }}</strong>
    </div>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Ingredient</th>
                <th class="right">Quantity</th>
                <th>Reason</th>
                <th class="right">Cost Lost</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entries as $entry)
                <tr>
                    <td>{{ $entry->recorded_date->format('M d, Y') }}</td>
                    <td>{{ $entry->inventoryItem->name }}</td>
                    <td class="right num">{{ $entry->quantity_wasted }} {{ $entry->inventoryItem->unit }}</td>
                    <td>{{ ucfirst($entry->reason) }}</td>
                    <td class="right num">RM {{ number_format($entry->cost_lost, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-pdf-base>