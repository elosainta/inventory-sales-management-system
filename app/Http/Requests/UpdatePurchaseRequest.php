<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id'    => ['required', 'exists:suppliers,id'],
            'purchase_date'  => ['required', 'date'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'status'         => ['required', 'in:completed,pending'],
        ];
    }
}
