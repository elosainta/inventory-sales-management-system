<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two changes that are invisible until someone is standing in a kitchen with a
 * phone: ingredients are chosen by typing rather than by scrolling a hundred
 * <option>s, and a Head Chef can reach the section editor at all.
 */
class ItemPickerAndSectionAccessTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $name = 'Oyster sauce'): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name, 'category' => 'Sauce', 'unit' => 'unit',
            'quantity_on_hand' => 5, 'unit_cost' => 11.7,
        ]);
    }

    public function test_purchase_line_items_offer_a_searchable_picker_not_a_dropdown(): void
    {
        $this->item();

        $page = $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('purchases.index'))
            ->assertOk();

        // The datalist is what makes it searchable; the old long <select> is gone.
        $page->assertSee('id="inventory-options"', false);
        $page->assertSee('class="item-picker"', false);
        $page->assertDontSee('<option value="">Select ingredient</option>', false);
    }

    public function test_the_picker_knows_every_inventory_item_by_name_and_unit(): void
    {
        $this->item();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER]))
            ->get(route('market-purchases.index'))
            ->assertOk()
            // Fed to the picker as data, so the browser can filter it as you type.
            ->assertSee('"name":"Oyster sauce","unit":"unit"', false);
    }

    public function test_a_head_chef_can_edit_sections_and_reaches_them_from_prep_overview(): void
    {
        $headChef = User::factory()->create(['role' => User::ROLE_HEAD_CHEF]);

        $this->actingAs($headChef)->get(route('sections.index'))->assertOk();
        $this->actingAs($headChef)->get(route('prep.overview'))
            ->assertOk()
            ->assertSee('Edit sections &amp; tasks', false);
    }

    public function test_a_junior_chef_still_cannot_edit_sections(): void
    {
        $junior = User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);

        $this->actingAs($junior)->get(route('sections.index'))->assertForbidden();
    }
}
