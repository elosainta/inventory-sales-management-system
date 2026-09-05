<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreInvoiceScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('use-invoice-scan');
    }

    public function rules(): array
    {
        return [
            // Same ceiling as sale receipt photos. `mimes` checks the real
            // file, not the name it arrived under — this goes to an outside
            // API and onto the company's books.
            'invoice' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,pdf', 'max:25600'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice.mimes' => 'Upload a photo of the invoice (JPG, PNG, WEBP, GIF) or a PDF.',
            'invoice.max'   => 'That file is over 25 MB — photograph the invoice rather than scanning it at full resolution.',
        ];
    }
}
