<?php

use App\Models\InventoryItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Migrations\Migration;

/**
 * Sausage and sourdough are counted in pieces in the kitchen, not in packets,
 * so recipes could not name them in the unit anyone actually uses. The clearest
 * symptom is the "sun rise egg" recipe asking for 0.05 pkt of sourdough —
 * someone wanting a slice and having to write it as a twentieth of a packet.
 *
 * Note what that scales to. A packet is 12 slices, so 0.05 pkt is 0.6 of a
 * slice, and this migration writes 0.6 — the faithful conversion, because it
 * preserves the amount of bread the plate has always used. It is almost
 * certainly meant to be 1 whole slice, but rounding it here would quietly
 * increase the bread on that plate by two thirds on an assumption. That is the
 * Owner's call, and it is one field on the Recipes page.
 *
 * The conversion keeps the money still. Quantity is multiplied by the pack
 * size and unit cost divided by it, so `quantity_on_hand × unit_cost` — the
 * stock value on the Inventory page and the Inventory Value tile on the
 * dashboard — comes out to the same ringgit it did before. `monetary_value`
 * is not assigned here: InventoryItem::booted() recomputes it on save, in
 * decimal, and that is the only thing allowed to write it.
 *
 * Recipe lines are scaled by the same factor, so a plate that used a
 * twentieth of a loaf still uses exactly as much bread as it did; plate costs
 * are recalculated afterwards so nothing drifts from its ingredients.
 *
 * Reversible: down() divides back by the same factors and restores the
 * original units, so the figures return to what they were.
 */
return new class extends Migration
{
    /**
     * Pieces per packet, from the kitchen on 2026-08-29.
     *
     * SAUSAGE #10 is deliberately NOT here, and stays that way. It is priced by
     * weight — 10 kg at RM35.80/kg — and the Owner confirmed on 2026-08-29 that
     * only the chicken sausage was ever meant to change. This is a closed
     * decision, not a pending one: do not "finish the job" by converting it.
     * No recipe uses it either way.
     *
     * inventory_item_id => [pieces per current unit, current unit]
     */
    private const CONVERSIONS = [
        56  => [5, 'pkt'],   // CHICKEN SAUSAGE — 5 pieces per packet
        181 => [12, 'pkt'],  // SOURDOUGH       — 12 slices per packet
    ];

    public function up(): void
    {
        foreach (self::CONVERSIONS as $id => [$factor, $fromUnit]) {
            if (! $factor) {
                throw new RuntimeException(
                    "No pieces-per-{$fromUnit} figure for inventory item #{$id}. "
                    . 'Fill in self::CONVERSIONS before deploying — relabelling '
                    . 'without converting would misstate stock value and any '
                    . 'recipe priced off it.'
                );
            }

            $item = InventoryItem::find($id);

            if (! $item) {
                echo "  inventory item #{$id} not found — skipped\n";
                continue;
            }

            if ($item->unit === 'pcs') {
                echo "  {$item->name} is already in pcs — skipped\n";
                continue;
            }

            $wasQty   = (float) $item->quantity_on_hand;
            $wasCost  = (float) $item->unit_cost;
            $wasValue = (float) $item->monetary_value;

            $item->quantity_on_hand = $wasQty * $factor;
            $item->unit_cost        = round($wasCost / $factor, 2);
            $item->unit             = 'pcs';
            $item->save();

            printf(
                "  %-18s %s %s @ %.2f  ->  %s pcs @ %.2f   (value %.2f -> %.2f)\n",
                $item->name,
                rtrim(rtrim(number_format($wasQty, 2), '0'), '.'),
                $fromUnit,
                $wasCost,
                rtrim(rtrim(number_format((float) $item->quantity_on_hand, 2), '0'), '.'),
                (float) $item->unit_cost,
                $wasValue,
                (float) $item->monetary_value,
            );

            // Recipe lines are written in the item's unit, so they scale with it.
            foreach (RecipeIngredient::where('inventory_item_id', $id)->get() as $line) {
                $wasLine = (float) $line->quantity;
                $line->quantity = $wasLine * $factor;
                $line->save();

                printf(
                    "    recipe '%s': %s %s -> %s pcs\n",
                    $line->recipe?->name ?? '(deleted)',
                    rtrim(rtrim(number_format($wasLine, 4), '0'), '.'),
                    $fromUnit,
                    rtrim(rtrim(number_format((float) $line->quantity, 4), '0'), '.'),
                );
            }
        }

        // Unit cost moved, so every plate priced off these has to be re-added.
        $touched = Recipe::whereHas(
            'ingredients',
            fn ($q) => $q->whereIn('inventory_item_id', array_keys(self::CONVERSIONS))
        )->get();

        $touched->each->recalculatePlateCost();

        echo '  recalculated ' . $touched->count() . " plate cost(s)\n";
    }

    public function down(): void
    {
        foreach (self::CONVERSIONS as $id => [$factor, $toUnit]) {
            if (! $factor) {
                continue;
            }

            $item = InventoryItem::find($id);

            if (! $item || $item->unit !== 'pcs') {
                continue;
            }

            $item->quantity_on_hand = (float) $item->quantity_on_hand / $factor;
            $item->unit_cost        = round((float) $item->unit_cost * $factor, 2);
            $item->unit             = $toUnit;
            $item->save();

            foreach (RecipeIngredient::where('inventory_item_id', $id)->get() as $line) {
                $line->quantity = (float) $line->quantity / $factor;
                $line->save();
            }
        }

        Recipe::whereHas(
            'ingredients',
            fn ($q) => $q->whereIn('inventory_item_id', array_keys(self::CONVERSIONS))
        )->get()->each->recalculatePlateCost();
    }
};
