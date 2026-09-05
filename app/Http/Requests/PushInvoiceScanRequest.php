<?php

namespace App\Http\Requests;

use App\Domain\Purchasing\Actions\PushInvoiceToBukku;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The reviewed bill, on its way to Bukku.
 *
 * Everything here is what the human confirmed on screen. Nothing is trusted
 * from the extraction, and nothing is defaulted silently: a missing supplier
 * or a missing account has to be answered before a bill can exist.
 */
class PushInvoiceScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('use-invoice-scan');
    }

    protected function prepareForValidation(): void
    {
        // A line the reviewer emptied out is a line they removed, not an
        // error to shout about.
        $lines = array_filter(
            $this->input('lines', []),
            fn ($line) => filled($line['description'] ?? null),
        );

        $this->merge(['lines' => array_values($lines)]);
    }

    public function rules(): array
    {
        return [
            'contact_id'     => ['required', 'integer', 'min:1'],
            'supplier_name'  => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date'   => ['required', 'date'],
            'term_id'        => ['required', Rule::in(array_keys(PushInvoiceToBukku::TERMS))],

            'lines'                => ['required', 'array', 'min:1'],
            'lines.*.description'  => ['required', 'string', 'max:255'],
            'lines.*.quantity'     => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price'   => ['required', 'numeric', 'min:0'],
            'lines.*.account_id'   => ['required', 'integer', 'min:1'],
            'lines.*.product_id'   => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.required' => 'Choose which supplier this invoice is from.',
            'lines.required'      => 'A bill needs at least one line — fill in a description.',
            'lines.min'           => 'A bill needs at least one line — fill in a description.',
        ];
    }
}
