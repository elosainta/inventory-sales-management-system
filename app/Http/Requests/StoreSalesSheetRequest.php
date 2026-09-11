<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The Log sales sheet: every dish on the menu with a quantity box, one date,
 * one Save. `qty[recipe_id]` and `price[recipe_id]`.
 *
 * A box left at 0 (or emptied) is a dish that was not sold, and is dropped
 * before validation; a non-number is kept so it fails `integer` with a
 * message instead of vanishing and logging one sale fewer. Array KEYS come
 * from the request, so the surviving ones are re-exposed as `recipe_ids` and
 * checked against the recipes table.
 *
 * Its own error bag, so a mistake on the sheet shows on the sheet and not in
 * the open-order pop-up on the same page.
 */
class StoreSalesSheetRequest extends FormRequest
{
    protected $errorBag = 'sheet';

    public function authorize(): bool
    {
        return Gate::allows('manage-sales');
    }

    protected function prepareForValidation(): void
    {
        $qty = array_filter(
            (array) $this->input('qty', []),
            fn ($q) => $q !== null && $q !== '' && ! (is_numeric($q) && (float) $q == 0),
        );

        $this->merge(['qty' => $qty, 'recipe_ids' => array_keys($qty)]);
    }

    public function rules(): array
    {
        $rules = [
            'sale_date'    => ['required', 'date'],
            'qty'          => ['required', 'array', 'min:1'],
            'qty.*'        => ['required', 'integer', 'min:1', 'max:99999'],
            'recipe_ids'   => ['required', 'array'],
            'recipe_ids.*' => ['integer', 'exists:recipes,id'],
            'price'        => ['array'],
        ];

        // Only a dish that was sold needs a price.
        foreach ((array) $this->input('recipe_ids', []) as $id) {
            $rules["price.{$id}"] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'qty.required' => 'Type how many were sold for at least one dish.',
            'qty.min'      => 'Type how many were sold for at least one dish.',
            'qty.*.integer'=> 'Quantities are whole dishes.',
            'price.*.required' => 'Every dish sold needs a price.',
        ];
    }
}
