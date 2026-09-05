<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A blank discount field arrives as '' (or null once
     * ConvertEmptyStringsToNull has run) and `discount` is NOT NULL DEFAULT 0,
     * so writing it straight through is a SQL error. filled() catches both
     * shapes without leaning on that middleware being registered. Store and
     * update share this request, so one guard covers both paths.
     */
    protected function prepareForValidation(): void
    {
        $open = $this->boolean('is_open_order');

        $this->merge([
            'discount' => $this->filled('discount') ? $this->input('discount') : 0,
            // An open order is off-menu food with a typed-in name and no
            // recipe. Blanking the other side here is what makes editing a
            // sale across that boundary drop the stale value instead of
            // silently keeping it.
            'recipe_id' => $open ? null : $this->input('recipe_id'),
            'item_name' => $open ? $this->input('item_name') : null,
        ]);
    }

    public function rules(): array
    {
        return [
            // An open order is off-menu food keyed in by hand: no recipe, so
            // no ingredient deduction. Every other sale still needs a recipe.
            'recipe_id'     => ['required_unless:is_open_order,1', 'nullable', 'exists:recipes,id'],
            'item_name'     => ['required_if:is_open_order,1', 'nullable', 'string', 'max:255'],
            'qty_sold'      => ['required', 'integer', 'min:1'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'sale_date'     => ['required', 'date'],
            // Staff extra order from the kitchen, usually discounted. Both fields
            // always arrive (the form pairs the checkbox with a hidden 0) so
            // unticking on edit clears the flag instead of leaving it set.
            'is_open_order' => ['required', 'boolean'],
            'discount'      => ['nullable', 'numeric', 'min:0'],
            // Optional photo(s) of the sale — receipt, POS screen, plate. Images
            // only; max: is in KB, so 25600 = 25 MB per file.
            'photos'        => ['nullable', 'array'],
            'photos.*'      => ['file', 'mimes:jpg,jpeg,png,webp,gif,heic', 'max:25600'],
        ];
    }
}