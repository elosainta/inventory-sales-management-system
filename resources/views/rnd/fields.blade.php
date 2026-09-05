{{-- The R&D costing sheet form.

     One modal serves both adding and editing — the form's action and _method
     are set by JS. Two copies would mean two line editors on one page fighting
     over the same element ids, and two sets of fields to keep in step.

     Ingredients are picked from inventory, never typed: R&D is done with the
     kitchen's own stock, and the id is what deducts. partials/item-picker is
     included by the page.

     No Blade output appears inside the row template literal below — a backtick
     or a ${ out of Blade would end the literal early and silently kill the rest
     of the script on a page that still renders 200. Anything the rows need in
     the user's language is shaped in $jsStrings and passed through @json. --}}
@php
    $input = 'width:100%; padding:9px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;';
    $label = 'display:block; font-size:12px; font-weight:600; color:hsl(24,10%,35%); margin-bottom:5px;';
    $head  = 'font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.04em; color:hsl(24,5%,50%);';
    $grid  = 'display:grid; grid-template-columns:minmax(0,2.2fr) 100px 64px 100px 88px 26px; gap:8px; align-items:center;';

    // Shaped here rather than inline in @json: Blade splits that directive's
    // argument on commas, so a string containing one would lose its tail.
    $jsStrings = ['search' => __('Type to search inventory…')];
@endphp

<div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:12px; margin-bottom:18px;">
    <div>
        <label style="{{ $label }}">{{ __('Menu name') }}</label>
        <input type="text" name="menu_name" required maxlength="255"
               placeholder="{{ __('The dish this was for') }}" style="{{ $input }}">
    </div>
    <div>
        <label style="{{ $label }}">{{ __('Servings (pax)') }}</label>
        <input type="number" name="serving_size" value="1" min="1" max="999" required style="{{ $input }}">
    </div>
    <div>
        <label style="{{ $label }}">{{ __('Date') }}</label>
        <input type="date" name="purchased_on" value="{{ now()->toDateString() }}" required style="{{ $input }}">
    </div>
</div>

{{-- The costing table --}}
<div style="border-top:1px solid hsl(30,15%,90%); padding-top:14px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <h3 style="font-size:15px; font-weight:600; margin:0;">{{ __('What it was made with') }}</h3>
        <button type="button" onclick="addRndLine()"
                style="background:hsl(30,15%,92%); border:1px solid hsl(30,15%,85%); padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer;">
            + {{ __('Add ingredient') }}
        </button>
    </div>

    <div style="{{ $grid }} margin-bottom:6px;">
        <div style="{{ $head }}">{{ __('Ingredient') }}</div>
        <div style="{{ $head }}">{{ __('Price per unit') }}</div>
        <div style="{{ $head }}">{{ __('Unit') }}</div>
        <div style="{{ $head }}">{{ __('Qty used') }}</div>
        <div style="{{ $head }} text-align:right;">{{ __('Total') }}</div>
        <div></div>
    </div>

    <div id="rnd-lines"></div>
</div>

{{-- The bottom of the sheet, live as you type --}}
<div style="margin-top:16px; background:hsl(30,15%,96%); border-radius:8px; padding:14px 16px;">
    @php $row = 'display:flex; justify-content:space-between; align-items:center; gap:12px; padding:5px 0;'; @endphp

    <div style="{{ $row }}">
        <span style="font-size:13px; color:hsl(24,5%,40%);">{{ __('Total') }}</span>
        <span id="rnd-total" style="font-family:'JetBrains Mono',monospace; font-size:14px;">RM 0.00</span>
    </div>

    <div style="{{ $row }}">
        <label style="font-size:13px; color:hsl(24,5%,40%); display:flex; align-items:center; gap:8px;">
            {{ __('Miscellaneous') }}
            <input type="number" name="misc_percent" id="rnd-misc-percent" value="30" min="0" max="100" step="0.01"
                   oninput="recalcRnd()"
                   style="width:66px; padding:4px 6px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:13px;">%
        </label>
        <span id="rnd-misc-amount" style="font-family:'JetBrains Mono',monospace; font-size:14px;">RM 0.00</span>
    </div>

    <div style="{{ $row }} border-top:1px solid hsl(30,15%,88%); margin-top:4px; padding-top:9px;">
        <span style="font-size:13px; font-weight:700;">{{ __('Grand total') }}</span>
        <span id="rnd-grand" style="font-family:'JetBrains Mono',monospace; font-size:16px; font-weight:700;">RM 0.00</span>
    </div>

    <div style="{{ $row }}">
        <label for="rnd-selling-price" style="font-size:13px; color:hsl(24,5%,40%);">{{ __('Selling price') }} <span style="color:hsl(24,5%,55%);">({{ __('optional') }})</span></label>
        <input type="number" name="selling_price" id="rnd-selling-price" step="0.01" min="0"
               oninput="recalcRnd()" placeholder="0.00"
               style="width:110px; padding:5px 8px; border:1px solid hsl(30,15%,85%); border-radius:5px; font-size:14px; text-align:right; font-family:'JetBrains Mono',monospace;">
    </div>

    <div style="{{ $row }} border-top:1px solid hsl(30,15%,88%); margin-top:4px; padding-top:9px;">
        <span style="font-size:13px; font-weight:700;">{{ __('Profit per dish') }}</span>
        <span id="rnd-profit" style="font-family:'JetBrains Mono',monospace; font-size:16px; font-weight:700;">—</span>
    </div>
</div>

<div style="margin-top:14px;">
    <label style="{{ $label }}">{{ __('Remark') }} <span style="font-weight:400; color:hsl(24,5%,55%);">({{ __('optional') }})</span></label>
    <textarea name="remark" rows="2" maxlength="2000" placeholder="{{ __('How it went') }}" style="{{ $input }} resize:vertical;"></textarea>
</div>

<script>
(function () {
    const strings = @json($jsStrings);
    const cell    = 'width:100%; padding:7px 9px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px; box-sizing:border-box;';
    const grid    = @json($grid);

    let counter = 0;

    window.addRndLine = function (preset) {
        const idx  = counter++;
        const name = 'lines[' + idx + '][inventory_item_id]';
        const row  = document.createElement('div');
        row.className = 'rnd-line';
        row.style.cssText = grid + ' margin-bottom:8px;';
        row.innerHTML =
            '<div>' +
              '<input type="text" class="item-picker" list="inventory-options" data-for="' + name + '"' +
                    ' placeholder="' + strings.search + '" required autocomplete="off" style="' + cell + '">' +
              '<input type="hidden" name="' + name + '">' +
            '</div>' +
            '<input type="number" name="lines[' + idx + '][unit_price]" step="0.01" min="0" required style="' + cell + '">' +
            '<div class="rnd-unit" style="font-size:12px; color:hsl(24,5%,50%);">&mdash;</div>' +
            '<input type="number" name="lines[' + idx + '][quantity]" step="0.0001" min="0.0001" required style="' + cell + '">' +
            '<div class="rnd-line-total" style="text-align:right; font-family:\'JetBrains Mono\',monospace; font-size:13px;">RM 0.00</div>' +
            '<button type="button" class="rnd-remove" style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); font-size:17px; padding:0;">&times;</button>';

        document.getElementById('rnd-lines').appendChild(row);

        if (preset) {
            const picker = row.querySelector('.item-picker');
            // The label if the ingredient is still on the shelf, otherwise the
            // name snapshotted on the line — a deleted ingredient must not
            // silently blank the row it is on.
            picker.value = ItemPicker.labelFor(preset.inventory_item_id) || preset.item;
            ItemPicker.resolve(picker);
            row.querySelector('[name$="[unit_price]"]').value = preset.unit_price;
            row.querySelector('[name$="[quantity]"]').value   = trimZeros(preset.quantity);
            row.querySelector('.rnd-unit').textContent        = preset.unit || '—';
        }

        recalcRnd();
        return row;
    };

    function trimZeros(value) {
        return String(parseFloat(value));
    }

    const container = document.getElementById('rnd-lines');

    container.addEventListener('click', (e) => {
        if (!e.target.classList.contains('rnd-remove')) return;
        // Never leave the sheet with nothing on it — the request rejects that
        // anyway, and an empty editor gives no way back.
        if (container.querySelectorAll('.rnd-line').length > 1) e.target.parentElement.remove();
        recalcRnd();
    });

    container.addEventListener('input', (e) => {
        if (e.target.classList.contains('item-picker')) {
            const item = ItemPicker.resolve(e.target);
            const row  = e.target.closest('.rnd-line');
            if (item) {
                // Its last known cost, as a starting point. Editable — a trial
                // is often bought at a market price the shelf has not seen yet.
                row.querySelector('[name$="[unit_price]"]').value = item.cost;
                row.querySelector('.rnd-unit').textContent        = item.unit;
            }
        }
        recalcRnd();
    });

    window.recalcRnd = function () {
        let total = 0;

        container.querySelectorAll('.rnd-line').forEach((row) => {
            const price = parseFloat(row.querySelector('[name$="[unit_price]"]').value) || 0;
            const qty   = parseFloat(row.querySelector('[name$="[quantity]"]').value) || 0;
            const line  = price * qty;
            total += line;
            row.querySelector('.rnd-line-total').textContent = 'RM ' + line.toFixed(2);
        });

        const miscPct = parseFloat(document.getElementById('rnd-misc-percent').value) || 0;
        const misc    = total * miscPct / 100;
        const grand   = total + misc;
        const selling = document.getElementById('rnd-selling-price').value;

        document.getElementById('rnd-total').textContent       = 'RM ' + total.toFixed(2);
        document.getElementById('rnd-misc-amount').textContent = 'RM ' + misc.toFixed(2);
        document.getElementById('rnd-grand').textContent       = 'RM ' + grand.toFixed(2);

        const profit = document.getElementById('rnd-profit');
        if (selling === '') {
            profit.textContent = '—';
            profit.style.color = 'inherit';
        } else {
            const value = parseFloat(selling) - grand;
            profit.textContent = 'RM ' + value.toFixed(2);
            profit.style.color = value < 0 ? 'hsl(0,55%,45%)' : 'hsl(145,45%,32%)';
        }
    };

    window.resetRndLines = function () {
        container.innerHTML = '';
        counter = 0;
    };
})();
</script>
