<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\RndEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R&D in global search.
 *
 * SearchController only queries buckets the searcher can view, and this one is
 * behind `view-rnd`. Ingredients are matched on the LINE's snapshotted name,
 * not the live inventory one, so a trial stays findable by what an ingredient
 * was called when it was recorded — which is also what the results list
 * renders.
 */
class GlobalSearchRndTest extends TestCase
{
    use RefreshDatabase;

    private function entry(array $overrides = []): RndEntry
    {
        $item = InventoryItem::create([
            'name'             => 'Yuzu koshō',
            'category'         => 'Pantry',
            'unit'             => 'kg',
            'quantity_on_hand' => 10,
            'unit_cost'        => 18.50,
        ]);

        $entry = RndEntry::create(array_merge([
            'menu_name'      => 'Yuzu dressing',
            'serving_size'   => 1,
            'misc_percent'   => 30,
            'remark'         => 'Trying it in the dressing',
            'purchased_on'   => now()->toDateString(),
            'status'         => RndEntry::STATUS_PENDING,
        ], $overrides));

        $entry->lines()->create([
            'inventory_item_id' => $item->id,
            'item'              => $item->name,
            'unit'              => $item->unit,
            'quantity'          => 2,
            'unit_price'        => 18.50,
        ]);

        return $entry->load('lines');
    }

    public function test_a_manager_finds_one_by_ingredient_menu_or_remark(): void
    {
        $this->entry();
        $chef = User::factory()->create(['role' => User::ROLE_HEAD_CHEF, 'is_demo' => false]);

        foreach (['Yuzu koshō', 'Yuzu dressing', 'Trying it'] as $term) {
            $this->actingAs($chef)
                ->get(route('search.index', ['q' => $term]))
                ->assertOk()
                ->assertSee('Yuzu dressing', false);
        }
    }

    public function test_it_stays_findable_under_the_name_the_ingredient_was_recorded_with(): void
    {
        // The snapshot is the point: renaming the ingredient must not lose the
        // spending history filed under the old name.
        $entry = $this->entry();
        $entry->lines->first()->inventoryItem->update(['name' => 'Yuzu paste']);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_OWNER, 'is_demo' => false]))
            ->get(route('search.index', ['q' => 'koshō']))
            ->assertOk()
            ->assertSee('Yuzu koshō', false);
    }

    public function test_admin_gets_the_bucket_too(): void
    {
        $this->entry();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN, 'is_demo' => false]))
            ->get(route('search.index', ['q' => 'Yuzu']))
            ->assertOk()
            ->assertSee('Yuzu dressing', false);
    }

    public function test_a_part_timer_cannot_search_at_all(): void
    {
        // search-global already excludes them, so the bucket never runs — but
        // asserted here so widening that gate later cannot quietly hand a part
        // timer a list of what things cost.
        $this->entry();

        $this->actingAs(User::factory()->create(['role' => User::ROLE_PART_TIMER, 'is_demo' => false]))
            ->get(route('search.index', ['q' => 'Yuzu']))
            ->assertForbidden();
    }
}
