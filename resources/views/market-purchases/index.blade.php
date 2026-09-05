@php use Illuminate\Support\Facades\Storage; @endphp
<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Market</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Ingredients purchased from markets and other non-supplier sources.</p>
        </div>
        @can('manage-market-purchases')
        <button
            onclick="document.getElementById('add-modal').style.display='flex'"
            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
            + New Market Purchase
        </button>
        @endcan
    </div>

    {{-- Date range filter --}}
    @php
        $active = 'background:hsl(20,60%,45%); color:white; border-color:hsl(20,60%,45%);';
        $normal = 'background:white; color:hsl(24,10%,20%); border-color:hsl(30,15%,85%);';
    @endphp
    <div style="display:flex; gap:8px; align-items:center; margin-bottom:24px; flex-wrap:wrap;">
        <a href="{{ route('market-purchases.index', ['range' => 'today']) }}"
           style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; {{ $range === 'today' ? $active : $normal }}">Today</a>
        <a href="{{ route('market-purchases.index', ['range' => 'week']) }}"
           style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; {{ $range === 'week' ? $active : $normal }}">This Week</a>
        <a href="{{ route('market-purchases.index', ['range' => 'month']) }}"
           style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; {{ $range === 'month' ? $active : $normal }}">This Month</a>
        <a href="{{ route('market-purchases.index', ['range' => 'year']) }}"
           style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; text-decoration:none; {{ $range === 'year' ? $active : $normal }}">This Year</a>
        <form method="GET" action="{{ route('market-purchases.index') }}" style="display:flex; gap:6px; align-items:center;">
            <input type="month" name="month" value="{{ $month }}"
                   style="padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px;">
            <button type="submit"
                    style="padding:7px 14px; border:1px solid; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer; {{ !$range ? $active : $normal }}">Custom</button>
        </form>
        <span style="margin-left:auto; font-size:14px; color:hsl(24,5%,45%);">
            Total spend: <strong>@money($totalSpend)</strong>
        </span>
    </div>

    @if($purchases->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            @if($totalOnRecord > 0)
                Nothing here for this period. There {{ $totalOnRecord === 1 ? 'is' : 'are' }}
                <strong>{{ $totalOnRecord }}</strong> {{ \Illuminate\Support\Str::plural('market purchase', $totalOnRecord) }} on record — change the month above to see them.
            @else
                No market purchases yet. Log your first one.
            @endif
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Date</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Signed By</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Logged By</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Items</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Status</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Receipt</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Total</th>
                        @can('delete-entries')
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Actions</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $purchase)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px;">{{ $purchase->purchase_date->format('M d, Y') }}</td>
                            <td style="padding:12px 16px; font-weight:500;">{{ $purchase->signed_by }}</td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%);">{{ $purchase->user?->name ?? '—' }}</td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%);">
                                {{ $purchase->lines->count() }} item{{ $purchase->lines->count() === 1 ? '' : 's' }}
                            </td>
                            <td style="padding:12px 16px;">
                                <span style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">Completed</span>
                            </td>
                            <td style="padding:12px 16px;">
                                @if($purchase->receipt_path)
                                    <img src="{{ route('market-purchases.receipt', $purchase) }}"
                                         alt="Receipt"
                                         onclick="openReceipt('{{ route('market-purchases.receipt', $purchase) }}')"
                                         style="width:44px; height:44px; object-fit:cover; border-radius:4px; cursor:pointer; border:1px solid hsl(30,15%,85%);">
                                @else
                                    <span style="color:hsl(24,5%,65%); font-size:13px;">—</span>
                                @endif
                            </td>
                            <td style="padding:12px 16px; font-weight:500;">@money($purchase->total_amount)</td>
                            @can('delete-entries')
                            <td style="padding:12px 16px;">
                                <form action="{{ route('market-purchases.destroy', $purchase) }}" method="POST"
                                      onsubmit="return confirm('Remove this market purchase?')" style="display:inline;">
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

    {{-- Image lightbox --}}
    <div id="receipt-overlay"
         onclick="closeReceipt()"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:100; align-items:center; justify-content:center; cursor:zoom-out;">
        <img id="receipt-full" src="" alt="Receipt"
             style="max-width:90vw; max-height:90vh; border-radius:8px; object-fit:contain;">
    </div>

    @can('manage-market-purchases')
    {{-- Add Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:flex-start; justify-content:center; padding:32px 16px; overflow-y:auto;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:720px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">New Market Purchase</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Record ingredients bought from a market or non-supplier source.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('market-purchases.store') }}" method="POST" enctype="multipart/form-data">
                @csrf

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Signed By</label>
                        <input type="text" name="signed_by" placeholder="Staff name" required maxlength="100"
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                        <input type="date" name="purchase_date" value="{{ date('Y-m-d') }}" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">
                        Receipt Photo <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span>
                    </label>
                    <input type="file" name="receipt" accept="image/jpg,image/jpeg,image/png,image/webp"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    <p style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">JPG, PNG or WebP. Max 5 MB.</p>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">
                        Notes <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span>
                    </label>
                    <input type="text" name="notes" placeholder="e.g. Bought from Pasar Minggu" maxlength="500"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                </div>

                @include('partials.line-items')

                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('add-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Log purchase
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReceipt(url) {
            document.getElementById('receipt-full').src = url;
            document.getElementById('receipt-overlay').style.display = 'flex';
        }

        function closeReceipt() {
            document.getElementById('receipt-overlay').style.display = 'none';
            document.getElementById('receipt-full').src = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeReceipt();
        });
    </script>
    @endcan
</x-app-shell>
