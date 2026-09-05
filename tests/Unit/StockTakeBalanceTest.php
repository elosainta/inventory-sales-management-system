<?php

namespace Tests\Unit;

use App\Models\StockTakeEntry;
use PHPUnit\Framework\TestCase;

/**
 * The arithmetic behind the Current stock | In | Out | Balance sheet. Both the
 * form preview and the controller write go through these, so a change that
 * breaks the identity breaks here first.
 */
class StockTakeBalanceTest extends TestCase
{
    public function test_stock_in_adds_and_stock_out_takes_off(): void
    {
        $this->assertSame(47.0, StockTakeEntry::balance(40, 12, 5));
    }

    public function test_a_line_that_moved_nothing_leaves_the_shelf_alone(): void
    {
        $this->assertSame(40.0, StockTakeEntry::balance(40));
    }

    /**
     * An Out bigger than what is on hand means the figure inventory carried was
     * already wrong. Stock stops at zero rather than going negative.
     */
    public function test_taking_out_more_than_is_there_stops_at_zero(): void
    {
        $this->assertSame(0.0, StockTakeEntry::balance(10, 0, 25));
    }

    public function test_fractional_quantities_land_on_two_places(): void
    {
        $this->assertSame(9.0, StockTakeEntry::balance(8.5, 2.25, 1.75));
    }

    /**
     * The bug this guards: two catalog lines pointing at one inventory item.
     * Applied one at a time the second write would overwrite the first.
     */
    public function test_two_lines_on_the_same_stock_are_netted_not_overwritten(): void
    {
        $net = StockTakeEntry::netMovement([
            ['inventory_item_id' => 7, 'qty_in' => 10, 'qty_out' => 0],
            ['inventory_item_id' => 7, 'qty_in' => 0,  'qty_out' => 4],
            ['inventory_item_id' => 9, 'qty_in' => 2,  'qty_out' => 0],
        ]);

        $this->assertSame([7 => 6.0, 9 => 2.0], $net);
    }

    public function test_count_only_lines_move_no_stock(): void
    {
        $net = StockTakeEntry::netMovement([
            ['inventory_item_id' => null, 'qty_in' => 10, 'qty_out' => 3],
            ['qty_in' => 5, 'qty_out' => 1],
        ]);

        $this->assertSame([], $net);
    }

    public function test_blank_columns_are_treated_as_no_movement(): void
    {
        $net = StockTakeEntry::netMovement([
            ['inventory_item_id' => 3, 'qty_in' => null, 'qty_out' => '2.5'],
        ]);

        $this->assertSame([3 => -2.5], $net);
    }
}
