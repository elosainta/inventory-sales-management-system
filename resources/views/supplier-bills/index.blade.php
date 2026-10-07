<x-app-shell title="Supplier Bills">
    @php
        $tabs = ['unpaid' => 'Unpaid', 'paid' => 'Paid', 'all' => 'All'];
        $pill = fn ($bill) => (float) $bill['balance'] <= 0
            ? ['Paid', '#15803d', 'hsl(145,45%,96%)']
            : ((float) $bill['balance'] < (float) $bill['amount']
                ? ['Part paid', '#b45309', 'hsl(40,60%,96%)']
                : ['Unpaid', '#b91c1c', 'hsl(0,55%,97%)']);
    @endphp

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; flex-wrap:wrap; margin-bottom:18px;">
        <div>
            <h1 style="font-size:24px; margin:0 0 4px;">Supplier Bills</h1>
            <p style="color:hsl(24,5%,45%); font-size:13px; margin:0;">
                Every bill on the books, by month. Read from Bukku, including bills entered there directly.
            </p>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            @foreach($tabs as $key => $label)
                <a href="{{ route('supplier-bills.index', ['status' => $key]) }}"
                   style="padding:7px 14px; border-radius:6px; font-size:14px; text-decoration:none; border:1px solid {{ $status === $key ? 'hsl(20,60%,42%)' : 'hsl(30,15%,85%)' }}; background:{{ $status === $key ? 'hsl(20,60%,42%)' : 'white' }}; color:{{ $status === $key ? 'white' : 'hsl(24,10%,30%)' }};">{{ $label }}</a>
            @endforeach
            @can('export-pdf')
                <a href="{{ route('supplier-bills.export-pdf', ['status' => $status]) }}"
                   style="padding:7px 14px; border-radius:6px; font-size:14px; text-decoration:none; border:1px solid hsl(30,15%,85%); background:white; color:hsl(24,10%,30%);">Download PDF</a>
            @endcan
        </div>
    </div>

    @unless($configured)
        <div style="background:hsl(0,55%,97%); border:1px solid hsl(0,55%,88%); border-radius:8px; padding:12px 16px; margin-bottom:18px; font-size:14px;">
            Bukku is not configured on this server, so there is nothing to read. The bills live in Bukku, not here.
        </div>
    @endunless

    {{-- The figures for whatever the filter is showing, so the tiles and the
         months below can never disagree about what is being counted. --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px, 1fr)); gap:12px; margin-bottom:22px;">
        <x-stat label="Bills">{{ number_format($totals['count']) }}</x-stat>
        <x-stat label="Billed">@money($totals['billed'])</x-stat>
        <x-stat label="Paid" ink="#15803d">@money($totals['paid'])</x-stat>
        <x-stat label="Still owed" ink="#b91c1c">@money($totals['outstanding'])</x-stat>
    </div>

    @if($byMonth->isEmpty())
        <p style="color:hsl(24,5%,45%); font-size:14px;">No {{ $status === 'all' ? '' : $status . ' ' }}bills to show.</p>
    @endif

    @foreach($byMonth as $month)
        <details style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:12px 16px; margin-bottom:10px;">
            {{-- display:flex on a <summary> drops the disclosure triangle in
                 Chrome, so the flex row is a span inside it. --}}
            <summary style="cursor:pointer; font-size:15px;">
                <span style="display:inline-flex; justify-content:space-between; gap:12px; width:calc(100% - 1.4em); vertical-align:top; flex-wrap:wrap;">
                    <span>
                        <strong>{{ $month['label'] }}</strong>
                        <span style="color:hsl(24,5%,45%); font-size:13px;">· {{ $month['count'] }} {{ Str::plural('bill', $month['count']) }} · {{ $month['suppliers']->count() }} {{ Str::plural('supplier', $month['suppliers']->count()) }}</span>
                    </span>
                    <span style="font-family:'JetBrains Mono',monospace; font-size:13px; white-space:nowrap;">
                        <span style="color:hsl(24,5%,45%);">billed</span> @money($month['billed'])
                        &nbsp; <span style="color:#15803d;">paid</span> @money($month['paid'])
                        &nbsp; <span style="color:#b91c1c;">owed</span> @money($month['outstanding'])
                    </span>
                </span>
            </summary>

            @foreach($month['suppliers'] as $supplier => $rows)
                <div style="border-top:1px solid hsl(30,15%,93%); padding:10px 0 10px 12px;">
                    <div style="display:flex; justify-content:space-between; gap:12px; font-weight:600; font-size:14px; flex-wrap:wrap;">
                        <span>{{ $supplier }} <span style="color:hsl(24,5%,45%); font-weight:400;">· {{ $rows['count'] }}</span></span>
                        <span style="font-family:'JetBrains Mono',monospace;">
                            @money($rows['billed'])
                            @if($rows['outstanding'] > 0)
                                <span style="color:#b91c1c; font-weight:400;">· @money($rows['outstanding']) owed</span>
                            @endif
                        </span>
                    </div>
                    @foreach($rows['bills'] as $bill)
                        @php [$label, $ink, $bg] = $pill($bill); @endphp
                        <div style="display:flex; justify-content:space-between; gap:12px; font-size:13px; color:hsl(24,10%,30%); padding:4px 0 0 12px; flex-wrap:wrap;">
                            <span>
                                @if(! empty($bill['short_link']))
                                    <a href="{{ $bill['short_link'] }}" target="_blank" rel="noopener" style="color:hsl(20,60%,45%); text-decoration:none; font-family:'JetBrains Mono',monospace;">{{ $bill['number'] }}</a>
                                @else
                                    <span style="font-family:'JetBrains Mono',monospace;">{{ $bill['number'] }}</span>
                                @endif
                                @if(! empty($bill['number2'])) · {{ $bill['number2'] }} @endif
                                · {{ \Illuminate\Support\Carbon::parse($bill['date'])->format('d M Y') }}
                                <span style="background:{{ $bg }}; color:{{ $ink }}; border-radius:10px; padding:1px 8px; font-size:11px;">{{ $label }}</span>
                            </span>
                            <span style="font-family:'JetBrains Mono',monospace;">
                                @money($bill['amount'])
                                @if((float) $bill['balance'] > 0)
                                    <span style="color:#b91c1c;">· @money($bill['balance']) owed</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </details>
    @endforeach

    <p style="color:hsl(24,5%,45%); font-size:12px; margin-top:18px;">
        Payments are recorded in Bukku and this page follows within five minutes. Bukku's bill list carries no payment date, so a month's "paid" is what has been settled against bills <em>dated</em> in that month, not money that left the bank that month.
    </p>
</x-app-shell>
