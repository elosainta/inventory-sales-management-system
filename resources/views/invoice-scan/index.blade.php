<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px; gap:16px; flex-wrap:wrap;">
        <div>
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400;">Invoice Scan / DO</h1>
                <span style="background:hsl(20,60%,45%); color:white; font-size:11px; font-weight:600; letter-spacing:0.06em; padding:3px 9px; border-radius:999px;">BETA</span>
            </div>
            <p style="color:hsl(24,5%,45%); font-size:14px; max-width:620px;">
                Photograph a supplier invoice or delivery order (DO), check what was read off it, and send it straight to Bukku as a purchase bill.
            </p>
        </div>
    </div>

    {{-- A beta that writes to the accounts has to say what it does and does not do. --}}
    <div style="background:hsl(30,25%,96%); border:1px solid hsl(30,15%,88%); border-left:3px solid hsl(20,60%,45%); border-radius:8px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:hsl(24,10%,30%); line-height:1.6;">
        <strong>This is a beta.</strong> Nothing reaches Bukku until you have read the numbers and pressed
        <em>Send to Bukku</em> — the scan only fills the form in. Check the total against the paper before you send:
        a misread figure becomes a real bill on the books, and a bill has to be voided in Bukku, not deleted.
    </div>

    @if(! $configured)
        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:#991b1b; line-height:1.6;">
            <strong>Not configured yet.</strong> The scan and Bukku keys are missing from this server's
            <code>.env</code>. Set <code>ANTHROPIC_API_KEY</code> and <code>BUKKU_API_TOKEN</code>, then run
            <code>php artisan bukku:ping</code> to confirm.
        </div>
    @endif

    {{-- What is still owed, read off Bukku - every bill, not only the ones
         scanned here. Managers only (the controller sends nothing otherwise).
         <details>, so it is one line until someone wants the list. --}}
    @if($owed->isNotEmpty())
        @php
            // By month, oldest first: this is an aging list, so the month
            // holding the most overdue money reads first. The month LABEL is
            // taken off a row's own date, never by re-parsing the Y-m key —
            // Carbon fills a missing day from today, so parsing 2026-02 on
            // the 31st lands in March.
            $byMonth = $owed
                ->groupBy(fn ($b) => \Illuminate\Support\Carbon::parse($b['date'])->format('Y-m'))
                ->sortKeys();
        @endphp
        <details id="owed" style="background:white; border:1px solid hsl(30,15%,90%); border-left:3px solid #d97706; border-radius:8px; padding:14px 18px; margin-bottom:24px;">
            <summary style="cursor:pointer; font-size:15px;">
                <strong>Owed to suppliers: @money($owed->sum('balance'))</strong>
                <span style="color:hsl(24,5%,45%); font-size:13px;">on {{ $owed->count() }} unpaid {{ Str::plural('bill', $owed->count()) }} in Bukku, by month, oldest first</span>
            </summary>
            <p style="color:hsl(24,5%,45%); font-size:12px; margin:10px 0 4px;">
                Read from Bukku, including bills entered there directly. Record payments in Bukku; this list updates within five minutes.
            </p>
            @foreach($byMonth as $monthBills)
                @php
                    $month   = \Illuminate\Support\Carbon::parse($monthBills->first()['date'])->format('F Y');
                    $overdue = $monthBills->filter(fn ($b) => \Illuminate\Support\Carbon::parse($b['date'])->diffInDays(today()) > 30);
                    $bySupplier = $monthBills
                        ->groupBy(fn ($b) => $kitchenNames[$b['contact_id'] ?? 0] ?? ($b['contact_name'] ?? 'Unknown supplier'))
                        ->sortByDesc(fn ($group) => $group->sum('balance'));
                @endphp
                {{-- A month is shut until someone wants it: ninety-odd bills
                     open at once is the wall this replaced. --}}
                <details style="border-top:1px solid hsl(30,15%,88%); padding:8px 0;">
                    {{-- display:flex on a <summary> drops the disclosure
                         triangle in Chrome, so the flex row is a span inside
                         it and the marker stays, matching the panel above. --}}
                    <summary style="cursor:pointer; font-size:14px;">
                        <span style="display:inline-flex; justify-content:space-between; gap:12px; width:calc(100% - 1.4em); vertical-align:top;">
                            <span>
                                <strong>{{ $month }}</strong>
                                <span style="color:hsl(24,5%,45%);">· {{ $monthBills->count() }} {{ Str::plural('bill', $monthBills->count()) }} · {{ $bySupplier->count() }} {{ Str::plural('supplier', $bySupplier->count()) }}</span>
                                @if($overdue->isNotEmpty())
                                    <span style="color:#b91c1c;">· {{ $overdue->count() }} over 30 days</span>
                                @endif
                            </span>
                            <strong style="font-family:'JetBrains Mono',monospace; white-space:nowrap;">@money($monthBills->sum('balance'))</strong>
                        </span>
                    </summary>
                @foreach($bySupplier as $supplier => $supplierBills)
                    <div style="padding:8px 0 8px 12px;">
                        <div style="display:flex; justify-content:space-between; gap:12px; font-weight:600; font-size:14px;">
                            <span>{{ $supplier }} <span style="color:hsl(24,5%,45%); font-weight:400;">· {{ $supplierBills->count() }}</span></span>
                            <span style="font-family:'JetBrains Mono',monospace;">@money($supplierBills->sum('balance'))</span>
                        </div>
                        @foreach($supplierBills as $bill)
                            @php $age = (int) \Illuminate\Support\Carbon::parse($bill['date'])->diffInDays(today()); @endphp
                            <div style="display:flex; justify-content:space-between; gap:12px; font-size:13px; color:hsl(24,10%,30%); padding:3px 0 0 12px; flex-wrap:wrap;">
                                <span>
                                    @if(! empty($bill['short_link']))
                                        <a href="{{ $bill['short_link'] }}" target="_blank" rel="noopener" style="color:hsl(20,60%,45%); text-decoration:none; font-family:'JetBrains Mono',monospace;">{{ $bill['number'] }}</a>
                                    @else
                                        <span style="font-family:'JetBrains Mono',monospace;">{{ $bill['number'] }}</span>
                                    @endif
                                    @if(! empty($bill['number2'])) · {{ $bill['number2'] }} @endif
                                    · {{ \Illuminate\Support\Carbon::parse($bill['date'])->format('d M Y') }}
                                    <span style="color:{{ $age > 30 ? '#b91c1c' : 'hsl(24,5%,45%)' }};">· {{ $age }} {{ Str::plural('day', $age) }} old</span>
                                </span>
                                <span style="font-family:'JetBrains Mono',monospace;">
                                    @money($bill['balance'])
                                    @if((float) $bill['balance'] < (float) $bill['amount'])
                                        <span style="color:hsl(24,5%,45%);">of @money($bill['amount'])</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
                </details>
            @endforeach
        </details>
        {{-- Arriving from the dashboard's "Owed to suppliers" opens the list. --}}
        <script>if (location.hash === '#owed') document.getElementById('owed').open = true;</script>
    @endif

    {{-- Upload --}}
    <form method="POST" action="{{ route('invoice-scan.store') }}" enctype="multipart/form-data"
          onsubmit="document.getElementById('scan-go').disabled=true; document.getElementById('scan-go').textContent='Reading it…'; document.getElementById('scan-wait').style.display='block';"
          style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px; margin-bottom:32px;">
        @csrf

        {{-- Which paper this is. A delivery order goes through exactly the same
             review and becomes exactly the same Bukku bill as an invoice; the
             choice only labels it, so it can be told apart later. Plain radios:
             two options do not need a widget, and a wrong pick can still be
             corrected on the review screen before anything is sent. --}}
        <fieldset style="border:none; padding:0; margin:0 0 18px;">
            <legend style="font-size:14px; font-weight:600; margin-bottom:8px;">What are you scanning?</legend>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                @foreach(\App\Models\InvoiceScan::TYPES as $value => $label)
                    <label style="display:inline-flex; align-items:center; gap:8px; border:1px solid hsl(30,15%,85%); border-radius:6px; padding:9px 14px; font-size:14px; cursor:pointer;">
                        <input type="radio" name="document_type" value="{{ $value }}"
                               @checked(old('document_type', \App\Models\InvoiceScan::TYPE_INVOICE) === $value)
                               style="accent-color:hsl(20,60%,45%); color:hsl(20,60%,45%);">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        <label for="invoice" style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Photo or PDF</label>
        <p style="color:hsl(24,5%,45%); font-size:13px; margin-bottom:12px;">JPG, PNG, WEBP, GIF or PDF, up to 25 MB.</p>

        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            {{-- The camera is the native one. `capture="environment"` on a file
                 input opens the rear camera straight from the browser on a
                 phone, which is the only device this ever gets used on — no
                 getUserMedia, no preview canvas, no library. On a desktop the
                 attribute is ignored and this is an ordinary file picker,
                 which is not a wrong thing for the button to do. --}}
            <button type="button" onclick="takePhoto()" title="Take a photo of the invoice or delivery order"
                    style="display:inline-flex; align-items:center; gap:8px; background:white; border:1px solid hsl(30,15%,85%); border-radius:6px; padding:9px 16px; font-size:14px; font-weight:500; cursor:pointer; color:hsl(24,10%,20%);">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/>
                    <circle cx="12" cy="13" r="3.5"/>
                </svg>
                Take photo
            </button>

            <input type="file" name="invoice" id="invoice" required
                   accept=".jpg,.jpeg,.png,.webp,.gif,.pdf" data-accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"
                   style="flex:1; min-width:240px; padding:9px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">

            <button type="submit" id="scan-go"
                    style="background:hsl(20,60%,45%); color:white; padding:10px 20px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                Scan
            </button>
        </div>

        <script>
            // One input, two ways in. Two inputs sharing name="invoice" would
            // fight over which one posts, and the empty one would block the
            // submit on its own `required`.
            function takePhoto() {
                var input = document.getElementById('invoice');
                input.setAttribute('accept', 'image/*');
                input.setAttribute('capture', 'environment');
                input.click();
            }

            // Put it back afterwards, or the file button next to it would be
            // stuck opening the camera and refuse to offer a PDF.
            document.getElementById('invoice').addEventListener('change', function () {
                this.setAttribute('accept', this.dataset.accept);
                this.removeAttribute('capture');
            });
        </script>

        @error('invoice')
            <p style="color:#b91c1c; font-size:13px; margin-top:10px;">{{ $message }}</p>
        @enderror

        <p id="scan-wait" style="display:none; color:hsl(24,5%,45%); font-size:13px; margin-top:12px;">
            This takes up to a minute. Leave the page open.
        </p>
    </form>

    {{-- History --}}
    @if($scans->isEmpty())
        <div style="text-align:center; padding:64px; color:hsl(24,5%,45%);">
            Nothing scanned yet. Upload an invoice or delivery order above.
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table app-scan-list" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Scanned</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Supplier</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Document</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">By</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Status</th>
                        <th style="text-align:right; padding:12px 16px; font-weight:600;">Total</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($scans as $scan)
                        <tr style="border-bottom:1px solid hsl(30,15%,93%);">
                            <td style="padding:12px 16px; white-space:nowrap;">{{ $scan->created_at->format('M d, Y H:i') }}</td>
                            <td style="padding:12px 16px; font-weight:500;">{{ $scan->supplier_name ?: '—' }}</td>
                            <td style="padding:12px 16px; white-space:nowrap;">
                                @if($scan->isDeliveryOrder())
                                    <span style="background:hsl(210,60%,94%); color:hsl(210,55%,32%); font-size:11px; font-weight:600; letter-spacing:0.04em; padding:2px 8px; border-radius:999px; margin-right:6px;">DO</span>
                                @else
                                    <span style="background:hsl(30,20%,93%); color:hsl(24,10%,35%); font-size:11px; font-weight:600; letter-spacing:0.04em; padding:2px 8px; border-radius:999px; margin-right:6px;">INV</span>
                                @endif
                                <span style="font-family:'JetBrains Mono',monospace; font-size:13px;">{{ $scan->invoice_number ?: '—' }}</span>
                            </td>
                            <td style="padding:12px 16px; color:hsl(24,5%,45%);">{{ $scan->user?->name ?? '—' }}</td>
                            <td style="padding:12px 16px;">
                                @if($scan->isPosted())
                                    <span style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">
                                        In Bukku · {{ $scan->bukku_number }}
                                    </span>
                                @elseif($scan->status === \App\Models\InvoiceScan::STATUS_FAILED)
                                    <span style="background:#fee2e2; color:#991b1b; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">Could not read</span>
                                @else
                                    <span style="background:#fef3c7; color:#92400e; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px;">Needs checking</span>
                                @endif
                                @if($scan->isPosted() && isset($bills[$scan->bukku_transaction_id]))
                                    @if((float) $bills[$scan->bukku_transaction_id]['balance'] > 0)
                                        <span style="background:#fef3c7; color:#92400e; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px; margin-left:4px;">Owes @money($bills[$scan->bukku_transaction_id]['balance'])</span>
                                    @else
                                        <span style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px; margin-left:4px;">Paid</span>
                                    @endif
                                @endif
                                @isset($duplicateIds[$scan->id])
                                    <span title="Looks like the same invoice as another scan - open it to see which"
                                          style="background:#fee2e2; color:#991b1b; font-size:12px; font-weight:600; padding:2px 10px; border-radius:999px; margin-left:4px;">Duplicate?</span>
                                @endisset
                            </td>
                            <td style="padding:12px 16px; text-align:right; font-family:'JetBrains Mono',monospace;">
                                @if($scan->total_amount !== null)@money($scan->total_amount)@else—@endif
                            </td>
                            <td style="padding:12px 16px;">
                                <a href="{{ route('invoice-scan.show', $scan) }}"
                                   style="color:hsl(20,60%,45%); font-weight:500; text-decoration:none; font-size:13px;">
                                    {{ $scan->isPosted() ? 'View' : 'Check & send' }} →
                                </a>
                                @can('delete-entries')
                                    <form action="{{ route('invoice-scan.destroy', $scan) }}" method="POST"
                                          onsubmit="return confirm('Remove this scan? {{ $scan->isPosted() ? 'The bill stays in Bukku — void it there if it was wrong.' : 'The photo goes with it.' }}')"
                                          style="display:inline; margin-left:12px;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); padding:4px; vertical-align:middle;" title="Remove">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px;">{{ $scans->links() }}</div>
    @endif
</x-app-shell>
