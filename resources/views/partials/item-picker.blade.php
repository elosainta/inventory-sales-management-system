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

    // Delegated, so rows added after the page loaded are covered without rebinding.
    document.addEventListener('input', (e) => {
        if (e.target.classList && e.target.classList.contains('item-picker')) resolve(e.target);
    });

    return {
        items,
        resolve,
        // An ingredient created after the page loaded — see the "not in
        // inventory" panel in partials/line-items. Registering it here rather
        // than in the caller means every other row on the form can pick it too,
        // which is the case where someone buys the same new thing twice.
        add(item) {
            items.push(item);
            return register(item);
        },
        labelFor: (id) => (items.find((i) => i.id === Number(id)) || {}).label || '',
    };
})();
</script>
