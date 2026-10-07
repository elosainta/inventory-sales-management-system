<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Correcting an ingredient's price on the Inventory page used to leave every
 * dish that uses it on the plate cost worked out from the OLD price, and
 * nothing on the Recipes page said so. Eggs were held by the tray and costed
 * per egg, which read as a RM 36 loss on a RM 35 breakfast; the price was put
 * right by hand and the Recipes page went on reporting the loss, because only
 * a purchase ever repriced anything.
 */
class IngredientRepriceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => User::ROLE_OWNER]);
    }

    private function egg(float $cost): InventoryItem
    {
        return InventoryItem::create([
            'name'             => 'EGG',
            'category'         => 'Retail',
            'unit'             => 'pcs',
            'quantity_on_hand' => 1050,
            'unit_cost'        => $cost,
        ]);
    }

    /** One egg, 30% miscellaneous, so the plate cost is the price plus a third. */
    private function breakfast(InventoryItem $egg): Recipe
    {
        $recipe = Recipe::create([
            'name'          => 'Big Breakfast',
            'serving_size'  => 1,
            'selling_price' => 35.00,
            'misc_percent'  => 30,
            'plate_cost'    => 0,
        ]);

        RecipeIngredient::create([
            'recipe_id'         => $recipe->id,
            'inventory_item_id' => $egg->id,
            'quantity'          => 1,
        ]);

        $recipe->recalculatePlateCost();

        return $recipe->fresh();
    }

    public function test_correcting_a_price_reprices_the_dishes_that_use_it(): void
    {
        $egg       = $this->egg(16.80);
        $breakfast = $this->breakfast($egg);

        $this->assertEquals(21.84, $breakfast->plate_cost);

        $this->actingAs($this->owner())
            ->patch(route('inventory.update', $egg), [
                'name'              => 'EGG',
                'category'          => 'Retail',
                'unit'              => 'pcs',
                'quantity_on_hand'  => 1050,
                'reorder_threshold' => 20,
                'unit_cost'         => 0.56,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals(0.73, $breakfast->fresh()->plate_cost);
    }

    /**
     * A quantity correction is the part timer's daily job and must not set the
     * whole menu's plate costs rewriting themselves for nothing.
     */
    public function test_a_quantity_correction_leaves_the_plate_cost_alone(): void
    {
        $egg       = $this->egg(0.56);
        $breakfast = $this->breakfast($egg);
        $before    = $breakfast->plate_cost;

        $this->actingAs($this->owner())
            ->patch(route('inventory.update', $egg), [
                'name'              => 'EGG',
                'category'          => 'Retail',
                'unit'              => 'pcs',
                'quantity_on_hand'  => 900,
                'reorder_threshold' => 20,
                'unit_cost'         => 0.56,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals($before, $breakfast->fresh()->plate_cost);
        $this->assertEquals(900, $egg->fresh()->quantity_on_hand);
    }
}
