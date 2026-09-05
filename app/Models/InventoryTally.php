<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTally extends Model
{
    use LogsActivity;

    protected $fillable = [
        'counted_by',
        'counted_on',
        'note',
    ];

    protected $casts = [
        'counted_on' => 'date',
    ];

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InventoryTallyLine::class);
    }
}
