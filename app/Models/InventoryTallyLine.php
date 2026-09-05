<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTallyLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inventory_item_id',
        'item_name',
        'unit',
        'category',
        'system_quantity',
        'counted_quantity',
    ];

    protected $casts = [
        'system_quantity'  => 'decimal:2',
        'counted_quantity' => 'decimal:2',
    ];

    public function tally(): BelongsTo
    {
        return $this->belongsTo(InventoryTally::class, 'inventory_tally_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /**
     * Counted minus system, at the moment of counting. Null when either figure
     * is missing (e.g. an ad-hoc item that was never in live inventory).
     */
    public function variance(): ?float
    {
        if ($this->counted_quantity === null || $this->system_quantity === null) {
            return null;
        }

        return (float) $this->counted_quantity - (float) $this->system_quantity;
    }
}
