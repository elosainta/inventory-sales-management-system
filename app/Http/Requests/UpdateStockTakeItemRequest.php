<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockTakeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    public function rules(): array
    {
        // The section is fixed on update; only name + unit change. Uniqueness is
        // scoped to the item's own section, ignoring itself.
        $item = $this->route('stockTakeItem');

        return [
            'name'         => [
                'required', 'string', 'max:255',
                Rule::unique('stock_take_items')
                    ->where(fn ($q) => $q->where('section', $item->section))
                    ->ignore($item->id),
            ],
            'default_unit' => ['nullable', 'string', 'max:50'],
            'inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'That item is already in this section.',
        ];
    }
}
