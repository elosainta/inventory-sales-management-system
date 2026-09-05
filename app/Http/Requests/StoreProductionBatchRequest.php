<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    /**
     * The chef says which dishes were made and how many of each. Nothing about
     * ingredients is submitted — the recipe is the formula, and LogProduction
     * reads it server-side. A posted ingredient list would let anyone holding
     * `manage-production` (every chef) write any quantity they liked straight
     * into inventory.
     *
     * The form renders a quantity box against every recipe, so `quantities`
     * arrives as [recipe_id => qty] with most entries blank. Blanks are
     * stripped here, and the surviving keys are validated as real recipe ids —
     * they come from the request, so they cannot be trusted as they stand.
     */
    protected function prepareForValidation(): void
    {
        // Blank means the dish was not cooked, and so does 0 — someone tabbing
        // down the sheet types a zero as readily as they leave it empty, and an
        // error on "I made none of that" would be nonsense. A non-numeric value
        // is deliberately NOT stripped: it survives to fail `integer` with a
        // message, rather than vanishing and quietly logging one dish fewer.
        $quantities = array_filter(
            (array) $this->input('quantities', []),
            fn ($qty) => $qty !== null && $qty !== '' && ! (is_numeric($qty) && (float) $qty == 0),
        );

        $this->merge([
            'quantities' => $quantities,
            'recipe_ids' => array_keys($quantities),
        ]);
    }

    public function rules(): array
    {
        return [
            'quantities'      => ['required', 'array', 'min:1'],
            'quantities.*'    => ['required', 'integer', 'min:1', 'max:99999999'],
            'recipe_ids'      => ['required', 'array', 'min:1'],
            'recipe_ids.*'    => ['required', 'integer', 'exists:recipes,id'],
            'produced_by'     => ['required', 'string', 'max:100'],
            'production_date' => ['required', 'date'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'quantities.required'   => 'Enter how many were made against at least one dish.',
            'quantities.min'        => 'Enter how many were made against at least one dish.',
            'recipe_ids.required'   => 'Enter how many were made against at least one dish.',
            'quantities.*.integer'  => 'How many were made must be a whole number — 1, 2, 3 and so on.',
            'quantities.*.min'      => 'How many were made must be a whole number — 1, 2, 3 and so on.',
            'quantities.*.max'      => 'That is more dishes than the kitchen can have made.',
            'recipe_ids.*.exists'   => 'One of the dishes no longer exists — reload the page and try again.',
            'recipe_ids.*.integer'  => 'One of the dishes was not recognised — reload the page and try again.',
        ];
    }
}
