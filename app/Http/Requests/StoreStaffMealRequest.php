<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStaffMealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    /**
     * A staff meal is a costing sheet: a header and one or more ingredient lines.
     *
     * `item` and `unit` are NOT accepted on a line — they are read off the
     * picked inventory item and snapshotted by the controller, so a line can
     * never read as one ingredient while naming another.
     *
     * `created_by` is set by the controller for the same kind of reason: a
     * posted one would let anybody file lunch under someone else's name.
     *
     * The numeric ceilings are the DECIMAL columns', so an over-long figure
     * fails validation instead of erroring out of the database.
     */
    public function rules(): array
    {
        return [
            'meal_date'    => ['required', 'date'],
            'dish'         => ['required', 'string', 'max:255'],
            'misc_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'remark'       => ['nullable', 'string', 'max:2000'],

            'lines'                     => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'lines.*.quantity'          => ['required', 'numeric', 'min:0.0001', 'max:99999999'],
            'lines.*.unit_price'        => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required' => 'Add at least one ingredient — a meal is made of something.',
            'lines.min'      => 'Add at least one ingredient — a meal is made of something.',
        ];
    }
}
