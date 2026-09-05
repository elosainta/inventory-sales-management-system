<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            // The finished stock a batch of this recipe creates. Null keeps the
            // recipe on the old path, where a sale deducts its raw ingredients.
            'output_inventory_item_id' => ['nullable', 'exists:inventory_items,id'],
            'serving_size'  => ['required', 'integer', 'min:1'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'misc_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'ingredients'   => ['required', 'array', 'min:1'],
            'ingredients.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'ingredients.*.quantity'          => ['required', 'numeric', 'min:0.0001'],
        ];
    }
}