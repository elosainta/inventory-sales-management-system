<?php

namespace App\Models;

use App\Traits\CostingLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One ingredient line on a staff meal sheet. The rest is App\Traits\CostingLine. */
class StaffMealLine extends Model
{
    use CostingLine;

    protected $fillable = ['staff_meal_id'];

    public function staffMeal(): BelongsTo
    {
        return $this->belongsTo(StaffMeal::class);
    }
}
