<?php

use App\Models\RecipeIngredient;
use Illuminate\Database\Migrations\Migration;

/**
 * "sun rise egg" asked for 0.6 of a slice of sourdough.
 *
 * That figure was correct arithmetic and wrong cooking. The recipe was written
 * as 0.05 of a packet, and 2026_08_29_000001 scaled it faithfully when the item
 * moved to slices — 0.05 × 12 = 0.6 — because rounding on an assumption would
 * have changed how much bread the plate used. The Owner has now confirmed it is
 * one whole slice, so this is the assumption being replaced by an answer.
 *
 * No money moves: sourdough carries a unit cost of RM0.00, so the plate cost is
 * unchanged whatever this quantity says. What does change is stock — a sale of
 * this dish will take 1 slice off inventory instead of 0.6.
 *
 * Guarded on the current value, so it cannot overwrite a figure someone has
 * since set by hand on the Recipes page.
 */
return new class extends Migration
{
    private const ITEM_SOURDOUGH = 181;

    private const WAS = 0.6;

    private const NOW = 1;

    public function up(): void
    {
        foreach (RecipeIngredient::where('inventory_item_id', self::ITEM_SOURDOUGH)->with('recipe')->get() as $line) {
            if (abs((float) $line->quantity - self::WAS) > 0.0001) {
                printf(
                    "  recipe '%s': sourdough is %s, not %s — left alone\n",
                    $line->recipe?->name ?? '(deleted)',
                    rtrim(rtrim(number_format((float) $line->quantity, 4), '0'), '.'),
                    self::WAS,
                );
                continue;
            }

            $line->quantity = self::NOW;
            $line->save();

            printf("  recipe '%s': sourdough %s -> %d slice\n", $line->recipe?->name ?? '(deleted)', self::WAS, self::NOW);

            // Sourdough costs RM0.00 so this will not move, but a plate cost
            // should never be left stale relative to its ingredients.
            $line->recipe?->recalculatePlateCost();
        }
    }

    public function down(): void
    {
        foreach (RecipeIngredient::where('inventory_item_id', self::ITEM_SOURDOUGH)->get() as $line) {
            if (abs((float) $line->quantity - self::NOW) > 0.0001) {
                continue;
            }

            $line->quantity = self::WAS;
            $line->save();
            $line->recipe?->recalculatePlateCost();
        }
    }
};
