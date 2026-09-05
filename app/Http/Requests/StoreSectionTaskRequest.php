<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSectionTaskRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:150'],
            'description'    => ['nullable', 'string', 'max:255'],
            'requires_photo' => ['nullable', 'boolean'],
        ];
    }
}
