<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ingredient line on an R&D costing sheet.
 *
 * Child detail, so it is not audited in its own right — the sheet is (see
 * RndEntry). `item` and `unit` are snapshots of the ingredient at the time, so
 * a renamed or deleted one still reads correctly on an old sheet.
 */
class RndEntryLine extends Model
{
    protected $fillable = [
        'rnd_entry_id',
        'inventory_item_id',
        'item',
        'unit',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'quantity'   => 'decimal:4',
        'unit_price' => 'decimal:2',
    ];

    public function rndEntry(): BelongsTo
    {
        return $this->belongsTo(RndEntry::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /** Derived, never stored — see Rules in CLAUDE.md. */
    public function getTotalAttribute(): float
    {
        return round((float) $this->quantity * (float) $this->unit_price, 2);
    }
}
