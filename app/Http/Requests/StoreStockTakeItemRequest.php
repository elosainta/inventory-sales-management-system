<?php

namespace App\Http\Requests;

use App\Models\StockTakeItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTakeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    public function rules(): array
    {
        return [
            'section'      => ['required', Rule::in(array_keys(StockTakeItem::SECTIONS))],
            'name'         => [
                'required', 'string', 'max:255',
                Rule::unique('stock_take_items')->where(fn ($q) => $q->where('section', $this->input('section'))),
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
