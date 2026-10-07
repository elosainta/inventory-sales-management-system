<?php

use App\Models\InventoryItem;
use App\Models\Recipe;
use Illuminate\Database\Migrations\Migration;

/**
 * Repairs the migration before this one. It divided the egg price by 30 to
 * turn a tray price into a piece price, which was right against the copy of
 * the database it was rehearsed on (RM 16.80 a tray) and wrong against live,
 * where the price had already been corrected to RM 0.56 an egg by hand. So it
 * divided a second time and left eggs at RM 0.01, and repriced 17 dishes at
 * that figure.
 *
 * The quantity it wrote is right: 35 trays really are 1,050 eggs. Only the
 * price is wrong, and it cannot be recovered by multiplying RM 0.01 back up —
 * that division truncated. RM 0.56 is the figure: RM 16.80 a tray, 30 eggs.
 *
 * Guarded on the exact damage — id 32, named EGG, held in pcs at RM 0.01 —
 * because nobody would key a sen as an egg price, so it can only be this. The
 * quantity is deliberately NOT in the guard: eggs move all day and a sale
 * between the two deploys would have made this a no-op. A second run, or any
 * database where the price is already sensible, changes nothing.
 */
return new class extends Migration
{
    private const ITEM_ID = 32;
    private const ITEM_NAME = 'EGG';
    private const BROKEN_COST = '0.01';
    private const PER_EGG = '0.56';

    public function up(): void
    {
        $egg = InventoryItem::where('id', self::ITEM_ID)
            ->where('name', self::ITEM_NAME)
            ->where('unit', 'pcs')
            ->first();

        if (! $egg || (string) $egg->unit_cost !== self::BROKEN_COST) {
            echo "  eggs: price is not the broken figure — nothing changed.\n";

            return;
        }

        $egg->update(['unit_cost' => self::PER_EGG]);

        $recipes = Recipe::whereHas(
            'ingredients',
            fn ($query) => $query->where('inventory_item_id', self::ITEM_ID)
        )->get();

        $recipes->each->recalculatePlateCost();

        echo "  eggs: RM " . self::BROKEN_COST . " -> RM " . self::PER_EGG
            . " each, stock worth RM {$egg->monetary_value}; {$recipes->count()} recipes repriced.\n";
    }

    public function down(): void
    {
        // There is nothing to go back to — RM 0.01 an egg was never a price.
    }
};
