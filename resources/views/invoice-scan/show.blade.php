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

    {{-- The same paper invoice scanned more than once. A flag, not a lock:
         see InvoiceScan::possibleDuplicates(). Red when one of them is already
         a bill, because sending this too would put a second one on the books. --}}
    @if($duplicates->isNotEmpty())
        @php $alreadyBilled = $duplicates->first(fn ($d) => $d['scan']->isPosted()); @endphp
        <div style="background:{{ $alreadyBilled ? '#fef2f2' : '#fffbeb' }}; border:1px solid {{ $alreadyBilled ? '#fecaca' : '#fde68a' }}; border-left:3px solid {{ $alreadyBilled ? '#b91c1c' : '#d97706' }}; border-radius:8px; padding:14px 18px; margin-bottom:24px; font-size:13px; color:{{ $alreadyBilled ? '#991b1b' : '#92400e' }}; line-height:1.6;">
            <strong>
                @if($alreadyBilled && ! $posted)
                    Possible duplicate — this invoice may already be in Bukku as {{ $alreadyBilled['scan']->bukku_number }}. Sending it again makes a second bill.
                @else
                    Possible duplicate — this invoice looks like one scanned before.
                @endif
            </strong>
            <ul style="margin:6px 0 0; padding-left:18px;">
                @foreach($duplicates as $dup)
                    <li>
                        <a href="{{ route('invoice-scan.show', $dup['scan']) }}" style="color:inherit; font-weight:600;">Scan #{{ $dup['scan']->id }}</a>
                        — {{ $dup['scan']->supplier_name ?: 'supplier not read' }},
                        {{ $dup['scan']->invoice_number ?: 'no number' }},
                        scanned {{ $dup['scan']->created_at->format('M d, Y') }} by {{ $dup['scan']->user?->name ?? 'a former team member' }}.
                        {{ $dup['scan']->isPosted() ? 'Sent as ' . $dup['scan']->bukku_number . '.' : 'Not sent.' }}
                        <span style="opacity:0.75;">({{ $dup['reason'] }})</span>
                    </li>
                @endforeach
            </ul>
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

                    @if($scan->purchase)
                        <p style="font-size:13px; margin:0 0 16px;">
                            Added to stock as
                            <a href="{{ route('purchases.index') }}" style="color:hsl(20,60%,45%); font-weight:500;">purchase #{{ $scan->purchase_id }}</a>,
                            {{ $scan->purchase->lines()->count() }} {{ \Illuminate\Support\Str::plural('line', $scan->purchase->lines()->count()) }}.
                        </p>
                    @else
                        <p style="font-size:13px; color:hsl(24,5%,45%); margin:0 0 16px;">
                            No lines were matched to your inventory, so nothing was added to stock.
                        </p>
                    @endif

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
                @php $billedTwin = $duplicates->first(fn ($d) => $d['scan']->isPosted()); @endphp
                <form method="POST" action="{{ route('invoice-scan.push', $scan) }}"
                      onsubmit="{{ $billedTwin ? "if (! confirm(" . json_encode('This invoice may already be in Bukku as ' . $billedTwin['scan']->bukku_number . '. Send it anyway and make a second bill?') . ")) return false;" : '' }} this.querySelector('button[type=submit]').disabled=true; this.querySelector('button[type=submit]').textContent='Sending…';"
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

                    <h3 style="font-family:'DM Sans',sans-serif; font-size:16px; font-weight:500; margin:24px 0 4px;">What was read off the invoice</h3>
                    <p style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:12px; line-height:1.6;">
                        Untick a row to leave it off the bill. Match a row to something on your shelf and the
                        system remembers that wording &mdash; the next invoice calling it the same thing arrives
                        already matched. Leave the match empty for anything you do not stock, like a delivery
                        fee or an item that is new to the kitchen.
                    </p>
                    <p style="background:hsl(30,25%,97%); border:1px solid hsl(30,15%,88%); border-radius:6px; padding:10px 12px; font-size:12px; color:hsl(24,5%,35%); margin-bottom:12px; line-height:1.6;">
                        <strong>Sending also adds the matched lines to your stock.</strong> Every row you have
                        matched goes in as a purchase, raising the quantity on hand and setting the unit cost to
                        what you paid here. Unmatched rows go on the bill only. <strong>Do not also key this
                        delivery in under Purchases</strong> &mdash; that would count the stock twice.
                    </p>

                    {{-- Wide by nature: nine things belong on one line of a bill. It
                         scrolls inside itself rather than pushing the page sideways. --}}
                    <div style="overflow-x:auto; border:1px solid hsl(30,15%,88%); border-radius:8px;">
                        <table style="width:100%; min-width:760px; border-collapse:collapse; font-size:13px;">
                            <thead>
                                <tr style="background:hsl(30,25%,97%); text-align:left;">
                                    <th style="padding:9px 10px; width:34px;" title="Tick to put this line on the bill">&check;</th>
                                    <th style="padding:9px 10px; min-width:200px;">Read as</th>
                                    <th style="padding:9px 10px; width:82px;">Qty</th>
                                    <th style="padding:9px 10px; width:100px;">Unit price</th>
                                    <th style="padding:9px 10px; width:92px; text-align:right;">Amount</th>
                                    <th style="padding:9px 10px; min-width:215px;">Your inventory item</th>
                                    <th style="padding:9px 10px; width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="lines">
                                @foreach($lines as $i => $line)
                                    @php
                                        // What the kitchen has already been taught this wording means.
                                        // old() wins, so a correction survives a failed submit.
                                        $normalised = \App\Models\InvoiceItemAlias::normalise($line['description'] ?? '');
                                        $matchedId  = old("lines.{$i}.inventory_item_id", $aliasMatches[$normalised] ?? null);
                                    @endphp
                                    <tr class="line-row" style="border-top:1px solid hsl(30,15%,90%);">
                                        <td style="padding:8px 10px; vertical-align:top;">
                                            <input type="hidden" name="lines[{{ $i }}][include]" value="0">
                                            <input type="checkbox" name="lines[{{ $i }}][include]" value="1"
                                                   @checked(old("lines.{$i}.include", '1') === '1')
                                                   style="width:17px; height:17px; cursor:pointer; margin-top:9px;">
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <input type="text" name="lines[{{ $i }}][description]"
                                                   value="{{ $line['description'] ?? '' }}" style="{{ $inputCss }}">
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <input type="number" step="0.001" min="0" name="lines[{{ $i }}][quantity]"
                                                   value="{{ $line['quantity'] ?? 1 }}" class="ln-qty"
                                                   style="{{ $inputCss }} font-family:'JetBrains Mono',monospace;">
                                        </td>
                                        <td style="padding:8px 10px;">
                                            <input type="number" step="0.01" min="0" name="lines[{{ $i }}][unit_price]"
                                                   value="{{ $line['unit_price'] ?? 0 }}" class="ln-price"
                                                   style="{{ $inputCss }} font-family:'JetBrains Mono',monospace;">
                                        </td>
                                        <td style="padding:8px 10px; text-align:right; vertical-align:middle;">
                                            <span class="ln-total" style="font-family:'JetBrains Mono',monospace; font-weight:600;">0.00</span>
                                        </td>
                                        <td style="padding:8px 10px;">
                                            {{-- The search box: a native datalist typeahead over the whole
                                                 shelf, the same picker Purchases uses — and the same
                                                 "not in inventory yet, add it?" panel under it, so a line
                                                 for something new can be matched without leaving a
                                                 half-reviewed bill. --}}
                                            <input type="text" class="item-picker ln-match" list="inventory-options"
                                                   data-for="lines[{{ $i }}][inventory_item_id]"
                                                   placeholder="Search your inventory&hellip;" style="{{ $inputCss }}">
                                            <input type="hidden" name="lines[{{ $i }}][inventory_item_id]" value="{{ $matchedId }}">
                                            @include('partials.new-item-panel')
                                            <span class="ln-match-note" style="display:block; font-size:11px; margin-top:4px;"></span>
                                        </td>
                                        <td style="padding:8px 10px; text-align:center;">
                                            <button type="button" onclick="this.closest('.line-row').remove(); recalc();"
                                                    title="Remove this line"
                                                    style="border:1px solid hsl(30,15%,85%); background:white; border-radius:6px; cursor:pointer; color:#b91c1c; padding:6px 9px;">&times;</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <button type="button" onclick="addLine()"
                            style="margin-top:10px; background:white; border:1px solid hsl(30,15%,85%); border-radius:6px; padding:8px 14px; font-size:13px; font-weight:500; cursor:pointer;">
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
                        @can('send-invoice-scan')
                            <button type="submit" @disabled(empty($contacts))
                                    style="background:hsl(20,60%,45%); color:white; padding:11px 22px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">
                                Send to Bukku
                            </button>
                        @else
                            {{-- Anyone may scan and read; a manager posts the bill. Said
                                 here rather than left as a missing button, and said with
                                 the second half — nothing typed on this screen is stored
                                 until Send, so the matches go with whoever presses it. --}}
                            <p style="max-width:320px; text-align:right; font-size:13px; color:hsl(24,5%,45%); line-height:1.6; margin:0;">
                                <strong style="color:hsl(24,10%,25%);">A manager sends this to Bukku.</strong><br>
                                The scan is saved and they can open it from the Invoice Scan
                                list — anything you change here is not.
                            </p>
                        @endcan
                    </div>
                </form>
            @endif
        </div>
    </div>

    @unless($posted)
        {{-- Defines window.ItemPicker and the <datalist> the match boxes read.
             Must come before the script below, which calls labelFor(). --}}
        @include('partials.item-picker', ['pickerItems' => $pickerItems])
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
                const on    = row.querySelector('input[type=checkbox]').checked;

                // An unticked row is not billed, so it must not be counted. The
                // whole row dims rather than vanishing: a rejected line is still
                // something the reviewer needs to see they rejected.
                row.querySelector('.ln-total').textContent = total.toFixed(2);
                row.style.opacity = on ? '1' : '0.42';
                if (on) { grand += total; }

                syncMatch(row);
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

        // Show what a row is matched to, and say plainly whether anything will
        // be learned from it. A silent match is one nobody checks.
        function syncMatch(row) {
            const box    = row.querySelector('.ln-match');
            const hidden = row.querySelector('input[name$="[inventory_item_id]"]');
            const note   = row.querySelector('.ln-match-note');
            if (!box || !hidden || !note) { return; }

            // Pre-fill from the id the server resolved out of the dictionary.
            // labelFor() rather than a server-rendered label, so the picker
            // stays the single source of truth for how an item is written.
            if (hidden.value && !box.value) { box.value = window.ItemPicker.labelFor(hidden.value); }

            const matched = !!hidden.value;
            note.textContent = matched
                ? 'Matched \u2014 this wording will be remembered.'
                : 'No match \u2014 nothing will be remembered for this wording.';
            note.style.color = matched ? 'hsl(140,40%,30%)' : 'hsl(24,5%,55%)';
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

                if (field.type === 'checkbox') {
                    // A new row starts billed. Clearing its value would post
                    // "on" instead of "1" and quietly fail the include filter.
                    field.checked = true;
                } else {
                    field.value = field.classList.contains('ln-qty') ? '1'
                        : (field.classList.contains('ln-price') ? '0' : '');
                }
            });

            // data-for points at the hidden field by name, so it has to move
            // with it — otherwise every new row writes its match into row 0.
            clone.querySelectorAll('[data-for]').forEach(function (field) {
                field.dataset.for = field.dataset.for.replace(/lines\[\d+\]/, 'lines[' + next + ']');
                field.value = '';
            });

            clone.querySelector('.ln-total').textContent = '0.00';
            // The row it was cloned from may have had its "add it?" panel open.
            const panel = clone.querySelector('.new-item');
            if (panel) { panel.hidden = true; }
            document.getElementById('lines').appendChild(clone);
            recalc();
        }

        document.addEventListener('input', function (e) {
            const t = e.target;
            if (t.classList.contains('ln-qty') || t.classList.contains('ln-price') || t.classList.contains('ln-match')) {
                recalc();
            }
        });

        // Ticking or rejecting changes what gets billed, so it changes the total.
        document.addEventListener('change', function (e) {
            if (e.target.type === 'checkbox') { recalc(); }
        });

        // A line matched to an item just created from its panel: the note under
        // it has to say "matched" now, not on the next keystroke.
        document.addEventListener('item-created', recalc);

        recalc();
    </script>
    @endunless
</x-app-shell>
