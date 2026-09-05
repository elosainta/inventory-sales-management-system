<?php

namespace App\Domain\Sales\Actions;

use App\Models\Sale;
use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

class LogSale
{
    public function execute(array $data): Sale
    {
        // Cast, don't just null-coalesce: a blank discount field arrives as ''
        // unless ConvertEmptyStringsToNull is in the stack, and '' is a TypeError.
        $data['total_revenue'] = Sale::revenue(
            $data['qty_sold'],
            $data['selling_price'],
            (float) ($data['discount'] ?? 0)
        );

        return DB::transaction(function () use ($data) {
            $sale = Sale::create($data);

            $recipe = Recipe::with('ingredients.inventoryItem', 'outputInventoryItem')
                ->find($data['recipe_id'] ?? null);

            if ($recipe) {
                // Inventory -> Production -> Sales. An open order is off-menu,
                // has no recipe, and deducts nothing. The low-stock alert is
                // raised from LogProduction, not here.
                if ($output = $recipe->outputInventoryItem) {
                    // The dish is made before it is sold, and production has
                    // already taken the raw ingredients off the shelf. The sale
                    // takes the finished dish and nothing else — deducting the
                    // ingredients again here would take them off twice and read
                    // the kitchen as far emptier than it is.
                    $output->quantity_on_hand = max(0, $output->quantity_on_hand - $data['qty_sold']);
                    $output->save();
                } else {
                    // No production step for this recipe, so the sale is still
                    // the only thing that moves stock and deducts the raw
                    // ingredients itself. This is every recipe until someone
                    // gives it a finished good on the Recipes page.
                    foreach ($recipe->ingredients as $ingredient) {
                        $item = $ingredient->inventoryItem;
                        if (!$item) continue;

                        $deduct = $ingredient->quantity * $data['qty_sold'];
                        $newQty = max(0, $item->quantity_on_hand - $deduct);

                        $item->quantity_on_hand = $newQty;
                        $item->save();
                    }
                }
            }

            return $sale;
        });
    }
}