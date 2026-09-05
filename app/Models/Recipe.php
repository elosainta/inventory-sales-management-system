<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recipe extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['name', 'output_inventory_item_id', 'serving_size', 'plate_cost', 'selling_price', 'misc_percent'];

    public function getProfitAttribute(): float
    {
        return round($this->selling_price - $this->plate_cost, 2);
    }

    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /**
     * The finished stock a batch of this recipe creates — the "-> Production"
     * step of Inventory -> Production -> Sales.
     *
     * Nullable, and that is what makes the pipeline opt-in. Where it is set,
     * production turns raw ingredients into this item and a sale takes the
     * finished dish off the shelf. Where it is null, the recipe has no
     * production step and a sale deducts its raw ingredients directly, exactly
     * as it always did. See [[Path — Logging production]].
     */
    public function outputInventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'output_inventory_item_id');
    }

    /**
     * The formula: what making $quantity servings takes off the shelf.
     *
     * Returns [inventory_item_id => quantity consumed]. Summed per item rather
     * than per ingredient row, because a recipe can list the same inventory
     * item twice (a sauce that uses oil in two steps) and deducting those one
     * at a time would lose the first.
     *
     * @return array<int, float>
     */
    public function consumptionFor(int|float $quantity): array
    {
        $used = [];

        foreach ($this->ingredients as $ingredient) {
            if (! $ingredient->inventory_item_id) {
                continue;
            }

            $used[$ingredient->inventory_item_id] =
                ($used[$ingredient->inventory_item_id] ?? 0) + (float) $ingredient->quantity * (float) $quantity;
        }

        return array_map(fn ($q) => round($q, 4), $used);
    }

    /**
     * Ingredient-only cost (before the miscellaneous overhead).
     */
    public function ingredientCost(): float
    {
        return (float) $this->ingredients()
            ->with('inventoryItem')
            ->get()
            ->sum(fn($ing) => $ing->quantity * ($ing->inventoryItem?->unit_cost ?? 0));
    }

    /**
     * Recalculate plate_cost from current ingredient prices, then apply the
     * miscellaneous overhead (gas, condiments, small consumables) as a percent
     * of ingredient cost. Call this after adding/removing/updating ingredients
     * or changing misc_percent.
     */
    public function recalculatePlateCost(): void
    {
        $ingredients = $this->ingredientCost();
        $misc        = $ingredients * ((float) ($this->misc_percent ?? 0) / 100);

        $this->plate_cost = round($ingredients + $misc, 2);
        $this->save();
    }
}