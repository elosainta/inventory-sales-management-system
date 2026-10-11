<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockTake extends Model
{
    use LogsActivity;

    protected $fillable = [
        'section',
        'taken_on',
        'counted_by',
        'note',
    ];

    protected $casts = [
        'taken_on' => 'date',
    ];

    /** @return BelongsTo<User, $this> */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /** @return HasMany<StockTakeEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(StockTakeEntry::class);
    }

    /** @return HasMany<StockTakeOpenOrder, $this> */
    public function openOrders(): HasMany
    {
        return $this->hasMany(StockTakeOpenOrder::class);
    }

    public function sectionLabel(): string
    {
        return StockTakeItem::SECTIONS[$this->section] ?? ucfirst($this->section);
    }
}
