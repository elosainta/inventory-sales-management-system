<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Special Events</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Private dinners, banquets, and one-off occasions.</p>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('events.export-pdf') }}?month={{ $month }}"
               style="background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,15%); padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
                Export PDF
            </a>
            @can('manage-events')
            <button onclick="document.getElementById('add-modal').style.display='flex'"
                    style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                + Add Event
            </button>
            @endcan
        </div>
    </div>

    {{-- KPI Cards --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:32px;">
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Events This Month</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">{{ $eventCount }}</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Revenue</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif; color:hsl(140,60%,30%);">@money($totalRevenue)</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Cost</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif; color:hsl(0,70%,50%);">@money($totalCost)</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Net Profit</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif; color:{{ $netProfit >= 0 ? 'hsl(140,60%,30%)' : 'hsl(0,70%,50%)' }};">
                @money($netProfit)
            </div>
        </div>
    </div>

    {{-- Month filter --}}
    <form method="GET" action="{{ route('events.index') }}"
          class="app-filters" style="display:flex; gap:12px; align-items:center; margin-bottom:24px; flex-wrap:wrap;">
        <input type="month" name="month" value="{{ $month }}"
               style="padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
        <button type="submit"
                style="padding:8px 16px; background:hsl(30,15%,92%); border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; cursor:pointer;">
            Filter
        </button>
    </form>

    {{-- Table --}}
    @if($events->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            No events logged for this month.
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Date</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Event Name</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Menu</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Revenue</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Cost</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Profit</th>
                        @can('manage-events')
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $event)
                        @php $profit = $event->revenue - $event->cost; @endphp
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px;">{{ $event->event_date->format('M d, Y') }}</td>
                            <td style="padding:12px 16px; font-weight:500;">{{ $event->name }}</td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%); font-size:13px;">
                                {{ $event->menu ?? '—' }}
                            </td>
                            <td style="padding:12px 16px; text-align:right; color:hsl(140,60%,30%); font-weight:500;">
                                @money($event->revenue)
                            </td>
                            <td style="padding:12px 16px; text-align:right; color:hsl(0,70%,50%);">
                                @money($event->cost)
                            </td>
                            <td style="padding:12px 16px; text-align:right; font-weight:600; color:{{ $profit >= 0 ? 'hsl(140,60%,30%)' : 'hsl(0,70%,50%)' }};">
                                @money($profit)
                            </td>
                            @can('manage-events')
                            <td style="padding:12px 16px;">
                                <form action="{{ route('events.destroy', $event) }}" method="POST"
                                      onsubmit="return confirm('Remove this event?')" style="display:inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Remove">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                </form>
                            </td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @can('manage-events')
    {{-- Add Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Add Event</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Log a special event and its financials.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('events.store') }}" method="POST">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Event Name</label>
                    <input type="text" name="name" required placeholder="e.g. Private Dining — Lim Family"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                    <input type="date" name="event_date" value="{{ date('Y-m-d') }}" required
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Menu <span style="color:hsl(24,5%,55%); font-weight:400;">(optional)</span></label>
                    <textarea name="menu" rows="3" placeholder="Brief description of dishes served"
                              style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box; resize:vertical;"></textarea>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Revenue (RM)</label>
                        <input type="number" name="revenue" value="0" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Cost (RM)</label>
                        <input type="number" name="cost" value="0" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('add-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save event
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</x-app-shell>
