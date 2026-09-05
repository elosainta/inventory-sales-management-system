<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'   => ['required', 'exists:suppliers,id'],
            'status'        => ['required', 'in:completed,pending'],
            'purchase_date' => ['required', 'date'],
            'lines'         => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'lines.*.quantity'          => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price'        => ['required', 'numeric', 'min:0'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'receipt' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}