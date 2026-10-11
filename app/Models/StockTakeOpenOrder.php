<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeOpenOrder extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'stock_take_id',
        'item_name',
        'quantity',
        'note',
    ];

    /** @return BelongsTo<StockTake, $this> */
    public function stockTake(): BelongsTo
    {
        return $this->belongsTo(StockTake::class);
    }
}
