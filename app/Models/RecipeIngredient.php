<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class RecipeIngredient extends Model
{
    use LogsActivity;

    protected $fillable = [
        'recipe_id',
        'inventory_item_id',
        'quantity',
    ];

    public function recipe()
    {
        return $this->belongsTo(Recipe::class);
    }

    public function inventoryItem()
    {
        return $this->belongsTo(InventoryItem::class);
    }
}