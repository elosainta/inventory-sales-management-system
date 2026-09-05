@php
    $posted   = $scan->isPosted();
    $lines    = old('lines', $scan->lines() ?: [['description' => '', 'quantity' => 1, 'unit_price' => 0]]);
    $inputCss = 'width:100%; padding:8px 10px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;';
    $labelCss = 'display:block; font-size:13px; font-weight:600; margin-bottom:5px;';
@endphp

<x-app-shell>
    <div style="margin-bottom:24px;">
        <a href="{{ route('invoice-scan.index') }}" style="color:hsl(24,5%,45%); font-size:13px; text-decoration:none;">← All scans</a>
        <div style="display:flex; align-items:center; gap:10px; margin-top:10px;">
            <h1 style="font-family:'DM Sans',sans-serif; font-size:28px; font-weight:400;">
                {{ $posted ? 'Bill ' . $scan->bukku_number : 'Check this invoice' }}
            </h1>
            <span style="background:hsl(20,60%,45%); color:white; font-size:11px; font-weight:600; letter-spacing:0.06em; padding:3px 9px; border-radius:999px;">BETA</span>
        </div>
    </div>

    @if($scan->status === \App\Models\InvoiceScan::STATUS_FAILED)
        <div style="background:#fef2f2; border:1px solid #fecaca; border-radius:8px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:#991b1b; line-height:1.6;">
            <strong>The invoice could not be read.</strong> The photo is still here — read it yourself and fill the form in by hand.
            <br><span style="opacity:0.75; font-size:12px;">{{ $scan->scan_error }}</span>
        </div>
    @endif

    <div style="display:grid; grid-template-columns:minmax(260px,1fr) minmax(320px,2fr); gap:24px; align-items:start;">

        {{-- The paper, kept next to the numbers so they can be compared without leaving the page --}}
        <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:16px; position:sticky; top:16px;">
            <p style="font-size:13px; font-weight:600; margin-bottom:10px;">{{ $scan->original_filename }}</p>
            @if(\Illuminate\Support\Str::endsWith(strtolower($scan->original_filename), '.pdf'))
                <a href="{{ route('invoice-scan.photo', $scan) }}" target="_blank" rel="noopener"
                   style="color:hsl(20,60%,45%); font-size:14px; font-weight:500;">Open the PDF →</a>
            @else
                <a href="{{ route('invoice-scan.photo', $scan) }}" target="_blank" rel="noopener">
                    <img src="{{ route('invoice-scan.photo', $scan) }}" alt="The scanned invoice"
                         style="width:100%; border-radius:6px; border:1px solid hsl(30,15%,88%);">
                </a>
            @endif
        </div>

        <div>
            @if($posted)
                {{-- Done. The only thing left to say is where it went. --}}
                <div style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
                    <span style="background:#dcfce7; color:#166534; font-size:12px; font-weight:600; padding:3px 12px; border-radius:999px;">In Bukku</span>
                    <h2 style="font-family:'DM Sans',sans-serif; font-size:22px; font-weight:400; margin:16px 0 4px;">{{ $scan->bukku_number }}</h2>
                    <p style="color:hsl(24,5%,45%); font-size:13px; margin-bottom:20px;">
                        Sent {{ $scan->posted_at?->format('M d, Y H:i') }} by {{ $scan->user?->name ?? 'a former team member' }}.
                    </p>

                    <table style="width:100%; font-size:14px; border-collapse:collapse;">
                        <tr><td style="padding:6px 0; color:hsl(24,5%,45%);">Supplier</td><td style="padding:6px 0; font-weight:500;">{{ $scan->supplier_name ?: '—' }}</td></tr>
                        <tr><td style="padding:6px 0; color:hsl(24,5%,45%);">Invoice No.</td><td style="padding:6px 0; font-family:'JetBrains Mono',monospace;">{{ $scan->invoice_number ?: '—' }}</td></tr>
                        <tr><td style="padding:6px 0; color:hsl(24,5%,45%);">Date</td><td style="padding:6px 0;">{{ $scan->invoice_date?->format('M d, Y') }}</td></tr>
                        <tr><td style="padding:6px 0; color:hsl(24,5%,45%);">Total</td><td style="padding:6px 0; font-family:'JetBrains Mono',monospace; font-weight:600;">@money($scan->total_amount)</td></tr>
                    </table>

                    @if($scan->bukku_short_link)
                        <a href="{{ $scan->bukku_short_link }}" target="_blank" rel="noopener"
                           style="display:inline-block; margin-top:20px; background:hsl(20,60%,45%); color:white; padding:9px 18px; border-radius:6px; font-size:14px; font-weight:500; text-decoration:none;">
                            Open in Bukku →
                        </a>
                    @endif

                    <p style="color:hsl(24,5%,55%); font-size:12px; margin-top:20px; line-height:1.6;">
                        Wrong? Void it in Bukku — a posted bill cannot be corrected from here.
                    </p>
                </div>
            @else
                <form method="POST" action="{{ route('invoice-scan.push', $scan) }}"
                      onsubmit="this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').textContent='Sending…';"
                      style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; padding:24px;">
                    @csrf

                    @if(empty($contacts))
                        <div style="background:#fffbeb; border:1px solid #fde68a; border-radius:6px; padding:12px 14px; margin-bottom:20px; font-size:13px; color:#92400e; line-height:1.6;">
                            No suppliers came back from Bukku, so this cannot be sent yet.
                            Run <code>php artisan bukku:ping</code> on the server to see why.
                        </div>
                    @endif

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px;">
                        <div style="grid-column:1 / -1;">
                            <label for="contact_id" style="{{ $labelCss }}">Supplier in Bukku *</label>
                            @if($scan->supplier_name)
                                <p style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:6px;">
                                    Read off the invoice as “<strong>{{ $scan->supplier_name }}</strong>” — pick the matching account.
                                </p>
                            @endif
                            <select name="contact_id" id="contact_id" required style="{{ $inputCss }}">
                                <option value="">Choose a supplier…</option>
                                @foreach($contacts as $contact)
                                    <option value="{{ $contact['id'] }}" @selected((int) old('contact_id') === (int) $contact['id'])>
                                        {{ $contact['name'] ?? $contact['legal_name'] ?? ('Contact #' . $contact['id']) }}
                                    </option>
                                @endforeach
                            </select>
                            <input type="hidden" name="supplier_name" value="{{ $scan->supplier_name }}">
                        </div>

                        <div>
                            <label for="invoice_number" style="{{ $labelCss }}">Invoice number</label>
                            <input type="text" name="invoice_number" id="invoice_number"
                                   value="{{ old('invoice_number', $scan->invoice_number) }}" style="{{ $inputCss }}">
                        </div>

                        <div>
                            <label for="invoice_date" style="{{ $labelCss }}">Invoice date *</label>
                            <input type="date" name="invoice_date" id="invoice_date" required
                                   value="{{ old('invoice_date', $scan->invoice_date?->toDateString() ?? today()->toDateString()) }}"
                                   style="{{ $inputCss }}">
                        </div>

                        <div>
                            <label for="term_id" style="{{ $labelCss }}">Payment terms *</label>
                            <select name="term_id" id="term_id" required style="{{ $inputCss }}">
                                @foreach($terms as $id => $term)
                                    <option value="{{ $id }}" @selected((int) old('term_id', $defaultTermId) === (int) $id)>{{ $term['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; margin:24px 0 4px;">Lines</h3>
                    <p style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:12px; line-height:1.6;">
                        Map a line to a stock product where you can — the account then comes off the
                        product itself, which is what puts it against inventory. Anything left
                        unmapped goes to the account you pick on the row.
                    </p>

                    <div id="lines">
                        @foreach($lines as $i => $line)
                            <div class="line-row" style="border:1px solid hsl(30,15%,88%); border-radius:6px; padding:12px; margin-bottom:10px; background:hsl(30,25%,98%);">
                                <div style="display:grid; grid-template-columns:1fr 80px 110px 110px; gap:8px; align-items:end;">
                                    <div>
                                        <label style="{{ $labelCss }} font-size:12px;">Description</label>
                                        <input type="text" name="lines[{{ $i }}][description]"
                                               value="{{ $line['description'] ?? '' }}" style="{{ $inputCss }}">
                                    </div>
                                    <div>
                                        <label style="{{ $labelCss }} font-size:12px;">Qty</label>
                                        <input type="number" step="0.001" min="0" name="lines[{{ $i }}][quantity]"
                                               value="{{ $line['quantity'] ?? 1 }}" class="ln-qty" style="{{ $inputCss }} font-family:'JetBrains Mono',monospace;">
                                    </div>
                                    <div>
                                        <label style="{{ $labelCss }} font-size:12px;">Unit price</label>
                                        <input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_price]"
                                               value="{{ $line['unit_price'] ?? 0 }}" class="ln-price" style="{{ $inputCss }} font-family:'JetBrains Mono',monospace;">
                                    </div>
                                    <div style="text-align:right; padding-bottom:9px;">
                                        <span class="ln-total" style="font-family:'JetBrains Mono',monospace; font-size:14px; font-weight:600;">0.00</span>
                                    </div>
                                </div>

                                <div style="display:grid; grid-template-columns:1fr 1fr 40px; gap:8px; margin-top:8px; align-items:end;">
                                    <div>
                                        <label style="{{ $labelCss }} font-size:12px;">Stock product (optional)</label>
                                        <select name="lines[{{ $i }}][product_id]" style="{{ $inputCss }}">
                                            <option value="">— not stock —</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product['id'] }}">{{ $product['name'] ?? ('Product #' . $product['id']) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label style="{{ $labelCss }} font-size:12px;">Account <span style="font-weight:400; color:hsl(24,5%,55%);">(unmapped lines)</span></label>
                                        <select name="lines[{{ $i }}][account_id]" required style="{{ $inputCss }}">
                                            @foreach($accounts as $account)
                                                <option value="{{ $account['id'] }}" @selected((int) $account['id'] === $defaultAccount)>
                                                    {{ $account['code'] ?? '' }} {{ $account['name'] ?? ('Account #' . $account['id']) }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="button" onclick="this.closest('.line-row').remove(); recalc();"
                                            title="Remove this line"
                                            style="height:37px; border:1px solid hsl(30,15%,85%); background:white; border-radius:6px; cursor:pointer; color:#b91c1c; font-size:16px;">×</button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" onclick="addLine()"
                            style="background:white; border:1px solid hsl(30,15%,85%); border-radius:6px; padding:8px 14px; font-size:13px; font-weight:500; cursor:pointer;">
                        + Add line
                    </button>

                    @error('lines')
                        <p style="color:#b91c1c; font-size:13px; margin-top:10px;">{{ $message }}</p>
                    @enderror

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:24px; padding-top:20px; border-top:1px solid hsl(30,15%,90%);">
                        <div style="font-size:14px;">
                            Bill total
                            <strong id="grand-total" style="font-family:'JetBrains Mono',monospace; font-size:20px; margin-left:10px;">RM 0.00</strong>
                            @if($scan->extracted['total'] ?? null)
                                <span id="read-total" data-read="{{ $scan->extracted['total'] }}"
                                      style="display:block; font-size:12px; color:hsl(24,5%,45%); margin-top:4px;">
                                    Read off the invoice as RM {{ number_format((float) $scan->extracted['total'], 2) }}
                                </span>
                            @endif
                        </div>
                        <button type="submit" @disabled(empty($contacts))
                                style="background:hsl(20,60%,45%); color:white; padding:11px 22px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                            Send to Bukku
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @unless($posted)
    <script>
        // Line maths, mirrored from the server so the reviewer sees the same
        // total they are about to post. The server recomputes it in decimal
        // and does not trust anything typed here.
        function recalc() {
            let grand = 0;
            document.querySelectorAll('.line-row').forEach(function (row) {
                const qty   = parseFloat(row.querySelector('.ln-qty').value) || 0;
                const price = parseFloat(row.querySelector('.ln-price').value) || 0;
                const total = qty * price;
                row.querySelector('.ln-total').textContent = total.toFixed(2);
                grand += total;
            });
            document.getElementById('grand-total').textContent = 'RM ' + grand.toFixed(2);

            // Flag a drift from what the scan read. A beta should say when it
            // disagrees with itself rather than let a wrong total go quietly.
            const readEl = document.getElementById('read-total');
            if (readEl) {
                const read = parseFloat(readEl.dataset.read);
                const off  = Math.abs(read - grand) > 0.01;
                readEl.style.color = off ? '#b91c1c' : 'hsl(24,5%,45%)';
                readEl.style.fontWeight = off ? '600' : '400';
            }
        }

        function addLine() {
            const rows = document.querySelectorAll('.line-row');
            const last = rows[rows.length - 1];
            if (!last) { return; }

            const clone = last.cloneNode(true);
            // Re-index every field so the new row posts as its own line rather
            // than overwriting the one it was cloned from.
            const next = rows.length;
            clone.querySelectorAll('[name]').forEach(function (field) {
                field.name = field.name.replace(/lines\[\d+\]/, 'lines[' + next + ']');
                if (field.tagName === 'SELECT') { field.selectedIndex = field.name.includes('product_id') ? 0 : field.selectedIndex; }
                else { field.value = field.classList.contains('ln-qty') ? '1' : (field.classList.contains('ln-price') ? '0' : ''); }
            });
            clone.querySelector('.ln-total').textContent = '0.00';
            document.getElementById('lines').appendChild(clone);
            recalc();
        }

        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('ln-qty') || e.target.classList.contains('ln-price')) { recalc(); }
        });

        recalc();
    </script>
    @endunless
</x-app-shell>
