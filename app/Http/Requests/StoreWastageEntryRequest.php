<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWastageEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'quantity_wasted'   => ['required', 'numeric', 'min:0.01'],
            'reason'            => ['required', 'string', 'in:spoilage,expired,burnt,dropped,over-prepped,contaminated'],
            'recorded_date'     => ['required', 'date'],
        ];
    }
}