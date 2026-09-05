<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBatch extends Model
{
    use LogsActivity;

    protected $fillable = [
        'user_id',
        'recipe_id',
        'quantity_produced',
        'produced_by',
        'production_date',
        'total_value',
        'notes',
    ];

    protected $casts = [
        'production_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The dish that was cooked. Null on batches logged before the pipeline. */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionBatchLine::class);
    }
}
