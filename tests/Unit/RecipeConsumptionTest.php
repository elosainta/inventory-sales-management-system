<?php

namespace Tests\Unit;

use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

/**
 * The formula behind Inventory -> Production -> Sales: what making N servings
 * of a dish takes off the shelf. Relations are set by hand so this runs with no
 * database (this clone has no pdo_sqlite).
 */
class RecipeConsumptionTest extends TestCase
{
    private function recipe(array $ingredients): Recipe
    {
        $recipe = new Recipe();

        $recipe->setRelation('ingredients', new Collection(array_map(function ($row) {
            $ingredient = new RecipeIngredient();
            $ingredient->inventory_item_id = $row[0];
            $ingredient->quantity          = $row[1];

            return $ingredient;
        }, $ingredients)));

        return $recipe;
    }

    public function test_each_ingredient_is_multiplied_by_the_batch_size(): void
    {
        $recipe = $this->recipe([[7, 0.08], [9, 2.0]]);

        $this->assertSame([7 => 1.6, 9 => 40.0], $recipe->consumptionFor(20));
    }

    public function test_one_serving_consumes_the_recipe_as_written(): void
    {
        $recipe = $this->recipe([[7, 0.08], [9, 2.0]]);

        $this->assertSame([7 => 0.08, 9 => 2.0], $recipe->consumptionFor(1));
    }

    /**
     * The bug this guards: a recipe listing the same inventory item twice — oil
     * used in two steps, say. Deducting those one at a time would apply only
     * the last, so the shelf would keep stock the kitchen has already used.
     */
    public function test_an_item_listed_twice_is_summed_not_overwritten(): void
    {
        $recipe = $this->recipe([[3, 0.5], [4, 1.0], [3, 0.25]]);

        $this->assertSame([3 => 7.5, 4 => 10.0], $recipe->consumptionFor(10));
    }

    /** An ingredient row whose inventory item was deleted moves nothing. */
    public function test_an_unlinked_ingredient_is_skipped(): void
    {
        $recipe = $this->recipe([[null, 5.0], [6, 1.5]]);

        $this->assertSame([6 => 3.0], $recipe->consumptionFor(2));
    }

    public function test_a_recipe_with_no_ingredients_consumes_nothing(): void
    {
        $this->assertSame([], $this->recipe([])->consumptionFor(50));
    }

    /**
     * Recipe quantities carry four decimals while stock columns carry two, so
     * the formula rounds at four — enough to stay faithful to the recipe
     * without accumulating float dust across a large batch.
     */
    public function test_fractional_quantities_round_to_four_places(): void
    {
        $recipe = $this->recipe([[2, 0.3333]]);

        $this->assertSame([2 => 0.9999], $recipe->consumptionFor(3));
    }
}
