<?php

namespace App\Http\Requests;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Editing an existing item without manage-inventory is the part timer's
        // "key it in" case: the form disables everything but the quantity, so
        // nothing else arrives and the required rules below would reject a
        // legitimate submission. Creating a new item still needs the full set —
        // an item has to start somewhere.
        if ($this->route('inventoryItem') && ! \Illuminate\Support\Facades\Gate::allows('manage-inventory')) {
            return ['quantity_on_hand' => ['required', 'numeric', 'min:0']];
        }

        return [
            'name'               => ['required', 'string', 'max:255'],
            'category'           => ['required', 'string', \Illuminate\Validation\Rule::in(InventoryItem::CATEGORIES)],
            'unit'               => ['required', 'string', \Illuminate\Validation\Rule::in(InventoryItem::UNITS)],
            'quantity_on_hand'   => ['required', 'numeric', 'min:0'],
            'reorder_threshold'  => ['required', 'numeric', 'min:0'],
            'unit_cost'          => ['required', 'numeric', 'min:0'],
            'pack_size'          => ['nullable', 'numeric', 'min:1'],
            // The id of a product in Bukku, not a local row — no exists rule
            // to write, because the catalogue it belongs to lives over HTTP.
            // Only reachable with manage-inventory: the early return above
            // narrows a part timer to quantity alone, and update() intersects
            // on that same key, so this cannot arrive from a hand-rolled post.
            'bukku_product_id'   => ['nullable', 'integer', 'min:1'],
        ];
    }
}