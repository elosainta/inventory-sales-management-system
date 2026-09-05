<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserLanguageRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        // Same rule as ProfileUpdateRequest: null (or the empty string the
        // English radio submits, which ConvertEmptyStringsToNull turns into
        // null) means English, 'id' means Bahasa Indonesia.
        return [
            'preferred_language' => ['nullable', 'in:id'],
        ];
    }
}
