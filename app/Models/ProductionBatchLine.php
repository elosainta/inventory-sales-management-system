<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBatchLine extends Model
{
    protected $fillable = [
        'production_batch_id',
        'inventory_item_id',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    /** @return BelongsTo<ProductionBatch, $this> */

    /** @return BelongsTo<ProductionBatch, $this> */
    public function productionBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class);
    }

    /** @return BelongsTo<InventoryItem, $this> */

    /** @return BelongsTo<InventoryItem, $this> */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
