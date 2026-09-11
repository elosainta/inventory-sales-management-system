<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Production opens on the menu: one card per dish, the same card the Recipes
 * page shows. Tapping one opens that dish laid out like its recipe, with a
 * quantity box per ingredient, and what is typed there is what comes off the
 * shelf.
 *
 * Until 2026-09-11 no ingredient amount was ever submitted - the recipe alone
 * decided what was used, because a typed-in amount lets a chef take any
 * quantity off stock. The Owner chose accuracy over that. What keeps it safe
 * is pinned here: only the dish's own ingredients can be deducted, every one
 * needs an amount, and a junior chef still sees no menu pricing.
 */
class ProductionDishGridTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $beef;

    private InventoryItem $tomato;

    private function dish(bool $withFinishedDish = false): Recipe
    {
        $this->beef   = InventoryItem::create(['name' => 'Chuck tender', 'category' => 'Meat', 'unit' => 'kg', 'quantity_on_hand' => 5, 'unit_cost' => 40]);
        $this->tomato = InventoryItem::create(['name' => 'Tomato', 'category' => 'Vegetables', 'unit' => 'kg', 'quantity_on_hand' => 1, 'unit_cost' => 5]);

        $recipe = Recipe::create(['name' => 'beef taco', 'selling_price' => 30]);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'inventory_item_id' => $this->beef->id, 'quantity' => 0.3]);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'inventory_item_id' => $this->tomato->id, 'quantity' => 0.02]);

        if ($withFinishedDish) {
            $out = InventoryItem::create(['name' => 'Beef taco (plated)', 'category' => 'Meat', 'unit' => 'pcs', 'quantity_on_hand' => 0, 'unit_cost' => 0]);
            $recipe->update(['output_inventory_item_id' => $out->id]);
        }

        return $recipe->fresh();
    }

    private function chef(): User
    {
        return User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);
    }

    private function logDish(Recipe $recipe, array $used, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->chef())
            ->from(route('production.dish', $recipe))
            ->post(route('production.dish.store', $recipe), array_merge([
                'quantity_produced' => 2,
                'produced_by'       => 'Chris',
                'production_date'   => '2026-09-11',
                'used'              => $used,
            ], $extra));
    }

    // ---- the grid ----

    public function test_a_chef_sees_every_dish_as_a_card_that_opens_its_page(): void
    {
        $recipe = $this->dish();

        $this->actingAs($this->chef())
            ->get(route('production.index'))
            ->assertOk()
            ->assertSee('beef taco')
            ->assertSee('Chuck tender (0.3 kg)')
            ->assertSee(route('production.dish', $recipe), false)
            ->assertSee('Tap a dish to log how many were made.')
            // Not the Recipes page's controls.
            ->assertDontSee('+ Add Recipe')
            ->assertDontSee(route('recipes.show', $recipe), false)
            ->assertDontSee(route('recipes.destroy', $recipe), false)
            // Nor menu pricing: a junior chef is off every page that prices it.
            ->assertDontSee('Selling Price')
            ->assertDontSee('Profit Per Dish');
    }

    public function test_a_manager_sees_the_same_card_as_on_recipes_prices_included(): void
    {
        $this->dish();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_HEAD_CHEF]))
            ->get(route('production.index'))
            ->assertOk()
            ->assertSee('Plate Cost')
            ->assertSee('Selling Price')
            ->assertSee('Profit Per Dish')
            ->assertDontSee('+ Add Recipe');
    }

    public function test_the_recipes_page_still_renders_its_cards(): void
    {
        // It now draws the body of each card from the same partial.
        $recipe = $this->dish();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('Serves 1')
            ->assertSee('Chuck tender (0.3 kg)')
            ->assertSee('Profit Per Dish')
            ->assertSee(route('recipes.show', $recipe), false)
            ->assertSee('+ Add Recipe');
    }

    // ---- the dish page ----

    public function test_every_box_starts_at_zero_with_the_recipe_beside_it(): void
    {
        // Zero, not the recipe amount (the Owner, 2026-09-11): the chef types
        // only what they used rather than deleting what they did not.
        $recipe = $this->dish();

        $html = $this->actingAs($this->chef())
            ->get(route('production.dish', $recipe))
            ->assertOk()
            ->assertSee('beef taco')
            ->assertSee('name="used[' . $this->beef->id . ']"', false)
            ->assertSee('name="used[' . $this->tomato->id . ']"', false)
            ->assertSee('Recipe says')
            ->assertSee('<span class="recipe-says">0.3</span>', false)
            ->assertSee('<span class="recipe-says">0.02</span>', false)
            // Costs are a manager's.
            ->assertDontSee('Unit Cost')
            ->assertDontSee('Subtotal')
            ->getContent();

        preg_match_all('#<input type="number" name="used\[\d+\]"[^>]*value="([^"]*)"#', $html, $m);
        $this->assertSame(['0', '0'], $m[1]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_HEAD_CHEF]))
            ->get(route('production.dish', $recipe))
            ->assertOk()
            ->assertSee('Unit Cost');
    }

    public function test_what_the_chef_typed_is_what_comes_off_the_shelf(): void
    {
        $recipe = $this->dish(withFinishedDish: true);

        // Two tacos: the recipe says 0.6 kg beef and 0.04 kg tomato. The chef
        // actually used 0.75 kg beef and no tomato at all.
        $this->logDish($recipe, [$this->beef->id => '0.75', $this->tomato->id => '0'])
            ->assertRedirect(route('production.index'));

        $this->assertEquals(4.25, (float) $this->beef->fresh()->quantity_on_hand);
        $this->assertEquals(1.0, (float) $this->tomato->fresh()->quantity_on_hand);

        // The batch keeps what was actually used, beside the recipe for anyone who looks.
        $batch = ProductionBatch::with('lines')->sole();
        $this->assertEquals(2, (int) $batch->quantity_produced);
        $this->assertEquals(0.75, (float) $batch->lines->firstWhere('inventory_item_id', $this->beef->id)->quantity);
        $this->assertEquals(0.0, (float) $batch->lines->firstWhere('inventory_item_id', $this->tomato->id)->quantity);

        // The finished dish still goes up by how many were made.
        $this->assertEquals(2.0, (float) $recipe->outputInventoryItem->fresh()->quantity_on_hand);
    }

    public function test_an_ingredient_the_dish_does_not_use_is_refused(): void
    {
        $recipe = $this->dish();
        $salt   = InventoryItem::create(['name' => 'Salt', 'category' => 'Spice', 'unit' => 'kg', 'quantity_on_hand' => 3, 'unit_cost' => 2]);

        $this->logDish($recipe, [$this->beef->id => '0.6', $this->tomato->id => '0.04', $salt->id => '3'])
            ->assertRedirect(route('production.dish', $recipe))
            ->assertSessionHasErrors('used');

        $this->assertEquals(3.0, (float) $salt->fresh()->quantity_on_hand);
        $this->assertEquals(5.0, (float) $this->beef->fresh()->quantity_on_hand);
        $this->assertSame(0, ProductionBatch::count());
    }

    public function test_every_ingredient_needs_an_amount(): void
    {
        // An empty box is a question, not a zero.
        $recipe = $this->dish();

        $this->logDish($recipe, [$this->beef->id => '0.6'])
            ->assertSessionHasErrors('used.' . $this->tomato->id);

        $this->assertSame(0, ProductionBatch::count());
    }

    public function test_a_part_timer_cannot_reach_the_dish_page_or_post_it(): void
    {
        $recipe     = $this->dish();
        $partTimer  = User::factory()->create(['role' => User::ROLE_PART_TIMER]);

        $this->actingAs($partTimer)->get(route('production.dish', $recipe))->assertForbidden();
        $this->actingAs($partTimer)->post(route('production.dish.store', $recipe), [
            'quantity_produced' => 1, 'produced_by' => 'x', 'production_date' => '2026-09-11',
            'used' => [$this->beef->id => 9],
        ])->assertForbidden();

        $this->assertEquals(5.0, (float) $this->beef->fresh()->quantity_on_hand);
    }

    // ---- removing an entry ----

    /**
     * Removing an entry puts back exactly what it took - read off its own lines,
     * so an amount the chef corrected comes back as corrected, not as the recipe
     * says - and takes the dishes it made back off. Until 2026-09-11 a delete
     * left the stock as it was, and the confirm box said so.
     */
    public function test_removing_an_entry_puts_the_shelf_back(): void
    {
        $recipe = $this->dish(withFinishedDish: true);

        $this->logDish($recipe, [$this->beef->id => '0.75', $this->tomato->id => '0.04']);
        $this->assertEquals(4.25, (float) $this->beef->fresh()->quantity_on_hand);
        $this->assertEquals(2.0, (float) $recipe->outputInventoryItem->fresh()->quantity_on_hand);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_HEAD_CHEF]))
            ->from(route('production.index'))
            ->delete(route('production.destroy', ProductionBatch::sole()))
            ->assertRedirect(route('production.index'));

        $this->assertEquals(5.0, (float) $this->beef->fresh()->quantity_on_hand);
        $this->assertEquals(1.0, (float) $this->tomato->fresh()->quantity_on_hand);
        $this->assertEquals(0.0, (float) $recipe->outputInventoryItem->fresh()->quantity_on_hand);
        $this->assertSame(0, ProductionBatch::count());
    }

    public function test_a_finished_dish_already_sold_does_not_go_below_zero(): void
    {
        $recipe = $this->dish(withFinishedDish: true);
        $this->logDish($recipe, [$this->beef->id => '0.6', $this->tomato->id => '0.04']);

        // Both tacos sold since.
        $recipe->outputInventoryItem->update(['quantity_on_hand' => 0]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->delete(route('production.destroy', ProductionBatch::sole()));

        $this->assertEquals(0.0, (float) $recipe->outputInventoryItem->fresh()->quantity_on_hand);
        $this->assertEquals(5.0, (float) $this->beef->fresh()->quantity_on_hand);
    }

    public function test_a_chef_cannot_remove_an_entry(): void
    {
        $recipe = $this->dish();
        $this->logDish($recipe, [$this->beef->id => '0.6', $this->tomato->id => '0.04']);

        $this->actingAs($this->chef())
            ->delete(route('production.destroy', ProductionBatch::sole()))
            ->assertForbidden();

        $this->assertSame(1, ProductionBatch::count());
        $this->assertEquals(4.4, (float) $this->beef->fresh()->quantity_on_hand);
    }

    public function test_the_old_log_production_pop_up_is_gone(): void
    {
        $this->dish();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_HEAD_CHEF]))
            ->get(route('production.index'))
            ->assertOk()
            ->assertDontSee('+ Log Production')
            ->assertDontSee('id="add-modal"', false);

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('production.store'));
    }

    public function test_the_dish_page_script_parses(): void
    {
        // A syntax error would leave every box unscaled and unmarked on a page
        // that still returns 200.
        if (! shell_exec('node --version 2>&1')) {
            $this->markTestSkipped('node is not on PATH.');
        }

        $html = $this->actingAs($this->chef())->get(route('production.dish', $this->dish()))->getContent();
        preg_match_all('#<script>(.*?)</script>#s', $html, $m);
        $script = collect($m[1])->first(fn ($s) => str_contains($s, 'qty-used'));
        $this->assertNotNull($script);

        $file = tempnam(sys_get_temp_dir(), 'dish') . '.js';
        file_put_contents($file, $script);
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $out, $status);
        @unlink($file);

        $this->assertSame(0, $status, implode(' | ', $out));
    }
}
