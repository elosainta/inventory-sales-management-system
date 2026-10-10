{{-- Log sales: every dish on the menu with a quantity box starting at 0 and
     its menu price, one date, one Save (the Owner, 2026-09-11). Type how many
     of each were sold; a dish left at 0 is not logged. Each dish sold becomes
     its own sale through LogSale, the same as the pop-up, so stock moves the
     same way. Open orders (off-menu) still go through the pop-up. --}}
@php $sheetErrors = $errors->getBag('sheet'); @endphp
<form method="POST" action="{{ route('sales.sheet') }}" id="sales-sheet"
      style="background:white; border:1px solid hsl(30,15%,90%); border-radius:8px; margin-bottom:32px; overflow:hidden;">
    @csrf
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; padding:14px 18px; background:hsl(30,15%,97%); border-bottom:1px solid hsl(30,15%,90%);">
        <div>
            <h2 style="font-family:'DM Sans',sans-serif; font-size:18px; font-weight:500; margin:0;">Log sales</h2>
            <p style="font-size:13px; color:hsl(24,5%,45%); margin:2px 0 0;">Type how many of each dish were sold. Anything left at 0 is not logged.</p>
        </div>
        <label style="font-size:14px; font-weight:500; display:flex; align-items:center; gap:8px;">
            Date
            <input type="date" name="sale_date" value="{{ old('sale_date', date('Y-m-d')) }}" required
                   style="padding:7px 10px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px;">
        </label>
    </div>

    <div id="sheet-restored" style="display:none; background:hsl(40,60%,96%); border-bottom:1px solid hsl(40,50%,85%); padding:10px 18px; font-size:13px; color:#92400e;">
        Your typed quantities were kept from before. Check them, then save.
    </div>

    @if($sheetErrors->any())
        <div style="background:#fef2f2; border-bottom:1px solid #fecaca; padding:10px 18px; font-size:13px; color:#991b1b;">
            @foreach(array_unique($sheetErrors->all()) as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <div style="max-height:520px; overflow-y:auto;">
        <table class="app-table" style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead style="position:sticky; top:0; background:white; z-index:1;">
                <tr style="border-bottom:1px solid hsl(30,15%,90%);">
                    <th style="text-align:left; padding:10px 18px; font-weight:600;">Dish</th>
                    <th style="text-align:right; padding:10px 18px; font-weight:600; width:110px;">Qty</th>
                    <th style="text-align:right; padding:10px 18px; font-weight:600; width:140px;">Price (RM)</th>
                    <th style="text-align:right; padding:10px 18px; font-weight:600; width:130px;">Revenue</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recipes as $recipe)
                    <tr class="sheet-row" style="border-bottom:1px solid hsl(30,15%,94%);">
                        <td style="padding:8px 18px; font-weight:500;">{{ $recipe->name }}</td>
                        <td style="padding:8px 18px; text-align:right;">
                            <input type="number" name="qty[{{ $recipe->id }}]" value="{{ old('qty.' . $recipe->id, 0) }}"
                                   min="0" step="1" class="sheet-qty" onfocus="this.select()" aria-label="{{ $recipe->name }} quantity"
                                   style="width:74px; padding:6px 8px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; text-align:right; font-family:'JetBrains Mono',monospace;">
                        </td>
                        <td style="padding:8px 18px; text-align:right;">
                            <input type="number" name="price[{{ $recipe->id }}]" value="{{ old('price.' . $recipe->id, number_format((float) $recipe->selling_price, 2, '.', '')) }}"
                                   min="0" step="0.01" class="sheet-price" onfocus="this.select()" aria-label="{{ $recipe->name }} price"
                                   style="width:96px; padding:6px 8px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; text-align:right; font-family:'JetBrains Mono',monospace;">
                        </td>
                        <td class="sheet-rev" style="padding:8px 18px; text-align:right; font-family:'JetBrains Mono',monospace; color:hsl(24,5%,55%);">0.00</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div style="display:flex; justify-content:flex-end; align-items:center; gap:18px; flex-wrap:wrap; padding:14px 18px; border-top:1px solid hsl(30,15%,90%);">
        <span style="font-size:14px; color:hsl(24,5%,45%);"><span id="sheet-count">0 dishes</span> · total
            <strong id="sheet-total" style="font-family:'JetBrains Mono',monospace; font-size:18px; color:hsl(142,50%,35%); margin-left:4px;">RM 0.00</strong></span>
        <button type="submit"
                style="background:hsl(20,60%,45%); color:white; padding:9px 20px; border-radius:6px; font-size:14px; font-weight:500; border:none; cursor:pointer;">Save sales</button>
    </div>
