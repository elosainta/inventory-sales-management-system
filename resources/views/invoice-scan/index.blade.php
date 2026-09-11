<x-app-shell>
    <div class="app-page-header" style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:32px; gap:16px; flex-wrap:wrap;">
        <div>
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <h1 style="font-family:'DM Sans',sans-serif; font-size:32px; font-weight:400;">Invoice Scan</h1>
                <span style="background:hsl(20,60%,45%); color:white; font-size:11px; font-weight:600; letter-spacing:0.06em; padding:3px 9px; border-radius:999px;">BETA</span>
            </div>
            <p style="color:hsl(24,5%,45%); font-size:14px; max-width:620px;">
                Photograph a supplier invoice, check what was read off it, and send it straight to Bukku as a purchase bill.
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

    {{-- Upload --}}
    <form method="POST" action="{{ route('invoice-scan.store') }}" enctype="multipart/form-data"
          onsubmit="document.getElementById('scan-go').disabled=true; document.getElementById('scan-go').textContent='Reading the invoice…'; document.getElementById('scan-wait').style.display='block';"
          style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px; margin-bottom:32px;">
        @csrf
        <label for="invoice" style="display:block; font-size:14px; font-weight:600; margin-bottom:6px;">Invoice photo or PDF</label>
        <p style="color:hsl(24,5%,45%); font-size:13px; margin-bottom:12px;">JPG, PNG, WEBP, GIF or PDF, up to 25 MB.</p>

        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            {{-- The camera is the native one. `capture="environment"` on a file
                 input opens the rear camera straight from the browser on a
                 phone, which is the only device this ever gets used on — no
                 getUserMedia, no preview canvas, no library. On a desktop the
                 attribute is ignored and this is an ordinary file picker,
                 which is not a wrong thing for the button to do. --}}
            <button type="button" onclick="takePhoto()" title="Take a photo of the invoice"
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
                Scan invoice
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
            No invoices scanned yet. Upload one above.
        </div>
    @else
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; overflow:hidden;">
            <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="border-bottom:1px solid hsl(30,15%,90%); background:hsl(30,15%,97%);">
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Scanned</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Supplier</th>
                        <th style="text-align:left; padding:12px 16px; font-weight:600;">Invoice No.</th>
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
                            <td style="padding:12px 16px; font-family:'JetBrains Mono',monospace; font-size:13px;">{{ $scan->invoice_number ?: '—' }}</td>
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
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px;">{{ $scans->links() }}</div>
    @endif
</x-app-shell>
