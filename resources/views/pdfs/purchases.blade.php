<x-pdf-base title="Purchases Report">
    <h2>Purchases</h2>
    <p class="subtitle">Every ingredient bought, grouped by supplier — one supplier per page.</p>

    @forelse($suppliers as $i => $group)
        <div @if($i > 0) style="page-break-before: always;" @endif>
            <h3 style="margin:0 0 12px 0; font-size:13pt; color:hsl(20,60%,45%);">
                {{ $group['supplier']->name ?? 'No supplier' }}
            </h3>

            <table>
                <thead>
                    <tr>
                        <th>Ingredients</th>
                        <th>UOM</th>
                        <th class="right">Price</th>
                        <th class="right">Quantity</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($group['lines'] as $line)
                        <tr>
                            <td>{{ $line->inventoryItem->name ?? '—' }}</td>
                            <td>{{ $line->inventoryItem->unit ?? '' }}</td>
                            <td class="right num">RM {{ number_format($line->unit_price, 2) }}</td>
                            <td class="right num">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                            <td class="right num">RM {{ number_format($line->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="right" style="border-top:1.5px solid #57534e; font-weight:bold;">TOTAL</td>
                        <td class="right num" style="border-top:1.5px solid #57534e; font-weight:bold;">RM {{ number_format($group['lines']->sum('line_total'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @empty
        <p class="muted">No purchases recorded.</p>
    @endforelse
</x-pdf-base>
