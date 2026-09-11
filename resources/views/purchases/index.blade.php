@php use Illuminate\Support\Facades\Storage; @endphp
<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px;">
        <div>
            <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400; margin-bottom:8px;">Purchases</h1>
            <p style="color:hsl(24,5%,45%); font-size:14px;">Every supplier order — what came in and what it cost.</p>
        </div>
        <div style="display:flex; gap:8px;">
            @can('export-pdf')
            <a href="{{ route('purchases.export-pdf') }}"
               style="background:white; border:1px solid hsl(30,15%,85%); color:hsl(24,10%,15%); padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
                Export PDF
            </a>
            @endcan
            @can('manage-purchases')
            <button
                onclick="document.getElementById('add-modal').style.display='flex'"
                style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                + New Purchase
            </button>
            @endcan
        </div>
    </div>
@include('partials.period-filter', ['route' => 'purchases.index', 'totalLabel' => 'Total spend', 'total' => $totalSpend])
    @if($purchases->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            @if($totalOnRecord > 0)
                Nothing here for this period. There {{ $totalOnRecord === 1 ? 'is' : 'are' }}
                <strong>{{ $totalOnRecord }}</strong> {{ \Illuminate\Support\Str::plural('purchase', $totalOnRecord) }} on record — change the month above to see them.
            @else
                No purchases yet. Log your first one.
            @endif
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Date</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Supplier</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Invoice #</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Items</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Status</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Receipt</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Total</th>
                        @canany(['manage-purchases', 'delete-entries'])
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Actions</th>
                        @endcanany
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchases as $purchase)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px;">{{ $purchase->purchase_date->format('M d, Y') }}</td>
                            <td style="padding:12px 16px;">{{ $purchase->supplier->name }}</td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%); font-family:'JetBrains Mono',monospace; font-size:13px;">
                                {{ $purchase->invoice_number ?: '—' }}
                            </td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%);">{{ $purchase->lines->count() }} item{{ $purchase->lines->count() === 1 ? '' : 's' }}</td>
                            <td style="padding:12px 16px;">
                                @can('manage-purchases')
                                    <form action="{{ route('purchases.toggle-status', $purchase) }}" method="POST" style="display:inline;">
                                        @csrf
                                        @method('PATCH')
                                        @if($purchase->status === 'completed')
                                            <button type="submit"
                                                    style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px; border:none; cursor:pointer;">
                                                Completed
                                            </button>
                                        @else
                                            <button type="submit"
                                                    style="background:#fef9c3; color:#854d0e; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px; border:none; cursor:pointer;">
                                                Pending
                                            </button>
                                        @endif
                                    </form>
                                @else
                                    @if($purchase->status === 'completed')
                                        <span style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">Completed</span>
                                    @else
                                        <span style="background:#fef9c3; color:#854d0e; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">Pending</span>
                                    @endif
                                @endcan
                            </td>
                            <td style="padding:12px 16px;">
                                @if($purchase->receipt_path)
                                    <img src="{{ route('purchases.receipt', $purchase) }}"
                                         alt="Receipt"
                                         onclick="openReceipt('{{ route('purchases.receipt', $purchase) }}')"
                                         style="width:44px; height:44px; object-fit:cover; border-radius:4px; cursor:pointer; border:1px solid hsl(30,15%,85%);">
                                @else
                                    <span style="color:hsl(24,5%,65%); font-size:13px;">—</span>
                                @endif
                            </td>
                            <td style="padding:12px 16px; font-weight:500;">@money($purchase->total_amount)</td>
                            @canany(['manage-purchases', 'delete-entries'])
                            <td style="padding:12px 16px;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    @can('manage-purchases')
                                    <button onclick="openEditModal(this)"
                                            data-edit-url="{{ route('purchases.update', $purchase) }}"
                                            data-supplier-id="{{ $purchase->supplier_id }}"
                                            data-date="{{ $purchase->purchase_date->format('Y-m-d') }}"
                                            data-invoice="{{ $purchase->invoice_number }}"
                                            data-status="{{ $purchase->status }}"
                                            style="background:none; border:none; cursor:pointer; color:hsl(24,5%,45%); padding:4px;" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>
                                    @endcan
                                    @can('delete-entries')
                                    <form action="{{ route('purchases.destroy', $purchase) }}" method="POST"
                                          onsubmit="return confirm('Remove this purchase?')" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px;" title="Remove">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                        </button>
                                    </form>
                                    @endcan
                                </div>
                            </td>
                            @endcanany
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Image lightbox overlay --}}
    <div id="receipt-overlay"
         onclick="closeReceipt()"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85); z-index:100; align-items:center; justify-content:center; cursor:zoom-out;">
        <img id="receipt-full"
             src=""
             alt="Receipt"
             style="max-width:90vw; max-height:90vh; border-radius:8px; object-fit:contain;">
    </div>

    @can('manage-purchases')
    {{-- Edit Modal --}}
    <div id="edit-modal"
         onclick="if(event.target===this)document.getElementById('edit-modal').style.display='none'"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:center; justify-content:center; padding:16px;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:480px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">Edit Purchase</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Edit header details only — line items cannot be changed here.</p>
                </div>
                <button onclick="document.getElementById('edit-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form id="edit-form" method="POST">
                @csrf
                @method('PATCH')
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Supplier</label>
                    <select id="edit-supplier" name="supplier_id" required
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Status</label>
                        <select id="edit-status" name="status" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                        <input type="date" id="edit-date" name="purchase_date" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">
                        Invoice Number <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span>
                    </label>
                    <input type="text" id="edit-invoice" name="invoice_number" placeholder="e.g. INV-2026-001"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box; font-family:'JetBrains Mono',monospace;">
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; padding-top:8px;">
                    <button type="button"
                            onclick="document.getElementById('edit-modal').style.display='none'"
                            style="padding:8px 16px; font-size:14px; background:none; border:none; cursor:pointer;">
                        Cancel
                    </button>
                    <button type="submit"
                            style="background-color:hsl(20,60%,45%); color:white; padding:8px 16px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                        Save changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add Modal --}}
    <div id="add-modal"
         style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:50; align-items:flex-start; justify-content:center; padding:32px 16px; overflow-y:auto;">
        <div style="background:white; border-radius:8px; padding:24px; width:100%; max-width:720px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                <div>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400;">New Purchase</h2>
                    <p style="font-size:13px; color:hsl(24,5%,45%);">Record a supplier delivery with itemized lines.</p>
                </div>
                <button onclick="document.getElementById('add-modal').style.display='none'"
                        style="background:none; border:none; cursor:pointer; font-size:20px; color:hsl(24,5%,45%);">×</button>
            </div>

            <form action="{{ route('purchases.store') }}" method="POST" enctype="multipart/form-data" id="purchase-form">
                @csrf
                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Supplier</label>
                    <select name="supplier_id" required
                            style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                        <option value="">Select supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">
                        Invoice Number <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span>
                    </label>
                    <input type="text" name="invoice_number" placeholder="e.g. INV-2026-001"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box; font-family:'JetBrains Mono',monospace;">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">
                        Receipt Photo <span style="color:hsl(24,5%,45%); font-weight:400;">(optional)</span>
                    </label>
                    <input type="file" name="receipt" accept="image/jpg,image/jpeg,image/png,image/webp"
                           style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    <p style="font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">JPG, PNG or WebP. Max 5MB.</p>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px;">
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Status</label>
                        <select name="status" required
                                style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
                            <option value="completed">Completed</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size:14px; font-weight:500; margin-bottom:4px;">Date</label>
                        <input type="date" name="purchase_date" value="{{ date('Y-m-d') }}" required
                               style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                    </div>
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
        function openEditModal(btn) {
            var d = btn.dataset;
            document.getElementById('edit-form').action = d.editUrl;
            document.getElementById('edit-supplier').value = d.supplierId;
            document.getElementById('edit-date').value = d.date;
            document.getElementById('edit-invoice').value = d.invoice || '';
            document.getElementById('edit-status').value = d.status;
            document.getElementById('edit-modal').style.display = 'flex';
            document.getElementById('edit-supplier').focus();
        }

        function openReceipt(url) {
            document.getElementById('receipt-full').src = url;
            document.getElementById('receipt-overlay').style.display = 'flex';
        }

        function closeReceipt() {
            document.getElementById('receipt-overlay').style.display = 'none';
            document.getElementById('receipt-full').src = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') { closeReceipt(); document.getElementById('edit-modal').style.display = 'none'; }
        });

    </script>
    @endcan
</x-app-shell>