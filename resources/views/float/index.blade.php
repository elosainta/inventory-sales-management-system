<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Petty Cash</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Cash issued for runs to the market and reconciliations.</p>
        </div>
        @can('manage-float')
        <button
            onclick="document.getElementById('add-modal').style.display='flex'"
            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
            + Issue Float
        </button>
        @endcan
    </div>

    {{-- KPI Cards --}}
    <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; margin-bottom:32px;">
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Total Given</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">@money($totalGiven)</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Total Spent</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">@money($totalSpent)</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Total Returned</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif;">@money($totalReturned)</div>
        </div>
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:20px;">
            <div style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.05em; color:hsl(24,5%,45%); margin-bottom:8px;">Net Balance</div>
            <div style="font-size:26px; font-weight:500; font-family:'DM Sans',sans-serif; color:{{ $netBalance >= 0 ? 'hsl(140,60%,30%)' : 'hsl(0,70%,50%)' }};">
                @money($netBalance)
            </div>
        </div>
    </div>

    {{-- Table --}}
    @if($issuances->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            No float issuances yet.
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Date</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Given</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Spent</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Returned</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Status</th>
                        @can('manage-float')
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($issuances as $issuance)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px;">{{ $issuance->issued_date->format('M d, Y') }}</td>
                            <td style="padding:12px 16px;">@money($issuance->amount_given)</td>
                            <td style="padding:12px 16px;">@money($issuance->amount_spent)</td>
                            <td style="padding:12px 16px;">@money($issuance->amount_returned)</td>
                            <td style="padding:12px 16px;">
                                @if($issuance->status === 'reconciled')
                                    <span style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">Reconciled</span>
                                @elseif($issuance->status === 'overspent')
                                    <span style="background:#fee2e2; color:#991b1b; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">Overspent</span>
                                @else
                                    <span style="background:#fef9c3; color:#854d0e; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">Open</span>
                                @endif
                            </td>
                            @can('manage-float')
                            <td style="padding:12px 16px;">
                                <form action="{{ route('float.destroy', $issuance) }}" method="POST"
                                      onsubmit="return confirm('Remove this entry?')" style="display:inline;">
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

    @can('manage-float')
    {{-- Add Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Issue Float</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Hand over cash for market runs or one-off purchases.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('float.store') }}" method="POST">
                @csrf
                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Given (RM)</label>
                        <input type="number" name="amount_given" value="0" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Spent (RM)</label>
                        <input type="number" name="amount_spent" value="0" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Returned (RM)</label>
                        <input type="number" name="amount_returned" value="0" step="0.01" min="0" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Status</label>
                        <select name="status" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            <option value="open">Open</option>
                            <option value="reconciled">Reconciled</option>
                            <option value="overspent">Overspent</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                        <input type="date" name="issued_date" value="{{ date('Y-m-d') }}" required
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
                        Issue float
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endcan

</x-app-shell>