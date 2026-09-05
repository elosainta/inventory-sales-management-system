<?php

namespace App\Http\Requests;

use App\Models\StockTakeItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockTakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // gate check is done in the controller
    }

    /**
     * Only the two movement columns are submitted. Current stock is read off
     * live inventory by the controller and the balance is worked out from it,
     * so neither is accepted from the form — a posted figure would let anyone
     * write any stock level they liked straight into inventory.
     *
     * The numeric ceiling is the DECIMAL(10,2) column's, so an over-long
     * figure fails validation instead of erroring out of the database.
     */
    public function rules(): array
    {
        return [
            'section'   => ['required', Rule::in(array_keys(StockTakeItem::SECTIONS))],
            'taken_on'  => ['required', 'date'],
            'note'      => ['nullable', 'string', 'max:2000'],

            'entries'                      => ['nullable', 'array'],
            'entries.*.stock_take_item_id' => ['nullable', 'integer', 'exists:stock_take_items,id'],
            // Set when a chef adds something to the sheet straight from
            // inventory. The controller turns it into a catalog line so the
            // row moves stock exactly like every other one.
            'entries.*.inventory_item_id'  => ['nullable', 'integer', 'exists:inventory_items,id'],
            'entries.*.item_name'          => ['nullable', 'string', 'max:255'],
            'entries.*.unit'               => ['nullable', 'string', 'max:50'],
            'entries.*.qty_in'             => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'entries.*.qty_out'            => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],

            'open_orders'              => ['nullable', 'array'],
            'open_orders.*.item_name'  => ['nullable', 'string', 'max:255'],
            'open_orders.*.quantity'   => ['nullable', 'string', 'max:100'],
            'open_orders.*.note'       => ['nullable', 'string', 'max:255'],
        ];
    }
}
