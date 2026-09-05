<?php

namespace App\Domain\Recipes\Actions;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Support\Facades\DB;

class SaveRecipe
{
    /**
     * Create a recipe with its ingredients, then compute plate cost.
     *
     * Expected $data shape:
     * [
     *   'name'         => 'Nasi Lemak',
     *   'serving_size' => 1,
     *   'ingredients'  => [
     *     ['inventory_item_id' => 1, 'quantity' => 0.25],
     *     ...
     *   ],
     * ]
     */
    public function execute(array $data, ?Recipe $recipe = null): Recipe
    {
        return DB::transaction(function () use ($data, $recipe) {
            $ingredients = $data['ingredients'] ?? [];
            unset($data['ingredients']);

            // Default the miscellaneous overhead to 30% when left blank.
            if (! isset($data['misc_percent']) || $data['misc_percent'] === '' || $data['misc_percent'] === null) {
                $data['misc_percent'] = 30;
            }

            $data['plate_cost'] = 0;

            // Merge duplicate ingredient rows (same item picked twice) by summing
            // their quantities. recipe_ingredients has a unique (recipe_id,
            // inventory_item_id) constraint, so inserting the same item twice would
            // otherwise throw a 500.
            $mergedQuantities = [];
            foreach ($ingredients as $ing) {
                $itemId = $ing['inventory_item_id'];
                $mergedQuantities[$itemId] = ($mergedQuantities[$itemId] ?? 0) + (float) $ing['quantity'];
            }

            if ($recipe) {
                $recipe->update($data);
                $recipe->ingredients()->delete();
            } else {
                $recipe = Recipe::create($data);
            }

            foreach ($mergedQuantities as $itemId => $quantity) {
                RecipeIngredient::create([
                    'recipe_id'         => $recipe->id,
                    'inventory_item_id' => $itemId,
                    'quantity'          => $quantity,
                ]);
            }

            $recipe->recalculatePlateCost();

            return $recipe;
        });
    }
}