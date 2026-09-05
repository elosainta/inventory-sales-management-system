<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\StockTakeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The count sheet is a curated shortlist, so anything nobody thought to add to
 * it used to be uncountable: a chef had to find a manager to edit the catalog on
 * another page. A chef can now put any inventory item on today's sheet, and the
 * point of that is that the row MOVES STOCK — if it only recorded a note, the
 * bug would still be there wearing a nicer hat.
 */
class StockTakeAddFromInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function chef(): User
    {
        return User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);
    }

    private function springOnion(float $onHand = 4.0): InventoryItem
    {
        return InventoryItem::create([
            'name'             => 'Spring onion',
            'category'         => 'Vegetables',
            'unit'             => 'kg',
            'quantity_on_hand' => $onHand,
            'unit_cost'        => 8.00,
        ]);
    }

    /**
     * The count sheet renders a JSON payload of the whole inventory into an
     * inline <script>. `php artisan view:cache` writes the compiled template
     * without parsing it, so a Blade slip there compiles "successfully" and
     * then 500s in the kitchen's face. Only actually requesting the page
     * catches it — which is why this test exists.
     */
    public function test_the_count_sheet_renders(): void
    {
        $this->springOnion();

        $this->actingAs($this->chef())
            ->get(route('stock-take.create'))
            ->assertOk()
            ->assertSee('Spring onion');
    }

    public function test_a_chef_can_count_an_item_that_is_not_on_the_sheet_and_it_moves_stock(): void
    {
        $item = $this->springOnion(4.0);

        $this->actingAs($this->chef())->post(route('stock-take.store'), [
            'section'  => StockTakeItem::SECTION_PANTRY,
            'taken_on' => now()->toDateString(),
            'entries'  => [
                ['inventory_item_id' => $item->id, 'qty_in' => 3, 'qty_out' => 1],
            ],
        ])->assertRedirect();

        // 4 + 3 - 1. The whole point: a picked row is not a count-only note.
        $this->assertEquals(6.0, $item->fresh()->quantity_on_hand);
    }

    public function test_counting_it_puts_it_on_the_sheet_for_next_time(): void
    {
        $item = $this->springOnion();

        $this->actingAs($this->chef())->post(route('stock-take.store'), [
            'section'  => StockTakeItem::SECTION_PANTRY,
            'taken_on' => now()->toDateString(),
            'entries'  => [['inventory_item_id' => $item->id, 'qty_in' => 2]],
        ])->assertRedirect();

        $catalog = StockTakeItem::firstWhere('name', 'Spring onion');

        $this->assertNotNull($catalog, 'the picked item should join the catalog');
        $this->assertSame($item->id, $catalog->inventory_item_id, 'and be linked, or it moves nothing');
        $this->assertSame(StockTakeItem::SECTION_PANTRY, $catalog->section);
    }

    public function test_counting_the_same_item_twice_does_not_duplicate_the_catalog_row(): void
    {
        $item = $this->springOnion();

        foreach ([2, 5] as $qty) {
            $this->actingAs($this->chef())->post(route('stock-take.store'), [
                'section'  => StockTakeItem::SECTION_PANTRY,
                'taken_on' => now()->toDateString(),
                'entries'  => [['inventory_item_id' => $item->id, 'qty_in' => $qty]],
            ])->assertRedirect();
        }

        $this->assertSame(1, StockTakeItem::where('name', 'Spring onion')->count());
    }

    public function test_an_existing_unlinked_catalog_row_gets_linked_rather_than_duplicated(): void
    {
        // The catalog carries names inventory has never heard of. If one later
        // turns out to BE an inventory item, counting it should wire the two
        // together instead of leaving a second row that moves nothing.
        $item = $this->springOnion(10.0);
        StockTakeItem::create([
            'section' => StockTakeItem::SECTION_PANTRY,
            'name'    => 'Spring onion',
        ]);

        $this->actingAs($this->chef())->post(route('stock-take.store'), [
            'section'  => StockTakeItem::SECTION_PANTRY,
            'taken_on' => now()->toDateString(),
            'entries'  => [['inventory_item_id' => $item->id, 'qty_out' => 4]],
        ])->assertRedirect();

        $this->assertSame(1, StockTakeItem::where('name', 'Spring onion')->count());
        $this->assertEquals(6.0, $item->fresh()->quantity_on_hand);
    }

    public function test_the_owner_still_cannot_record_a_sheet(): void
    {
        // record-stock-take is !isAdmin && !isOwner — the owner reviews counts,
        // and this new path must not become a way around that.
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $item  = $this->springOnion(4.0);

        $this->actingAs($owner)->post(route('stock-take.store'), [
            'section'  => StockTakeItem::SECTION_PANTRY,
            'taken_on' => now()->toDateString(),
            'entries'  => [['inventory_item_id' => $item->id, 'qty_in' => 3]],
        ])->assertForbidden();

        $this->assertEquals(4.0, $item->fresh()->quantity_on_hand);
    }

    public function test_the_sheet_starts_empty_rather_than_printing_the_catalog(): void
    {
        // Pantry is inventory. The sheet used to print every catalog line, so a
        // chef counting the four things that moved scrolled past fifty-three
        // that did not. Nothing is pre-printed now — the search puts rows on it.
        $this->springOnion();

        StockTakeItem::create([
            'section'    => StockTakeItem::SECTION_PANTRY,
            'name'       => 'Oyster sauce',
            'sort_order' => 1,
        ]);

        $html = $this->actingAs($this->chef())
            ->get(route('stock-take.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('entries[0][stock_take_item_id]', $html);
        $this->assertStringContainsString('id="entry-rows"></tbody>', $html);

        // Both are still reachable: inventory to be counted, and the catalog
        // line nothing backs, which records a count and moves no stock.
        $this->assertStringContainsString('Spring onion', $html);
        $this->assertStringContainsString('Oyster sauce', $html);
    }

    public function test_the_rendered_sheet_script_parses(): void
    {
        // The sheet builds its rows with `row.innerHTML =` and template
        // literals, and the payload is Blade output dropped into the same
        // <script>. A backtick or a ${ out of Blade would end a literal early
        // and silently kill the rest of the script on a page that still
        // returns 200 — so an ingredient named with one must not break it.
        if (! shell_exec('node --version 2>&1')) {
            $this->markTestSkipped('node is not on PATH.');
        }

        InventoryItem::create([
            'name'             => 'Back`tick ${danger} </script> onion',
            'category'         => 'Vegetables',
            'unit'             => 'kg',
            'quantity_on_hand' => 1,
            'unit_cost'        => 1,
        ]);

        $html = $this->actingAs($this->chef())
            ->get(route('stock-take.create'))
            ->assertOk()
            ->getContent();

        preg_match_all('#<script>(.*?)</script>#s', $html, $matches);
        $script = collect($matches[1])->first(fn ($s) => str_contains($s, 'addPickedRow'));

        $this->assertNotNull($script, 'The count-sheet script was not rendered at all.');

        $file = tempnam(sys_get_temp_dir(), 'stocktake') . '.js';
        file_put_contents($file, $script);
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        @unlink($file);

        $this->assertSame(0, $status, 'Rendered count-sheet script is not valid JS: ' . implode(' | ', $output));
    }
}
