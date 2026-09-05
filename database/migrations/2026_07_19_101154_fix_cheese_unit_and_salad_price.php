<?php

use App\Models\InventoryItem;
use App\Models\Recipe;
use Illuminate\Database\Migrations\Migration;

/**
 * Data correction (requested 2026-07-19):
 *   1. The cheese inventory item is counted in pieces — its unit should be "pcs".
 *   2. The "Scallion & Chicken Salad" recipe's selling price should be RM25.00
 *      (it was keyed as RM28.00).
 *
 * These match by name. The live catalog/menu isn't in the repo, so if a production
 * record is named differently, adjust the patterns in the constants below — that is
 * the only place you should need to touch.
 */
return new class extends Migration
{
    /** Name patterns (case-insensitive LIKE) identifying the records to correct. */
    private const CHEESE_PATTERNS = ['%cheese%', '%keju%'];               // inventory_items
    private const SALAD_PATTERNS  = ['%scallion%chicken%salad%', '%scallion%salad%']; // recipes

    private const SALAD_NEW_PRICE = 25.00;
    private const SALAD_OLD_PRICE = 28.00; // used by down()

    public function up(): void
    {
        // 1. Cheese -> unit "pcs". Unit is a label only, so quantity/value are untouched.
        $cheese = $this->cheeseItems();
        $this->report('Cheese unit -> pcs', $cheese->pluck('name'));
        $cheese->each(function (InventoryItem $item) {
            $item->unit         = 'pcs';
            $item->last_updated = now();
            $item->save();
        });

        // 2. Scallion & Chicken Salad -> RM25.00 selling price.
        $this->report('Salad selling_price -> RM25.00', $this->saladRecipes()->pluck('name'));
        $this->setSaladPrice(self::SALAD_NEW_PRICE);
    }

    /** Print which records a step matched, so a no-op (bad pattern) is visible on deploy. */
    private function report(string $label, \Illuminate\Support\Collection $names): void
    {
        echo $names->isEmpty()
            ? "  [!] {$label}: matched NOTHING — check the name pattern.\n"
            : "  [ok] {$label}: " . $names->implode(', ') . "\n";
    }

    public function down(): void
    {
        // Restore the salad's previous selling price. The cheese item's previous
        // unit is unknown, so it is left as "pcs" — reverse it by hand if needed.
        $this->setSaladPrice(self::SALAD_OLD_PRICE);
    }

    /**
     * Set the salad recipe's selling price. Only selling_price changes — plate cost
     * is driven by ingredients, and profit is derived on read, so nothing else needs
     * recalculating.
     */
    private function setSaladPrice(float $price): void
    {
        $this->saladRecipes()->each(function (Recipe $recipe) use ($price) {
            $recipe->selling_price = $price;
            $recipe->save();
        });
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, InventoryItem> */
    private function cheeseItems(): \Illuminate\Database\Eloquent\Collection
    {
        return InventoryItem::where(fn ($q) => $this->applyPatterns($q, self::CHEESE_PATTERNS))->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Recipe> */
    private function saladRecipes(): \Illuminate\Database\Eloquent\Collection
    {
        return Recipe::where(fn ($q) => $this->applyPatterns($q, self::SALAD_PATTERNS))->get();
    }

    private function applyPatterns($query, array $patterns): void
    {
        foreach ($patterns as $pattern) {
            $query->orWhere('name', 'like', $pattern);
        }
    }
};
