<?php

namespace Tests\Feature;

use App\Domain\Purchasing\Actions\LogMarketPurchase;
use App\Domain\Purchasing\Actions\LogPurchase;
use App\Models\InventoryItem;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A delivery is bought by the pack and cooked with by the piece. These pin the
 * bridge between the two, because getting it wrong is not a rounding error: a
 * carton price left on a kilo unit put RM 13,580 of stock that did not exist on
 * the Inventory page and made six dishes read a loss.
 */
class PackSizePurchaseTest extends TestCase
{
    use RefreshDatabase;

    private function sourdough(array $overrides = []): InventoryItem
    {
        return InventoryItem::create(array_merge([
            'name'              => 'SOURDOUGH',
            'category'          => 'Pantry',
            'unit'              => 'slices',
            'quantity_on_hand'  => 0,
            'reorder_threshold' => 0,
            'unit_cost'         => 0,
            'pack_size'         => 8,
        ], $overrides));
    }

    private function buy(InventoryItem $item, string $quantity, string $price): void
    {
        $supplier = Supplier::create([
            'name' => 'COOPERATIVE', 'contact' => '', 'email' => '', 'address' => '',
        ]);

        app(LogPurchase::class)->execute([
            'supplier_id'   => $supplier->id,
            'purchase_date' => now()->toDateString(),
            'status'        => 'completed',
            'user_id'       => User::factory()->create()->id,
            'lines'         => [[
                'inventory_item_id' => $item->id,
                'quantity'          => $quantity,
                'unit_price'        => $price,
            ]],
        ]);
    }

    public function test_buying_six_loaves_puts_slices_on_the_shelf_at_the_slice_price(): void
    {
        $bread = $this->sourdough();

        // The invoice reads "**COUNTRY SOURDOUGH LOAF  6 @ 14.00".
        $this->buy($bread, '6', '14.00');

        $bread->refresh();

        $this->assertEquals('48.00', $bread->quantity_on_hand, 'six loaves of eight');
        $this->assertEquals('1.75', $bread->unit_cost, 'RM 14 a loaf over eight slices');
        $this->assertEquals('84.00', $bread->monetary_value, 'and the shelf is worth what was paid');
    }

    public function test_an_item_with_no_pack_size_is_taken_in_exactly_as_keyed(): void
    {
        $tomatoes = $this->sourdough([
            'name' => 'TOMATO', 'unit' => 'kg', 'pack_size' => null,
        ]);

        $this->buy($tomatoes, '3', '12.00');

        $tomatoes->refresh();

        $this->assertEquals('3.00', $tomatoes->quantity_on_hand);
        $this->assertEquals('12.00', $tomatoes->unit_cost);
    }

    public function test_a_pack_size_of_one_is_not_treated_as_a_pack(): void
    {
        $oil = $this->sourdough(['name' => 'OLIVE OIL', 'unit' => 'btl', 'pack_size' => 1]);

        $this->buy($oil, '2', '25.00');

        $oil->refresh();

        $this->assertEquals('2.00', $oil->quantity_on_hand);
        $this->assertEquals('25.00', $oil->unit_cost);
    }

    public function test_the_price_lands_on_the_sen_rounded_half_up(): void
    {
        // RM 10 over 3 is 3.333..., and RM 1 over 8 is exactly 0.125 - the half
        // that has to go up. Truncating is what left eggs at a single sen.
        $thirds = $this->sourdough(['name' => 'THIRDS', 'pack_size' => 3]);
        $halves = $this->sourdough(['name' => 'HALVES', 'pack_size' => 8]);

        $this->buy($thirds, '1', '10.00');
        $this->buy($halves, '1', '1.00');

        $this->assertEquals('3.33', $thirds->refresh()->unit_cost);
        $this->assertEquals('0.13', $halves->refresh()->unit_cost);
    }

    public function test_a_market_purchase_applies_the_pack_the_same_way(): void
    {
        $eggs = $this->sourdough(['name' => 'EGG', 'unit' => 'pcs', 'pack_size' => 30]);

        app(LogMarketPurchase::class)->execute([
            'market_name'   => 'Central market',
            'purchase_date' => now()->toDateString(),
            'signed_by'     => 'Sam',
            'user_id'       => User::factory()->create()->id,
            'lines'         => [[
                'inventory_item_id' => $eggs->id,
                'quantity'          => '2',
                'unit_price'        => '16.80',
            ]],
        ]);

        $eggs->refresh();

        $this->assertEquals('60.00', $eggs->quantity_on_hand, 'two trays of thirty');
        $this->assertEquals('0.56', $eggs->unit_cost);
    }

    public function test_a_delivery_reprices_the_dishes_that_use_it_at_the_piece_price(): void
    {
        $bread = $this->sourdough();

        $recipe = Recipe::create([
            'name'          => 'sun rise egg',
            'serving_size'  => '1pax',
            'misc_percent'  => 30,
            'selling_price' => 18.00,
        ]);
        $recipe->ingredients()->create([
            'inventory_item_id' => $bread->id,
            'item'              => $bread->name,
            'unit'              => $bread->unit,
            'quantity'          => 1,
            'unit_price'        => 0,
        ]);

        $this->buy($bread, '6', '14.00');

        // One slice at RM 1.75 plus 30% miscellaneous, not one loaf at RM 14.
        $this->assertEquals('2.28', $recipe->refresh()->plate_cost);
    }
}
