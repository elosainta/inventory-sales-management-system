<x-app-shell>
    <div style="margin-bottom:24px;">
        <a href="{{ route('purchases.index') }}" style="color:hsl(24,5%,45%); font-size:13px; text-decoration:none;">← All purchases</a>
        <h1 style="font-family:'DM Sans',sans-serif; font-size:28px; font-weight:400; margin-top:10px;">
            {{ $purchase->supplier->name }}
        </h1>
    </div>

    <div style="display:grid; grid-template-columns:minmax(260px,1fr) minmax(320px,2fr); gap:24px; align-items:start;">

        {{-- The paper, next to the numbers, so they can be compared without leaving the page --}}
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:16px; position:sticky; top:16px;">
            <p style="font-size:13px; font-weight:600; margin-bottom:10px;">Receipt</p>
            @if($purchase->receipt_path)
                <a href="{{ route('purchases.receipt', $purchase) }}" target="_blank" rel="noopener">
                    <img src="{{ route('purchases.receipt', $purchase) }}" alt="Receipt"
                         style="width:100%; border-radius:6px; border:1px solid hsl(30,15%,88%);">
                </a>
            @else
                <p style="color:hsl(24,5%,55%); font-size:13px;">No receipt was attached to this purchase.</p>
            @endif
        </div>

        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
            @if($purchase->status === 'completed')
                <span style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:3px 12px; border-radius:999px;">Completed</span>
            @else
                <span style="background:#fef9c3; color:#854d0e; font-size:12px; font-weight:600; padding:3px 12px; border-radius:999px;">Pending</span>
            @endif

            <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400; margin:16px 0 4px;">
                Purchase #{{ $purchase->id }}
            </h2>
            <p style="color:hsl(24,5%,45%); font-size:13px; margin-bottom:20px;">
                Logged {{ $purchase->created_at?->format('M d, Y H:i') }} by {{ $purchase->user?->name ?? 'a former team member' }}.
            </p>

            <table style="width:100%; font-size:14px; border-collapse:collapse;">
                <tr><td style="padding:6px 0; color:hsl(24,5%,45%);">Supplier</td><td style="padding:6px 0; font-weight:500;">{{ $purchase->supplier->name }}</td></tr>
                <tr><td style="padding:6px 0; color:hsl(24,5%,45%);">Invoice No.</td><td style="padding:6px 0; font-family:'JetBrains Mono',monospace;">{{ $purchase->invoice_number ?: '—' }}</td></tr>
                <tr><td style="padding:6px 0; color:hsl(24,5%,45%);">Date</td><td style="padding:6px 0;">{{ $purchase->purchase_date->format('M d, Y') }}</td></tr>
                <tr><td style="padding:6px 0; color:hsl(24,5%,45%);">Total</td><td style="padding:6px 0; font-family:'JetBrains Mono',monospace; font-weight:600;">@money($purchase->total_amount)</td></tr>
            </table>

            @if($purchase->invoiceScan)
                <p style="font-size:13px; margin:16px 0 0;">
                    Read from a photo —
                    <a href="{{ route('invoice-scan.show', $purchase->invoiceScan) }}" style="color:hsl(20,60%,45%); font-weight:500;">scan #{{ $purchase->invoiceScan->id }}</a>{{ $purchase->invoiceScan->bukku_number ? ', billed in Bukku as ' . $purchase->invoiceScan->bukku_number : '' }}.
                </p>
            @endif

            <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; margin:28px 0 12px;">
                What came in
            </h3>

            @if($purchase->lines->isEmpty())
                <p style="color:hsl(24,5%,55%); font-size:13px;">No lines were recorded against this purchase.</p>
            @else
                <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                    <thead>
                        <tr style="border-bottom:1px solid hsl(30,15%,90%);">
                            <th style="text-align:left; padding:8px 0; font-weight:600;">Item</th>
                            <th style="text-align:right; padding:8px 0; font-weight:600;">Qty</th>
                            <th style="text-align:right; padding:8px 0; font-weight:600;">Unit price</th>
                            <th style="text-align:right; padding:8px 0; font-weight:600;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($purchase->lines as $line)
                            <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                                <td style="padding:8px 0;">{{ $line->inventoryItem?->name ?? 'Item no longer in inventory' }}</td>
                                <td style="padding:8px 0; text-align:right; font-family:'JetBrains Mono',monospace;">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }} {{ $line->inventoryItem?->unit }}</td>
                                <td style="padding:8px 0; text-align:right; font-family:'JetBrains Mono',monospace;">@money($line->unit_price)</td>
                                <td style="padding:8px 0; text-align:right; font-family:'JetBrains Mono',monospace; font-weight:500;">@money($line->line_total)</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-app-shell>
