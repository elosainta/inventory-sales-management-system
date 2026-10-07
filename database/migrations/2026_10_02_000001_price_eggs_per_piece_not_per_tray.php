<?php

use App\Models\InventoryItem;
use App\Models\Recipe;
use Illuminate\Database\Migrations\Migration;

/**
 * One-off, production data: EGG was stocked and priced by the TRAY while every
 * recipe lists eggs by the piece, so "1 egg" cost a recipe a whole tray —
 * RM 16.80 instead of RM 0.56. That alone put five dishes below zero profit
 * (Big Breakfast read RM 114.17 to make and sells for RM 35.00) and had
 * production taking 2 trays off the shelf for one breakfast.
 *
 * A tray is 30 eggs (`pack_size` already said so; nothing reads that column).
 * The shelf becomes pieces: 35 trays -> 1,050 eggs, RM 16.80 -> RM 0.56, and
 * the stock's total value is unchanged at RM 588.00. Recipe quantities are
 * left exactly as they are — they were always written in pieces, which is the
 * whole bug — and every recipe that uses an egg is then repriced.
 *
 * Keyed on id AND name so it fails closed: if id 32 is ever something else,
 * nothing happens. Idempotent — a second run sees `pcs` and stops. The tray
 * price is read off the row rather than hardcoded, so a delivery that
 * repriced it between this commit and the deploy still converts correctly.
 *
 * To undo: see down().
 */
return new class extends Migration
{
    private const ITEM_ID = 32;
    private const ITEM_NAME = 'EGG';
    private const PER_TRAY = 30;

    /** The unit names that mean "one tray" on this row. */
    private const TRAY_UNITS = ['unit', 'tray'];

    public function up(): void
    {
        $egg = $this->egg();

        if (! $egg || ! in_array($egg->unit, self::TRAY_UNITS, true)) {
            echo "  eggs: already per-piece or not the expected item — nothing changed.\n";

            return;
        }

        $trays = $egg->quantity_on_hand;

        $egg->update([
            'unit'             => 'pcs',
            'quantity_on_hand' => bcmul((string) $trays, (string) self::PER_TRAY, 2),
            'unit_cost'        => bcdiv((string) $egg->unit_cost, (string) self::PER_TRAY, 2),
            'pack_size'        => self::PER_TRAY,
        ]);

        $repriced = $this->repriceRecipesUsingTheEgg();

        echo "  eggs: {$trays} trays -> {$egg->quantity_on_hand} pcs at RM {$egg->unit_cost} each; {$repriced} recipes repriced.\n";
    }

    public function down(): void
    {
        $egg = $this->egg();

        if (! $egg || $egg->unit !== 'pcs') {
            return;
        }

        $egg->update([
            'unit'             => 'unit',
            'quantity_on_hand' => bcdiv((string) $egg->quantity_on_hand, (string) self::PER_TRAY, 2),
            'unit_cost'        => bcmul((string) $egg->unit_cost, (string) self::PER_TRAY, 2),
        ]);

        $this->repriceRecipesUsingTheEgg();
    }

    private function egg(): ?InventoryItem
    {
        return InventoryItem::where('id', self::ITEM_ID)
            ->where('name', self::ITEM_NAME)
            ->first();
    }

    /**
     * Only the recipes that use an egg. Repricing the whole menu would also
     * fold in every other ingredient's price drift since its last save, which
     * is a separate decision and would hide what this migration did.
     */
    private function repriceRecipesUsingTheEgg(): int
    {
        $recipes = Recipe::whereHas(
            'ingredients',
            fn ($query) => $query->where('inventory_item_id', self::ITEM_ID)
        )->get();

        $recipes->each->recalculatePlateCost();

        return $recipes->count();
    }
};
