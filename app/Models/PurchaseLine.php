<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class PurchaseLine extends Model
{
    use LogsActivity;

    protected $fillable = [
        'purchase_id',
        'inventory_item_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}