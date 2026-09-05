<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Adding an ingredient without leaving the purchase you are in the middle of.
 *
 * The line-item picker searches inventory as you type. When nothing matches,
 * it offers to create the ingredient there and then — which only works if
 * inventory.store answers XHR with the new item instead of a redirect. A
 * redirect would throw away the half-filled purchase, which is the whole
 * reason the chef was not sent to the Inventory page in the first place.
 */
class InlineIngredientCreationTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        return User::factory()->create(['role' => User::ROLE_HEAD_CHEF, 'is_demo' => false]);
    }

    public function test_it_returns_the_new_item_rather_than_a_redirect(): void
    {
        $response = $this->actingAs($this->manager())
            ->postJson(route('inventory.store'), [
                'name'              => 'Kaffir Lime Leaf',
                'category'          => 'Produce',
                'unit'              => 'g',
                'quantity_on_hand'  => 0,
                'reorder_threshold' => 0,
                'unit_cost'         => 0,
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'name', 'unit', 'cost']);

        $item = InventoryItem::findOrFail($response->json('id'));

        // Zero on both, on purpose: the purchase being written is what puts the
        // first of it on the shelf, and LogPurchase writes quantity and cost
        // when the form is submitted. Seeding either here would double it.
        $this->assertSame('Kaffir Lime Leaf', $item->name);
        $this->assertEquals(0, $item->quantity_on_hand);
        $this->assertEquals(0, $item->unit_cost);
    }

    public function test_a_bad_unit_is_still_rejected(): void
    {
        // The panel offers only InventoryItem::UNITS, but the panel is not the
        // boundary — the validator is, and it now reads the same constant.
        $this->actingAs($this->manager())
            ->postJson(route('inventory.store'), [
                'name'              => 'Mystery',
                'category'          => 'Produce',
                'unit'              => 'bushel',
                'quantity_on_hand'  => 0,
                'reorder_threshold' => 0,
                'unit_cost'         => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unit');
    }

    public function test_a_junior_chef_cannot_create_one(): void
    {
        // record-inventory is what the panel is gated on in the view. Asserted
        // here on the route as well, because a view is not a boundary either.
        $this->actingAs(User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]))
            ->postJson(route('inventory.store'), [
                'name'              => 'Contraband',
                'category'          => 'Produce',
                'unit'              => 'kg',
                'quantity_on_hand'  => 0,
                'reorder_threshold' => 0,
                'unit_cost'         => 0,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('inventory_items', ['name' => 'Contraband']);
    }

    public function test_the_purchase_form_renders_the_panel_for_a_manager(): void
    {
        $this->actingAs($this->manager())
            ->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('Not in inventory yet', false)
            ->assertSee('new-item-add', false);
    }

    public function test_the_rendered_line_item_script_parses(): void
    {
        // The row editor builds each row with `row.innerHTML =` and a template
        // literal, and the panel added above is Blade-rendered markup dropped
        // straight into that literal. A backtick or a ${ coming out of Blade
        // would end the literal early and take the rest of the script with it,
        // and nothing on the server would notice — the page still renders 200.
        if (! shell_exec('node --version 2>&1')) {
            $this->markTestSkipped('node is not on PATH.');
        }

        $html = $this->actingAs($this->manager())
            ->get(route('purchases.index'))
            ->assertOk()
            ->getContent();

        preg_match_all('#<script>(.*?)</script>#s', $html, $matches);
        $script = collect($matches[1])->first(fn ($s) => str_contains($s, 'addLineRow'));

        $this->assertNotNull($script, 'The line-item script was not rendered at all.');

        $file = tempnam(sys_get_temp_dir(), 'lineitems') . '.js';
        file_put_contents($file, $script);
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        @unlink($file);

        $this->assertSame(0, $status, 'Rendered line-item script is not valid JS: ' . implode(' | ', $output));
    }
}
