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

    /** @return BelongsTo<User, $this> */

    /** @return BelongsTo<User, $this> */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /** @return HasMany<InventoryTallyLine, $this> */

    /** @return HasMany<InventoryTallyLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryTallyLine::class);
    }
}
