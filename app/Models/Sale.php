<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use LogsActivity;

    protected $fillable = [
        'recipe_id',
        'item_name',
        'is_open_order',
        'qty_sold',
        'selling_price',
        'discount',
        'total_revenue',
        'sale_date',
    ];

    protected $casts = [
        'sale_date'     => 'datetime',
        'is_open_order' => 'boolean',
    ];

    /**
     * What actually lands in the till: gross less the discount, never negative.
     * Both the log and the edit path go through here so the two can't drift.
     */
    public static function revenue(int|float $qty, int|float $price, int|float $discount = 0): float
    {
        return max(0, round($qty * $price - $discount, 2));
    }

    /**
     * What to call this line on screen. An open order is off-menu food keyed
     * in by hand, so it has no recipe — item_name is the only name it has.
     */
    public function getLabelAttribute(): string
    {
        return $this->recipe?->name ?? $this->item_name ?? 'Deleted recipe';
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function attachments()
    {
        return $this->hasMany(SaleAttachment::class);
    }

}