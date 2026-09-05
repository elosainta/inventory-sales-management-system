<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExportFeedbackPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    public function rules(): array
    {
        return [
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }
}
