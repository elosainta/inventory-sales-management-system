{{-- A searchable inventory-item picker, shared by every form that has to choose one.

     A <select> of a hundred ingredients is a swipe, not a search, and the phone
     in a chef's hand is where most of this gets typed. A native <datalist>
     filters as you type — no library, no scroll.

     Use: a visible text input with class="item-picker", list="inventory-options"
     and data-for="<name of the hidden field>", sitting next to a hidden input of
     that name. The typed text is only ever a lookup key; the id is what submits.

     Expects $pickerItems — inventory items with id, name, unit, unit_cost. --}}
@php
    // Built here rather than inline in @json: Blade splits that directive's
    // argument on commas, so an expression containing any would lose its tail.
    $pickerData = $pickerItems->map(fn ($i) => [
        'id'   => (int) $i->id,
        'name' => $i->name,
        'unit' => $i->unit,
        'cost' => (float) ($i->unit_cost ?? 0),
    ])->values();
@endphp
<datalist id="inventory-options"></datalist>
<script>
window.ItemPicker = (function () {
    const items = @json($pickerData);

    const byLabel = new Map();
    const list    = document.getElementById('inventory-options');

    // Two ingredients can share a name. The second one to appear carries its
    // id, so a label always resolves to exactly one item — a label that quietly
    // resolved to the wrong one would post stock to the wrong shelf.
    function register(item) {
        let label = item.name + ' (' + item.unit + ')';
        if (byLabel.has(label)) label += ' #' + item.id;
        item.label = label;
        byLabel.set(label, item);
        list.appendChild(Object.assign(document.createElement('option'), { value: label }));
        return item;
    }

    items.forEach(register);

    // Anything that is not an exact label leaves the hidden id empty and blocks
    // the submit, so a half-typed name can never be saved as "nothing chosen".
    // Blank is allowed through — some pickers are optional and say so themselves.
    function resolve(input) {
        const item   = byLabel.get(input.value.trim()) || null;
        // Scoped to the picker's own form: the stock-take items page repeats one
        // form per row, every one of them with a field of the same name.
        const hidden = input.form
            ? input.form.elements[input.dataset.for]
            : document.getElementsByName(input.dataset.for)[0];
        if (hidden) hidden.value = item ? item.id : '';
        input.setCustomValidity(!input.value.trim() || item ? '' : 'Pick an item from the list.');
        return item;
    }

    // What was typed matches nothing on the shelf. Where the page renders a
    // partials/new-item-panel beside the input, offer to create it there rather
    // than making someone abandon a half-filled form to go and add it. Two
    // characters, because one matches most of the catalogue and the panel
    // would flash open on the way to every word. No panel means the viewer
    // does not hold record-inventory, and nothing is offered.
    function offer(input, matched) {
        const panel = input.parentElement && input.parentElement.querySelector('.new-item');
        if (!panel) return;

        const typed = input.value.trim();
        panel.hidden = !!matched || typed.length < 2;
        panel.querySelector('.new-item-name').textContent = typed;
    }

    // Delegated, so rows added after the page loaded are covered without rebinding.
    document.addEventListener('input', (e) => {
        if (e.target.classList && e.target.classList.contains('item-picker')) offer(e.target, resolve(e.target));
    });

    document.addEventListener('click', async (e) => {
        const button = e.target.closest && e.target.closest('.new-item-add');
        if (!button) return;

        const panel = button.closest('.new-item');
        const input = panel.parentElement.querySelector('.item-picker');
        const name  = input.value.trim();
        if (!name) return;

        button.disabled = true;
        try {
            // Quantity and cost start at zero on purpose. Whatever is being
            // filled in - a purchase, or a scanned invoice once it is sent - is
            // what puts the first of it on the shelf, and LogPurchase writes
            // both. Seeding them here would double the stock.
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

            const item = add(await response.json());
            input.value = item.label;
            resolve(input);
            panel.hidden = true;
            // Its own event rather than a synthetic 'input': the purchase form
            // fills the unit price on input, and would overwrite what was typed
            // with the new item's zero cost.
            input.dispatchEvent(new CustomEvent('item-created', { bubbles: true, detail: item }));
        } catch (error) {
            alert('Could not add that item. ' + error.message);
        } finally {
            button.disabled = false;
        }
    });

    function add(item) {
        items.push(item);
        return register(item);
    }

    return {
        items,
        resolve,
        // An item created after the page loaded, from the panel above.
        // Registering it here rather than in the caller means every other row
        // on the form can pick it too - the case where the same new thing is on
        // two lines.
        add,
        labelFor: (id) => (items.find((i) => i.id === Number(id)) || {}).label || '',
    };
})();
</script>
