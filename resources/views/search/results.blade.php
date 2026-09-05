<x-app-shell>
    {{-- Header --}}
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:28px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Search Results</h1>
            @if(strlen($q) >= 2)
                <p style="color:hsl(24,5%,45%); font-size:14px;">
                    {{ $total }} result{{ $total !== 1 ? 's' : '' }} for "<strong>{{ $q }}</strong>"
                </p>
            @else
                <p style="color:hsl(24,5%,45%); font-size:14px;">Enter at least 2 characters to search.</p>
            @endif
        </div>
    </div>

    {{-- Search bar --}}
    <form action="{{ route('search.index') }}" method="GET"
          style="display:flex; gap:8px; align-items:center; margin-bottom:32px; max-width:560px;">
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ auth()->user()?->isAdmin() ? 'Search support tickets, users…' : 'Search inventory, recipes, suppliers…' }}" autofocus
               style="flex:1; padding:9px 14px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; font-family:'DM Sans',sans-serif;">
        <button type="submit"
                style="background-color:hsl(20,60%,45%); color:white; padding:9px 20px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
            Search
        </button>
    </form>

    @if(strlen($q) >= 2 && $total === 0)
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:48px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">
            No results found for "<strong>{{ $q }}</strong>".
        </div>
    @elseif(strlen($q) >= 2)

    @php
        $cardStyle = 'background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden; margin-bottom:20px;';
        $rowStyle  = 'display:flex; justify-content:space-between; align-items:center; padding:12px 18px; border-bottom:1px solid hsl(30,15%,93%);';
        $lastRowStyle = 'display:flex; justify-content:space-between; align-items:center; padding:12px 18px;';
        $headStyle = 'display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);';
        $labelStyle = 'font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%);';
        $linkStyle  = 'font-size:13px; color:hsl(20,60%,45%); text-decoration:none; font-weight:500;';
    @endphp

    {{-- Inventory --}}
    @if($inventory->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Inventory &nbsp;({{ $inventory->count() }})</span>
            <a href="{{ route('inventory.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($inventory as $item)
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">{{ $item->name }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">
                    {{ $item->quantity_on_hand }} {{ $item->unit }} on hand &nbsp;·&nbsp; @money($item->monetary_value)
                </div>
            </div>
            <span style="background:hsl(30,15%,92%); font-size:11px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; letter-spacing:0.04em;">{{ $item->category }}</span>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Suppliers --}}
    @if($suppliers->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Suppliers &nbsp;({{ $suppliers->count() }})</span>
            <a href="{{ route('suppliers.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($suppliers as $supplier)
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div style="font-size:14px; font-weight:500;">{{ $supplier->name }}</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Recipes --}}
    @if($recipes->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Recipes &nbsp;({{ $recipes->count() }})</span>
            <a href="{{ route('recipes.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($recipes as $recipe)
        <a href="{{ route('recipes.show', $recipe) }}" style="text-decoration:none; color:inherit; display:block;">
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">{{ $recipe->name }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">RM {{ number_format($recipe->plate_cost, 2) }} plate cost</div>
            </div>
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="hsl(24,5%,55%)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </div>
        </a>
        @endforeach
    </div>
    @endif

    {{-- Purchases --}}
    @if($purchases->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Purchases &nbsp;({{ $purchases->count() }})</span>
            <a href="{{ route('purchases.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($purchases as $purchase)
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">{{ $purchase->supplier?->name ?? '—' }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">
                    {{ $purchase->purchase_date->format('M d, Y') }}
                    @if($purchase->invoice_number)
                        &nbsp;·&nbsp; <span style="font-family:'JetBrains Mono',monospace;">{{ $purchase->invoice_number }}</span>
                    @endif
                </div>
            </div>
            <div style="font-size:14px; font-weight:600;">@money($purchase->total_amount)</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Sales --}}
    @if($sales->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Sales &nbsp;({{ $sales->count() }})</span>
            <a href="{{ route('sales.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($sales as $sale)
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">{{ $sale->label }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">
                    {{ $sale->sale_date->format('M d, Y') }} &nbsp;·&nbsp; {{ $sale->qty_sold }} sold
                </div>
            </div>
            <div style="font-size:14px; font-weight:600; color:hsl(140,60%,30%);">@money($sale->total_revenue)</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Wastage --}}
    @if($wastage->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Wastage &nbsp;({{ $wastage->count() }})</span>
            <a href="{{ route('wastage.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($wastage as $entry)
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">{{ $entry->inventoryItem?->name ?? '—' }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">
                    {{ \Carbon\Carbon::parse($entry->recorded_date)->format('M d, Y') }}
                    &nbsp;·&nbsp; {{ ucfirst(str_replace('-', ' ', $entry->reason)) }}
                </div>
            </div>
            <div style="font-size:14px; font-weight:600; color:hsl(0,70%,50%);">@money($entry->cost_lost)</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- R&D --}}
    @if($rnd->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">R&D &nbsp;({{ $rnd->count() }})</span>
            <a href="{{ route('rnd.index') }}" style="{{ $linkStyle }}">View all &rarr;</a>
        </div>
        @foreach($rnd as $entry)
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">{{ $entry->menu_name ?: __('R&D trial') }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">
                    {{ $entry->lines->pluck('item')->join(', ') ?: __('no ingredients') }}
                    &nbsp;&middot;&nbsp; {{ $entry->purchased_on->format('M d, Y') }}
                    @if($entry->invoice_number) &nbsp;&middot;&nbsp; {{ $entry->invoice_number }} @endif
                    &nbsp;&middot;&nbsp; {{ ucfirst($entry->status) }}
                    &nbsp;&middot;&nbsp; {{ $entry->creator?->name ?? __('a former team member') }}
                </div>
            </div>
            <div style="font-size:14px; font-weight:600;">@money($entry->grand_total)</div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Events --}}
    @if($events->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Special Events &nbsp;({{ $events->count() }})</span>
            <a href="{{ route('events.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($events as $event)
        @php $profit = $event->revenue - $event->cost; @endphp
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">{{ $event->name }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">{{ $event->event_date->format('M d, Y') }}</div>
            </div>
            <div style="font-size:14px; font-weight:600; color:{{ $profit >= 0 ? 'hsl(140,60%,30%)' : 'hsl(0,70%,50%)' }};">
                @money($profit) profit
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Support Tickets --}}
    @if($supportTickets->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Support Tickets &nbsp;({{ $supportTickets->count() }})</span>
            <a href="{{ route('support-tickets.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($supportTickets as $ticket)
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">
                    <span style="font-family:'JetBrains Mono',monospace; color:hsl(20,60%,40%);">{{ $ticket->ticket_number ?? '—' }}</span>
                    &nbsp; {{ \Illuminate\Support\Str::limit($ticket->description, 60) }}
                </div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">
                    {{ $ticket->name }} &nbsp;·&nbsp; {{ $ticket->created_at->format('M d, Y') }}
                </div>
            </div>
            <span style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; padding:2px 8px; border-radius:4px;
                {{ $ticket->isResolved() ? 'color:hsl(142,40%,35%); background:hsl(142,50%,92%);' : 'color:hsl(20,60%,40%); background:hsl(20,60%,90%);' }}">
                {{ $ticket->isResolved() ? 'Resolved' : 'Open' }}
            </span>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Users --}}
    @if($users->isNotEmpty())
    <div style="{{ $cardStyle }}">
        <div style="{{ $headStyle }}">
            <span style="{{ $labelStyle }}">Users &nbsp;({{ $users->count() }})</span>
            <a href="{{ route('users.index') }}" style="{{ $linkStyle }}">View all →</a>
        </div>
        @foreach($users as $user)
        <div style="{{ $loop->last ? $lastRowStyle : $rowStyle }}">
            <div>
                <div style="font-size:14px; font-weight:500;">{{ $user->name }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:2px;">{{ $user->email }}</div>
            </div>
            <span style="background:hsl(30,15%,92%); font-size:11px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:capitalize;">{{ str_replace('_', ' ', $user->role) }}</span>
        </div>
        @endforeach
    </div>
    @endif

    @endif
</x-app-shell>
