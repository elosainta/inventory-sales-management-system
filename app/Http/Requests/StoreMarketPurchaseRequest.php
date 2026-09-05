<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMarketPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'signed_by'                      => ['required', 'string', 'max:100'],
            'purchase_date'                  => ['required', 'date'],
            'notes'                          => ['nullable', 'string', 'max:500'],
            'receipt'                        => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'lines'                          => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id'      => ['required', 'exists:inventory_items,id'],
            'lines.*.quantity'               => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price'             => ['required', 'numeric', 'min:0'],
        ];
    }
}
