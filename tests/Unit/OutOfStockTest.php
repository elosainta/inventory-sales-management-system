<?php

namespace Tests\Unit;

use App\Models\InventoryItem;
use PHPUnit\Framework\TestCase;

/**
 * An item flags when it has run out — not when it dips under the limit column.
 * That column is how much the kitchen holds at most, not a floor to stay above.
 */
class OutOfStockTest extends TestCase
{
    private function item(float $onHand, float $limit): InventoryItem
    {
        $item = new InventoryItem();
        $item->quantity_on_hand  = $onHand;
        $item->reorder_threshold = $limit;

        return $item;
    }

    public function test_an_empty_shelf_flags(): void
    {
        $this->assertTrue($this->item(0, 10)->isOutOfStock());
    }

    public function test_anything_left_does_not_flag(): void
    {
        $this->assertFalse($this->item(0.25, 10)->isOutOfStock());
        $this->assertFalse($this->item(4, 10)->isOutOfStock());
    }
}
