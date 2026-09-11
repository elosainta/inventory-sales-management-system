<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class InventoryItem extends Model
{
    use LogsActivity;

    protected $fillable = [
        'name',
        'category',
        'unit',
        'quantity_on_hand',
        'reorder_threshold',
        'unit_cost',
        'bukku_product_id',
        'pack_size',
        'monetary_value',
        'last_updated',
    ];

    /**
     * monetary_value is derived, never entered: it is always the rounded
     * quantity times the unit cost. Deriving it here rather than at each of
     * the ten call sites that move stock is what keeps the Inventory total
     * and the dashboard's Inventory Value tallying with the item rows —
     * quantity_on_hand is DECIMAL(12,2) while recipe quantities carry four,
     * so a value worked out before the column rounded the quantity drifted a
     * few cents further out on every deduction.
     */
    protected static function booted(): void
    {
        static::saving(function (self $item) {
            $item->quantity_on_hand = self::round2($item->quantity_on_hand);
            $item->monetary_value   = self::round2(bcmul($item->quantity_on_hand, (string) $item->unit_cost, 4));
        });
    }

    /**
     * Half-up to two places in decimal, not binary. round() gets 2.65 x 11.70
     * wrong — the exact answer is 31.005, but the float lands a hair under and
     * rounds down to 31.00 where the DECIMAL columns say 31.01.
     * ponytail: assumes a non-negative figure, which stock and cost both are.
     */
    private static function round2(string|int|float $value): string
    {
        return bcadd((string) $value, '0.005', 2);
    }

    /**
     * Stock is considered "low" once it falls to half the reorder threshold
     * or less. The threshold is treated as a comfortable target rather than
     * the trigger point, so an item only flags Low when seriously depleted.
     */
    /**
     * The category and unit vocabularies.
     *
     * These were written out four times — the validator's `in:` rules, the
     * controller's $categories, and two hand-typed <option> lists in the
     * inventory modals. The list that decides is the validator's, so anything
     * that offers a choice reads from here instead of retyping it. Adding a
     * unit is now one edit.
     */
    public const CATEGORIES = ['Meat', 'Seafood', 'Dairy', 'Produce', 'Vegetables', 'Pantry', 'Spice', 'Retail'];

    public const UNITS = ['kg', 'g', 'L', 'ml', 'unit', 'pkt', 'box', 'btl', 'gallon', 'pcs', 'tray', 'slices'];

    public const LOW_STOCK_FACTOR = 0.5;

    public function isLowStock(): bool
    {
        return $this->quantity_on_hand <= $this->reorder_threshold * self::LOW_STOCK_FACTOR;
    }
}