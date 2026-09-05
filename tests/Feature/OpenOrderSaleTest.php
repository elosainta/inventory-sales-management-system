<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An open order is off-menu food a staff member asked the kitchen for: the
 * dish is keyed in by hand, so it has no recipe and deducts nothing from
 * stock. Every ordinary sale still has to name a recipe.
 */
class OpenOrderSaleTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'qty_sold'      => 2,
            'selling_price' => '10.00',
            'sale_date'     => '2026-08-13',
            'is_open_order' => '0',
        ], $extra);
    }

    private function headChef(): User
    {
        return User::factory()->create(['role' => User::ROLE_HEAD_CHEF]);
    }

    /** A one-ingredient dish, so any stock movement is unmistakable. */
    private function dish(): Recipe
    {
        $item   = InventoryItem::create(['name' => 'Rice', 'category' => 'Dry', 'unit' => 'kg', 'quantity_on_hand' => 50, 'unit_cost' => 4]);
        $recipe = Recipe::create(['name' => 'Nasi goreng', 'selling_price' => 10]);

        RecipeIngredient::create(['recipe_id' => $recipe->id, 'inventory_item_id' => $item->id, 'quantity' => 1]);

        return $recipe;
    }

    public function test_open_order_is_logged_from_a_typed_in_dish(): void
    {
        // SaleController@store returns back(), because the form is a modal on
        // the sales page. A test request carries no referer, so back() would
        // resolve to '/' unless the origin is stated — ->from() is what makes
        // this assert the behaviour a browser actually gets.
        $this->actingAs($this->headChef())
            ->from(route('sales.index'))
            ->post('/sales', $this->payload([
                'is_open_order' => '1',
                'item_name'     => 'Fried rice with egg',
            ]))
            ->assertRedirect(route('sales.index'));

        $sale = Sale::firstOrFail();

        $this->assertNull($sale->recipe_id);
        $this->assertSame('Fried rice with egg', $sale->item_name);
        $this->assertSame('Fried rice with egg', $sale->label);
        // Compare the amount, not the driver's rendering of it. total_revenue is
        // DECIMAL(12,2), which MariaDB hands back as '20.00' and the in-memory
        // SQLite these tests run on hands back as '20'. Formatting both sides
        // keeps the assertion exact without asserting which database is under it.
        $this->assertSame('20.00', number_format((float) $sale->total_revenue, 2, '.', ''));
    }

    public function test_open_order_does_not_touch_stock(): void
    {
        $this->dish();

        $this->actingAs($this->headChef())->post('/sales', $this->payload([
            'is_open_order' => '1',
            'item_name'     => 'Staff noodles',
        ]));

        $this->assertEquals(50, InventoryItem::first()->quantity_on_hand);
    }

    /** The counterpart: an ordinary sale still deducts its recipe ingredients. */
    public function test_an_ordinary_sale_still_deducts_stock(): void
    {
        $recipe = $this->dish();

        $this->actingAs($this->headChef())->post('/sales', $this->payload(['recipe_id' => $recipe->id]));

        $this->assertEquals(48, InventoryItem::first()->quantity_on_hand);
    }

    public function test_open_order_needs_a_dish_name(): void
    {
        $this->actingAs($this->headChef())
            ->post('/sales', $this->payload(['is_open_order' => '1', 'item_name' => '']))
            ->assertSessionHasErrors('item_name');
    }

    public function test_an_ordinary_sale_still_needs_a_recipe(): void
    {
        $this->actingAs($this->headChef())
            ->post('/sales', $this->payload())
            ->assertSessionHasErrors('recipe_id');
    }

    /** Switching a sale to an open order has to drop the recipe it used to point at. */
    public function test_editing_into_an_open_order_clears_the_recipe(): void
    {
        $recipe = $this->dish();
        $sale   = Sale::create($this->payload([
            'recipe_id'     => $recipe->id,
            'is_open_order' => false,
            'total_revenue' => 20,
        ]));

        $this->actingAs($this->headChef())->patch("/sales/{$sale->id}", $this->payload([
            'is_open_order' => '1',
            'item_name'     => 'Off-menu curry',
            'recipe_id'     => $recipe->id,
        ]));

        $sale->refresh();

        $this->assertNull($sale->recipe_id);
        $this->assertSame('Off-menu curry', $sale->item_name);
    }
}
