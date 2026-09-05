<x-pdf-base title="Inventory Report">
    <h2>Inventory</h2>
    <p class="subtitle">Every ingredient on the line, grouped by the supplier it was last bought from — one supplier per page.</p>

    <div class="summary">
        Total items: <strong>{{ $suppliers->sum(fn ($g) => $g['items']->count()) }}</strong> &middot;
        Total stock value: <strong>RM {{ number_format($totalValue, 2) }}</strong> &middot;
        Low-stock items: <strong>{{ $suppliers->sum(fn ($g) => $g['items']->filter->isLowStock()->count()) }}</strong>
    </div>

    @foreach($suppliers as $i => $group)
        <div @if($i > 0) style="page-break-before: always;" @endif>
            <h3 style="margin:0 0 12px 0; font-size:13pt; color:hsl(20,60%,45%);">
                {{ $group['supplier'] ?? 'No supplier' }}
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
                    @foreach($group['items'] as $item)
                        <tr>
                            <td>
                                {{ $item->name }}
                                @if($item->isLowStock())
                                    <span class="muted">(low)</span>
                                @endif
                            </td>
                            <td>{{ $item->unit }}</td>
                            <td class="right num">RM {{ number_format($item->unit_cost, 2) }}</td>
                            <td class="right num">{{ rtrim(rtrim(number_format($item->quantity_on_hand, 2), '0'), '.') }}</td>
                            <td class="right num">RM {{ number_format($item->monetary_value, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="right" style="border-top:1.5px solid #57534e; font-weight:bold;">TOTAL</td>
                        <td class="right num" style="border-top:1.5px solid #57534e; font-weight:bold;">RM {{ number_format($group['items']->sum('monetary_value'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endforeach
</x-pdf-base>
