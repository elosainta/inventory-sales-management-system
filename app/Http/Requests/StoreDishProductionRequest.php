<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * One dish logged from its own page, with what each ingredient actually took.
 *
 * `used` is keyed by inventory item id and may carry ONLY this recipe's own
 * ingredients - `array:<ids>` refuses any other key before the controller
 * runs, and LogProduction checks again, because a typed-in amount goes
 * straight off stock. Every ingredient needs an amount: a box left empty is a
 * question, not a zero, and 0 has to be typed to mean "none used".
 */
class StoreDishProductionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage-production');
    }

    public function rules(): array
    {
        $ids = array_keys($this->route('recipe')->consumptionFor(1));

        $rules = [
            'quantity_produced' => ['required', 'integer', 'min:1', 'max:99999'],
            'used'              => ['required', 'array:' . implode(',', $ids)],
            'produced_by'       => ['required', 'string', 'max:100'],
            'production_date'   => ['required', 'date'],
            'notes'             => ['nullable', 'string', 'max:500'],
        ];

        foreach ($ids as $id) {
            $rules["used.{$id}"] = ['required', 'numeric', 'min:0', 'max:99999'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'used.array'      => "Only this dish's own ingredients can be taken off the shelf here.",
            'used.*.required' => 'Every ingredient needs an amount - type 0 if none was used.',
        ];
    }
}
