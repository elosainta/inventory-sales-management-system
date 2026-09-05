<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpecialEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'menu'       => ['nullable', 'string'],
            'revenue'    => ['required', 'numeric', 'min:0'],
            'cost'       => ['required', 'numeric', 'min:0'],
        ];
    }
}
