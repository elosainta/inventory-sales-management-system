<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Writing an approved R&D trial up as a real dish.
 *
 * The ingredient and its quantity are NOT accepted here — they come off the
 * R&D entry, which is the whole point: the recipe is what the trial actually
 * used. Only the things a trial does not know are asked for.
 */
class StoreRndRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'serving_size'  => ['required', 'integer', 'min:1'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'misc_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
