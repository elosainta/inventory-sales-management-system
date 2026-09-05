<x-pdf-base title="Sales Report">
    <h2>Sales</h2>
    <p class="subtitle">Plates leaving the pass — revenue per recipe.</p>

    <div class="summary">
        Total sales: <strong>{{ $sales->count() }}</strong> &middot;
        Total revenue: <strong>RM {{ number_format($sales->sum('total_revenue'), 2) }}</strong> &middot;
        Plates sold: <strong>{{ $sales->sum('qty_sold') }}</strong> &middot;
        Discounts given: <strong>RM {{ number_format($sales->sum('discount'), 2) }}</strong> &middot;
        Open orders: <strong>{{ $sales->where('is_open_order', true)->count() }}</strong>
    </div>

    @php
        // Same rule as the screen: one section per day, most recent day first.
        $grouped = $sales->groupBy(fn ($s) => $s->sale_date->format('Y-m-d'))->sortKeysDesc();
    @endphp

    @foreach($grouped as $day => $group)
        <h3 style="margin:16px 0 6px; font-size:13px;">
            {{ \Illuminate\Support\Carbon::parse($day)->format('l, M d, Y') }} —
            {{ $group->sum('qty_sold') }} sold ·
            RM {{ number_format($group->sum('total_revenue'), 2) }}
        </h3>
        <table>
            <thead>
                <tr>
                    <th>Dish</th>
                    <th class="right">Qty</th>
                    <th class="right">Price</th>
                    <th class="right">Discount</th>
                    <th class="right">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group as $sale)
                    <tr>
                        <td>
                            {{ $sale->label }}
                            @if($sale->is_open_order)
                                <em style="font-size:9px;">(open order)</em>
                            @endif
                        </td>
                        <td class="right">{{ $sale->qty_sold }}</td>
                        <td class="right num">RM {{ number_format($sale->selling_price, 2) }}</td>
                        <td class="right num">{{ $sale->discount > 0 ? 'RM ' . number_format($sale->discount, 2) : '—' }}</td>
                        <td class="right num">RM {{ number_format($sale->total_revenue, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</x-pdf-base>
