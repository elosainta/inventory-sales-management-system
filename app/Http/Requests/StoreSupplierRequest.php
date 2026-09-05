<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:50'],
            'email'   => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ];
    }
}