</form>

<script>
    (function () {
        const sheet = document.getElementById('sales-sheet');

        // Preview only - LogSale works the revenue out again on save.
        function paint() {
            let total = 0, dishes = 0;
            sheet.querySelectorAll('.sheet-row').forEach(function (row) {
                const qty   = parseInt(row.querySelector('.sheet-qty').value, 10) || 0;
                const price = parseFloat(row.querySelector('.sheet-price').value) || 0;
                const rev   = qty > 0 ? qty * price : 0;
                const cell  = row.querySelector('.sheet-rev');
                cell.textContent = rev.toFixed(2);
                cell.style.color = qty > 0 ? 'hsl(142,50%,35%)' : 'hsl(24,5%,55%)';
                // A dish being logged stands out among the ones left at 0.
                row.style.background = qty > 0 ? 'hsl(142,40%,97%)' : '';
                if (qty > 0) { total += rev; dishes++; }
            });
            document.getElementById('sheet-total').textContent = 'RM ' + total.toFixed(2);
            document.getElementById('sheet-count').textContent = dishes + (dishes === 1 ? ' dish' : ' dishes');
        }

        // A whole service is a lot of typing, and anything else on this page
        // — logging an open order, saving a discount — reloads it and used to
        // take the lot with it. The draft is kept per date, restored on load,
        // and dropped only when the sheet itself saves.
        const KEY = 'sales-sheet-draft';
        const dateBox = sheet.querySelector('input[name="sale_date"]');

        function store() {
            const draft = { date: dateBox.value, qty: {}, price: {} };
            sheet.querySelectorAll('.sheet-row').forEach(function (row) {
                const qtyBox = row.querySelector('.sheet-qty');
                if ((parseInt(qtyBox.value, 10) || 0) <= 0) return;
                const id = qtyBox.name.replace(/\D+/g, '');
                draft.qty[id] = qtyBox.value;
                draft.price[id] = row.querySelector('.sheet-price').value;
            });
            try { sessionStorage.setItem(KEY, JSON.stringify(draft)); } catch (e) { /* private window */ }
        }

        function restore() {
            let draft;
            try { draft = JSON.parse(sessionStorage.getItem(KEY) || 'null'); } catch (e) { return; }
            if (!draft || !Object.keys(draft.qty || {}).length) return;

            if (draft.date) dateBox.value = draft.date;
            Object.keys(draft.qty).forEach(function (id) {
                const qtyBox = sheet.querySelector('[name="qty[' + id + ']"]');
                const priceBox = sheet.querySelector('[name="price[' + id + ']"]');
                if (qtyBox) qtyBox.value = draft.qty[id];
                if (priceBox && draft.price[id]) priceBox.value = draft.price[id];
            });
            document.getElementById('sheet-restored').style.display = '';
        }

        @if(session('sheet_saved'))
            try { sessionStorage.removeItem(KEY); } catch (e) {}
        @else
            restore();
        @endif

        sheet.addEventListener('input', function () { paint(); store(); });
        paint();

        // Save fired twice on 7 October and logged every dish on the sheet a
        // second time, which the head chef then had to delete by hand. The
        // button is let go again on bfcache restore, or going Back would find
        // a sheet that cannot be saved.
        sheet.addEventListener('submit', function () {
            const button = sheet.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Saving…';
        });
        window.addEventListener('pageshow', function () {
            const button = sheet.querySelector('button[type="submit"]');
            button.disabled = false;
            button.textContent = 'Save sales';
        });
    })();
</script>
