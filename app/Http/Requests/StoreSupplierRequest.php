<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return ['bukku_contact_id.unique' => 'Another supplier is already linked to that Bukku supplier.'];
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'contact' => ['required', 'string', 'max:50'],
            'email'   => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            // One Bukku supplier is one kitchen supplier, or a scan's purchases
            // would split between two again.
            'bukku_contact_id' => ['nullable', 'integer', 'min:1',
                Rule::unique('suppliers', 'bukku_contact_id')->ignore($this->route('supplier'))],
        ];
    }
}