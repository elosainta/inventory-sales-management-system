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
        return Gate::allows('send-invoice-scan');
    }

    protected function prepareForValidation(): void
    {
        // A line the reviewer emptied out is a line they removed, not an
        // error to shout about.
        // A line the reviewer emptied out is a line they removed, and a line
        // they unticked is one they rejected — neither belongs on the bill.
        // The tick is what "reject" actually means here: the row stays visible
        // with its reading intact, it just does not get billed.
        $lines = array_filter(
            $this->input('lines', []),
            fn ($line) => filled($line['description'] ?? null)
                && (bool) ($line['include'] ?? false),
        );

        // The review screen no longer asks which account a line belongs to, so
        // every line lands on the configured fallback. That is a blunter bill
        // than one where stock is posted against stock — see the note in
        // PushInvoiceToBukku::applyProduct(), which still overrides this for
        // any line that does arrive carrying a Bukku product.
        $fallback = (int) config('services.bukku.default_account_id');

        $lines = array_map(
            fn ($line) => $line + ['account_id' => $fallback],
            $lines,
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
            // The kitchen's own shelf, not Bukku's catalogue. Optional: a line
            // for something not stocked here (a delivery fee, a new item) is
            // still a real bill line, it just teaches the dictionary nothing.
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
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
