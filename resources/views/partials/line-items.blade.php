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
                @can('record-inventory')
                <div class="new-item" hidden style="margin-top:6px; padding:8px; background:hsl(30,15%,96%); border:1px solid hsl(30,15%,88%); border-radius:6px;">
                    <div style="font-size:12px; color:hsl(24,5%,45%); margin-bottom:6px;">
                        Not in inventory yet. Add <strong class="new-item-name"></strong>?
                    </div>
                    <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:6px;">
                        <select class="new-item-category" style="padding:6px 8px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px;">
                            @foreach(\App\Models\InventoryItem::CATEGORIES as $cat)
                                <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                        <select class="new-item-unit" style="padding:6px 8px; border:1px solid hsl(30,15%,85%); border-radius:6px; font-size:13px;">
                            @foreach(\App\Models\InventoryItem::UNITS as $u)
                                <option value="{{ $u }}">{{ $u }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="new-item-add"
                                style="background:hsl(24,45%,42%); color:#fff; border:none; padding:6px 12px; border-radius:6px; font-size:13px; cursor:pointer;">Add</button>
                    </div>
                </div>
                @endcan
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
        offerToCreate(e.target, item);
        recalcTotal();
    });

    // What the chef typed matches nothing on the shelf. Rather than making them
    // leave a half-filled purchase to go and add the ingredient, offer it here.
    // Two characters, because one character matches most of the catalogue and
    // the panel would flash open on the way to every word.
    function offerToCreate(input, matched) {
        const panel = input.parentElement.querySelector('.new-item');
        if (!panel) return;                       // no record-inventory: never offered

        const typed = input.value.trim();
        panel.hidden = !!matched || typed.length < 2;
        panel.querySelector('.new-item-name').textContent = typed;
    }

    document.getElementById('line-items').addEventListener('click', async (e) => {
        const button = e.target.closest('.new-item-add');
        if (!button) return;

        const panel = button.closest('.new-item');
        const input = panel.parentElement.querySelector('.item-picker');
        const name  = input.value.trim();
        if (!name) return;

        button.disabled = true;
        try {
            // Quantity and cost start at zero on purpose: this purchase is what
            // puts the first of it on the shelf, and LogPurchase writes both
            // when the form is submitted. Seeding them from the line would
            // double the stock.
            const response = await fetch('{{ route('inventory.store') }}', {
                method:  'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    name,
                    category:          panel.querySelector('.new-item-category').value,
                    unit:              panel.querySelector('.new-item-unit').value,
                    quantity_on_hand:  0,
                    reorder_threshold: 0,
                    unit_cost:         0,
                }),
            });

            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                throw new Error(body.message || 'Check the name, category and unit.');
            }

            const item = ItemPicker.add(await response.json());
            input.value = item.label;
            ItemPicker.resolve(input);
            panel.hidden = true;
        } catch (error) {
            alert('Could not add that ingredient. ' + error.message);
        } finally {
            button.disabled = false;
        }
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
