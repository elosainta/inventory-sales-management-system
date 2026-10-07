<?php

namespace App\Models;

use App\Support\Money;
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
     * Take in a delivery and leave the shelf in the units a recipe counts.
     *
     * A delivery is keyed the way it is bought - a tray of eggs, a 4x5kg
     * carton of oil, a pack of 84 cheese slices - while a recipe counts one
     * egg, one kilo, one slice. pack_size is the bridge between the two, and
     * applying it here is the whole purpose of the column: nothing read it
     * until 1.38.5, so a pack price landed on a piece unit and six dishes
     * read a loss while the Inventory page carried RM 13,580 of stock that
     * was never there.
     *
     * No pack size means the invoice and the shelf already agree on the unit,
     * so both figures go in untouched.
     *
     * ponytail: assumes a packed item is always bought by the pack, which is
     * how this kitchen buys - every pack size on record is a tray, carton or
     * pack from a supplier. Add a per-line packs/pieces choice if one ever
     * starts selling a packed item loose.
     */
    public function receive(string|int|float $quantity, string|int|float $unitPrice): void
    {
        $perPack = $this->pack_size > 1 ? (string) $this->pack_size : '1';

        $this->quantity_on_hand = bcadd(
            (string) $this->quantity_on_hand,
            bcmul((string) $quantity, $perPack, 4),
            4
        );
        $this->unit_cost    = Money::perUnit($unitPrice, $perPack);
        $this->last_updated = now();
        $this->save();
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

    /**
     * The units a dropdown offers: the built-in list, then every unit an item
     * already carries. A unit added from "+ New unit…" exists once an item is
     * saved with it — there is no units table, so one nobody uses any more
     * drops off the list when its last item goes. Case-insensitive, first
     * spelling wins, so "KG" never sits beside "kg".
     */
    public static function units(): array
    {
        return collect(self::UNITS)
            ->merge(self::query()->distinct()->pluck('unit'))
            ->filter()
            ->unique(fn ($unit) => mb_strtolower($unit))
            ->values()
            ->all();
    }

    /**
     * An item is flagged when it has actually run out — nothing left on the
     * shelf. It used to flag at half of `reorder_threshold`, which read that
     * column as a minimum to stay above; it is the kitchen's upper limit,
     * how much of a thing they hold, so it is not a trigger at all (the
     * Owner, 2026-09-12).
     */
    public function isOutOfStock(): bool
    {
        return $this->quantity_on_hand <= 0;
    }

    /** The same rule in SQL — the dashboard used to hand-write its own. */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity_on_hand', '<=', 0);
    }
}