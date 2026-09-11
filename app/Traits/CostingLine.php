<?php

namespace App\Traits;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ingredient line on a costing sheet - RndEntryLine or StaffMealLine,
 * which were the same model twice. The model declares only its parent key
 * and relation.
 *
 * Child detail, so not audited in its own right - the sheet is. `item` and
 * `unit` are snapshots of the ingredient at the time, so a renamed or deleted
 * one still reads correctly on an old sheet.
 */
trait CostingLine
{
    public function initializeCostingLine(): void
    {
        $this->mergeFillable(['inventory_item_id', 'item', 'unit', 'quantity', 'unit_price']);
        $this->mergeCasts(['quantity' => 'decimal:4', 'unit_price' => 'decimal:2']);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /** Derived, never stored - see Rules in CLAUDE.md. */
    public function getTotalAttribute(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
