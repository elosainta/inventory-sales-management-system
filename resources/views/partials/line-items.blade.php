{{-- The purchase line-item editor. Purchases and Market purchases each carried a
     byte-identical copy of this; one copy means the next fix lands on both.

     Expects $items — the inventory items that can be bought. --}}
@include('partials.item-picker', ['pickerItems' => $items])

<div style="border-top:1px solid hsl(30,15%,90%); padding-top:16px; margin-bottom:16px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <h3 style="font-size:15px; font-weight:600;">Line Items</h3>
        <button type="button" onclick="addLineRow()"
                style="background:hsl(30,15%,92%); border:1px solid hsl(30,15%,85%); padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer;">
            + Add Item
        </button>
    </div>
    <div id="line-items"></div>
</div>

<div style="display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:hsl(30,15%,96%); border-radius:6px; margin-bottom:16px;">
    <span style="font-size:13px; color:hsl(24,5%,45%);">Total preview</span>
    <span id="total-preview" style="font-weight:600; font-size:16px;">RM 0.00</span>
</div>

<script>
    let lineCounter = 0;

    function addLineRow() {
        const idx  = lineCounter++;
        const name = 'lines[' + idx + '][inventory_item_id]';
        const row  = document.createElement('div');
        row.className = 'line-row';
        row.style.cssText = 'display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; align-items:end; margin-bottom:8px;';
        row.innerHTML = `
            <div>
                <label style="display:block; font-size:12px; color:hsl(24,5%,45%); margin-bottom:2px;">Ingredient</label>
                <input type="text" class="item-picker" list="inventory-options" data-for="${name}"
                       placeholder="Type to search…" required autocomplete="off"
                       style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
                <input type="hidden" name="${name}">
                @include('partials.new-item-panel')
            </div>
            <div>
                <label style="display:block; font-size:12px; color:hsl(24,5%,45%); margin-bottom:2px;">Quantity</label>
                <input type="number" name="lines[${idx}][quantity]" step="0.01" min="0.01" required
                       oninput="recalcTotal()"
                       style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block; font-size:12px; color:hsl(24,5%,45%); margin-bottom:2px;">Unit Price (RM)</label>
                <input type="number" name="lines[${idx}][unit_price]" step="0.01" min="0" required
                       oninput="recalcTotal()"
                       style="width:100%; padding:8px 12px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:14px; box-sizing:border-box;">
            </div>
            <button type="button" onclick="this.parentElement.remove(); recalcTotal();"
                    style="background:none; border:none; cursor:pointer; color:hsl(0,70%,50%); font-size:18px; padding:8px;">×</button>
        `;
        document.getElementById('line-items').appendChild(row);
    }

    // Picking an ingredient fills in its last known unit price, same as the old
    // dropdown did. Only a whole match fills it — a half-typed name leaves the
    // price alone rather than blanking what someone already keyed in.
    document.getElementById('line-items').addEventListener('input', (e) => {
        if (!e.target.classList.contains('item-picker')) return;
        const item  = ItemPicker.resolve(e.target);
        const price = document.getElementsByName(e.target.dataset.for.replace('[inventory_item_id]', '[unit_price]'))[0];
        if (item && price) price.value = item.cost;
        recalcTotal();
    });

    function recalcTotal() {
        let total = 0;
        document.querySelectorAll('.line-row').forEach((row) => {
            const qty   = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
            const price = parseFloat(row.querySelector('input[name*="[unit_price]"]').value) || 0;
            total += qty * price;
        });
        document.getElementById('total-preview').textContent = 'RM ' + total.toFixed(2);
    }

    document.addEventListener('DOMContentLoaded', () => addLineRow());
</script>
