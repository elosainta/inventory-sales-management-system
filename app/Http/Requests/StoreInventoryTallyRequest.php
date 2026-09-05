<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryTallyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    public function rules(): array
    {
        return [
            'counted_on' => ['required', 'date'],
            'note'       => ['nullable', 'string', 'max:2000'],

            'lines'                     => ['nullable', 'array'],
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'lines.*.item_name'         => ['nullable', 'string', 'max:255'],
            'lines.*.unit'              => ['nullable', 'string', 'max:50'],
            'lines.*.category'          => ['nullable', 'string', 'max:100'],
            'lines.*.counted_quantity'  => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
