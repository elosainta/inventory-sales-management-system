<?php

namespace App\Http\Requests;

use App\Domain\Purchasing\Actions\PushInvoiceToBukku;
use App\Models\InvoiceScan;
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

        // A delivery order may go without a supplier. Bukku will not take a
        // bill without one, so blank means the kitchen supplier that stands
        // for delivery orders - filled in here, before validation, so the
        // `required` rule below still has the last word when there is none.
        if ($this->input('document_type') === InvoiceScan::TYPE_DELIVERY_ORDER && blank($this->input('supplier_id'))) {
            $this->merge(['supplier_id' => InvoiceScan::deliveryOrderSupplierId()]);
        }
    }

    public function rules(): array
    {
        return [
            // The kitchen's own supplier (the Suppliers page), not a Bukku
            // contact: LinkSupplierToBukku finds or registers that on Send.
            'supplier_id'    => ['required', 'integer', 'exists:suppliers,id'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'invoice_date'   => ['required', 'date'],
            // What the paper says the bill comes to. Blank means "the lines".
            'bill_total'     => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'term_id'        => ['required', Rule::in(array_keys(PushInvoiceToBukku::TERMS))],
            // Correctable here, because whoever uploaded it may have picked the
            // wrong one; absent means keep what the scan already says.
            'document_type'  => ['nullable', Rule::in(array_keys(InvoiceScan::TYPES))],

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
            'supplier_id.required' => $this->input('document_type') === InvoiceScan::TYPE_DELIVERY_ORDER
                ? 'Choose a supplier, or link one on the Suppliers page to "' . InvoiceScan::DELIVERY_ORDER_SUPPLIER . '" in Bukku so a delivery order can be sent without one.'
                : 'Choose which supplier this invoice is from.',
            'lines.required'      => 'A bill needs at least one line — fill in a description.',
            'lines.min'           => 'A bill needs at least one line — fill in a description.',
        ];
    }
}
