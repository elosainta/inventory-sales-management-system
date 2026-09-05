<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFloatIssuanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
    return [
        'amount_given'    => ['required', 'numeric', 'min:0'],
        'amount_spent'    => ['required', 'numeric', 'min:0'],
        'amount_returned' => ['required', 'numeric', 'min:0'],
        'status'          => ['required', 'in:open,reconciled,overspent'],
        'issued_date'     => ['required', 'date'],
        ];
    }
}