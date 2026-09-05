<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tally sheet used to print all 237 inventory items across eight category
 * cards, so counting one shelf meant scrolling past seven of them. It starts
 * empty now and a search box puts rows on it.
 *
 * A tally is still a full physical count when it needs to be — the category
 * buttons are what keeps that possible, and they are the half of this change
 * that is easy to drop by accident. Search is for a handful; a category is for
 * a shelf.
 */
class TallySheetSearchTest extends TestCase
{
    use RefreshDatabase;

    private function chef(): User
    {
        return User::factory()->create(['role' => User::ROLE_JUNIOR_CHEF]);
    }

    private function item(string $name, string $category = 'Produce'): InventoryItem
    {
        return InventoryItem::create([
            'name'             => $name,
            'category'         => $category,
            'unit'             => 'kg',
            'quantity_on_hand' => 3,
            'unit_cost'        => 5.00,
        ]);
    }

    public function test_the_sheet_starts_empty_rather_than_printing_every_item(): void
    {
        $this->item('Spring onion');
        $this->item('Beef shin', 'Meat');

        $html = $this->actingAs($this->chef())
            ->get(route('tally.create'))
            ->assertOk()
            ->getContent();

        // No pre-printed rows...
        $this->assertStringNotContainsString('lines[0][inventory_item_id]', $html);
        $this->assertStringContainsString('id="tally-rows"></tbody>', $html);

        // ...but everything is still reachable through the search payload.
        $this->assertStringContainsString('Spring onion', $html);
        $this->assertStringContainsString('Beef shin', $html);
    }

    public function test_a_counted_line_still_reconciles_live_stock(): void
    {
        // The row the search builds carries the item id and the counted figure
        // and nothing else — name, unit, category and the system figure are all
        // read off the database at save time. This is the whole point of the
        // feature, so it is asserted against the route rather than the markup.
        $item = $this->item('Spring onion');

        $this->actingAs($this->chef())->post(route('tally.store'), [
            'counted_on' => now()->toDateString(),
            'lines'      => [
                ['inventory_item_id' => $item->id, 'counted_quantity' => 1.25],
            ],
        ])->assertRedirect();

        $this->assertEquals(1.25, $item->fresh()->quantity_on_hand);

        $line = \App\Models\InventoryTallyLine::firstOrFail();
        $this->assertSame('Spring onion', $line->item_name);
        $this->assertEquals(3, $line->system_quantity);   // snapshot taken before the reconcile
    }

    public function test_the_rendered_sheet_script_parses(): void
    {
        // The sheet builds rows with `row.innerHTML =` and template literals,
        // and both the payload and the notice strings are Blade output dropped
        // into the same <script>. A comma inside one of those strings already
        // broke this once — @json splits its argument on commas — and the page
        // 500s rather than failing quietly, but a stray backtick would not.
        if (! shell_exec('node --version 2>&1')) {
            $this->markTestSkipped('node is not on PATH.');
        }

        $this->item('Back`tick ${danger} </script> onion');

        $html = $this->actingAs($this->chef())
            ->get(route('tally.create'))
            ->assertOk()
            ->getContent();

        preg_match_all('#<script>(.*?)</script>#s', $html, $matches);
        $script = collect($matches[1])->first(fn ($s) => str_contains($s, 'addCategory'));

        $this->assertNotNull($script, 'The tally sheet script was not rendered at all.');

        $file = tempnam(sys_get_temp_dir(), 'tally') . '.js';
        file_put_contents($file, $script);
        exec('node --check ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        @unlink($file);

        $this->assertSame(0, $status, 'Rendered tally script is not valid JS: ' . implode(' | ', $output));
    }
}
