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

    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(StockTakeEntry::class);
    }

    public function openOrders(): HasMany
    {
        return $this->hasMany(StockTakeOpenOrder::class);
    }

    public function sectionLabel(): string
    {
        return StockTakeItem::SECTIONS[$this->section] ?? ucfirst($this->section);
    }
}
