<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WastageEntry extends Model
{
    use LogsActivity;

    protected $fillable = [
        'inventory_item_id',
        'quantity_wasted',
        'cost_lost',
        'reason',
        'recorded_date',
    ];

    protected $casts = [
        'recorded_date' => 'datetime',
    ];

    /** @return BelongsTo<InventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
