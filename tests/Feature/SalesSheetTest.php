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
 * Log sales: every dish on the menu with a quantity box at 0 and its menu
 * price, one date, one Save. Each dish sold becomes its own sale through
 * LogSale, so stock moves exactly as it does from the pop-up.
 *
 * The pop-up stays as "+ Open order": off-menu food has no row on the sheet.
 */
class SalesSheetTest extends TestCase
{
    use RefreshDatabase;

    private InventoryItem $rice;

    private Recipe $pork;

    private Recipe $chicken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rice    = InventoryItem::create(['name' => 'Rice', 'category' => 'Pantry', 'unit' => 'kg', 'quantity_on_hand' => 10, 'unit_cost' => 4]);
        $this->pork    = Recipe::create(['name' => 'PAD KRA PAO PORK', 'selling_price' => 25]);
        $this->chicken = Recipe::create(['name' => 'PAD KRA PAO CHICKEN', 'selling_price' => 23]);
        RecipeIngredient::create(['recipe_id' => $this->pork->id, 'inventory_item_id' => $this->rice->id, 'quantity' => 0.2]);
        RecipeIngredient::create(['recipe_id' => $this->chicken->id, 'inventory_item_id' => $this->rice->id, 'quantity' => 0.2]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => User::ROLE_HEAD_CHEF]);
    }

    private function save(array $qty, array $price = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->manager())
            ->from(route('sales.index'))
            ->post(route('sales.sheet'), [
                'sale_date' => '2026-09-11',
                'qty'       => $qty,
                'price'     => $price + [$this->pork->id => '25.00', $this->chicken->id => '23.00'],
            ]);
    }

    public function test_every_dish_is_listed_with_a_quantity_at_zero_and_its_price(): void
    {
        $html = $this->actingAs($this->manager())
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Log sales')
            ->assertSee('PAD KRA PAO PORK')
            ->assertSee('PAD KRA PAO CHICKEN')
            ->assertSee('name="price[' . $this->pork->id . ']" value="25.00"', false)
            ->assertSee('Save sales')
            ->assertSee('+ Open order')
            ->assertDontSee('+ Log Sale')
            ->getContent();

        preg_match_all('#name="qty\[\d+\]" value="([^"]*)"#', $html, $m);
        $this->assertSame(['0', '0'], $m[1]);
    }

    public function test_one_save_logs_every_dish_sold_and_skips_the_zeros(): void
    {
        $this->save([$this->pork->id => '3', $this->chicken->id => '0'])
            ->assertRedirect(route('sales.index'))
            ->assertSessionHas('success', 'Sale logged.');

        $sale = Sale::sole();
        $this->assertSame($this->pork->id, $sale->recipe_id);
        $this->assertSame(3, (int) $sale->qty_sold);
        $this->assertEquals(75.0, (float) $sale->total_revenue);
        $this->assertEquals(9.4, (float) $this->rice->fresh()->quantity_on_hand);   // 3 x 0.2 kg
    }

    public function test_several_dishes_in_one_go_and_the_price_can_be_changed(): void
    {
        $this->save([$this->pork->id => '2', $this->chicken->id => '1'], [$this->chicken->id => '20.00'])
            ->assertSessionHas('success', '2 sales logged.');

        $this->assertSame(2, Sale::count());
        $this->assertEquals(20.0, (float) Sale::where('recipe_id', $this->chicken->id)->value('total_revenue'));
        $this->assertEquals(9.4, (float) $this->rice->fresh()->quantity_on_hand);   // 3 dishes x 0.2 kg
    }

    public function test_an_all_zero_sheet_logs_nothing(): void
    {
        $this->save([$this->pork->id => '0', $this->chicken->id => ''])
            ->assertSessionHasErrorsIn('sheet', 'qty');

        $this->assertSame(0, Sale::count());
        $this->assertEquals(10.0, (float) $this->rice->fresh()->quantity_on_hand);
    }

    public function test_half_a_dish_is_refused_rather_than_rounded(): void
    {
        $this->save([$this->pork->id => '1.5'])->assertSessionHasErrorsIn('sheet', 'qty.' . $this->pork->id);

        $this->assertSame(0, Sale::count());
    }

    public function test_a_dish_that_does_not_exist_is_refused(): void
    {
        $this->save([999999 => '1'], [999999 => '5'])->assertSessionHasErrorsIn('sheet', 'recipe_ids.0');

        $this->assertSame(0, Sale::count());
    }

    public function test_an_open_order_still_goes_through_the_pop_up(): void
    {
        $this->actingAs($this->manager())
            ->from(route('sales.index', ['month' => '2026-09']))
            ->post(route('sales.store'), [
                'is_open_order' => '1',
                'item_name'     => 'Fried rice with egg',
                'qty_sold'      => 1,
                'selling_price' => '8.00',
                'sale_date'     => '2026-09-11',
                'discount'      => '3',
            ])
            ->assertRedirect(route('sales.index', ['month' => '2026-09']));

        $this->assertTrue((bool) Sale::sole()->is_open_order);
        $this->assertEquals(10.0, (float) $this->rice->fresh()->quantity_on_hand);
    }

    public function test_a_junior_chef_cannot_save_the_sheet(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]))
            ->post(route('sales.sheet'), ['sale_date' => '2026-09-11', 'qty' => [$this->pork->id => 5], 'price' => [$this->pork->id => 25]])
            ->assertForbidden();

        $this->assertSame(0, Sale::count());
    }

    public function test_the_sheet_script_parses(): void
    {
        if (! shell_exec('node --version 2>&1')) {
            $this->markTestSkipped('node is not on PATH.');
        }

        $html = $this->actingAs($this->manager())->get(route('sales.index'))->getContent();
        preg_match_all('#<script>(.*?)</script>#s', $html, $m);
        $script = collect($m[1])->first(fn ($s) => str_contains($s, 'sheet-qty'));
        $this->assertNotNull($script);

        $file = tempnam(sys_get_temp_dir(), 'sheet') . '.js';
        file_put_contents($file, $script);
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $out, $status);
        @unlink($file);

        $this->assertSame(0, $status, implode(' | ', $out));
    }
}
