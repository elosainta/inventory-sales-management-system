<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

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

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }

}