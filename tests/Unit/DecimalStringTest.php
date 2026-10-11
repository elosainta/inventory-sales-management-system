<?php

namespace Tests\Unit;

use App\Models\InventoryItem;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stock arithmetic in float can leave a leftover like 1.1E-16, which PHP
 * writes in exponent form — and bcmath refuses that outright. Found by
 * Larastan on 2026-10-11: a 0.90 kg shelf, a dish using 0.3 kg, three sold.
 */
class DecimalStringTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_float_leftover_does_not_break_saving_stock(): void
    {
        $item = InventoryItem::create(['name' => 'Ayam', 'category' => 'Meat', 'unit' => 'kg', 'quantity_on_hand' => '0.90', 'unit_cost' => '12.00']);

        // What LogSale does: shelf minus recipe quantity x dishes sold.
        $item->quantity_on_hand = max(0, $item->quantity_on_hand - 0.3 * 3);
        $item->save();

        $this->assertEquals(0, $item->fresh()->quantity_on_hand);
        $this->assertEquals(0, $item->fresh()->monetary_value);
    }

    public function test_decimal_never_writes_an_exponent(): void
    {
        $this->assertSame('0.000000', Money::decimal(0.90 - 0.3 * 3));
        $this->assertSame('1000000000000000.000000', Money::decimal(1e15));
        $this->assertSame('2.65', Money::decimal('2.65'));
        $this->assertSame('3', Money::decimal(3));
    }
}
