<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPurchaseLine extends Model
{
    protected $fillable = [
        'market_purchase_id',
        'inventory_item_id',
        'quantity',
        'unit_price',
        'line_total',
    ];

    /** @return BelongsTo<MarketPurchase, $this> */

    /** @return BelongsTo<MarketPurchase, $this> */
    public function marketPurchase(): BelongsTo
    {
        return $this->belongsTo(MarketPurchase::class);
    }

    /** @return BelongsTo<InventoryItem, $this> */

    /** @return BelongsTo<InventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
