<?php

namespace Tests\Feature;

use App\Domain\Production\Actions\LogProduction;
use App\Models\InventoryItem;
use App\Models\ProductionBatch;
use App\Models\ProductionBatchLine;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * MariaDB 12 refuses a write that clashes with another transaction ("1020
 * Record has changed since last read") instead of waiting. A junior chef lost a
 * production batch to it on 2026-09-12. The stock-moving transactions now take
 * three attempts.
 *
 * DatabaseMigrations, not RefreshDatabase: RefreshDatabase wraps every test in a
 * transaction, and Laravel never retries a nested one, so the retry could not
 * happen here at all.
 */
class TransactionRetryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_a_write_clash_while_logging_production_is_retried_not_lost(): void
    {
        $beef   = InventoryItem::create(['name' => 'Chuck tender', 'category' => 'Meat', 'unit' => 'kg', 'quantity_on_hand' => 5, 'unit_cost' => 40]);
        $recipe = Recipe::create(['name' => 'beef taco', 'selling_price' => 30]);
        RecipeIngredient::create(['recipe_id' => $recipe->id, 'inventory_item_id' => $beef->id, 'quantity' => 0.3]);

        // The first line write fails exactly as MariaDB failed it on production.
        $clashed = false;
        ProductionBatchLine::creating(function () use (&$clashed) {
            if (! $clashed) {
                $clashed = true;
                throw new \RuntimeException("SQLSTATE[HY000]: General error: 1020 Record has changed since last read in table 'production_batch_lines'; try restarting transaction");
            }
        });

        app(LogProduction::class)->execute([
            'recipe_id'         => $recipe->id,
            'quantity_produced' => 2,
            'produced_by'       => 'Test cook',
            'production_date'   => '2026-09-12',
        ]);

        $this->assertTrue($clashed, 'The first attempt should have hit the clash.');
        $this->assertSame(1, ProductionBatch::count());
        $this->assertSame(1, ProductionBatchLine::count());
        // Taken off once: 5 - 0.3 x 2. Twice would read 3.8.
        $this->assertEquals(4.4, (float) $beef->fresh()->quantity_on_hand);
    }
}
