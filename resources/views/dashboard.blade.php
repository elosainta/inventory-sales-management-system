<x-app-shell>
    @php $monthLabel = \Carbon\Carbon::parse($month . '-01')->format('F Y'); @endphp

    {{-- Header + Month Picker --}}
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">
                {{ auth()->user()->role === 'owner' ? 'Overview' : 'Dashboard' }}
            </h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Showing data for <strong>{{ $monthLabel }}</strong></p>
        </div>
        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; justify-content:flex-start;">
            <form method="GET" action="{{ route('dashboard') }}" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <input type="month" name="month" value="{{ $month }}"
                       style="padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px;">
                <button type="submit"
                        style="background-color:hsl(20,60%,45%); color:white; padding:7px 16px; border-radius:6px; font-size:13px; font-weight:500; border:none; cursor:pointer;">
                    Go
                </button>
            </form>
            @if(auth()->user()->role === 'owner')
            <button onclick="toggleEditMode()" class="edit-toggle-btn" style="background:none; border:1px solid hsl(30,15%,85%); cursor:pointer; color:hsl(24,5%,55%); padding:7px 10px; border-radius:6px; display:flex; align-items:center;" title="Toggle edit mode">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            @endif
        </div>
    </div>

    {{-- KPI Cards --}}
    <div style="margin-bottom:32px;">
        <div class="app-kpi-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px;">
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Inventory Value</div>
                <div class="app-kpi" data-value="{{ $inventoryValue }}" data-prefix="RM " data-decimals="2" style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">RM {{ number_format($inventoryValue, 2) }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">On-hand stock at cost</div>
            </div>
            @unless($hideSales)
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Sales</div>
                <div class="app-kpi" data-value="{{ $salesThisMonth }}" data-prefix="RM " data-decimals="2" style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">RM {{ number_format($salesThisMonth, 2) }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Gross revenue logged</div>
            </div>
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Gross Margin</div>
                <div class="app-kpi" data-value="{{ $grossMargin }}" data-suffix="%" data-decimals="1" style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">{{ $grossMargin }}%</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Sales minus cost of goods sold</div>
            </div>
            @endunless
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Petty Cash Balance</div>
                <div class="app-kpi" data-value="{{ $pettyCashBalance }}" data-prefix="RM " data-decimals="2" style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">RM {{ number_format($pettyCashBalance, 2) }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Net float outstanding</div>
            </div>
        </div>
        <div class="app-kpi-grid" style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px;">
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Purchase Spend</div>
                <div class="app-kpi" data-value="{{ $purchaseSpend }}" data-prefix="RM " data-decimals="2" style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">RM {{ number_format($purchaseSpend, 2) }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Supplier orders this month</div>
            </div>
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Wastage Cost</div>
                <div class="app-kpi" data-value="{{ $wasteCost }}" data-prefix="RM " data-decimals="2" style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif; color:hsl(0,70%,50%);">RM {{ number_format($wasteCost, 2) }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Cost of binned product</div>
            </div>
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Wastage Rate</div>
                <div class="app-kpi" data-value="{{ $wastageRate }}" data-suffix="%" data-decimals="1" style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif; color:hsl(0,70%,50%);">{{ $wastageRate }}%</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">Wastage / purchase spend</div>
            </div>
            <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
                <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Low Stock Items</div>
                <div class="app-kpi" data-value="{{ $lowStockCount }}" data-decimals="0" style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif; color:{{ $lowStockCount > 0 ? 'hsl(0,70%,50%)' : 'inherit' }};">{{ $lowStockCount }}</div>
                <div style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">At or below reorder line</div>
            </div>
        </div>
    </div>

    {{-- Charts Row 1 --}}
    <div class="app-chart-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
        @unless($hideSales)
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:4px;">Sales vs. Cost of Goods</h3>
            <p style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:16px;">{{ $monthLabel }}, day by day</p>
            <canvas id="salesCogsChart" height="120"></canvas>
        </div>
        @endunless
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:4px;">Wastage by Reason</h3>
            <p style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:16px;">Where the leakage comes from</p>
            <canvas id="wastageReasonChart" height="120"></canvas>
        </div>
    </div>

    {{-- Charts Row 2 --}}
    <div class="app-chart-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:4px;">Inventory by Category</h3>
            <p style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:16px;">Where your capital is sitting</p>
            <canvas id="inventoryCategoryChart" height="120"></canvas>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:4px;">Wastage Trend</h3>
            <p style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:16px;">Daily cost lost — {{ $monthLabel }}</p>
            <canvas id="wastageTrendChart" height="120"></canvas>
        </div>
    </div>

    {{-- Bottom Row --}}
    <div class="app-bottom-grid" style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px;">
        {{-- Low Stock --}}
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:16px;">Low Stock</h3>
            @forelse($lowStockItems as $item)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid hsl(30,15%,93%);">
                    <div>
                        <div style="font-size:14px; font-weight:500;">{{ $item->name }}</div>
                        <div style="font-size:12px; color:hsl(24,5%,45%);">{{ $item->quantity_on_hand }} {{ $item->unit }} on hand</div>
                    </div>
                    <span style="background:#fee2e2; color:#991b1b; font-size:11px; font-weight:600; padding:2px 8px; border-radius:999px;">Low</span>
                </div>
            @empty
                <p style="font-size:14px; color:hsl(24,5%,45%);">All items well stocked.</p>
            @endforelse
        </div>

        {{-- Top Wasted --}}
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:16px;">Top Wasted</h3>
            @forelse($topWasted as $entry)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid hsl(30,15%,93%);">
                    <div style="font-size:14px; font-weight:500;">{{ $entry->inventoryItem->name }}</div>
                    <div style="font-size:14px; font-weight:600; color:hsl(0,70%,50%);">RM {{ number_format($entry->total, 2) }}</div>
                </div>
            @empty
                <p style="font-size:14px; color:hsl(24,5%,45%);">No wastage recorded.</p>
            @endforelse
        </div>

        {{-- Spend by Supplier --}}
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
            <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:16px;">Spend by Supplier</h3>
            @forelse($spendBySupplier as $entry)
                <div style="display:flex; justify-content:space-between; align-items:center; padding:8px 0; border-bottom:1px solid hsl(30,15%,93%);">
                    <div style="font-size:14px; font-weight:500;">{{ $entry->supplier->name }}</div>
                    <div style="font-size:14px; font-weight:600;">RM {{ number_format($entry->total, 2) }}</div>
                </div>
            @empty
                <p style="font-size:14px; color:hsl(24,5%,45%);">No purchases recorded.</p>
            @endforelse
        </div>
    </div>

    {{-- ── Owner sections ──────────────────────────────────────────── --}}
    @if($ownerData)
    <div class="app-owner-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:24px;">
    @php $sectionCard = 'background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;'; @endphp
    @php $sectionHead = 'padding:20px 24px; border-bottom:1px solid hsl(30,15%,90%); display:flex; justify-content:space-between; align-items:center;'; @endphp
    @php $viewLink = 'font-size:13px; color:hsl(20,60%,45%); text-decoration:none; font-weight:500;'; @endphp
    @php $addBtn = 'edit-only'; @endphp
    @php $addBtnStyle = 'display:none; background-color:hsl(20,60%,45%); color:white; padding:6px 14px; border-radius:6px; font-size:13px; font-weight:500; border:none; cursor:pointer;'; @endphp
    @php $delBtn = 'background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;'; @endphp
    @php $trashIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>'; @endphp
    @php $editIcon = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'; @endphp

    {{-- ── Inventory ──────────────────────── --}}
    <div style="{{ $sectionCard }}">
        <div style="{{ $sectionHead }}">
            <div>
                <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:2px;">Inventory</h3>
                <p style="font-size:12px; color:hsl(24,5%,45%); margin:0;">All {{ $ownerData['inventory']->count() }} ingredients at current stock levels</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="{{ $addBtn }}" onclick="document.getElementById('ov-inv-modal').style.display='flex'"
                        style="{{ $addBtnStyle }}">+ Add Ingredient</button>
            </div>
        </div>
        @if($ownerData['inventory']->isEmpty())
            <div style="padding:40px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">No ingredients yet.</div>
        @else
        {{-- The full list scrolls inside the card so it can't stretch the page. --}}
        @php $stickyTh = 'text-align:left; padding:11px 16px; font-weight:600; position:sticky; top:0; background:hsl(30,15%,97%); box-shadow:inset 0 -1px 0 hsl(30,15%,90%);'; @endphp
        <div style="overflow-x:auto; overflow-y:auto; max-height:420px;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead><tr>
                <th style="{{ $stickyTh }}">Name</th>
                <th class="edit-only" style="display:none; {{ $stickyTh }}">Category</th>
                <th style="{{ $stickyTh }}">On Hand</th>
                <th class="edit-only" style="display:none; {{ $stickyTh }}">Unit Cost</th>
                <th style="{{ $stickyTh }}">Value</th>
                <th class="edit-only" style="display:none; {{ $stickyTh }}"></th>
            </tr></thead>
            <tbody>
            @foreach($ownerData['inventory'] as $item)
            <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                <td style="padding:11px 16px; font-weight:500;">
                    {{ $item->name }}
                    @if($item->quantity_on_hand <= $item->reorder_threshold)
                        <span style="margin-left:6px; background:#fee2e2; color:#991b1b; font-size:11px; font-weight:600; padding:2px 7px; border-radius:999px;">Low</span>
                    @endif
                </td>
                <td class="edit-only" style="display:none; padding:11px 16px;">
                    <span style="background:hsl(30,15%,92%); font-size:11px; font-weight:600; padding:2px 8px; border-radius:4px; text-transform:uppercase; letter-spacing:0.05em;">{{ $item->category }}</span>
                </td>
                <td style="padding:11px 16px;">{{ $item->quantity_on_hand }} {{ $item->unit }}</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">@money($item->unit_cost)</td>
                <td style="padding:11px 16px; font-weight:500;">@money($item->monetary_value)</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">
                    <form action="{{ route('inventory.destroy', $item) }}" method="POST" onsubmit="return confirm('Remove {{ addslashes($item->name) }}?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="{{ $delBtn }}" title="Remove">{!! $trashIcon !!}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    @unless($hideSales)
    {{-- ── Sales ──────────────────────────── --}}
    <div style="{{ $sectionCard }}">
        <div style="{{ $sectionHead }}">
            <div>
                <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:2px;">Sales</h3>
                <p style="font-size:12px; color:hsl(24,5%,45%); margin:0;">Last 7 sales logged — revenue per recipe</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="{{ $addBtn }}" onclick="document.getElementById('ov-sale-modal').style.display='flex'"
                        style="{{ $addBtnStyle }}">+ Log Sale</button>
            </div>
        </div>
        @if($ownerData['sales']->isEmpty())
            <div style="padding:40px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">No sales logged yet.</div>
        @else
        <div style="overflow-x:auto;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead><tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Date</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Recipe</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;">Qty</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Revenue</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;"></th>
            </tr></thead>
            <tbody>
            @foreach($ownerData['sales'] as $sale)
            <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                <td style="padding:11px 16px;">{{ $sale->sale_date->format('M d, Y') }}</td>
                <td style="padding:11px 16px; font-weight:500;">{{ $sale->label }}</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">{{ $sale->qty_sold }}</td>
                <td style="padding:11px 16px; font-weight:500; color:hsl(140,60%,30%);">@money($sale->total_revenue)</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">
                    <form action="{{ route('sales.destroy', $sale) }}" method="POST" onsubmit="return confirm('Remove this sale?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="{{ $delBtn }}" title="Remove">{!! $trashIcon !!}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
    @endunless

    {{-- ── Purchases ───────────────────────── --}}
    <div style="{{ $sectionCard }}">
        <div style="{{ $sectionHead }}">
            <div>
                <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:2px;">Purchases</h3>
                <p style="font-size:12px; color:hsl(24,5%,45%); margin:0;">Last 7 supplier orders</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <a class="{{ $addBtn }}" href="{{ route('purchases.index') }}"
                   style="display:none; background-color:hsl(20,60%,45%); color:white; padding:6px 14px; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none;">+ New Purchase</a>
            </div>
        </div>
        @if($ownerData['purchases']->isEmpty())
            <div style="padding:40px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">No purchases yet.</div>
        @else
        <div style="overflow-x:auto;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead><tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Date</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Supplier</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;">Invoice</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Total</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;"></th>
            </tr></thead>
            <tbody>
            @foreach($ownerData['purchases'] as $purchase)
            <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                <td style="padding:11px 16px;">{{ $purchase->purchase_date->format('M d, Y') }}</td>
                <td style="padding:11px 16px; font-weight:500;">{{ $purchase->supplier?->name ?? '—' }}</td>
                <td class="edit-only" style="display:none; padding:11px 16px; color:hsl(24,5%,45%);">{{ $purchase->invoice_number ?: '—' }}</td>
                <td style="padding:11px 16px; font-weight:500;">@money($purchase->total_amount)</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">
                    <form action="{{ route('purchases.destroy', $purchase) }}" method="POST" onsubmit="return confirm('Remove this purchase?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="{{ $delBtn }}" title="Remove">{!! $trashIcon !!}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    {{-- ── Wastage ─────────────────────────── --}}
    <div style="{{ $sectionCard }}">
        <div style="{{ $sectionHead }}">
            <div>
                <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:2px;">Wastage</h3>
                <p style="font-size:12px; color:hsl(24,5%,45%); margin:0;">Last 7 wastage entries</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="{{ $addBtn }}" onclick="document.getElementById('ov-wastage-modal').style.display='flex'"
                        style="{{ $addBtnStyle }}">+ Log Wastage</button>
            </div>
        </div>
        @if($ownerData['wastage']->isEmpty())
            <div style="padding:40px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">No wastage recorded.</div>
        @else
        <div style="overflow-x:auto;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead><tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Date</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Ingredient</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;">Qty</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;">Reason</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Cost Lost</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;"></th>
            </tr></thead>
            <tbody>
            @foreach($ownerData['wastage'] as $entry)
            <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                <td style="padding:11px 16px;">{{ \Carbon\Carbon::parse($entry->recorded_date)->format('M d, Y') }}</td>
                <td style="padding:11px 16px; font-weight:500;">{{ $entry->inventoryItem?->name ?? '—' }}</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">{{ $entry->quantity_wasted }} {{ $entry->inventoryItem?->unit }}</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">
                    <span style="background:hsl(30,15%,92%); font-size:12px; font-weight:500; padding:2px 8px; border-radius:4px; text-transform:capitalize;">{{ str_replace('-', ' ', $entry->reason) }}</span>
                </td>
                <td style="padding:11px 16px; font-weight:500; color:hsl(0,70%,50%);">@money($entry->cost_lost)</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">
                    <form action="{{ route('wastage.destroy', $entry) }}" method="POST" onsubmit="return confirm('Remove this entry?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="{{ $delBtn }}" title="Remove">{!! $trashIcon !!}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    {{-- ── Special Events ──────────────────── --}}
    <div style="{{ $sectionCard }}">
        <div style="{{ $sectionHead }}">
            <div>
                <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:2px;">Special Events</h3>
                <p style="font-size:12px; color:hsl(24,5%,45%); margin:0;">Last 7 private dinners and banquets</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="{{ $addBtn }}" onclick="document.getElementById('ov-event-modal').style.display='flex'"
                        style="{{ $addBtnStyle }}">+ Add Event</button>
            </div>
        </div>
        @if($ownerData['events']->isEmpty())
            <div style="padding:40px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">No events recorded.</div>
        @else
        <div style="overflow-x:auto;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead><tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Date</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Event</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;">Revenue</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;">Cost</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Profit</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;"></th>
            </tr></thead>
            <tbody>
            @foreach($ownerData['events'] as $event)
            @php $profit = $event->revenue - $event->cost; @endphp
            <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                <td style="padding:11px 16px;">{{ $event->event_date->format('M d, Y') }}</td>
                <td style="padding:11px 16px; font-weight:500;">{{ $event->name }}</td>
                <td class="edit-only" style="display:none; padding:11px 16px; color:hsl(140,60%,30%);">@money($event->revenue)</td>
                <td class="edit-only" style="display:none; padding:11px 16px; color:hsl(0,70%,50%);">@money($event->cost)</td>
                <td style="padding:11px 16px; font-weight:600; color:{{ $profit >= 0 ? 'hsl(140,60%,30%)' : 'hsl(0,70%,50%)' }};">@money($profit)</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">
                    <form action="{{ route('events.destroy', $event) }}" method="POST" onsubmit="return confirm('Remove {{ addslashes($event->name) }}?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="{{ $delBtn }}" title="Remove">{!! $trashIcon !!}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    {{-- ── Petty Cash ──────────────────────── --}}
    <div style="{{ $sectionCard }}">
        <div style="{{ $sectionHead }}">
            <div>
                <h3 style="font-family:'DM Sans',sans-serif; font-size:17px; font-weight:500; margin-bottom:2px;">Petty Cash</h3>
                <p style="font-size:12px; color:hsl(24,5%,45%); margin:0;">Last 7 float issuances</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <button class="{{ $addBtn }}" onclick="document.getElementById('ov-float-modal').style.display='flex'"
                        style="{{ $addBtnStyle }}">+ Issue Float</button>
            </div>
        </div>
        @if($ownerData['float']->isEmpty())
            <div style="padding:40px; text-align:center; color:hsl(24,5%,45%); font-size:14px;">No float issued yet.</div>
        @else
        <div style="overflow-x:auto;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead><tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Date</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Given</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;">Spent</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;">Returned</th>
                <th style="text-align:left; padding:11px 16px; font-weight:600;">Status</th>
                <th class="edit-only" style="display:none; text-align:left; padding:11px 16px; font-weight:600;"></th>
            </tr></thead>
            <tbody>
            @foreach($ownerData['float'] as $issuance)
            <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                <td style="padding:11px 16px;">{{ \Carbon\Carbon::parse($issuance->issued_date)->format('M d, Y') }}</td>
                <td style="padding:11px 16px;">@money($issuance->amount_given)</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">@money($issuance->amount_spent)</td>
                <td class="edit-only" style="display:none; padding:11px 16px;">@money($issuance->amount_returned)</td>
                <td style="padding:11px 16px;">
                    @php
                        $statusColors = ['reconciled' => 'background:#d1fae5; color:#065f46;', 'open' => 'background:#fef9c3; color:#854d0e;', 'overspent' => 'background:#fee2e2; color:#991b1b;'];
                    @endphp
                    <span style="font-size:12px; font-weight:600; padding:2px 8px; border-radius:4px; {{ $statusColors[$issuance->status] ?? '' }} text-transform:capitalize;">{{ $issuance->status }}</span>
                </td>
                <td class="edit-only" style="display:none; padding:11px 16px;">
                    <form action="{{ route('float.destroy', $issuance) }}" method="POST" onsubmit="return confirm('Remove this entry?')" style="display:inline;">
                        @csrf @method('DELETE')
                        <button type="submit" style="{{ $delBtn }}" title="Remove">{!! $trashIcon !!}</button>
                    </form>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    </div>{{-- /owner sections grid --}}

    {{-- ════ Add Modals ════ --}}

    {{-- Add Ingredient --}}
    <div id="ov-inv-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Add Ingredient</h2>
                <button onclick="document.getElementById('ov-inv-modal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form action="{{ route('inventory.store') }}" method="POST">
                @csrf
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div style="grid-column:1/-1;">
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Name</label>
                        <input type="text" name="name" required maxlength="255" style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Category</label>
                        <select name="category" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            @foreach(['Meat','Seafood','Dairy','Produce','Pantry','Spice'] as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Unit</label>
                        <select name="unit" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            @foreach(['kg','g','L','ml'] as $u)
                                <option value="{{ $u }}">{{ $u }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Qty on Hand</label>
                        <input type="number" name="quantity_on_hand" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Threshold/Limit</label>
                        <input type="number" name="reorder_threshold" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Unit Cost (RM)</label>
                        <input type="number" name="unit_cost" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button" onclick="document.getElementById('ov-inv-modal').style.display='none'" style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit" style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">Add ingredient</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Log Sale --}}
    <div id="ov-sale-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:460px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Log Sale</h2>
                <button onclick="document.getElementById('ov-sale-modal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form action="{{ route('sales.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Recipe</label>
                    <select name="recipe_id" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select recipe</option>
                        @foreach($ownerData['recipes'] as $recipe)
                            <option value="{{ $recipe->id }}">{{ $recipe->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Quantity</label>
                        <input type="number" name="qty_sold" value="1" min="1" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Selling Price (RM)</label>
                        <input type="number" name="selling_price" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" name="sale_date" value="{{ date('Y-m-d') }}" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button" onclick="document.getElementById('ov-sale-modal').style.display='none'" style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit" style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">Log sale</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Log Wastage --}}
    <div id="ov-wastage-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:460px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Log Wastage</h2>
                <button onclick="document.getElementById('ov-wastage-modal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form action="{{ route('wastage.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Ingredient</label>
                    <select name="inventory_item_id" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select ingredient</option>
                        @foreach($ownerData['inventory'] as $item)
                            <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->unit }})</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Qty Wasted</label>
                        <input type="number" name="quantity_wasted" step="0.01" min="0.01" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Reason</label>
                        <select name="reason" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            @foreach(['spoilage','expired','burnt','dropped','over-prepped','contaminated'] as $r)
                                <option value="{{ $r }}">{{ ucfirst(str_replace('-', ' ', $r)) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" name="recorded_date" value="{{ date('Y-m-d') }}" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button" onclick="document.getElementById('ov-wastage-modal').style.display='none'" style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit" style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">Log wastage</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add Event --}}
    <div id="ov-event-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:460px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Add Event</h2>
                <button onclick="document.getElementById('ov-event-modal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form action="{{ route('events.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Event Name</label>
                    <input type="text" name="name" required maxlength="255" style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" name="event_date" value="{{ date('Y-m-d') }}" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:14px;">
                    <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Menu</label>
                    <input type="text" name="menu" maxlength="500" style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Revenue (RM)</label>
                        <input type="number" name="revenue" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Cost (RM)</label>
                        <input type="number" name="cost" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button" onclick="document.getElementById('ov-event-modal').style.display='none'" style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit" style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">Add event</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Issue Float --}}
    <div id="ov-float-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:460px; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <h2 style="font-family:'DM Sans',sans-serif; font-size:20px; font-weight:400;">Issue Float</h2>
                <button onclick="document.getElementById('ov-float-modal').style.display='none'" style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>
            <form action="{{ route('float.store') }}" method="POST">
                @csrf
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:14px;">
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Amount Given (RM)</label>
                        <input type="number" name="amount_given" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Amount Spent (RM)</label>
                        <input type="number" name="amount_spent" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Amount Returned (RM)</label>
                        <input type="number" name="amount_returned" value="0" step="0.01" min="0" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Status</label>
                        <select name="status" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            <option value="open">Open</option>
                            <option value="reconciled">Reconciled</option>
                            <option value="overspent">Overspent</option>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:13px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" name="issued_date" value="{{ date('Y-m-d') }}" required style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button" onclick="document.getElementById('ov-float-modal').style.display='none'" style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">Cancel</button>
                    <button type="submit" style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">Issue float</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const EDIT_KEY = 'app-edit-mode';

        function applyEditMode(active) {
            document.body.classList.toggle('edit-mode', active);
            document.querySelectorAll('.edit-only').forEach(function(el) {
                var tag = el.tagName.toLowerCase();
                var defaultDisplay = (tag === 'th' || tag === 'td') ? 'table-cell' : 'inline-flex';
                el.style.display = active ? (el.dataset.display || defaultDisplay) : 'none';
            });
            document.querySelectorAll('.edit-toggle-btn').forEach(function(btn) {
                btn.style.color = active ? 'hsl(20,60%,45%)' : 'hsl(24,5%,55%)';
            });
            localStorage.setItem(EDIT_KEY, active ? '1' : '0');
        }

        function toggleEditMode() {
            applyEditMode(!document.body.classList.contains('edit-mode'));
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem(EDIT_KEY) === '1') {
                applyEditMode(true);
            }
        });
    </script>
    @endif

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const copper      = 'hsl(20,60%,45%)';
        const copperLight = 'hsla(20,60%,45%,0.15)';
        const gold        = 'hsl(45,60%,45%)';
        const teal        = 'hsl(180,30%,40%)';
        const palette     = [copper, gold, teal, 'hsl(15,50%,55%)', 'hsl(35,40%,60%)', 'hsl(200,30%,50%)'];

        function fmtDate(str) {
            const d = new Date(str + 'T00:00:00');
            return d.toLocaleDateString('en-MY', { month: 'short', day: 'numeric' });
        }

        function rmTooltip(label) {
            return function(ctx) {
                const val = typeof ctx.parsed === 'object' ? ctx.parsed.y : ctx.parsed;
                return (label ? label + ': ' : '') + 'RM ' + val.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };
        }

        function emptyChart(id, isEmpty) {
            const el = document.getElementById(id);
            if (!isEmpty) return el;
            el.style.display = 'none';
            const msg = document.createElement('p');
            msg.style.cssText = 'font-size:14px; color:hsl(24,5%,45%); padding:40px 0; text-align:center;';
            msg.textContent = 'No data for this month yet.';
            el.parentNode.appendChild(msg);
            return null;
        }

        @unless($hideSales)
        // Sales vs COGS
        const salesDates   = @json($salesTrend->pluck('date'));
        const salesRevenue = @json($salesTrend->pluck('revenue'));
        const cogsDates    = @json($purchaseTrend->pluck('date'));
        const cogsTotal    = @json($purchaseTrend->pluck('total'));
        const allDates     = [...new Set([...salesDates, ...cogsDates])].sort();

        const salesCogsEl = emptyChart('salesCogsChart', allDates.length === 0);
        if (salesCogsEl) {
            new Chart(salesCogsEl, {
                type: 'line',
                data: {
                    labels: allDates.map(fmtDate),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: allDates.map(d => { const i = salesDates.indexOf(d); return i >= 0 ? salesRevenue[i] : 0; }),
                            borderColor: copper, backgroundColor: copperLight, fill: true, tension: 0.4,
                        },
                        {
                            label: 'Purchase Spend',
                            data: allDates.map(d => { const i = cogsDates.indexOf(d); return i >= 0 ? cogsTotal[i] : 0; }),
                            borderColor: teal, backgroundColor: 'hsla(180,30%,40%,0.1)', fill: true, tension: 0.4,
                        }
                    ]
                },
                options: {
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { callbacks: { label: rmTooltip(null) } }
                    },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => 'RM ' + v.toLocaleString('en-MY') } } }
                }
            });
        }
        @endunless

        // Wastage by Reason donut
        const reasonLabels = @json($wastageByReason->pluck('reason'));
        const reasonTotals = @json($wastageByReason->pluck('total'));

        const wastageReasonEl = emptyChart('wastageReasonChart', reasonLabels.length === 0);
        if (wastageReasonEl) {
            new Chart(wastageReasonEl, {
                type: 'doughnut',
                data: { labels: reasonLabels, datasets: [{ data: reasonTotals, backgroundColor: palette }] },
                options: {
                    plugins: {
                        legend: { position: 'right' },
                        tooltip: { callbacks: { label: rmTooltip(null) } }
                    }
                }
            });
        }

        // Inventory by Category bar
        const catLabels = @json($inventoryByCategory->pluck('category'));
        const catTotals = @json($inventoryByCategory->pluck('total'));

        const invCatEl = emptyChart('inventoryCategoryChart', catLabels.length === 0);
        if (invCatEl) {
            new Chart(invCatEl, {
                type: 'bar',
                data: { labels: catLabels, datasets: [{ label: 'Value (RM)', data: catTotals, backgroundColor: palette }] },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: rmTooltip('Value') } }
                    },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => 'RM ' + v.toLocaleString('en-MY') } } }
                }
            });
        }

        // Wastage trend area
        const wtDates  = @json($wastageTrend->pluck('date'));
        const wtTotals = @json($wastageTrend->pluck('total'));

        const wastageTrendEl = emptyChart('wastageTrendChart', wtDates.length === 0);
        if (wastageTrendEl) {
            new Chart(wastageTrendEl, {
                type: 'line',
                data: {
                    labels: wtDates.map(fmtDate),
                    datasets: [{
                        label: 'Cost Lost',
                        data: wtTotals,
                        borderColor: 'hsl(0,70%,50%)', backgroundColor: 'hsla(0,70%,50%,0.1)', fill: true, tension: 0.4,
                    }]
                },
                options: {
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: rmTooltip('Cost Lost') } }
                    },
                    scales: { y: { beginAtZero: true, ticks: { callback: v => 'RM ' + v.toLocaleString('en-MY') } } }
                }
            });
        }
    </script>


    {{-- KPI count-up animation --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.app-kpi').forEach(function (el) {
                var target   = parseFloat(el.dataset.value) || 0;
                var prefix   = el.dataset.prefix  || '';
                var suffix   = el.dataset.suffix  || '';
                var decimals = parseInt(el.dataset.decimals  || '0');
                var duration = 900;
                var start    = performance.now();
                function step(now) {
                    var t      = Math.min(1, (now - start) / duration);
                    var eased  = 1 - Math.pow(1 - t, 3);
                    var value  = target * eased;
                    var formatted = value.toFixed(decimals)
                        .replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    el.textContent = prefix + formatted + suffix;
                    if (t < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            });
        });
    </script>
</x-app-shell>